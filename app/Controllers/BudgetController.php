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
        $financialNature = $this->normalizeFinancialNature((string) $request->input('financial_nature', 'despesa_reembolso'));
        $budget = $this->service()->dashboard($year, $financialNature);

        $simulationFlash = Session::getFlash('budget_simulation');
        $simulationResult = is_array($simulationFlash) ? $simulationFlash : null;

        $this->view('budget/index', [
            'title' => 'Orcamento e capacidade',
            'year' => $year,
            'financialNature' => $financialNature,
            'financialNatureOptions' => $this->service()->financialNatureOptions(false),
            'budget' => $budget,
            'simulationResult' => $simulationResult,
            'canManage' => $this->app->auth()->hasPermission('budget.manage'),
            'canSimulate' => $this->app->auth()->hasPermission('budget.simulate'),
        ]);
    }

    public function simulate(Request $request): void
    {
        $year = $this->normalizeYear((int) $request->input('year', (string) date('Y')));
        $financialNature = $this->normalizeFinancialNature((string) $request->input('financial_nature', 'despesa_reembolso'));
        $input = $request->all();
        $input['financial_nature'] = $financialNature;

        $result = $this->service()->simulate(
            year: $year,
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flashInput($input);
            flash('error', implode(' ', $result['errors']));
            $this->redirect($this->budgetUrl($year, $financialNature));
        }

        if (is_array($result['simulation'] ?? null)) {
            Session::flash('budget_simulation', $result['simulation']);
        }

        flash('success', $result['message']);
        $this->redirect($this->budgetUrl($year, $financialNature));
    }

    public function storeCycle(Request $request): void
    {
        $financialNature = $this->normalizeFinancialNature((string) $request->input('financial_nature', 'despesa_reembolso'));
        $input = $request->all();
        $input['financial_nature'] = $financialNature;

        $result = $this->service()->createAnnualBudgetCycle(
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $year = $this->resolveRedirectYear(
            preferredYear: $result['year'] ?? null,
            fallbackYear: $request->input('cycle_year', $request->input('year', (string) date('Y')))
        );
        $redirectNature = $this->resolveRedirectNature(
            preferredNature: $result['financial_nature'] ?? null,
            fallbackNature: $financialNature
        );

        if (!$result['ok']) {
            Session::flashInput($input);
            flash('error', implode(' ', $result['errors']));
            $this->redirect($this->budgetUrl($year, $redirectNature));
        }

        flash('success', $result['message']);
        $this->redirect($this->budgetUrl($year, $redirectNature));
    }

    public function updateCycle(Request $request): void
    {
        $financialNature = $this->normalizeFinancialNature((string) $request->input('financial_nature', 'despesa_reembolso'));
        $input = $request->all();
        $input['financial_nature'] = $financialNature;

        $result = $this->service()->updateAnnualBudgetCycle(
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $year = $this->resolveRedirectYear(
            preferredYear: $result['year'] ?? null,
            fallbackYear: $request->input('cycle_year', $request->input('year', (string) date('Y')))
        );
        $redirectNature = $this->resolveRedirectNature(
            preferredNature: $result['financial_nature'] ?? null,
            fallbackNature: $financialNature
        );

        if (!$result['ok']) {
            Session::flashInput($input);
            flash('error', implode(' ', $result['errors']));
            $this->redirect($this->budgetUrl($year, $redirectNature));
        }

        flash('success', $result['message']);
        $this->redirect($this->budgetUrl($year, $redirectNature));
    }

    public function destroyCycle(Request $request): void
    {
        $financialNature = $this->normalizeFinancialNature((string) $request->input('financial_nature', 'despesa_reembolso'));
        $input = $request->all();
        $input['financial_nature'] = $financialNature;

        $result = $this->service()->deleteAnnualBudgetCycle(
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $year = $this->resolveRedirectYear(
            preferredYear: $result['year'] ?? null,
            fallbackYear: $request->input('cycle_year', $request->input('year', (string) date('Y')))
        );
        $redirectNature = $this->resolveRedirectNature(
            preferredNature: $result['financial_nature'] ?? null,
            fallbackNature: $financialNature
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect($this->budgetUrl($year, $redirectNature));
        }

        flash('success', $result['message']);
        $this->redirect($this->budgetUrl($year, $redirectNature));
    }

    public function destroyYearCycles(Request $request): void
    {
        $financialNature = $this->normalizeFinancialNature((string) $request->input('financial_nature', 'despesa_reembolso'));
        $input = $request->all();
        $input['financial_nature'] = $financialNature;

        $result = $this->service()->deleteAnnualBudgetYear(
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $year = $this->resolveRedirectYear(
            preferredYear: $result['year'] ?? null,
            fallbackYear: $request->input('year', $request->input('cycle_year', (string) date('Y')))
        );
        $redirectNature = $this->resolveRedirectNature(
            preferredNature: $result['financial_nature'] ?? null,
            fallbackNature: $financialNature
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect($this->budgetUrl($year, $redirectNature));
        }

        flash('success', $result['message']);
        $this->redirect($this->budgetUrl($year, $redirectNature));
    }

    public function upsertScenarioParameter(Request $request): void
    {
        $year = $this->normalizeYear((int) $request->input('year', (string) date('Y')));
        $financialNature = $this->normalizeFinancialNature((string) $request->input('financial_nature', 'despesa_reembolso'));
        $input = [
            'organ_id' => (string) $request->input('scenario_parameter_organ_id', '0'),
            'modality' => (string) $request->input('scenario_parameter_modality', 'geral'),
            'base_variation_percent' => (string) $request->input('scenario_parameter_base_variation_percent', ''),
            'updated_variation_percent' => (string) $request->input('scenario_parameter_updated_variation_percent', ''),
            'worst_variation_percent' => (string) $request->input('scenario_parameter_worst_variation_percent', ''),
            'notes' => (string) $request->input('scenario_parameter_notes', ''),
            'financial_nature' => $financialNature,
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
            $this->redirect($this->budgetUrl($year, $financialNature));
        }

        flash('success', $result['message']);
        $this->redirect($this->budgetUrl($year, $financialNature));
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

    private function resolveRedirectYear(mixed $preferredYear, mixed $fallbackYear): int
    {
        if (is_numeric((string) $preferredYear)) {
            return $this->normalizeYear((int) $preferredYear);
        }

        if (is_numeric((string) $fallbackYear)) {
            return $this->normalizeYear((int) $fallbackYear);
        }

        return (int) date('Y');
    }

    private function normalizeFinancialNature(string $value): string
    {
        $normalized = trim(mb_strtolower($value));

        return $normalized === 'receita_reembolso' ? 'receita_reembolso' : 'despesa_reembolso';
    }

    private function resolveRedirectNature(mixed $preferredNature, mixed $fallbackNature): string
    {
        $preferred = trim((string) $preferredNature);
        if ($preferred !== '') {
            return $this->normalizeFinancialNature($preferred);
        }

        return $this->normalizeFinancialNature((string) $fallbackNature);
    }

    private function budgetUrl(int $year, string $financialNature): string
    {
        return '/budget?year=' . $year . '&financial_nature=' . urlencode($financialNature);
    }
}
