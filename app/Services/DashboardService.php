<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(private DashboardRepository $repository, private Config $config)
    {
    }

    /**
     * @return array{
     *   summary: array<string, int|float>,
     *   status_distribution: array<int, array<string, int|float|string>>,
     *   recent_timeline: array<int, array<string, mixed>>,
     *   executive_panel: array{
     *      summary: array<string, int|float>,
     *      bottlenecks: array<int, array<string, int|float|string>>,
     *      organ_ranking: array<int, array<string, int|float|string>>
     *   },
     *   recommendation: array{title: string, description: string, label: string, path: string},
     *   generated_at: string,
     *   data_source: string
     * }
     */
    public function overview(int $timelineLimit = 8, bool $preferSnapshot = true): array
    {
        $snapshot = $preferSnapshot ? $this->freshSnapshot() : null;
        if ($snapshot !== null) {
            $summary = $this->normalizeSummary(
                is_array($snapshot['summary'] ?? null) ? $snapshot['summary'] : []
            );
            $statusDistribution = $this->normalizeStatusDistribution(
                is_array($snapshot['status_distribution'] ?? null) ? $snapshot['status_distribution'] : [],
                (int) $summary['total_people']
            );
            $executivePanel = is_array($snapshot['executive_panel'] ?? null)
                ? $this->normalizeExecutivePanel($snapshot['executive_panel'])
                : $this->executivePanel();
            $recommendation = is_array($snapshot['recommendation'] ?? null)
                ? $this->normalizeRecommendation($snapshot['recommendation'])
                : $this->recommendation($summary);

            return [
                'summary' => $summary,
                'status_distribution' => $statusDistribution,
                'recent_timeline' => $this->repository->recentTimeline($timelineLimit),
                'executive_panel' => $executivePanel,
                'recommendation' => $recommendation,
                'generated_at' => (string) ($snapshot['captured_at'] ?? date('Y-m-d H:i:s')),
                'data_source' => 'snapshot',
            ];
        }

        $summary = $this->normalizeSummary($this->repository->summary());
        $statusDistribution = $this->normalizeStatusDistribution(
            $this->repository->statusDistribution(),
            (int) $summary['total_people']
        );

        return [
            'summary' => $summary,
            'status_distribution' => $statusDistribution,
            'recent_timeline' => $this->repository->recentTimeline($timelineLimit),
            'executive_panel' => $this->executivePanel(),
            'recommendation' => $this->recommendation($summary),
            'generated_at' => date('Y-m-d H:i:s'),
            'data_source' => 'live',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, int|float>
     */
    private function normalizeSummary(array $raw): array
    {
        $totalPeople = max(0, (int) ($raw['total_people'] ?? 0));
        $activePeople = max(0, min($totalPeople, (int) ($raw['active_people'] ?? 0)));
        $inProgressPeople = max(0, $totalPeople - $activePeople);
        $totalOrgans = max(0, (int) ($raw['total_organs'] ?? 0));

        $peopleWithDocuments = max(0, min($totalPeople, (int) ($raw['people_with_documents'] ?? 0)));
        $peopleWithActiveCostPlan = max(0, min($totalPeople, (int) ($raw['people_with_active_cost_plan'] ?? 0)));

        $withoutDocuments = max(0, $totalPeople - $peopleWithDocuments);
        $withoutCostPlan = max(0, $totalPeople - $peopleWithActiveCostPlan);
        $expectedReimbursement = max(0.0, (float) ($raw['expected_reimbursement_current_month'] ?? 0));
        $actualPostedReimbursement = max(0.0, (float) ($raw['actual_reimbursement_posted_current_month'] ?? 0));
        $actualPaidReimbursement = max(0.0, (float) ($raw['actual_reimbursement_paid_current_month'] ?? 0));
        $totalCdos = max(0, (int) ($raw['total_cdos'] ?? 0));
        $openCdos = max(0, min($totalCdos, (int) ($raw['open_cdos'] ?? 0)));
        $cdoTotalAmount = max(0.0, (float) ($raw['cdo_total_amount'] ?? 0));
        $cdoAllocatedAmount = max(0.0, (float) ($raw['cdo_allocated_amount'] ?? 0));
        $cdoAvailableAmount = max(0.0, $cdoTotalAmount - $cdoAllocatedAmount);

        return [
            'total_people' => $totalPeople,
            'active_people' => $activePeople,
            'in_progress_people' => $inProgressPeople,
            'total_organs' => $totalOrgans,
            'people_with_documents' => $peopleWithDocuments,
            'people_with_active_cost_plan' => $peopleWithActiveCostPlan,
            'people_without_documents' => $withoutDocuments,
            'people_without_cost_plan' => $withoutCostPlan,
            'documents_coverage_percent' => $this->percent($peopleWithDocuments, $totalPeople),
            'cost_plan_coverage_percent' => $this->percent($peopleWithActiveCostPlan, $totalPeople),
            'timeline_last_30_days' => max(0, (int) ($raw['timeline_last_30_days'] ?? 0)),
            'audit_last_30_days' => max(0, (int) ($raw['audit_last_30_days'] ?? 0)),
            'expected_reimbursement_current_month' => round($expectedReimbursement, 2),
            'actual_reimbursement_posted_current_month' => round($actualPostedReimbursement, 2),
            'actual_reimbursement_paid_current_month' => round($actualPaidReimbursement, 2),
            'reconciliation_deviation_posted_current' => round($actualPostedReimbursement - $expectedReimbursement, 2),
            'reconciliation_deviation_paid_current' => round($actualPaidReimbursement - $expectedReimbursement, 2),
            'total_cdos' => $totalCdos,
            'open_cdos' => $openCdos,
            'cdo_total_amount' => round($cdoTotalAmount, 2),
            'cdo_allocated_amount' => round($cdoAllocatedAmount, 2),
            'cdo_available_amount' => round($cdoAvailableAmount, 2),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, int|float|string>>
     */
    private function normalizeStatusDistribution(array $rows, int $totalPeople): array
    {
        $distribution = [];

        foreach ($rows as $row) {
            $total = max(0, (int) ($row['total'] ?? 0));
            $distribution[] = [
                'code' => (string) ($row['code'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                'total' => $total,
                'share' => $this->percent($total, $totalPeople),
            ];
        }

        return $distribution;
    }

    /**
     * @param array<string, int|float> $summary
     * @return array{title: string, description: string, label: string, path: string}
     */
    private function recommendation(array $summary): array
    {
        $totalPeople = (int) ($summary['total_people'] ?? 0);
        $withoutDocuments = (int) ($summary['people_without_documents'] ?? 0);
        $withoutCostPlan = (int) ($summary['people_without_cost_plan'] ?? 0);
        $inProgress = (int) ($summary['in_progress_people'] ?? 0);
        $deviationPosted = (float) ($summary['reconciliation_deviation_posted_current'] ?? 0.0);
        $totalCdos = (int) ($summary['total_cdos'] ?? 0);
        $openCdos = (int) ($summary['open_cdos'] ?? 0);
        $cdoAvailable = (float) ($summary['cdo_available_amount'] ?? 0.0);

        if ($totalPeople === 0) {
            return [
                'title' => 'Iniciar cadastro base',
                'description' => 'Ainda nao ha pessoas no pipeline. Comece com o primeiro cadastro para liberar a trilha operacional.',
                'label' => 'Cadastrar pessoa',
                'path' => '/people/create',
            ];
        }

        if (abs($deviationPosted) >= 0.01) {
            $isOver = $deviationPosted > 0;

            return [
                'title' => 'Conciliação financeira do mês',
                'description' => $isOver
                    ? sprintf('Desvio positivo de %s entre previsto e real lançado no mês atual. Priorize revisão de competências no Perfil 360.', $this->money($deviationPosted))
                    : sprintf('Desvio negativo de %s entre previsto e real lançado no mês atual. Valide lançamentos pendentes e janela de competência.', $this->money(abs($deviationPosted))),
                'label' => 'Revisar pessoas',
                'path' => '/people',
            ];
        }

        if ($totalCdos === 0) {
            return [
                'title' => 'Cadastrar primeiro CDO',
                'description' => 'Ainda nao ha CDO ativo na base. Cadastre o primeiro credito para iniciar o controle de vinculo e saldo.',
                'label' => 'Novo CDO',
                'path' => '/cdos/create',
            ];
        }

        if ($openCdos > 0 && $cdoAvailable >= 0.01) {
            return [
                'title' => 'Alocar saldo de CDO',
                'description' => sprintf('Ha %d CDO(s) com saldo disponivel de %s. Vincule pessoas para reduzir risco de execucao sem cobertura.', $openCdos, $this->money($cdoAvailable)),
                'label' => 'Abrir CDOs',
                'path' => '/cdos',
            ];
        }

        if ($withoutDocuments > 0) {
            return [
                'title' => 'Regularizar dossie documental',
                'description' => sprintf('%d pessoa(s) ainda sem documentos. Priorize upload de oficio/resposta e comprovantes.', $withoutDocuments),
                'label' => 'Ver pessoas',
                'path' => '/people',
            ];
        }

        if ($withoutCostPlan > 0) {
            return [
                'title' => 'Completar plano de custos',
                'description' => sprintf('%d pessoa(s) ainda sem versao ativa de custos. Crie a versao inicial para consolidar previsao.', $withoutCostPlan),
                'label' => 'Ver pessoas',
                'path' => '/people',
            ];
        }

        if ($inProgress > 0) {
            return [
                'title' => 'Acompanhar pipeline em andamento',
                'description' => sprintf('%d pessoa(s) ainda em etapas intermediarias. Revise timeline e avance os casos prontos.', $inProgress),
                'label' => 'Abrir pipeline',
                'path' => '/people',
            ];
        }

        return [
            'title' => 'Manter rotina de monitoramento',
            'description' => 'Pipeline estabilizado. Monitore auditoria e eventos para identificar desvios e retrabalho.',
            'label' => 'Abrir auditoria',
            'path' => '/people',
        ];
    }

    private function percent(int $value, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{title: string, description: string, label: string, path: string}
     */
    private function normalizeRecommendation(array $payload): array
    {
        return [
            'title' => trim((string) ($payload['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'label' => trim((string) ($payload['label'] ?? '')),
            'path' => trim((string) ($payload['path'] ?? '')),
        ];
    }

    /**
     * @return array{
     *   summary: array<string, int|float>,
     *   bottlenecks: array<int, array<string, int|float|string>>,
     *   organ_ranking: array<int, array<string, int|float|string>>
     * }
     */
    private function executivePanel(): array
    {
        return $this->normalizeExecutivePanel([
            'bottlenecks' => $this->repository->executiveBottlenecks(8),
            'organ_ranking' => $this->repository->executiveOrganRanking(10),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   summary: array<string, int|float>,
     *   bottlenecks: array<int, array<string, int|float|string>>,
     *   organ_ranking: array<int, array<string, int|float|string>>
     * }
     */
    private function normalizeExecutivePanel(array $payload): array
    {
        $rawBottlenecks = is_array($payload['bottlenecks'] ?? null) ? $payload['bottlenecks'] : [];
        $rawOrganRanking = is_array($payload['organ_ranking'] ?? null) ? $payload['organ_ranking'] : [];

        $bottlenecks = [];
        foreach ($rawBottlenecks as $row) {
            if (!is_array($row)) {
                continue;
            }

            $casesCount = max(0, (int) ($row['cases_count'] ?? 0));
            $riskCount = max(0, (int) ($row['em_risco_count'] ?? 0));
            $overdueCount = max(0, (int) ($row['vencido_count'] ?? 0));
            $criticalCount = $riskCount + $overdueCount;
            $criticalShare = $casesCount > 0 ? round(($criticalCount / $casesCount) * 100, 1) : 0.0;

            $bottlenecks[] = [
                'status_code' => (string) ($row['status_code'] ?? ''),
                'status_label' => (string) ($row['status_label'] ?? ''),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                'cases_count' => $casesCount,
                'impacted_organs_count' => max(0, (int) ($row['impacted_organs_count'] ?? 0)),
                'avg_days_in_status' => round(max(0.0, (float) ($row['avg_days_in_status'] ?? 0.0)), 2),
                'max_days_in_status' => max(0, (int) ($row['max_days_in_status'] ?? 0)),
                'em_risco_count' => $riskCount,
                'vencido_count' => $overdueCount,
                'critical_count' => $criticalCount,
                'critical_share_percent' => $criticalShare,
            ];
        }

        $organRanking = [];
        foreach ($rawOrganRanking as $row) {
            if (!is_array($row)) {
                continue;
            }

            $casesCount = max(0, (int) ($row['cases_count'] ?? 0));
            $noPrazoCount = max(0, (int) ($row['no_prazo_count'] ?? 0));
            $riskCount = max(0, (int) ($row['em_risco_count'] ?? 0));
            $overdueCount = max(0, (int) ($row['vencido_count'] ?? 0));
            $criticalCount = $riskCount + $overdueCount;
            $criticalShare = $casesCount > 0 ? round(($criticalCount / $casesCount) * 100, 1) : 0.0;
            $severityScore = ($overdueCount * 3) + ($riskCount * 2) + ($criticalShare / 10);

            $organRanking[] = [
                'organ_id' => max(0, (int) ($row['organ_id'] ?? 0)),
                'organ_name' => (string) ($row['organ_name'] ?? ''),
                'cases_count' => $casesCount,
                'no_prazo_count' => $noPrazoCount,
                'em_risco_count' => $riskCount,
                'vencido_count' => $overdueCount,
                'critical_count' => $criticalCount,
                'critical_share_percent' => $criticalShare,
                'avg_days_in_status' => round(max(0.0, (float) ($row['avg_days_in_status'] ?? 0.0)), 2),
                'max_days_in_status' => max(0, (int) ($row['max_days_in_status'] ?? 0)),
                'severity_score' => round($severityScore, 1),
            ];
        }

        usort($organRanking, static function (array $left, array $right): int {
            $scoreDiff = (float) ($right['severity_score'] ?? 0.0) <=> (float) ($left['severity_score'] ?? 0.0);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }

            $casesDiff = (int) ($right['cases_count'] ?? 0) <=> (int) ($left['cases_count'] ?? 0);
            if ($casesDiff !== 0) {
                return $casesDiff;
            }

            return strcmp((string) ($left['organ_name'] ?? ''), (string) ($right['organ_name'] ?? ''));
        });

        $criticalCases = array_sum(array_map(
            static fn (array $row): int => (int) ($row['critical_count'] ?? 0),
            $organRanking
        ));
        $overdueCases = array_sum(array_map(
            static fn (array $row): int => (int) ($row['vencido_count'] ?? 0),
            $organRanking
        ));
        $riskCases = array_sum(array_map(
            static fn (array $row): int => (int) ($row['em_risco_count'] ?? 0),
            $organRanking
        ));
        $totalCases = array_sum(array_map(
            static fn (array $row): int => (int) ($row['cases_count'] ?? 0),
            $organRanking
        ));
        $organsWithCritical = count(array_filter(
            $organRanking,
            static fn (array $row): bool => ((int) ($row['critical_count'] ?? 0)) > 0
        ));

        return [
            'summary' => [
                'total_organs_monitored' => count($organRanking),
                'organs_with_critical_cases' => $organsWithCritical,
                'total_cases_monitored' => $totalCases,
                'critical_cases' => $criticalCases,
                'overdue_cases' => $overdueCases,
                'risk_cases' => $riskCases,
                'critical_share_percent' => $totalCases > 0 ? round(($criticalCases / $totalCases) * 100, 1) : 0.0,
            ],
            'bottlenecks' => $bottlenecks,
            'organ_ranking' => $organRanking,
        ];
    }

    /** @return array<string, mixed>|null */
    private function freshSnapshot(): ?array
    {
        $maxAgeMinutes = max(0, (int) $this->config->get('ops.kpi_snapshot_max_age_minutes', 240));
        if ($maxAgeMinutes <= 0) {
            return null;
        }

        $directory = $this->resolveSnapshotDir();
        if (!is_dir($directory)) {
            return null;
        }

        $files = glob(rtrim($directory, '/') . '/kpi_snapshot_*.json');
        if (!is_array($files) || $files === []) {
            return null;
        }

        $latestFile = null;
        $latestMtime = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $mtime = filemtime($file);
            if ($mtime === false || $mtime <= $latestMtime) {
                continue;
            }

            $latestMtime = $mtime;
            $latestFile = $file;
        }

        if ($latestFile === null || $latestMtime <= 0) {
            return null;
        }

        if ((time() - $latestMtime) > ($maxAgeMinutes * 60)) {
            return null;
        }

        $content = file_get_contents($latestFile);
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (!isset($decoded['summary']) || !is_array($decoded['summary'])) {
            return null;
        }

        return $decoded;
    }

    private function resolveSnapshotDir(): string
    {
        $configured = trim((string) $this->config->get('ops.kpi_snapshot_dir', 'storage/ops/kpi_snapshots'));
        if ($configured === '') {
            return BASE_PATH . '/storage/ops/kpi_snapshots';
        }

        if (str_starts_with($configured, '/')) {
            return rtrim($configured, '/');
        }

        return rtrim(BASE_PATH . '/' . ltrim($configured, '/'), '/');
    }
}
