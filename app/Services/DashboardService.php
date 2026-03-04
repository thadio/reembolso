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

        if ($totalPeople === 0) {
            return [
                'title' => 'Iniciar cadastro base',
                'description' => 'Ainda nao ha pessoas no pipeline. Comece com o primeiro cadastro para liberar a trilha operacional.',
                'label' => 'Cadastrar pessoa',
                'path' => '/people/create',
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
}
