<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LgpdRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $data */
    public function createSensitiveAccessLog(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sensitive_access_logs (
                entity,
                entity_id,
                action,
                sensitivity,
                subject_person_id,
                subject_label,
                context_path,
                metadata,
                user_id,
                ip,
                user_agent,
                created_at
            ) VALUES (
                :entity,
                :entity_id,
                :action,
                :sensitivity,
                :subject_person_id,
                :subject_label,
                :context_path,
                :metadata,
                :user_id,
                :ip,
                :user_agent,
                NOW()
            )'
        );

        $stmt->execute([
            'entity' => $data['entity'],
            'entity_id' => $data['entity_id'],
            'action' => $data['action'],
            'sensitivity' => $data['sensitivity'],
            'subject_person_id' => $data['subject_person_id'],
            'subject_label' => $data['subject_label'],
            'context_path' => $data['context_path'],
            'metadata' => $data['metadata'],
            'user_id' => $data['user_id'],
            'ip' => $data['ip'],
            'user_agent' => $data['user_agent'],
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginateSensitiveAccess(array $filters, int $page, int $perPage): array
    {
        $sortMap = [
            'created_at' => 'sal.created_at',
            'user' => 'u.name',
            'action' => 'sal.action',
            'sensitivity' => 'sal.sensitivity',
            'entity' => 'sal.entity',
        ];

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = (string) ($filters['dir'] ?? 'desc');
        $sortColumn = $sortMap[$sort] ?? 'sal.created_at';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [];
        $where = $this->buildSensitiveAccessWhere($filters, $params);

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM sensitive_access_logs sal
             LEFT JOIN users u ON u.id = sal.user_id
             LEFT JOIN people p ON p.id = sal.subject_person_id
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db->prepare(
            "SELECT
                sal.id,
                sal.entity,
                sal.entity_id,
                sal.action,
                sal.sensitivity,
                sal.subject_person_id,
                sal.subject_label,
                sal.context_path,
                sal.metadata,
                sal.user_id,
                sal.ip,
                sal.user_agent,
                sal.created_at,
                u.name AS user_name,
                u.email AS user_email,
                p.name AS person_name
             FROM sensitive_access_logs sal
             LEFT JOIN users u ON u.id = sal.user_id
             LEFT JOIN people p ON p.id = sal.subject_person_id
             {$where}
             ORDER BY {$sortColumn} {$direction}, sal.id DESC
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

    /**
     * @param array<string, mixed> $filters
     * @return array{total: int, cpf_access: int, document_access: int, distinct_users: int, last_24h: int}
     */
    public function summarySensitiveAccess(array $filters): array
    {
        $params = [];
        $where = $this->buildSensitiveAccessWhere($filters, $params);

        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN sal.sensitivity = 'cpf' THEN 1 ELSE 0 END) AS cpf_access,
                SUM(CASE WHEN sal.sensitivity <> 'cpf' THEN 1 ELSE 0 END) AS document_access,
                COUNT(DISTINCT sal.user_id) AS distinct_users,
                SUM(CASE WHEN sal.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS last_24h
             FROM sensitive_access_logs sal
             LEFT JOIN users u ON u.id = sal.user_id
             LEFT JOIN people p ON p.id = sal.subject_person_id
             {$where}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'cpf_access' => (int) ($row['cpf_access'] ?? 0),
            'document_access' => (int) ($row['document_access'] ?? 0),
            'distinct_users' => (int) ($row['distinct_users'] ?? 0),
            'last_24h' => (int) ($row['last_24h'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function actionOptions(): array
    {
        $stmt = $this->db->query(
            'SELECT action, COUNT(*) AS total
             FROM sensitive_access_logs
             GROUP BY action
             ORDER BY action ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function sensitivityOptions(): array
    {
        $stmt = $this->db->query(
            'SELECT sensitivity, COUNT(*) AS total
             FROM sensitive_access_logs
             GROUP BY sensitivity
             ORDER BY sensitivity ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function usersForFilter(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email
             FROM users
             WHERE deleted_at IS NULL
             ORDER BY name ASC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function policies(): array
    {
        $stmt = $this->db->query(
            'SELECT
                id,
                policy_key,
                policy_label,
                description,
                retention_days,
                anonymize_after_days,
                supports_anonymization,
                is_active,
                created_by,
                updated_by,
                created_at,
                updated_at
             FROM lgpd_retention_policies
             WHERE deleted_at IS NULL
             ORDER BY policy_key ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findPolicyByKey(string $policyKey): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                policy_key,
                policy_label,
                description,
                retention_days,
                anonymize_after_days,
                supports_anonymization,
                is_active,
                created_by,
                updated_by,
                created_at,
                updated_at
             FROM lgpd_retention_policies
             WHERE policy_key = :policy_key
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(['policy_key' => $policyKey]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function upsertPolicy(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO lgpd_retention_policies (
                policy_key,
                policy_label,
                description,
                retention_days,
                anonymize_after_days,
                supports_anonymization,
                is_active,
                created_by,
                updated_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :policy_key,
                :policy_label,
                :description,
                :retention_days,
                :anonymize_after_days,
                :supports_anonymization,
                :is_active,
                :created_by,
                :updated_by,
                NOW(),
                NOW(),
                NULL
             )
             ON DUPLICATE KEY UPDATE
                policy_label = VALUES(policy_label),
                description = VALUES(description),
                retention_days = VALUES(retention_days),
                anonymize_after_days = VALUES(anonymize_after_days),
                supports_anonymization = VALUES(supports_anonymization),
                is_active = VALUES(is_active),
                updated_by = VALUES(updated_by),
                updated_at = NOW(),
                deleted_at = NULL'
        );

        $stmt->execute([
            'policy_key' => $data['policy_key'],
            'policy_label' => $data['policy_label'],
            'description' => $data['description'],
            'retention_days' => $data['retention_days'],
            'anonymize_after_days' => $data['anonymize_after_days'],
            'supports_anonymization' => $data['supports_anonymization'],
            'is_active' => $data['is_active'],
            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'],
        ]);
    }

    /** @return array<string, mixed>|null */
    public function latestRetentionRun(): ?array
    {
        $stmt = $this->db->query(
            'SELECT
                id,
                run_mode,
                status,
                summary,
                initiated_by,
                created_at
             FROM lgpd_retention_runs
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function retentionRuns(int $limit = 12): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id,
                r.run_mode,
                r.status,
                r.summary,
                r.initiated_by,
                r.created_at,
                u.name AS user_name
             FROM lgpd_retention_runs r
             LEFT JOIN users u ON u.id = r.initiated_by
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function createRetentionRun(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO lgpd_retention_runs (
                run_mode,
                status,
                summary,
                initiated_by,
                created_at
             ) VALUES (
                :run_mode,
                :status,
                :summary,
                :initiated_by,
                NOW()
             )'
        );

        $stmt->execute([
            'run_mode' => $data['run_mode'],
            'status' => $data['status'],
            'summary' => $data['summary'],
            'initiated_by' => $data['initiated_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function countSensitiveAccessOlderThan(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM sensitive_access_logs
             WHERE created_at < :cutoff'
        );

        $stmt->execute(['cutoff' => $cutoff]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function purgeSensitiveAccessOlderThan(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM sensitive_access_logs
             WHERE created_at < :cutoff'
        );

        $stmt->execute(['cutoff' => $cutoff]);

        return $stmt->rowCount();
    }

    public function countAuditLogOlderThan(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM audit_log
             WHERE created_at < :cutoff'
        );

        $stmt->execute(['cutoff' => $cutoff]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function purgeAuditLogOlderThan(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM audit_log
             WHERE created_at < :cutoff'
        );

        $stmt->execute(['cutoff' => $cutoff]);

        return $stmt->rowCount();
    }

    public function countPeopleForAnonymization(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM people
             WHERE deleted_at IS NOT NULL
               AND deleted_at <= :cutoff
               AND (
                    cpf <> :anonymized_cpf
                    OR email IS NOT NULL
                    OR phone IS NOT NULL
                    OR birth_date IS NOT NULL
                    OR tags IS NOT NULL
                    OR notes IS NOT NULL
                    OR name NOT LIKE :anonymized_name_like
                    OR sei_process_number IS NULL
                    OR sei_process_number NOT LIKE :anonymized_sei_like
               )'
        );

        $stmt->execute([
            'cutoff' => $cutoff,
            'anonymized_cpf' => '000.000.000-00',
            'anonymized_name_like' => 'Titular anonimizado #%',
            'anonymized_sei_like' => 'anon-%',
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function anonymizePeople(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'UPDATE people
             SET
                name = CONCAT("Titular anonimizado #", id),
                cpf = :anonymized_cpf,
                birth_date = NULL,
                email = NULL,
                phone = NULL,
                sei_process_number = CONCAT("anon-", id),
                tags = NULL,
                notes = NULL,
                updated_at = NOW()
             WHERE deleted_at IS NOT NULL
               AND deleted_at <= :cutoff
               AND (
                    cpf <> :anonymized_cpf
                    OR email IS NOT NULL
                    OR phone IS NOT NULL
                    OR birth_date IS NOT NULL
                    OR tags IS NOT NULL
                    OR notes IS NOT NULL
                    OR name NOT LIKE :anonymized_name_like
                    OR sei_process_number IS NULL
                    OR sei_process_number NOT LIKE :anonymized_sei_like
               )'
        );

        $stmt->execute([
            'cutoff' => $cutoff,
            'anonymized_cpf' => '000.000.000-00',
            'anonymized_name_like' => 'Titular anonimizado #%',
            'anonymized_sei_like' => 'anon-%',
        ]);

        return $stmt->rowCount();
    }

    public function countUsersForAnonymization(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM users
             WHERE deleted_at IS NOT NULL
               AND deleted_at <= :cutoff
               AND (
                    name NOT LIKE :anonymized_name_like
                    OR email NOT LIKE :anonymized_email_like
                    OR cpf IS NOT NULL
                    OR password_hash <> :anonymized_password
                    OR last_login_at IS NOT NULL
               )'
        );

        $stmt->execute([
            'cutoff' => $cutoff,
            'anonymized_name_like' => 'Usuario anonimizado #%',
            'anonymized_email_like' => 'anon-user-%@anon.local',
            'anonymized_password' => '!anonymized!',
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function anonymizeUsers(string $cutoff): int
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET
                name = CONCAT("Usuario anonimizado #", id),
                email = CONCAT("anon-user-", id, "@anon.local"),
                cpf = NULL,
                password_hash = :anonymized_password,
                last_login_at = NULL,
                updated_at = NOW()
             WHERE deleted_at IS NOT NULL
               AND deleted_at <= :cutoff
               AND (
                    name NOT LIKE :anonymized_name_like
                    OR email NOT LIKE :anonymized_email_like
                    OR cpf IS NOT NULL
                    OR password_hash <> :anonymized_password
                    OR last_login_at IS NOT NULL
               )'
        );

        $stmt->execute([
            'cutoff' => $cutoff,
            'anonymized_name_like' => 'Usuario anonimizado #%',
            'anonymized_email_like' => 'anon-user-%@anon.local',
            'anonymized_password' => '!anonymized!',
        ]);

        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
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
     * @return array<int, array<string, mixed>>
     */
    public function sensitiveAccessForExport(array $filters, int $limit = 5000): array
    {
        $params = [];
        $where = $this->buildSensitiveAccessWhere($filters, $params);

        $stmt = $this->db->prepare(
            "SELECT
                sal.id,
                sal.created_at,
                sal.action,
                sal.sensitivity,
                sal.entity,
                sal.entity_id,
                sal.subject_person_id,
                sal.subject_label,
                sal.context_path,
                sal.ip,
                sal.user_agent,
                sal.metadata,
                u.name AS user_name,
                u.email AS user_email
             FROM sensitive_access_logs sal
             LEFT JOIN users u ON u.id = sal.user_id
             LEFT JOIN people p ON p.id = sal.subject_person_id
             {$where}
             ORDER BY sal.created_at DESC, sal.id DESC
             LIMIT :limit"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(10000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     */
    private function buildSensitiveAccessWhere(array $filters, array &$params): string
    {
        $where = 'WHERE 1=1';

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (
                sal.action LIKE :q_action
                OR sal.entity LIKE :q_entity
                OR sal.subject_label LIKE :q_subject
                OR sal.context_path LIKE :q_path
                OR sal.ip LIKE :q_ip
                OR u.name LIKE :q_user_name
                OR u.email LIKE :q_user_email
                OR p.name LIKE :q_person_name
            )';
            $params['q_action'] = '%' . $q . '%';
            $params['q_entity'] = '%' . $q . '%';
            $params['q_subject'] = '%' . $q . '%';
            $params['q_path'] = '%' . $q . '%';
            $params['q_ip'] = '%' . $q . '%';
            $params['q_user_name'] = '%' . $q . '%';
            $params['q_user_email'] = '%' . $q . '%';
            $params['q_person_name'] = '%' . $q . '%';
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where .= ' AND sal.action = :action';
            $params['action'] = $action;
        }

        $sensitivity = trim((string) ($filters['sensitivity'] ?? ''));
        if ($sensitivity !== '') {
            $where .= ' AND sal.sensitivity = :sensitivity';
            $params['sensitivity'] = $sensitivity;
        }

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where .= ' AND sal.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        if ($fromDate !== '') {
            $where .= ' AND sal.created_at >= :from_date';
            $params['from_date'] = $fromDate . ' 00:00:00';
        }

        $toDate = trim((string) ($filters['to_date'] ?? ''));
        if ($toDate !== '') {
            $where .= ' AND sal.created_at <= :to_date';
            $params['to_date'] = $toDate . ' 23:59:59';
        }

        return $where;
    }
}
