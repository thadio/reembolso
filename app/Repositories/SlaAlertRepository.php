<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SlaAlertRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginatePending(array $filters, int $page, int $perPage): array
    {
        $sortMap = [
            'person_name' => 'p.name',
            'organ_name' => 'o.name',
            'status_order' => 's.sort_order',
            'days_in_status' => $this->daysInStatusExpression(),
            'sla_level' => $this->slaLevelExpression(),
            'updated_at' => 'a.updated_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'status_order');
        $dir = (string) ($filters['dir'] ?? 'asc');
        $sortColumn = $sortMap[$sort] ?? 's.sort_order';
        $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $params = [];
        $where = $this->buildWhere($filters, $params, true);

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db->prepare(
            "SELECT
                a.id AS assignment_id,
                a.person_id,
                p.name AS person_name,
                p.status AS person_status,
                p.sei_process_number,
                o.id AS organ_id,
                o.name AS organ_name,
                s.id AS status_id,
                s.code AS status_code,
                s.label AS status_label,
                s.sort_order AS status_order,
                a.updated_at AS status_changed_at,
                sr.id AS rule_id,
                COALESCE(sr.warning_days, 5) AS warning_days,
                COALESCE(sr.overdue_days, 10) AS overdue_days,
                COALESCE(sr.notify_email, 0) AS notify_email,
                COALESCE(sr.notify_recipients, '') AS notify_recipients,
                COALESCE(sr.is_active, 1) AS rule_is_active,
                {$this->daysInStatusExpression()} AS days_in_status,
                {$this->slaLevelExpression()} AS sla_level
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             {$where}
             ORDER BY {$sortColumn} {$direction}, a.id ASC
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
     * @return array{total: int, no_prazo: int, em_risco: int, vencido: int}
     */
    public function summary(array $filters): array
    {
        $localFilters = $filters;
        $localFilters['severity'] = '';

        $params = [];
        $where = $this->buildWhere($localFilters, $params, false);

        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN {$this->slaLevelExpression()} = 'no_prazo' THEN 1 ELSE 0 END) AS no_prazo,
                SUM(CASE WHEN {$this->slaLevelExpression()} = 'em_risco' THEN 1 ELSE 0 END) AS em_risco,
                SUM(CASE WHEN {$this->slaLevelExpression()} = 'vencido' THEN 1 ELSE 0 END) AS vencido
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             {$where}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'no_prazo' => (int) ($row['no_prazo'] ?? 0),
            'em_risco' => (int) ($row['em_risco'] ?? 0),
            'vencido' => (int) ($row['vencido'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function activeStatusesForSla(): array
    {
        $stmt = $this->db->query(
            'SELECT id, code, label, sort_order, next_action_label
             FROM assignment_statuses
             WHERE is_active = 1
               AND next_action_label IS NOT NULL
             ORDER BY sort_order ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findStatusForSla(string $statusCode): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, label, sort_order, next_action_label
             FROM assignment_statuses
             WHERE code = :code
               AND is_active = 1
               AND next_action_label IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute(['code' => $statusCode]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function rules(): array
    {
        $stmt = $this->db->query(
            'SELECT
                id,
                status_code,
                warning_days,
                overdue_days,
                notify_email,
                notify_recipients,
                is_active,
                created_by,
                created_at,
                updated_at
             FROM sla_rules
             WHERE deleted_at IS NULL
             ORDER BY status_code ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findRuleByStatus(string $statusCode): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                id,
                status_code,
                warning_days,
                overdue_days,
                notify_email,
                notify_recipients,
                is_active,
                created_by,
                created_at,
                updated_at
             FROM sla_rules
             WHERE status_code = :status_code
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['status_code' => $statusCode]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function upsertRule(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sla_rules (
                status_code,
                warning_days,
                overdue_days,
                notify_email,
                notify_recipients,
                is_active,
                created_by,
                created_at,
                updated_at,
                deleted_at
             ) VALUES (
                :status_code,
                :warning_days,
                :overdue_days,
                :notify_email,
                :notify_recipients,
                :is_active,
                :created_by,
                NOW(),
                NOW(),
                NULL
             )
             ON DUPLICATE KEY UPDATE
                warning_days = VALUES(warning_days),
                overdue_days = VALUES(overdue_days),
                notify_email = VALUES(notify_email),
                notify_recipients = VALUES(notify_recipients),
                is_active = VALUES(is_active),
                updated_at = NOW(),
                deleted_at = NULL'
        );

        $stmt->execute([
            'status_code' => $data['status_code'],
            'warning_days' => $data['warning_days'],
            'overdue_days' => $data['overdue_days'],
            'notify_email' => $data['notify_email'],
            'notify_recipients' => $data['notify_recipients'],
            'is_active' => $data['is_active'],
            'created_by' => $data['created_by'],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function notificationCandidates(string $severity = 'all', int $limit = 120): array
    {
        $normalizedSeverity = in_array($severity, ['all', 'em_risco', 'vencido'], true)
            ? $severity
            : 'all';

        $params = [];
        $where = $this->buildWhere(['severity' => $normalizedSeverity], $params, true)
            . ' AND sr.id IS NOT NULL AND sr.is_active = 1 AND sr.notify_email = 1 AND sr.notify_recipients IS NOT NULL AND sr.notify_recipients <> ""';

        if ($normalizedSeverity === 'all') {
            $where .= ' AND ' . $this->slaLevelExpression() . ' IN ("em_risco", "vencido")';
        }

        $stmt = $this->db->prepare(
            "SELECT
                a.id AS assignment_id,
                a.person_id,
                p.name AS person_name,
                p.sei_process_number,
                o.name AS organ_name,
                s.code AS status_code,
                s.label AS status_label,
                sr.id AS rule_id,
                sr.notify_recipients,
                {$this->daysInStatusExpression()} AS days_in_status,
                {$this->slaLevelExpression()} AS sla_level
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             {$where}
             ORDER BY {$this->daysInStatusExpression()} DESC, a.updated_at ASC
             LIMIT :limit"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function createNotificationLog(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sla_notification_logs (
                rule_id,
                assignment_id,
                person_id,
                status_code,
                severity,
                recipient,
                subject,
                body_preview,
                sent_success,
                response_message,
                sent_by,
                created_at
            ) VALUES (
                :rule_id,
                :assignment_id,
                :person_id,
                :status_code,
                :severity,
                :recipient,
                :subject,
                :body_preview,
                :sent_success,
                :response_message,
                :sent_by,
                NOW()
            )'
        );

        $stmt->execute([
            'rule_id' => $data['rule_id'],
            'assignment_id' => $data['assignment_id'],
            'person_id' => $data['person_id'],
            'status_code' => $data['status_code'],
            'severity' => $data['severity'],
            'recipient' => $data['recipient'],
            'subject' => $data['subject'],
            'body_preview' => $data['body_preview'],
            'sent_success' => $data['sent_success'],
            'response_message' => $data['response_message'],
            'sent_by' => $data['sent_by'],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recentNotificationLogs(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                l.id,
                l.assignment_id,
                l.person_id,
                l.status_code,
                l.severity,
                l.recipient,
                l.subject,
                l.sent_success,
                l.response_message,
                l.created_at,
                p.name AS person_name,
                o.name AS organ_name,
                u.name AS sent_by_name
             FROM sla_notification_logs l
             INNER JOIN people p ON p.id = l.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN users u ON u.id = l.sent_by
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function daysInStatusExpression(): string
    {
        return 'TIMESTAMPDIFF(DAY, DATE(a.updated_at), CURDATE())';
    }

    private function slaLevelExpression(): string
    {
        return sprintf(
            'CASE
                WHEN %s >= COALESCE(sr.overdue_days, 10) THEN "vencido"
                WHEN %s >= COALESCE(sr.warning_days, 5) THEN "em_risco"
                ELSE "no_prazo"
             END',
            $this->daysInStatusExpression(),
            $this->daysInStatusExpression()
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     */
    private function buildWhere(array $filters, array &$params, bool $applySeverity): string
    {
        $where = 'WHERE a.deleted_at IS NULL
            AND p.deleted_at IS NULL
            AND s.is_active = 1
            AND s.next_action_label IS NOT NULL
            AND (sr.id IS NULL OR sr.is_active = 1)';

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                p.name LIKE :q_person
                OR o.name LIKE :q_organ
                OR p.sei_process_number LIKE :q_process
                OR p.cpf LIKE :q_cpf
            )';
            $search = '%' . $query . '%';
            $params['q_person'] = $search;
            $params['q_organ'] = $search;
            $params['q_process'] = $search;
            $params['q_cpf'] = $search;
        }

        $statusCode = trim((string) ($filters['status_code'] ?? ''));
        if ($statusCode !== '') {
            $where .= ' AND s.code = :status_code';
            $params['status_code'] = $statusCode;
        }

        if ($applySeverity) {
            $severity = trim((string) ($filters['severity'] ?? ''));
            if (in_array($severity, ['no_prazo', 'em_risco', 'vencido'], true)) {
                $where .= ' AND ' . $this->slaLevelExpression() . ' = :severity';
                $params['severity'] = $severity;
            }
        }

        return $where;
    }
}
