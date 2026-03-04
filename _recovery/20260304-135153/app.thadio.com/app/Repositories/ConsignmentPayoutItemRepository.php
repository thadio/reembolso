<?php

namespace App\Repositories;

use App\Support\AuditableTrait;
use PDO;

class ConsignmentPayoutItemRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private const TABLE = 'consignment_payout_items';

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    // ─── QUERIES ────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByPayout(int $payoutId): array
    {
        if (!$this->pdo || $payoutId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT pi.*, s.product_id AS sale_product_id, s.order_id AS sale_order_id,
                    s.credit_amount AS sale_credit_amount, s.percent_applied AS sale_percent,
                    COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', pi.product_id)) AS product_name,
                    COALESCE(
                        NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                        TRIM(CAST(pi.product_id AS CHAR))
                    ) AS sku
             FROM " . self::TABLE . " pi
             LEFT JOIN consignment_sales s ON s.id = pi.consignment_sale_id
             LEFT JOIN order_items oi ON oi.id = COALESCE(s.order_item_id, pi.order_item_id)
             LEFT JOIN products p  ON p.sku  = pi.product_id
             LEFT JOIN products p2 ON p2.sku = oi.product_sku
             WHERE pi.payout_id = :pid
             ORDER BY pi.id ASC"
        );
        $stmt->execute([':pid' => $payoutId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a sale is already in any payout item.
     */
    public function saleAlreadyPaid(int $consignmentSaleId): bool
    {
        if (!$this->pdo || $consignmentSaleId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM " . self::TABLE . "
             WHERE consignment_sale_id = :sid"
        );
        $stmt->execute([':sid' => $consignmentSaleId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    // ─── MUTATIONS ──────────────────────────────────────────────

    /**
     * Create a payout item. Returns the inserted ID.
     */
    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO " . self::TABLE . " (
                  payout_id, consignment_sale_id, product_id, order_id, order_item_id,
                  amount, percent_applied, ledger_debit_movement_id
                ) VALUES (
                  :payout_id, :consignment_sale_id, :product_id, :order_id, :order_item_id,
                  :amount, :percent_applied, :ledger_debit_movement_id
                )";

        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute([
                ':payout_id' => $data['payout_id'],
                ':consignment_sale_id' => $data['consignment_sale_id'],
                ':product_id' => $data['product_id'],
                ':order_id' => $data['order_id'],
                ':order_item_id' => $data['order_item_id'],
                ':amount' => $data['amount'] ?? 0,
                ':percent_applied' => $data['percent_applied'] ?? null,
                ':ledger_debit_movement_id' => $data['ledger_debit_movement_id'] ?? null,
            ]);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return 0; // duplicate
            }
            throw $e;
        }

        $id = (int) $this->pdo->lastInsertId();
        $this->auditLog('create', 'consignment_payout_item', $id, null, $data);
        return $id;
    }

    /**
     * Update ledger_debit_movement_id after ledger entry is created.
     */
    public function setLedgerDebitMovementId(int $id, int $movementId): void
    {
        if (!$this->pdo || $id <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE " . self::TABLE . " SET ledger_debit_movement_id = :mid WHERE id = :id"
        );
        $stmt->execute([':mid' => $movementId, ':id' => $id]);
    }

    /**
     * Delete all items for a payout (for cancellation).
     */
    public function deleteByPayout(int $payoutId): void
    {
        if (!$this->pdo || $payoutId <= 0) {
            return;
        }
        // Audit before delete
        $items = $this->listByPayout($payoutId);
        foreach ($items as $item) {
            $this->auditLog('delete', 'consignment_payout_item', (int) $item['id'], $item, null);
        }

        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE payout_id = :pid");
        $stmt->execute([':pid' => $payoutId]);
    }

    // ─── SCHEMA ─────────────────────────────────────────────────

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          payout_id BIGINT UNSIGNED NOT NULL,
          consignment_sale_id BIGINT UNSIGNED NOT NULL,
          product_id BIGINT UNSIGNED NOT NULL,
          order_id BIGINT UNSIGNED NOT NULL,
          order_item_id BIGINT UNSIGNED NOT NULL,
          amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          percent_applied DECIMAL(5,2) NULL,
          ledger_debit_movement_id BIGINT UNSIGNED NULL COMMENT 'FK cupons_creditos_movimentos.id do débito',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_payout_sale (payout_id, consignment_sale_id),
          INDEX idx_payout_item_payout (payout_id),
          INDEX idx_payout_item_sale (consignment_sale_id),
          INDEX idx_payout_item_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }
}
