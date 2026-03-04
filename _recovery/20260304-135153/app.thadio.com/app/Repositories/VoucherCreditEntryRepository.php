<?php

namespace App\Repositories;

use App\Repositories\PeopleCompatViewRepository;
use PDO;

class VoucherCreditEntryRepository
{
    private ?PDO $pdo;
    private const TABLE = 'cupons_creditos_movimentos';
    private const UNIQUE_INDEX = 'uniq_voucher_item';

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            PeopleCompatViewRepository::ensure($this->pdo);
            $this->ensureTable();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function findByOrderItem(int $voucherId, int $orderId, int $orderItemId): ?array
    {
        if (!$this->pdo || $voucherId <= 0 || $orderId <= 0 || $orderItemId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM " . self::TABLE . "
             WHERE voucher_account_id = :voucher_id
               AND order_id = :order_id
               AND order_item_id = :order_item_id
             LIMIT 1"
        );
        $stmt->execute([
            ':voucher_id' => $voucherId,
            ':order_id' => $orderId,
            ':order_item_id' => $orderItemId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find a single movement by its ID.
     */
    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update specific columns of a movement by its ID.
     *
     * @param int $id
     * @param array<string, mixed> $data  Columns to update
     */
    public function update(int $id, array $data): void
    {
        if (!$this->pdo || $id <= 0 || empty($data)) {
            return;
        }
        $sets = [];
        $params = [':id' => $id];
        foreach ($data as $col => $val) {
            $placeholder = ':' . $col;
            $sets[] = "`{$col}` = {$placeholder}";
            $params[$placeholder] = $val;
        }
        $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function insert(array $data): bool
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO " . self::TABLE . " (
                  voucher_account_id, vendor_pessoa_id, order_id, order_item_id, product_id, variation_id,
                  sku, product_name, quantity, unit_price, line_total, percent, credit_amount,
                  sold_at, buyer_name, buyer_email, type, event_type, event_id, event_label,
                  event_notes, event_at
                ) VALUES (
                  :voucher_account_id, :vendor_pessoa_id, :order_id, :order_item_id, :product_id, :variation_id,
                  :sku, :product_name, :quantity, :unit_price, :line_total, :percent, :credit_amount,
                  :sold_at, :buyer_name, :buyer_email, :type, :event_type, :event_id, :event_label,
                  :event_notes, :event_at
                )";
        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute([
                ':voucher_account_id' => $data['voucher_account_id'],
                ':vendor_pessoa_id' => $data['vendor_pessoa_id'] ?? null,
                ':order_id' => $data['order_id'],
                ':order_item_id' => $data['order_item_id'],
                ':product_id' => $data['product_id'],
                ':variation_id' => $data['variation_id'],
                ':sku' => $data['sku'],
                ':product_name' => $data['product_name'],
                ':quantity' => $data['quantity'],
                ':unit_price' => $data['unit_price'],
                ':line_total' => $data['line_total'],
                ':percent' => $data['percent'],
                ':credit_amount' => $data['credit_amount'],
                ':sold_at' => $data['sold_at'],
                ':buyer_name' => $data['buyer_name'],
                ':buyer_email' => $data['buyer_email'],
                ':type' => $data['type'] ?? 'credito',
                ':event_type' => $data['event_type'] ?? 'sale',
                ':event_id' => $data['event_id'] ?? 0,
                ':event_label' => $data['event_label'] ?? null,
                ':event_notes' => $data['event_notes'] ?? null,
                ':event_at' => $data['event_at'] ?? null,
            ]);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function listByVoucher(int $voucherId): array
    {
        if (!$this->pdo || $voucherId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM " . self::TABLE . "
             WHERE voucher_account_id = :voucher_id
             ORDER BY COALESCE(event_at, sold_at, created_at) ASC, id ASC"
        );
        $stmt->execute([':voucher_id' => $voucherId]);
        return $stmt->fetchAll();
    }

    /**
     * @param int[] $voucherIds
     * @return array<int, array<string, mixed>>
     */
    public function listByVoucherIds(array $voucherIds): array
    {
        if (!$this->pdo) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $voucherIds), function (int $id): bool {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM " . self::TABLE . "
             WHERE voucher_account_id IN ({$placeholders})
             ORDER BY COALESCE(event_at, sold_at, created_at) ASC, id ASC"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    public function listByOrder(int $orderId, ?string $type = null): array
    {
        if (!$this->pdo || $orderId <= 0) {
            return [];
        }

        $sql = "SELECT *
                FROM " . self::TABLE . "
                WHERE order_id = :order_id";
        $params = [':order_id' => $orderId];
        if ($type !== null && $type !== '') {
            $sql .= " AND type = :type";
            $params[':type'] = $type;
        }
        $sql .= " ORDER BY COALESCE(event_at, sold_at, created_at) ASC, id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function sumConsignmentCreditsByVendor(int $limit = 50): array
    {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, $limit);

        $sql = "SELECT m.vendor_pessoa_id,
                       COALESCE(f.full_name, CONCAT('Fornecedor ', m.vendor_pessoa_id)) AS vendor_name,
                       SUM(m.credit_amount) AS total_credit
                FROM " . self::TABLE . " m
                LEFT JOIN vw_fornecedores_compat f ON f.id = m.vendor_pessoa_id
                WHERE m.type = 'credito'
                  AND m.vendor_pessoa_id IS NOT NULL
                  AND m.vendor_pessoa_id > 0
                  AND m.percent IS NOT NULL
                  AND m.percent > 0
                  AND m.credit_amount > 0
                  AND m.event_type = 'sale'
                GROUP BY m.vendor_pessoa_id, vendor_name
                ORDER BY total_credit DESC
                LIMIT :limit_rows";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function listByEvent(int $orderId, string $type, string $eventType, int $eventId): array
    {
        if (!$this->pdo || $orderId <= 0 || $eventType === '' || $eventId < 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM " . self::TABLE . "
             WHERE order_id = :order_id
               AND type = :type
               AND event_type = :event_type
               AND event_id = :event_id
             ORDER BY COALESCE(event_at, sold_at, created_at) ASC, id ASC"
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':type' => $type,
            ':event_type' => $eventType,
            ':event_id' => $eventId,
        ]);
        return $stmt->fetchAll();
    }

    public function sumCreditsByVoucher(int $voucherId): float
    {
        if (!$this->pdo || $voucherId <= 0) {
            return 0.0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT SUM(credit_amount) AS total
             FROM " . self::TABLE . "
             WHERE voucher_account_id = :voucher_id
               AND type = 'credito'"
        );
        $stmt->execute([':voucher_id' => $voucherId]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (float) $value : 0.0;
    }

    public function sumDebitsByVoucher(int $voucherId): float
    {
        if (!$this->pdo || $voucherId <= 0) {
            return 0.0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT SUM(credit_amount) AS total
             FROM " . self::TABLE . "
             WHERE voucher_account_id = :voucher_id
               AND type = 'debito'"
        );
        $stmt->execute([':voucher_id' => $voucherId]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (float) $value : 0.0;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          voucher_account_id INT UNSIGNED NOT NULL,
          vendor_pessoa_id BIGINT UNSIGNED NULL,
          order_id BIGINT UNSIGNED NOT NULL,
          order_item_id BIGINT UNSIGNED NOT NULL,
          product_id BIGINT UNSIGNED NULL,
          variation_id BIGINT UNSIGNED NULL,
          sku VARCHAR(100) NULL,
          product_name VARCHAR(240) NULL,
          quantity INT UNSIGNED NOT NULL DEFAULT 1,
          unit_price DECIMAL(10,2) NULL,
          line_total DECIMAL(10,2) NULL,
          percent DECIMAL(5,2) NULL,
          credit_amount DECIMAL(10,2) NOT NULL,
          sold_at DATETIME NULL,
          buyer_name VARCHAR(200) NULL,
          buyer_email VARCHAR(200) NULL,
          type VARCHAR(20) NOT NULL DEFAULT 'credito',
          event_type VARCHAR(40) NOT NULL DEFAULT 'sale',
          event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
          event_label VARCHAR(200) NULL,
          event_notes VARCHAR(255) NULL,
          event_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_voucher_item (voucher_account_id, order_id, order_item_id, type, event_type, event_id),
          INDEX idx_voucher (voucher_account_id),
          INDEX idx_vendor_pessoa (vendor_pessoa_id),
          INDEX idx_order (order_id),
          INDEX idx_event (event_type, event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->ensureColumn('vendor_pessoa_id', "ALTER TABLE " . self::TABLE . " ADD COLUMN vendor_pessoa_id BIGINT UNSIGNED NULL");
        $this->ensureColumn('event_type', "ALTER TABLE " . self::TABLE . " ADD COLUMN event_type VARCHAR(40) NOT NULL DEFAULT 'sale' AFTER type");
        $this->ensureColumn('event_id', "ALTER TABLE " . self::TABLE . " ADD COLUMN event_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER event_type");
        $this->ensureColumn('event_label', "ALTER TABLE " . self::TABLE . " ADD COLUMN event_label VARCHAR(200) NULL AFTER event_id");
        $this->ensureColumn('event_notes', "ALTER TABLE " . self::TABLE . " ADD COLUMN event_notes VARCHAR(255) NULL AFTER event_label");
        $this->ensureColumn('event_at', "ALTER TABLE " . self::TABLE . " ADD COLUMN event_at DATETIME NULL AFTER event_notes");
        $this->ensureIndex('idx_vendor_pessoa', ['vendor_pessoa_id'], false);
        $this->ensureIndex(self::UNIQUE_INDEX, [
            'voucher_account_id',
            'order_id',
            'order_item_id',
            'type',
            'event_type',
            'event_id',
        ], true);
        $this->ensureIndex('idx_event', ['event_type', 'event_id'], false);
        $this->backfillVendorPessoaId();

        // Módulo de consignação: payout_id para vincular movimentos a payouts
        $this->ensureColumn('payout_id', "ALTER TABLE " . self::TABLE . " ADD COLUMN payout_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER event_at");
        $this->ensureIndex('idx_credit_movement_payout', ['payout_id'], false);
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM " . self::TABLE . " LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
            $this->pdo->exec($ddl);
        }
    }

    /**
     * @param string[] $columns
     */
    private function ensureIndex(string $name, array $columns, bool $unique): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM " . self::TABLE . " WHERE Key_name = :name");
        $stmt->execute([':name' => $name]);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        if (!$rows) {
            $this->createIndex($name, $columns, $unique);
            return;
        }

        $current = [];
        foreach ($rows as $row) {
            $seq = (int) ($row['Seq_in_index'] ?? 0);
            if ($seq > 0) {
                $current[$seq] = (string) ($row['Column_name'] ?? '');
            }
        }
        ksort($current);
        $current = array_values(array_filter($current, static function (string $col): bool {
            return $col !== '';
        }));

        if ($current !== $columns) {
            $this->pdo->exec("ALTER TABLE " . self::TABLE . " DROP INDEX {$name}");
            $this->createIndex($name, $columns, $unique);
        }
    }

    /**
     * @param string[] $columns
     */
    private function createIndex(string $name, array $columns, bool $unique): void
    {
        $cols = implode(', ', $columns);
        $type = $unique ? 'UNIQUE KEY' : 'INDEX';
        $this->pdo->exec("ALTER TABLE " . self::TABLE . " ADD {$type} {$name} ({$cols})");
    }

    private function backfillVendorPessoaId(): void
    {
        // hard-removal de aliases: manter apenas vendor_pessoa_id.
    }

    private function columnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM " . self::TABLE . " LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch() !== false;
        $stmt->closeCursor();
        return $exists;
    }
}
