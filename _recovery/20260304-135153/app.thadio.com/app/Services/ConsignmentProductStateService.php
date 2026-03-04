<?php

namespace App\Services;

use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ProductRepository;
use App\Support\AuditableTrait;
use PDO;

/**
 * Máquina de estados formal para produtos consignados.
 *
 * Toda transição de consignment_status DEVE passar por este Service.
 * Nenhum Repository ou Controller altera o status diretamente.
 */
class ConsignmentProductStateService
{
    use AuditableTrait;

    private ?PDO $pdo;
    private ConsignmentProductRegistryRepository $registry;
    private ProductRepository $products;

    /**
     * Mapa de transições permitidas: estado_atual => [estados_destino].
     */
    private const TRANSITIONS = [
        'em_estoque'       => ['vendido_pendente', 'devolvido', 'doado', 'descartado'],
        'vendido_pendente' => ['em_estoque', 'vendido_pago'],
        'vendido_pago'     => ['proprio_pos_pgto'],
        'proprio_pos_pgto' => ['doado', 'descartado'],  // em_estoque apenas via admin_override
        'devolvido'        => [],
        'doado'            => [],
        'descartado'       => [],
    ];

    /**
     * Labels para exibição no front-end.
     */
    public const STATUS_LABELS = [
        'em_estoque'       => 'Em estoque (consignado)',
        'vendido_pendente' => 'Vendido – pgto. pendente',
        'vendido_pago'     => 'Vendido e pago',
        'proprio_pos_pgto' => 'Próprio (pós-pagamento)',
        'devolvido'        => 'Devolvido à fornecedora',
        'doado'            => 'Doado',
        'descartado'       => 'Descartado / perda',
    ];

