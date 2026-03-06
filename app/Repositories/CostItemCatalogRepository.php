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
        string $reimbursable,
        string $periodicity,
        string $sort,
        string $dir,
        int $page,
        int $perPage
    ): array {
        $sortMap = [
            'name' => 'name',
            'linkage_code' => 'linkage_code',
            'is_reimbursable' => 'is_reimbursable',
            'payment_periodicity' => 'payment_periodicity',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'name';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        $normalizedQuery = trim($query);
        if ($normalizedQuery !== '') {
            $where .= ' AND (name LIKE :q_name OR CAST(linkage_code AS CHAR) LIKE :q_linkage)';
            $search = '%' . $normalizedQuery . '%';
            $params['q_name'] = $search;
            $params['q_linkage'] = $search;
        }

        if (in_array($linkage, ['309', '510'], true)) {
            $where .= ' AND linkage_code = :linkage_code';
            $params['linkage_code'] = (int) $linkage;
        }

        if ($reimbursable === 'reimbursable') {
            $where .= ' AND is_reimbursable = 1';
        } elseif ($reimbursable === 'non_reimbursable') {
            $where .= ' AND is_reimbursable = 0';
        }

        if (in_array($periodicity, ['mensal', 'anual', 'unico'], true)) {
            $where .= ' AND payment_periodicity = :payment_periodicity';
            $params['payment_periodicity'] = $periodicity;
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
                name,
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
                name,
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
        string $name,
        int $linkageCode,
        int $isReimbursable,
        string $paymentPeriodicity,
        ?int $ignoreId = null
    ): bool {
        $sql = 'SELECT id
                FROM cost_item_catalog
                WHERE name = :name
                  AND linkage_code = :linkage_code
                  AND is_reimbursable = :is_reimbursable
                  AND payment_periodicity = :payment_periodicity
                  AND deleted_at IS NULL';
        $params = [
            'name' => $name,
            'linkage_code' => $linkageCode,
            'is_reimbursable' => $isReimbursable,
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
                name,
                linkage_code,
                is_reimbursable,
                payment_periodicity,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :name,
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
            'name' => $data['name'],
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
                name = :name,
                linkage_code = :linkage_code,
                is_reimbursable = :is_reimbursable,
                payment_periodicity = :payment_periodicity,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
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
                name,
                linkage_code,
                is_reimbursable,
                payment_periodicity
             FROM cost_item_catalog
             WHERE deleted_at IS NULL
             ORDER BY linkage_code ASC, name ASC, id ASC'
        );

        return $stmt->fetchAll();
    }
}
