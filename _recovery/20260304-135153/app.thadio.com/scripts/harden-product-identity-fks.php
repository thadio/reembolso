#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

[$pdo, $connectionError] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, 'ERR:' . ($connectionError ?? 'database connection failed') . PHP_EOL);
    exit(1);
}

$apply = false;
$help = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        $help = true;
    }
}

if ($help) {
    echo "Usage:\n";
    echo "  php scripts/harden-product-identity-fks.php [--apply]\n";
    echo "\n";
    echo "Default mode is dry-run. Use --apply to execute ALTER TABLE.\n";
    exit(0);
}

/**
 * @return array{data_type:string,unsigned:bool}|null
 */
function columnType(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare(
        "SELECT DATA_TYPE, COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column
         LIMIT 1"
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

function foreignKeyExists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND CONSTRAINT_NAME = :constraint
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'
         LIMIT 1"
    );
    $stmt->execute([
        ':table' => $table,
        ':constraint' => $constraint,
    ]);
    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND INDEX_NAME = :index
         LIMIT 1"
    );
    $stmt->execute([
        ':table' => $table,
        ':index' => $index,
    ]);
    return (bool) $stmt->fetchColumn();
}

function countOrphans(PDO $pdo, string $table, string $column): int
{
    $safeTable = preg_replace('/[^a-z0-9_]+/i', '', $table);
    $safeColumn = preg_replace('/[^a-z0-9_]+/i', '', $column);
    if ($safeTable === null || $safeTable === '' || $safeColumn === null || $safeColumn === '') {
        return 1;
    }

    $sql = "SELECT COUNT(*)
            FROM {$safeTable} t
            WHERE t.{$safeColumn} IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.{$safeColumn})";
    return (int) $pdo->query($sql)->fetchColumn();
}

$typeAdjustments = [
    [
        'table' => 'consignacao_recebimento_produtos',
        'column' => 'product_id',
        'ddl' => 'ALTER TABLE consignacao_recebimento_produtos
                  MODIFY COLUMN product_id BIGINT UNSIGNED NOT NULL',
    ],
    [
        'table' => 'sacolinha_itens',
        'column' => 'product_id',
        'ddl' => 'ALTER TABLE sacolinha_itens
                  MODIFY COLUMN product_id BIGINT UNSIGNED NULL',
    ],
];

$indexAdjustments = [
    [
        'table' => 'sacolinha_itens',
        'index' => 'idx_sacolinha_itens_product',
        'ddl' => 'ALTER TABLE sacolinha_itens
                  ADD INDEX idx_sacolinha_itens_product (product_id)',
    ],
];

$targets = [
    [
        'table' => 'consignment_product_registry',
        'column' => 'product_id',
        'constraint' => 'fk_consign_registry_product_sku',
        'ddl' => 'ALTER TABLE consignment_product_registry
                  ADD CONSTRAINT fk_consign_registry_product_sku
                  FOREIGN KEY (product_id)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'consignment_sales',
        'column' => 'product_id',
        'constraint' => 'fk_consign_sales_product_sku',
        'ddl' => 'ALTER TABLE consignment_sales
                  ADD CONSTRAINT fk_consign_sales_product_sku
                  FOREIGN KEY (product_id)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'consignment_payout_items',
        'column' => 'product_id',
        'constraint' => 'fk_consign_payout_item_product_sku',
        'ddl' => 'ALTER TABLE consignment_payout_items
                  ADD CONSTRAINT fk_consign_payout_item_product_sku
                  FOREIGN KEY (product_id)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'cupons_creditos_movimentos',
        'column' => 'product_id',
        'constraint' => 'fk_cupons_mov_product_sku',
        'ddl' => 'ALTER TABLE cupons_creditos_movimentos
                  ADD CONSTRAINT fk_cupons_mov_product_sku
                  FOREIGN KEY (product_id)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'produto_baixas',
        'column' => 'product_id',
        'constraint' => 'fk_produto_baixas_product_sku',
        'ddl' => 'ALTER TABLE produto_baixas
                  ADD CONSTRAINT fk_produto_baixas_product_sku
                  FOREIGN KEY (product_id)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'consignacao_recebimento_produtos',
        'column' => 'product_id',
        'constraint' => 'fk_consig_receb_prod_product_sku',
        'ddl' => 'ALTER TABLE consignacao_recebimento_produtos
                  ADD CONSTRAINT fk_consig_receb_prod_product_sku
                  FOREIGN KEY (product_id)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'consignment_items',
        'column' => 'product_sku',
        'constraint' => 'fk_consign_items_product_sku',
        'ddl' => 'ALTER TABLE consignment_items
                  ADD CONSTRAINT fk_consign_items_product_sku
                  FOREIGN KEY (product_sku)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'order_return_items',
        'column' => 'product_sku',
        'constraint' => 'fk_order_return_items_product_sku',
        'ddl' => 'ALTER TABLE order_return_items
                  ADD CONSTRAINT fk_order_return_items_product_sku
                  FOREIGN KEY (product_sku)
                  REFERENCES products(sku)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE',
    ],
    [
        'table' => 'sacolinha_itens',
        'column' => 'product_id',
        'constraint' => 'fk_sacolinha_itens_product_sku',
        'ddl' => 'ALTER TABLE sacolinha_itens
                  ADD CONSTRAINT fk_sacolinha_itens_product_sku
                  FOREIGN KEY (product_id)
                  REFERENCES products(sku)
                  ON DELETE SET NULL
                  ON UPDATE CASCADE',
    ],
];

