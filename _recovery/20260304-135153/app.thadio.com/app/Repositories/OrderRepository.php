<?php

namespace App\Repositories;

use PDO;
use App\Support\AuditService;
use App\Support\Input;
use App\Services\OrderService;
use App\Services\OrderDeliveryPolicy;

class OrderRepository
{
    private ?PDO $pdo;

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

    /**
     * Cria a tabela orders com os campos exibidos no painel.
     */
    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS orders (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pessoa_id BIGINT UNSIGNED NULL,
          status VARCHAR(50) NOT NULL DEFAULT 'open',
          billing_name VARCHAR(200) NULL,
          billing_email VARCHAR(200) NULL,
          billing_phone VARCHAR(50) NULL,
          billing_address_1 VARCHAR(200) NULL,
          billing_address_2 VARCHAR(200) NULL,
          billing_city VARCHAR(100) NULL,
          billing_state VARCHAR(50) NULL,
          billing_zip VARCHAR(20) NULL,
          billing_country VARCHAR(50) NULL,
          subtotal DECIMAL(10,2) NULL,
          shipping_total DECIMAL(10,2) NULL,
          tax_total DECIMAL(10,2) NULL,
          discount_total DECIMAL(10,2) NULL,
          total DECIMAL(10,2) NULL,
          payment_status VARCHAR(50) NULL,
          payment_method VARCHAR(100) NULL,
          fulfillment_status VARCHAR(50) NULL,
          delivery_mode VARCHAR(40) NULL,
          shipment_kind VARCHAR(40) NULL,
          delivery_status VARCHAR(50) NULL,
          carrier_id INT UNSIGNED NULL,
          bag_id INT UNSIGNED NULL,
          tracking_code VARCHAR(160) NULL,
          estimated_delivery_at DATETIME NULL,
          shipped_at DATETIME NULL,
          delivered_at DATETIME NULL,
          logistics_notes TEXT NULL,
          buyer_note TEXT NULL,
          sales_channel VARCHAR(100) NULL,
          currency VARCHAR(10) DEFAULT 'BRL',
          ordered_at DATETIME NULL,
          completed_at DATETIME NULL,
          
          number INT NOT NULL UNIQUE,
          cart_id VARCHAR(120) NULL,
          line_items LONGTEXT NULL,
          external_id VARCHAR(120) NULL,
          archived TINYINT(1) NOT NULL DEFAULT 0,
          entered_by LONGTEXT NULL,
          shipping_info LONGTEXT NULL,
          activities LONGTEXT NULL,
          custom_field LONGTEXT NULL,
          updated_date DATETIME NULL,
          weight_unit VARCHAR(20) NULL,
          date_created DATETIME NULL,
          subscription_information LONGTEXT NULL,
          billing_info LONGTEXT NULL,
          buyer_info LONGTEXT NULL,
          buyer_language VARCHAR(10) NULL,
          channel_info LONGTEXT NULL,
          totals LONGTEXT NULL,
          fulfillments LONGTEXT NULL,
          refunds LONGTEXT NULL,
          discount LONGTEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_orders_pessoa (pessoa_id),
          INDEX idx_orders_status (status),
          INDEX idx_orders_number (number),
          INDEX idx_orders_payment_status (payment_status),
          INDEX idx_orders_fulfillment_status (fulfillment_status),
          INDEX idx_orders_delivery_mode (delivery_mode),
          INDEX idx_orders_shipment_kind (shipment_kind),
          INDEX idx_orders_delivery_status (delivery_status),
          INDEX idx_orders_carrier (carrier_id),
          INDEX idx_orders_bag (bag_id),
          INDEX idx_orders_tracking_code (tracking_code),
          INDEX idx_orders_shipped_at (shipped_at),
          INDEX idx_orders_delivered_at (delivered_at),
          INDEX idx_orders_ordered_at (ordered_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $this->pdo->exec($sql);

        $this->ensureColumn('orders', 'delivery_mode', 'VARCHAR(40) NULL AFTER fulfillment_status');
        $this->ensureColumn('orders', 'shipment_kind', 'VARCHAR(40) NULL AFTER delivery_mode');
        $this->ensureColumn('orders', 'delivery_status', 'VARCHAR(50) NULL AFTER shipment_kind');
        $this->ensureColumn('orders', 'carrier_id', 'INT UNSIGNED NULL AFTER delivery_status');
        $this->ensureColumn('orders', 'bag_id', 'INT UNSIGNED NULL AFTER carrier_id');
        $this->ensureColumn('orders', 'tracking_code', 'VARCHAR(160) NULL AFTER bag_id');
        $this->ensureColumn('orders', 'estimated_delivery_at', 'DATETIME NULL AFTER tracking_code');
        $this->ensureColumn('orders', 'shipped_at', 'DATETIME NULL AFTER estimated_delivery_at');
        $this->ensureColumn('orders', 'delivered_at', 'DATETIME NULL AFTER shipped_at');
        $this->ensureColumn('orders', 'logistics_notes', 'TEXT NULL AFTER delivered_at');

        $this->ensureIndex('orders', 'idx_orders_delivery_mode', 'delivery_mode');
        $this->ensureIndex('orders', 'idx_orders_shipment_kind', 'shipment_kind');
        $this->ensureIndex('orders', 'idx_orders_delivery_status', 'delivery_status');
        $this->ensureIndex('orders', 'idx_orders_carrier', 'carrier_id');
        $this->ensureIndex('orders', 'idx_orders_bag', 'bag_id');
        $this->ensureIndex('orders', 'idx_orders_tracking_code', 'tracking_code');
        $this->ensureIndex('orders', 'idx_orders_shipped_at', 'shipped_at');
        $this->ensureIndex('orders', 'idx_orders_delivered_at', 'delivered_at');

        $this->ensureOrderItemsSchema();
        $this->addProductSkuFkIfMissing();
    }
    
    /**
     * Add FK constraint to products(sku) if not exists (idempotent).
     */
    private function addProductSkuFkIfMissing(): void
    {
        try {
            if (
                !$this->tableExists('order_items')
                || !$this->tableExists('products')
                || !$this->columnExists('order_items', 'product_sku')
                || !$this->columnExists('products', 'sku')
            ) {
                return;
            }

            if (!$this->isCompatibleForeignKey('order_items', 'product_sku', 'products', 'sku')) {
                return;
            }

            if ($this->foreignKeyExists('order_items', 'fk_order_items_product_sku')) {
                return;
            }

            $this->pdo->exec(
                'ALTER TABLE order_items
                 ADD CONSTRAINT fk_order_items_product_sku
                 FOREIGN KEY (product_sku)
                 REFERENCES products(sku)
                 ON DELETE RESTRICT
                 ON UPDATE CASCADE'
            );
        } catch (\Throwable $e) {
            // Falha ao adicionar FK não é crítico (pode ter dados órfãos)
            error_log("Falha ao adicionar FK order_items.product_sku: " . $e->getMessage());
        }
    }

    private function ensureOrderItemsSchema(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS order_items (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id INT UNSIGNED NOT NULL,
          product_sku BIGINT UNSIGNED NULL COMMENT 'SKU do produto (FK para products.sku)',
          product_name VARCHAR(200) NOT NULL,
          sku VARCHAR(100) NULL COMMENT 'SKU como string (redundante, manter para compatibilidade)',
          quantity INT NOT NULL DEFAULT 1,
          price DECIMAL(10,2) NOT NULL,
          total DECIMAL(10,2) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_order_items_order (order_id),
          INDEX idx_order_items_product_sku (product_sku),
          CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);

        $this->ensureColumn('order_items', 'product_sku', 'BIGINT UNSIGNED NULL AFTER order_id');
        $this->ensureColumn('order_items', 'product_name', "VARCHAR(200) NOT NULL DEFAULT '' AFTER product_sku");
        $this->ensureColumn('order_items', 'sku', 'VARCHAR(100) NULL AFTER product_name');
        $this->ensureColumn('order_items', 'quantity', 'INT NOT NULL DEFAULT 1 AFTER sku');
        $this->ensureColumn('order_items', 'price', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER quantity');
        $this->ensureColumn('order_items', 'total', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price');
        $this->ensureColumn('order_items', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER total');

        if ($this->columnExists('order_items', 'product_sku') && $this->columnExists('order_items', 'sku')) {
            $this->pdo->exec(
                "UPDATE order_items
                 SET sku = COALESCE(NULLIF(sku, ''), CAST(product_sku AS CHAR))
                 WHERE product_sku IS NOT NULL"
            );
        }

        $this->ensureIndex('order_items', 'idx_order_items_order', 'order_id');
        $this->ensureIndex('order_items', 'idx_order_items_product_sku', 'product_sku');
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }
        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function ensureIndex(string $table, string $index, string $column): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }
        $this->pdo->exec(sprintf('ALTER TABLE %s ADD INDEX %s (%s)', $table, $index, $column));
    }

    private function tableExists(string $table): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
             LIMIT 1'
        );
        $stmt->execute([':table' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
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

    private function indexExists(string $table, string $index): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :index
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':index' => $index,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_TYPE = "FOREIGN KEY"
               AND CONSTRAINT_NAME = :constraint
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
     * @return array{data_type:string,unsigned:bool}|null
     */
    private function columnTypeInfo(string $table, string $column): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE
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
        ];
    }
    
    /**
     * Save order (insert or update)
     */
    public function save(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $id = isset($data['id']) ? (int)$data['id'] : null;
        $personId = $this->resolvePersonId($data);
        $payload = $this->normalizeOrderPayload($data, $personId);
        $personColumn = $this->personColumn();
        
        // Generate unique order number if not provided
        if (!isset($data['number']) || (int) $data['number'] <= 0) {
            $data['number'] = $this->getNextOrderNumber();
        }
        
        // Capturar old values se for UPDATE (para auditoria)
        $oldValues = null;
        $oldOrder = null;
        if ($id) {
            $oldOrder = $this->find($id);
            $oldValues = $oldOrder;
        }
        $previousPaymentStatus = $this->canonicalPaymentStatus((string) ($oldOrder['payment_status'] ?? 'none'), 'none');
        $this->assertPaymentMethodForPaidStatus(
            (string) ($payload['payment_status'] ?? 'none'),
            $payload['payment_method'] ?? null,
            $previousPaymentStatus
        );
        
        if ($id) {
            // UPDATE
            $sql = "UPDATE orders SET
                {$personColumn} = :person_id,
                status = :status,
                billing_name = :billing_name,
                billing_email = :billing_email,
                billing_phone = :billing_phone,
                billing_address_1 = :billing_address_1,
                billing_address_2 = :billing_address_2,
                billing_city = :billing_city,
                billing_state = :billing_state,
                billing_zip = :billing_zip,
                billing_country = :billing_country,
                subtotal = :subtotal,
                shipping_total = :shipping_total,
                tax_total = :tax_total,
                discount_total = :discount_total,
                total = :total,
                payment_status = :payment_status,
                payment_method = :payment_method,
                fulfillment_status = :fulfillment_status,
                delivery_mode = :delivery_mode,
                shipment_kind = :shipment_kind,
                delivery_status = :delivery_status,
                carrier_id = :carrier_id,
                bag_id = :bag_id,
                tracking_code = :tracking_code,
                estimated_delivery_at = :estimated_delivery_at,
                shipped_at = :shipped_at,
                delivered_at = :delivered_at,
                logistics_notes = :logistics_notes,
                buyer_note = :buyer_note,
                sales_channel = :sales_channel,
                currency = :currency,
                shipping_info = :shipping_info,
                custom_field = :custom_field,
                ordered_at = :ordered_at
            WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':person_id' => $payload['pessoa_id'],
                ':status' => $payload['status'],
                ':billing_name' => $payload['billing_name'],
                ':billing_email' => $payload['billing_email'],
                ':billing_phone' => $payload['billing_phone'],
                ':billing_address_1' => $payload['billing_address_1'],
                ':billing_address_2' => $payload['billing_address_2'],
                ':billing_city' => $payload['billing_city'],
                ':billing_state' => $payload['billing_state'],
                ':billing_zip' => $payload['billing_zip'],
                ':billing_country' => $payload['billing_country'],
                ':subtotal' => $payload['subtotal'],
                ':shipping_total' => $payload['shipping_total'],
                ':tax_total' => $payload['tax_total'],
                ':discount_total' => $payload['discount_total'],
                ':total' => $payload['total'],
                ':payment_status' => $payload['payment_status'],
                ':payment_method' => $payload['payment_method'],
                ':fulfillment_status' => $payload['fulfillment_status'],
                ':delivery_mode' => $payload['delivery_mode'],
                ':shipment_kind' => $payload['shipment_kind'],
                ':delivery_status' => $payload['delivery_status'],
                ':carrier_id' => $payload['carrier_id'],
                ':bag_id' => $payload['bag_id'],
                ':tracking_code' => $payload['tracking_code'],
                ':estimated_delivery_at' => $payload['estimated_delivery_at'],
                ':shipped_at' => $payload['shipped_at'],
                ':delivered_at' => $payload['delivered_at'],
                ':logistics_notes' => $payload['logistics_notes'],
                ':buyer_note' => $payload['buyer_note'],
                ':sales_channel' => $payload['sales_channel'],
                ':currency' => $payload['currency'],
                ':shipping_info' => $payload['shipping_info_json'],
                ':custom_field' => $payload['custom_field_json'],
                ':ordered_at' => $payload['ordered_at'],
            ]);
            
            $newOrder = $this->find($id);
            $this->safeAudit('UPDATE', 'orders', $id, $oldValues, $newOrder);
            
            return $id;
        } else {
            // INSERT
            $sql = "INSERT INTO orders (
                {$personColumn}, status, billing_name, billing_email, billing_phone,
                billing_address_1, billing_address_2, billing_city, billing_state, billing_zip, billing_country,
                subtotal, shipping_total, tax_total, discount_total, total,
                payment_status, payment_method, fulfillment_status,
                delivery_mode, shipment_kind, delivery_status, carrier_id, bag_id, tracking_code,
                estimated_delivery_at, shipped_at, delivered_at, logistics_notes,
                buyer_note, sales_channel, currency, shipping_info, custom_field,
                ordered_at, number
            ) VALUES (
                :person_id, :status, :billing_name, :billing_email, :billing_phone,
                :billing_address_1, :billing_address_2, :billing_city, :billing_state, :billing_zip, :billing_country,
                :subtotal, :shipping_total, :tax_total, :discount_total, :total,
                :payment_status, :payment_method, :fulfillment_status,
                :delivery_mode, :shipment_kind, :delivery_status, :carrier_id, :bag_id, :tracking_code,
                :estimated_delivery_at, :shipped_at, :delivered_at, :logistics_notes,
                :buyer_note, :sales_channel, :currency, :shipping_info, :custom_field,
                :ordered_at, :number
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':person_id' => $payload['pessoa_id'],
                ':status' => $payload['status'],
                ':billing_name' => $payload['billing_name'],
                ':billing_email' => $payload['billing_email'],
                ':billing_phone' => $payload['billing_phone'],
                ':billing_address_1' => $payload['billing_address_1'],
                ':billing_address_2' => $payload['billing_address_2'],
                ':billing_city' => $payload['billing_city'],
                ':billing_state' => $payload['billing_state'],
                ':billing_zip' => $payload['billing_zip'],
                ':billing_country' => $payload['billing_country'],
                ':subtotal' => $payload['subtotal'],
                ':shipping_total' => $payload['shipping_total'],
                ':tax_total' => $payload['tax_total'],
                ':discount_total' => $payload['discount_total'],
                ':total' => $payload['total'],
                ':payment_status' => $payload['payment_status'],
                ':payment_method' => $payload['payment_method'],
                ':fulfillment_status' => $payload['fulfillment_status'],
                ':delivery_mode' => $payload['delivery_mode'],
                ':shipment_kind' => $payload['shipment_kind'],
                ':delivery_status' => $payload['delivery_status'],
                ':carrier_id' => $payload['carrier_id'],
                ':bag_id' => $payload['bag_id'],
                ':tracking_code' => $payload['tracking_code'],
                ':estimated_delivery_at' => $payload['estimated_delivery_at'],
                ':shipped_at' => $payload['shipped_at'],
                ':delivered_at' => $payload['delivered_at'],
                ':logistics_notes' => $payload['logistics_notes'],
                ':buyer_note' => $payload['buyer_note'],
                ':sales_channel' => $payload['sales_channel'],
                ':currency' => $payload['currency'],
                ':shipping_info' => $payload['shipping_info_json'],
                ':custom_field' => $payload['custom_field_json'],
                ':ordered_at' => $payload['ordered_at'],
                ':number' => $data['number'],
            ]);
            
            $newId = (int)$this->pdo->lastInsertId();
            
            $newOrder = $this->find($newId);
            $this->safeAudit('INSERT', 'orders', $newId, null, $newOrder);
            
            return $newId;
        }
    }
    
    /**
     * Find order by ID
     */
    public function find(int $id): ?array
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $sql = "SELECT * FROM orders WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return null;
        }
        return $this->normalizeOrderRow($result);
    }
    
    /**
     * Update order status
     */
    public function updateStatus(int $id, string $status): void
    {
        $status = $this->canonicalOrderStatus($status, 'open');
        // Capturar old values para auditoria
        $oldOrder = $this->find($id);
        $oldDeliveryMode = is_array($oldOrder) ? (string) ($oldOrder['delivery_mode'] ?? 'shipment') : 'shipment';
        
        $sql = "UPDATE orders SET status = :status WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':status' => $status]);
        
        $newOrder = $this->find($id);
        $this->safeAudit('UPDATE', 'orders', $id, $oldOrder, $newOrder);
    }
    
    /**
     * Get next order number
     */
    public function getNextOrderNumber(): int
    {
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(number), 0) + 1 as next_number FROM orders");
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Find order with all related details (items, payment, shipping)
     * Substitui: $this->orderGateway->get($orderId)
     */
    public function findOrderWithDetails(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        
        $order = $this->find($id);
        if (!$order) {
            return null;
        }
        
        // Buscar items do pedido
        $order['line_items'] = $this->getOrderItems($id);
        
        // Parse JSON fields se existirem
        $jsonFields = ['billing_info', 'shipping_info', 'totals', 'entered_by', 'activities'];
        foreach ($jsonFields as $field) {
            if (isset($order[$field]) && is_string($order[$field])) {
                $decoded = json_decode($order[$field], true);
                $order[$field] = $decoded ?? $order[$field];
            }
        }
        
        return $order;
    }
    
    /**
     * Get order items
     * Retorna items no formato esperado pelo sistema
     */
    public function getOrderItems(int $orderId): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        $sql = "SELECT
                    id,
                    order_id,
                    product_sku,
                    0 AS variation_id,
                    product_name,
                    product_name AS name,
                    sku,
                    quantity,
                    price,
                    total
                FROM order_items
                WHERE order_id = :order_id
                ORDER BY id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get order item quantities (para validação de disponibilidade)
     * Retorna array com [product_sku => quantity]
     */
    public function getOrderItemQuantities(int $orderId): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        $sql = "SELECT product_sku, SUM(quantity) as qty 
                FROM order_items 
                WHERE order_id = :order_id 
                GROUP BY product_sku";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        $quantities = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['product_sku']) {
                $quantities[(int)$row['product_sku']] = (int)$row['qty'];
            }
        }
        
        return $quantities;
    }
    
    /**
     * List orders with filters
     */
    public function listOrders(
        array $filters = [],
        int $limit = 50,
        int $offset = 0,
        string $sortKey = 'ordered_at',
        string $sortDir = 'DESC'
    ): array
    {
        if (!$this->pdo) {
            return [];
        }

        [$whereSql, $params] = $this->buildListOrdersWhere($filters);
        $sort = $this->normalizeOrderListSort($sortKey, $sortDir);
        $sql = "SELECT * FROM orders {$whereSql} ORDER BY {$sort['column']} {$sort['direction']}, id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'normalizeOrderRow'], $rows);
    }

    public function countOrders(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        [$whereSql, $params] = $this->buildListOrdersWhere($filters);
        $sql = "SELECT COUNT(*) FROM orders {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $count = $stmt->fetchColumn();

        return $count !== false ? (int) $count : 0;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listCustomerPurchaseOrders(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        if (!$this->pdo) {
            return [];
        }

        $personColumn = $this->personColumn();
        $hasPeopleTable = $this->tableExists('pessoas');
        $hasOrderItemsTable = $this->tableExists('order_items');
        [$whereSql, $params] = $this->buildCustomerPurchaseWhere($filters, 'o', $hasPeopleTable);
        $useLimit = $limit > 0;
        if ($useLimit) {
            $limit = max(1, $limit);
        }
        $offset = max(0, $offset);

        $joinPeople = $hasPeopleTable ? "LEFT JOIN pessoas p ON p.id = o.{$personColumn}" : '';
        $customerNameExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.full_name), ''), NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')"
            : "COALESCE(NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')";
        $customerEmailExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.email), ''), NULLIF(TRIM(o.billing_email), ''))"
            : "NULLIF(TRIM(o.billing_email), '')";
        $itemsCountExpr = $hasOrderItemsTable
            ? '(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id)'
            : '0';

        $sql = "SELECT
                    o.id AS order_id,
                    o.number AS order_number,
                    o.{$personColumn} AS pessoa_id,
                    {$customerNameExpr} AS customer_name,
                    {$customerEmailExpr} AS customer_email,
                    o.status,
                    o.payment_status,
                    COALESCE(o.total, 0) AS order_total,
                    COALESCE(o.ordered_at, o.created_at) AS order_date,
                    {$itemsCountExpr} AS items_count
                FROM orders o
                {$joinPeople}
                {$whereSql}
                ORDER BY customer_name ASC, order_date DESC, o.id DESC"
                . ($useLimit ? "\n                LIMIT :limit OFFSET :offset" : '');

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($useLimit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listCustomerPurchasedProducts(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        if (!$this->pdo || !$this->tableExists('order_items')) {
            return [];
        }

        $personColumn = $this->personColumn();
        $hasPeopleTable = $this->tableExists('pessoas');
        [$whereSql, $params] = $this->buildCustomerPurchaseWhere($filters, 'o', $hasPeopleTable);
        [$havingSql, $havingParams] = $this->buildCustomerPurchasedProductsHaving($filters);
        $useLimit = $limit > 0;
        if ($useLimit) {
            $limit = max(1, $limit);
        }
        $offset = max(0, $offset);

        $joinPeople = $hasPeopleTable ? "LEFT JOIN pessoas p ON p.id = o.{$personColumn}" : '';
        $customerNameExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.full_name), ''), NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')"
            : "COALESCE(NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')";
        $customerEmailExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.email), ''), NULLIF(TRIM(o.billing_email), ''))"
            : "NULLIF(TRIM(o.billing_email), '')";

        $groupBy = [
            "o.{$personColumn}",
            'o.billing_name',
            'o.billing_email',
            'oi.product_sku',
            'oi.sku',
            'oi.product_name',
        ];
        if ($hasPeopleTable) {
            $groupBy[] = 'p.full_name';
            $groupBy[] = 'p.email';
        }

        $sql = "SELECT
                    o.{$personColumn} AS pessoa_id,
                    {$customerNameExpr} AS customer_name,
                    {$customerEmailExpr} AS customer_email,
                    oi.product_sku,
                    COALESCE(NULLIF(TRIM(oi.sku), ''), CAST(COALESCE(oi.product_sku, 0) AS CHAR)) AS product_sku_display,
                    COALESCE(NULLIF(TRIM(oi.product_name), ''), CONCAT('SKU ', CAST(COALESCE(oi.product_sku, 0) AS CHAR))) AS product_name,
                    SUM(oi.quantity) AS quantity_total,
                    SUM(oi.total) AS amount_total,
                    COUNT(DISTINCT o.id) AS orders_count,
                    MAX(COALESCE(o.ordered_at, o.created_at)) AS last_order_at
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                {$joinPeople}
                {$whereSql}
                GROUP BY " . implode(', ', $groupBy) . "
                " . ($havingSql !== '' ? "HAVING {$havingSql}\n" : '') . "
                ORDER BY customer_name ASC, last_order_at DESC, product_name ASC"
                . ($useLimit ? "\n                LIMIT :limit OFFSET :offset" : '');

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        foreach ($havingParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($useLimit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countCustomerPurchaseOrders(array $filters = []): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $hasPeopleTable = $this->tableExists('pessoas');
        [$whereSql, $params] = $this->buildCustomerPurchaseWhere($filters, 'o', $hasPeopleTable);
        $joinPeople = $hasPeopleTable ? "LEFT JOIN pessoas p ON p.id = o.{$this->personColumn()}" : '';
        $sql = "SELECT COUNT(*) FROM orders o {$joinPeople} {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function countCustomerPurchasedProducts(array $filters = []): int
    {
        if (!$this->pdo || !$this->tableExists('order_items')) {
            return 0;
        }

        $personColumn = $this->personColumn();
        $hasPeopleTable = $this->tableExists('pessoas');
        [$whereSql, $params] = $this->buildCustomerPurchaseWhere($filters, 'o', $hasPeopleTable);
        [$havingSql, $havingParams] = $this->buildCustomerPurchasedProductsHaving($filters);
        $joinPeople = $hasPeopleTable ? "LEFT JOIN pessoas p ON p.id = o.{$personColumn}" : '';
        $customerNameExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.full_name), ''), NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')"
            : "COALESCE(NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')";
        $customerEmailExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.email), ''), NULLIF(TRIM(o.billing_email), ''))"
            : "NULLIF(TRIM(o.billing_email), '')";

        $groupBy = [
            "o.{$personColumn}",
            'o.billing_name',
            'o.billing_email',
            'oi.product_sku',
            'oi.sku',
            'oi.product_name',
        ];
        if ($hasPeopleTable) {
            $groupBy[] = 'p.full_name';
            $groupBy[] = 'p.email';
        }

        $baseSql = "SELECT
                        {$customerNameExpr} AS customer_name,
                        {$customerEmailExpr} AS customer_email,
                        COALESCE(NULLIF(TRIM(oi.sku), ''), CAST(COALESCE(oi.product_sku, 0) AS CHAR)) AS product_sku_display,
                        COALESCE(NULLIF(TRIM(oi.product_name), ''), CONCAT('SKU ', CAST(COALESCE(oi.product_sku, 0) AS CHAR))) AS product_name,
                        SUM(oi.quantity) AS quantity_total,
                        SUM(oi.total) AS amount_total,
                        COUNT(DISTINCT o.id) AS orders_count,
                        MAX(COALESCE(o.ordered_at, o.created_at)) AS last_order_at
                    FROM orders o
                    INNER JOIN order_items oi ON oi.order_id = o.id
                    {$joinPeople}
                    {$whereSql}
                    GROUP BY " . implode(', ', $groupBy) . "
                    " . ($havingSql !== '' ? "HAVING {$havingSql}" : '');

        $sql = "SELECT COUNT(*) FROM ({$baseSql}) purchase_lines";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        foreach ($havingParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function summarizeCustomerPurchaseOrders(array $filters = []): array
    {
        if (!$this->pdo) {
            return [];
        }

        $personColumn = $this->personColumn();
        $hasPeopleTable = $this->tableExists('pessoas');
        $hasOrderItemsTable = $this->tableExists('order_items');
        [$whereSql, $params] = $this->buildCustomerPurchaseWhere($filters, 'o', $hasPeopleTable);

        $joinPeople = $hasPeopleTable ? "LEFT JOIN pessoas p ON p.id = o.{$personColumn}" : '';
        $customerNameExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.full_name), ''), NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')"
            : "COALESCE(NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')";
        $itemsCountExpr = $hasOrderItemsTable
            ? '(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id)'
            : '0';

        $groupBy = [
            "o.{$personColumn}",
            'o.billing_name',
            'o.billing_email',
        ];
        if ($hasPeopleTable) {
            $groupBy[] = 'p.full_name';
            $groupBy[] = 'p.email';
        }

        $sql = "SELECT
                    o.{$personColumn} AS pessoa_id,
                    {$customerNameExpr} AS customer_name,
                    COUNT(*) AS orders,
                    SUM({$itemsCountExpr}) AS items,
                    SUM(COALESCE(o.total, 0)) AS amount_total
                FROM orders o
                {$joinPeople}
                {$whereSql}
                GROUP BY " . implode(', ', $groupBy) . "
                ORDER BY amount_total DESC, customer_name ASC";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function summarizeCustomerPurchasedProducts(array $filters = []): array
    {
        if (!$this->pdo || !$this->tableExists('order_items')) {
            return [];
        }

        $personColumn = $this->personColumn();
        $hasPeopleTable = $this->tableExists('pessoas');
        [$whereSql, $params] = $this->buildCustomerPurchaseWhere($filters, 'o', $hasPeopleTable);
        [$havingSql, $havingParams] = $this->buildCustomerPurchasedProductsHaving($filters);

        $joinPeople = $hasPeopleTable ? "LEFT JOIN pessoas p ON p.id = o.{$personColumn}" : '';
        $customerNameExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.full_name), ''), NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')"
            : "COALESCE(NULLIF(TRIM(o.billing_name), ''), NULLIF(TRIM(o.billing_email), ''), 'Cliente não identificado')";
        $customerEmailExpr = $hasPeopleTable
            ? "COALESCE(NULLIF(TRIM(p.email), ''), NULLIF(TRIM(o.billing_email), ''))"
            : "NULLIF(TRIM(o.billing_email), '')";

        $groupBy = [
            "o.{$personColumn}",
            'o.billing_name',
            'o.billing_email',
            'oi.product_sku',
            'oi.sku',
            'oi.product_name',
        ];
        if ($hasPeopleTable) {
            $groupBy[] = 'p.full_name';
            $groupBy[] = 'p.email';
        }

        $baseSql = "SELECT
                        o.{$personColumn} AS pessoa_id,
                        {$customerNameExpr} AS customer_name,
                        {$customerEmailExpr} AS customer_email,
                        COALESCE(NULLIF(TRIM(oi.sku), ''), CAST(COALESCE(oi.product_sku, 0) AS CHAR)) AS product_sku_display,
                        COALESCE(NULLIF(TRIM(oi.product_name), ''), CONCAT('SKU ', CAST(COALESCE(oi.product_sku, 0) AS CHAR))) AS product_name,
                        SUM(oi.quantity) AS quantity_total,
                        SUM(oi.total) AS amount_total,
                        COUNT(DISTINCT o.id) AS orders_count,
                        MAX(COALESCE(o.ordered_at, o.created_at)) AS last_order_at
                    FROM orders o
                    INNER JOIN order_items oi ON oi.order_id = o.id
                    {$joinPeople}
                    {$whereSql}
                    GROUP BY " . implode(', ', $groupBy) . "
                    " . ($havingSql !== '' ? "HAVING {$havingSql}" : '');

        $sql = "SELECT
                    s.pessoa_id,
                    s.customer_name,
                    COUNT(*) AS product_lines,
                    SUM(s.quantity_total) AS quantity_total,
                    SUM(s.amount_total) AS amount_total
                FROM ({$baseSql}) s
                GROUP BY s.pessoa_id, s.customer_name
                ORDER BY amount_total DESC, s.customer_name ASC";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        foreach ($havingParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildCustomerPurchaseWhere(array $filters, string $ordersAlias, bool $hasPeopleTable): array
    {
        $whereSql = 'WHERE 1=1';
        $params = [];
        $personColumn = $this->personColumn();

        $customerIdFilter = isset($filters['customer_id']) ? (int) $filters['customer_id'] : 0;
        if ($customerIdFilter > 0) {
            $whereSql .= " AND {$ordersAlias}.{$personColumn} = :purchase_customer_id";
            $params[':purchase_customer_id'] = $customerIdFilter;
        }

        $customerQuery = trim((string) ($filters['customer_query'] ?? ($filters['customer'] ?? '')));
        if ($customerQuery !== '') {
            $customerClauses = [
                "{$ordersAlias}.billing_name LIKE :purchase_customer_query",
                "{$ordersAlias}.billing_email LIKE :purchase_customer_query",
            ];
            if ($hasPeopleTable) {
                $customerClauses[] = 'p.full_name LIKE :purchase_customer_query';
                $customerClauses[] = 'p.email LIKE :purchase_customer_query';
            }
            $whereSql .= ' AND (' . implode(' OR ', $customerClauses) . ')';
            $params[':purchase_customer_query'] = '%' . $customerQuery . '%';
        }

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            $searchClauses = [
                "CAST({$ordersAlias}.id AS CHAR) LIKE :purchase_search",
                "{$ordersAlias}.billing_name LIKE :purchase_search",
                "{$ordersAlias}.billing_email LIKE :purchase_search",
                "{$ordersAlias}.status LIKE :purchase_search",
                "{$ordersAlias}.payment_status LIKE :purchase_search",
                "CAST(COALESCE({$ordersAlias}.ordered_at, {$ordersAlias}.created_at) AS CHAR) LIKE :purchase_search",
            ];
            if ($hasPeopleTable) {
                $searchClauses[] = 'p.full_name LIKE :purchase_search';
                $searchClauses[] = 'p.email LIKE :purchase_search';
            }
            $whereSql .= ' AND (' . implode(' OR ', $searchClauses) . ')';
            $params[':purchase_search'] = '%' . $search . '%';
        }

        $statusFilter = trim((string) ($filters['status'] ?? ''));
        if ($statusFilter !== '') {
            $whereSql .= " AND {$ordersAlias}.status = :purchase_status";
            $params[':purchase_status'] = OrderService::normalizeOrderStatus($statusFilter);
        }

        $startFilter = trim((string) ($filters['start'] ?? ''));
        if ($startFilter !== '') {
            $whereSql .= " AND DATE(COALESCE({$ordersAlias}.ordered_at, {$ordersAlias}.created_at)) >= :purchase_start";
            $params[':purchase_start'] = $startFilter;
        }

        $endFilter = trim((string) ($filters['end'] ?? ''));
        if ($endFilter !== '') {
            $whereSql .= " AND DATE(COALESCE({$ordersAlias}.ordered_at, {$ordersAlias}.created_at)) <= :purchase_end";
            $params[':purchase_end'] = $endFilter;
        }

        return [$whereSql, $params];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildCustomerPurchasedProductsHaving(array $filters): array
    {
        $having = '';
        $params = [];

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            $params[':purchase_product_search'] = '%' . $search . '%';
            $having = "(customer_name LIKE :purchase_product_search
                OR customer_email LIKE :purchase_product_search
                OR product_sku_display LIKE :purchase_product_search
                OR product_name LIKE :purchase_product_search
                OR CAST(quantity_total AS CHAR) LIKE :purchase_product_search
                OR CAST(amount_total AS CHAR) LIKE :purchase_product_search
                OR CAST(orders_count AS CHAR) LIKE :purchase_product_search
                OR CAST(last_order_at AS CHAR) LIKE :purchase_product_search)";
        }

        return [$having, $params];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listDeliveries(array $filters = [], int $limit = 0): array
    {
        if (!$this->pdo) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        $hasCarriersTable = $this->tableExists('carriers');
        $hasBagShipmentsTable = $this->tableExists('bag_shipments');
        $hasPeopleTable = $this->tableExists('pessoas');
        $personColumn = $this->personColumn();

        $orderId = isset($filters['order_id']) ? (int) $filters['order_id'] : 0;
        if ($orderId > 0) {
            $where[] = 'o.id = :order_id';
            $params[':order_id'] = $orderId;
        }

        $deliveryStatus = trim((string) ($filters['delivery_status'] ?? ''));
        if ($deliveryStatus !== '') {
            $where[] = 'o.delivery_status = :delivery_status';
            $params[':delivery_status'] = $this->canonicalFulfillmentStatus($deliveryStatus, 'pending');
        }

        $deliveryMode = trim((string) ($filters['delivery_mode'] ?? ''));
        if ($deliveryMode !== '') {
            $where[] = 'o.delivery_mode = :delivery_mode';
            $params[':delivery_mode'] = OrderService::normalizeDeliveryMode($deliveryMode);
        }

        $shipmentKind = trim((string) ($filters['shipment_kind'] ?? ''));
        if ($shipmentKind !== '') {
            $where[] = 'o.shipment_kind = :shipment_kind';
            $params[':shipment_kind'] = OrderService::normalizeShipmentKind($shipmentKind, 'shipment');
        }

        $carrierId = isset($filters['carrier_id']) ? (int) $filters['carrier_id'] : 0;
        if ($carrierId > 0) {
            if ($hasBagShipmentsTable) {
                $where[] = '(o.carrier_id = :carrier_id
                    OR (o.shipment_kind = \'bag_deferred\'
                        AND EXISTS (
                            SELECT 1
                            FROM bag_shipments bs_filter_carrier
                            WHERE bs_filter_carrier.bag_id = o.bag_id
                              AND bs_filter_carrier.carrier_id = :carrier_id
                        )
                    ))';
            } else {
                $where[] = 'o.carrier_id = :carrier_id';
            }
            $params[':carrier_id'] = $carrierId;
        }

        $trackingSearch = trim((string) ($filters['tracking_code'] ?? ''));
        if ($trackingSearch !== '') {
            if ($hasBagShipmentsTable) {
                $where[] = '(o.tracking_code LIKE :tracking_code
                    OR (o.shipment_kind = \'bag_deferred\'
                        AND EXISTS (
                            SELECT 1
                            FROM bag_shipments bs_filter_tracking
                            WHERE bs_filter_tracking.bag_id = o.bag_id
                              AND bs_filter_tracking.tracking_code LIKE :tracking_code
                        )
                    ))';
            } else {
                $where[] = 'o.tracking_code LIKE :tracking_code';
            }
            $params[':tracking_code'] = '%' . $trackingSearch . '%';
        }

        $customerSearch = trim((string) ($filters['customer'] ?? ''));
        if ($customerSearch !== '') {
            $customerColumn = $this->tableExists('pessoas')
                ? 'COALESCE(p.full_name, o.billing_name)'
                : 'o.billing_name';
            $where[] = '(' . $customerColumn . ' LIKE :customer OR o.billing_name LIKE :customer)';
            $params[':customer'] = '%' . $customerSearch . '%';
        }

        $dateFrom = trim((string) ($filters['period_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 'DATE(COALESCE(o.ordered_at, o.created_at)) >= :period_from';
            $params[':period_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['period_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = 'DATE(COALESCE(o.ordered_at, o.created_at)) <= :period_to';
            $params[':period_to'] = $dateTo;
        }

        $carrierSelect = $hasCarriersTable
            ? 'oc.name AS carrier_name, oc.tracking_url_template,'
            : 'NULL AS carrier_name, NULL AS tracking_url_template,';
        $bagShipmentSelect = $hasBagShipmentsTable
            ? 'bs.id AS bag_shipment_id,
               bs.status AS bag_shipment_status,
               bs.carrier_id AS bag_carrier_id,
               bs.tracking_code AS bag_tracking_code,
               bs.estimated_delivery_at AS bag_estimated_delivery_at,
               bs.shipped_at AS bag_shipped_at,
               bs.delivered_at AS bag_delivered_at,
               bs.updated_at AS bag_updated_at,'
            : 'NULL AS bag_shipment_id,
               NULL AS bag_shipment_status,
               NULL AS bag_carrier_id,
               NULL AS bag_tracking_code,
               NULL AS bag_estimated_delivery_at,
               NULL AS bag_shipped_at,
               NULL AS bag_delivered_at,
               NULL AS bag_updated_at,';
        $bagCarrierSelect = ($hasBagShipmentsTable && $hasCarriersTable)
            ? 'bc.name AS bag_carrier_name, bc.tracking_url_template AS bag_tracking_url_template,'
            : 'NULL AS bag_carrier_name, NULL AS bag_tracking_url_template,';
        $customerSelect = $hasPeopleTable
            ? 'COALESCE(p.full_name, o.billing_name) AS customer_name'
            : 'o.billing_name AS customer_name';
        $joinCarrier = $hasCarriersTable ? 'LEFT JOIN carriers oc ON oc.id = o.carrier_id' : '';
        $joinBagShipment = $hasBagShipmentsTable
            ? 'LEFT JOIN (
                    SELECT bs_latest.*
                    FROM bag_shipments bs_latest
                    INNER JOIN (
                        SELECT bag_id, MAX(id) AS max_id
                        FROM bag_shipments
                        GROUP BY bag_id
                    ) bs_idx ON bs_idx.max_id = bs_latest.id
               ) bs ON bs.bag_id = o.bag_id'
            : '';
        $joinBagCarrier = ($hasBagShipmentsTable && $hasCarriersTable) ? 'LEFT JOIN carriers bc ON bc.id = bs.carrier_id' : '';
        $joinPeople = $hasPeopleTable ? "LEFT JOIN pessoas p ON p.id = o.{$personColumn}" : '';

        $useLimit = $limit > 0;
        if ($useLimit) {
            $limit = max(1, $limit);
        }
        $sql = "SELECT
                    o.id,
                    o.number,
                    o.{$personColumn} AS pessoa_id,
                    o.billing_name,
                    o.delivery_mode,
                    o.shipment_kind,
                    o.delivery_status,
                    o.fulfillment_status,
                    o.carrier_id,
                    o.bag_id,
                    o.tracking_code,
                    o.estimated_delivery_at,
                    o.shipped_at,
                    o.delivered_at,
                    o.updated_at,
                    o.ordered_at,
                    o.shipping_info,
                    {$carrierSelect}
                    {$bagShipmentSelect}
                    {$bagCarrierSelect}
                    {$customerSelect}
                FROM orders o
                {$joinCarrier}
                {$joinBagShipment}
                {$joinBagCarrier}
                {$joinPeople}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(o.updated_at, o.created_at) DESC, o.id DESC"
                . ($useLimit ? "\n                LIMIT :limit" : '');

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($useLimit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $output = [];
        foreach ($rows as $row) {
            $shippingInfo = $this->decodeJson($row['shipping_info'] ?? null);
            $mode = OrderService::normalizeDeliveryMode(
                (string) ($row['delivery_mode'] ?? ($shippingInfo['delivery_mode'] ?? ''))
            );
            $shipmentKindValue = OrderService::normalizeShipmentKind(
                (string) ($row['shipment_kind'] ?? ($shippingInfo['shipment_kind'] ?? '')),
                $mode
            );
            $status = $this->canonicalFulfillmentStatus(
                (string) ($row['delivery_status'] ?? ($shippingInfo['status'] ?? ($row['fulfillment_status'] ?? 'pending'))),
                'pending'
            );
            $trackingCodeValue = trim((string) ($row['tracking_code'] ?? ($shippingInfo['tracking_code'] ?? '')));
            $bagTrackingCodeValue = trim((string) ($row['bag_tracking_code'] ?? ''));
            $carrierIdValue = isset($row['carrier_id']) && (int) $row['carrier_id'] > 0
                ? (int) $row['carrier_id']
                : (isset($shippingInfo['carrier_id']) ? (int) $shippingInfo['carrier_id'] : 0);
            $bagCarrierIdValue = isset($row['bag_carrier_id']) && (int) $row['bag_carrier_id'] > 0
                ? (int) $row['bag_carrier_id']
                : 0;
            $carrierName = trim((string) ($row['carrier_name'] ?? ($shippingInfo['carrier'] ?? '')));
            $bagCarrierName = trim((string) ($row['bag_carrier_name'] ?? ''));

            if ($shipmentKindValue === 'bag_deferred') {
                if ($trackingCodeValue === '' && $bagTrackingCodeValue !== '') {
                    $trackingCodeValue = $bagTrackingCodeValue;
                }
                if ($carrierIdValue <= 0 && $bagCarrierIdValue > 0) {
                    $carrierIdValue = $bagCarrierIdValue;
                }
                if ($carrierName === '' && $bagCarrierName !== '') {
                    $carrierName = $bagCarrierName;
                }
            }

            $trackingUrl = null;
            $trackingTemplate = trim((string) ($row['tracking_url_template'] ?? ''));
            if ($trackingTemplate === '') {
                $trackingTemplate = trim((string) ($row['bag_tracking_url_template'] ?? ''));
            }
            if ($trackingTemplate !== '' && $trackingCodeValue !== '') {
                $trackingUrl = str_replace('{{tracking_code}}', urlencode($trackingCodeValue), $trackingTemplate);
            }

            $updatedAt = $row['updated_at'] ?? null;
            $bagUpdatedAt = $row['bag_updated_at'] ?? null;
            if ($bagUpdatedAt !== null && $bagUpdatedAt !== '') {
                $orderUpdatedTs = $updatedAt !== null ? strtotime((string) $updatedAt) : false;
                $bagUpdatedTs = strtotime((string) $bagUpdatedAt);
                if ($orderUpdatedTs === false || ($bagUpdatedTs !== false && $bagUpdatedTs > $orderUpdatedTs)) {
                    $updatedAt = $bagUpdatedAt;
                }
            }

            $output[] = [
                'id' => (int) ($row['id'] ?? 0),
                'number' => (int) ($row['number'] ?? 0),
                'customer_name' => trim((string) ($row['customer_name'] ?? $row['billing_name'] ?? '')),
                'delivery_mode' => $mode,
                'shipment_kind' => $shipmentKindValue,
                'delivery_status' => $status,
                'carrier_id' => $carrierIdValue > 0 ? $carrierIdValue : null,
                'carrier_name' => $carrierName,
                'bag_id' => isset($row['bag_id']) && (int) $row['bag_id'] > 0 ? (int) $row['bag_id'] : null,
                'tracking_code' => $trackingCodeValue,
                'estimated_delivery_at' => $row['estimated_delivery_at'] ?? ($shippingInfo['estimated_delivery_at'] ?? ($shippingInfo['eta'] ?? ($row['bag_estimated_delivery_at'] ?? null))),
                'shipped_at' => $row['shipped_at'] ?? ($shippingInfo['shipped_at'] ?? ($row['bag_shipped_at'] ?? null)),
                'delivered_at' => $row['delivered_at'] ?? ($shippingInfo['delivered_at'] ?? ($row['bag_delivered_at'] ?? null)),
                'updated_at' => $updatedAt,
                'ordered_at' => $row['ordered_at'] ?? null,
                'bag_shipment_status' => (string) ($row['bag_shipment_status'] ?? ''),
                'tracking_url' => $trackingUrl,
            ];
        }

        return $output;
    }
    
    /**
     * Update payment information
     * Substitui: $this->orderGateway->updatePayment()
     */
    public function updatePayment(int $orderId, array $paymentData): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $paymentStatus = $this->canonicalPaymentStatus((string) ($paymentData['status'] ?? 'pending'), 'pending');
        $paymentMethod = $paymentData['method'] ?? ($paymentData['method_title'] ?? null);
        $paymentMethod = $paymentMethod !== null ? trim((string) $paymentMethod) : null;
        if ($paymentMethod === '') {
            $paymentMethod = null;
        }
        
        // Capturar old values para auditoria
        $oldOrder = $this->find($orderId);
        $previousPaymentStatus = $this->canonicalPaymentStatus((string) ($oldOrder['payment_status'] ?? 'none'), 'none');
        $this->assertPaymentMethodForPaidStatus($paymentStatus, $paymentMethod, $previousPaymentStatus, true);
        
        $sql = "UPDATE orders SET 
                payment_status = :payment_status,
                payment_method = :payment_method,
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            ':id' => $orderId,
            ':payment_status' => $paymentStatus,
            ':payment_method' => $paymentMethod,
        ]);
        
        if ($result) {
            $newOrder = $this->find($orderId);
            $this->safeAudit('UPDATE', 'orders', $orderId, $oldOrder, $newOrder);
        }
        
        return $result;
    }
    
    /**
     * Update shipping information
     * Substitui: $this->orderGateway->updateShipping()
     */
    public function updateShipping(int $orderId, array $shippingData): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $policy = new OrderDeliveryPolicy();
        $resolved = $policy->resolve([
            'delivery_mode' => (string) ($shippingData['delivery_mode'] ?? ''),
            'shipment_kind' => (string) ($shippingData['shipment_kind'] ?? ''),
            'fulfillment_status' => (string) ($shippingData['status'] ?? 'pending'),
            'carrier_id' => $shippingData['carrier_id'] ?? null,
            'tracking_code' => $shippingData['tracking_code'] ?? null,
            'estimated_delivery_at' => $shippingData['estimated_delivery_at'] ?? ($shippingData['eta'] ?? null),
            'shipped_at' => $shippingData['shipped_at'] ?? null,
            'delivered_at' => $shippingData['delivered_at'] ?? null,
            'logistics_notes' => $shippingData['logistics_notes'] ?? null,
            'bag_id' => $shippingData['bag_id'] ?? null,
        ], false);
        $fulfillmentStatus = $this->canonicalFulfillmentStatus((string) ($resolved['fulfillment_status'] ?? 'pending'), 'pending');
        $deliveryMode = OrderService::normalizeDeliveryMode((string) ($resolved['delivery_mode'] ?? 'shipment'));
        $shipmentKind = OrderService::normalizeShipmentKind(
            (string) ($resolved['shipment_kind'] ?? ''),
            $deliveryMode
        );
        $carrierId = isset($resolved['carrier_id']) && (int) $resolved['carrier_id'] > 0 ? (int) $resolved['carrier_id'] : null;
        $bagId = isset($resolved['bag_id']) && (int) $resolved['bag_id'] > 0 ? (int) $resolved['bag_id'] : null;
        $trackingCode = trim((string) ($resolved['tracking_code'] ?? ''));
        $trackingCode = $trackingCode !== '' ? $trackingCode : null;
        $estimatedDeliveryAt = $this->normalizeDateTime($resolved['estimated_delivery_at'] ?? ($shippingData['eta'] ?? null));
        $shippedAt = $this->normalizeDateTime($resolved['shipped_at'] ?? null);
        $deliveredAt = $this->normalizeDateTime($resolved['delivered_at'] ?? null);
        $logisticsNotes = trim((string) ($resolved['logistics_notes'] ?? ''));
        $logisticsNotes = $logisticsNotes !== '' ? $logisticsNotes : null;
        $shippingPayload = $shippingData;
        $shippingPayload['status'] = $fulfillmentStatus;
        $shippingPayload['delivery_mode'] = $deliveryMode;
        $shippingPayload['shipment_kind'] = $shipmentKind;
        $shippingPayload['carrier_id'] = $carrierId;
        $shippingPayload['bag_id'] = $bagId;
        $shippingPayload['tracking_code'] = $trackingCode;
        $shippingPayload['estimated_delivery_at'] = $estimatedDeliveryAt;
        $shippingPayload['shipped_at'] = $shippedAt;
        $shippingPayload['delivered_at'] = $deliveredAt;
        $shippingPayload['logistics_notes'] = $logisticsNotes;
        $shippingJson = json_encode($shippingPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Capturar old values para auditoria
        $oldOrder = $this->find($orderId);
        
        $sql = "UPDATE orders SET 
                fulfillment_status = :fulfillment_status,
                delivery_status = :delivery_status,
                delivery_mode = :delivery_mode,
                shipment_kind = :shipment_kind,
                carrier_id = :carrier_id,
                bag_id = :bag_id,
                tracking_code = :tracking_code,
                estimated_delivery_at = :estimated_delivery_at,
                shipped_at = :shipped_at,
                delivered_at = :delivered_at,
                logistics_notes = :logistics_notes,
                shipping_info = :shipping_info,
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            ':id' => $orderId,
            ':fulfillment_status' => $fulfillmentStatus,
            ':delivery_status' => $fulfillmentStatus,
            ':delivery_mode' => $deliveryMode,
            ':shipment_kind' => $shipmentKind,
            ':carrier_id' => $carrierId,
            ':bag_id' => $bagId,
            ':tracking_code' => $trackingCode,
            ':estimated_delivery_at' => $estimatedDeliveryAt,
            ':shipped_at' => $shippedAt,
            ':delivered_at' => $deliveredAt,
            ':logistics_notes' => $logisticsNotes,
            ':shipping_info' => $shippingJson,
        ]);
        
        if ($result) {
            $newOrder = $this->find($orderId);
            $this->safeAudit('UPDATE', 'orders', $orderId, $oldOrder, $newOrder);
        }
        
        return $result;
    }
    
    /**
     * Update order status (com suporte a pagamento e metadata)
     * Substitui: $this->orderGateway->updateStatus()
     */
    public function updateStatusComplete(int $id, string $status, ?bool $paid = null, array $meta = []): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $status = $this->canonicalOrderStatus($status, 'open');
        
        // Capturar old values para auditoria
        $oldOrder = $this->find($id);
        $oldDeliveryMode = is_array($oldOrder)
            ? (string) ($oldOrder['delivery_mode'] ?? 'shipment')
            : 'shipment';
        $previousPaymentStatus = $this->canonicalPaymentStatus((string) ($oldOrder['payment_status'] ?? 'none'), 'none');
        $existingPaymentMethod = trim((string) ($oldOrder['payment_method'] ?? ''));

        $sql = "UPDATE orders SET status = :status";
        $params = [':id' => $id, ':status' => $status];

        $paymentStatus = null;
        if ($paid !== null) {
            $paymentStatus = $paid ? 'paid' : 'pending';
        } elseif (array_key_exists('payment_status', $meta)) {
            $paymentStatus = $this->canonicalPaymentStatus((string) $meta['payment_status'], 'pending');
        } elseif (array_key_exists('retrato_payment_status', $meta)) {
            $paymentStatus = $this->canonicalPaymentStatus((string) $meta['retrato_payment_status'], 'pending');
        }
        if ($paymentStatus !== null) {
            $sql .= ", payment_status = :payment_status";
            $params[':payment_status'] = $paymentStatus;
        }
        $nextPaymentStatus = $paymentStatus ?? $previousPaymentStatus;

        $fulfillmentStatus = null;
        if (array_key_exists('fulfillment_status', $meta)) {
            $fulfillmentStatus = $this->canonicalFulfillmentStatus((string) $meta['fulfillment_status'], 'pending');
        } elseif (array_key_exists('retrato_fulfillment_status', $meta)) {
            $fulfillmentStatus = $this->canonicalFulfillmentStatus((string) $meta['retrato_fulfillment_status'], 'pending');
        }
        if ($fulfillmentStatus !== null) {
            $sql .= ", fulfillment_status = :fulfillment_status";
            $params[':fulfillment_status'] = $fulfillmentStatus;
            $sql .= ", delivery_status = :delivery_status";
            $params[':delivery_status'] = $fulfillmentStatus;
        }

        $deliveryMode = null;
        if (array_key_exists('delivery_mode', $meta)) {
            $deliveryMode = OrderService::normalizeDeliveryMode((string) $meta['delivery_mode']);
        } elseif (array_key_exists('retrato_delivery_mode', $meta)) {
            $deliveryMode = OrderService::normalizeDeliveryMode((string) $meta['retrato_delivery_mode']);
        }
        if ($deliveryMode !== null) {
            $sql .= ", delivery_mode = :delivery_mode";
            $params[':delivery_mode'] = $deliveryMode;
        }

        $shipmentKind = null;
        if (array_key_exists('shipment_kind', $meta)) {
            $shipmentKind = OrderService::normalizeShipmentKind(
                (string) $meta['shipment_kind'],
                $deliveryMode ?? OrderService::normalizeDeliveryMode($oldDeliveryMode)
            );
        } elseif (array_key_exists('retrato_shipment_kind', $meta)) {
            $shipmentKind = OrderService::normalizeShipmentKind(
                (string) $meta['retrato_shipment_kind'],
                $deliveryMode ?? OrderService::normalizeDeliveryMode($oldDeliveryMode)
            );
        }
        if ($shipmentKind !== null || ($deliveryMode !== null && $deliveryMode !== 'shipment')) {
            $sql .= ", shipment_kind = :shipment_kind";
            $params[':shipment_kind'] = $deliveryMode === 'shipment' ? $shipmentKind : null;
        }

        if (array_key_exists('bag_id', $meta) || array_key_exists('retrato_bag_id', $meta)) {
            $bagIdRaw = $meta['bag_id'] ?? $meta['retrato_bag_id'] ?? null;
            $bagId = $bagIdRaw !== null && $bagIdRaw !== '' && (int) $bagIdRaw > 0 ? (int) $bagIdRaw : null;
            $sql .= ", bag_id = :bag_id";
            $params[':bag_id'] = $bagId;
        }

        if (array_key_exists('sales_channel', $meta) || array_key_exists('retrato_sales_channel', $meta)) {
            $salesChannelValue = $meta['sales_channel'] ?? $meta['retrato_sales_channel'] ?? null;
            $salesChannel = trim((string) $salesChannelValue);
            $sql .= ", sales_channel = :sales_channel";
            $params[':sales_channel'] = $salesChannel !== '' ? $salesChannel : null;
        }

        $nextPaymentMethod = $existingPaymentMethod !== '' ? $existingPaymentMethod : null;
        if (array_key_exists('payment_method', $meta)) {
            $paymentMethod = trim((string) $meta['payment_method']);
            $sql .= ", payment_method = :payment_method";
            $params[':payment_method'] = $paymentMethod !== '' ? $paymentMethod : null;
            $nextPaymentMethod = $paymentMethod !== '' ? $paymentMethod : null;
        }
        $this->assertPaymentMethodForPaidStatus(
            $nextPaymentStatus,
            $nextPaymentMethod,
            $previousPaymentStatus,
            array_key_exists('payment_method', $meta)
        );

        if (array_key_exists('shipping_info', $meta)) {
            $shippingInfo = $meta['shipping_info'];
            if (is_array($shippingInfo)) {
                if (isset($shippingInfo['status'])) {
                    $shippingInfo['status'] = $this->canonicalFulfillmentStatus((string) $shippingInfo['status'], 'pending');
                } elseif ($fulfillmentStatus !== null) {
                    $shippingInfo['status'] = $fulfillmentStatus;
                }
                if (isset($shippingInfo['delivery_mode'])) {
                    $deliveryMode = OrderService::normalizeDeliveryMode((string) $shippingInfo['delivery_mode']);
                } elseif ($deliveryMode === null) {
                    $deliveryMode = OrderService::normalizeDeliveryMode('');
                }
                if ($deliveryMode !== null) {
                    $shippingInfo['delivery_mode'] = $deliveryMode;
                    $sql .= ", delivery_mode = :delivery_mode_from_shipping";
                    $params[':delivery_mode_from_shipping'] = $deliveryMode;
                }
                $shipmentKind = OrderService::normalizeShipmentKind(
                    (string) ($shippingInfo['shipment_kind'] ?? ($shipmentKind ?? '')),
                    $deliveryMode ?? OrderService::normalizeDeliveryMode($oldDeliveryMode)
                );
                $shippingInfo['shipment_kind'] = $shipmentKind;
                $sql .= ", shipment_kind = :shipment_kind_from_shipping";
                $params[':shipment_kind_from_shipping'] = $deliveryMode === 'shipment' ? $shipmentKind : null;

                $carrierId = isset($shippingInfo['carrier_id']) && (int) $shippingInfo['carrier_id'] > 0
                    ? (int) $shippingInfo['carrier_id']
                    : null;
                $bagId = isset($shippingInfo['bag_id']) && (int) $shippingInfo['bag_id'] > 0
                    ? (int) $shippingInfo['bag_id']
                    : null;
                $trackingCode = trim((string) ($shippingInfo['tracking_code'] ?? ''));
                $trackingCode = $trackingCode !== '' ? $trackingCode : null;
                $estimatedDeliveryAt = $this->normalizeDateTime($shippingInfo['estimated_delivery_at'] ?? ($shippingInfo['eta'] ?? null));
                $shippedAt = $this->normalizeDateTime($shippingInfo['shipped_at'] ?? null);
                $deliveredAt = $this->normalizeDateTime($shippingInfo['delivered_at'] ?? null);
                $logisticsNotes = trim((string) ($shippingInfo['logistics_notes'] ?? ''));
                $logisticsNotes = $logisticsNotes !== '' ? $logisticsNotes : null;

                $sql .= ", carrier_id = :carrier_id";
                $params[':carrier_id'] = $carrierId;
                $sql .= ", bag_id = :bag_id_from_shipping";
                $params[':bag_id_from_shipping'] = $bagId;
                $sql .= ", tracking_code = :tracking_code";
                $params[':tracking_code'] = $trackingCode;
                $sql .= ", estimated_delivery_at = :estimated_delivery_at";
                $params[':estimated_delivery_at'] = $estimatedDeliveryAt;
                $sql .= ", shipped_at = :shipped_at";
                $params[':shipped_at'] = $shippedAt;
                $sql .= ", delivered_at = :delivered_at";
                $params[':delivered_at'] = $deliveredAt;
                $sql .= ", logistics_notes = :logistics_notes";
                $params[':logistics_notes'] = $logisticsNotes;

                $shippingInfo = json_encode($shippingInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (!is_string($shippingInfo)) {
                $shippingInfo = null;
            }
            $sql .= ", shipping_info = :shipping_info";
            $params[':shipping_info'] = $shippingInfo;
        }
        
        if ($status === 'completed' && !isset($meta['skip_completed_at'])) {
            $sql .= ", completed_at = NOW()";
        } elseif ($status !== 'completed' && !isset($meta['keep_completed_at'])) {
            $sql .= ", completed_at = NULL";
        }
        
        $sql .= ", updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $newOrder = $this->find($id);
            $this->safeAudit('UPDATE', 'orders', $id, $oldOrder, $newOrder);
        }
        
        return $result;
    }
    
    /**
     * Soft delete - move para lixeira
     * Substitui: $this->orderGateway->delete($orderId, false)
     */
    public function trash(int $id, string $deletedAt, int $deletedBy): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        $sql = "UPDATE orders SET 
                archived = 1,
                status = :status,
                custom_field = JSON_SET(
                    COALESCE(custom_field, '{}'),
                    '$.deleted_at', :deleted_at,
                    '$.deleted_by', :deleted_by
                ),
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':status' => OrderService::normalizeOrderStatus('trash'),
            ':deleted_at' => $deletedAt,
            ':deleted_by' => $deletedBy,
        ]);
    }
    
    /**
     * Restore from trash
     */
    public function restore(int $id): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        $sql = "UPDATE orders SET 
                archived = 0,
                status = :status,
                custom_field = JSON_REMOVE(
                    COALESCE(custom_field, '{}'),
                    '$.deleted_at',
                    '$.deleted_by'
                ),
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':status' => OrderService::normalizeOrderStatus('open'),
        ]);
    }
    
    /**
     * Hard delete (permanent)
     * Substitui: $this->orderGateway->delete($orderId, true)
     */
    public function permanentDelete(int $id): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        // Os items serão deletados automaticamente (CASCADE)
        $sql = "DELETE FROM orders WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Save order items
     */
    public function saveOrderItems(int $orderId, array $items): void
    {
        if (!$this->pdo) {
            return;
        }

        // Instantiate repository before transaction to avoid implicit commits
        // from schema ensure routines.
        $productRepo = new ProductRepository($this->pdo);

        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $oldQuantities = $this->getOrderItemQuantities($orderId);
            $newQuantities = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $productSku = (int) ($item['product_sku'] ?? 0);
                $quantity = max(0, (int) ($item['quantity'] ?? 0));
                if ($productSku <= 0 || $quantity <= 0) {
                    continue;
                }
                $newQuantities[$productSku] = ($newQuantities[$productSku] ?? 0) + $quantity;
            }

            $allSkus = array_unique(array_merge(array_keys($oldQuantities), array_keys($newQuantities)));
            foreach ($allSkus as $sku) {
                $oldQty = (int) ($oldQuantities[$sku] ?? 0);
                $newQty = (int) ($newQuantities[$sku] ?? 0);
                $delta = $newQty - $oldQty;
                if ($delta === 0) {
                    continue;
                }

                if ($delta > 0) {
                    if (!$productRepo->isAvailable((int) $sku, $delta)) {
                        throw new \RuntimeException('Disponibilidade insuficiente para SKU ' . $sku . '.');
                    }
                    $ok = $productRepo->decrementQuantity(
                        (int) $sku,
                        $delta,
                        $orderId,
                        'Venda via pedido #' . $orderId
                    );
                    if (!$ok) {
                        throw new \RuntimeException('Falha ao debitar disponibilidade do SKU ' . $sku . '.');
                    }
                    continue;
                }

                $ok = $productRepo->incrementQuantity(
                    (int) $sku,
                    abs($delta),
                    'ajuste',
                    'Ajuste de itens do pedido #' . $orderId
                );
                if (!$ok) {
                    throw new \RuntimeException('Falha ao reverter disponibilidade do SKU ' . $sku . '.');
                }
            }

            $stmtDelete = $this->pdo->prepare('DELETE FROM order_items WHERE order_id = :order_id');
            $stmtDelete->execute([':order_id' => $orderId]);

            $sql = "INSERT INTO order_items (
                order_id, product_sku, product_name, sku, quantity, price, total
            ) VALUES (
                :order_id, :product_sku, :product_name, :sku, :quantity, :price, :total
            )";
            $stmtInsert = $this->pdo->prepare($sql);

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $productSku = (int) ($item['product_sku'] ?? 0);
                $quantity = max(0, (int) ($item['quantity'] ?? 0));
                if ($productSku <= 0 || $quantity <= 0) {
                    continue;
                }
                $price = isset($item['price']) ? (float) $item['price'] : 0.0;
                $total = isset($item['total']) ? (float) $item['total'] : ($price * $quantity);
                $name = trim((string) ($item['name'] ?? $item['product_name'] ?? ''));

                $stmtInsert->execute([
                    ':order_id' => $orderId,
                    ':product_sku' => $productSku,
                    ':product_name' => $name,
                    ':sku' => isset($item['sku']) && $item['sku'] !== '' ? (string) $item['sku'] : (string) $productSku,
                    ':quantity' => $quantity,
                    ':price' => $price,
                    ':total' => $total,
                ]);
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Lista itens de pedidos com informações de produtos (para consignação)
     * 
     * @param array<int> $orderIds
     * @return array
     */
    public function listOrderItemsWithProducts(array $orderIds): array
    {
        if (empty($orderIds) || !$this->pdo) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        // MODELO UNIFICADO: Usar product_sku + LEFT JOIN com products
        $sql = "
            SELECT 
                oi.id,
                oi.order_id,
                oi.product_sku,
                0 AS variation_id,
                oi.product_name,
                oi.sku,
                oi.quantity,
                oi.price,
                oi.total,
                oi.product_name AS name,
                COALESCE(p.name, oi.product_name) AS product_name_from_catalog,
                CAST(COALESCE(p.sku, oi.product_sku) AS CHAR) AS product_sku_from_catalog,
                COALESCE(p.price, oi.price) AS product_price
            FROM order_items oi
            LEFT JOIN products p ON oi.product_sku = p.sku
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.order_id, oi.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($orderIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca resumo do pedido (para consignação)
     * 
     * @param int $orderId
     * @return array|null
     */
    public function findOrderSummary(int $orderId): ?array
    {
        if ($orderId <= 0 || !$this->pdo) {
            return null;
        }

        $personColumn = $this->personColumn();

        $sql = "
            SELECT 
                id,
                {$personColumn} AS pessoa_id,
                status,
                billing_name,
                billing_email,
                total,
                created_at,
                updated_at
            FROM orders
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Conta pedidos que contêm um produto específico
     * 
     * @param int $productSku SKU do produto
     * @return int
     */
    public function countOrdersForProduct(int $productSku): int
    {
        if ($productSku <= 0 || !$this->pdo) {
            return 0;
        }

        $sql = "
            SELECT COUNT(DISTINCT o.id) as total
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            WHERE oi.product_sku = :product_sku
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':product_sku' => $productSku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Lista pedidos que contêm um produto específico
     * 
     * @param int $productSku SKU do produto
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listOrdersForProduct(int $productSku, int $limit = 0, int $offset = 0): array
    {
        if ($productSku <= 0 || !$this->pdo) {
            return [];
        }

        $personColumn = $this->personColumn();
        $useLimit = $limit > 0;

        $sql = "
            SELECT DISTINCT
                o.id as order_id,
                o.{$personColumn} AS pessoa_id,
                o.status,
                o.billing_name,
                o.billing_email,
                o.total,
                o.payment_status,
                o.payment_method,
                o.created_at,
                o.updated_at
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            WHERE oi.product_sku = :product_sku
            ORDER BY o.created_at DESC";
        if ($useLimit) {
            $sql .= "\n            LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':product_sku', $productSku, PDO::PARAM_INT);
        if ($useLimit) {
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeOrderRow(array $row): array
    {
        $row['status'] = $this->canonicalOrderStatus((string) ($row['status'] ?? 'open'), 'open');
        $row['payment_status'] = $this->canonicalPaymentStatus((string) ($row['payment_status'] ?? 'none'), 'none');
        $row['fulfillment_status'] = $this->canonicalFulfillmentStatus((string) ($row['fulfillment_status'] ?? 'pending'), 'pending');

        $personId = isset($row['pessoa_id']) ? (int) $row['pessoa_id'] : 0;
        $customerId = isset($row['customer_id']) ? (int) $row['customer_id'] : 0;
        if ($personId <= 0 && $customerId > 0) {
            $row['pessoa_id'] = $customerId;
        }
        if ($customerId <= 0 && $personId > 0) {
            $row['customer_id'] = $personId;
        }

        $billingName = trim((string) ($row['billing_name'] ?? ''));
        [$billingFirstName, $billingLastName] = $this->splitFullName($billingName);
        $billingEmail = trim((string) ($row['billing_email'] ?? ''));
        $billingPhone = trim((string) ($row['billing_phone'] ?? ''));
        $billingAddress1 = trim((string) ($row['billing_address_1'] ?? ''));
        $billingAddress2 = trim((string) ($row['billing_address_2'] ?? ''));
        $billingCity = trim((string) ($row['billing_city'] ?? ''));
        $billingState = trim((string) ($row['billing_state'] ?? ''));
        $billingZip = trim((string) ($row['billing_zip'] ?? ''));
        $billingCountry = trim((string) ($row['billing_country'] ?? 'BR'));

        $billing = [
            'first_name' => $billingFirstName,
            'last_name' => $billingLastName,
            'full_name' => $billingName,
            'email' => $billingEmail,
            'phone' => $billingPhone,
            'address_1' => $billingAddress1,
            'address_2' => $billingAddress2,
            'number' => '',
            'neighborhood' => '',
            'city' => $billingCity,
            'state' => $billingState,
            'postcode' => $billingZip,
            'country' => $billingCountry !== '' ? $billingCountry : 'BR',
        ];

        $shippingInfo = $this->decodeJson($row['shipping_info'] ?? null);
        $deliveryMode = OrderService::normalizeDeliveryMode(
            (string) ($row['delivery_mode'] ?? ($shippingInfo['delivery_mode'] ?? ''))
        );
        $shipmentKind = OrderService::normalizeShipmentKind(
            (string) ($row['shipment_kind'] ?? ($shippingInfo['shipment_kind'] ?? '')),
            $deliveryMode
        );
        $deliveryStatus = $this->canonicalFulfillmentStatus(
            (string) ($row['delivery_status'] ?? ($shippingInfo['status'] ?? ($row['fulfillment_status'] ?? 'pending'))),
            'pending'
        );
        $carrierId = isset($row['carrier_id']) && (int) $row['carrier_id'] > 0
            ? (int) $row['carrier_id']
            : (isset($shippingInfo['carrier_id']) && (int) $shippingInfo['carrier_id'] > 0 ? (int) $shippingInfo['carrier_id'] : null);
        $bagId = isset($row['bag_id']) && (int) $row['bag_id'] > 0
            ? (int) $row['bag_id']
            : (isset($shippingInfo['bag_id']) && (int) $shippingInfo['bag_id'] > 0 ? (int) $shippingInfo['bag_id'] : null);
        $trackingCode = trim((string) ($row['tracking_code'] ?? ($shippingInfo['tracking_code'] ?? '')));
        $estimatedDeliveryAt = $this->coalesceString([
            $row['estimated_delivery_at'] ?? null,
            $shippingInfo['estimated_delivery_at'] ?? null,
            $shippingInfo['eta'] ?? null,
        ]);
        $shippedAt = $this->coalesceString([
            $row['shipped_at'] ?? null,
            $shippingInfo['shipped_at'] ?? null,
        ]);
        $deliveredAt = $this->coalesceString([
            $row['delivered_at'] ?? null,
            $shippingInfo['delivered_at'] ?? null,
        ]);
        $logisticsNotes = $this->coalesceString([
            $row['logistics_notes'] ?? null,
            $shippingInfo['logistics_notes'] ?? null,
        ]);

        $carrierName = trim((string) ($shippingInfo['carrier'] ?? ''));

        $shippingInfo['status'] = $deliveryStatus;
        $shippingInfo['delivery_mode'] = $deliveryMode;
        $shippingInfo['shipment_kind'] = $shipmentKind;
        $shippingInfo['carrier_id'] = $carrierId;
        $shippingInfo['bag_id'] = $bagId;
        $shippingInfo['carrier'] = $carrierName;
        $shippingInfo['tracking_code'] = $trackingCode !== '' ? $trackingCode : null;
        $shippingInfo['estimated_delivery_at'] = $estimatedDeliveryAt !== '' ? $estimatedDeliveryAt : null;
        $shippingInfo['eta'] = $estimatedDeliveryAt !== '' ? $estimatedDeliveryAt : null;
        $shippingInfo['shipped_at'] = $shippedAt !== '' ? $shippedAt : null;
        $shippingInfo['delivered_at'] = $deliveredAt !== '' ? $deliveredAt : null;
        $shippingInfo['logistics_notes'] = $logisticsNotes !== '' ? $logisticsNotes : null;

        $row['delivery_mode'] = $deliveryMode;
        $row['shipment_kind'] = $shipmentKind;
        $row['delivery_status'] = $deliveryStatus;
        $row['fulfillment_status'] = $deliveryStatus;
        $row['carrier_id'] = $carrierId;
        $row['bag_id'] = $bagId;
        $row['tracking_code'] = $trackingCode !== '' ? $trackingCode : null;
        $row['estimated_delivery_at'] = $estimatedDeliveryAt !== '' ? $estimatedDeliveryAt : null;
        $row['shipped_at'] = $shippedAt !== '' ? $shippedAt : null;
        $row['delivered_at'] = $deliveredAt !== '' ? $deliveredAt : null;
        $row['logistics_notes'] = $logisticsNotes !== '' ? $logisticsNotes : null;
        $row['shipping_info'] = $shippingInfo;

        $shipping = $billing;
        if (isset($shippingInfo['address']) && is_array($shippingInfo['address'])) {
            $shippingAddress = $shippingInfo['address'];
            $shipping['full_name'] = trim((string) ($shippingAddress['full_name'] ?? $shipping['full_name']));
            [$shippingFirstName, $shippingLastName] = $this->splitFullName($shipping['full_name']);
            $shipping['first_name'] = $shippingFirstName;
            $shipping['last_name'] = $shippingLastName;
            $shipping['email'] = trim((string) ($shippingAddress['email'] ?? $shipping['email']));
            $shipping['phone'] = trim((string) ($shippingAddress['phone'] ?? $shipping['phone']));
            $shipping['address_1'] = trim((string) ($shippingAddress['address_1'] ?? $shipping['address_1']));
            $shipping['address_2'] = trim((string) ($shippingAddress['address_2'] ?? $shipping['address_2']));
            $shipping['number'] = trim((string) ($shippingAddress['number'] ?? $shipping['number']));
            $shipping['neighborhood'] = trim((string) ($shippingAddress['neighborhood'] ?? $shipping['neighborhood']));
            $shipping['city'] = trim((string) ($shippingAddress['city'] ?? $shipping['city']));
            $shipping['state'] = trim((string) ($shippingAddress['state'] ?? $shipping['state']));
            $shipping['postcode'] = trim((string) ($shippingAddress['postcode'] ?? $shipping['postcode']));
            $shipping['country'] = trim((string) ($shippingAddress['country'] ?? $shipping['country']));
        }

        $metaData = [];
        $this->appendMetaEntry($metaData, 'retrato_payment_status', $row['payment_status'] ?? null);
        $this->appendMetaEntry($metaData, 'retrato_fulfillment_status', $row['fulfillment_status'] ?? null);
        $this->appendMetaEntry($metaData, 'retrato_delivery_mode', $deliveryMode);
        $this->appendMetaEntry($metaData, 'retrato_shipment_kind', $shipmentKind);
        $this->appendMetaEntry($metaData, 'retrato_bag_id', $bagId);
        $this->appendMetaEntry($metaData, 'retrato_sales_channel', $row['sales_channel'] ?? null);
        $this->appendMetaEntry($metaData, 'retrato_shipping_provider', $carrierName !== '' ? $carrierName : null);
        $this->appendMetaEntry($metaData, 'retrato_tracking_code', $trackingCode !== '' ? $trackingCode : null);
        $this->appendMetaEntry($metaData, 'retrato_shipping_eta', $estimatedDeliveryAt !== '' ? $estimatedDeliveryAt : null);

        $customField = $this->decodeJson($row['custom_field'] ?? null);
        $row['payment_method'] = $this->resolvePaymentMethodLabel(
            $row['payment_method'] ?? null,
            $customField['retrato_payment_entries'] ?? null
        );
        if (isset($customField['retrato_payment_entries'])) {
            $this->appendMetaEntry($metaData, 'retrato_payment_entries', $customField['retrato_payment_entries']);
        }
        if (array_key_exists('retrato_opening_fee_deferred', $customField)) {
            $this->appendMetaEntry($metaData, 'retrato_opening_fee_deferred', $customField['retrato_opening_fee_deferred']);
        }
        if (array_key_exists('retrato_opening_fee_value', $customField)) {
            $this->appendMetaEntry($metaData, 'retrato_opening_fee_value', $customField['retrato_opening_fee_value']);
        }

        $normalized = $row;
        $normalized['customer_note'] = (string) ($row['buyer_note'] ?? '');
        $normalized['billing'] = $billing;
        $normalized['shipping'] = $shipping;
        $normalized['meta_data'] = $metaData;
        $normalized['shipping_lines'] = [[
            'id' => 0,
            'method_title' => $this->deliveryModeLabel($deliveryMode),
            'total' => (string) ($row['shipping_total'] ?? '0.00'),
        ]];
        $normalized['payment_method_title'] = (string) ($row['payment_method'] ?? '');
        $normalized['date_created'] = $row['ordered_at'] ?? ($row['created_at'] ?? null);
        $normalized['date_paid'] = ($row['payment_status'] ?? '') === 'paid'
            ? ($row['updated_at'] ?? $normalized['date_created'])
            : null;
        $normalized['date_completed'] = ($row['status'] ?? '') === 'completed'
            ? ($row['completed_at'] ?? ($row['updated_at'] ?? null))
            : null;

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeOrderPayload(array $data, ?int $personId): array
    {
        $billing = isset($data['billing']) && is_array($data['billing']) ? $data['billing'] : [];
        $shipping = isset($data['shipping']) && is_array($data['shipping']) ? $data['shipping'] : [];
        $shippingInfo = isset($data['shipping_info']) && is_array($data['shipping_info'])
            ? $data['shipping_info']
            : $this->decodeJson($data['shipping_info'] ?? null);

        $billingName = $this->coalesceString([
            $data['billing_name'] ?? null,
            $data['billing_full_name'] ?? null,
            $billing['full_name'] ?? null,
        ]);
        $billingEmail = $this->coalesceString([
            $data['billing_email'] ?? null,
            $billing['email'] ?? null,
        ]);
        $billingPhone = $this->coalesceString([
            $data['billing_phone'] ?? null,
            $billing['phone'] ?? null,
        ]);
        $billingAddress1 = $this->coalesceString([
            $data['billing_address_1'] ?? null,
            $billing['address_1'] ?? null,
        ]);
        $billingAddress2 = $this->coalesceString([
            $data['billing_address_2'] ?? null,
            $billing['address_2'] ?? null,
        ]);
        $billingCity = $this->coalesceString([
            $data['billing_city'] ?? null,
            $billing['city'] ?? null,
        ]);
        $billingState = $this->coalesceString([
            $data['billing_state'] ?? null,
            $billing['state'] ?? null,
        ]);
        $billingZip = $this->coalesceString([
            $data['billing_zip'] ?? null,
            $data['billing_postcode'] ?? null,
            $billing['postcode'] ?? null,
        ]);
        $billingCountry = strtoupper($this->coalesceString([
            $data['billing_country'] ?? null,
            $billing['country'] ?? null,
        ], 'BR'));

        $status = $this->canonicalOrderStatus($this->coalesceString([$data['status'] ?? null], 'open'), 'open');
        $paymentStatus = $this->canonicalPaymentStatus($this->coalesceString([
            $data['payment_status'] ?? null,
            $data['payment']['status'] ?? null,
        ], 'none'), 'none');
        $paymentMethod = $this->coalesceString([
            $data['payment_method'] ?? null,
            $data['payment']['method'] ?? null,
        ]);
        $fulfillmentStatus = $this->canonicalFulfillmentStatus($this->coalesceString([
            $data['fulfillment_status'] ?? null,
            $data['delivery_status'] ?? null,
            $shippingInfo['status'] ?? null,
        ], 'pending'), 'pending');
        $policy = new OrderDeliveryPolicy();
        $resolvedDelivery = $policy->resolve([
            'delivery_mode' => $this->coalesceString([
                $data['delivery_mode'] ?? null,
                $shippingInfo['delivery_mode'] ?? null,
            ]),
            'shipment_kind' => $this->coalesceString([
                $data['shipment_kind'] ?? null,
                $shippingInfo['shipment_kind'] ?? null,
            ]),
            'fulfillment_status' => $fulfillmentStatus,
            'carrier_id' => $data['carrier_id'] ?? ($shippingInfo['carrier_id'] ?? null),
            'tracking_code' => $this->coalesceString([
                $data['tracking_code'] ?? null,
                $shippingInfo['tracking_code'] ?? null,
            ]),
            'estimated_delivery_at' => $data['estimated_delivery_at'] ?? ($shippingInfo['estimated_delivery_at'] ?? ($shippingInfo['eta'] ?? null)),
            'shipped_at' => $data['shipped_at'] ?? ($shippingInfo['shipped_at'] ?? null),
            'delivered_at' => $data['delivered_at'] ?? ($shippingInfo['delivered_at'] ?? null),
            'logistics_notes' => $this->coalesceString([
                $data['logistics_notes'] ?? null,
                $shippingInfo['logistics_notes'] ?? null,
            ]),
            'bag_id' => $data['bag_id'] ?? ($shippingInfo['bag_id'] ?? null),
        ], false);

        $deliveryMode = (string) ($resolvedDelivery['delivery_mode'] ?? 'shipment');
        $shipmentKind = $resolvedDelivery['shipment_kind'] !== null
            ? (string) $resolvedDelivery['shipment_kind']
            : null;
        $fulfillmentStatus = $this->canonicalFulfillmentStatus((string) ($resolvedDelivery['fulfillment_status'] ?? 'pending'), 'pending');
        $carrierId = isset($resolvedDelivery['carrier_id']) && (int) $resolvedDelivery['carrier_id'] > 0
            ? (int) $resolvedDelivery['carrier_id']
            : null;
        $bagId = isset($resolvedDelivery['bag_id']) && (int) $resolvedDelivery['bag_id'] > 0
            ? (int) $resolvedDelivery['bag_id']
            : null;
        $trackingCode = trim((string) ($resolvedDelivery['tracking_code'] ?? ''));
        $estimatedDeliveryAt = $this->normalizeDateTime($resolvedDelivery['estimated_delivery_at'] ?? null);
        $shippedAt = $this->normalizeDateTime($resolvedDelivery['shipped_at'] ?? null);
        $deliveredAt = $this->normalizeDateTime($resolvedDelivery['delivered_at'] ?? null);
        $logisticsNotes = trim((string) ($resolvedDelivery['logistics_notes'] ?? ''));

        $shippingInfo['status'] = $fulfillmentStatus;
        $shippingInfo['delivery_mode'] = $deliveryMode;
        $shippingInfo['shipment_kind'] = $shipmentKind;
        $shippingInfo['carrier_id'] = $carrierId;
        $shippingInfo['bag_id'] = $bagId;
        $shippingInfo['tracking_code'] = $trackingCode !== '' ? $trackingCode : null;
        $shippingInfo['estimated_delivery_at'] = $estimatedDeliveryAt;
        $shippingInfo['eta'] = $estimatedDeliveryAt;
        $shippingInfo['shipped_at'] = $shippedAt;
        $shippingInfo['delivered_at'] = $deliveredAt;
        $shippingInfo['logistics_notes'] = $logisticsNotes !== '' ? $logisticsNotes : null;

        $subtotal = $this->floatValue($data['subtotal'] ?? null);
        if ($subtotal === null) {
            $subtotal = $this->calculateItemsSubtotal($data['items'] ?? []);
        }
        $shippingTotal = $this->floatValue($data['shipping_total'] ?? null);
        if ($shippingTotal === null) {
            $shippingTotal = $this->floatValue($shippingInfo['total'] ?? null) ?? 0.0;
        }
        $taxTotal = $this->floatValue($data['tax_total'] ?? null) ?? 0.0;
        $discountTotal = $this->floatValue($data['discount_total'] ?? null) ?? 0.0;
        $total = $this->floatValue($data['total'] ?? null);
        if ($total === null) {
            $total = max(0.0, ($subtotal ?? 0.0) + $shippingTotal + $taxTotal - $discountTotal);
        }

        $paymentEntries = null;
        if (isset($data['payment_entries']) && is_array($data['payment_entries'])) {
            $paymentEntries = $data['payment_entries'];
        } elseif (isset($data['payments']) && is_array($data['payments'])) {
            $paymentEntries = $data['payments'];
        }

        $customField = $this->decodeJson($data['custom_field'] ?? null);
        if ($paymentEntries !== null) {
            $customField['retrato_payment_entries'] = $paymentEntries;
        }
        if (array_key_exists('opening_fee_deferred', $data)) {
            $openingDeferredRaw = $data['opening_fee_deferred'];
            $customField['retrato_opening_fee_deferred'] = $openingDeferredRaw === true
                || $openingDeferredRaw === 1
                || $openingDeferredRaw === '1'
                || $openingDeferredRaw === 'on';
        }
        if (array_key_exists('opening_fee_value', $data)) {
            $customField['retrato_opening_fee_value'] = $this->floatValue($data['opening_fee_value']);
        }

        $paymentMethod = $this->resolvePaymentMethodLabel(
            $paymentMethod,
            $paymentEntries !== null
                ? $paymentEntries
                : ($customField['retrato_payment_entries'] ?? null)
        );

        return [
            'pessoa_id' => $personId,
            'status' => $status,
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'billing_phone' => $billingPhone,
            'billing_address_1' => $billingAddress1,
            'billing_address_2' => $billingAddress2,
            'billing_city' => $billingCity,
            'billing_state' => $billingState,
            'billing_zip' => $billingZip,
            'billing_country' => $billingCountry,
            'subtotal' => $subtotal ?? 0.0,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'total' => $total,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'fulfillment_status' => $fulfillmentStatus,
            'delivery_mode' => $deliveryMode,
            'shipment_kind' => $shipmentKind,
            'delivery_status' => $fulfillmentStatus,
            'carrier_id' => $carrierId,
            'bag_id' => $bagId,
            'tracking_code' => $trackingCode !== '' ? $trackingCode : null,
            'estimated_delivery_at' => $estimatedDeliveryAt,
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
            'logistics_notes' => $logisticsNotes !== '' ? $logisticsNotes : null,
            'buyer_note' => $this->coalesceString([$data['buyer_note'] ?? null]),
            'sales_channel' => $this->coalesceString([$data['sales_channel'] ?? null]),
            'currency' => strtoupper($this->coalesceString([$data['currency'] ?? null], 'BRL')),
            'ordered_at' => $this->coalesceString([$data['ordered_at'] ?? null], date('Y-m-d H:i:s')),
            'shipping_info' => $shippingInfo,
            'shipping_info_json' => empty($shippingInfo)
                ? null
                : json_encode($shippingInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'custom_field_json' => empty($customField)
                ? null
                : json_encode($customField, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'shipping' => $shipping,
        ];
    }

    /**
     * @param array<int, mixed> $candidates
     */
    private function coalesceString(array $candidates, string $default = ''): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }
        return $default;
    }

    /**
     * @param mixed $value
     */
    private function floatValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Input::parseNumber($value);
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
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function deliveryModeLabel(string $mode): string
    {
        return match ($mode) {
            'immediate_in_hand' => 'Em mãos agora',
            'store_pickup' => 'Retirada na loja',
            'shipment' => 'Enviar/Entregar',
            default => 'Entrega',
        };
    }

    /**
     * @param mixed $items
     */
    private function calculateItemsSubtotal($items): float
    {
        if (!is_array($items)) {
            return 0.0;
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($quantity <= 0) {
                continue;
            }
            $price = $this->floatValue($item['price'] ?? null) ?? 0.0;
            $lineTotal = $this->floatValue($item['total'] ?? null);
            $subtotal += $lineTotal !== null ? $lineTotal : ($price * $quantity);
        }

        return $subtotal;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, array{key: string, value: mixed}> $metaData
     * @param mixed $value
     */
    private function appendMetaEntry(array &$metaData, string $key, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $metaData[] = [
            'key' => $key,
            'value' => $value,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitFullName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [$fullName];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $first = array_shift($parts);
        $last = implode(' ', $parts);
        return [trim((string) $first), trim($last)];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildListOrdersWhere(array $filters): array
    {
        $whereSql = 'WHERE 1=1';
        $params = [];
        $personColumn = $this->personColumn();
        $stateColumn = $this->resolveOrderColumn(['shipping_state', 'billing_state']);
        $originColumn = $this->resolveOrderColumn(['sales_channel']);
        $totalColumn = $this->resolveOrderColumn(['total_sales', 'total']);
        $dateColumn = $this->resolveOrderColumn(['ordered_at', 'created_at', 'updated_at']);
        $numberColumn = $this->resolveOrderColumn(['number']);

        if (!empty($filters['status'])) {
            $whereSql .= ' AND status = :status';
            $params[':status'] = OrderService::normalizeOrderStatus((string) $filters['status']);
        }

        $personIdFilter = isset($filters['pessoa_id']) ? (int) $filters['pessoa_id'] : 0;
        if ($personIdFilter > 0) {
            $whereSql .= " AND {$personColumn} = :pessoa_id";
            $params[':pessoa_id'] = $personIdFilter;
        }

        if (!empty($filters['payment_status'])) {
            $whereSql .= ' AND payment_status = :payment_status';
            $params[':payment_status'] = OrderService::normalizePaymentStatus((string) $filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $searchClauses = [
                'CAST(id AS CHAR) LIKE :search',
                'billing_name LIKE :search',
                'billing_email LIKE :search',
                'status LIKE :search',
                'payment_status LIKE :search',
                'fulfillment_status LIKE :search',
                "EXISTS (
                    SELECT 1
                    FROM order_items oi
                    WHERE oi.order_id = orders.id
                      AND (
                        COALESCE(oi.product_name, '') LIKE :search
                        OR COALESCE(oi.sku, '') LIKE :search
                        OR CAST(COALESCE(oi.product_sku, 0) AS CHAR) LIKE :search
                      )
                )",
            ];
            if ($originColumn !== null) {
                $searchClauses[] = "{$originColumn} LIKE :search";
            }
            if ($stateColumn !== null) {
                $searchClauses[] = "{$stateColumn} LIKE :search";
            }
            if ($totalColumn !== null) {
                $searchClauses[] = "CAST({$totalColumn} AS CHAR) LIKE :search";
            }
            if ($numberColumn !== null) {
                $searchClauses[] = "CAST({$numberColumn} AS CHAR) LIKE :search";
            }
            $whereSql .= ' AND (' . implode(' OR ', $searchClauses) . ')';
            $params[':search'] = $search;
        }

        $columnFilterMap = [
            'filter_id' => 'CAST(id AS CHAR)',
            'filter_order' => 'CAST(id AS CHAR)',
            'filter_customer' => 'billing_name',
            'filter_status' => 'status',
            'filter_payment' => 'payment_status',
            'filter_fulfillment' => 'fulfillment_status',
            'filter_items' => 'CAST((SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = orders.id) AS CHAR)',
        ];
        if ($stateColumn !== null) {
            $columnFilterMap['filter_state'] = $stateColumn;
        }
        if ($originColumn !== null) {
            $columnFilterMap['filter_origin'] = $originColumn;
        }
        if ($totalColumn !== null) {
            $columnFilterMap['filter_total'] = "CAST({$totalColumn} AS CHAR)";
        }
        if ($dateColumn !== null) {
            $columnFilterMap['filter_date'] = "CONCAT_WS(' ', CAST({$dateColumn} AS CHAR), DATE_FORMAT({$dateColumn}, '%d/%m/%Y %H:%i'))";
        }
        foreach ($columnFilterMap as $filterKey => $columnSql) {
            if (!isset($filters[$filterKey])) {
                continue;
            }
            $raw = trim((string) $filters[$filterKey]);
            if ($raw === '') {
                continue;
            }
            $this->appendMultiLikeClause($whereSql, $params, $columnSql, $raw, $filterKey);
        }

        return [$whereSql, $params];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function appendMultiLikeClause(string &$whereSql, array &$params, string $columnSql, string $rawValue, string $filterKey): void
    {
        $values = array_values(array_filter(array_map('trim', explode(',', $rawValue)), static fn(string $value): bool => $value !== ''));
        if (empty($values)) {
            return;
        }

        $normalizedKey = preg_replace('/[^a-z0-9_]+/i', '_', $filterKey) ?: 'filter';
        if (count($values) === 1) {
            $paramKey = ':' . $normalizedKey;
            $whereSql .= " AND {$columnSql} LIKE {$paramKey}";
            $params[$paramKey] = '%' . $values[0] . '%';
            return;
        }

        $parts = [];
        foreach ($values as $index => $value) {
            $paramKey = ':' . $normalizedKey . '_' . $index;
            $parts[] = "{$columnSql} LIKE {$paramKey}";
            $params[$paramKey] = '%' . $value . '%';
        }

        $whereSql .= ' AND (' . implode(' OR ', $parts) . ')';
    }

    /**
     * @return array{column:string,direction:string}
     */
    private function normalizeOrderListSort(string $sortKey, string $sortDir): array
    {
        $direction = strtoupper(trim($sortDir)) === 'ASC' ? 'ASC' : 'DESC';
        $normalized = strtolower(trim($sortKey));
        $stateColumn = $this->resolveOrderColumn(['shipping_state', 'billing_state']) ?? 'id';
        $originColumn = $this->resolveOrderColumn(['sales_channel']) ?? 'id';
        $totalColumn = $this->resolveOrderColumn(['total_sales', 'total']) ?? 'id';
        $dateColumn = $this->resolveOrderColumn(['ordered_at', 'created_at', 'updated_at']) ?? 'id';
        $column = match ($normalized) {
            'id', 'order' => 'id',
            'customer' => 'billing_name',
            'state' => $stateColumn,
            'origin' => $originColumn,
            'status' => 'status',
            'payment' => 'payment_status',
            'fulfillment' => 'fulfillment_status',
            'total' => $totalColumn,
            'items' => '(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = orders.id)',
            'date', 'ordered_at', 'created_at' => $dateColumn,
            default => $dateColumn,
        };

        return [
            'column' => $column,
            'direction' => $direction,
        ];
    }

    private function resolveOrderColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($this->columnExists('orders', $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function personColumn(): string
    {
        if ($this->columnExists('orders', 'pessoa_id')) {
            return 'pessoa_id';
        }
        if ($this->columnExists('orders', 'customer_id')) {
            return 'customer_id';
        }
        return 'pessoa_id';
    }

    private function canonicalOrderStatus(?string $status, string $fallback = 'open'): string
    {
        $normalized = OrderService::normalizeOrderStatus($status);
        if (in_array($normalized, ['draft', 'open', 'completed', 'cancelled', 'refunded', 'trash', 'deleted'], true)) {
            return $normalized;
        }
        return $fallback;
    }

    private function canonicalPaymentStatus(?string $status, string $fallback = 'none'): string
    {
        $normalized = OrderService::normalizePaymentStatus($status);
        if (array_key_exists($normalized, OrderService::PAYMENT_STATUS_LABELS)) {
            return $normalized;
        }
        return $fallback;
    }

    private function canonicalFulfillmentStatus(?string $status, string $fallback = 'pending'): string
    {
        $normalized = OrderService::normalizeFulfillmentStatus($status);
        if (array_key_exists($normalized, OrderService::FULFILLMENT_STATUS_LABELS)) {
            return $normalized;
        }
        return $fallback;
    }

    /**
     * @param mixed $entriesRaw
     */
    private function resolvePaymentMethodLabel(?string $currentLabel, $entriesRaw = null): ?string
    {
        $label = trim((string) ($currentLabel ?? ''));
        $normalized = strtolower($label);
        $inferred = $this->inferPaymentMethodFromEntries($entriesRaw);

        if ($normalized === 'multi') {
            return $inferred ?? 'Pagamento dividido';
        }

        if ($label !== '' && $normalized !== 'manual') {
            return $label;
        }

        if ($inferred !== null) {
            return $inferred;
        }

        return $label !== '' ? $label : null;
    }

    /**
     * @param mixed $entriesRaw
     */
    private function inferPaymentMethodFromEntries($entriesRaw): ?string
    {
        if (is_string($entriesRaw)) {
            $decoded = json_decode($entriesRaw, true);
            if (is_array($decoded)) {
                $entriesRaw = $decoded;
            }
        }

        if (!is_array($entriesRaw) || empty($entriesRaw)) {
            return null;
        }

        $labels = [];
        foreach ($entriesRaw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $label = trim((string) ($entry['method_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($entry['method_type'] ?? ''));
            }
            if ($label === '') {
                $methodId = (int) ($entry['method_id'] ?? 0);
                if ($methodId > 0) {
                    $label = 'Metodo #' . $methodId;
                }
            }
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        if (empty($labels)) {
            return null;
        }

        $labels = array_values(array_unique($labels));
        if (count($labels) === 1) {
            return $labels[0];
        }

        return 'Pagamento dividido';
    }

    private function assertPaymentMethodForPaidStatus(
        ?string $targetPaymentStatus,
        ?string $targetPaymentMethod,
        ?string $previousPaymentStatus = null,
        bool $forceWhenPaid = false
    ): void {
        $targetStatus = $this->canonicalPaymentStatus($targetPaymentStatus, 'none');
        if ($targetStatus !== 'paid') {
            return;
        }

        $previousStatus = $this->canonicalPaymentStatus($previousPaymentStatus, 'none');
        if (!$forceWhenPaid && $previousStatus === 'paid') {
            return;
        }

        if (trim((string) $targetPaymentMethod) !== '') {
            return;
        }

        throw new \RuntimeException(
            'Nao e possivel marcar o pedido como pago sem informar o metodo de pagamento/recebimento.'
        );
    }

    private function safeAudit(string $action, string $table, int $recordId, ?array $oldValues, ?array $newValues): void
    {
        try {
            if ($this->pdo) {
                AuditService::setPDO($this->pdo);
            }
            AuditService::log($action, $table, $recordId, $oldValues, $newValues);
        } catch (\Throwable $e) {
            error_log('Falha ao registrar auditoria (OrderRepository::' . $action . '): ' . $e->getMessage());
        }
    }

    private function resolvePersonId(array $data): ?int
    {
        $personId = isset($data['pessoa_id'])
            ? (int) $data['pessoa_id']
            : (isset($data['customer_id']) ? (int) $data['customer_id'] : 0);
        return $personId > 0 ? $personId : null;
    }
}
