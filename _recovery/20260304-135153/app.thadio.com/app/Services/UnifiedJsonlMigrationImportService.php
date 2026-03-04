<?php

namespace App\Services;

use PDO;
use PDOStatement;

class UnifiedJsonlMigrationImportService
{
    private PDO $pdo;

    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $tableColumnsCache = [];

    /**
     * @var array<string, PDOStatement>
     */
    private array $statementCache = [];

    private ?PDOStatement $mapSelectStatement = null;
    private ?PDOStatement $mapUpsertStatement = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function importFile(string $filePath, array $options = []): array
    {
        $mode = (string) ($options['mode'] ?? 'upsert');
        if (!in_array($mode, ['insert', 'upsert'], true)) {
            $mode = 'upsert';
        }

        $dryRun = !empty($options['dry_run']);
        $strict = !array_key_exists('strict', $options) || !empty($options['strict']);
        $continueOnError = !empty($options['continue_on_error']);
        $skipUnchanged = !array_key_exists('skip_unchanged', $options) || !empty($options['skip_unchanged']);
        $withIdempotencyMap = !array_key_exists('idempotency_map', $options) || !empty($options['idempotency_map']);

        $report = [
            'status' => 'success',
            'file_path' => $filePath,
            'mode' => $mode,
            'dry_run' => $dryRun,
            'strict' => $strict,
            'continue_on_error' => $continueOnError,
            'skip_unchanged' => $skipUnchanged,
            'started_at' => date('c'),
            'finished_at' => null,
            'manifest' => null,
            'summary' => null,
            'totals' => [
                'lines_read' => 0,
                'schema_lines' => 0,
                'data_rows' => 0,
                'section_end_lines' => 0,
                'imported_rows' => 0,
                'db_affected_rows' => 0,
                'skipped_rows' => 0,
                'media_blob_rows' => 0,
                'warnings' => 0,
                'errors' => 0,
            ],
            'entities' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (!is_file($filePath) || !is_readable($filePath)) {
            $this->pushError($report, 'Arquivo nao encontrado ou sem permissao de leitura: ' . $filePath, 0, '');
            $report['status'] = 'error';
            $report['finished_at'] = date('c');
            return $report;
        }

        if ($withIdempotencyMap && !$dryRun) {
            $this->ensureIdempotencyMapTable();
        }

        $schemas = [];
        $manifest = null;
        $summary = null;
        $currentEntity = null;
        $sectionCounts = [];
        $seenSectionEnd = [];
        $firstDataLineNo = null;

        $isGzip = str_ends_with(strtolower($filePath), '.gz');
        $handle = $isGzip ? @gzopen($filePath, 'rb') : @fopen($filePath, 'rb');
        if ($handle === false) {
            $this->pushError($report, 'Falha ao abrir arquivo para leitura.', 0, '');
            $report['status'] = 'error';
            $report['finished_at'] = date('c');
            return $report;
        }

        $lineNumber = 0;
        try {
            while (true) {
                $line = $isGzip ? gzgets($handle) : fgets($handle);
                if ($line === false) {
                    break;
                }
                $lineNumber++;
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                $report['totals']['lines_read']++;
                $payload = json_decode($trimmed, true);
                if (!is_array($payload)) {
                    $this->pushError($report, 'Linha JSON invalida.', $lineNumber, $trimmed);
                    if (!$continueOnError) {
                        break;
                    }
                    continue;
                }

                $type = (string) ($payload['type'] ?? '');
                if ($type === '') {
                    $this->pushError($report, 'Linha sem campo type.', $lineNumber, $trimmed);
                    if (!$continueOnError) {
                        break;
                    }
                    continue;
                }

                if ($firstDataLineNo === null) {
                    $firstDataLineNo = $lineNumber;
                }

                if ($type === 'manifest') {
                    if ($manifest !== null) {
                        $this->pushError($report, 'Manifest duplicado.', $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }
                    if ($firstDataLineNo !== $lineNumber) {
                        $this->pushError($report, 'Manifest deve ser a primeira linha util do arquivo.', $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                    }
                    $manifest = $payload;
                    $report['manifest'] = $manifest;
                    continue;
                }

                if ($type === 'schema') {
                    $entity = $this->normalizeEntityName((string) ($payload['entity'] ?? ''));
                    if ($entity === '') {
                        $this->pushError($report, 'Schema sem entity valida.', $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }
                    if ($currentEntity !== null) {
                        $this->pushError($report, 'Nova secao schema iniciada sem section_end da entidade anterior: ' . $currentEntity, $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                    }

                    $schemas[$entity] = $payload;
                    $currentEntity = $entity;
                    $report['totals']['schema_lines']++;
                    $this->ensureEntityReport($report, $entity);

                    if (!$this->tableExists($entity)) {
                        $this->pushWarning($report, 'Tabela alvo inexistente no banco para entidade: ' . $entity, $lineNumber, $trimmed);
                        if ($strict) {
                            $report['entities'][$entity]['table_missing'] = true;
                        }
                    }
                    continue;
                }

                if ($type === 'data') {
                    $entity = $this->normalizeEntityName((string) ($payload['entity'] ?? ''));
                    if ($entity === '') {
                        $this->pushError($report, 'Data sem entity valida.', $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }
                    $this->ensureEntityReport($report, $entity);
                    $report['totals']['data_rows']++;
                    $report['entities'][$entity]['data_rows']++;
                    $sectionCounts[$entity] = ($sectionCounts[$entity] ?? 0) + 1;

                    if (!isset($schemas[$entity])) {
                        $this->pushError($report, 'Data recebida antes de schema para entidade: ' . $entity, $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }

                    if ($currentEntity !== null && $entity !== $currentEntity) {
                        $this->pushError($report, 'Data fora de secao atual. Esperado: ' . $currentEntity . ', recebido: ' . $entity, $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }

                    if (!empty($report['entities'][$entity]['table_missing']) && $strict) {
                        $this->pushError($report, 'Nao e possivel importar entidade sem tabela alvo: ' . $entity, $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }

                    $rowData = $payload['data'] ?? null;
                    if (!is_array($rowData)) {
                        $this->pushError($report, 'Data sem objeto data valido para entidade: ' . $entity, $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }

                    $legacyId = isset($payload['legacy_id']) ? (string) $payload['legacy_id'] : '';
                    $sourceSystem = isset($manifest['source_system']) ? (string) $manifest['source_system'] : 'legacy';
                    $idempotencyKey = isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : '';
                    if ($idempotencyKey === '' && $legacyId !== '') {
                        $idempotencyKey = $sourceSystem . ':' . $entity . ':' . $legacyId;
                    }
                    if ($legacyId === '') {
                        $this->pushWarning($report, 'Registro sem legacy_id em ' . $entity . '; idempotencia parcial.', $lineNumber, $trimmed);
                    }

                    $payloadHash = hash('sha256', json_encode($rowData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    if (!$dryRun && $withIdempotencyMap && $skipUnchanged && $legacyId !== '') {
                        $previousHash = $this->findPreviousPayloadHash($sourceSystem, $entity, $legacyId);
                        if ($previousHash !== null && hash_equals($previousHash, $payloadHash)) {
                            $report['totals']['skipped_rows']++;
                            $report['entities'][$entity]['skipped_rows']++;
                            continue;
                        }
                    }

                    if ($dryRun) {
                        $this->validateRowColumns($entity, $rowData);
                        $report['totals']['imported_rows']++;
                        $report['entities'][$entity]['imported_rows']++;
                        continue;
                    }

                    try {
                        $affectedRows = $this->importRow($entity, $rowData, $mode);
                        $report['totals']['imported_rows']++;
                        $report['entities'][$entity]['imported_rows']++;
                        $report['totals']['db_affected_rows'] += $affectedRows;
                        $report['entities'][$entity]['db_affected_rows'] += $affectedRows;

                        if ($withIdempotencyMap && $legacyId !== '') {
                            $pkJson = $this->extractPkJson($schemas[$entity], $rowData);
                            $this->upsertIdempotencyMap(
                                $sourceSystem,
                                $entity,
                                $legacyId,
                                $idempotencyKey,
                                $payloadHash,
                                'imported',
                                $pkJson,
                                null
                            );
                        }
                    } catch (\Throwable $exception) {
                        if ($withIdempotencyMap && $legacyId !== '') {
                            $this->upsertIdempotencyMap(
                                $sourceSystem,
                                $entity,
                                $legacyId,
                                $idempotencyKey,
                                $payloadHash,
                                'error',
                                null,
                                mb_substr($exception->getMessage(), 0, 500)
                            );
                        }

                        $this->pushError($report, 'Falha ao importar registro de ' . $entity . ': ' . $exception->getMessage(), $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                    }
                    continue;
                }

                if ($type === 'section_end') {
                    $entity = $this->normalizeEntityName((string) ($payload['entity'] ?? ''));
                    if ($entity === '') {
                        $this->pushError($report, 'section_end sem entity valida.', $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                        continue;
                    }
                    $this->ensureEntityReport($report, $entity);
                    $report['totals']['section_end_lines']++;
                    $seenSectionEnd[$entity] = true;

                    if ($currentEntity !== null && $currentEntity !== $entity) {
                        $this->pushError($report, 'section_end fora da secao atual. Atual=' . $currentEntity . ', recebido=' . $entity, $lineNumber, $trimmed);
                        if (!$continueOnError) {
                            break;
                        }
                    }
                    $currentEntity = null;

                    $expectedCount = (int) ($payload['count'] ?? -1);
                    if ($expectedCount >= 0) {
                        $actualCount = (int) ($sectionCounts[$entity] ?? 0);
                        if ($expectedCount !== $actualCount) {
                            $message = 'Contagem divergente em section_end de ' . $entity . '. Esperado=' . $expectedCount . ', lido=' . $actualCount;
                            if ($strict) {
                                $this->pushError($report, $message, $lineNumber, $trimmed);
                                if (!$continueOnError) {
                                    break;
                                }
                            } else {
                                $this->pushWarning($report, $message, $lineNumber, $trimmed);
                            }
                        }
                    }
                    continue;
                }

                if ($type === 'summary') {
                    $summary = $payload;
                    $report['summary'] = $summary;
                    continue;
                }

                if ($type === 'media_blob') {
                    $report['totals']['media_blob_rows']++;
                    $this->pushWarning($report, 'Linha media_blob recebida. O importador atual valida, mas nao materializa binario.', $lineNumber, '');
                    continue;
                }

                $this->pushWarning($report, 'Tipo de linha desconhecido: ' . $type, $lineNumber, $trimmed);
            }
        } finally {
            if ($isGzip) {
                gzclose($handle);
            } else {
                fclose($handle);
            }
        }

        if ($manifest === null) {
            $this->pushError($report, 'Arquivo sem linha de manifest.', 0, '');
        }
        if ($summary === null) {
            $this->pushError($report, 'Arquivo sem linha de summary.', 0, '');
        }
        if ($currentEntity !== null) {
            $this->pushError($report, 'Arquivo terminou sem section_end da entidade: ' . $currentEntity, 0, '');
        }

        if (is_array($manifest) && isset($manifest['counts']) && is_array($manifest['counts'])) {
            foreach ($manifest['counts'] as $entityName => $count) {
                $entity = $this->normalizeEntityName((string) $entityName);
                if ($entity === '') {
                    continue;
                }
                $this->ensureEntityReport($report, $entity);
                $expected = (int) $count;
                $actual = (int) ($report['entities'][$entity]['data_rows'] ?? 0);
                if ($expected !== $actual) {
                    $message = 'Manifest.counts divergente para ' . $entity . '. Esperado=' . $expected . ', lido=' . $actual;
                    if ($strict) {
                        $this->pushError($report, $message, 0, '');
                    } else {
                        $this->pushWarning($report, $message, 0, '');
                    }
                }
            }
        }

        foreach (array_keys($schemas) as $entity) {
            if (empty($seenSectionEnd[$entity])) {
                $this->pushError($report, 'Secao sem section_end para entidade: ' . $entity, 0, '');
            }
        }

        $report['finished_at'] = date('c');
        if ($report['totals']['errors'] > 0) {
            $report['status'] = $report['totals']['imported_rows'] > 0 ? 'partial' : 'error';
        } elseif ($report['totals']['warnings'] > 0) {
            $report['status'] = 'success_with_warnings';
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function ensureEntityReport(array &$report, string $entity): void
    {
        if (isset($report['entities'][$entity])) {
            return;
        }

        $report['entities'][$entity] = [
            'data_rows' => 0,
            'imported_rows' => 0,
            'db_affected_rows' => 0,
            'skipped_rows' => 0,
            'table_missing' => false,
        ];
    }

    private function normalizeEntityName(string $entity): string
    {
        $entity = trim($entity);
        if ($entity === '') {
            return '';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $entity)) {
            return '';
        }
        return strtolower($entity);
    }

    /**
     * @param array<string, mixed> $rowData
     */
    private function validateRowColumns(string $tableName, array $rowData): void
    {
        $columns = array_keys($rowData);
        if (empty($columns)) {
            throw new \RuntimeException('Registro sem colunas para tabela ' . $tableName . '.');
        }

        $metadata = $this->getTableColumnsMetadata($tableName);
        foreach ($columns as $column) {
            if (!is_string($column) || !isset($metadata[$column])) {
                throw new \RuntimeException('Coluna invalida para ' . $tableName . ': ' . (string) $column);
            }
        }
    }

    /**
     * @param array<string, mixed> $rowData
     */
    private function importRow(string $tableName, array $rowData, string $mode): int
    {
        $this->validateRowColumns($tableName, $rowData);

        $columns = array_keys($rowData);
        sort($columns);
        $statement = $this->prepareInsertStatement($tableName, $columns, $mode);
        $metadata = $this->getTableColumnsMetadata($tableName);

        $payload = [];
        foreach ($columns as $column) {
            $value = $rowData[$column] ?? null;
            $payload[':' . $column] = $this->normalizeValueForColumn($value, $metadata[$column]['data_type'] ?? '');
        }

        $statement->execute($payload);
        return (int) $statement->rowCount();
    }

    private function normalizeValueForColumn(mixed $value, string $dataType): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException('Falha ao serializar valor composto para coluna do tipo ' . $dataType . '.');
            }
            return $encoded;
        }

        return $value;
    }

    /**
     * @param array<int, string> $columns
     */
    private function prepareInsertStatement(string $tableName, array $columns, string $mode): PDOStatement
    {
        $cacheKey = $tableName . '|' . $mode . '|' . implode(',', $columns);
        if (isset($this->statementCache[$cacheKey])) {
            return $this->statementCache[$cacheKey];
        }

        $quotedColumns = array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

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

        $statement = $this->pdo->prepare($sql);
        $this->statementCache[$cacheKey] = $statement;
        return $statement;
    }

    private function tableExists(string $tableName): bool
    {
        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }

        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND TABLE_TYPE = 'BASE TABLE'
             LIMIT 1"
        );
        $statement->execute([':table_name' => $tableName]);
        $exists = (bool) $statement->fetchColumn();
        $this->tableExistsCache[$tableName] = $exists;

        return $exists;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTableColumnsMetadata(string $tableName): array
    {
        if (isset($this->tableColumnsCache[$tableName])) {
            return $this->tableColumnsCache[$tableName];
        }

        $statement = $this->pdo->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name"
        );
        $statement->execute([':table_name' => $tableName]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || empty($rows)) {
            throw new \RuntimeException('Tabela sem metadados ou inexistente: ' . $tableName);
        }

        $columns = [];
        foreach ($rows as $row) {
            $columnName = (string) ($row['COLUMN_NAME'] ?? '');
            if ($columnName === '') {
                continue;
            }
            $columns[$columnName] = [
                'data_type' => strtolower((string) ($row['DATA_TYPE'] ?? '')),
                'is_nullable' => strtoupper((string) ($row['IS_NULLABLE'] ?? 'YES')) === 'YES',
                'column_default' => $row['COLUMN_DEFAULT'] ?? null,
            ];
        }

        if (empty($columns)) {
            throw new \RuntimeException('Nao foi possivel carregar colunas da tabela: ' . $tableName);
        }

        $this->tableColumnsCache[$tableName] = $columns;
        return $columns;
    }

    private function ensureIdempotencyMapTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migration_import_map (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_system VARCHAR(120) NOT NULL,
            entity VARCHAR(120) NOT NULL,
            legacy_id VARCHAR(190) NOT NULL,
            idempotency_key VARCHAR(255) NOT NULL,
            payload_hash CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'imported',
            target_pk_json LONGTEXT NULL,
            message VARCHAR(500) NULL,
            imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_migration_map_source_entity_legacy (source_system, entity, legacy_id),
            UNIQUE KEY uniq_migration_map_idempotency (idempotency_key),
            INDEX idx_migration_map_entity (entity, imported_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->pdo->exec($sql);
    }

    private function findPreviousPayloadHash(string $sourceSystem, string $entity, string $legacyId): ?string
    {
        if ($legacyId === '') {
            return null;
        }

        if ($this->mapSelectStatement === null) {
            $this->mapSelectStatement = $this->pdo->prepare(
                "SELECT payload_hash
                 FROM migration_import_map
                 WHERE source_system = :source_system
                   AND entity = :entity
                   AND legacy_id = :legacy_id
                 LIMIT 1"
            );
        }

        $this->mapSelectStatement->execute([
            ':source_system' => $sourceSystem,
            ':entity' => $entity,
            ':legacy_id' => $legacyId,
        ]);
        $value = $this->mapSelectStatement->fetchColumn();
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function upsertIdempotencyMap(
        string $sourceSystem,
        string $entity,
        string $legacyId,
        string $idempotencyKey,
        string $payloadHash,
        string $status,
        ?string $targetPkJson,
        ?string $message
    ): void {
        if ($legacyId === '') {
            return;
        }

        if ($idempotencyKey === '') {
            $idempotencyKey = $sourceSystem . ':' . $entity . ':' . $legacyId;
        }

        if ($this->mapUpsertStatement === null) {
            $this->mapUpsertStatement = $this->pdo->prepare(
                "INSERT INTO migration_import_map (
                    source_system,
                    entity,
                    legacy_id,
                    idempotency_key,
                    payload_hash,
                    status,
                    target_pk_json,
                    message
                ) VALUES (
                    :source_system,
                    :entity,
                    :legacy_id,
                    :idempotency_key,
                    :payload_hash,
                    :status,
                    :target_pk_json,
                    :message
                )
                ON DUPLICATE KEY UPDATE
                    idempotency_key = VALUES(idempotency_key),
                    payload_hash = VALUES(payload_hash),
                    status = VALUES(status),
                    target_pk_json = VALUES(target_pk_json),
                    message = VALUES(message),
                    imported_at = CURRENT_TIMESTAMP"
            );
        }

        $this->mapUpsertStatement->execute([
            ':source_system' => $sourceSystem,
            ':entity' => $entity,
            ':legacy_id' => $legacyId,
            ':idempotency_key' => $idempotencyKey,
            ':payload_hash' => $payloadHash,
            ':status' => $status,
            ':target_pk_json' => $targetPkJson,
            ':message' => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $rowData
     */
    private function extractPkJson(array $schema, array $rowData): ?string
    {
        $pkColumns = $schema['pk'] ?? [];
        if (!is_array($pkColumns) || empty($pkColumns)) {
            return null;
        }

        $pkData = [];
        foreach ($pkColumns as $column) {
            if (!is_string($column) || $column === '') {
                continue;
            }
            if (array_key_exists($column, $rowData)) {
                $pkData[$column] = $rowData[$column];
            }
        }

        if (empty($pkData)) {
            return null;
        }

        $encoded = json_encode($pkData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function pushWarning(array &$report, string $message, int $lineNumber, string $raw): void
    {
        $report['totals']['warnings']++;
        $report['warnings'][] = [
            'line' => $lineNumber,
            'message' => $message,
            'raw' => $raw !== '' ? mb_substr($raw, 0, 500) : '',
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function pushError(array &$report, string $message, int $lineNumber, string $raw): void
    {
        $report['totals']['errors']++;
        $report['errors'][] = [
            'line' => $lineNumber,
            'message' => $message,
            'raw' => $raw !== '' ? mb_substr($raw, 0, 500) : '',
        ];
    }
}
