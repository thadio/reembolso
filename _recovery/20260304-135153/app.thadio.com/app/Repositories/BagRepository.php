<?php

namespace App\Repositories;

use App\Models\Bag;
use PDO;

use App\Support\AuditableTrait;
class BagRepository
{
    use AuditableTrait;

    private ?PDO $pdo;
    private ?bool $productsTableExists = null;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function all(?string $status = null): array
    {
        if (!$this->pdo) {
            return [];
        }
        $weightSelect = $this->weightTotalsSelect();
        $productsJoin = $this->weightTotalsJoin();
        $params = [];
        $where = '';
        if ($status) {
            $where = 'WHERE b.status = :status';
            $params[':status'] = $status;
        }

        $sql = "SELECT b.*,
                COALESCE(SUM(i.quantity), 0) AS items_qty,
                COALESCE(SUM(i.total_price), 0) AS items_total,
                {$weightSelect}
                FROM sacolinhas b
                LEFT JOIN sacolinha_itens i ON i.bag_id = b.id
                {$productsJoin}
                {$where}
                GROUP BY b.id
                ORDER BY b.opened_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listByPerson(int $pessoaId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $weightSelect = $this->weightTotalsSelect();
        $productsJoin = $this->weightTotalsJoin();
        $sql = "SELECT b.*,
                COALESCE(SUM(i.quantity), 0) AS items_qty,
                COALESCE(SUM(i.total_price), 0) AS items_total,
                {$weightSelect}
                FROM sacolinhas b
                LEFT JOIN sacolinha_itens i ON i.bag_id = b.id
                {$productsJoin}
                WHERE b.pessoa_id = :pessoa_id
                GROUP BY b.id
                ORDER BY b.opened_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pessoa_id' => $pessoaId]);
        return $stmt->fetchAll();
    }

    public function listOpenWithTotals(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $weightSelect = $this->weightTotalsSelect();
        $productsJoin = $this->weightTotalsJoin();

        $sql = "SELECT b.*,
                COALESCE(SUM(i.quantity), 0) AS items_qty,
                COALESCE(SUM(i.total_price), 0) AS items_total,
                {$weightSelect}
                FROM sacolinhas b
                LEFT JOIN sacolinha_itens i ON i.bag_id = b.id
                {$productsJoin}
                WHERE b.status = 'aberta'
                GROUP BY b.id
                ORDER BY b.opened_at ASC, b.id ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOpenSummaryByPerson(): array
    {
        $rows = $this->listOpenWithTotals();
        if (empty($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $personId = (int) ($row['pessoa_id'] ?? 0);
            $bagId = (int) ($row['id'] ?? 0);
            if ($personId <= 0 || $bagId <= 0) {
                continue;
            }
            if (isset($map[$personId]) && (int) ($map[$personId]['id'] ?? 0) >= $bagId) {
                continue;
            }

            $map[$personId] = [
                'id' => $bagId,
                'pessoa_id' => $personId,
                'opened_at' => (string) ($row['opened_at'] ?? ''),
                'expected_close_at' => (string) ($row['expected_close_at'] ?? ''),
                'items_qty' => (int) ($row['items_qty'] ?? 0),
                'items_total' => (float) ($row['items_total'] ?? 0),
                'items_weight' => (float) ($row['items_weight'] ?? 0),
                'opening_fee_value' => (float) ($row['opening_fee_value'] ?? 0),
                'opening_fee_paid' => !empty($row['opening_fee_paid']),
                'opening_fee_paid_at' => (string) ($row['opening_fee_paid_at'] ?? ''),
            ];
        }

        return $map;
    }

    public function countOpen(): int
    {
        if (!$this->pdo) {
            return 0;
        }
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM sacolinhas WHERE status = 'aberta'");
        $count = $stmt ? $stmt->fetchColumn() : 0;
        return (int) $count;
    }

    public function listOpenPersonIds(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT DISTINCT pessoa_id FROM sacolinhas WHERE status = 'aberta'");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (!$rows) {
            return [];
        }
        return array_map('intval', $rows);
    }

    public function find(int $id): ?Bag
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM sacolinhas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Bag::fromArray($row) : null;
    }

    public function findOpenByPerson(int $pessoaId): ?Bag
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM sacolinhas WHERE pessoa_id = :pessoa_id AND status = 'aberta' ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute([':pessoa_id' => $pessoaId]);
        $row = $stmt->fetch();
        return $row ? Bag::fromArray($row) : null;
    }