echo 'MODE=' . ($apply ? 'apply' : 'dry-run') . PHP_EOL;

foreach ($typeAdjustments as $adjustment) {
    $table = $adjustment['table'];
    $column = $adjustment['column'];
    $ddl = $adjustment['ddl'];

    $fromType = columnType($pdo, $table, $column);
    if ($fromType === null) {
        echo "TYPE.{$table}.{$column}=blocked_missing_column" . PHP_EOL;
        continue;
    }

    $isBigintUnsigned = $fromType['data_type'] === 'bigint' && $fromType['unsigned'] === true;
    if ($isBigintUnsigned) {
        echo "TYPE.{$table}.{$column}=ok" . PHP_EOL;
        continue;
    }

    if (!$apply) {
        echo "TYPE.{$table}.{$column}=needs_alter" . PHP_EOL;
        continue;
    }

    try {
        $pdo->exec($ddl);
        echo "TYPE.{$table}.{$column}=altered" . PHP_EOL;
    } catch (Throwable $e) {
        echo "TYPE.{$table}.{$column}=error:" . $e->getMessage() . PHP_EOL;
    }
}

foreach ($indexAdjustments as $adjustment) {
    $table = $adjustment['table'];
    $index = $adjustment['index'];
    $ddl = $adjustment['ddl'];

    if (indexExists($pdo, $table, $index)) {
        echo "INDEX.{$table}.{$index}=ok" . PHP_EOL;
        continue;
    }

    if (!$apply) {
        echo "INDEX.{$table}.{$index}=needs_create" . PHP_EOL;
        continue;
    }

    try {
        $pdo->exec($ddl);
        echo "INDEX.{$table}.{$index}=created" . PHP_EOL;
    } catch (Throwable $e) {
        echo "INDEX.{$table}.{$index}=error:" . $e->getMessage() . PHP_EOL;
    }
}

foreach ($targets as $target) {
    $table = $target['table'];
    $column = $target['column'];
    $constraint = $target['constraint'];
    $ddl = $target['ddl'];

    if (foreignKeyExists($pdo, $table, $constraint)) {
        echo "FK.{$constraint}=already_exists" . PHP_EOL;
        continue;
    }

    $fromType = columnType($pdo, $table, $column);
    $toType = columnType($pdo, 'products', 'sku');
    if ($fromType === null || $toType === null) {
        echo "FK.{$constraint}=blocked_missing_column" . PHP_EOL;
        continue;
    }

    if (
        $fromType['data_type'] !== $toType['data_type']
        || $fromType['unsigned'] !== $toType['unsigned']
    ) {
        echo "FK.{$constraint}=blocked_incompatible_type" . PHP_EOL;
        continue;
    }

    $orphans = countOrphans($pdo, $table, $column);
    if ($orphans > 0) {
        echo "FK.{$constraint}=blocked_orphans:{$orphans}" . PHP_EOL;
        continue;
    }

    if (!$apply) {
        echo "FK.{$constraint}=ready" . PHP_EOL;
        continue;
    }

    try {
        $pdo->exec($ddl);
        echo "FK.{$constraint}=added" . PHP_EOL;
    } catch (Throwable $e) {
        echo "FK.{$constraint}=error:" . $e->getMessage() . PHP_EOL;
    }
}
