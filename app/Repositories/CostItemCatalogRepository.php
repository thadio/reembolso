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
            'cost_code' => 'cost_code',
            'name' => 'name',
            'macro_category' => 'macro_category',
            'subcategory' => 'subcategory',
            'expense_nature' => 'expense_nature',
            'reimbursability' => 'reimbursability',
            'predictability' => 'predictability',
            'linkage_code' => 'linkage_code',
            'payment_periodicity' => 'payment_periodicity',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'cost_code';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        $normalizedQuery = trim($query);
        if ($normalizedQuery !== '') {
            $where .= ' AND (
                name LIKE :q_name
                OR type_description LIKE :q_description
                OR CAST(cost_code AS CHAR) LIKE :q_code
                OR CAST(linkage_code AS CHAR) LIKE :q_linkage
            )';
            $search = '%' . $normalizedQuery . '%';
            $params['q_name'] = $search;
            $params['q_description'] = $search;
            $params['q_code'] = $search;
            $params['q_linkage'] = $search;
        }

        if (in_array($linkage, ['309', '510'], true)) {
            $where .= ' AND linkage_code = :linkage_code';
            $params['linkage_code'] = (int) $linkage;
        }

        if (in_array($reimbursability, ['reembolsavel', 'parcialmente_reembolsavel', 'nao_reembolsavel'], true)) {
            $where .= ' AND reimbursability = :reimbursability';
            $params['reimbursability'] = $reimbursability;
        }

        if (in_array($periodicity, ['mensal', 'anual', 'eventual', 'unico'], true)) {
            $where .= ' AND payment_periodicity = :payment_periodicity';
            $params['payment_periodicity'] = $periodicity;
        }

        if ($macroCategory !== '') {
            $where .= ' AND macro_category = :macro_category';
            $params['macro_category'] = $macroCategory;
        }

        if ($subcategory !== '') {
            $where .= ' AND subcategory = :subcategory';
            $params['subcategory'] = $subcategory;
        }

        if (in_array($expenseNature, ['remuneratoria', 'indenizatoria', 'encargos', 'provisoes'], true)) {
            $where .= ' AND expense_nature = :expense_nature';
            $params['expense_nature'] = $expenseNature;
        }

        if (in_array($predictability, ['fixa', 'variavel', 'eventual'], true)) {
            $where .= ' AND predictability = :predictability';
            $params['predictability'] = $predictability;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM cost_item_catalog {$where}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                id,
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
            {$where}
            ORDER BY {$sortColumn} {$direction}, id ASC
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
                id,
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
               AND deleted_at IS NULL
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
                id,
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
                payment_periodicity
             FROM cost_item_catalog
             WHERE deleted_at IS NULL
             ORDER BY
                CASE WHEN cost_code IS NULL THEN 1 ELSE 0 END ASC,
                macro_category ASC,
                subcategory ASC,
                cost_code ASC,
                name ASC,
                id ASC'
        );

        return $stmt->fetchAll();
    }
}
