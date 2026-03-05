<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReportRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name
             FROM organs
             WHERE deleted_at IS NULL
             ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeStatusesForSla(): array
    {
        $stmt = $this->db->query(
            'SELECT id, code, label, sort_order
             FROM assignment_statuses
             WHERE is_active = 1
               AND next_action_label IS NOT NULL
             ORDER BY sort_order ASC'
        );

        return $stmt->fetchAll();
    }

    /** @return array{total: int, no_prazo: int, em_risco: int, vencido: int, avg_days_in_status: float} */
    public function operationalSummary(array $filters): array
    {
        $params = [];
        $where = $this->buildOperationalWhere($filters, $params, true);

        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "no_prazo" THEN 1 ELSE 0 END) AS no_prazo,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "em_risco" THEN 1 ELSE 0 END) AS em_risco,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "vencido" THEN 1 ELSE 0 END) AS vencido,
                IFNULL(AVG(' . $this->daysInStatusExpression() . '), 0) AS avg_days_in_status
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             ' . $where
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'no_prazo' => (int) ($row['no_prazo'] ?? 0),
            'em_risco' => (int) ($row['em_risco'] ?? 0),
            'vencido' => (int) ($row['vencido'] ?? 0),
            'avg_days_in_status' => (float) ($row['avg_days_in_status'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function operationalBottlenecks(array $filters, int $limit = 8): array
    {
        $params = [];
        $where = $this->buildOperationalWhere($filters, $params, true);

        $stmt = $this->db->prepare(
            'SELECT
                s.code AS status_code,
                s.label AS status_label,
                s.sort_order,
                COUNT(*) AS cases_count,
                IFNULL(AVG(' . $this->daysInStatusExpression() . '), 0) AS avg_days_in_status,
                IFNULL(MAX(' . $this->daysInStatusExpression() . '), 0) AS max_days_in_status,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "vencido" THEN 1 ELSE 0 END) AS vencido_count,
                SUM(CASE WHEN ' . $this->slaLevelExpression() . ' = "em_risco" THEN 1 ELSE 0 END) AS risco_count
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             ' . $where . '
             GROUP BY s.code, s.label, s.sort_order
             ORDER BY avg_days_in_status DESC, cases_count DESC, s.sort_order ASC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(30, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginateOperationalRows(array $filters, int $page, int $perPage): array
    {
        $params = [];
        $where = $this->buildOperationalWhere($filters, $params, true);

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             ' . $where
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        [$sortColumn, $direction] = $this->operationalSort($filters);

        $listStmt = $this->db->prepare(
            'SELECT
                a.id AS assignment_id,
                a.person_id,
                p.name AS person_name,
                p.sei_process_number,
                o.id AS organ_id,
                o.name AS organ_name,
                s.code AS status_code,
                s.label AS status_label,
                s.sort_order,
                a.updated_at AS status_changed_at,
                ' . $this->daysInStatusExpression() . ' AS days_in_status,
                ' . $this->slaLevelExpression() . ' AS sla_level
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             ' . $where . '
             ORDER BY ' . $sortColumn . ' ' . $direction . ', a.id ASC
             LIMIT :limit OFFSET :offset'
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

    /** @return array<int, array<string, mixed>> */
    public function operationalRowsForExport(array $filters, int $limit = 2000): array
    {
        $params = [];
        $where = $this->buildOperationalWhere($filters, $params, true);
        [$sortColumn, $direction] = $this->operationalSort($filters);

        $stmt = $this->db->prepare(
            'SELECT
                a.id AS assignment_id,
                a.person_id,
                p.name AS person_name,
                p.sei_process_number,
                o.id AS organ_id,
                o.name AS organ_name,
                s.code AS status_code,
                s.label AS status_label,
                s.sort_order,
                a.updated_at AS status_changed_at,
                ' . $this->daysInStatusExpression() . ' AS days_in_status,
                ' . $this->slaLevelExpression() . ' AS sla_level
             FROM assignments a
             INNER JOIN people p ON p.id = a.person_id
             INNER JOIN organs o ON o.id = p.organ_id
             INNER JOIN assignment_statuses s ON s.id = a.current_status_id
             LEFT JOIN sla_rules sr ON sr.status_code = s.code AND sr.deleted_at IS NULL
             ' . $where . '
             ORDER BY ' . $sortColumn . ' ' . $direction . ', a.id ASC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(10000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array{summary: array<string, float>, months: array<int, array<string, mixed>>} */
    public function financialDataset(array $filters): array
    {
        $year = (int) ($filters['year'] ?? (int) date('Y'));
        $monthFrom = (int) ($filters['month_from'] ?? 1);
        $monthTo = (int) ($filters['month_to'] ?? 12);
        $organId = (int) ($filters['organ_id'] ?? 0);

        $forecastMap = $this->forecastByMonth($year, $organId);
        $effectiveMap = $this->effectiveByMonth($year, $organId);
        $paidMap = $this->paidByMonth($year, $organId);
        $payableMap = $this->payableByMonth($year, $organId);

        $months = [];
        $forecastTotal = 0.0;
        $effectiveTotal = 0.0;
        $paidTotal = 0.0;
        $payableTotal = 0.0;

        for ($month = $monthFrom; $month <= $monthTo; $month++) {
            $forecast = (float) ($forecastMap[$month] ?? 0.0);
            $effective = (float) ($effectiveMap[$month] ?? 0.0);
            $paid = (float) ($paidMap[$month] ?? 0.0);
            $payable = (float) ($payableMap[$month] ?? 0.0);

            $forecastTotal += $forecast;
            $effectiveTotal += $effective;
            $paidTotal += $paid;
            $payableTotal += $payable;

            $months[] = [
                'month_number' => $month,
                'month_label' => sprintf('%02d/%04d', $month, $year),
                'forecast_amount' => $forecast,
                'effective_amount' => $effective,
                'paid_amount' => $paid,
                'payable_amount' => $payable,
            ];
        }

        $adherencePercent = $forecastTotal > 0
            ? ($effectiveTotal / $forecastTotal) * 100
            : 0.0;

        $paymentCoveragePercent = $effectiveTotal > 0
            ? ($paidTotal / $effectiveTotal) * 100
            : 0.0;

        return [
            'summary' => [
                'forecast_total' => $forecastTotal,
                'effective_total' => $effectiveTotal,
                'paid_total' => $paidTotal,
                'payable_total' => $payableTotal,
                'variance_forecast_effective' => $effectiveTotal - $forecastTotal,
                'adherence_percent' => $adherencePercent,
                'payment_coverage_percent' => $paymentCoveragePercent,
            ],
            'months' => $months,
        ];
    }

    /** @return array{summary: array<string, int|float>, months: array<int, array<string, mixed>>} */
    public function financialStatusDataset(array $filters): array
    {
        $year = (int) ($filters['year'] ?? (int) date('Y'));
        $monthFrom = (int) ($filters['month_from'] ?? 1);
        $monthTo = (int) ($filters['month_to'] ?? 12);
        $organId = (int) ($filters['organ_id'] ?? 0);

        $openMap = $this->openFinancialStatusByMonth($year, $organId);
        $overdueMap = $this->overdueFinancialStatusByMonth($year, $organId);
        $paidMap = $this->paidFinancialStatusByMonth($year, $organId);
        $reconciledMap = $this->reconciledFinancialStatusByMonth($year, $organId);

        $months = [];
        $openCountTotal = 0;
        $openAmountTotal = 0.0;
        $overdueCountTotal = 0;
        $overdueAmountTotal = 0.0;
        $paidCountTotal = 0;
        $paidAmountTotal = 0.0;
        $reconciledCountTotal = 0;
        $reconciledAmountTotal = 0.0;

        for ($month = $monthFrom; $month <= $monthTo; $month++) {
            $openCount = max(0, (int) ($openMap[$month]['count'] ?? 0));
            $openAmount = max(0.0, (float) ($openMap[$month]['amount'] ?? 0.0));
            $overdueCount = max(0, (int) ($overdueMap[$month]['count'] ?? 0));
            $overdueAmount = max(0.0, (float) ($overdueMap[$month]['amount'] ?? 0.0));
            $paidCount = max(0, (int) ($paidMap[$month]['count'] ?? 0));
            $paidAmount = max(0.0, (float) ($paidMap[$month]['amount'] ?? 0.0));
            $reconciledCount = max(0, (int) ($reconciledMap[$month]['count'] ?? 0));
            $reconciledAmount = max(0.0, (float) ($reconciledMap[$month]['amount'] ?? 0.0));

            $openCountTotal += $openCount;
            $openAmountTotal += $openAmount;
            $overdueCountTotal += $overdueCount;
            $overdueAmountTotal += $overdueAmount;
            $paidCountTotal += $paidCount;
            $paidAmountTotal += $paidAmount;
            $reconciledCountTotal += $reconciledCount;
            $reconciledAmountTotal += $reconciledAmount;

            $months[] = [
                'month_number' => $month,
                'month_label' => sprintf('%02d/%04d', $month, $year),
                'open_count' => $openCount,
                'open_amount' => $openAmount,
                'overdue_count' => $overdueCount,
                'overdue_amount' => $overdueAmount,
                'paid_count' => $paidCount,
                'paid_amount' => $paidAmount,
                'reconciled_count' => $reconciledCount,
                'reconciled_amount' => $reconciledAmount,
            ];
        }

        $totalCount = $openCountTotal + $overdueCountTotal + $paidCountTotal;
        $reconciledCoveragePercent = $totalCount > 0
            ? ($reconciledCountTotal / $totalCount) * 100
            : 0.0;

        return [
            'summary' => [
                'open_count' => $openCountTotal,
                'open_amount' => $openAmountTotal,
                'overdue_count' => $overdueCountTotal,
                'overdue_amount' => $overdueAmountTotal,
                'paid_count' => $paidCountTotal,
                'paid_amount' => $paidAmountTotal,
                'reconciled_count' => $reconciledCountTotal,
                'reconciled_amount' => $reconciledAmountTotal,
                'total_count' => $totalCount,
                'reconciled_coverage_percent' => $reconciledCoveragePercent,
            ],
            'months' => $months,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function accountabilityInvoices(array $filters, int $limit = 3000): array
    {
        $where = 'WHERE i.deleted_at IS NULL
            AND i.reference_month BETWEEN :period_start AND :period_end';
        $params = [
            'period_start' => (string) ($filters['period_start'] ?? date('Y-01-01')),
            'period_end' => (string) ($filters['period_end'] ?? date('Y-12-31')),
        ];

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND i.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                i.invoice_number LIKE :q_number
                OR i.title LIKE :q_title
                OR i.digitable_line LIKE :q_digitable
                OR i.reference_code LIKE :q_reference
                OR o.name LIKE :q_organ
            )';
            $search = '%' . $query . '%';
            $params['q_number'] = $search;
            $params['q_title'] = $search;
            $params['q_digitable'] = $search;
            $params['q_reference'] = $search;
            $params['q_organ'] = $search;
        }

        $stmt = $this->db->prepare(
            'SELECT
                i.id,
                i.organ_id,
                o.name AS organ_name,
                i.invoice_number,
                i.title,
                i.reference_month,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                i.status,
                i.pdf_original_name,
                i.pdf_mime_type,
                i.pdf_storage_path,
                i.created_at
             FROM invoices i
             INNER JOIN organs o ON o.id = i.organ_id
             ' . $where . '
             ORDER BY i.reference_month ASC, i.id ASC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(10000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function accountabilityPayments(array $filters, int $limit = 5000): array
    {
        $where = 'WHERE p.deleted_at IS NULL
            AND i.deleted_at IS NULL
            AND p.payment_date BETWEEN :period_start AND :period_end';
        $params = [
            'period_start' => (string) ($filters['period_start'] ?? date('Y-01-01')),
            'period_end' => (string) ($filters['period_end'] ?? date('Y-12-31')),
        ];

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND i.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where .= ' AND (
                i.invoice_number LIKE :q_number
                OR i.title LIKE :q_title
                OR o.name LIKE :q_organ
                OR p.process_reference LIKE :q_process
                OR p.proof_original_name LIKE :q_proof
            )';
            $search = '%' . $query . '%';
            $params['q_number'] = $search;
            $params['q_title'] = $search;
            $params['q_organ'] = $search;
            $params['q_process'] = $search;
            $params['q_proof'] = $search;
        }

        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.invoice_id,
                p.payment_date,
                p.amount,
                p.process_reference,
                p.proof_original_name,
                p.proof_mime_type,
                p.proof_storage_path,
                p.created_at,
                i.organ_id,
                o.name AS organ_name,
                i.invoice_number,
                i.title AS invoice_title,
                i.reference_month AS invoice_reference_month
             FROM payments p
             INNER JOIN invoices i ON i.id = p.invoice_id
             INNER JOIN organs o ON o.id = i.organ_id
             ' . $where . '
             ORDER BY p.payment_date ASC, p.id ASC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', max(1, min(15000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function auditCriticalRows(array $filters, int $limit = 3000): array
    {
        $window = $this->periodWindow($filters);
        $organId = (int) ($filters['organ_id'] ?? 0);
        $query = trim((string) ($filters['q'] ?? ''));

        $where = 'WHERE a.created_at >= :period_start_at
            AND a.created_at < :period_end_at
            AND (
                a.action LIKE "%create%"
                OR a.action LIKE "%update%"
                OR a.action LIKE "%delete%"
                OR a.action LIKE "%approve%"
                OR a.action LIKE "%status%"
                OR a.action LIKE "%export%"
                OR a.action LIKE "%dispatch%"
            )';
        $params = [
            'period_start_at' => $window['start'],
            'period_end_at' => $window['end_exclusive'],
        ];

        if ($query !== '') {
            $where .= ' AND (
                a.entity LIKE :q_entity
                OR a.action LIKE :q_action
                OR u.name LIKE :q_user
                OR a.ip LIKE :q_ip
            )';
            $search = '%' . $query . '%';
            $params['q_entity'] = $search;
            $params['q_action'] = $search;
            $params['q_user'] = $search;
            $params['q_ip'] = $search;
        }

        if ($organId > 0) {
            $where .= ' AND ' . $this->auditScopeByOrganSql('a', $organId);
        }

        $stmt = $this->db->prepare(
            'SELECT
                a.id,
                a.entity,
                a.entity_id,
                a.action,
                a.before_data,
                a.after_data,
                a.metadata,
                a.user_id,
                a.ip,
                a.user_agent,
                a.created_at,
                u.name AS user_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             ' . $where . '
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(12000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function auditSensitiveAccessRows(array $filters, int $limit = 3000): array
    {
        $window = $this->periodWindow($filters);
        $organId = (int) ($filters['organ_id'] ?? 0);
        $query = trim((string) ($filters['q'] ?? ''));

        $where = 'WHERE sal.created_at >= :period_start_at
            AND sal.created_at < :period_end_at';
        $params = [
            'period_start_at' => $window['start'],
            'period_end_at' => $window['end_exclusive'],
        ];

        if ($query !== '') {
            $where .= ' AND (
                sal.action LIKE :q_action
                OR sal.entity LIKE :q_entity
                OR sal.sensitivity LIKE :q_sensitivity
                OR sal.subject_label LIKE :q_subject
                OR u.name LIKE :q_user
                OR sal.ip LIKE :q_ip
            )';
            $search = '%' . $query . '%';
            $params['q_action'] = $search;
            $params['q_entity'] = $search;
            $params['q_sensitivity'] = $search;
            $params['q_subject'] = $search;
            $params['q_user'] = $search;
            $params['q_ip'] = $search;
        }

        if ($organId > 0) {
            $where .= ' AND (
                (p.organ_id = :organ_id_people)
                OR (sal.entity = "organ" AND sal.entity_id = :organ_id_entity)
            )';
            $params['organ_id_people'] = $organId;
            $params['organ_id_entity'] = $organId;
        }

        $stmt = $this->db->prepare(
            'SELECT
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
                p.name AS person_name,
                p.organ_id
             FROM sensitive_access_logs sal
             LEFT JOIN users u ON u.id = sal.user_id
             LEFT JOIN people p ON p.id = sal.subject_person_id
             ' . $where . '
             ORDER BY sal.created_at DESC, sal.id DESC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(12000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function auditOpenPendingRows(array $filters, int $limit = 3000): array
    {
        $window = $this->periodWindow($filters);
        $organId = (int) ($filters['organ_id'] ?? 0);
        $query = trim((string) ($filters['q'] ?? ''));

        $where = 'WHERE pi.deleted_at IS NULL
            AND pi.status = "aberta"
            AND pi.created_at >= :period_start_at
            AND pi.created_at < :period_end_at';
        $params = [
            'period_start_at' => $window['start'],
            'period_end_at' => $window['end_exclusive'],
        ];

        if ($organId > 0) {
            $where .= ' AND p.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        if ($query !== '') {
            $where .= ' AND (
                p.name LIKE :q_person
                OR o.name LIKE :q_organ
                OR pi.pending_type LIKE :q_type
                OR pi.title LIKE :q_title
                OR pi.description LIKE :q_description
            )';
            $search = '%' . $query . '%';
            $params['q_person'] = $search;
            $params['q_organ'] = $search;
            $params['q_type'] = $search;
            $params['q_title'] = $search;
            $params['q_description'] = $search;
        }

        $stmt = $this->db->prepare(
            'SELECT
                pi.id,
                pi.person_id,
                pi.assignment_id,
                pi.pending_type,
                pi.source_key,
                pi.title,
                pi.description,
                pi.severity,
                pi.status,
                pi.due_date,
                pi.created_at,
                p.name AS person_name,
                p.sei_process_number,
                o.id AS organ_id,
                o.name AS organ_name
             FROM analyst_pending_items pi
             INNER JOIN people p ON p.id = pi.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             ' . $where . '
             ORDER BY
                CASE pi.severity WHEN "alta" THEN 3 WHEN "media" THEN 2 WHEN "baixa" THEN 1 ELSE 0 END DESC,
                pi.due_date ASC,
                pi.updated_at DESC,
                pi.id DESC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(12000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function auditUnresolvedDivergenceRows(array $filters, int $limit = 3000): array
    {
        $window = $this->periodWindow($filters);
        $organId = (int) ($filters['organ_id'] ?? 0);
        $query = trim((string) ($filters['q'] ?? ''));

        $where = 'WHERE d.deleted_at IS NULL
            AND d.requires_justification = 1
            AND d.is_resolved = 0
            AND d.created_at >= :period_start_at
            AND d.created_at < :period_end_at';
        $params = [
            'period_start_at' => $window['start'],
            'period_end_at' => $window['end_exclusive'],
        ];

        if ($organId > 0) {
            $where .= ' AND p.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        if ($query !== '') {
            $where .= ' AND (
                p.name LIKE :q_person
                OR o.name LIKE :q_organ
                OR d.match_key LIKE :q_match
                OR d.divergence_type LIKE :q_type
                OR d.justification_text LIKE :q_justification
            )';
            $search = '%' . $query . '%';
            $params['q_person'] = $search;
            $params['q_organ'] = $search;
            $params['q_match'] = $search;
            $params['q_type'] = $search;
            $params['q_justification'] = $search;
        }

        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.reconciliation_id,
                d.cost_mirror_id,
                d.person_id,
                d.match_key,
                d.divergence_type,
                d.severity,
                d.expected_amount,
                d.actual_amount,
                d.difference_amount,
                d.threshold_amount,
                d.requires_justification,
                d.is_resolved,
                d.created_at,
                r.reference_month,
                r.status AS reconciliation_status,
                p.name AS person_name,
                p.sei_process_number,
                o.id AS organ_id,
                o.name AS organ_name
             FROM cost_mirror_divergences d
             INNER JOIN people p ON p.id = d.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             LEFT JOIN cost_mirror_reconciliations r ON r.id = d.reconciliation_id AND r.deleted_at IS NULL
             ' . $where . '
             ORDER BY
                CASE d.severity WHEN "alta" THEN 3 WHEN "media" THEN 2 WHEN "baixa" THEN 1 ELSE 0 END DESC,
                ABS(d.difference_amount) DESC,
                d.id DESC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(12000, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, float> */
    private function forecastByMonth(int $year, int $organId): array
    {
        $yearLiteral = max(2000, min(2100, $year));

        $sql = 'SELECT
                    mm.month_number,
                    IFNULL(SUM(
                        CASE
                            WHEN cpi.cost_type = "mensal"
                                 AND (cpi.start_date IS NULL OR cpi.start_date <= LAST_DAY(STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d")))
                                 AND (cpi.end_date IS NULL OR cpi.end_date >= STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d"))
                            THEN cpi.amount
                            WHEN cpi.cost_type = "anual"
                                 AND (cpi.start_date IS NULL OR cpi.start_date <= LAST_DAY(STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d")))
                                 AND (cpi.end_date IS NULL OR cpi.end_date >= STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d"))
                            THEN cpi.amount / 12
                            WHEN cpi.cost_type = "unico"
                                 AND DATE_FORMAT(COALESCE(cpi.start_date, DATE(cpi.created_at)), "%Y-%m") = DATE_FORMAT(STR_TO_DATE(CONCAT(' . $yearLiteral . ', "-", LPAD(mm.month_number, 2, "0"), "-01"), "%Y-%m-%d"), "%Y-%m")
                            THEN cpi.amount
                            ELSE 0
                        END
                    ), 0) AS total
                FROM (' . $this->monthsSql() . ') mm
                LEFT JOIN cost_plans cp ON cp.deleted_at IS NULL AND cp.is_active = 1
                LEFT JOIN people pe ON pe.id = cp.person_id AND pe.deleted_at IS NULL
                LEFT JOIN cost_plan_items cpi ON cpi.cost_plan_id = cp.id AND cpi.deleted_at IS NULL
                WHERE 1 = 1';

        $params = [];

        if ($organId > 0) {
            $sql .= ' AND pe.organ_id = :organ_id';
            $params['organ_id'] = $organId;
        }

        $sql .= ' GROUP BY mm.month_number';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthTotalsFromRows($stmt->fetchAll());
    }

    /** @return array<int, float> */
    private function effectiveByMonth(int $year, int $organId): array
    {
        $sql = 'SELECT src.month_number, SUM(src.amount) AS total
                FROM (
                    SELECT MONTH(i.reference_month) AS month_number, SUM(i.total_amount) AS amount
                    FROM invoices i
                    WHERE i.deleted_at IS NULL
                      AND i.status <> "cancelado"
                      AND YEAR(i.reference_month) = :year_invoices'
                    . ($organId > 0 ? ' AND i.organ_id = :organ_id_invoices' : '') . '
                    GROUP BY MONTH(i.reference_month)

                    UNION ALL

                    SELECT MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) AS month_number, SUM(r.amount) AS amount
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND YEAR(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) = :year_reimbursement'
                    . ($organId > 0 ? ' AND pe.organ_id = :organ_id_reimbursement' : '') . '
                    GROUP BY MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at)))
                ) src
                GROUP BY src.month_number';

        $params = [
            'year_invoices' => $year,
            'year_reimbursement' => $year,
        ];

        if ($organId > 0) {
            $params['organ_id_invoices'] = $organId;
            $params['organ_id_reimbursement'] = $organId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthTotalsFromRows($stmt->fetchAll());
    }

    /** @return array<int, float> */
    private function paidByMonth(int $year, int $organId): array
    {
        $sql = 'SELECT src.month_number, SUM(src.amount) AS total
                FROM (
                    SELECT MONTH(pmt.payment_date) AS month_number, SUM(pmt.amount) AS amount
                    FROM payments pmt
                    INNER JOIN invoices i ON i.id = pmt.invoice_id AND i.deleted_at IS NULL
                    WHERE pmt.deleted_at IS NULL
                      AND YEAR(pmt.payment_date) = :year_payments'
                    . ($organId > 0 ? ' AND i.organ_id = :organ_id_payments' : '') . '
                    GROUP BY MONTH(pmt.payment_date)

                    UNION ALL

                    SELECT MONTH(COALESCE(DATE(r.paid_at), r.reference_month, DATE(r.created_at))) AS month_number, SUM(r.amount) AS amount
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND r.status = "pago"
                      AND YEAR(COALESCE(DATE(r.paid_at), r.reference_month, DATE(r.created_at))) = :year_reimbursement_paid'
                    . ($organId > 0 ? ' AND pe.organ_id = :organ_id_reimbursement_paid' : '') . '
                    GROUP BY MONTH(COALESCE(DATE(r.paid_at), r.reference_month, DATE(r.created_at)))
                ) src
                GROUP BY src.month_number';

        $params = [
            'year_payments' => $year,
            'year_reimbursement_paid' => $year,
        ];

        if ($organId > 0) {
            $params['organ_id_payments'] = $organId;
            $params['organ_id_reimbursement_paid'] = $organId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthTotalsFromRows($stmt->fetchAll());
    }

    /** @return array<int, float> */
    private function payableByMonth(int $year, int $organId): array
    {
        $sql = 'SELECT src.month_number, SUM(src.amount) AS total
                FROM (
                    SELECT MONTH(i.reference_month) AS month_number, SUM(GREATEST(i.total_amount - i.paid_amount, 0)) AS amount
                    FROM invoices i
                    WHERE i.deleted_at IS NULL
                      AND i.status <> "cancelado"
                      AND YEAR(i.reference_month) = :year_invoices'
                    . ($organId > 0 ? ' AND i.organ_id = :organ_id_invoices' : '') . '
                    GROUP BY MONTH(i.reference_month)

                    UNION ALL

                    SELECT MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) AS month_number, SUM(r.amount) AS amount
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND r.status <> "pago"
                      AND YEAR(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) = :year_reimbursement'
                    . ($organId > 0 ? ' AND pe.organ_id = :organ_id_reimbursement' : '') . '
                    GROUP BY MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at)))
                ) src
                GROUP BY src.month_number';

        $params = [
            'year_invoices' => $year,
            'year_reimbursement' => $year,
        ];

        if ($organId > 0) {
            $params['organ_id_invoices'] = $organId;
            $params['organ_id_reimbursement'] = $organId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthTotalsFromRows($stmt->fetchAll());
    }

    /** @return array<int, array{count: int, amount: float}> */
    private function openFinancialStatusByMonth(int $year, int $organId): array
    {
        $sql = 'SELECT src.month_number, SUM(src.item_count) AS total_count, SUM(src.amount_total) AS total_amount
                FROM (
                    SELECT
                        MONTH(i.reference_month) AS month_number,
                        COUNT(*) AS item_count,
                        IFNULL(SUM(GREATEST(i.total_amount - i.paid_amount, 0)), 0) AS amount_total
                    FROM invoices i
                    WHERE i.deleted_at IS NULL
                      AND i.status IN ("aberto", "pago_parcial")
                      AND (i.due_date IS NULL OR i.due_date >= CURDATE())
                      AND YEAR(i.reference_month) = :year_invoices'
                    . ($organId > 0 ? ' AND i.organ_id = :organ_id_invoices' : '') . '
                    GROUP BY MONTH(i.reference_month)

                    UNION ALL

                    SELECT
                        MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) AS month_number,
                        COUNT(*) AS item_count,
                        IFNULL(SUM(r.amount), 0) AS amount_total
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND r.status = "pendente"
                      AND (r.due_date IS NULL OR r.due_date >= CURDATE())
                      AND YEAR(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) = :year_reimbursement'
                    . ($organId > 0 ? ' AND pe.organ_id = :organ_id_reimbursement' : '') . '
                    GROUP BY MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at)))
                ) src
                GROUP BY src.month_number';

        $params = [
            'year_invoices' => $year,
            'year_reimbursement' => $year,
        ];

        if ($organId > 0) {
            $params['organ_id_invoices'] = $organId;
            $params['organ_id_reimbursement'] = $organId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthStatusTotalsFromRows($stmt->fetchAll());
    }

    /** @return array<int, array{count: int, amount: float}> */
    private function overdueFinancialStatusByMonth(int $year, int $organId): array
    {
        $sql = 'SELECT src.month_number, SUM(src.item_count) AS total_count, SUM(src.amount_total) AS total_amount
                FROM (
                    SELECT
                        MONTH(i.reference_month) AS month_number,
                        COUNT(*) AS item_count,
                        IFNULL(SUM(GREATEST(i.total_amount - i.paid_amount, 0)), 0) AS amount_total
                    FROM invoices i
                    WHERE i.deleted_at IS NULL
                      AND i.status NOT IN ("cancelado", "pago")
                      AND GREATEST(i.total_amount - i.paid_amount, 0) > 0
                      AND (
                        i.status = "vencido"
                        OR (i.due_date IS NOT NULL AND i.due_date < CURDATE())
                      )
                      AND YEAR(i.reference_month) = :year_invoices'
                    . ($organId > 0 ? ' AND i.organ_id = :organ_id_invoices' : '') . '
                    GROUP BY MONTH(i.reference_month)

                    UNION ALL

                    SELECT
                        MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) AS month_number,
                        COUNT(*) AS item_count,
                        IFNULL(SUM(r.amount), 0) AS amount_total
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND r.status = "pendente"
                      AND r.due_date IS NOT NULL
                      AND r.due_date < CURDATE()
                      AND YEAR(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at))) = :year_reimbursement'
                    . ($organId > 0 ? ' AND pe.organ_id = :organ_id_reimbursement' : '') . '
                    GROUP BY MONTH(COALESCE(r.reference_month, DATE(r.due_date), DATE(r.created_at)))
                ) src
                GROUP BY src.month_number';

        $params = [
            'year_invoices' => $year,
            'year_reimbursement' => $year,
        ];

        if ($organId > 0) {
            $params['organ_id_invoices'] = $organId;
            $params['organ_id_reimbursement'] = $organId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthStatusTotalsFromRows($stmt->fetchAll());
    }

    /** @return array<int, array{count: int, amount: float}> */
    private function paidFinancialStatusByMonth(int $year, int $organId): array
    {
        $sql = 'SELECT src.month_number, SUM(src.item_count) AS total_count, SUM(src.amount_total) AS total_amount
                FROM (
                    SELECT
                        MONTH(i.reference_month) AS month_number,
                        COUNT(*) AS item_count,
                        IFNULL(SUM(CASE WHEN i.paid_amount > 0 THEN i.paid_amount ELSE i.total_amount END), 0) AS amount_total
                    FROM invoices i
                    WHERE i.deleted_at IS NULL
                      AND i.status = "pago"
                      AND YEAR(i.reference_month) = :year_invoices'
                    . ($organId > 0 ? ' AND i.organ_id = :organ_id_invoices' : '') . '
                    GROUP BY MONTH(i.reference_month)

                    UNION ALL

                    SELECT
                        MONTH(COALESCE(DATE(r.paid_at), r.reference_month, DATE(r.created_at))) AS month_number,
                        COUNT(*) AS item_count,
                        IFNULL(SUM(r.amount), 0) AS amount_total
                    FROM reimbursement_entries r
                    INNER JOIN people pe ON pe.id = r.person_id AND pe.deleted_at IS NULL
                    WHERE r.deleted_at IS NULL
                      AND r.status = "pago"
                      AND YEAR(COALESCE(DATE(r.paid_at), r.reference_month, DATE(r.created_at))) = :year_reimbursement'
                    . ($organId > 0 ? ' AND pe.organ_id = :organ_id_reimbursement' : '') . '
                    GROUP BY MONTH(COALESCE(DATE(r.paid_at), r.reference_month, DATE(r.created_at)))
                ) src
                GROUP BY src.month_number';

        $params = [
            'year_invoices' => $year,
            'year_reimbursement' => $year,
        ];

        if ($organId > 0) {
            $params['organ_id_invoices'] = $organId;
            $params['organ_id_reimbursement'] = $organId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthStatusTotalsFromRows($stmt->fetchAll());
    }

    /** @return array<int, array{count: int, amount: float}> */
    private function reconciledFinancialStatusByMonth(int $year, int $organId): array
    {
        $sql = 'SELECT
                    MONTH(r.reference_month) AS month_number,
                    COUNT(*) AS total_count,
                    IFNULL(SUM(cm.total_amount), 0) AS total_amount
                FROM cost_mirror_reconciliations r
                INNER JOIN cost_mirrors cm ON cm.id = r.cost_mirror_id AND cm.deleted_at IS NULL
                WHERE r.deleted_at IS NULL
                  AND r.status = "aprovado"
                  AND YEAR(r.reference_month) = :year'
                . ($organId > 0 ? ' AND cm.organ_id = :organ_id' : '') . '
                GROUP BY MONTH(r.reference_month)';

        $params = ['year' => $year];
        if ($organId > 0) {
            $params['organ_id'] = $organId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->monthStatusTotalsFromRows($stmt->fetchAll());
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array<int, float>
     */
    private function monthTotalsFromRows(array $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $month = (int) ($row['month_number'] ?? 0);
            if ($month < 1 || $month > 12) {
                continue;
            }

            $totals[$month] = (float) ($row['total'] ?? 0.0);
        }

        return $totals;
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array<int, array{count: int, amount: float}>
     */
    private function monthStatusTotalsFromRows(array $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $month = (int) ($row['month_number'] ?? 0);
            if ($month < 1 || $month > 12) {
                continue;
            }

            $totals[$month] = [
                'count' => max(0, (int) ($row['total_count'] ?? 0)),
                'amount' => max(0.0, (float) ($row['total_amount'] ?? 0.0)),
            ];
        }

        return $totals;
    }

    private function daysInStatusExpression(): string
    {
        return 'TIMESTAMPDIFF(DAY, a.updated_at, NOW())';
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
    private function buildOperationalWhere(array $filters, array &$params, bool $applySeverity): string
    {
        $periodStart = $this->normalizeDate(
            (string) ($filters['period_start'] ?? date('Y-01-01')),
            date('Y-01-01')
        );
        $periodEnd = $this->normalizeDate(
            (string) ($filters['period_end'] ?? date('Y-12-31')),
            date('Y-12-31')
        );

        if ($periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        $periodEndExclusive = date('Y-m-d', strtotime($periodEnd . ' +1 day'));

        $where = 'WHERE a.deleted_at IS NULL
            AND p.deleted_at IS NULL
            AND s.is_active = 1
            AND s.next_action_label IS NOT NULL
            AND (sr.id IS NULL OR sr.is_active = 1)
            AND a.updated_at >= :period_start_at
            AND a.updated_at < :period_end_at';

        $params['period_start_at'] = $periodStart . ' 00:00:00';
        $params['period_end_at'] = $periodEndExclusive . ' 00:00:00';

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

        $organId = (int) ($filters['organ_id'] ?? 0);
        if ($organId > 0) {
            $where .= ' AND p.organ_id = :organ_id';
            $params['organ_id'] = $organId;
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

    /** @param array<string, mixed> $filters
     *  @return array{0: string, 1: string}
     */
    private function operationalSort(array $filters): array
    {
        $sortMap = [
            'person_name' => 'p.name',
            'organ_name' => 'o.name',
            'status_order' => 's.sort_order',
            'days_in_status' => $this->daysInStatusExpression(),
            'sla_level' => $this->slaLevelExpression(),
            'updated_at' => 'a.updated_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'days_in_status');
        $dir = (string) ($filters['dir'] ?? 'desc');

        $sortColumn = $sortMap[$sort] ?? $sortMap['days_in_status'];
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        return [$sortColumn, $direction];
    }

    private function monthsSql(): string
    {
        return 'SELECT 1 AS month_number
                UNION ALL SELECT 2
                UNION ALL SELECT 3
                UNION ALL SELECT 4
                UNION ALL SELECT 5
                UNION ALL SELECT 6
                UNION ALL SELECT 7
                UNION ALL SELECT 8
                UNION ALL SELECT 9
                UNION ALL SELECT 10
                UNION ALL SELECT 11
                UNION ALL SELECT 12';
    }

    /** @param array<string, mixed> $filters
     *  @return array{start: string, end_exclusive: string}
     */
    private function periodWindow(array $filters): array
    {
        $periodStart = $this->normalizeDate(
            (string) ($filters['period_start'] ?? date('Y-01-01')),
            date('Y-01-01')
        );
        $periodEnd = $this->normalizeDate(
            (string) ($filters['period_end'] ?? date('Y-12-31')),
            date('Y-12-31')
        );

        if ($periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        return [
            'start' => $periodStart . ' 00:00:00',
            'end_exclusive' => date('Y-m-d', strtotime($periodEnd . ' +1 day')) . ' 00:00:00',
        ];
    }

    private function auditScopeByOrganSql(string $auditAlias, int $organId): string
    {
        $organ = max(0, $organId);

        return '(
            (' . $auditAlias . '.entity = "organ" AND ' . $auditAlias . '.entity_id = ' . $organ . ')
            OR (' . $auditAlias . '.entity = "person" AND EXISTS (
                SELECT 1 FROM people p
                WHERE p.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "assignment" AND EXISTS (
                SELECT 1
                FROM assignments ass
                INNER JOIN people p ON p.id = ass.person_id
                WHERE ass.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "assignment_checklist" AND EXISTS (
                SELECT 1
                FROM assignments ass
                INNER JOIN people p ON p.id = ass.person_id
                WHERE ass.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "assignment_checklist_item" AND EXISTS (
                SELECT 1
                FROM assignment_checklist_items aci
                INNER JOIN assignments ass ON ass.id = aci.assignment_id
                INNER JOIN people p ON p.id = ass.person_id
                WHERE aci.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "timeline_event" AND EXISTS (
                SELECT 1
                FROM timeline_events te
                INNER JOIN people p ON p.id = te.person_id
                WHERE te.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "document" AND EXISTS (
                SELECT 1
                FROM documents d
                INNER JOIN people p ON p.id = d.person_id
                WHERE d.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "cost_plan" AND EXISTS (
                SELECT 1
                FROM cost_plans cp
                INNER JOIN people p ON p.id = cp.person_id
                WHERE cp.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "cost_plan_item" AND EXISTS (
                SELECT 1
                FROM cost_plan_items cpi
                INNER JOIN people p ON p.id = cpi.person_id
                WHERE cpi.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "reimbursement_entry" AND EXISTS (
                SELECT 1
                FROM reimbursement_entries re
                INNER JOIN people p ON p.id = re.person_id
                WHERE re.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "process_comment" AND EXISTS (
                SELECT 1
                FROM process_comments pc
                INNER JOIN people p ON p.id = pc.person_id
                WHERE pc.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "process_admin_timeline_note" AND EXISTS (
                SELECT 1
                FROM process_admin_timeline_notes patn
                INNER JOIN people p ON p.id = patn.person_id
                WHERE patn.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
            OR (' . $auditAlias . '.entity = "analyst_pending_item" AND EXISTS (
                SELECT 1
                FROM analyst_pending_items api
                INNER JOIN people p ON p.id = api.person_id
                WHERE api.id = ' . $auditAlias . '.entity_id
                  AND p.organ_id = ' . $organ . '
            ))
        )';
    }

    private function normalizeDate(string $value, string $default): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $default;
        }

        return date('Y-m-d', $timestamp);
    }
}
