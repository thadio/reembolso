<?php

namespace App\Core;

use App\Services\SchemaBootstrapService;
use PDO;
use Throwable;

/**
 * Valida requisitos mínimos de schema para runtime HTTP.
 *
 * Não executa DDL em requests normais; apenas falha cedo quando a base
 * está inconsistente para evitar erros tardios durante os fluxos.
 */
final class SchemaGuard
{
    /**
     * @var array<string, array<int, string>>
     */
    private const REQUIRED_COLUMNS = [
        'usuarios' => ['id', 'email', 'password_hash', 'profile_id'],
        'perfis' => ['id', 'name', 'permissions'],
        'pessoas' => ['id', 'full_name', 'status'],
        'products' => ['sku', 'name', 'quantity', 'status'],
        'orders' => ['id', 'pessoa_id', 'status', 'total'],
        'order_items' => ['id', 'order_id', 'product_sku', 'quantity', 'price', 'total'],
        'order_returns' => ['id', 'order_id', 'status', 'refund_status'],
        'order_return_items' => ['id', 'return_id', 'product_sku', 'quantity'],
        'inventory_movements' => ['id', 'product_sku', 'movement_type', 'quantity_before', 'quantity_after'],
        'bancos' => ['id', 'name', 'status'],
        'contas_bancarias' => ['id', 'bank_id', 'status'],
        'metodos_pagamento' => ['id', 'name', 'status'],
        'terminais_pagamento' => ['id', 'name', 'status'],
        'tipos_entrega' => ['id', 'name', 'status'],
        'canais_venda' => ['id', 'name', 'status'],
        'cupons_creditos' => ['id', 'pessoa_id', 'status', 'balance'],
        'cupons_creditos_identificacoes' => ['id', 'label', 'status'],
        'finance_entries' => ['id', 'entry_type', 'pessoa_id', 'amount', 'status'],
        'dashboard_layouts' => ['user_id', 'layout'],
        'dash_refresh_log' => ['id', 'refresh_type', 'status', 'created_at'],
        'dash_sales_daily' => ['id', 'date', 'revenue', 'orders_count'],
        'dash_stock_snapshot' => ['id', 'snapshot_date', 'total_units'],
        'audit_log' => ['id', 'action', 'table_name', 'created_at'],
        'consignment_report_views' => ['id', 'name', 'fields_config', 'detail_level', 'is_default'],
    ];

    public static function validate(PDO $pdo): ?string
    {
        static $checked = false;
        static $cachedError = null;

        if ($checked) {
            return $cachedError;
        }
        $checked = true;

        try {
            $requiredTables = self::requiredTables();
            $requiredViews = self::requiredViews();
            $existingTables = self::fetchExistingTables($pdo, $requiredTables);

            $missingTables = [];
            foreach ($requiredTables as $table) {
                if (!isset($existingTables[$table])) {
                    $missingTables[] = $table;
                }
            }

            $missingColumns = [];
            $columnTables = array_keys(self::REQUIRED_COLUMNS);
            $existingColumnTables = array_values(array_intersect($columnTables, array_keys($existingTables)));
            if (!empty($existingColumnTables)) {
                $existingColumns = self::fetchExistingColumns($pdo, $existingColumnTables);
                foreach (self::REQUIRED_COLUMNS as $table => $columns) {
                    if (!isset($existingTables[$table])) {
                        continue;
                    }
                    $present = $existingColumns[$table] ?? [];
                    $missing = [];
                    foreach ($columns as $column) {
                        if (!isset($present[$column])) {
                            $missing[] = $column;
                        }
                    }
                    if (!empty($missing)) {
                        $missingColumns[$table] = $missing;
                    }
                }
            }

            $missingViews = [];
            if (!empty($requiredViews)) {
                $existingViews = self::fetchExistingViews($pdo, $requiredViews);
                foreach ($requiredViews as $view) {
                    if (!isset($existingViews[$view])) {
                        $missingViews[] = $view;
                    }
                }
            }

            if (empty($missingTables) && empty($missingColumns) && empty($missingViews)) {
                $cachedError = null;
                return null;
            }

            $issues = [];
            foreach ($missingTables as $table) {
                $issues[] = "tabela '{$table}' ausente";
            }
            foreach ($missingColumns as $table => $columns) {
                $issues[] = "colunas ausentes em '{$table}': " . implode(', ', $columns);
            }
            foreach ($missingViews as $view) {
                $issues[] = "view '{$view}' ausente";
            }

            $preview = implode('; ', array_slice($issues, 0, 6));
            if (count($issues) > 6) {
                $preview .= '; ...';
            }

            $cachedError =
                "Schema não está pronto para execução HTTP ({$preview}). " .
                "Execute: php scripts/bootstrap-db.php";
        } catch (Throwable $e) {
            $cachedError = 'Falha ao validar schema da aplicação: ' . $e->getMessage()
                . '. Execute: php scripts/bootstrap-db.php';
        }

        return $cachedError;
    }

    /**
     * @return array<int, string>
     */
    private static function requiredTables(): array
    {
        $tables = array_merge(array_keys(self::REQUIRED_COLUMNS), SchemaBootstrapService::expectedTables());
        $tables = array_values(array_unique(array_filter($tables, static fn($table): bool => is_string($table) && $table !== '')));
        sort($tables);
        return $tables;
    }

    /**
     * @return array<int, string>
     */
    private static function requiredViews(): array
    {
        $views = SchemaBootstrapService::expectedViews();
        $views = array_values(array_unique(array_filter($views, static fn($view): bool => is_string($view) && $view !== '')));
        sort($views);
        return $views;
    }

    /**
     * @param array<int, string> $tables
     * @return array<string, true>
     */
    private static function fetchExistingTables(PDO $pdo, array $tables): array
    {
        if (empty($tables)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $sql = "SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($tables);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $result[(string) $table] = true;
        }
        return $result;
    }

    /**
     * @param array<int, string> $tables
     * @return array<string, array<string, true>>
     */
    private static function fetchExistingColumns(PDO $pdo, array $tables): array
    {
        if (empty($tables)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $sql = "SELECT TABLE_NAME, COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($tables);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $table = (string) ($row['TABLE_NAME'] ?? '');
            $column = (string) ($row['COLUMN_NAME'] ?? '');
            if ($table === '' || $column === '') {
                continue;
            }
            if (!isset($result[$table])) {
                $result[$table] = [];
            }
            $result[$table][$column] = true;
        }

        return $result;
    }

    /**
     * @param array<int, string> $views
     * @return array<string, true>
     */
    private static function fetchExistingViews(PDO $pdo, array $views): array
    {
        if (empty($views)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($views), '?'));
        $sql = "SELECT TABLE_NAME
                FROM information_schema.VIEWS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($views);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $view) {
            $result[(string) $view] = true;
        }

        return $result;
    }
}
