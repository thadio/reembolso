<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DependencyInspectorService
{
    /**
     * @var array<string, array{label: string, list_path: string, show_path: string|null}>
     */
    private const ENTITY_CATALOG = [
        'people' => ['label' => 'Pessoas', 'list_path' => '/people', 'show_path' => '/people/show?id={id}'],
        'organs' => ['label' => 'Orgaos', 'list_path' => '/organs', 'show_path' => '/organs/show?id={id}'],
        'users' => ['label' => 'Usuarios', 'list_path' => '/users', 'show_path' => '/users/show?id={id}'],
        'cdos' => ['label' => 'CDOs', 'list_path' => '/cdos', 'show_path' => '/cdos/show?id={id}'],
        'invoices' => ['label' => 'Boletos', 'list_path' => '/invoices', 'show_path' => '/invoices/show?id={id}'],
        'cost_mirrors' => ['label' => 'Espelhos de custo', 'list_path' => '/cost-mirrors', 'show_path' => '/cost-mirrors/show?id={id}'],
        'office_templates' => ['label' => 'Oficios (templates)', 'list_path' => '/office-templates', 'show_path' => '/office-templates/show?id={id}'],
        'process_metadata' => ['label' => 'Processo formal', 'list_path' => '/process-meta', 'show_path' => '/process-meta/show?id={id}'],
        'mte_destinations' => ['label' => 'UORG MTE', 'list_path' => '/mte-destinations', 'show_path' => '/mte-destinations/show?id={id}'],
        'assignment_flows' => ['label' => 'Fluxos BPMN', 'list_path' => '/pipeline-flows', 'show_path' => '/pipeline-flows/show?id={id}'],
        'document_types' => ['label' => 'Tipos de documento', 'list_path' => '/document-types', 'show_path' => '/document-types/show?id={id}'],
        'cost_item_catalog' => ['label' => 'Catalogo de itens de custo', 'list_path' => '/cost-items', 'show_path' => '/cost-items/show?id={id}'],
        'budget_cycles' => ['label' => 'Ciclos orcamentarios', 'list_path' => '/budget', 'show_path' => null],
        'hiring_scenarios' => ['label' => 'Simulacoes de contratacao', 'list_path' => '/budget', 'show_path' => null],
        'budget_scenario_parameters' => ['label' => 'Parametros de simulacao', 'list_path' => '/budget', 'show_path' => null],
    ];

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, array<int, string>> */
    private array $tableColumnsCache = [];

    /** @var array<string, array<int, array<string, string>>> */
    private array $incomingConstraintsCache = [];

    private ?string $schemaName = null;

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<int, array{table: string, label: string, list_path: string, show_path: string|null, exists: bool}>
     */
    public function catalog(): array
    {
        $items = [];

        foreach (self::ENTITY_CATALOG as $table => $meta) {
            $items[] = [
                'table' => $table,
                'label' => $meta['label'],
                'list_path' => $meta['list_path'],
                'show_path' => $meta['show_path'],
                'exists' => $this->tableExists($table),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function systemOverview(): array
    {
        $rows = [];

        foreach (self::ENTITY_CATALOG as $table => $meta) {
            $exists = $this->tableExists($table);
            $constraints = $exists ? $this->incomingConstraints($table) : [];

            $restrictive = 0;
            $cascade = 0;
            $setNull = 0;

            foreach ($constraints as $constraint) {
                $rule = $this->normalizeDeleteRule((string) ($constraint['delete_rule'] ?? 'NO ACTION'));
                if ($rule === 'CASCADE') {
                    $cascade++;
                    continue;
                }

                if ($rule === 'SET NULL') {
                    $setNull++;
                    continue;
                }

                $restrictive++;
            }

            $rows[] = [
                'table' => $table,
                'label' => $meta['label'],
                'list_path' => $meta['list_path'],
                'show_path' => $meta['show_path'],
                'exists' => $exists,
                'has_deleted_at' => $exists ? $this->hasColumn($table, 'deleted_at') : false,
                'incoming_constraints' => count($constraints),
                'restrictive_constraints' => $restrictive,
                'cascade_constraints' => $cascade,
                'set_null_constraints' => $setNull,
            ];
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => [
                (int) ($b['restrictive_constraints'] ?? 0),
                (int) ($b['incoming_constraints'] ?? 0),
                (string) ($a['label'] ?? ''),
            ] <=> [
                (int) ($a['restrictive_constraints'] ?? 0),
                (int) ($a['incoming_constraints'] ?? 0),
                (string) ($b['label'] ?? ''),
            ]
        );

        return $rows;
    }

    /**
     * @return array{
     *   ok: bool,
     *   errors: array<int, string>,
     *   entity?: array<string, mixed>,
     *   record?: array<string, mixed>,
     *   summary?: array<string, int>,
     *   dependencies?: array<int, array<string, mixed>>
     * }
     */
    public function inspect(string $entity, int $id): array
    {
        $table = $this->normalizeEntity($entity);
        if ($table === null) {
            return [
                'ok' => false,
                'errors' => ['Selecione uma entidade valida para analise.'],
            ];
        }

        if ($id <= 0) {
            return [
                'ok' => false,
                'errors' => ['Informe um ID valido (maior que zero).'],
            ];
        }

        if (!$this->tableExists($table)) {
            return [
                'ok' => false,
                'errors' => ['A tabela selecionada nao existe neste ambiente.'],
            ];
        }

        $record = $this->recordSnapshot($table, $id);
        if ($record === null) {
            return [
                'ok' => false,
                'errors' => ['Registro nao encontrado para a entidade selecionada.'],
            ];
        }

        $meta = self::ENTITY_CATALOG[$table];
        $entityInfo = [
            'table' => $table,
            'label' => $meta['label'],
            'list_path' => $meta['list_path'],
            'show_path' => $meta['show_path'] !== null ? str_replace('{id}', (string) $id, $meta['show_path']) : null,
            'has_deleted_at' => $this->hasColumn($table, 'deleted_at'),
        ];

        $dependencies = [];
        $blockingConstraints = 0;
        $blockingRows = 0;
        $activeReferencesTotal = 0;
        $referencesTotal = 0;

        foreach ($this->incomingConstraints($table) as $constraint) {
            $sourceTable = (string) ($constraint['table_name'] ?? '');
            $sourceColumn = (string) ($constraint['column_name'] ?? '');
            $deleteRule = $this->normalizeDeleteRule((string) ($constraint['delete_rule'] ?? 'NO ACTION'));

            if ($sourceTable === '' || $sourceColumn === '' || !$this->tableExists($sourceTable)) {
                continue;
            }

            $counts = $this->referenceCounts($sourceTable, $sourceColumn, $id);
            $total = max(0, (int) ($counts['total'] ?? 0));
            $active = max(0, (int) ($counts['active'] ?? 0));

            $referencesTotal += $total;
            $activeReferencesTotal += $active;

            $blocksDelete = $total > 0 && in_array($deleteRule, ['RESTRICT', 'NO ACTION'], true);
            if ($blocksDelete) {
                $blockingConstraints++;
                $blockingRows += $active;
            }

            $dependencies[] = [
                'source_table' => $sourceTable,
                'source_column' => $sourceColumn,
                'constraint_name' => (string) ($constraint['constraint_name'] ?? ''),
                'delete_rule' => $deleteRule,
                'total' => $total,
                'active' => $active,
                'source_has_deleted_at' => $this->hasColumn($sourceTable, 'deleted_at'),
                'blocks_delete' => $blocksDelete,
                'impact_label' => $this->impactLabel($deleteRule),
            ];
        }

        usort(
            $dependencies,
            static fn (array $a, array $b): int => [
                ($b['blocks_delete'] ?? false) ? 1 : 0,
                (int) ($b['active'] ?? 0),
                (int) ($b['total'] ?? 0),
                (string) ($a['source_table'] ?? ''),
            ] <=> [
                ($a['blocks_delete'] ?? false) ? 1 : 0,
                (int) ($a['active'] ?? 0),
                (int) ($a['total'] ?? 0),
                (string) ($b['source_table'] ?? ''),
            ]
        );

        return [
            'ok' => true,
            'errors' => [],
            'entity' => $entityInfo,
            'record' => $record,
            'summary' => [
                'dependencies_count' => count($dependencies),
                'references_total' => $referencesTotal,
                'active_references_total' => $activeReferencesTotal,
                'blocking_constraints' => $blockingConstraints,
                'blocking_rows' => $blockingRows,
            ],
            'dependencies' => $dependencies,
        ];
    }

    /** @return array<string, mixed>|null */
    private function recordSnapshot(string $table, int $id): ?array
    {
        $columns = ['id'];
        foreach (['deleted_at', 'name', 'title', 'code', 'cycle_year', 'financial_nature', 'reference_month', 'created_at', 'updated_at'] as $column) {
            if ($this->hasColumn($table, $column)) {
                $columns[] = $column;
            }
        }

        $quotedColumns = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = :id LIMIT 1',
            $quotedColumns,
            $this->quoteIdentifier($table),
            $this->quoteIdentifier('id')
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string, int> */
    private function referenceCounts(string $table, string $column, int $id): array
    {
        $sql = sprintf(
            'SELECT COUNT(*) AS total FROM %s WHERE %s = :id',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($column)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $total = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        if (!$this->hasColumn($table, 'deleted_at')) {
            return [
                'total' => $total,
                'active' => $total,
            ];
        }

        $activeSql = sprintf(
            'SELECT COUNT(*) AS total FROM %s WHERE %s = :id AND deleted_at IS NULL',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($column)
        );
        $activeStmt = $this->db->prepare($activeSql);
        $activeStmt->execute(['id' => $id]);

        return [
            'total' => $total,
            'active' => (int) ($activeStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function incomingConstraints(string $table): array
    {
        if (array_key_exists($table, $this->incomingConstraintsCache)) {
            return $this->incomingConstraintsCache[$table];
        }

        $sql = 'SELECT
                    kcu.TABLE_NAME AS table_name,
                    kcu.COLUMN_NAME AS column_name,
                    kcu.CONSTRAINT_NAME AS constraint_name,
                    rc.DELETE_RULE AS delete_rule
                FROM information_schema.KEY_COLUMN_USAGE kcu
                INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                   AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                WHERE kcu.REFERENCED_TABLE_SCHEMA = :schema
                  AND kcu.REFERENCED_TABLE_NAME = :table_name
                  AND kcu.REFERENCED_COLUMN_NAME = :column_name
                ORDER BY kcu.TABLE_NAME ASC, kcu.COLUMN_NAME ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'schema' => $this->schemaName(),
            'table_name' => $table,
            'column_name' => 'id',
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = [
                'table_name' => (string) ($row['table_name'] ?? ''),
                'column_name' => (string) ($row['column_name'] ?? ''),
                'constraint_name' => (string) ($row['constraint_name'] ?? ''),
                'delete_rule' => (string) ($row['delete_rule'] ?? 'NO ACTION'),
            ];
        }

        $this->incomingConstraintsCache[$table] = $normalized;

        return $normalized;
    }

    /** @return array<int, string> */
    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->tableColumnsCache)) {
            return $this->tableColumnsCache[$table];
        }

        $stmt = $this->db->prepare(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table'
        );
        $stmt->execute([
            'schema' => $this->schemaName(),
            'table' => $table,
        ]);

        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $column = (string) ($row['COLUMN_NAME'] ?? '');
            if ($column === '') {
                continue;
            }

            $columns[] = $column;
        }

        $this->tableColumnsCache[$table] = $columns;

        return $columns;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table
             LIMIT 1'
        );
        $stmt->execute([
            'schema' => $this->schemaName(),
            'table' => $table,
        ]);

        $exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function schemaName(): string
    {
        if ($this->schemaName !== null) {
            return $this->schemaName;
        }

        $schema = (string) ($this->db->query('SELECT DATABASE()')->fetchColumn() ?: '');
        $this->schemaName = $schema;

        return $schema;
    }

    private function normalizeEntity(string $value): ?string
    {
        $table = trim(mb_strtolower($value));
        if ($table === '') {
            return null;
        }

        return array_key_exists($table, self::ENTITY_CATALOG) ? $table : null;
    }

    private function normalizeDeleteRule(string $rule): string
    {
        $normalized = strtoupper(trim($rule));
        if (!in_array($normalized, ['RESTRICT', 'NO ACTION', 'CASCADE', 'SET NULL'], true)) {
            return 'NO ACTION';
        }

        return $normalized;
    }

    private function impactLabel(string $deleteRule): string
    {
        return match ($deleteRule) {
            'CASCADE' => 'Exclusao em cascata dos vinculados',
            'SET NULL' => 'Vinculo sera limpo (SET NULL)',
            default => 'Bloqueia exclusao enquanto houver vinculos',
        };
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException('Identificador SQL invalido.');
        }

        return '`' . $identifier . '`';
    }
}
