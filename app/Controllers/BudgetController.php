<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\BudgetRepository;
use App\Services\BudgetService;

final class BudgetController extends Controller
{
    public function index(Request $request): void
    {
        $year = $this->normalizeYear((int) $request->input('year', (string) date('Y')));
        $budget = $this->service()->dashboard($year);

        $simulationFlash = Session::getFlash('budget_simulation');
        $simulationResult = is_array($simulationFlash) ? $simulationFlash : null;

        $this->view('budget/index', [
            'title' => 'Orcamento e capacidade',
            'year' => $year,
            'budget' => $budget,
            'simulationResult' => $simulationResult,
            'canManage' => $this->app->auth()->hasPermission('budget.manage'),
            'canSimulate' => $this->app->auth()->hasPermission('budget.simulate'),
        ]);
    }

    public function simulate(Request $request): void
    {
        $year = $this->normalizeYear((int) $request->input('year', (string) date('Y')));

        $result = $this->service()->simulate(
            year: $year,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flashInput($request->all());
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/budget?year=' . $year);
        }

        if (is_array($result['simulation'] ?? null)) {
            Session::flash('budget_simulation', $result['simulation']);
        }

        flash('success', $result['message']);
        $this->redirect('/budget?year=' . $year);
    }

    public function upsertParameter(Request $request): void
    {
        $year = $this->normalizeYear((int) $request->input('year', (string) date('Y')));
        $input = [
            'organ_id' => (string) $request->input('parameter_organ_id', '0'),
            'avg_monthly_cost' => (string) $request->input('parameter_avg_monthly_cost', ''),
            'notes' => (string) $request->input('parameter_notes', ''),
        ];

        $result = $this->service()->upsertOrgParameter(
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flashInput($request->all());
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/budget?year=' . $year);
        }

        flash('success', $result['message']);
        $this->redirect('/budget?year=' . $year);
    }

    public function upsertScenarioParameter(Request $request): void
    {
        $year = $this->normalizeYear((int) $request->input('year', (string) date('Y')));
        $input = [
            'organ_id' => (string) $request->input('scenario_parameter_organ_id', '0'),
            'modality' => (string) $request->input('scenario_parameter_modality', 'geral'),
            'base_variation_percent' => (string) $request->input('scenario_parameter_base_variation_percent', ''),
            'updated_variation_percent' => (string) $request->input('scenario_parameter_updated_variation_percent', ''),
            'worst_variation_percent' => (string) $request->input('scenario_parameter_worst_variation_percent', ''),
            'notes' => (string) $request->input('scenario_parameter_notes', ''),
        ];

        $result = $this->service()->upsertScenarioParameter(
            year: $year,
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flashInput($request->all());
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/budget?year=' . $year);
        }

        flash('success', $result['message']);
        $this->redirect('/budget?year=' . $year);
    }

    private function service(): BudgetService
    {
        return new BudgetService(
            new BudgetRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function normalizeYear(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }
}
