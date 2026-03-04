<?php

namespace App\Services;

use PDO;

class TableMigrationUploadService
{
    private PDO $pdo;
    private string $uploadsDir;
    private int $maxUploadBytes;

    /**
     * @var array<string, int>|null
     */
    private ?array $cachedPlanIndex = null;

    public function __construct(PDO $pdo, ?string $uploadsDir = null, ?int $maxUploadBytes = null)
    {
        $this->pdo = $pdo;
        $this->uploadsDir = $uploadsDir ?: dirname(__DIR__, 2) . '/uploads/migration-imports';
        $maxMb = (int) (getenv('MIGRATION_UPLOAD_MAX_MB') ?: 50);
        $maxMb = max(1, min(500, $maxMb));
        $this->maxUploadBytes = $maxUploadBytes ?: ($maxMb * 1024 * 1024);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildExecutionPlan(): array
    {
        $tables = $this->listManagedTables();
        if (empty($tables)) {
            return [];
        }

        $fkGraph = $this->loadForeignKeyGraph($tables);
        $ordered = $this->topologicalSort($tables, $fkGraph['parents'], $fkGraph['children']);

        $plan = [];
        foreach ($ordered as $index => $tableName) {
            $parents = $fkGraph['parents'][$tableName] ?? [];
            sort($parents);
            $plan[] = [
                'position' => $index + 1,
                'table' => $tableName,
                'depends_on' => array_values($parents),
                'rows_estimate' => $this->estimateTableRows($tableName),
            ];
        }

        $planIndex = [];
        foreach ($plan as $row) {
            $planIndex[(string) $row['table']] = (int) $row['position'];
        }
        $this->cachedPlanIndex = $planIndex;

        return $plan;
    }

    /**
     * @return array{content: string, mime: string, file_name: string}
     */
    public function buildTemplateDownload(string $tableName, string $format): array
    {
        $normalizedFormat = strtolower(trim($format));
        if (!in_array($normalizedFormat, ['csv', 'json'], true)) {
            throw new \InvalidArgumentException('Formato de modelo inválido. Use csv ou json.');
        }

        $resolvedTable = $this->resolveManagedTableName($tableName);
        if ($resolvedTable === null) {
            throw new \InvalidArgumentException('Tabela inválida para geração de modelo: ' . $tableName);
        }

        $plan = $this->buildExecutionPlan();
        $planByTable = [];
        foreach ($plan as $item) {
            $name = (string) ($item['table'] ?? '');
            if ($name === '') {
                continue;
            }
            $planByTable[$name] = $item;
        }
        $planInfo = $planByTable[$resolvedTable] ?? ['position' => null, 'depends_on' => []];

        $metadata = $this->loadTableTemplateMetadata($resolvedTable);
        $generatedAt = date('Y-m-d H:i:s');
        $safeTableName = preg_replace('/[^a-zA-Z0-9_]+/', '_', $resolvedTable) ?: 'tabela';
        $downloadName = 'modelo-' . $safeTableName . '-detalhado.' . $normalizedFormat;

        if ($normalizedFormat === 'csv') {
            $rows = $this->buildCsvTemplateRows($metadata['columns']);
            $content = $this->renderCsv($rows, ';');

            return [
                'content' => $content,
                'mime' => 'text/csv; charset=utf-8',
                'file_name' => $downloadName,
            ];
        }

        $exampleRow = [];
        foreach ($metadata['columns'] as $columnMeta) {
            $columnName = (string) ($columnMeta['column_name'] ?? '');
            if ($columnName === '') {
                continue;
            }
            $exampleRow[$columnName] = $columnMeta['example_value'] ?? null;
        }

        $payload = [
            'table' => $resolvedTable,
            'generated_at' => $generatedAt,
            'instructions' => [
                'Formato aceito no upload JSON: array de objetos, com chaves iguais aos nomes das colunas.',
                'Este arquivo é um modelo detalhado. Para importar, use somente o bloco upload_rows_example.',
                'Use null para valores opcionais e remova campos auto_increment quando não quiser forçar IDs.',
            ],
            'execution_plan' => [
                'position' => (int) ($planInfo['position'] ?? 0),
                'depends_on' => array_values(array_map('strval', $planInfo['depends_on'] ?? [])),
            ],
            'columns' => array_values($metadata['columns']),
            'upload_rows_example' => [$exampleRow],
        ];

        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($content)) {
            throw new \RuntimeException('Falha ao gerar modelo JSON.');
        }

        return [
            'content' => $content . "\n",
            'mime' => 'application/json; charset=utf-8',
            'file_name' => $downloadName,
        ];
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function importUploadedTables(array $files, array $options = []): array
    {
        $planIndex = $this->cachedPlanIndex;
        if ($planIndex === null) {
            $plan = $this->buildExecutionPlan();
            $planIndex = [];
            foreach ($plan as $row) {
                $planIndex[(string) $row['table']] = (int) $row['position'];
            }
        }

        $continueOnError = !empty($options['continue_on_error']);
        $csvMode = (string) ($options['csv_mode'] ?? 'upsert');
        if (!in_array($csvMode, ['insert', 'upsert'], true)) {
            $csvMode = 'upsert';
        }

        $batchId = date('Ymd_His');
        $uploaded = $this->normalizeUploadedFiles($files);
        if (empty($uploaded)) {
            return [];
        }

        usort($uploaded, function (array $a, array $b) use ($planIndex): int {
            $aTable = (string) $a['table'];
            $bTable = (string) $b['table'];
            $aPos = $planIndex[$aTable] ?? PHP_INT_MAX;
            $bPos = $planIndex[$bTable] ?? PHP_INT_MAX;
            if ($aPos === $bPos) {
                return strcmp($aTable, $bTable);
            }
            return $aPos <=> $bPos;
        });

        $results = [];
        foreach ($uploaded as $file) {
            $tableName = (string) $file['table'];
            $originalName = (string) $file['name'];
            $tmpName = (string) $file['tmp_name'];
            $size = (int) $file['size'];
            $error = (int) $file['error'];

            $result = [
                'table' => $tableName,
                'file_name' => $originalName,
                'mode' => 'n/a',
                'status' => 'error',
                'message' => '',
                'affected_rows' => 0,
                'stored_path' => '',
            ];

            try {
                $this->assertValidUpload($tableName, $originalName, $size, $error, $planIndex);
                $storedPath = $this->moveUploadToStorage($batchId, $tableName, $originalName, $tmpName);
                $result['stored_path'] = $storedPath;

                $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                if ($extension === 'sql') {
                    $result['mode'] = 'sql';
                    $result['affected_rows'] = $this->executeSqlFile($tableName, $storedPath);
                } elseif ($extension === 'csv') {
                    $result['mode'] = 'csv_' . $csvMode;
                    $result['affected_rows'] = $this->executeCsvFile($tableName, $storedPath, $csvMode);
                } elseif ($extension === 'json') {
                    $result['mode'] = 'json_' . $csvMode;
                    $result['affected_rows'] = $this->executeJsonFile($tableName, $storedPath, $csvMode);
                } else {
                    throw new \RuntimeException('Formato não suportado: .' . $extension);
                }

                $result['status'] = 'success';
                $result['message'] = 'Importação concluída.';
            } catch (\Throwable $exception) {
                $result['status'] = 'error';
                $result['message'] = $exception->getMessage();
                if (!$continueOnError) {
                    $results[] = $result;
                    break;
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    private function listManagedTables(): array
    {
        $tables = $this->listDatabaseTables();
        if (empty($tables)) {
            $tables = $this->listRepositoryTables();
        }

        $filtered = [];
        foreach ($tables as $tableName) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                continue;
            }
            if (preg_match('/backup/i', $tableName)) {
                continue;
            }
            $filtered[] = $tableName;
        }

        $filtered = array_values(array_unique($filtered));
        sort($filtered);

        return $filtered;
    }

    private function resolveManagedTableName(string $tableName): ?string
    {
        $normalized = trim($tableName);
        if ($normalized === '') {
            return null;
        }

        $tableMap = [];
        foreach ($this->listManagedTables() as $managedTable) {
            $tableMap[strtolower($managedTable)] = $managedTable;
        }

        return $tableMap[strtolower($normalized)] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function listDatabaseTables(): array
    {
        $statement = $this->pdo->query(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_TYPE = 'BASE TABLE'"
        );

        if (!$statement) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn($value): string => (string) $value, $rows);
    }

    /**
     * @return array<int, string>
     */
    private function listRepositoryTables(): array
    {
        $dir = dirname(__DIR__) . '/Repositories';
        $files = glob($dir . '/*.php');
        if (!is_array($files)) {
            return [];
        }

        $tables = [];
        foreach ($files as $filePath) {
            $contents = file_get_contents($filePath);
            if (!is_string($contents) || $contents === '') {
                continue;
            }

            if (preg_match_all('/CREATE TABLE IF NOT EXISTS\\s+`?([a-zA-Z0-9_]+)`?/i', $contents, $matches)) {
                foreach ($matches[1] as $tableName) {
                    $tables[] = strtolower((string) $tableName);
                }
            }
        }

        return $tables;
    }

    /**
     * @param array<int, string> $tables
     * @return array{parents: array<string, array<int, string>>, children: array<string, array<int, string>>}
     */
    private function loadForeignKeyGraph(array $tables): array
    {
        $tableSet = array_fill_keys($tables, true);
        $parents = [];
        $children = [];

        foreach ($tables as $tableName) {
            $parents[$tableName] = [];
            $children[$tableName] = [];
        }

        $statement = $this->pdo->query(
            "SELECT TABLE_NAME AS child_table,
                    REFERENCED_TABLE_NAME AS parent_table
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        if (!$statement) {
            return ['parents' => $parents, 'children' => $children];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return ['parents' => $parents, 'children' => $children];
        }

        foreach ($rows as $row) {
            $child = (string) ($row['child_table'] ?? '');
            $parent = (string) ($row['parent_table'] ?? '');
            if ($child === '' || $parent === '' || $child === $parent) {
                continue;
            }
            if (!isset($tableSet[$child]) || !isset($tableSet[$parent])) {
                continue;
            }

            $parents[$child][] = $parent;
            $children[$parent][] = $child;
        }

        foreach ($parents as $tableName => $dependencies) {
            $parents[$tableName] = array_values(array_unique($dependencies));
        }
        foreach ($children as $tableName => $dependents) {
            $children[$tableName] = array_values(array_unique($dependents));
        }

        return ['parents' => $parents, 'children' => $children];
    }

    /**
     * @param array<int, string> $tables
     * @param array<string, array<int, string>> $parents
     * @param array<string, array<int, string>> $children
     * @return array<int, string>
     */
    private function topologicalSort(array $tables, array $parents, array $children): array
    {
        $inDegree = [];
        foreach ($tables as $tableName) {
            $inDegree[$tableName] = count($parents[$tableName] ?? []);
        }

        $queue = [];
        foreach ($inDegree as $tableName => $count) {
            if ($count === 0) {
                $queue[] = $tableName;
            }
        }

        usort($queue, fn(string $a, string $b): int => $this->tableWeight($a) <=> $this->tableWeight($b) ?: strcmp($a, $b));
        $sorted = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current === null) {
                break;
            }
            $sorted[] = $current;

            foreach ($children[$current] ?? [] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }

            usort($queue, fn(string $a, string $b): int => $this->tableWeight($a) <=> $this->tableWeight($b) ?: strcmp($a, $b));
        }

        if (count($sorted) !== count($tables)) {
            $remaining = array_values(array_diff($tables, $sorted));
            usort($remaining, fn(string $a, string $b): int => $this->tableWeight($a) <=> $this->tableWeight($b) ?: strcmp($a, $b));
            $sorted = array_merge($sorted, $remaining);
        }

        return $sorted;
    }

    private function tableWeight(string $tableName): int
    {
        static $weights = [
            'perfis' => 10,
            'regras' => 11,
            'regras_versoes' => 12,
            'pessoas' => 20,
            'pessoas_papeis' => 21,
            'usuarios' => 22,
            'bancos' => 30,
            'contas_bancarias' => 31,
            'metodos_pagamento' => 32,
            'terminais_pagamento' => 33,
            'tipos_entrega' => 34,
            'canais_venda' => 35,
            'carriers' => 36,
            'financeiro_categorias' => 37,
            'catalog_brands' => 40,
            'catalog_categories' => 41,
            'brands' => 42,
            'product_categories' => 43,
            'colecoes' => 44,
            'products' => 45,
            'datas_comemorativas' => 46,
            'sku_reservations' => 47,
            'produto_lotes' => 48,
            'inventory_items' => 49,
            'orders' => 55,
            'order_addresses' => 56,
            'order_items' => 57,
            'order_payments' => 58,
            'order_shipments' => 59,
            'shipment_events' => 60,
            'order_returns' => 61,
            'order_return_items' => 62,
            'consignacao_recebimentos' => 65,
            'consignacao_recebimento_itens' => 66,
            'consignacao_recebimento_produtos' => 67,
            'consignacao_devolucoes' => 68,
            'consignacao_devolucao_itens' => 69,
            'consignments' => 70,
            'consignment_items' => 71,
            'consignacao_creditos' => 72,
            'cupons_creditos_identificacoes' => 75,
            'cupons_creditos' => 76,
            'cupons_creditos_movimentos' => 77,
            'sacolinhas' => 80,
            'sacolinha_itens' => 81,
            'bag_shipments' => 82,
            'finance_entries' => 85,
            'financeiro_lancamentos' => 86,
            'finance_transactions' => 87,
            'credit_accounts' => 88,
            'credit_entries' => 89,
            'produto_baixas' => 90,
            'cliente_historico' => 91,
            'ponto_registros' => 93,
            'inventarios' => 94,
            'inventario_itens' => 95,
            'inventario_scans' => 96,
            'inventario_logs' => 97,
            'inventario_pendentes' => 98,
            'inventory_movements' => 99,
            'media_files' => 110,
            'media_links' => 111,
            'dashboard_layouts' => 120,
            'dash_sales_daily' => 121,
            'dash_stock_snapshot' => 122,
            'dash_refresh_log' => 123,
            'audit_log' => 124,
        ];

        return $weights[$tableName] ?? 500;
    }

    private function estimateTableRows(string $tableName): int
    {
        $statement = $this->pdo->prepare(
            "SELECT TABLE_ROWS
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1"
        );
        $statement->execute([':table_name' => $tableName]);
        $value = $statement->fetchColumn();

        return (int) ($value ?: 0);
    }

    /**
     * @param array<string, mixed> $files
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUploadedFiles(array $files): array
    {
        $rows = [];

        if (!isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        foreach ($files['name'] as $tableName => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $rows[] = [
                'table' => (string) $tableName,
                'name' => $name,
                'type' => (string) ($files['type'][$tableName] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$tableName] ?? ''),
                'error' => (int) ($files['error'][$tableName] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($files['size'][$tableName] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, int> $planIndex
     */
    private function assertValidUpload(string $tableName, string $originalName, int $size, int $error, array $planIndex): void
    {
        if (!isset($planIndex[$tableName])) {
            throw new \RuntimeException('Tabela não prevista no plano atual: ' . $tableName);
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->translateUploadError($error));
        }

        if ($size <= 0) {
            throw new \RuntimeException('Arquivo vazio.');
        }

        if ($size > $this->maxUploadBytes) {
            throw new \RuntimeException('Arquivo maior que o limite permitido.');
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['sql', 'csv', 'json'], true)) {
            throw new \RuntimeException('Formato inválido. Use .sql, .csv ou .json.');
        }
    }