    /**
     * CSS badge classes por status.
     */
    public const STATUS_BADGES = [
        'em_estoque'       => 'badge-info',
        'vendido_pendente' => 'badge-warning',
        'vendido_pago'     => 'badge-success',
        'proprio_pos_pgto' => 'badge-secondary',
        'devolvido'        => 'badge-dark',
        'doado'            => 'badge-dark',
        'descartado'       => 'badge-danger',
    ];

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->registry = new ConsignmentProductRegistryRepository($pdo);
        $this->products = new ProductRepository($pdo);
    }

    /**
     * Check if a transition from $current to $target is allowed.
     *
     * @param string $current  Current consignment_status
     * @param string $target   Target consignment_status
     * @param array  $context  Optional context: ['admin_override' => bool]
     */
    public function isAllowed(string $current, string $target, array $context = []): bool
    {
        // Admin override bypasses the map for proprio_pos_pgto → em_estoque
        if (!empty($context['admin_override'])) {
            if ($current === 'proprio_pos_pgto' && $target === 'em_estoque') {
                return true;
            }
        }

        // Explicit bypass for payout cancellation rollback: vendido_pago → vendido_pendente
        if (!empty($context['allow_payout_cancel'])) {
            if ($current === 'vendido_pago' && $target === 'vendido_pendente') {
                return true;
            }
        }

        $allowed = self::TRANSITIONS[$current] ?? [];
        return in_array($target, $allowed, true);
    }

    /**
     * Get current consignment_status for a product.
     */
    public function getCurrentStatus(int $productId): ?string
    {
        $product = $this->products->find($productId);
        if (!$product) {
            return null;
        }
        return $product->consignment_status ?? null;
    }

    /**
     * Perform a state transition for a product.
     *
     * @param int    $productId
     * @param string $targetStatus
     * @param array  $context  Keys: admin_override, user_id, notes, reason, ...
     * @throws \InvalidArgumentException If transition is invalid
     */
    public function transition(int $productId, string $targetStatus, array $context = []): void
    {
        $current = $this->getCurrentStatus($productId);
        if ($current === null) {
            throw new \InvalidArgumentException(
                "Produto #{$productId} não possui consignment_status (não é consignado ou não encontrado)."
            );
        }

        if ($current === $targetStatus) {
            return; // No-op — already in target state
        }

        if (!$this->isAllowed($current, $targetStatus, $context)) {
            throw new \InvalidArgumentException(
                "Transição inválida: {$current} → {$targetStatus} para produto #{$productId}"
            );
        }

        $userId = $context['user_id'] ?? null;
        $notes = $context['notes'] ?? null;

        $this->applyTransition($productId, $current, $targetStatus, $context);
        $this->logTransition($productId, $current, $targetStatus, $context);
    }

    /**
     * Apply the transition: update product + registry.
     */
    private function applyTransition(int $productId, string $from, string $to, array $context): void
    {
        $userId = $context['user_id'] ?? null;
        $notes = $context['notes'] ?? null;

        // Update products.consignment_status
        if ($this->pdo) {
            $sql = "UPDATE products SET consignment_status = :cs WHERE sku = :sku";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':cs' => $to, ':sku' => $productId]);
        }

        // Update registry
        $this->registry->updateStatusByProduct($productId, $to, $userId, $notes);

        // Special handling for transitions
        if ($to === 'proprio_pos_pgto') {
            $this->applyDetach($productId, $userId, $notes);
        }

        if ($to === 'em_estoque' && $from === 'proprio_pos_pgto' && !empty($context['admin_override'])) {
            $this->applyReattach($productId, $userId, $context);
        }
    }

    /**
     * Detach product from consignment (regra de ouro: vendido_pago → proprio_pos_pgto).
     * Changes source to 'consignacao_quitada', nullifies supplier.
     */
    private function applyDetach(int $productId, ?int $userId, ?string $notes): void
    {
        if (!$this->pdo) {
            return;
        }

        // Get current product data for audit
        $product = $this->products->find($productId);

        // Update product fields
        $sql = "UPDATE products SET
                  source = 'consignacao_quitada',
                  supplier_pessoa_id = NULL,
                  percentual_consignacao = NULL,
                  consignment_status = 'proprio_pos_pgto',
                  consignment_detached_at = NOW()
                WHERE sku = :sku";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sku' => $productId]);

        // Update registry with detach info
        $reg = $this->registry->findByProductId($productId);
        if ($reg) {
            $this->registry->update((int) $reg['id'], [
                'consignment_status' => 'proprio_pos_pgto',
                'detached_at' => date('Y-m-d H:i:s'),
                'original_source' => 'consignacao',
                'status_changed_at' => date('Y-m-d H:i:s'),
                'status_changed_by' => $userId,
                'notes' => ($reg['notes'] ?? '') !== ''
                    ? $reg['notes'] . "\n[REGRA DE OURO] Produto pago retornou ao estoque como próprio. " . date('Y-m-d H:i:s')
                    : '[REGRA DE OURO] Produto pago retornou ao estoque como próprio. ' . date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Reattach product to consignment (admin override: proprio_pos_pgto → em_estoque).
     * Restores source to 'consignacao', supplier, percent from registry snapshot.
     */
    private function applyReattach(int $productId, ?int $userId, array $context): void
    {
        if (!$this->pdo) {
            return;
        }

        $reg = $this->registry->findByProductId($productId);
        if (!$reg) {
            return;
        }

        $reason = $context['reason'] ?? $context['notes'] ?? 'Admin override';
        $originalSupplierId = $reg['consignment_supplier_original_id'] ?? null;
        $percentSnapshot = $reg['consignment_percent_snapshot'] ?? null;

        // Restore product fields
        $sql = "UPDATE products SET
                  source = 'consignacao',
                  supplier_pessoa_id = :sid,
                  percentual_consignacao = :pct,
                  consignment_status = 'em_estoque',
                  consignment_detached_at = NULL
                WHERE sku = :sku";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sid' => $originalSupplierId,
            ':pct' => $percentSnapshot,
            ':sku' => $productId,
        ]);

        // Update registry
        $this->registry->update((int) $reg['id'], [
            'supplier_pessoa_id' => $originalSupplierId,
            'consignment_status' => 'em_estoque',
            'detached_at' => null,
            'original_source' => null,
            'status_changed_at' => date('Y-m-d H:i:s'),
            'status_changed_by' => $userId,
            'notes' => ($reg['notes'] ?? '') !== ''
                ? $reg['notes'] . "\n[ADMIN OVERRIDE] Reativado como consignado. Motivo: {$reason}. " . date('Y-m-d H:i:s')
                : "[ADMIN OVERRIDE] Reativado como consignado. Motivo: {$reason}. " . date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log the transition for audit.
     */
    private function logTransition(int $productId, string $from, string $to, array $context): void
    {
        if (!$this->pdo) {
            return;
        }

        $this->auditLog(
            'transition',
            'consignment_state_transition',
            $productId,
            ['consignment_status' => $from],
            [
                'consignment_status' => $to,
                'admin_override' => !empty($context['admin_override']),
                'user_id' => $context['user_id'] ?? null,
                'notes' => $context['notes'] ?? null,
            ]
        );
    }

    /**
     * Get allowed destination states from a given status.
     *
     * @return string[]
     */
    public function getAllowedTargets(string $currentStatus, bool $isAdmin = false): array
    {
        $targets = self::TRANSITIONS[$currentStatus] ?? [];
        if ($isAdmin && $currentStatus === 'proprio_pos_pgto') {
            $targets[] = 'em_estoque';
        }
        return $targets;
    }

    /**
     * Get all valid statuses.
     *
     * @return string[]
     */
    public static function allStatuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    /**
     * Initialize consignment_status for a product when it's created as consignado.
     */
    public function initializeForNewProduct(int $productId, int $supplierPessoaId, array $context = []): void
    {
        if (!$this->pdo) {
            return;
        }

        // Set initial consignment_status on the product
        $sql = "UPDATE products SET consignment_status = 'em_estoque' WHERE sku = :sku AND source = 'consignacao'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sku' => $productId]);

        // Create or update registry entry
        $this->registry->upsert([
            'product_id' => $productId,
            'supplier_pessoa_id' => $supplierPessoaId,
            'consignment_supplier_original_id' => $supplierPessoaId,
            'origin_type' => $context['origin_type'] ?? 'lote_produtos',
            'intake_id' => $context['intake_id'] ?? null,
            'consignment_id' => $context['consignment_id'] ?? null,
            'received_at' => $context['received_at'] ?? date('Y-m-d'),
            'consignment_percent_snapshot' => $context['consignment_percent_snapshot'] ?? null,
            'minimum_price_snapshot' => $context['minimum_price_snapshot'] ?? null,
            'consignment_status' => 'em_estoque',
            'status_changed_at' => date('Y-m-d H:i:s'),
            'status_changed_by' => $context['user_id'] ?? null,
        ]);
    }
}
