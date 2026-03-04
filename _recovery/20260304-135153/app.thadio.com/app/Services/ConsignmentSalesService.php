<?php

namespace App\Services;

use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ConsignmentSaleRepository;
use App\Repositories\ProductRepository;
use App\Support\AuditableTrait;
use PDO;

/**
 * Sync de vendas consignadas com o ledger.
 *
 * Este serviço é chamado pelo ConsignmentCreditService após gerar crédito,
 * e pelos métodos de débito quando vendas são revertidas.
 */
class ConsignmentSalesService
{
    use AuditableTrait;

    private ?PDO $pdo;
    private ConsignmentSaleRepository $sales;
    private ConsignmentProductRegistryRepository $registry;
    private ProductRepository $products;
    private ConsignmentProductStateService $stateService;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sales = new ConsignmentSaleRepository($pdo);
        $this->registry = new ConsignmentProductRegistryRepository($pdo);
        $this->products = new ProductRepository($pdo);
        $this->stateService = new ConsignmentProductStateService($pdo);
    }

    /**
     * Sync consignment sales from an order after credit generation.
     *
     * Called from ConsignmentCreditService::generateForOrder() inside its transaction.
     *
     * @param int   $orderId
     * @param array $vendorLines Array of credit line data from CreditService:
     *   [
     *     'order_item_id' => int,
     *     'product_id' => int,
     *     'supplier_pessoa_id' => int,
     *     'sold_at' => string,
     *     'unit_price' => float,
     *     'line_total' => float,
     *     'percent' => float,
     *     'credit_amount' => float,
     *     'sku' => string,
     *     'product_name' => string,
     *     'ledger_credit_movement_id' => int|null,
     *   ]
     */
    public function syncFromOrder(int $orderId, array $vendorLines): void
    {
        if (!$this->pdo || $orderId <= 0 || empty($vendorLines)) {
            return;
        }

        foreach ($vendorLines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $orderItemId = (int) ($line['order_item_id'] ?? 0);
            $supplierPessoaId = (int) ($line['supplier_pessoa_id'] ?? 0);

            if ($productId <= 0 || $orderItemId <= 0 || $supplierPessoaId <= 0) {
                continue;
            }

            // Check protection: do NOT create sale for consignacao_quitada or proprio_pos_pgto
            $product = $this->products->find($productId);
            if ($product) {
                $source = $product->source ?? '';
                $cStatus = $product->consignment_status ?? '';
                if ($source === 'consignacao_quitada' || $cStatus === 'proprio_pos_pgto') {
                    // Log audit: produto já quitado, revenda não gera comissão
                    $this->auditLog(
                        $this->pdo,
                        'consignment_sales_sync',
                        $productId,
                        'skip_quitado',
                        null,
                        ['order_id' => $orderId, 'reason' => 'Produto já quitado à fornecedora, revenda não gera comissão']
                    );
                    continue;
                }
            }

            $grossAmount = (float) ($line['unit_price'] ?? $line['line_total'] ?? 0);
            $discountAmount = (float) ($line['discount_amount'] ?? 0);
            $netAmount = (float) ($line['line_total'] ?? ($grossAmount - $discountAmount));
            $percentApplied = (float) ($line['percent'] ?? 0);
            $creditAmount = (float) ($line['credit_amount'] ?? 0);

            // 1. Create/update consignment_sales record
            $this->sales->upsert([
                'product_id' => $productId,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'supplier_pessoa_id' => $supplierPessoaId,
                'sold_at' => $line['sold_at'] ?? date('Y-m-d H:i:s'),
                'gross_amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'net_amount' => $netAmount,
                'percent_applied' => $percentApplied,
                'credit_amount' => $creditAmount,
                'commission_formula_version' => 'v1',
                'ledger_credit_movement_id' => $line['ledger_credit_movement_id'] ?? null,
                'sale_status' => 'ativa',
                'payout_status' => 'pendente',
            ]);

            // 2. Ensure registry exists
            $this->registry->upsert([
                'product_id' => $productId,
                'supplier_pessoa_id' => $supplierPessoaId,
                'consignment_supplier_original_id' => $supplierPessoaId,
                'consignment_status' => 'vendido_pendente',
                'status_changed_at' => date('Y-m-d H:i:s'),
            ]);

            // 3. Transition product state: em_estoque → vendido_pendente
            try {
                $this->stateService->transition($productId, 'vendido_pendente', [
                    'notes' => "Venda pedido #{$orderId}",
                ]);
            } catch (\InvalidArgumentException $e) {
                // May already be in vendido_pendente (idempotent)
                error_log("ConsignmentSalesService::syncFromOrder - state transition: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle a sale reversal (return, cancel, refund, etc.).
     *
     * Called from ConsignmentCreditService debit methods.
     * Implements the "Regra de Ouro" for paid items.
     *
     * @param int    $orderId
     * @param int    $orderItemId
     * @param string $eventType  'return'|'order_cancel'|'order_trash'|'order_delete'|'payment_refund'|'payment_failed'
     * @param int    $productId
     * @return array{should_debit_ledger: bool, golden_rule_applied: bool, notes: string}
     */
    public function handleReversal(int $orderId, int $orderItemId, string $eventType, int $productId): array
    {
        $result = [
            'should_debit_ledger' => true,
            'golden_rule_applied' => false,
            'notes' => '',
        ];

        if (!$this->pdo || $productId <= 0) {
            return $result;
        }

        // Find the consignment sale
        $sale = $this->sales->findByOrderItem($orderId, $orderItemId, $productId);
        if (!$sale) {
            // No consignment sale found — proceed with normal debit
            return $result;
        }

        $saleId = (int) $sale['id'];
        $payoutStatus = $sale['payout_status'] ?? 'pendente';

        if ($payoutStatus === 'pendente') {
            // ── CASE 1: Not yet paid → normal reversal ──
            // Mark sale as reversed
            $this->sales->markReversed($saleId, $eventType, "Revertido por {$eventType}");

            // Transition product back to em_estoque
            try {
                $this->stateService->transition($productId, 'em_estoque', [
                    'notes' => "Venda revertida ({$eventType}) pedido #{$orderId}",
                ]);
            } catch (\InvalidArgumentException $e) {
                error_log("ConsignmentSalesService::handleReversal - state: " . $e->getMessage());
            }

            $result['should_debit_ledger'] = true;
            $result['notes'] = "Venda revertida (não paga). Crédito debitado normalmente.";

        } elseif ($payoutStatus === 'pago') {
            // ── CASE 2: Already paid → REGRA DE OURO ──
            // Do NOT debit the supplier's ledger (no negative balance)
            $this->sales->markReversed($saleId, $eventType,
                "[REGRA DE OURO] Item pago retornou. Débito NÃO aplicado ao saldo da fornecedora."
            );

            // Transition product: vendido_pago → proprio_pos_pgto
            // This also reclassifies source to 'consignacao_quitada' via applyDetach
            try {
                $this->stateService->transition($productId, 'proprio_pos_pgto', [
                    'notes' => "[REGRA DE OURO] Produto pago retornou ao estoque como próprio. Pedido #{$orderId}, evento: {$eventType}",
                ]);
            } catch (\InvalidArgumentException $e) {
                error_log("ConsignmentSalesService::handleReversal - golden rule state: " . $e->getMessage());
            }

            // Restore product to available inventory (if applicable)
            $this->restoreProductToInventory($productId);

            // ── Record internal adjustment event (paid_item_returned_internal) ──
            // Per spec: register a zero-impact ledger marker so the event is traceable.
            $this->recordInternalAdjustment($orderId, $orderItemId, $productId, $sale, $eventType);

            $result['should_debit_ledger'] = false;
            $result['golden_rule_applied'] = true;
            $result['notes'] = "[REGRA DE OURO] Produto pago retornou. Débito NÃO aplicado. Produto reclassificado como próprio.";

            // Audit the golden rule application
            $this->auditLog(
                $this->pdo,
                'consignment_golden_rule',
                $productId,
                'apply',
                ['payout_status' => 'pago', 'order_id' => $orderId],
                [
                    'event_type' => $eventType,
                    'sale_id' => $saleId,
                    'payout_id' => $sale['payout_id'] ?? null,
                    'credit_amount' => $sale['credit_amount'] ?? 0,
                    'notes' => 'Produto pago retornou ao estoque como próprio. Fornecedora não é cobrada.',
                ]
            );
        }

        return $result;
    }

    /**
     * Restore a product to available inventory after golden rule application.
     */
    private function restoreProductToInventory(int $productId): void
    {
        if (!$this->pdo) {
            return;
        }

        // Only set to disponivel + qty=1 if product was previously esgotado
        $product = $this->products->find($productId);
        if ($product && in_array($product->status ?? '', ['esgotado', 'baixado'], true)) {
            $sql = "UPDATE products SET status = 'disponivel', quantity = 1 WHERE sku = :sku";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':sku' => $productId]);
        }
    }

    /**
     * Record a zero-impact internal adjustment event in the ledger.
     * This makes the golden rule application traceable without affecting balances.
     *
     * Event type: paid_item_returned_internal (ajuste_interno)
     */
    private function recordInternalAdjustment(
        int $orderId,
        int $orderItemId,
        int $productId,
        array $sale,
        string $originalEventType
    ): void {
        if (!$this->pdo) {
            return;
        }

        $voucherAccountId = (int) ($sale['voucher_account_id'] ?? 0);
        $supplierPessoaId = (int) ($sale['supplier_pessoa_id'] ?? 0);

        // If voucher_account_id is not on the sale, try to resolve from the supplier
        if ($voucherAccountId <= 0 && $supplierPessoaId > 0) {
            $voucherRepo = new \App\Repositories\VoucherAccountRepository($this->pdo);
            $accounts = $voucherRepo->listByPerson($supplierPessoaId, false);
            foreach ($accounts as $acc) {
                if (($acc['scope'] ?? '') === 'consignacao' && ($acc['type'] ?? '') === 'credito') {
                    $voucherAccountId = (int) ($acc['id'] ?? 0);
                    break;
                }
            }
        }

        try {
            $ledger = new \App\Repositories\VoucherCreditEntryRepository($this->pdo);
            $ledger->insert([
                'voucher_account_id' => $voucherAccountId > 0 ? $voucherAccountId : null,
                'vendor_pessoa_id' => $supplierPessoaId,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'product_id' => $productId,
                'variation_id' => null,
                'sku' => $sale['sku'] ?? null,
                'product_name' => $sale['product_name'] ?? null,
                'quantity' => 0,
                'unit_price' => null,
                'line_total' => null,
                'percent' => $sale['percent_applied'] ?? null,
                'credit_amount' => 0,
                'sold_at' => $sale['sold_at'] ?? null,
                'buyer_name' => null,
                'buyer_email' => null,
                'type' => 'ajuste_interno',
                'event_type' => 'paid_item_returned_internal',
                'event_id' => (int) ($sale['payout_id'] ?? 0),
                'event_label' => '[REGRA DE OURO] Item pago retornou — ajuste interno',
                'event_notes' => "Evento original: {$originalEventType}. Produto reclassificado como próprio. Sem impacto no saldo.",
                'event_at' => date('Y-m-d H:i:s'),
                'payout_id' => $sale['payout_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log("ConsignmentSalesService::recordInternalAdjustment: " . $e->getMessage());
        }
    }

    /**
     * Admin adjustment: revert a sale + debit for a vendido_pendente item that needs
     * to bypass the normal order flow.
     *
     * @throws \InvalidArgumentException
     */
    public function adminAdjustRevertSale(
        int $orderId,
        int $orderItemId,
        int $productId,
        int $userId,
        string $justification
    ): void {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        if (trim($justification) === '') {
            throw new \InvalidArgumentException('Justificativa obrigatória para ajuste administrativo.');
        }

        $sale = $this->sales->findByOrderItem($orderId, $orderItemId, $productId);
        if (!$sale) {
            throw new \InvalidArgumentException("Venda consignada não encontrada para pedido #{$orderId}, item #{$orderItemId}, produto #{$productId}.");
        }

        if ($sale['sale_status'] !== 'ativa') {
            throw new \InvalidArgumentException('Venda já foi revertida.');
        }

        if ($sale['payout_status'] === 'pago') {
            throw new \InvalidArgumentException('Venda já foi paga. Use cancelamento de payout primeiro.');
        }

        // Mark sale as reversed with admin adjustment
        $this->sales->markReversed(
            (int) $sale['id'],
            'admin_adjustment',
            "[ADMIN AJUSTE] Revertido por user #{$userId}. Motivo: {$justification}"
        );

        // Transition state back to em_estoque
        try {
            $this->stateService->transition($productId, 'em_estoque', [
                'user_id' => $userId,
                'notes' => "[ADMIN AJUSTE] Venda revertida manualmente. Motivo: {$justification}",
                'admin_override' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            error_log("adminAdjustRevertSale state transition: " . $e->getMessage());
        }

        $this->auditLog(
            $this->pdo,
            'consignment_admin_adjustment',
            $productId,
            'revert_sale',
            $sale,
            [
                'user_id' => $userId,
                'justification' => $justification,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
            ]
        );
    }

    /**
     * Validate if a product can be written off (baixa) given its consignment state.
     *
     * @return array{allowed: bool, message: string, order_id: int|null}
     */
    public function validateWriteoffAllowed(int $productId): array
    {
        $product = $this->products->find($productId);
        if (!$product || ($product->source ?? '') !== 'consignacao') {
            return ['allowed' => true, 'message' => '', 'order_id' => null];
        }

        $status = $product->consignment_status ?? 'em_estoque';

        if ($status === 'vendido_pendente') {
            // Find the active sale to get the order_id
            $activeSales = $this->sales->listActiveByProductIds([$productId]);
            $orderId = !empty($activeSales) ? (int) ($activeSales[0]['order_id'] ?? 0) : null;

            return [
                'allowed' => false,
                'message' => "Este produto tem uma venda ativa" .
                    ($orderId ? " (Pedido #{$orderId})" : "") .
                    ". Registre primeiro a devolução ou cancelamento do pedido antes de devolver à fornecedora.",
                'order_id' => $orderId,
            ];
        }

        if ($status === 'vendido_pago') {
            return [
                'allowed' => false,
                'message' => "Este produto já foi pago à fornecedora. Requer ajuste administrativo (admin_override) para prosseguir.",
                'order_id' => null,
            ];
        }

        return ['allowed' => true, 'message' => '', 'order_id' => null];
    }
}
