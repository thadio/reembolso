<?php

namespace App\Repositories;

use PDO;

class InventoryRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTables();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function getOpen(): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->query("SELECT * FROM inventarios WHERE status = 'aberto' ORDER BY id DESC LIMIT 1");
        $row = $stmt ? $stmt->fetch() : null;
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM inventarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listInventories(int $limit = 50, int $offset = 0): array
    {
        if (!$this->pdo) {
            return [];
        }
        $limit = $limit < 0 ? 0 : $limit;
        $offset = $offset < 0 ? 0 : $offset;
        if ($limit === 0) {
            $stmt = $this->pdo->query('SELECT * FROM inventarios ORDER BY id DESC');
            return $stmt ? $stmt->fetchAll() : [];
        }

        $limit = min(200, $limit);
        $sql = 'SELECT * FROM inventarios ORDER BY id DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countInventories(): int
    {
        if (!$this->pdo) {
            return 0;
        }
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM inventarios');
        $count = $stmt ? $stmt->fetchColumn() : 0;
        return (int) $count;
    }

    public function nextId(): int
    {
        if (!$this->pdo) {
            return 1;
        }
        $stmt = $this->pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventarios'");
        $row = $stmt ? $stmt->fetch() : null;
        return $row && $row['AUTO_INCREMENT'] ? (int) $row['AUTO_INCREMENT'] : 1;
    }

    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO inventarios (status, blind_count, default_reason, opened_by, opened_at) ' .
            'VALUES (:status, :blind_count, :default_reason, :opened_by, NOW())'
        );
        $stmt->execute([
            ':status' => $data['status'] ?? 'aberto',
            ':blind_count' => !empty($data['blind_count']) ? 1 : 0,
            ':default_reason' => $data['default_reason'] ?? null,
            ':opened_by' => $data['opened_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateDefaultReason(int $inventoryId, string $reason): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE inventarios SET default_reason = :reason WHERE id = :id');
        $stmt->execute([
            ':reason' => $reason,
            ':id' => $inventoryId,
        ]);
    }

    public function close(int $inventoryId, int $userId): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        $stmt = $this->pdo->prepare(
            "UPDATE inventarios SET status = 'fechado', closed_by = :user_id, closed_at = NOW() WHERE id = :id AND status = 'aberto'"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':id' => $inventoryId,
        ]);
    }

    public function reopen(int $inventoryId): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        $stmt = $this->pdo->prepare(
            "UPDATE inventarios SET status = 'aberto', closed_by = NULL, closed_at = NULL WHERE id = :id AND status = 'fechado'"
        );
        $stmt->execute([
            ':id' => $inventoryId,
        ]);
    }

    public function getItem(int $inventoryId, int $productId): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT i.*, u.full_name AS last_user_name ' .
            'FROM inventario_itens i ' .
            'LEFT JOIN usuarios u ON u.id = i.last_user_id ' .
            'WHERE i.inventario_id = :inventory_id AND i.product_id = :product_id ' .
            'LIMIT 1'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function applyScan(
        int $inventoryId,
        int $productId,
        string $sku,
        ?string $productName,
        int $userId,
        int $quantity,
        string $mode
    ): ?array {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $quantity = max(1, (int) $quantity);
        $mode = in_array($mode, ['increment', 'override', 'ignore', 'adjust'], true) ? $mode : 'increment';

        if ($mode === 'ignore') {
            $this->logScan($inventoryId, $productId, $sku, $productName, $userId, $mode, $quantity);
            return $this->getItem($inventoryId, $productId);
        }

        $sqlBase = 'INSERT INTO inventario_itens ' .
            '(inventario_id, product_id, sku, product_name, counted_quantity, scan_count, first_scan_at, last_scan_at, last_user_id) ' .
            'VALUES (:inventory_id, :product_id, :sku, :product_name, :counted_quantity, 1, NOW(), NOW(), :user_id) ' .
            'ON DUPLICATE KEY UPDATE ' .
            'last_scan_at = NOW(), last_user_id = VALUES(last_user_id), ' .
            'product_name = VALUES(product_name), ' .
            'first_scan_at = IFNULL(first_scan_at, VALUES(first_scan_at)), ';

        if ($mode === 'increment') {
            $sql = $sqlBase .
                'counted_quantity = counted_quantity + VALUES(counted_quantity), ' .
                'scan_count = scan_count + 1';
        } else {
            $sql = $sqlBase .
                'counted_quantity = VALUES(counted_quantity), ' .
                'scan_count = scan_count + 1';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
            ':sku' => $sku,
            ':product_name' => $productName,
            ':counted_quantity' => $quantity,
            ':user_id' => $userId,
        ]);

        $this->logScan($inventoryId, $productId, $sku, $productName, $userId, $mode, $quantity);

        return $this->getItem($inventoryId, $productId);
    }

    public function updateCountedQuantity(
        int $inventoryId,
        int $productId,
        string $sku,
        ?string $productName,
        int $userId,
        int $quantity
    ): ?array {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $quantity = max(0, (int) $quantity);

        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_itens ' .
            '(inventario_id, product_id, sku, product_name, counted_quantity, scan_count, first_scan_at, last_scan_at, last_user_id) ' .
            'VALUES (:inventory_id, :product_id, :sku, :product_name, :counted_quantity, 1, NOW(), NOW(), :user_id) ' .
            'ON DUPLICATE KEY UPDATE ' .
            'counted_quantity = VALUES(counted_quantity), ' .
            'last_scan_at = NOW(), last_user_id = VALUES(last_user_id), ' .
            'product_name = VALUES(product_name), sku = VALUES(sku), ' .
            'first_scan_at = IFNULL(first_scan_at, VALUES(first_scan_at)), ' .
            'scan_count = scan_count'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
            ':sku' => $sku,
            ':product_name' => $productName,
            ':counted_quantity' => $quantity,
            ':user_id' => $userId,
        ]);

        return $this->getItem($inventoryId, $productId);
    }

    public function overwriteLastScan(
        int $inventoryId,
        int $productId,
        string $sku,
        ?string $productName,
        int $userId,
        string $action,
        int $quantity
    ): void {
        if (!$this->pdo) {
            return;
        }

        $action = in_array($action, ['increment', 'override', 'ignore', 'adjust'], true) ? $action : 'adjust';
        $quantity = max(0, (int) $quantity);

        $stmt = $this->pdo->prepare(
            'SELECT id FROM inventario_scans ' .
            'WHERE inventario_id = :inventory_id AND product_id = :product_id ' .
            'ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
        ]);
        $row = $stmt->fetch();

        if ($row && isset($row['id'])) {
            $update = $this->pdo->prepare(
                'UPDATE inventario_scans SET sku = :sku, product_name = :product_name, user_id = :user_id, ' .
                'action = :action, quantity = :quantity, created_at = NOW() WHERE id = :id'
            );
            $update->execute([
                ':sku' => $sku,
                ':product_name' => $productName,
                ':user_id' => $userId,
                ':action' => $action,
                ':quantity' => $quantity,
                ':id' => (int) $row['id'],
            ]);
            return;
        }

        $this->logScan($inventoryId, $productId, $sku, $productName, $userId, $action, $quantity);
    }

    public function listRecentScans(int $inventoryId, int $limit = 8): array
    {
        if (!$this->pdo) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.full_name AS user_name ' .
            'FROM inventario_scans s ' .
            'LEFT JOIN usuarios u ON u.id = s.user_id ' .
            'WHERE s.inventario_id = :inventory_id ' .
            'ORDER BY s.id DESC ' .
            'LIMIT ' . $limit
        );
        $stmt->execute([':inventory_id' => $inventoryId]);
        return $stmt->fetchAll();
    }

    public function listScans(int $inventoryId, string $query = '', int $limit = 50, int $offset = 0): array
    {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $query = trim($query);

        $where = 's.inventario_id = :inventory_id';
        $params = [':inventory_id' => $inventoryId];
        if ($query !== '') {
            $where .= ' AND (s.sku LIKE :term OR s.product_name LIKE :term)';
            $params[':term'] = '%' . $query . '%';
        }

        $sql = 'SELECT s.*, u.full_name AS user_name ' .
            'FROM inventario_scans s ' .
            'LEFT JOIN usuarios u ON u.id = s.user_id ' .
            'WHERE ' . $where . ' ' .
            'ORDER BY s.id DESC ' .
            'LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findScan(int $inventoryId, int $scanId): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inventario_scans WHERE inventario_id = :inventory_id AND id = :id LIMIT 1'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':id' => $scanId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteScan(int $inventoryId, int $scanId): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM inventario_scans WHERE inventario_id = :inventory_id AND id = :id'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':id' => $scanId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function recalculateItemFromScans(int $inventoryId, int $productId): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM inventario_scans WHERE inventario_id = :inventory_id AND product_id = :product_id ORDER BY id ASC'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
        ]);
        $rows = $stmt->fetchAll();

        $counted = 0;
        $scanCount = 0;
        $firstScanAt = null;
        $lastScanAt = null;
        $lastUserId = null;
        $lastSku = '';
        $lastName = null;

        foreach ($rows as $row) {
            $action = (string) ($row['action'] ?? '');
            if ($action === 'ignore') {
                continue;
            }
            $quantity = max(0, (int) ($row['quantity'] ?? 0));
            if ($action === 'increment') {
                $counted += $quantity;
            } elseif (in_array($action, ['override', 'adjust'], true)) {
                $counted = $quantity;
            }
            $scanCount += 1;
            $lastSku = (string) ($row['sku'] ?? $lastSku);
            $lastName = (string) ($row['product_name'] ?? $lastName);
            $lastScanAt = $row['created_at'] ?? $lastScanAt;
            $lastUserId = isset($row['user_id']) ? (int) $row['user_id'] : $lastUserId;
            if ($firstScanAt === null) {
                $firstScanAt = $lastScanAt;
            }
        }

        if ($scanCount === 0) {
            $stmt = $this->pdo->prepare(
                'DELETE FROM inventario_itens WHERE inventario_id = :inventory_id AND product_id = :product_id'
            );
            $stmt->execute([
                ':inventory_id' => $inventoryId,
                ':product_id' => $productId,
            ]);
            return null;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_itens ' .
            '(inventario_id, product_id, sku, product_name, counted_quantity, scan_count, first_scan_at, last_scan_at, last_user_id) ' .
            'VALUES (:inventory_id, :product_id, :sku, :product_name, :counted_quantity, :scan_count, :first_scan_at, :last_scan_at, :last_user_id) ' .
            'ON DUPLICATE KEY UPDATE ' .
            'sku = VALUES(sku), product_name = VALUES(product_name), counted_quantity = VALUES(counted_quantity), ' .
            'scan_count = VALUES(scan_count), first_scan_at = VALUES(first_scan_at), last_scan_at = VALUES(last_scan_at), ' .
            'last_user_id = VALUES(last_user_id)'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
            ':sku' => $lastSku,
            ':product_name' => $lastName,
            ':counted_quantity' => $counted,
            ':scan_count' => $scanCount,
            ':first_scan_at' => $firstScanAt,
            ':last_scan_at' => $lastScanAt,
            ':last_user_id' => $lastUserId,
        ]);

        return $this->getItem($inventoryId, $productId);
    }

    public function listItemIds(int $inventoryId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT product_id FROM inventario_itens WHERE inventario_id = :inventory_id');
        $stmt->execute([':inventory_id' => $inventoryId]);
        $rows = $stmt->fetchAll();
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['product_id'];
        }
        return $ids;
    }

    public function summary(int $inventoryId): array
    {
        if (!$this->pdo) {
            return ['unique_items' => 0, 'total_counted' => 0, 'total_scans' => 0];
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS unique_items, COALESCE(SUM(counted_quantity), 0) AS total_counted, ' .
            'COALESCE(SUM(scan_count), 0) AS total_scans ' .
            'FROM inventario_itens WHERE inventario_id = :inventory_id'
        );
        $stmt->execute([':inventory_id' => $inventoryId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['unique_items' => 0, 'total_counted' => 0, 'total_scans' => 0];
        }
        return [
            'unique_items' => (int) ($row['unique_items'] ?? 0),
            'total_counted' => (int) ($row['total_counted'] ?? 0),
            'total_scans' => (int) ($row['total_scans'] ?? 0),
        ];
    }

    public function logAdjustment(
        int $inventoryId,
        int $productId,
        string $sku,
        string $action,
        ?array $before,
        ?array $after,
        ?string $reason,
        int $userId
    ): void {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_logs ' .
            '(inventario_id, product_id, sku, action, before_json, after_json, reason, user_id, created_at) ' .
            'VALUES (:inventory_id, :product_id, :sku, :action, :before_json, :after_json, :reason, :user_id, NOW())'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
            ':sku' => $sku,
            ':action' => $action,
            ':before_json' => $this->encodeJson($before),
            ':after_json' => $this->encodeJson($after),
            ':reason' => $reason,
            ':user_id' => $userId,
        ]);
    }

    public function savePendingItems(int $inventoryId, array $rows): void
    {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM inventario_pendentes WHERE inventario_id = :inventory_id');
        $stmt->execute([':inventory_id' => $inventoryId]);

        if (empty($rows)) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO inventario_pendentes ' .
            '(inventario_id, product_id, sku, product_name, quantity, availability_status, created_at) ' .
            'VALUES (:inventory_id, :product_id, :sku, :product_name, :quantity, :availability_status, NOW())'
        );

        foreach ($rows as $row) {
            $insert->execute([
                ':inventory_id' => $inventoryId,
                ':product_id' => (int) ($row['id'] ?? 0),
                ':sku' => (string) ($row['sku'] ?? ''),
                ':product_name' => (string) ($row['name'] ?? ''),
                ':quantity' => isset($row['quantity']) && $row['quantity'] !== null ? (int) $row['quantity'] : null,
                ':availability_status' => (string) ($row['availability_status'] ?? ''),
            ]);
        }
    }

    public function listPending(int $inventoryId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id,
                    inventario_id,
                    product_id,
                    sku,
                    product_name,
                    quantity,
                    availability_status,
                    resolved_action,
                    resolved_by,
                    resolved_at,
                    created_at
             FROM inventario_pendentes
             WHERE inventario_id = :inventory_id
             ORDER BY product_name ASC'
        );
        $stmt->execute([':inventory_id' => $inventoryId]);
        return $stmt->fetchAll();
    }

    public function listItems(int $inventoryId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT i.*, u.full_name AS last_user_name ' .
            'FROM inventario_itens i ' .
            'LEFT JOIN usuarios u ON u.id = i.last_user_id ' .
            'WHERE i.inventario_id = :inventory_id ' .
            'ORDER BY i.last_scan_at DESC, i.id DESC'
        );
        $stmt->execute([':inventory_id' => $inventoryId]);
        return $stmt->fetchAll();
    }

    public function listLogs(int $inventoryId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT l.*, u.full_name AS user_name ' .
            'FROM inventario_logs l ' .
            'LEFT JOIN usuarios u ON u.id = l.user_id ' .
            'WHERE l.inventario_id = :inventory_id ' .
            'ORDER BY l.id DESC'
        );
        $stmt->execute([':inventory_id' => $inventoryId]);
        return $stmt->fetchAll();
    }

    public function markPendingResolved(int $inventoryId, array $ids, string $action, int $userId): void
    {
        if (!$this->pdo || empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = [
            ':inventory_id' => $inventoryId,
            ':action' => $action,
            ':user_id' => $userId,
        ];
        foreach ($ids as $index => $id) {
            $key = ':id' . $index;
            $placeholders[] = $key;
            $params[$key] = (int) $id;
        }

        $sql = 'UPDATE inventario_pendentes SET resolved_action = :action, resolved_by = :user_id, resolved_at = NOW() ' .
            'WHERE inventario_id = :inventory_id AND id IN (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function logScan(
        int $inventoryId,
        int $productId,
        string $sku,
        ?string $productName,
        int $userId,
        string $action,
        int $quantity
    ): void {
        if (!$this->pdo) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventario_scans ' .
            '(inventario_id, product_id, sku, product_name, user_id, action, quantity, created_at) ' .
            'VALUES (:inventory_id, :product_id, :sku, :product_name, :user_id, :action, :quantity, NOW())'
        );
        $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':product_id' => $productId,
            ':sku' => $sku,
            ':product_name' => $productName,
            ':user_id' => $userId,
            ':action' => $action,
            ':quantity' => $quantity,
        ]);
    }

    private function encodeJson(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private function ensureTables(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS inventarios (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              status VARCHAR(20) NOT NULL DEFAULT 'aberto',
              blind_count TINYINT(1) NOT NULL DEFAULT 0,
              default_reason VARCHAR(255) NULL,
              opened_by INT UNSIGNED NULL,
              opened_at DATETIME NOT NULL,
              closed_by INT UNSIGNED NULL,
              closed_at DATETIME NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX idx_inventarios_status (status),
              INDEX idx_inventarios_opened (opened_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS inventario_itens (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              inventario_id INT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED NOT NULL,
              sku VARCHAR(100) NOT NULL,
              product_name VARCHAR(255) NULL,
              counted_quantity INT UNSIGNED NOT NULL DEFAULT 0,
              scan_count INT UNSIGNED NOT NULL DEFAULT 0,
              first_scan_at DATETIME NULL,
              last_scan_at DATETIME NULL,
              last_user_id INT UNSIGNED NULL,
              UNIQUE KEY uniq_inventario_produto (inventario_id, product_id),
              INDEX idx_inventario_itens (inventario_id),
              INDEX idx_inventario_sku (sku)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS inventario_scans (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              inventario_id INT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED NULL,
              sku VARCHAR(100) NOT NULL,
              product_name VARCHAR(255) NULL,
              user_id INT UNSIGNED NULL,
              action VARCHAR(20) NOT NULL,
              quantity INT UNSIGNED NOT NULL DEFAULT 1,
              created_at DATETIME NOT NULL,
              INDEX idx_inventario_scans (inventario_id),
              INDEX idx_inventario_scan_prod (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS inventario_logs (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              inventario_id INT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED NULL,
              sku VARCHAR(100) NOT NULL,
              action VARCHAR(30) NOT NULL,
              before_json TEXT NULL,
              after_json TEXT NULL,
              reason VARCHAR(255) NULL,
              user_id INT UNSIGNED NULL,
              created_at DATETIME NOT NULL,
              INDEX idx_inventario_logs (inventario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS inventario_pendentes (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              inventario_id INT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED NULL,
              sku VARCHAR(100) NOT NULL,
              product_name VARCHAR(255) NOT NULL,
              quantity INT NULL,
              availability_status VARCHAR(30) NULL,
              resolved_action VARCHAR(30) NULL,
              resolved_by INT UNSIGNED NULL,
              resolved_at DATETIME NULL,
              created_at DATETIME NOT NULL,
              INDEX idx_inventario_pendentes (inventario_id),
              INDEX idx_inventario_pendente_prod (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );

        $this->ensureColumn('inventarios', 'blind_count', "ALTER TABLE inventarios ADD COLUMN blind_count TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        $this->ensureColumn('inventario_itens', 'product_name', "ALTER TABLE inventario_itens ADD COLUMN product_name VARCHAR(255) NULL AFTER sku");
        $this->ensureColumn('inventario_scans', 'product_name', "ALTER TABLE inventario_scans ADD COLUMN product_name VARCHAR(255) NULL AFTER sku");
        $this->ensureColumn('inventario_logs', 'reason', "ALTER TABLE inventario_logs ADD COLUMN reason VARCHAR(255) NULL AFTER after_json");
        $this->ensureColumn('inventario_pendentes', 'quantity', "ALTER TABLE inventario_pendentes ADD COLUMN quantity INT NULL AFTER product_name");
        $this->ensureColumn('inventario_pendentes', 'availability_status', "ALTER TABLE inventario_pendentes ADD COLUMN availability_status VARCHAR(30) NULL AFTER quantity");
        $this->ensureColumn('inventario_pendentes', 'resolved_action', "ALTER TABLE inventario_pendentes ADD COLUMN resolved_action VARCHAR(30) NULL AFTER availability_status");

        $this->ensureProductIdColumnCompatibility();
        $this->ensureProductForeignKeys();
    }

    private function ensureColumn(string $table, string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
            $this->pdo->exec($ddl);
        }
    }

    private function ensureProductIdColumnCompatibility(): void
    {
        $this->ensureBigintProductColumn('inventario_itens', false);
        $this->ensureBigintProductColumn('inventario_scans', true);
        $this->ensureBigintProductColumn('inventario_logs', true);
        $this->ensureBigintProductColumn('inventario_pendentes', true);
    }

    private function ensureBigintProductColumn(string $table, bool $nullable): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, 'product_id')) {
            return;
        }

        $info = $this->columnTypeInfo($table, 'product_id');
        if ($info !== null
            && $info['data_type'] === 'bigint'
            && $info['unsigned'] === true
            && $info['nullable'] === $nullable
        ) {
            return;
        }

        try {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            $this->pdo->exec("ALTER TABLE {$table} MODIFY COLUMN product_id BIGINT UNSIGNED {$nullSql}");
        } catch (\Throwable $e) {
            error_log("Falha ao ajustar tipo {$table}.product_id: " . $e->getMessage());
        }
    }

    private function ensureProductForeignKeys(): void
    {
        $targets = [
            [
                'table' => 'inventario_itens',
                'column' => 'product_id',
                'constraint' => 'fk_inventario_itens_product_sku',
                'on_delete' => 'RESTRICT',
            ],
            [
                'table' => 'inventario_scans',
                'column' => 'product_id',
                'constraint' => 'fk_inventario_scans_product_sku',
                'on_delete' => 'SET NULL',
            ],
            [
                'table' => 'inventario_logs',
                'column' => 'product_id',
                'constraint' => 'fk_inventario_logs_product_sku',
                'on_delete' => 'SET NULL',
            ],
            [
                'table' => 'inventario_pendentes',
                'column' => 'product_id',
                'constraint' => 'fk_inventario_pendentes_product_sku',
                'on_delete' => 'SET NULL',
            ],
        ];

        foreach ($targets as $target) {
            $table = $target['table'];
            $column = $target['column'];
            $constraint = $target['constraint'];
            $onDelete = $target['on_delete'];

            if ($this->foreignKeyExists($table, $constraint)) {
                continue;
            }

            if (
                !$this->tableExists($table)
                || !$this->tableExists('products')
                || !$this->columnExists($table, $column)
                || !$this->columnExists('products', 'sku')
            ) {
                continue;
            }

            if (!$this->isCompatibleForeignKey($table, $column, 'products', 'sku')) {
                continue;
            }

            if ($this->countProductOrphans($table, $column) > 0) {
                continue;
            }

            try {
                $this->pdo->exec(
                    "ALTER TABLE {$table}
                     ADD CONSTRAINT {$constraint}
                     FOREIGN KEY ({$column}) REFERENCES products(sku)
                     ON DELETE {$onDelete}
                     ON UPDATE CASCADE"
                );
            } catch (\Throwable $e) {
                error_log("Falha ao adicionar FK {$constraint}: " . $e->getMessage());
            }
        }
    }

    private function countProductOrphans(string $table, string $column): int
    {
        if (!$this->pdo || !$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return 0;
        }
        $sql = "SELECT COUNT(*)
                FROM {$table} t
                WHERE t.{$column} IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.{$column})";
        $stmt = $this->pdo->query($sql);
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
             LIMIT 1'
        );
        $stmt->execute([':table' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
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

    private function foreignKeyExists(string $table, string $constraint): bool
    {
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
}
