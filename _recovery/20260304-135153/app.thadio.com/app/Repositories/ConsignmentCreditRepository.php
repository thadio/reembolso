<?php

namespace App\Repositories;

use PDO;

class ConsignmentCreditRepository
{
    private ?PDO $pdo;

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

    public function findByOrderAndVendor(int $orderId, int $vendorPessoaId): ?array
    {
        if (!$this->pdo || $orderId <= 0 || $vendorPessoaId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM consignacao_creditos
             WHERE order_id = :order_id AND vendor_pessoa_id = :vendor_pessoa_id
             LIMIT 1"
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':vendor_pessoa_id' => $vendorPessoaId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $vendorPessoaId = $this->resolveVendorPessoaId($data);
        $customerPessoaId = $this->resolveCustomerPessoaId($data);

        $stmt = $this->pdo->prepare(
            "INSERT INTO consignacao_creditos
                (order_id, vendor_pessoa_id, customer_pessoa_id, voucher_account_id, amount, items_count)
             VALUES
                (:order_id, :vendor_pessoa_id, :customer_pessoa_id, :voucher_account_id, :amount, :items_count)"
        );
        $stmt->execute([
            ':order_id' => $data['order_id'],
            ':vendor_pessoa_id' => $vendorPessoaId,
            ':customer_pessoa_id' => $customerPessoaId,
            ':voucher_account_id' => $data['voucher_account_id'],
            ':amount' => $data['amount'],
            ':items_count' => $data['items_count'] ?? 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function upsert(array $data): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $vendorPessoaId = $this->resolveVendorPessoaId($data);
        $customerPessoaId = $this->resolveCustomerPessoaId($data);

        $stmt = $this->pdo->prepare(
            "INSERT INTO consignacao_creditos
                (order_id, vendor_pessoa_id, customer_pessoa_id, voucher_account_id, amount, items_count)
             VALUES
                (:order_id, :vendor_pessoa_id, :customer_pessoa_id, :voucher_account_id, :amount, :items_count)
             ON DUPLICATE KEY UPDATE
                vendor_pessoa_id = VALUES(vendor_pessoa_id),
                customer_pessoa_id = VALUES(customer_pessoa_id),
                voucher_account_id = VALUES(voucher_account_id),
                amount = VALUES(amount),
                items_count = VALUES(items_count)"
        );
        $stmt->execute([
            ':order_id' => $data['order_id'],
            ':vendor_pessoa_id' => $vendorPessoaId,
            ':customer_pessoa_id' => $customerPessoaId,
            ':voucher_account_id' => $data['voucher_account_id'],
            ':amount' => $data['amount'],
            ':items_count' => $data['items_count'] ?? 0,
        ]);
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS consignacao_creditos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id BIGINT UNSIGNED NOT NULL,
          vendor_pessoa_id BIGINT UNSIGNED NULL,
          customer_pessoa_id BIGINT UNSIGNED NOT NULL,
          voucher_account_id INT UNSIGNED NOT NULL,
          amount DECIMAL(10,2) NOT NULL,
          items_count INT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_consignacao_credito (order_id),
          INDEX idx_consignacao_credito_order (order_id),
          INDEX idx_consignacao_credito_voucher (voucher_account_id),
          INDEX idx_consignacao_credito_vendor_pessoa (vendor_pessoa_id),
          INDEX idx_consignacao_credito_customer_pessoa (customer_pessoa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->ensureColumn('vendor_pessoa_id', "ALTER TABLE consignacao_creditos ADD COLUMN vendor_pessoa_id BIGINT UNSIGNED NULL");
        $this->ensureColumn('customer_pessoa_id', "ALTER TABLE consignacao_creditos ADD COLUMN customer_pessoa_id BIGINT UNSIGNED NULL");
        $this->ensureIndex('idx_consignacao_credito_vendor_pessoa', ['vendor_pessoa_id']);
        $this->ensureIndex('idx_consignacao_credito_customer_pessoa', ['customer_pessoa_id']);
        $this->ensureUniqueIndex('uniq_consignacao_credito', ['order_id']);
        $this->backfillPessoaIds();
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM consignacao_creditos LIKE :col");
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
    private function ensureIndex(string $name, array $columns): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM consignacao_creditos WHERE Key_name = :name");
        $stmt->execute([':name' => $name]);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        if ($rows) {
            return;
        }
        $cols = implode(', ', $columns);
        $this->pdo->exec("ALTER TABLE consignacao_creditos ADD INDEX {$name} ({$cols})");
    }

    /**
     * @param string[] $columns
     */
    private function ensureUniqueIndex(string $name, array $columns): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM consignacao_creditos WHERE Key_name = :name");
        $stmt->execute([':name' => $name]);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        if ($rows) {
            return;
        }
        $cols = implode(', ', $columns);
        $this->pdo->exec("ALTER TABLE consignacao_creditos ADD UNIQUE KEY {$name} ({$cols})");
    }

    private function backfillPessoaIds(): void
    {
        // hard-removal de aliases: manter apenas *_pessoa_id.
    }

    private function resolveVendorPessoaId(array $data): ?int
    {
        $vendorPessoaId = isset($data['vendor_pessoa_id']) ? (int) $data['vendor_pessoa_id'] : 0;
        return $vendorPessoaId > 0 ? $vendorPessoaId : null;
    }

    private function resolveCustomerPessoaId(array $data): ?int
    {
        $customerPessoaId = isset($data['customer_pessoa_id']) ? (int) $data['customer_pessoa_id'] : 0;
        return $customerPessoaId > 0 ? $customerPessoaId : null;
    }

    private function columnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM consignacao_creditos LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch() !== false;
        $stmt->closeCursor();
        return $exists;
    }
}
