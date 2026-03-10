<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CostItemCatalogRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(
        string $query,
        string $linkage,
        string $reimbursability,
        string $periodicity,
        string $macroCategory,
        string $subcategory,
        string $expenseNature,
        string $predictability,
        string $sort,
        string $dir,
        int $page,
        int $perPage
    ): array {
        $sortMap = [
            'cost_code' => 'c.cost_code',
            'name' => 'c.name',
            'macro_category' => 'c.macro_category',
            'subcategory' => 'c.subcategory',
            'expense_nature' => 'c.expense_nature',
            'reimbursability' => 'c.reimbursability',
            'predictability' => 'c.predictability',
            'linkage_code' => 'c.linkage_code',
            'payment_periodicity' => 'c.payment_periodicity',
            'item_kind' => 'c.is_aggregator',
            'parent' => 'p.name',
            'created_at' => 'c.created_at',
            'updated_at' => 'c.updated_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'c.hierarchy_sort';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE c.deleted_at IS NULL';
        $params = [];

        $normalizedQuery = trim($query);
        if ($normalizedQuery !== '') {
            $where .= ' AND (
                c.name LIKE :q_name
                OR c.type_description LIKE :q_description
                OR CAST(c.cost_code AS CHAR) LIKE :q_code
                OR CAST(c.linkage_code AS CHAR) LIKE :q_linkage
                OR p.name LIKE :q_parent
            )';
            $search = '%' . $normalizedQuery . '%';
            $params['q_name'] = $search;
            $params['q_description'] = $search;
            $params['q_code'] = $search;
            $params['q_linkage'] = $search;
            $params['q_parent'] = $search;
        }

        if (in_array($linkage, ['309', '510'], true)) {
            $where .= ' AND c.linkage_code = :linkage_code';
            $params['linkage_code'] = (int) $linkage;
        }

        if (in_array($reimbursability, ['reembolsavel', 'parcialmente_reembolsavel', 'nao_reembolsavel'], true)) {
            $where .= ' AND c.reimbursability = :reimbursability';
            $params['reimbursability'] = $reimbursability;
        }

        if (in_array($periodicity, ['mensal', 'anual', 'eventual', 'unico'], true)) {
            $where .= ' AND c.payment_periodicity = :payment_periodicity';
            $params['payment_periodicity'] = $periodicity;
        }

        if ($macroCategory !== '') {
            $where .= ' AND c.macro_category = :macro_category';
            $params['macro_category'] = $macroCategory;
        }

        if ($subcategory !== '') {
            $where .= ' AND c.subcategory = :subcategory';
            $params['subcategory'] = $subcategory;
        }

        if (in_array($expenseNature, ['remuneratoria', 'indenizatoria', 'encargos', 'provisoes'], true)) {
            $where .= ' AND c.expense_nature = :expense_nature';
            $params['expense_nature'] = $expenseNature;
        }

        if (in_array($predictability, ['fixa', 'variavel', 'eventual'], true)) {
            $where .= ' AND c.predictability = :predictability';
            $params['predictability'] = $predictability;
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM cost_item_catalog c
             LEFT JOIN cost_item_catalog p ON p.id = c.parent_cost_item_id
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                c.id,
                c.parent_cost_item_id,
                c.is_aggregator,
                c.hierarchy_sort,
                c.cost_code,
                c.name,
                c.type_description,
                c.macro_category,
                c.subcategory,
                c.expense_nature,
                c.calculation_base,
                c.charge_incidence,
                c.reimbursability,
                c.predictability,
                c.linkage_code,
                c.is_reimbursable,
                c.payment_periodicity,
                c.created_at,
                c.updated_at,
                p.name AS parent_name
            FROM cost_item_catalog c
            LEFT JOIN cost_item_catalog p ON p.id = c.parent_cost_item_id
            {$where}
            ORDER BY {$sortColumn} {$direction}, c.is_aggregator DESC, c.hierarchy_sort ASC, c.id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === 'linkage_code') {
                $stmt->bindValue(':' . $key, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                c.id,
                c.parent_cost_item_id,
                c.is_aggregator,
                c.hierarchy_sort,
                c.cost_code,
                c.name,
                c.type_description,
                c.macro_category,
                c.subcategory,
                c.expense_nature,
                c.calculation_base,
                c.charge_incidence,
                c.reimbursability,
                c.predictability,
                c.linkage_code,
                c.is_reimbursable,
                c.payment_periodicity,
                c.created_at,
                c.updated_at,
                p.name AS parent_name
             FROM cost_item_catalog c
             LEFT JOIN cost_item_catalog p ON p.id = c.parent_cost_item_id
             WHERE c.id = :id
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findActiveById(int $id): ?array
    {
        return $this->findById($id);
    }

    /** @return array<string, mixed>|null */
    public function findActiveAggregatorById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                parent_cost_item_id,
                is_aggregator,
                hierarchy_sort,
                cost_code,
                name,
                type_description,
                macro_category,
                subcategory,
                expense_nature,
                calculation_base,
                charge_incidence,
                reimbursability,
                predictability,
                linkage_code,
                is_reimbursable,
                payment_periodicity,
                created_at,
                updated_at
             FROM cost_item_catalog
             WHERE id = :id
               AND is_aggregator = 1
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeAggregators(): array
    {
        $stmt = $this->db->query(
            'SELECT
                id,
                cost_code,
                name,
                macro_category,
                subcategory,
                expense_nature,
                reimbursability,
                predictability,
                linkage_code,
                payment_periodicity,
                hierarchy_sort
             FROM cost_item_catalog
             WHERE is_aggregator = 1
               AND deleted_at IS NULL
             ORDER BY hierarchy_sort ASC, name ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    public function countActiveAggregators(): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*) AS total
             FROM cost_item_catalog
             WHERE is_aggregator = 1
               AND deleted_at IS NULL'
        );

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function activeChildrenCount(int $parentId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM cost_item_catalog
             WHERE parent_cost_item_id = :parent_id
               AND is_aggregator = 0
               AND deleted_at IS NULL'
        );
        $stmt->execute(['parent_id' => $parentId]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function combinationExists(
        int $costCode,
        string $name,
        int $linkageCode,
        string $reimbursability,
        string $paymentPeriodicity,
        ?int $ignoreId = null
    ): bool {
        $sql = 'SELECT id
                FROM cost_item_catalog
                WHERE deleted_at IS NULL
                  AND (
                    cost_code = :cost_code
                    OR (
                        name = :name
                        AND linkage_code = :linkage_code
                        AND reimbursability = :reimbursability
                        AND payment_periodicity = :payment_periodicity
                    )
                  )';

        $params = [
            'cost_code' => $costCode,
            'name' => $name,
            'linkage_code' => $linkageCode,
            'reimbursability' => $reimbursability,
            'payment_periodicity' => $paymentPeriodicity,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cost_item_catalog (
                parent_cost_item_id,
                is_aggregator,
                hierarchy_sort,
                cost_code,
                name,
                type_description,
                macro_category,
                subcategory,
                expense_nature,
                calculation_base,
                charge_incidence,
                reimbursability,
                predictability,
                linkage_code,
                is_reimbursable,
                payment_periodicity,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :parent_cost_item_id,
                :is_aggregator,
                :hierarchy_sort,
                :cost_code,
                :name,
                :type_description,
                :macro_category,
                :subcategory,
                :expense_nature,
                :calculation_base,
                :charge_incidence,
                :reimbursability,
                :predictability,
                :linkage_code,
                :is_reimbursable,
                :payment_periodicity,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )'
        );

        $stmt->execute([
            'parent_cost_item_id' => $data['parent_cost_item_id'],
            'is_aggregator' => $data['is_aggregator'],
            'hierarchy_sort' => $data['hierarchy_sort'],
            'cost_code' => $data['cost_code'],
            'name' => $data['name'],
            'type_description' => $data['type_description'],
            'macro_category' => $data['macro_category'],
            'subcategory' => $data['subcategory'],
            'expense_nature' => $data['expense_nature'],
            'calculation_base' => $data['calculation_base'],
            'charge_incidence' => $data['charge_incidence'],
            'reimbursability' => $data['reimbursability'],
            'predictability' => $data['predictability'],
            'linkage_code' => $data['linkage_code'],
            'is_reimbursable' => $data['is_reimbursable'],
            'payment_periodicity' => $data['payment_periodicity'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_item_catalog
             SET
                parent_cost_item_id = :parent_cost_item_id,
                is_aggregator = :is_aggregator,
                hierarchy_sort = :hierarchy_sort,
                cost_code = :cost_code,
                name = :name,
                type_description = :type_description,
                macro_category = :macro_category,
                subcategory = :subcategory,
                expense_nature = :expense_nature,
                calculation_base = :calculation_base,
                charge_incidence = :charge_incidence,
                reimbursability = :reimbursability,
                predictability = :predictability,
                linkage_code = :linkage_code,
                is_reimbursable = :is_reimbursable,
                payment_periodicity = :payment_periodicity,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'parent_cost_item_id' => $data['parent_cost_item_id'],
            'is_aggregator' => $data['is_aggregator'],
            'hierarchy_sort' => $data['hierarchy_sort'],
            'cost_code' => $data['cost_code'],
            'name' => $data['name'],
            'type_description' => $data['type_description'],
            'macro_category' => $data['macro_category'],
            'subcategory' => $data['subcategory'],
            'expense_nature' => $data['expense_nature'],
            'calculation_base' => $data['calculation_base'],
            'charge_incidence' => $data['charge_incidence'],
            'reimbursability' => $data['reimbursability'],
            'predictability' => $data['predictability'],
            'linkage_code' => $data['linkage_code'],
            'is_reimbursable' => $data['is_reimbursable'],
            'payment_periodicity' => $data['payment_periodicity'],
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cost_item_catalog
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeList(): array
    {
        $stmt = $this->db->query(
            'SELECT
                c.id,
                c.parent_cost_item_id,
                c.is_aggregator,
                c.hierarchy_sort,
                c.cost_code,
                c.name,
                c.type_description,
                c.macro_category,
                c.subcategory,
                c.expense_nature,
                c.calculation_base,
                c.charge_incidence,
                c.reimbursability,
                c.predictability,
                c.linkage_code,
                c.is_reimbursable,
                c.payment_periodicity,
                p.name AS parent_name,
                p.hierarchy_sort AS parent_hierarchy_sort
             FROM cost_item_catalog c
             LEFT JOIN cost_item_catalog p ON p.id = c.parent_cost_item_id
             WHERE c.deleted_at IS NULL
             ORDER BY
                COALESCE(CASE WHEN c.is_aggregator = 1 THEN c.hierarchy_sort ELSE p.hierarchy_sort END, 9999) ASC,
                c.is_aggregator DESC,
                c.hierarchy_sort ASC,
                c.cost_code ASC,
                c.name ASC,
                c.id ASC'
        );

        return $stmt->fetchAll();
    }
}
