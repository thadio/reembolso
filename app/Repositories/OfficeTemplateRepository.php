<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OfficeTemplateRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginateTemplates(array $filters, int $page, int $perPage): array
    {
        $sortMap = [
            'name' => 't.name',
            'template_key' => 't.template_key',
            'template_type' => 't.template_type',
            'updated_at' => 't.updated_at',
            'created_at' => 't.created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'updated_at');
        $dir = (string) ($filters['dir'] ?? 'desc');
        $sortColumn = $sortMap[$sort] ?? 't.updated_at';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where = 'WHERE t.deleted_at IS NULL';
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                t.name LIKE :q_name
                OR t.template_key LIKE :q_key
                OR t.description LIKE :q_description
                OR ov.subject LIKE :q_subject
            )';
            $search = '%' . $query . '%';
            $params['q_name'] = $search;
            $params['q_key'] = $search;
            $params['q_description'] = $search;
            $params['q_subject'] = $search;
        }

        $templateType = trim((string) ($filters['template_type'] ?? ''));
        if ($templateType !== '') {
            $where .= ' AND t.template_type = :template_type';
            $params['template_type'] = $templateType;
        }

        $isActiveRaw = trim((string) ($filters['is_active'] ?? ''));
        if ($isActiveRaw === '1' || $isActiveRaw === '0') {
            $where .= ' AND t.is_active = :is_active';
            $params['is_active'] = (int) $isActiveRaw;
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM office_templates t
             LEFT JOIN office_template_versions ov
               ON ov.template_id = t.id
              AND ov.is_active = 1
              AND ov.deleted_at IS NULL
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db->prepare(
            "SELECT
                t.id,
                t.template_key,
                t.name,
                t.template_type,
                t.description,
                t.is_active,
                t.created_by,
                t.created_at,
                t.updated_at,
                ov.id AS active_version_id,
                ov.version_number AS active_version_number,
                ov.subject AS active_subject,
                u.name AS created_by_name,
                (
                    SELECT COUNT(*)
                    FROM office_template_versions vv
                    WHERE vv.template_id = t.id
                      AND vv.deleted_at IS NULL
                ) AS versions_count,
                (
                    SELECT COUNT(*)
                    FROM office_documents od
                    WHERE od.template_id = t.id
                      AND od.deleted_at IS NULL
                ) AS generated_count
             FROM office_templates t
             LEFT JOIN office_template_versions ov
               ON ov.template_id = t.id
              AND ov.is_active = 1
              AND ov.deleted_at IS NULL
             LEFT JOIN users u ON u.id = t.created_by
             {$where}
             ORDER BY {$sortColumn} {$direction}, t.id DESC
             LIMIT :limit OFFSET :offset"
        );
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
    public function findTemplateById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                t.id,
                t.template_key,
                t.name,
                t.template_type,
                t.description,
                t.is_active,
                t.created_by,
                t.created_at,
                t.updated_at,
                ov.id AS active_version_id,
                ov.version_number AS active_version_number,
                ov.subject AS active_subject,
                ov.body_html AS active_body_html,
                ov.variables_json AS active_variables_json,
                ov.notes AS active_notes,
                u.name AS created_by_name
             FROM office_templates t
             LEFT JOIN office_template_versions ov
               ON ov.template_id = t.id
              AND ov.is_active = 1
              AND ov.deleted_at IS NULL
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.id = :id
               AND t.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function versionsByTemplate(int $templateId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                v.id,
                v.template_id,
                v.version_number,
                v.subject,
                v.body_html,
                v.variables_json,
                v.notes,
                v.is_active,
                v.created_by,
                v.created_at,
                v.updated_at,
                u.name AS created_by_name
             FROM office_template_versions v
             LEFT JOIN users u ON u.id = v.created_by
             WHERE v.template_id = :template_id
               AND v.deleted_at IS NULL
             ORDER BY v.version_number DESC, v.id DESC'
        );
        $stmt->execute(['template_id' => $templateId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findVersionById(int $versionId, ?int $templateId = null): ?array
    {
        $sql = 'SELECT
                    id,
                    template_id,
                    version_number,
                    subject,
                    body_html,
                    variables_json,
                    notes,
                    is_active,
                    created_by,
                    created_at,
                    updated_at
                FROM office_template_versions
                WHERE id = :id
                  AND deleted_at IS NULL';
        $params = ['id' => $versionId];

        if ($templateId !== null) {
            $sql .= ' AND template_id = :template_id';
            $params['template_id'] = $templateId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function templateKeyExists(string $templateKey, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id
                FROM office_templates
                WHERE template_key = :template_key
                  AND deleted_at IS NULL
                LIMIT 1';
        $params = ['template_key' => $templateKey];

        if ($ignoreId !== null) {
            $sql = 'SELECT id
                    FROM office_templates
                    WHERE template_key = :template_key
                      AND deleted_at IS NULL
                      AND id <> :id
                    LIMIT 1';
            $params['id'] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function nextVersionNumber(int $templateId): int
    {
        $stmt = $this->db->prepare(
            'SELECT IFNULL(MAX(version_number), 0) + 1 AS next_version
             FROM office_template_versions
             WHERE template_id = :template_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['template_id' => $templateId]);

        return max(1, (int) ($stmt->fetch()['next_version'] ?? 1));
    }

    /** @param array<string, mixed> $data */
    public function createTemplate(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO office_templates (
                template_key,
                name,
                template_type,
                description,
                is_active,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :template_key,
                :name,
                :template_type,
                :description,
                :is_active,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );
        $stmt->execute([
            'template_key' => $data['template_key'],
            'name' => $data['name'],
            'template_type' => $data['template_type'],
            'description' => $data['description'],
            'is_active' => $data['is_active'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateTemplate(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE office_templates
             SET
                template_key = :template_key,
                name = :name,
                template_type = :template_type,
                description = :description,
                is_active = :is_active,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'id' => $id,
            'template_key' => $data['template_key'],
            'name' => $data['name'],
            'template_type' => $data['template_type'],
            'description' => $data['description'],
            'is_active' => $data['is_active'],
        ]);
    }

    public function deactivateVersions(int $templateId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE office_template_versions
             SET is_active = 0, updated_at = NOW()
             WHERE template_id = :template_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['template_id' => $templateId]);
    }

    /** @param array<string, mixed> $data */
    public function createVersion(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO office_template_versions (
                template_id,
                version_number,
                subject,
                body_html,
                variables_json,
                notes,
                is_active,
                created_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :template_id,
                :version_number,
                :subject,
                :body_html,
                :variables_json,
                :notes,
                :is_active,
                :created_by,
                NOW(),
                NOW(),
                NULL
            )'
        );
        $stmt->execute([
            'template_id' => $data['template_id'],
            'version_number' => $data['version_number'],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'variables_json' => $data['variables_json'],
            'notes' => $data['notes'],
            'is_active' => $data['is_active'],
            'created_by' => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function softDeleteTemplate(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE office_templates
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id]);
    }

    public function softDeleteVersionsByTemplate(int $templateId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE office_template_versions
             SET deleted_at = NOW(), updated_at = NOW(), is_active = 0
             WHERE template_id = :template_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['template_id' => $templateId]);
    }

    public function softDeleteDocumentsByTemplate(int $templateId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE office_documents
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE template_id = :template_id
               AND deleted_at IS NULL'
        );

        return $stmt->execute(['template_id' => $templateId]);
    }

    /** @param array<string, mixed> $data */
    public function createDocument(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO office_documents (
                template_id,
                template_version_id,
                person_id,
                organ_id,
                rendered_subject,
                rendered_html,
                context_json,
                generated_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :template_id,
                :template_version_id,
                :person_id,
                :organ_id,
                :rendered_subject,
                :rendered_html,
                :context_json,
                :generated_by,
                NOW(),
                NOW(),
                NULL
            )'
        );
        $stmt->execute([
            'template_id' => $data['template_id'],
            'template_version_id' => $data['template_version_id'],
            'person_id' => $data['person_id'],
            'organ_id' => $data['organ_id'],
            'rendered_subject' => $data['rendered_subject'],
            'rendered_html' => $data['rendered_html'],
            'context_json' => $data['context_json'],
            'generated_by' => $data['generated_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findDocumentById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.template_id,
                d.template_version_id,
                d.person_id,
                d.organ_id,
                d.rendered_subject,
                d.rendered_html,
                d.context_json,
                d.generated_by,
                d.created_at,
                d.updated_at,
                t.template_key,
                t.name AS template_name,
                t.template_type,
                v.version_number,
                p.name AS person_name,
                o.name AS organ_name,
                u.name AS generated_by_name
             FROM office_documents d
             INNER JOIN office_templates t ON t.id = d.template_id
             INNER JOIN office_template_versions v ON v.id = d.template_version_id
             LEFT JOIN people p ON p.id = d.person_id
             LEFT JOIN organs o ON o.id = d.organ_id
             LEFT JOIN users u ON u.id = d.generated_by
             WHERE d.id = :id
               AND d.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function documentsByTemplate(int $templateId, int $limit = 60): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.template_id,
                d.template_version_id,
                d.person_id,
                d.organ_id,
                d.rendered_subject,
                d.created_at,
                v.version_number,
                p.name AS person_name,
                o.name AS organ_name,
                u.name AS generated_by_name
             FROM office_documents d
             INNER JOIN office_template_versions v ON v.id = d.template_version_id
             LEFT JOIN people p ON p.id = d.person_id
             LEFT JOIN organs o ON o.id = d.organ_id
             LEFT JOIN users u ON u.id = d.generated_by
             WHERE d.template_id = :template_id
               AND d.deleted_at IS NULL
             ORDER BY d.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':template_id', $templateId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(400, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function activePeople(int $limit = 600): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.name,
                p.status,
                p.sei_process_number,
                p.organ_id,
                o.name AS organ_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(1, min(3000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findPersonById(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.name,
                p.cpf,
                p.email,
                p.phone,
                p.status,
                p.sei_process_number,
                p.organ_id,
                o.name AS organ_name
             FROM people p
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE p.id = :id
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function latestCostSummaryByPerson(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                cp.id AS cost_plan_id,
                cp.version_number,
                cp.label,
                IFNULL(SUM(
                    CASE
                        WHEN i.cost_type = "mensal" THEN i.amount
                        WHEN i.cost_type = "anual" THEN (i.amount / 12)
                        WHEN i.cost_type = "unico" THEN i.amount
                        ELSE 0
                    END
                ), 0) AS monthly_total,
                IFNULL(SUM(
                    CASE
                        WHEN i.cost_type = "mensal" THEN (i.amount * 12)
                        WHEN i.cost_type = "anual" THEN i.amount
                        WHEN i.cost_type = "unico" THEN i.amount
                        ELSE 0
                    END
                ), 0) AS annualized_total
             FROM cost_plans cp
             LEFT JOIN cost_plan_items i
               ON i.cost_plan_id = cp.id
              AND i.deleted_at IS NULL
             WHERE cp.person_id = :person_id
               AND cp.is_active = 1
               AND cp.deleted_at IS NULL
             GROUP BY cp.id, cp.version_number, cp.label
             ORDER BY cp.version_number DESC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function latestCdoByPerson(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                c.id,
                c.number,
                c.status,
                c.period_start,
                c.period_end,
                cp.allocated_amount
             FROM cdo_people cp
             INNER JOIN cdos c
               ON c.id = cp.cdo_id
              AND c.deleted_at IS NULL
             WHERE cp.person_id = :person_id
               AND cp.deleted_at IS NULL
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
