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

    private function normalizeDate(string $value, string $default): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $default;
        }

        return date('Y-m-d', $timestamp);
    }
}
