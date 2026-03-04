<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MteDestinationRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $sort, string $dir, int $page, int $perPage): array
    {
        $sortMap = [
            'name' => 'name',
            'code' => 'code',
            'created_at' => 'created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'name';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if ($query !== '') {
            $where .= ' AND (name LIKE :query_name OR code LIKE :query_code)';
            $search = '%' . $query . '%';
            $params['query_name'] = $search;
            $params['query_code'] = $search;
        }

        $countSql = "SELECT COUNT(*) AS total FROM mte_destinations {$where}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listSql = "
            SELECT
                id,
                name,
                code,
                created_at
            FROM mte_destinations
            {$where}
            ORDER BY {$sortColumn} {$direction}, id ASC
            LIMIT :limit OFFSET :offset
        ";

        $listStmt = $this->db->prepare($listSql);
        foreach ($params as $key => $value) {
            $listStmt->bindValue(':' . $key, $value);
        }
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();

        return [
            'items' => $listStmt->fetchAll(),
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
                code,
                notes,
                created_at,
                updated_at
             FROM mte_destinations
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $destination = $stmt->fetch();

        return $destination === false ? null : $destination;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO mte_destinations (
                name,
                code,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :code,
                :notes,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'name' => $data['name'],
            'code' => $data['code'],
            'notes' => $data['notes'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE mte_destinations
             SET
                name = :name,
                code = :code,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'code' => $data['code'],
            'notes' => $data['notes'],
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE mte_destinations
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM mte_destinations WHERE name = :name AND deleted_at IS NULL';
        $params = ['name' => $name];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM mte_destinations WHERE code = :code AND deleted_at IS NULL';
        $params = ['code' => $code];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeList(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, code
             FROM mte_destinations
             WHERE deleted_at IS NULL
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }
}
