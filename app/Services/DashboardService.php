<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(private DashboardRepository $repository)
    {
    }

    /**
     * @return array{
     *   summary: array<string, int|float>,
     *   status_distribution: array<int, array<string, int|float|string>>,
     *   recent_timeline: array<int, array<string, mixed>>,
     *   recommendation: array{title: string, description: string, label: string, path: string},
     *   generated_at: string
     * }
     */
    public function overview(int $timelineLimit = 8): array
    {
        $summary = $this->normalizeSummary($this->repository->summary());
        $statusDistribution = $this->normalizeStatusDistribution(
            $this->repository->statusDistribution(),
            (int) $summary['total_people']
        );

        return [
            'summary' => $summary,
            'status_distribution' => $statusDistribution,
            'recent_timeline' => $this->repository->recentTimeline($timelineLimit),
            'recommendation' => $this->recommendation($summary),
            'generated_at' => date('Y-m-d H:i:s'),
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
}
