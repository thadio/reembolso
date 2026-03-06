<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentTypeRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(string $query, string $status, string $sort, string $dir, int $page, int $perPage): array
    {
        $sortMap = [
            'name' => 'name',
            'is_active' => 'is_active',
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'name';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE 1 = 1';
        $params = [];

        $normalizedQuery = trim($query);
        if ($normalizedQuery !== '') {
            $where .= ' AND (name LIKE :q_name OR description LIKE :q_desc)';
            $search = '%' . $normalizedQuery . '%';
            $params['q_name'] = $search;
            $params['q_desc'] = $search;
        }

        if ($status === 'active') {
            $where .= ' AND is_active = 1';
        } elseif ($status === 'inactive') {
            $where .= ' AND is_active = 0';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM document_types {$where}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                id,
                name,
                description,
                is_active,
                created_at,
                updated_at
            FROM document_types
            {$where}
            ORDER BY {$sortColumn} {$direction}, id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
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
                description,
                is_active,
                created_at,
                updated_at
             FROM document_types
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM document_types WHERE name = :name';
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

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO document_types (
                name,
                description,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :name,
                :description,
                :is_active,
                NOW(),
                NOW()
             )'
        );
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'],
            'is_active' => $data['is_active'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE document_types
             SET name = :name,
                 description = :description,
                 is_active = :is_active,
                 updated_at = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'],
            'is_active' => $data['is_active'],
        ]);
    }

    public function setActive(int $id, bool $isActive): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE document_types
             SET is_active = :is_active,
                 updated_at = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }
}
