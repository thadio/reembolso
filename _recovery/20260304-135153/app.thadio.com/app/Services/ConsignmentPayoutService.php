<?php

namespace App\Services;

use App\Repositories\ConsignmentPayoutItemRepository;
use App\Repositories\ConsignmentPayoutRepository;
use App\Repositories\ConsignmentPeriodLockRepository;
use App\Repositories\ConsignmentSaleRepository;
use App\Repositories\FinanceEntriesRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Support\AuditableTrait;
use PDO;

/**
 * Criação, confirmação e cancelamento de pagamentos (payouts) de consignação.
 */
class ConsignmentPayoutService
{
    use AuditableTrait;

    private ?PDO $pdo;
    private ConsignmentPayoutRepository $payouts;
    private ConsignmentPayoutItemRepository $payoutItems;
    private ConsignmentSaleRepository $sales;
    private ConsignmentPeriodLockRepository $periodLocks;
    private VoucherAccountRepository $vouchers;
    private VoucherCreditEntryRepository $ledger;
    private ConsignmentProductStateService $stateService;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->payouts = new ConsignmentPayoutRepository($pdo);
        $this->payoutItems = new ConsignmentPayoutItemRepository($pdo);
        $this->sales = new ConsignmentSaleRepository($pdo);
        $this->periodLocks = new ConsignmentPeriodLockRepository($pdo);
        $this->vouchers = new VoucherAccountRepository($pdo);
        $this->ledger = new VoucherCreditEntryRepository($pdo);
        $this->stateService = new ConsignmentProductStateService($pdo);
    }

    /**
     * Create a payout as 'rascunho' with selected sale IDs.
     *
     * @param array $data  Payout header data
     * @param int[] $saleIds  IDs of consignment_sales to include
     * @return array{success: bool, payout_id: int, errors: array}
     */
    public function createDraft(array $data, array $saleIds): array
    {
        $errors = [];
        if (!$this->pdo) {
            $errors[] = 'Sem conexão com banco.';
            return ['success' => false, 'payout_id' => 0, 'errors' => $errors];
        }

        $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds), static fn (int $id): bool => $id > 0)));

        if (empty($saleIds)) {
            $errors[] = 'Selecione pelo menos um item de venda para o pagamento.';
            return ['success' => false, 'payout_id' => 0, 'errors' => $errors];
        }

        $supplierPessoaId = (int) ($data['supplier_pessoa_id'] ?? 0);
        if ($supplierPessoaId <= 0) {
            $errors[] = 'Fornecedora inválida.';
            return ['success' => false, 'payout_id' => 0, 'errors' => $errors];
        }

        // Validate all sales
        $totalAmount = 0.0;
        $validSales = [];

        foreach ($saleIds as $saleId) {
            $sale = $this->sales->find((int) $saleId);
            if (!$sale) {
                $errors[] = "Venda #{$saleId} não encontrada.";
                continue;
            }
            if ((int) $sale['supplier_pessoa_id'] !== $supplierPessoaId) {
                $errors[] = "Venda #{$saleId} pertence a outra fornecedora.";
                continue;
            }
            if ($sale['sale_status'] !== 'ativa') {
                $errors[] = "Venda #{$saleId} já foi revertida.";
                continue;
            }
            if ($sale['payout_status'] !== 'pendente') {
                $errors[] = "Venda #{$saleId} já foi paga.";
                continue;
            }

            // Check period lock
            $soldAt = $sale['sold_at'] ?? null;
            if ($soldAt) {
                $yearMonth = substr($soldAt, 0, 7);
                if ($this->periodLocks->isLocked($yearMonth)) {
                    $errors[] = "Venda #{$saleId} pertence ao período {$yearMonth} que está fechado.";
                    continue;
                }
            }

            $totalAmount += (float) ($sale['credit_amount'] ?? 0);
            $validSales[] = $sale;
        }

        if (!empty($errors)) {
            return ['success' => false, 'payout_id' => 0, 'errors' => $errors];
        }

        $payoutId = 0;
        try {
            $this->pdo->beginTransaction();

            // Create payout
            $payoutId = $this->payouts->create([
                'supplier_pessoa_id' => $supplierPessoaId,
                'payout_date' => $data['payout_date'] ?? date('Y-m-d'),
                'method' => $data['method'] ?? 'pix',
                'total_amount' => round($totalAmount, 2),
                'items_count' => count($validSales),
                'status' => 'rascunho',
                'reference' => $data['reference'] ?? null,
                'pix_key' => $data['pix_key'] ?? null,
                'origin_bank_account_id' => $data['origin_bank_account_id'] ?? null,
                'voucher_account_id' => $data['voucher_account_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            // Create payout items
            foreach ($validSales as $sale) {
                $this->payoutItems->create([
                    'payout_id' => $payoutId,
                    'consignment_sale_id' => (int) $sale['id'],
                    'product_id' => (int) $sale['product_id'],
                    'order_id' => (int) $sale['order_id'],
                    'order_item_id' => (int) $sale['order_item_id'],
                    'amount' => (float) ($sale['credit_amount'] ?? 0),
                    'percent_applied' => (float) ($sale['percent_applied'] ?? 0),
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errors[] = 'Erro ao salvar rascunho: ' . $e->getMessage();
            return ['success' => false, 'payout_id' => 0, 'errors' => $errors];
        }

        return ['success' => true, 'payout_id' => $payoutId, 'errors' => []];
    }

    /**
     * Confirm a payout: execute all ledger debits, update states.
     *
     * @param int   $payoutId
     * @param int   $userId
     * @param array $financeData  Optional finance entry data
     * @return array{success: bool, errors: array}
     */
    public function confirm(int $payoutId, int $userId, array $financeData = []): array
    {
        $errors = [];

        if (!$this->pdo) {
            $errors[] = 'Sem conexão com banco.';
            return ['success' => false, 'errors' => $errors];
        }

        $payout = $this->payouts->find($payoutId);
        if (!$payout) {
            $errors[] = 'Pagamento não encontrado.';
            return ['success' => false, 'errors' => $errors];
        }

        if ($payout['status'] !== 'rascunho') {
            $errors[] = 'Somente pagamentos em rascunho podem ser confirmados.';
            return ['success' => false, 'errors' => $errors];
        }

        $items = $this->payoutItems->listByPayout($payoutId);
        if (empty($items)) {
            $errors[] = 'Pagamento não possui itens.';
            return ['success' => false, 'errors' => $errors];
        }

        $supplierPessoaId = (int) $payout['supplier_pessoa_id'];
        $voucherAccountId = (int) ($payout['voucher_account_id'] ?? 0);
        $payoutDate = $payout['payout_date'] ?? date('Y-m-d');
        $totalAmount = 0.0;
        $saleIds = [];

        // Validate all items BEFORE starting transaction
        foreach ($items as $item) {
            $saleId = (int) ($item['consignment_sale_id'] ?? 0);
            $sale = $this->sales->find($saleId);
            if (!$sale) {
                $errors[] = "Venda #{$saleId} não encontrada.";
                continue;
            }
            if ($sale['sale_status'] !== 'ativa') {
                $errors[] = "Venda #{$saleId} já foi revertida — remova do pagamento.";
                continue;
            }
            if ($sale['payout_status'] !== 'pendente') {
                $errors[] = "Venda #{$saleId} já foi paga.";
                continue;
            }
            if ((int) ($sale['supplier_pessoa_id'] ?? 0) !== $supplierPessoaId) {
                $errors[] = "Venda #{$saleId} pertence a outra fornecedora.";
                continue;
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // ── Single transaction for the entire confirmation ──
        try {
            $this->pdo->beginTransaction();

            // Process each item
            foreach ($items as $item) {
                $saleId = (int) ($item['consignment_sale_id'] ?? 0);
                $sale = $this->sales->find($saleId);
                if (!$sale) {
                    throw new \RuntimeException("Venda #{$saleId} não encontrada durante confirmação.");
                }
                if ((int) ($sale['supplier_pessoa_id'] ?? 0) !== $supplierPessoaId) {
                    throw new \RuntimeException("Venda #{$saleId} pertence a outra fornecedora.");
                }
                if (($sale['sale_status'] ?? '') !== 'ativa') {
                    throw new \RuntimeException("Venda #{$saleId} já foi revertida.");
                }
                if (($sale['payout_status'] ?? '') !== 'pendente') {
                    throw new \RuntimeException("Venda #{$saleId} já está paga.");
                }

                $amount = (float) ($item['amount'] ?? $sale['credit_amount'] ?? 0);
                $totalAmount += $amount;

                // Insert debit movement in the ledger
                $ledgerInserted = $this->ledger->insert([
                    'voucher_account_id' => $voucherAccountId,
                    'vendor_pessoa_id' => $supplierPessoaId,
                    'order_id' => (int) ($sale['order_id'] ?? 0),
                    'order_item_id' => (int) ($sale['order_item_id'] ?? 0),
                    'product_id' => (int) ($sale['product_id'] ?? 0),
                    'variation_id' => null,
                    'sku' => $item['product_sku'] ?? null,
                    'product_name' => $item['product_name'] ?? ('Produto #' . ($sale['product_id'] ?? '')),
                    'quantity' => 1,
                    'unit_price' => null,
                    'line_total' => null,
                    'percent' => $item['sale_percent'] ?? null,
                    'credit_amount' => $amount,
                    'sold_at' => $sale['sold_at'] ?? null,
                    'buyer_name' => null,
                    'buyer_email' => null,
                    'type' => 'debito',
                    'event_type' => 'vendor_payout',
                    'event_id' => $payoutId,
                    'event_label' => 'Pagamento consignação #' . $payoutId,
                    'event_notes' => $payout['reference'] ?? null,
                    'event_at' => $payoutDate . ' ' . date('H:i:s'),
                    'payout_id' => $payoutId,
                ]);

                // Update payout item with ledger movement reference
                if ($ledgerInserted) {
                    $lastId = $this->getLastInsertedMovementId();
                    if ($lastId) {
                        $this->payoutItems->setLedgerDebitMovementId((int) $item['id'], $lastId);
                    }
                }

                $saleIds[] = $saleId;

                // Transition product state: vendido_pendente → vendido_pago
                $productId = (int) ($sale['product_id'] ?? 0);
                if ($productId > 0) {
                    try {
                        $this->stateService->transition($productId, 'vendido_pago', [
                            'user_id' => $userId,
                            'notes' => "Payout #{$payoutId} confirmado",
                        ]);
                    } catch (\InvalidArgumentException $e) {
                        error_log("ConsignmentPayoutService::confirm state: " . $e->getMessage());
                    }
                }
            }

            // Mark sales as paid
            $this->sales->markPaidByPayout($saleIds, $payoutId, $payoutDate . ' ' . date('H:i:s'));

            // Debit voucher balance
            if ($voucherAccountId > 0 && $totalAmount > 0) {
                $this->vouchers->debitBalance($voucherAccountId, $totalAmount);
            }

            // Update payout totals and confirm
            $this->payouts->update($payoutId, [
                'total_amount' => round($totalAmount, 2),
                'items_count' => count($saleIds),
            ]);
            $this->payouts->confirm($payoutId, $userId);

            // Create finance entry if data provided
            if (!empty($financeData) && class_exists(FinanceEntriesRepository::class)) {
                $financeRepo = new FinanceEntriesRepository($this->pdo);
                $financeEntryId = $this->createFinanceEntry($financeRepo, $payout, $totalAmount, $financeData);
                if ($financeEntryId) {
                    $this->payouts->update($payoutId, ['finance_entry_id' => $financeEntryId]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errors[] = 'Erro ao confirmar pagamento: ' . $e->getMessage();
            return ['success' => false, 'errors' => $errors];
        }

        $this->auditLog(
            'UPDATE',
            'consignment_payout',
            $payoutId,
            null,
            [
                'action' => 'confirm',
                'user_id' => $userId,
                'total_amount' => $totalAmount,
                'items_count' => count($saleIds),
                'sale_ids' => $saleIds,
            ]
        );

        return ['success' => true, 'errors' => []];
    }

    /**
     * Reprocess a confirmed payout, allowing SKU/item changes while preserving consistency.
     *
     * The operation keeps the same payout ID and performs:
     * - full rollback of prior payout effects (sales links, product states, voucher balance, ledger trail);
     * - full apply of the new selected sales;
     * - header/finance refresh.
     *
     * @param int   $payoutId
     * @param array $data      Editable payout header data
     * @param int[] $saleIds   New selection of consignment_sales IDs
     * @param int   $userId
     * @return array{success: bool, errors: array}
     */
    public function reprocessConfirmed(int $payoutId, array $data, array $saleIds, int $userId): array
    {
        $errors = [];

        if (!$this->pdo) {
            $errors[] = 'Sem conexão com banco.';
            return ['success' => false, 'errors' => $errors];
        }

        $payout = $this->payouts->find($payoutId);
        if (!$payout) {
            $errors[] = 'Pagamento não encontrado.';
            return ['success' => false, 'errors' => $errors];
        }

        if (($payout['status'] ?? '') !== 'confirmado') {
            $errors[] = 'Somente pagamentos confirmados podem ser reprocessados.';
            return ['success' => false, 'errors' => $errors];
        }

        $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds), static fn (int $id): bool => $id > 0)));
        if (empty($saleIds)) {
            $errors[] = 'Selecione ao menos uma venda para manter no pagamento.';
            return ['success' => false, 'errors' => $errors];
        }

        $supplierPessoaId = (int) ($payout['supplier_pessoa_id'] ?? 0);
        $requestedSupplierId = (int) ($data['supplier_pessoa_id'] ?? $supplierPessoaId);
        if ($requestedSupplierId !== $supplierPessoaId) {
            $errors[] = 'Não é permitido alterar a fornecedora de um pagamento confirmado.';
            return ['success' => false, 'errors' => $errors];
        }

        $payoutDate = trim((string) ($data['payout_date'] ?? ($payout['payout_date'] ?? date('Y-m-d'))));
        if ($payoutDate === '') {
            $payoutDate = date('Y-m-d');
        }
        $method = trim((string) ($data['method'] ?? ($payout['method'] ?? 'pix')));
        if ($method === '') {
            $method = 'pix';
        }

        $oldVoucherAccountId = (int) ($payout['voucher_account_id'] ?? 0);
        $newVoucherAccountId = (int) ($data['voucher_account_id'] ?? $oldVoucherAccountId);
        if ($newVoucherAccountId <= 0) {
            $errors[] = 'Conta voucher inválida para o payout.';
            return ['success' => false, 'errors' => $errors];
        }

        $validatedSales = [];
        foreach ($saleIds as $saleId) {
            $sale = $this->sales->find($saleId);
            if (!$sale) {
                $errors[] = "Venda #{$saleId} não encontrada.";
                continue;
            }
            if ((int) ($sale['supplier_pessoa_id'] ?? 0) !== $supplierPessoaId) {
                $errors[] = "Venda #{$saleId} pertence a outra fornecedora.";
                continue;
            }
            if (($sale['sale_status'] ?? '') !== 'ativa') {
                $errors[] = "Venda #{$saleId} não está ativa.";
                continue;
            }

            $salePayoutId = (int) ($sale['payout_id'] ?? 0);
            $salePayoutStatus = (string) ($sale['payout_status'] ?? '');
            $isSelectable = ($salePayoutStatus === 'pendente') || ($salePayoutId === $payoutId);
            if (!$isSelectable) {
                $errors[] = "Venda #{$saleId} já está vinculada a outro pagamento confirmado.";
                continue;
            }

            $validatedSales[] = $sale;
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $editBatchId = (int) floor(microtime(true) * 1000);
        $oldItems = $this->payoutItems->listByPayout($payoutId);
        $oldTotal = 0.0;
        foreach ($oldItems as $item) {
            $oldTotal += (float) ($item['amount'] ?? 0);
        }

        $payoutDateTime = $payoutDate . ' ' . date('H:i:s');
        $reversalNote = 'Reprocessamento payout #' . $payoutId . ' em ' . date('Y-m-d H:i:s');
        $reversalEventLabel = 'Ajuste payout #' . $payoutId . ' (estorno técnico)';
        $newEventLabel = 'Reprocessamento payout #' . $payoutId;
        $newEventNotes = trim((string) ($data['reference'] ?? ($payout['reference'] ?? '')));
        if ($newEventNotes === '') {
            $newEventNotes = 'Payout reprocessado em ' . date('Y-m-d H:i:s');
        }

        try {
            $this->pdo->beginTransaction();

            // 1) Rollback old payout effects
            foreach ($oldItems as $oldItem) {
                $oldAmount = (float) ($oldItem['amount'] ?? 0);
                $oldSaleId = (int) ($oldItem['consignment_sale_id'] ?? 0);
                $oldSale = $oldSaleId > 0 ? $this->sales->find($oldSaleId) : null;

                if ($oldVoucherAccountId > 0 && $oldAmount > 0) {
                    $this->ledger->insert([
                        'voucher_account_id' => $oldVoucherAccountId,
                        'vendor_pessoa_id' => $supplierPessoaId,
                        'order_id' => (int) ($oldItem['order_id'] ?? 0),
                        'order_item_id' => (int) ($oldItem['order_item_id'] ?? 0),
                        'product_id' => (int) ($oldItem['product_id'] ?? 0),
                        'variation_id' => null,
                        'sku' => $oldItem['sku'] ?? null,
                        'product_name' => $oldItem['product_name'] ?? null,
                        'quantity' => 1,
                        'unit_price' => null,
                        'line_total' => null,
                        'percent' => $oldItem['percent_applied'] ?? null,
                        'credit_amount' => $oldAmount,
                        'sold_at' => $oldSale['sold_at'] ?? null,
                        'buyer_name' => null,
                        'buyer_email' => null,
                        'type' => 'credito',
                        'event_type' => 'payout_edit_reversal',
                        'event_id' => $editBatchId,
                        'event_label' => $reversalEventLabel,
                        'event_notes' => $reversalNote,
                        'event_at' => date('Y-m-d H:i:s'),
                        'payout_id' => $payoutId,
                    ]);
                }

                $oldProductId = (int) ($oldItem['product_id'] ?? 0);
                if ($oldProductId > 0 && $oldSale && ($oldSale['sale_status'] ?? '') === 'ativa') {
                    try {
                        $this->stateService->transition($oldProductId, 'vendido_pendente', [
                            'user_id' => $userId,
                            'allow_payout_cancel' => true,
                            'notes' => $reversalNote,
                        ]);
                    } catch (\Throwable $e) {
                        error_log('ConsignmentPayoutService::reprocessConfirmed rollback state: ' . $e->getMessage());
                    }
                }
            }

            $this->sales->resetPayoutForSales($payoutId);
            $this->payoutItems->deleteByPayout($payoutId);

            if ($oldVoucherAccountId > 0 && $oldTotal > 0) {
                $this->vouchers->creditBalance($oldVoucherAccountId, round($oldTotal, 2));
            }

            // 2) Apply new selection
            $newSaleIds = [];
            $newTotal = 0.0;
            foreach ($validatedSales as $sale) {
                $saleId = (int) ($sale['id'] ?? 0);
                if ($saleId <= 0) {
                    continue;
                }

                // Revalidate inside TX to avoid race conditions.
                $freshSale = $this->sales->find($saleId);
                if (!$freshSale) {
                    throw new \RuntimeException("Venda #{$saleId} não encontrada durante reprocessamento.");
                }
                if ((int) ($freshSale['supplier_pessoa_id'] ?? 0) !== $supplierPessoaId) {
                    throw new \RuntimeException("Venda #{$saleId} pertence a outra fornecedora.");
                }
                if (($freshSale['sale_status'] ?? '') !== 'ativa') {
                    throw new \RuntimeException("Venda #{$saleId} não está ativa.");
                }
                $freshPayoutId = (int) ($freshSale['payout_id'] ?? 0);
                $freshPayoutStatus = (string) ($freshSale['payout_status'] ?? '');
                if ($freshPayoutStatus !== 'pendente' && $freshPayoutId !== $payoutId) {
                    throw new \RuntimeException("Venda #{$saleId} já foi vinculada a outro pagamento.");
                }

                $amount = (float) ($freshSale['credit_amount'] ?? 0);
                $newTotal += $amount;

                $createdItemId = $this->payoutItems->create([
                    'payout_id' => $payoutId,
                    'consignment_sale_id' => $saleId,
                    'product_id' => (int) ($freshSale['product_id'] ?? 0),
                    'order_id' => (int) ($freshSale['order_id'] ?? 0),
                    'order_item_id' => (int) ($freshSale['order_item_id'] ?? 0),
                    'amount' => $amount,
                    'percent_applied' => (float) ($freshSale['percent_applied'] ?? 0),
                ]);

                $ledgerInserted = $this->ledger->insert([
                    'voucher_account_id' => $newVoucherAccountId,
                    'vendor_pessoa_id' => $supplierPessoaId,
                    'order_id' => (int) ($freshSale['order_id'] ?? 0),
                    'order_item_id' => (int) ($freshSale['order_item_id'] ?? 0),
                    'product_id' => (int) ($freshSale['product_id'] ?? 0),
                    'variation_id' => null,
                    'sku' => $freshSale['sku'] ?? null,
                    'product_name' => $freshSale['product_name'] ?? ('Produto #' . ($freshSale['product_id'] ?? '')),
                    'quantity' => 1,
                    'unit_price' => null,
                    'line_total' => null,
                    'percent' => $freshSale['percent_applied'] ?? null,
                    'credit_amount' => $amount,
                    'sold_at' => $freshSale['sold_at'] ?? null,
                    'buyer_name' => null,
                    'buyer_email' => null,
                    'type' => 'debito',
                    'event_type' => 'vendor_payout_edit',
                    'event_id' => $editBatchId,
                    'event_label' => $newEventLabel,
                    'event_notes' => $newEventNotes,
                    'event_at' => $payoutDateTime,
                    'payout_id' => $payoutId,
                ]);

                if ($ledgerInserted && $createdItemId > 0) {
                    $lastMovementId = $this->getLastInsertedMovementId();
                    if ($lastMovementId) {
                        $this->payoutItems->setLedgerDebitMovementId($createdItemId, $lastMovementId);
                    }
                }

                $newSaleIds[] = $saleId;

                $productId = (int) ($freshSale['product_id'] ?? 0);
                if ($productId > 0) {
                    try {
                        $this->stateService->transition($productId, 'vendido_pago', [
                            'user_id' => $userId,
                            'notes' => "Payout #{$payoutId} reprocessado",
                        ]);
                    } catch (\Throwable $e) {
                        error_log('ConsignmentPayoutService::reprocessConfirmed apply state: ' . $e->getMessage());
                    }
                }
            }

            $this->sales->markPaidByPayout($newSaleIds, $payoutId, $payoutDateTime);

            if ($newVoucherAccountId > 0 && $newTotal > 0) {
                $this->vouchers->debitBalance($newVoucherAccountId, round($newTotal, 2));
            }

            $bankAccountId = (int) ($data['origin_bank_account_id'] ?? ($payout['origin_bank_account_id'] ?? 0));
            $updatedPayoutData = [
                'payout_date' => $payoutDate,
                'method' => $method,
                'reference' => $data['reference'] ?? ($payout['reference'] ?? null),
                'pix_key' => $data['pix_key'] ?? ($payout['pix_key'] ?? null),
                'origin_bank_account_id' => $bankAccountId > 0 ? $bankAccountId : null,
                'voucher_account_id' => $newVoucherAccountId,
                'notes' => $data['notes'] ?? ($payout['notes'] ?? null),
                'total_amount' => round($newTotal, 2),
                'items_count' => count($newSaleIds),
            ];
            $this->payouts->update($payoutId, $updatedPayoutData);

            $financeEntryId = (int) ($payout['finance_entry_id'] ?? 0);
            if ($financeEntryId > 0) {
                if (!$this->syncFinanceEntryForEditedPayout(
                    $financeEntryId,
                    $payoutId,
                    round($newTotal, 2),
                    $payoutDate,
                    $bankAccountId > 0 ? $bankAccountId : null
                )) {
                    throw new \RuntimeException("Não foi possível atualizar o lançamento financeiro #{$financeEntryId}.");
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errors[] = 'Erro ao reprocessar pagamento: ' . $e->getMessage();
            return ['success' => false, 'errors' => $errors];
        }

        $this->auditLog(
            'UPDATE',
            'consignment_payout',
            $payoutId,
            $payout,
            [
                'action' => 'reprocess_confirmed',
                'user_id' => $userId,
                'selected_sale_ids' => $saleIds,
                'edit_batch_id' => $editBatchId,
            ]
        );

        return ['success' => true, 'errors' => []];
    }

    /**
     * Cancel a confirmed payout: reverse everything.
     *
     * @param int    $payoutId
     * @param int    $userId
     * @param string $reason
     * @return array{success: bool, errors: array}
     */
    public function cancel(int $payoutId, int $userId, string $reason): array
    {
        $errors = [];

        if (!$this->pdo) {
            $errors[] = 'Sem conexão com banco.';
            return ['success' => false, 'errors' => $errors];
        }

        if (trim($reason) === '') {
            $errors[] = 'Motivo do cancelamento é obrigatório.';
            return ['success' => false, 'errors' => $errors];
        }

        $payout = $this->payouts->find($payoutId);
        if (!$payout) {
            $errors[] = 'Pagamento não encontrado.';
            return ['success' => false, 'errors' => $errors];
        }

        if ($payout['status'] !== 'confirmado') {
            $errors[] = 'Somente pagamentos confirmados podem ser cancelados.';
            return ['success' => false, 'errors' => $errors];
        }

        $items = $this->payoutItems->listByPayout($payoutId);
        $voucherAccountId = (int) ($payout['voucher_account_id'] ?? 0);
        $totalToReCredit = 0.0;

        // ── Single transaction for the entire cancellation ──
        try {
            $this->pdo->beginTransaction();

            foreach ($items as $item) {
                $saleId = (int) ($item['consignment_sale_id'] ?? 0);
                $sale = $this->sales->find($saleId);
                $amount = (float) ($item['amount'] ?? 0);
                $totalToReCredit += $amount;

                // Insert credit (reversal) in ledger
                $this->ledger->insert([
                    'voucher_account_id' => $voucherAccountId,
                    'vendor_pessoa_id' => (int) ($payout['supplier_pessoa_id'] ?? 0),
                    'order_id' => (int) ($item['order_id'] ?? 0),
                    'order_item_id' => (int) ($item['order_item_id'] ?? 0),
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'variation_id' => null,
                    'sku' => $item['product_sku'] ?? null,
                    'product_name' => $item['product_name'] ?? null,
                    'quantity' => 1,
                    'unit_price' => null,
                    'line_total' => null,
                    'percent' => $item['sale_percent'] ?? null,
                    'credit_amount' => $amount,
                    'sold_at' => $sale['sold_at'] ?? null,
                    'buyer_name' => null,
                    'buyer_email' => null,
                    'type' => 'credito',
                    'event_type' => 'payout_cancel',
                    'event_id' => $payoutId,
                    'event_label' => 'Cancelamento payout #' . $payoutId,
                    'event_notes' => $reason,
                    'event_at' => date('Y-m-d H:i:s'),
                    'payout_id' => $payoutId,
                ]);

                // Transition product state back: vendido_pago → vendido_pendente
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId > 0 && $sale && $sale['sale_status'] === 'ativa') {
                    try {
                        $this->stateService->transition($productId, 'vendido_pendente', [
                            'user_id' => $userId,
                            'allow_payout_cancel' => true,
                            'notes' => "Payout #{$payoutId} cancelado. Motivo: {$reason}",
                        ]);
                    } catch (\Throwable $e) {
                        error_log("ConsignmentPayoutService::cancel state revert: " . $e->getMessage());
                    }
                }
            }

            // Reset payout fields on sales
            $this->sales->resetPayoutForSales($payoutId);

            // Re-credit voucher balance
            if ($voucherAccountId > 0 && $totalToReCredit > 0) {
                $this->vouchers->creditBalance($voucherAccountId, $totalToReCredit);
            }

            // Reverse/adjust related finance entry
            $financeEntryId = (int) ($payout['finance_entry_id'] ?? 0);
            if ($financeEntryId > 0) {
                if (!$this->reverseFinanceEntryForCancelledPayout($financeEntryId, $payoutId, $reason)) {
                    throw new \RuntimeException("Não foi possível estornar/ajustar o lançamento financeiro #{$financeEntryId}.");
                }
            }

            // Cancel the payout
            $this->payouts->cancel($payoutId, $userId, $reason);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errors[] = 'Erro ao cancelar pagamento: ' . $e->getMessage();
            return ['success' => false, 'errors' => $errors];
        }

        $this->auditLog(
            'UPDATE',
            'consignment_payout',
            $payoutId,
            $payout,
            [
                'action' => 'cancel',
                'user_id' => $userId,
                'reason' => $reason,
                'total_re_credited' => $totalToReCredit,
            ]
        );

        return ['success' => true, 'errors' => []];
    }

    /**
     * Sync amount/date/status in the linked finance entry after payout reprocessing.
     */
    private function syncFinanceEntryForEditedPayout(
        int $financeEntryId,
        int $payoutId,
        float $totalAmount,
        string $payoutDate,
        ?int $bankAccountId
    ): bool {
        if (!$this->pdo || $financeEntryId <= 0) {
            return false;
        }

        $note = '[CONSIGNAÇÃO] Ajustado por edição do payout #' . $payoutId .
            ' em ' . date('Y-m-d H:i:s');
        $paidAt = $payoutDate . ' ' . date('H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE financeiro_lancamentos
                 SET amount = :amount,
                     due_date = :due_date,
                     status = 'pago',
                     paid_at = :paid_at,
                     paid_amount = :paid_amount,
                     bank_account_id = :bank_account_id,
                     notes = CASE
                         WHEN notes IS NULL OR notes = '' THEN :note
                         ELSE CONCAT(notes, '\n', :note)
                     END
                 WHERE id = :id"
            );
            $stmt->execute([
                ':id' => $financeEntryId,
                ':amount' => $totalAmount,
                ':due_date' => $payoutDate,
                ':paid_at' => $paidAt,
                ':paid_amount' => $totalAmount,
                ':bank_account_id' => $bankAccountId,
                ':note' => $note,
            ]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (\Throwable $e) {
            error_log('ConsignmentPayoutService::syncFinanceEntry(financeiro_lancamentos): ' . $e->getMessage());
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE finance_entries
                 SET amount = :amount,
                     due_date = :due_date,
                     status = 'pago',
                     paid_at = :paid_at,
                     paid_amount = :paid_amount,
                     notes = CASE
                         WHEN notes IS NULL OR notes = '' THEN :note
                         ELSE CONCAT(notes, '\n', :note)
                     END
                 WHERE id = :id"
            );
            $stmt->execute([
                ':id' => $financeEntryId,
                ':amount' => $totalAmount,
                ':due_date' => $payoutDate,
                ':paid_at' => $paidAt,
                ':paid_amount' => $totalAmount,
                ':note' => $note,
            ]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (\Throwable $e) {
            error_log('ConsignmentPayoutService::syncFinanceEntry(finance_entries): ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Reverse/adjust finance entry associated with a canceled payout.
     */
    private function reverseFinanceEntryForCancelledPayout(int $financeEntryId, int $payoutId, string $reason): bool
    {
        if (!$this->pdo || $financeEntryId <= 0) {
            return false;
        }

        $note = '[CONSIGNAÇÃO] Estornado por cancelamento do payout #' . $payoutId .
            ' em ' . date('Y-m-d H:i:s') . '. Motivo: ' . $reason;

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE financeiro_lancamentos
                 SET status = 'cancelado',
                     paid_at = NULL,
                     paid_amount = NULL,
                     notes = CASE
                         WHEN notes IS NULL OR notes = '' THEN :note
                         ELSE CONCAT(notes, '\n', :note)
                     END
                 WHERE id = :id"
            );
            $stmt->execute([
                ':id' => $financeEntryId,
                ':note' => $note,
            ]);

            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (\Throwable $e) {
            error_log('ConsignmentPayoutService::reverseFinanceEntry(financeiro_lancamentos): ' . $e->getMessage());
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE finance_entries
                 SET status = 'cancelado',
                     paid_at = NULL,
                     paid_amount = NULL,
                     notes = CASE
                         WHEN notes IS NULL OR notes = '' THEN :note
                         ELSE CONCAT(notes, '\n', :note)
                     END
                 WHERE id = :id"
            );
            $stmt->execute([
                ':id' => $financeEntryId,
                ':note' => $note,
            ]);
            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (\Throwable $e) {
            error_log('ConsignmentPayoutService::reverseFinanceEntry(finance_entries): ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Get receipt data for a confirmed payout.
     */
    public function getReceiptData(int $payoutId): ?array
    {
        $payout = $this->payouts->find($payoutId);
        if (!$payout) {
            return null;
        }

        $items = $this->payoutItems->listByPayout($payoutId);
        $sales = [];
        foreach ($items as $item) {
            $sale = $this->sales->find((int) ($item['consignment_sale_id'] ?? 0));
            $sales[] = array_merge($item, ['sale' => $sale]);
        }

        return [
            'payout' => $payout,
            'items' => $sales,
            'hash' => md5($payoutId . ':' . ($payout['confirmed_at'] ?? '') . ':' . ($payout['total_amount'] ?? '')),
        ];
    }

    /**
     * Create finance entry for a confirmed payout.
     */
    private function createFinanceEntry($financeRepo, array $payout, float $totalAmount, array $financeData): ?int
    {
        try {
            $entryId = $financeRepo->create([
                'type' => 'pagar',
                'category_id' => $financeData['category_id'] ?? null,
                'description' => 'Pagamento consignação - ' . ($payout['supplier_pessoa_id'] ?? ''),
                'amount' => $totalAmount,
                'due_date' => $payout['payout_date'] ?? date('Y-m-d'),
                'paid_at' => $payout['payout_date'] ?? date('Y-m-d'),
                'status' => 'pago',
                'bank_account_id' => $payout['origin_bank_account_id'] ?? null,
                'reference' => 'payout:' . ($payout['id'] ?? 0),
                'notes' => 'Pagamento consignação #' . ($payout['id'] ?? 0),
            ]);
            return $entryId;
        } catch (\Throwable $e) {
            error_log('ConsignmentPayoutService::createFinanceEntry: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the last inserted movement ID from the ledger.
     */
    private function getLastInsertedMovementId(): ?int
    {
        if (!$this->pdo) {
            return null;
        }
        $id = (int) $this->pdo->lastInsertId();
        return $id > 0 ? $id : null;
    }
}