    public function save(Bag $bag): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $personId = (int) ($bag->personId ?? 0);
            $status = strtolower(trim((string) ($bag->status ?? 'aberta')));
            if ($personId > 0 && $status === 'aberta') {
                $conflictId = $this->findAnotherOpenBagId($personId, $bag->id ? (int) $bag->id : null, true);
                if ($conflictId !== null) {
                    throw new \RuntimeException('Cliente já possui sacolinha aberta (#' . $conflictId . ').');
                }
            }

            $isUpdate = (bool) $bag->id;
            $oldData = null;
            if ($isUpdate) {
                $stmt = $this->pdo->prepare("SELECT * FROM sacolinhas WHERE id = :id");
                $stmt->execute([':id' => $bag->id]);
                $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($bag->id) {
                $sql = "UPDATE sacolinhas
                    SET pessoa_id = :pessoa_id,
                        customer_name = :customer_name,
                        customer_email = :customer_email,
                        status = :status,
                        opened_at = :opened_at,
                        expected_close_at = :expected_close_at,
                        closed_at = :closed_at,
                        opening_fee_value = :opening_fee_value,
                        opening_fee_paid = :opening_fee_paid,
                        opening_fee_paid_at = :opening_fee_paid_at,
                        notes = :notes
                    WHERE id = :id";
                $params = $bag->toDbParams() + [':id' => $bag->id];
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "INSERT INTO sacolinhas
                    (pessoa_id, customer_name, customer_email, status, opened_at, expected_close_at, closed_at,
                     opening_fee_value, opening_fee_paid, opening_fee_paid_at, notes)
                    VALUES
                    (:pessoa_id, :customer_name, :customer_email, :status, :opened_at, :expected_close_at, :closed_at,
                     :opening_fee_value, :opening_fee_paid, :opening_fee_paid_at, :notes)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($bag->toDbParams());
                $bag->id = (int) $this->pdo->lastInsertId();
            }

            $stmt = $this->pdo->prepare("SELECT * FROM sacolinhas WHERE id = :id");
            $stmt->execute([':id' => $bag->id]);
            $newData = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->auditLog(
                $isUpdate ? 'UPDATE' : 'INSERT',
                'sacolinhas',
                $bag->id,
                $oldData,
                $newData
            );

            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function findAnotherOpenBagId(int $personId, ?int $excludeId = null, bool $forUpdate = false): ?int
    {
        if (!$this->pdo || $personId <= 0) {
            return null;
        }

        $sql = "SELECT id
                FROM sacolinhas
                WHERE pessoa_id = :pessoa_id
                  AND status = 'aberta'";
        $params = [':pessoa_id' => $personId];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= " ORDER BY id DESC LIMIT 1";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }

        $intId = (int) $id;
        return $intId > 0 ? $intId : null;
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM sacolinhas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM sacolinhas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'sacolinhas', $id, $oldData, null);
    }

    public function listItems(int $bagId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT * FROM sacolinha_itens WHERE bag_id = :bag_id ORDER BY purchased_at DESC, id DESC");
        $stmt->execute([':bag_id' => $bagId]);
        return $stmt->fetchAll();
    }

    public function listShipments(int $bagId): array
    {
        if (!$this->pdo || $bagId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT * FROM bag_shipments WHERE bag_id = :bag_id ORDER BY id DESC");
        $stmt->execute([':bag_id' => $bagId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function latestShipment(int $bagId): ?array
    {
        if (!$this->pdo || $bagId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM bag_shipments WHERE bag_id = :bag_id ORDER BY id DESC LIMIT 1");
        $stmt->execute([':bag_id' => $bagId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createShipment(int $bagId, array $payload): int
    {
        if (!$this->pdo || $bagId <= 0) {
            return 0;
        }

        $sql = "INSERT INTO bag_shipments
                (bag_id, status, carrier_id, tracking_code, estimated_delivery_at, shipped_at, delivered_at, notes)
                VALUES
                (:bag_id, :status, :carrier_id, :tracking_code, :estimated_delivery_at, :shipped_at, :delivered_at, :notes)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':bag_id' => $bagId,
            ':status' => (string) ($payload['status'] ?? 'shipped'),
            ':carrier_id' => isset($payload['carrier_id']) && (int) $payload['carrier_id'] > 0 ? (int) $payload['carrier_id'] : null,
            ':tracking_code' => trim((string) ($payload['tracking_code'] ?? '')) ?: null,
            ':estimated_delivery_at' => $this->normalizeDateTime($payload['estimated_delivery_at'] ?? null),
            ':shipped_at' => $this->normalizeDateTime($payload['shipped_at'] ?? null),
            ':delivered_at' => $this->normalizeDateTime($payload['delivered_at'] ?? null),
            ':notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateShipment(int $shipmentId, array $payload): bool
    {
        if (!$this->pdo || $shipmentId <= 0) {
            return false;
        }

        $sql = "UPDATE bag_shipments
                SET status = :status,
                    carrier_id = :carrier_id,
                    tracking_code = :tracking_code,
                    estimated_delivery_at = :estimated_delivery_at,
                    shipped_at = :shipped_at,
                    delivered_at = :delivered_at,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $shipmentId,
            ':status' => (string) ($payload['status'] ?? 'shipped'),
            ':carrier_id' => isset($payload['carrier_id']) && (int) $payload['carrier_id'] > 0 ? (int) $payload['carrier_id'] : null,
            ':tracking_code' => trim((string) ($payload['tracking_code'] ?? '')) ?: null,
            ':estimated_delivery_at' => $this->normalizeDateTime($payload['estimated_delivery_at'] ?? null),
            ':shipped_at' => $this->normalizeDateTime($payload['shipped_at'] ?? null),
            ':delivered_at' => $this->normalizeDateTime($payload['delivered_at'] ?? null),
            ':notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
        ]);
    }

    /**
     * @return int[]
     */
    public function listOrderIds(int $bagId): array
    {
        if (!$this->pdo || $bagId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT order_id
             FROM sacolinha_itens
             WHERE bag_id = :bag_id
               AND order_id IS NOT NULL
             ORDER BY order_id ASC"
        );
        $stmt->execute([':bag_id' => $bagId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) $row;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function getTotals(int $bagId): array
    {
        if (!$this->pdo) {
            return ['items_qty' => 0, 'items_total' => 0.0, 'items_weight' => 0.0];
        }
        $weightSelect = $this->supportsProductWeightTotals()
            ? 'COALESCE(SUM(i.quantity * COALESCE(p.weight, 0)), 0) AS items_weight'
            : '0 AS items_weight';
        $productsJoin = $this->supportsProductWeightTotals()
            ? 'LEFT JOIN products p ON p.sku = i.product_id'
            : '';
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(i.quantity), 0) AS items_qty,
                    COALESCE(SUM(i.total_price), 0) AS items_total,
                    {$weightSelect}
             FROM sacolinha_itens i
             {$productsJoin}
             WHERE i.bag_id = :bag_id"
        );
        $stmt->execute([':bag_id' => $bagId]);
        $row = $stmt->fetch();
        return [
            'items_qty' => (int) ($row['items_qty'] ?? 0),
            'items_total' => (float) ($row['items_total'] ?? 0),
            'items_weight' => (float) ($row['items_weight'] ?? 0),
        ];
    }

    public function hasOrderItems(int $bagId, int $orderId): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sacolinha_itens WHERE bag_id = :bag_id AND order_id = :order_id");
        $stmt->execute([':bag_id' => $bagId, ':order_id' => $orderId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * @param array<int, int> $orderIds
     * @return array<int, array<string, mixed>>
     */
    public function bagStatusByOrderIds(array $orderIds): array
    {
        if (!$this->pdo || empty($orderIds)) {
            return [];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), function ($id) {
            return $id > 0;
        })));
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "SELECT DISTINCT bi.order_id, b.id AS bag_id, b.status
                FROM sacolinha_itens bi
                JOIN sacolinhas b ON b.id = bi.bag_id
                WHERE bi.order_id IN ({$placeholders})
                  AND b.id = (
                    SELECT MAX(b2.id)
                    FROM sacolinha_itens bi2
                    JOIN sacolinhas b2 ON b2.id = bi2.bag_id
                    WHERE bi2.order_id = bi.order_id
                  )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($orderIds);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $map[$orderId] = [
                'bag_id' => (int) ($row['bag_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @param array<int, int> $orderIds
     * @return array<int, array<string, mixed>>
     */
    public function openingFeeStatusByOrderIds(array $orderIds): array
    {
        if (!$this->pdo || empty($orderIds)) {
            return [];
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "SELECT DISTINCT bi.order_id,
                    b.id AS bag_id,
                    b.status,
                    COALESCE(b.opening_fee_value, 0) AS opening_fee_value,
                    COALESCE(b.opening_fee_paid, 0) AS opening_fee_paid
                FROM sacolinha_itens bi
                JOIN sacolinhas b ON b.id = bi.bag_id
                WHERE bi.order_id IN ({$placeholders})
                  AND b.id = (
                    SELECT MAX(b2.id)
                    FROM sacolinha_itens bi2
                    JOIN sacolinhas b2 ON b2.id = bi2.bag_id
                    WHERE bi2.order_id = bi.order_id
                  )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($orderIds);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $map[$orderId] = [
                'bag_id' => (int) ($row['bag_id'] ?? 0),
                'bag_status' => (string) ($row['status'] ?? ''),
                'opening_fee_value' => (float) ($row['opening_fee_value'] ?? 0),
                'opening_fee_paid' => !empty($row['opening_fee_paid']),
            ];
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function addItems(int $bagId, array $items): void
    {
        if (!$this->pdo || empty($items)) {
            return;
        }
        $sql = "INSERT INTO sacolinha_itens
            (bag_id, order_id, product_id, variation_id, sku, name, description, image_url, quantity, unit_price, total_price, purchased_at)
            VALUES
            (:bag_id, :order_id, :product_id, :variation_id, :sku, :name, :description, :image_url, :quantity, :unit_price, :total_price, :purchased_at)";
        $stmt = $this->pdo->prepare($sql);
        foreach ($items as $item) {
            $stmt->execute([
                ':bag_id' => $bagId,
                ':order_id' => $item['order_id'] ?? null,
                ':product_id' => $item['product_id'] ?? null,
                ':variation_id' => $item['variation_id'] ?? null,
                ':sku' => $item['sku'] ?? null,
                ':name' => $item['name'] ?? '',
                ':description' => $item['description'] ?? null,
                ':image_url' => $item['image_url'] ?? null,
                ':quantity' => $item['quantity'] ?? 1,
                ':unit_price' => $item['unit_price'] ?? 0,
                ':total_price' => $item['total_price'] ?? 0,
                ':purchased_at' => $item['purchased_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS sacolinhas (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pessoa_id BIGINT UNSIGNED NOT NULL,
          customer_name VARCHAR(160) NULL,
          customer_email VARCHAR(160) NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'aberta',
          opened_at DATETIME NOT NULL,
          expected_close_at DATETIME NOT NULL,
          closed_at DATETIME NULL,
          opening_fee_value DECIMAL(10,2) NOT NULL DEFAULT 0,
          opening_fee_paid TINYINT(1) NOT NULL DEFAULT 0,
          opening_fee_paid_at DATETIME NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_sacolinhas_pessoa (pessoa_id),
          INDEX idx_sacolinhas_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->ensureColumn('pessoa_id', "ALTER TABLE sacolinhas ADD COLUMN pessoa_id BIGINT UNSIGNED NULL");
        $this->ensureIndex('idx_sacolinhas_pessoa', ['pessoa_id'], false);
        $this->removeLegacyCustomerColumn();

        $sqlItems = "CREATE TABLE IF NOT EXISTS sacolinha_itens (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          bag_id INT UNSIGNED NOT NULL,
          order_id INT UNSIGNED NULL,
          product_id BIGINT UNSIGNED NULL,
          variation_id INT UNSIGNED NULL,
          sku VARCHAR(80) NULL,
          name VARCHAR(200) NOT NULL,
          description TEXT NULL,
          image_url TEXT NULL,
          quantity INT UNSIGNED NOT NULL DEFAULT 1,
          unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
          total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
          purchased_at DATETIME NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_sacolinha_itens_bag (bag_id),
          INDEX idx_sacolinha_itens_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sqlItems);
        $this->ensureBagItemProductCompatibility();
        $this->ensureBagItemProductForeignKey();

        $sqlShipments = "CREATE TABLE IF NOT EXISTS bag_shipments (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          bag_id INT UNSIGNED NOT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'shipped',
          carrier_id INT UNSIGNED NULL,
          tracking_code VARCHAR(160) NULL,
          estimated_delivery_at DATETIME NULL,
          shipped_at DATETIME NULL,
          delivered_at DATETIME NULL,
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_bag_shipments_bag (bag_id),
          INDEX idx_bag_shipments_status (status),
          INDEX idx_bag_shipments_carrier (carrier_id),
          INDEX idx_bag_shipments_tracking (tracking_code),
          INDEX idx_bag_shipments_shipped (shipped_at),
          INDEX idx_bag_shipments_delivered (delivered_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sqlShipments);
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM sacolinhas LIKE :col");
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
        $stmt = $this->pdo->prepare("SHOW INDEX FROM sacolinhas WHERE Key_name = :name");
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
            $this->pdo->exec("ALTER TABLE sacolinhas DROP INDEX {$name}");
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
        $this->pdo->exec("ALTER TABLE sacolinhas ADD {$type} {$name} ({$cols})");
    }

    private function removeLegacyCustomerColumn(): void
    {
        $this->dropIndexIfExists('idx_sacolinhas_customer');
        $this->dropColumnIfExists('customer_id');
    }

    private function dropColumnIfExists(string $column): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM sacolinhas LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch() !== false;
        $stmt->closeCursor();
        if (!$exists) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE sacolinhas DROP COLUMN {$column}");
        } catch (\Throwable $e) {
            error_log("Falha ao remover coluna sacolinhas.{$column}: " . $e->getMessage());
        }
    }

    private function dropIndexIfExists(string $index): void
    {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM sacolinhas WHERE Key_name = :name");
        $stmt->execute([':name' => $index]);
        $exists = $stmt->fetch() !== false;
        $stmt->closeCursor();
        if (!$exists) {
            return;
        }
        try {
            $this->pdo->exec("ALTER TABLE sacolinhas DROP INDEX {$index}");
        } catch (\Throwable $e) {
            error_log("Falha ao remover índice sacolinhas.{$index}: " . $e->getMessage());
        }
    }

    private function ensureBagItemProductCompatibility(): void
    {
        if (!$this->pdo || !$this->columnExistsOnTable('sacolinha_itens', 'product_id')) {
            return;
        }

        $type = $this->columnTypeInfo('sacolinha_itens', 'product_id');
        if ($type === null) {
            return;
        }
        if ($type['data_type'] !== 'bigint' || $type['unsigned'] !== true || $type['nullable'] !== true) {
            try {
                $this->pdo->exec(
                    'ALTER TABLE sacolinha_itens
                     MODIFY COLUMN product_id BIGINT UNSIGNED NULL'
                );
            } catch (\Throwable $e) {
                error_log('Falha ao ajustar sacolinha_itens.product_id: ' . $e->getMessage());
            }
        }

        if (!$this->indexExistsOnTable('sacolinha_itens', 'idx_sacolinha_itens_product')) {
            try {
                $this->pdo->exec(
                    'ALTER TABLE sacolinha_itens
                     ADD INDEX idx_sacolinha_itens_product (product_id)'
                );
            } catch (\Throwable $e) {
                error_log('Falha ao criar índice de sacolinha_itens.product_id: ' . $e->getMessage());
            }
        }
    }

    private function ensureBagItemProductForeignKey(): void
    {
        if (
            !$this->pdo
            || !$this->columnExistsOnTable('sacolinha_itens', 'product_id')
            || !$this->columnExistsOnTable('products', 'sku')
        ) {
            return;
        }

        if ($this->foreignKeyExists('sacolinha_itens', 'fk_sacolinha_itens_product_sku')) {
            return;
        }
        if (!$this->isCompatibleForeignKey('sacolinha_itens', 'product_id', 'products', 'sku')) {
            return;
        }
        if ($this->countProductOrphans('sacolinha_itens', 'product_id') > 0) {
            return;
        }

        try {
            $this->pdo->exec(
                'ALTER TABLE sacolinha_itens
                 ADD CONSTRAINT fk_sacolinha_itens_product_sku
                 FOREIGN KEY (product_id) REFERENCES products(sku)
                 ON DELETE SET NULL
                 ON UPDATE CASCADE'
            );
        } catch (\Throwable $e) {
            error_log('Falha ao adicionar FK de sacolinha_itens.product_id: ' . $e->getMessage());
        }
    }

    private function columnExistsOnTable(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private function indexExistsOnTable(string $table, string $index): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :index_name
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':index_name' => $index,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :constraint
               AND CONSTRAINT_TYPE = "FOREIGN KEY"
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':constraint' => $constraint,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private function countProductOrphans(string $table, string $column): int
    {
        if (!$this->pdo || !$this->columnExistsOnTable($table, $column)) {
            return 0;
        }
        $sql = "SELECT COUNT(*)
                FROM {$table} t
                WHERE t.{$column} IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.{$column})";
        $stmt = $this->pdo->query($sql);
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }

    private function isCompatibleForeignKey(
        string $fromTable,
        string $fromColumn,
        string $toTable,
        string $toColumn
    ): bool {
        $fromType = $this->columnTypeInfo($fromTable, $fromColumn);
        $toType = $this->columnTypeInfo($toTable, $toColumn);
        if ($fromType === null || $toType === null) {
            return false;
        }

        return $fromType['data_type'] === $toType['data_type']
            && $fromType['unsigned'] === $toType['unsigned'];
    }

    /**
     * @return array{data_type:string,unsigned:bool,nullable:bool}|null
     */
    private function columnTypeInfo(string $table, string $column): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $columnType = strtolower((string) ($row['COLUMN_TYPE'] ?? ''));
        return [
            'data_type' => strtolower((string) ($row['DATA_TYPE'] ?? '')),
            'unsigned' => str_contains($columnType, 'unsigned'),
            'nullable' => strtoupper((string) ($row['IS_NULLABLE'] ?? 'YES')) === 'YES',
        ];
    }

    private function weightTotalsSelect(): string
    {
        if (!$this->supportsProductWeightTotals()) {
            return '0 AS items_weight';
        }

        return 'COALESCE(SUM(i.quantity * COALESCE(p.weight, 0)), 0) AS items_weight';
    }

    private function weightTotalsJoin(): string
    {
        if (!$this->supportsProductWeightTotals()) {
            return '';
        }

        return 'LEFT JOIN products p ON p.sku = i.product_id';
    }

    private function supportsProductWeightTotals(): bool
    {
        if (!$this->pdo) {
            return false;
        }
        if ($this->productsTableExists !== null) {
            return $this->productsTableExists;
        }

        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'products'");
            $this->productsTableExists = $stmt ? ($stmt->fetchColumn() !== false) : false;
        } catch (\Throwable $e) {
            $this->productsTableExists = false;
        }

        return $this->productsTableExists;
    }

    /**
     * @param mixed $value
     */
    private function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, 'T')) {
            $raw = str_replace('T', ' ', $raw);
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}
