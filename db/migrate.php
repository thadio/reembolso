<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
$db = $app->db();

$db->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(190) NOT NULL,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_migrations_name (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);

$files = glob(__DIR__ . '/migrations/*.sql');
if ($files === false) {
    fwrite(STDERR, "Falha ao listar migrations.\n");
    exit(1);
}

sort($files);

$selectStmt = $db->prepare('SELECT 1 FROM migrations WHERE migration = :migration LIMIT 1');
$insertStmt = $db->prepare('INSERT INTO migrations (migration, executed_at) VALUES (:migration, NOW())');

foreach ($files as $file) {
    $migration = basename($file);

    $selectStmt->execute(['migration' => $migration]);
    if ($selectStmt->fetch() !== false) {
        echo "[skip] {$migration}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Não foi possível ler {$migration}.\n");
        exit(1);
    }

    try {
        $db->exec($sql);
        $insertStmt->execute(['migration' => $migration]);
        echo "[ok]   {$migration}\n";
    } catch (Throwable $throwable) {
        fwrite(STDERR, "[erro] {$migration}: {$throwable->getMessage()}\n");
        exit(1);
    }
}

echo "Migrations concluídas.\n";