    private function translateUploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto. Envie novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário indisponível.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão do PHP.',
            default => 'Erro desconhecido no upload.',
        };
    }

    private function moveUploadToStorage(string $batchId, string $tableName, string $originalName, string $tmpName): string
    {
        if (!is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Arquivo temporário inválido.');
        }

        $targetDir = $this->uploadsDir . '/' . $batchId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Não foi possível criar diretório de importação.');
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($originalName)) ?: 'arquivo';
        $targetFile = $targetDir . '/' . $tableName . '__' . date('His') . '__' . $safeName;

        if (!move_uploaded_file($tmpName, $targetFile)) {
            throw new \RuntimeException('Falha ao mover arquivo enviado.');
        }

        if ($extension === 'sql' || $extension === 'csv' || $extension === 'json') {
            return $targetFile;
        }

        throw new \RuntimeException('Formato não suportado.');
    }

    private function executeSqlFile(string $tableName, string $path): int
    {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new \RuntimeException('Falha ao ler arquivo SQL.');
        }

        $contents = $this->stripBom($contents);
        $statements = $this->splitSqlStatements($contents);
        if (empty($statements)) {
            throw new \RuntimeException('Nenhum comando SQL encontrado.');
        }

        $affectedRows = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($statements as $sql) {
                $trimmed = trim($sql);
                if ($trimmed === '') {
                    continue;
                }
                $this->assertSqlStatementAllowed($tableName, $trimmed);
                if (preg_match('/^START\\s+TRANSACTION$/i', $trimmed) || preg_match('/^(COMMIT|ROLLBACK)$/i', $trimmed)) {
                    continue;
                }
                $rows = $this->pdo->exec($trimmed);
                if ($rows !== false) {
                    $affectedRows += (int) $rows;
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $affectedRows;
    }

    /**
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $length = strlen($sql);
        $current = '';

        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $current .= $char;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $index++;
                }
                continue;
            }

            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $index++;
                    continue;
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $index++;
                    continue;
                }
            }

            if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
                $escaped = $index > 0 && $sql[$index - 1] === '\\';
                if (!$escaped) {
                    $inSingleQuote = !$inSingleQuote;
                }
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick) {
                $escaped = $index > 0 && $sql[$index - 1] === '\\';
                if (!$escaped) {
                    $inDoubleQuote = !$inDoubleQuote;
                }
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $tail = trim($current);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    private function assertSqlStatementAllowed(string $tableName, string $sql): void
    {
        $normalizedTable = strtolower($tableName);

        if (preg_match('/^SET\\s+/i', $sql)) {
            return;
        }
        if (preg_match('/^START\\s+TRANSACTION$/i', $sql)) {
            return;
        }
        if (preg_match('/^(COMMIT|ROLLBACK)$/i', $sql)) {
            return;
        }
        if (preg_match('/^TRUNCATE\\s+TABLE\\s+`?([a-zA-Z0-9_]+)`?$/i', $sql, $matches)) {
            $targetTable = strtolower((string) ($matches[1] ?? ''));
            if ($targetTable !== $normalizedTable) {
                throw new \RuntimeException('TRUNCATE permitido apenas para a tabela selecionada.');
            }
            return;
        }
        if (preg_match('/^(INSERT|REPLACE)\\s+INTO\\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
            $targetTable = strtolower((string) ($matches[2] ?? ''));
            if ($targetTable !== $normalizedTable) {
                throw new \RuntimeException('Comando SQL aponta para tabela diferente da selecionada.');
            }
            return;
        }

        throw new \RuntimeException('SQL não permitido neste fluxo. Use INSERT/REPLACE/TRUNCATE da tabela selecionada.');
    }

    private function executeCsvFile(string $tableName, string $path, string $mode): int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Falha ao abrir arquivo CSV.');
        }

        try {
            $firstLine = fgets($handle);
            if (!is_string($firstLine)) {
                throw new \RuntimeException('CSV vazio.');
            }

            $delimiter = $this->detectCsvDelimiter($firstLine);
            rewind($handle);

            $header = fgetcsv($handle, 0, $delimiter);
            if (!is_array($header) || empty($header)) {
                throw new \RuntimeException('Cabeçalho CSV inválido.');
            }

            $header = array_map(fn($value): string => trim((string) $value), $header);
            $header[0] = $this->stripBom($header[0]);

            $this->validateColumnsForTable($tableName, $header);
            $statement = $this->buildInsertStatement($tableName, $header, $mode);

            $inserted = 0;
            $this->pdo->beginTransaction();
            try {
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if ($row === [null] || $row === []) {
                        continue;
                    }
                    $payload = $this->buildCsvPayload($header, $row);
                    $statement->execute($payload);
                    $inserted += (int) $statement->rowCount();
                }
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
        } finally {
            fclose($handle);
        }

        return $inserted;
    }

    private function detectCsvDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $counts = [];
        foreach ($candidates as $candidate) {
            $counts[$candidate] = substr_count($line, $candidate);
        }
        arsort($counts);
        $delimiter = array_key_first($counts);
        return is_string($delimiter) ? $delimiter : ',';
    }

    /**
     * @param array<int, string> $header
     * @param array<int, mixed> $row
     * @return array<string, mixed>
     */
    private function buildCsvPayload(array $header, array $row): array
    {
        $payload = [];
        foreach ($header as $index => $column) {
            $value = $row[$index] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }
            $payload[':' . $column] = $value;
        }
        return $payload;
    }

    private function executeJsonFile(string $tableName, string $path, string $mode): int
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            throw new \RuntimeException('Arquivo JSON vazio.');
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('JSON inválido. Esperado array de objetos.');
        }

        $rows = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $rows[] = $entry;
        }

        if (empty($rows)) {
            throw new \RuntimeException('Nenhum registro válido encontrado no JSON.');
        }

        $header = [];
        foreach ($rows as $entry) {
            foreach (array_keys($entry) as $columnName) {
                if (!is_string($columnName) || trim($columnName) === '') {
                    continue;
                }
                $header[$columnName] = true;
            }
        }
        $columns = array_keys($header);
        sort($columns);
        $this->validateColumnsForTable($tableName, $columns);
        $statement = $this->buildInsertStatement($tableName, $columns, $mode);

        $affectedRows = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $payload = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    if (is_string($value)) {
                        $value = trim($value);
                        if ($value === '') {
                            $value = null;
                        }
                    }
                    $payload[':' . $column] = $value;
                }
                $statement->execute($payload);
                $affectedRows += (int) $statement->rowCount();
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $affectedRows;
    }

    /**
     * @param array<int, string> $columns
     */
    private function validateColumnsForTable(string $tableName, array $columns): void
    {
        if (empty($columns)) {
            throw new \RuntimeException('Nenhuma coluna foi detectada no arquivo.');
        }

        $statement = $this->pdo->prepare(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name"
        );
        $statement->execute([':table_name' => $tableName]);
        $dbColumns = $statement->fetchAll(PDO::FETCH_COLUMN);
        $dbColumns = array_map(static fn($value): string => (string) $value, is_array($dbColumns) ? $dbColumns : []);

        if (empty($dbColumns)) {
            throw new \RuntimeException('Tabela não encontrada no banco: ' . $tableName);
        }

        $dbColumnSet = array_fill_keys($dbColumns, true);
        foreach ($columns as $column) {
            if (!isset($dbColumnSet[$column])) {
                throw new \RuntimeException('Coluna inválida para ' . $tableName . ': ' . $column);
            }
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function buildInsertStatement(string $tableName, array $columns, string $mode): \PDOStatement
    {
        $quotedColumns = array_map(fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
        $placeholders = array_map(fn(string $column): string => ':' . $column, $columns);

        $sql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` ('
            . implode(', ', $quotedColumns)
            . ') VALUES ('
            . implode(', ', $placeholders)
            . ')';

        if ($mode === 'upsert') {
            $updates = [];
            foreach ($columns as $column) {
                $escaped = '`' . str_replace('`', '``', $column) . '`';
                $updates[] = $escaped . ' = VALUES(' . $escaped . ')';
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        }

        return $this->pdo->prepare($sql);
    }

    /**
     * @return array{columns: array<int, array<string, mixed>>}
     */
    private function loadTableTemplateMetadata(string $tableName): array
    {
        $columnsStmt = $this->pdo->prepare(
            "SELECT c.ORDINAL_POSITION,
                    c.COLUMN_NAME,
                    c.COLUMN_TYPE,
                    c.DATA_TYPE,
                    c.IS_NULLABLE,
                    c.COLUMN_DEFAULT,
                    c.EXTRA,
                    c.COLUMN_KEY,
                    c.COLUMN_COMMENT
             FROM information_schema.COLUMNS c
             WHERE c.TABLE_SCHEMA = DATABASE()
               AND c.TABLE_NAME = :table_name
             ORDER BY c.ORDINAL_POSITION"
        );
        $columnsStmt->execute([':table_name' => $tableName]);
        $columnRows = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($columnRows) || empty($columnRows)) {
            throw new \RuntimeException('Tabela não encontrada no banco: ' . $tableName);
        }

        $indexStmt = $this->pdo->prepare(
            "SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             ORDER BY INDEX_NAME, SEQ_IN_INDEX"
        );
        $indexStmt->execute([':table_name' => $tableName]);
        $indexRows = $indexStmt->fetchAll(PDO::FETCH_ASSOC);

        $fkStmt = $this->pdo->prepare(
            "SELECT k.COLUMN_NAME,
                    k.CONSTRAINT_NAME,
                    k.REFERENCED_TABLE_NAME,
                    k.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              AND rc.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = DATABASE()
               AND k.TABLE_NAME = :table_name
               AND k.REFERENCED_TABLE_NAME IS NOT NULL"
        );
        $fkStmt->execute([':table_name' => $tableName]);
        $fkRows = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

        $indexesByColumn = [];
        if (is_array($indexRows)) {
            foreach ($indexRows as $indexRow) {
                $columnName = (string) ($indexRow['COLUMN_NAME'] ?? '');
                $indexName = (string) ($indexRow['INDEX_NAME'] ?? '');
                if ($columnName === '' || $indexName === '' || $indexName === 'PRIMARY') {
                    continue;
                }

                $indexesByColumn[$columnName][] = [
                    'name' => $indexName,
                    'non_unique' => (int) ($indexRow['NON_UNIQUE'] ?? 1),
                ];
            }
        }

        $fksByColumn = [];
        if (is_array($fkRows)) {
            foreach ($fkRows as $fkRow) {
                $columnName = (string) ($fkRow['COLUMN_NAME'] ?? '');
                if ($columnName === '') {
                    continue;
                }

                $fksByColumn[$columnName][] = [
                    'constraint_name' => (string) ($fkRow['CONSTRAINT_NAME'] ?? ''),
                    'referenced_table' => (string) ($fkRow['REFERENCED_TABLE_NAME'] ?? ''),
                    'referenced_column' => (string) ($fkRow['REFERENCED_COLUMN_NAME'] ?? ''),
                    'update_rule' => (string) ($fkRow['UPDATE_RULE'] ?? ''),
                    'delete_rule' => (string) ($fkRow['DELETE_RULE'] ?? ''),
                ];
            }
        }

        $columns = [];
        foreach ($columnRows as $columnRow) {
            $columnName = (string) ($columnRow['COLUMN_NAME'] ?? '');
            if ($columnName === '') {
                continue;
            }

            $columnType = (string) ($columnRow['COLUMN_TYPE'] ?? '');
            $dataType = strtolower((string) ($columnRow['DATA_TYPE'] ?? ''));
            $isNullable = strtoupper((string) ($columnRow['IS_NULLABLE'] ?? 'YES')) === 'YES';
            $defaultRaw = $columnRow['COLUMN_DEFAULT'] ?? null;
            $defaultValue = $this->formatColumnDefault($defaultRaw);
            $extra = trim((string) ($columnRow['EXTRA'] ?? ''));
            $columnKey = strtoupper((string) ($columnRow['COLUMN_KEY'] ?? ''));
            $columnComment = trim((string) ($columnRow['COLUMN_COMMENT'] ?? ''));
            $isAutoIncrement = stripos($extra, 'auto_increment') !== false;
            $isPrimary = $columnKey === 'PRI';
            $isUnique = $columnKey === 'UNI';
            $isRequired = $this->isRequiredColumn($isNullable, $defaultRaw, $extra, $isAutoIncrement);
            $exampleValue = $this->deriveExampleValue($columnName, $dataType, $columnType, $isRequired, $isAutoIncrement);

            $constraints = [];
            if ($isPrimary) {
                $constraints[] = 'PK';
            }
            if ($isUnique) {
                $constraints[] = 'UNIQUE';
            }
            if ($isNullable) {
                $constraints[] = 'NULL';
            } else {
                $constraints[] = 'NOT NULL';
            }
            if ($isAutoIncrement) {
                $constraints[] = 'AUTO_INCREMENT';
            }
            if ($defaultValue !== null) {
                $constraints[] = 'DEFAULT ' . $defaultValue;
            }

            foreach ($indexesByColumn[$columnName] ?? [] as $indexInfo) {
                $indexName = (string) ($indexInfo['name'] ?? '');
                $nonUnique = (int) ($indexInfo['non_unique'] ?? 1);
                if ($indexName === '') {
                    continue;
                }
                $constraints[] = ($nonUnique === 0 ? 'UK ' : 'INDEX ') . $indexName;
            }

            foreach ($fksByColumn[$columnName] ?? [] as $fkInfo) {
                $refTable = (string) ($fkInfo['referenced_table'] ?? '');
                $refColumn = (string) ($fkInfo['referenced_column'] ?? '');
                $onUpdate = strtoupper((string) ($fkInfo['update_rule'] ?? ''));
                $onDelete = strtoupper((string) ($fkInfo['delete_rule'] ?? ''));
                if ($refTable === '' || $refColumn === '') {
                    continue;
                }
                $parts = ['FK ' . $refTable . '.' . $refColumn];
                if ($onUpdate !== '') {
                    $parts[] = 'ON UPDATE ' . $onUpdate;
                }
                if ($onDelete !== '') {
                    $parts[] = 'ON DELETE ' . $onDelete;
                }
                $constraints[] = implode(' ', $parts);
            }

            $columns[] = [
                'position' => (int) ($columnRow['ORDINAL_POSITION'] ?? 0),
                'column_name' => $columnName,
                'column_type' => $columnType,
                'data_type' => $dataType,
                'nullable' => $isNullable,
                'required_on_insert' => $isRequired,
                'default' => $defaultValue,
                'extra' => $extra !== '' ? $extra : null,
                'comment' => $columnComment !== '' ? $columnComment : null,
                'constraints' => array_values(array_unique($constraints)),
                'example_value' => $exampleValue,
            ];
        }

        return ['columns' => $columns];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<int, array<int, mixed>>
     */
    private function buildCsvTemplateRows(array $columns): array
    {
        $header = [];
        $typeRow = [];
        $requiredRow = [];
        $defaultRow = [];
        $constraintRow = [];
        $commentRow = [];
        $exampleRow = [];

        foreach ($columns as $columnMeta) {
            $header[] = (string) ($columnMeta['column_name'] ?? '');
            $typeRow[] = 'TIPO=' . (string) ($columnMeta['column_type'] ?? '');
            $requiredRow[] = 'OBRIGATORIO=' . (!empty($columnMeta['required_on_insert']) ? 'SIM' : 'NAO');
            $defaultValue = $columnMeta['default'] ?? null;
            $defaultRow[] = 'DEFAULT=' . ($defaultValue === null ? 'NULL' : (string) $defaultValue);

            $constraints = $columnMeta['constraints'] ?? [];
            $constraintRow[] = 'REGRAS=' . (is_array($constraints) ? implode(' | ', array_map('strval', $constraints)) : '');

            $comment = trim((string) ($columnMeta['comment'] ?? ''));
            $commentRow[] = 'DETALHE=' . ($comment !== '' ? $comment : '-');

            $exampleRow[] = $columnMeta['example_value'] ?? null;
        }

        return [
            $header,
            $typeRow,
            $requiredRow,
            $defaultRow,
            $constraintRow,
            $commentRow,
            $exampleRow,
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function renderCsv(array $rows, string $delimiter = ';'): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Falha ao montar modelo CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($stream, $row, $delimiter);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (!is_string($content)) {
            throw new \RuntimeException('Falha ao gerar conteúdo CSV.');
        }

        return $content;
    }

    private function formatColumnDefault(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }
        if (is_bool($default)) {
            return $default ? '1' : '0';
        }
        return (string) $default;
    }

    private function isRequiredColumn(bool $isNullable, mixed $defaultRaw, string $extra, bool $isAutoIncrement): bool
    {
        if ($isNullable || $isAutoIncrement) {
            return false;
        }
        if ($defaultRaw !== null) {
            return false;
        }
        if (stripos($extra, 'default_generated') !== false) {
            return false;
        }
        if (stripos($extra, 'virtual generated') !== false || stripos($extra, 'stored generated') !== false) {
            return false;
        }
        return true;
    }

    private function deriveExampleValue(
        string $columnName,
        string $dataType,
        string $columnType,
        bool $isRequired,
        bool $isAutoIncrement
    ): mixed {
        if ($isAutoIncrement) {
            return null;
        }

        $columnNameLower = strtolower($columnName);
        $enumValues = $this->parseMysqlEnumSetValues($columnType);
        if (!empty($enumValues)) {
            return $enumValues[0];
        }

        if (str_contains($columnNameLower, 'email')) {
            return 'exemplo@dominio.com';
        }
        if (str_contains($columnNameLower, 'phone') || str_contains($columnNameLower, 'telefone')) {
            return '11999999999';
        }
        if (str_contains($columnNameLower, 'cpf')) {
            return '00000000000';
        }
        if (str_contains($columnNameLower, 'cnpj')) {
            return '00000000000000';
        }
        if (str_contains($columnNameLower, 'cep')) {
            return '00000000';
        }
        if (str_contains($columnNameLower, 'sku')) {
            return 'SKU-EXEMPLO-001';
        }
        if (str_contains($columnNameLower, 'status')) {
            return $isRequired ? 'ativo' : null;
        }

        return match ($dataType) {
            'tinyint' => str_contains($columnType, '(1)') ? 1 : 0,
            'smallint', 'mediumint', 'int', 'integer', 'bigint' => $isRequired ? 1 : null,
            'decimal', 'float', 'double' => $isRequired ? '0.00' : null,
            'date' => date('Y-m-d'),
            'datetime', 'timestamp' => date('Y-m-d H:i:s'),
            'time' => '12:00:00',
            'json' => '{"chave":"valor"}',
            'char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext' => $isRequired ? 'exemplo' : null,
            default => $isRequired ? 'exemplo' : null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function parseMysqlEnumSetValues(string $columnType): array
    {
        if (!preg_match('/^(enum|set)\\((.*)\\)$/i', trim($columnType), $matches)) {
            return [];
        }

        $values = str_getcsv((string) ($matches[2] ?? ''), ',', "'", "\\");
        if (!is_array($values)) {
            return [];
        }

        $parsed = [];
        foreach ($values as $value) {
            $clean = trim((string) $value);
            if ($clean !== '') {
                $parsed[] = $clean;
            }
        }

        return $parsed;
    }

    private function stripBom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }
        return $value;
    }
}
