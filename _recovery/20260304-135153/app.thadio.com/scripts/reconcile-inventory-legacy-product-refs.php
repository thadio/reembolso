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
    echo "  php scripts/reconcile-inventory-legacy-product-refs.php [--apply]\n";
    echo "\n";
    echo "Default mode is dry-run.\n";
    echo "Use --apply to persist updates idempotently.\n";
    exit(0);
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param callable(array<string, mixed>):bool $updater
 */
function applyRows(array $rows, callable $updater): int
{
    $affected = 0;
    foreach ($rows as $row) {
        if ($updater($row)) {
            $affected++;
        }
    }
    return $affected;
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchRows(PDO $pdo, string $sql): array
{
    $stmt = $pdo->query($sql);
    if (!$stmt) {
        return [];
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function orphanCount(PDO $pdo, string $table): int
{
    $safeTable = preg_replace('/[^a-z0-9_]+/i', '', $table);
    if ($safeTable === null || $safeTable === '') {
        return 1;
    }
    $sql = "SELECT COUNT(*) FROM {$safeTable} t
            WHERE t.product_id IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.product_id)";
    return (int) $pdo->query($sql)->fetchColumn();
}

function fkExists(PDO $pdo, string $table, string $constraint): bool
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

$deterministicItemMap = fetchRows(
    $pdo,
    "SELECT
        i.id,
        i.product_id AS old_product_id,
        i.sku AS legacy_sku,
        p.sku AS new_product_sku,
        i.product_name
     FROM inventario_itens i
     INNER JOIN products p
             ON LOWER(TRIM(p.name)) = LOWER(TRIM(i.product_name))
     WHERE i.product_id IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM products px WHERE px.sku = i.product_id)
       AND (
            SELECT COUNT(*)
            FROM products p2
            WHERE LOWER(TRIM(p2.name)) = LOWER(TRIM(i.product_name))
       ) = 1
     ORDER BY i.id ASC"
);

$tablesForCascade = ['inventario_scans', 'inventario_logs', 'inventario_pendentes'];

$mode = $apply ? 'apply' : 'dry-run';
echo 'MODE=' . $mode . PHP_EOL;
echo 'CANDIDATE.item_name_based=' . count($deterministicItemMap) . PHP_EOL;
echo 'BEFORE.orphans.inventario_itens=' . orphanCount($pdo, 'inventario_itens') . PHP_EOL;
echo 'BEFORE.orphans.inventario_scans=' . orphanCount($pdo, 'inventario_scans') . PHP_EOL;
echo 'BEFORE.orphans.inventario_logs=' . orphanCount($pdo, 'inventario_logs') . PHP_EOL;
echo 'BEFORE.orphans.inventario_pendentes=' . orphanCount($pdo, 'inventario_pendentes') . PHP_EOL;

if (!$apply) {
    echo 'STATUS=DRY_RUN_ONLY' . PHP_EOL;
    exit(0);
}

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS product_legacy_ids (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          sku BIGINT UNSIGNED NULL,
          legacy_id VARCHAR(190) NOT NULL,
          origem VARCHAR(120) NOT NULL,
          data_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_product_legacy_sku (sku),
          INDEX idx_product_legacy_origem (origem),
          INDEX idx_product_legacy_legacy_id (legacy_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $itemUpdate = $pdo->prepare(
        "UPDATE inventario_itens
         SET product_id = :new_product_id
         WHERE id = :id
           AND product_id = :old_product_id"
    );

    $tableUpdates = [];
    foreach ($tablesForCascade as $table) {
        $tableUpdates[$table] = $pdo->prepare(
            "UPDATE {$table}
             SET product_id = :new_product_id
             WHERE product_id = :old_product_id
               AND sku = :legacy_sku"
        );
    }

    $legacyInsert = $pdo->prepare(
        "INSERT INTO product_legacy_ids (sku, legacy_id, origem, data_registro)
         SELECT :sku, :legacy_id, :origem, NOW()
         FROM DUAL
         WHERE NOT EXISTS (
           SELECT 1
           FROM product_legacy_ids x
           WHERE COALESCE(x.sku, 0) = COALESCE(:sku_check, 0)
             AND x.legacy_id = :legacy_id_check
             AND x.origem = :origem_check
         )"
    );

    $updatedItems = applyRows(
        $deterministicItemMap,
        static function (array $row) use ($itemUpdate, $legacyInsert): bool {
            $legacyInsert->execute([
                ':sku' => (int) $row['new_product_sku'],
                ':legacy_id' => (string) $row['old_product_id'],
                ':origem' => 'inventario_itens.product_id',
                ':sku_check' => (int) $row['new_product_sku'],
                ':legacy_id_check' => (string) $row['old_product_id'],
                ':origem_check' => 'inventario_itens.product_id',
            ]);
            $legacyInsert->execute([
                ':sku' => (int) $row['new_product_sku'],
                ':legacy_id' => (string) $row['legacy_sku'],
                ':origem' => 'inventario_itens.sku',
                ':sku_check' => (int) $row['new_product_sku'],
                ':legacy_id_check' => (string) $row['legacy_sku'],
                ':origem_check' => 'inventario_itens.sku',
            ]);

            $itemUpdate->execute([
                ':id' => (int) $row['id'],
                ':old_product_id' => (int) $row['old_product_id'],
                ':new_product_id' => (int) $row['new_product_sku'],
            ]);
            return $itemUpdate->rowCount() > 0;
        }
    );

    $updatedCascade = [];
    foreach ($tablesForCascade as $table) {
        $stmt = $tableUpdates[$table];
        $updatedCascade[$table] = applyRows(
            $deterministicItemMap,
            static function (array $row) use ($stmt, $legacyInsert, $table): bool {
                $legacyInsert->execute([
                    ':sku' => (int) $row['new_product_sku'],
                    ':legacy_id' => (string) $row['old_product_id'],
                    ':origem' => $table . '.product_id',
                    ':sku_check' => (int) $row['new_product_sku'],
                    ':legacy_id_check' => (string) $row['old_product_id'],
                    ':origem_check' => $table . '.product_id',
                ]);
                $legacyInsert->execute([
                    ':sku' => (int) $row['new_product_sku'],
                    ':legacy_id' => (string) $row['legacy_sku'],
                    ':origem' => $table . '.sku',
                    ':sku_check' => (int) $row['new_product_sku'],
                    ':legacy_id_check' => (string) $row['legacy_sku'],
                    ':origem_check' => $table . '.sku',
                ]);

                $stmt->execute([
                    ':old_product_id' => (int) $row['old_product_id'],
                    ':new_product_id' => (int) $row['new_product_sku'],
                    ':legacy_sku' => (string) $row['legacy_sku'],
                ]);
                return $stmt->rowCount() > 0;
            }
        );
    }

    // Primeiro, liberar nulabilidade para saneamento de órfãos históricos.
    $pdo->exec("ALTER TABLE inventario_scans MODIFY COLUMN product_id BIGINT UNSIGNED NULL");
    $pdo->exec("ALTER TABLE inventario_logs MODIFY COLUMN product_id BIGINT UNSIGNED NULL");
    $pdo->exec("ALTER TABLE inventario_pendentes MODIFY COLUMN product_id BIGINT UNSIGNED NULL");

    foreach (['inventario_logs', 'inventario_pendentes', 'inventario_scans'] as $table) {
        $safeTable = preg_replace('/[^a-z0-9_]+/i', '', $table);
        if ($safeTable === null || $safeTable === '') {
            continue;
        }

        $rows = fetchRows(
            $pdo,
            "SELECT id, product_id, sku
             FROM {$safeTable} t
             WHERE t.product_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.product_id)"
        );

        foreach ($rows as $row) {
            $legacyInsert->execute([
                ':sku' => null,
                ':legacy_id' => (string) ($row['product_id'] ?? ''),
                ':origem' => $table . '.unresolved_product_id',
                ':sku_check' => null,
                ':legacy_id_check' => (string) ($row['product_id'] ?? ''),
                ':origem_check' => $table . '.unresolved_product_id',
            ]);
            $legacySku = trim((string) ($row['sku'] ?? ''));
            if ($legacySku !== '') {
                $legacyInsert->execute([
                    ':sku' => null,
                    ':legacy_id' => $legacySku,
                    ':origem' => $table . '.unresolved_sku_text',
                    ':sku_check' => null,
                    ':legacy_id_check' => $legacySku,
                    ':origem_check' => $table . '.unresolved_sku_text',
                ]);
            }
        }

        $pdo->exec(
            "UPDATE {$safeTable} t
             SET t.product_id = NULL
             WHERE t.product_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = t.product_id)"
        );
    }

    // Hardening: tipos compatíveis com products.sku
    $pdo->exec("ALTER TABLE inventario_itens MODIFY COLUMN product_id BIGINT UNSIGNED NOT NULL");

    // FKs (idempotente)
    if (!fkExists($pdo, 'inventario_itens', 'fk_inventario_itens_product_sku')) {
        $pdo->exec(
            "ALTER TABLE inventario_itens
             ADD CONSTRAINT fk_inventario_itens_product_sku
             FOREIGN KEY (product_id) REFERENCES products(sku)
             ON DELETE RESTRICT ON UPDATE CASCADE"
        );
    }
    if (!fkExists($pdo, 'inventario_scans', 'fk_inventario_scans_product_sku')) {
        $pdo->exec(
            "ALTER TABLE inventario_scans
             ADD CONSTRAINT fk_inventario_scans_product_sku
             FOREIGN KEY (product_id) REFERENCES products(sku)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );
    }
    if (!fkExists($pdo, 'inventario_logs', 'fk_inventario_logs_product_sku')) {
        $pdo->exec(
            "ALTER TABLE inventario_logs
             ADD CONSTRAINT fk_inventario_logs_product_sku
             FOREIGN KEY (product_id) REFERENCES products(sku)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );
    }
    if (!fkExists($pdo, 'inventario_pendentes', 'fk_inventario_pendentes_product_sku')) {
        $pdo->exec(
            "ALTER TABLE inventario_pendentes
             ADD CONSTRAINT fk_inventario_pendentes_product_sku
             FOREIGN KEY (product_id) REFERENCES products(sku)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );
    }

    echo 'UPDATED.inventario_itens=' . $updatedItems . PHP_EOL;
    foreach ($updatedCascade as $table => $count) {
        echo 'UPDATED.' . $table . '=' . $count . PHP_EOL;
    }
    echo 'AFTER.orphans.inventario_itens=' . orphanCount($pdo, 'inventario_itens') . PHP_EOL;
    echo 'AFTER.orphans.inventario_scans=' . orphanCount($pdo, 'inventario_scans') . PHP_EOL;
    echo 'AFTER.orphans.inventario_logs=' . orphanCount($pdo, 'inventario_logs') . PHP_EOL;
    echo 'AFTER.orphans.inventario_pendentes=' . orphanCount($pdo, 'inventario_pendentes') . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERR:apply_failed=' . $e->getMessage() . PHP_EOL);
    exit(1);
}
