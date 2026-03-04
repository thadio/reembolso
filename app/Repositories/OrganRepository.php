<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OrganRepository
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
            'acronym' => 'acronym',
            'cnpj' => 'cnpj',
            'created_at' => 'created_at',
        ];

        $sortColumn = $sortMap[$sort] ?? 'name';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if ($query !== '') {
            $where .= ' AND (name LIKE :query_name OR acronym LIKE :query_acronym OR cnpj LIKE :query_cnpj)';
            $search = '%' . $query . '%';
            $params['query_name'] = $search;
            $params['query_acronym'] = $search;
            $params['query_cnpj'] = $search;
        }

        $countSql = "SELECT COUNT(*) AS total FROM organs {$where}";
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
                acronym,
                cnpj,
                contact_email,
                contact_phone,
                city,
                state,
                created_at
            FROM organs
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
                acronym,
                cnpj,
                contact_name,
                contact_email,
                contact_phone,
                address_line,
                city,
                state,
                zip_code,
                notes,
                created_at,
                updated_at
             FROM organs
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $organ = $stmt->fetch();

        return $organ === false ? null : $organ;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO organs (
                name,
                acronym,
                cnpj,
                contact_name,
                contact_email,
                contact_phone,
                address_line,
                city,
                state,
                zip_code,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :acronym,
                :cnpj,
                :contact_name,
                :contact_email,
                :contact_phone,
                :address_line,
                :city,
                :state,
                :zip_code,
                :notes,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'name' => $data['name'],
            'acronym' => $data['acronym'],
            'cnpj' => $data['cnpj'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip_code' => $data['zip_code'],
            'notes' => $data['notes'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE organs
             SET
                name = :name,
                acronym = :acronym,
                cnpj = :cnpj,
                contact_name = :contact_name,
                contact_email = :contact_email,
                contact_phone = :contact_phone,
                address_line = :address_line,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'acronym' => $data['acronym'],
            'cnpj' => $data['cnpj'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip_code' => $data['zip_code'],
            'notes' => $data['notes'],
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE organs SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute(['id' => $id]);
    }

    public function cnpjExists(string $cnpj, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM organs WHERE cnpj = :cnpj AND deleted_at IS NULL';
        $params = ['cnpj' => $cnpj];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }
}
