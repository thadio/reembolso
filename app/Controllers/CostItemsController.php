<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\CostItemCatalogRepository;
use App\Services\CostItemCatalogService;

final class CostItemsController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $linkage = (string) $request->input('linkage', '');
        $reimbursability = (string) $request->input('reimbursability', (string) $request->input('reimbursable', ''));
        $periodicity = (string) $request->input('periodicity', '');
        $macroCategory = (string) $request->input('macro_category', '');
        $subcategory = (string) $request->input('subcategory', '');
        $expenseNature = (string) $request->input('expense_nature', '');
        $predictability = (string) $request->input('predictability', '');
        $sort = (string) $request->input('sort', 'cost_code');
        $dir = (string) $request->input('dir', 'asc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '20')));

        $reimbursability = $this->normalizeReimbursabilityFilter($reimbursability);

        if (!in_array($linkage, ['', '309', '510'], true)) {
            $linkage = '';
        }

        if (!in_array($periodicity, ['', 'mensal', 'anual', 'eventual', 'unico'], true)) {
            $periodicity = '';
        }

        if (!in_array($macroCategory, ['', 'remuneracao_direta', 'encargos_obrigacoes_legais', 'beneficios_provisoes_indiretos'], true)) {
            $macroCategory = '';
        }

        $allowedSubcategories = [
            '',
            'Remuneracao Base',
            'Adicionais',
            'Gratificacoes',
            'Complementos',
            'Beneficios',
            'Encargos Sociais e Trabalhistas',
            'Provisoes Trabalhistas',
            'Remuneracoes Variaveis',
            'Custos de Pessoal Indiretos',
            'Cessao ou Cooperacao',
        ];
        if (!in_array($subcategory, $allowedSubcategories, true)) {
            $subcategory = '';
        }

        if (!in_array($expenseNature, ['', 'remuneratoria', 'indenizatoria', 'encargos', 'provisoes'], true)) {
            $expenseNature = '';
        }

        if (!in_array($predictability, ['', 'fixa', 'variavel', 'eventual'], true)) {
            $predictability = '';
        }

        $result = $this->service()->paginate(
            query: $query,
            linkage: $linkage,
            reimbursability: $reimbursability,
            periodicity: $periodicity,
            macroCategory: $macroCategory,
            subcategory: $subcategory,
            expenseNature: $expenseNature,
            predictability: $predictability,
            sort: $sort,
            dir: $dir,
            page: $page,
            perPage: $perPage
        );

        $this->view('cost_items/index', [
            'title' => 'Tipologia de Custos de Pessoal',
            'items' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => [
                'q' => $query,
                'linkage' => $linkage,
                'reimbursability' => $reimbursability,
                'periodicity' => $periodicity,
                'macro_category' => $macroCategory,
                'subcategory' => $subcategory,
                'expense_nature' => $expenseNature,
                'predictability' => $predictability,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPage,
            ],
            'canManage' => $this->app->auth()->hasPermission('cost_item.manage'),
            'linkageOptions' => $this->service()->linkageOptions(),
            'reimbursabilityOptions' => $this->service()->reimbursabilityOptions(),
            'periodicityOptions' => $this->service()->periodicityOptions(),
            'macroCategoryOptions' => $this->service()->macroCategoryOptions(),
            'subcategoryOptions' => $this->service()->subcategoryOptions(),
            'expenseNatureOptions' => $this->service()->expenseNatureOptions(),
            'predictabilityOptions' => $this->service()->predictabilityOptions(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('cost_items/create', [
            'title' => 'Novo Tipo de Custo',
            'item' => $this->emptyItem(),
            'linkageOptions' => $this->service()->linkageOptions(),
            'reimbursabilityOptions' => $this->service()->reimbursabilityOptions(),
            'periodicityOptions' => $this->service()->periodicityOptions(),
            'macroCategoryOptions' => $this->service()->macroCategoryOptions(),
            'subcategoryOptions' => $this->service()->subcategoryOptions(),
            'expenseNatureOptions' => $this->service()->expenseNatureOptions(),
            'calculationBaseOptions' => $this->service()->calculationBaseOptions(),
            'predictabilityOptions' => $this->service()->predictabilityOptions(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->create(
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-items/create');
        }

        flash('success', 'Tipo de custo cadastrado com sucesso.');
        $this->redirect('/cost-items/show?id=' . (int) ($result['id'] ?? 0));
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de custo invalido.');
            $this->redirect('/cost-items');
        }

        $item = $this->service()->find($id);
        if ($item === null) {
            flash('error', 'Tipo de custo nao encontrado.');
            $this->redirect('/cost-items');
        }

        $this->view('cost_items/show', [
            'title' => 'Detalhe do Tipo de Custo',
            'item' => $item,
            'canManage' => $this->app->auth()->hasPermission('cost_item.manage'),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de custo invalido.');
            $this->redirect('/cost-items');
        }

        $item = $this->service()->find($id);
        if ($item === null) {
            flash('error', 'Tipo de custo nao encontrado.');
            $this->redirect('/cost-items');
        }

        $this->view('cost_items/edit', [
            'title' => 'Editar Tipo de Custo',
            'item' => $item,
            'linkageOptions' => $this->service()->linkageOptions(),
            'reimbursabilityOptions' => $this->service()->reimbursabilityOptions(),
            'periodicityOptions' => $this->service()->periodicityOptions(),
            'macroCategoryOptions' => $this->service()->macroCategoryOptions(),
            'subcategoryOptions' => $this->service()->subcategoryOptions(),
            'expenseNatureOptions' => $this->service()->expenseNatureOptions(),
            'calculationBaseOptions' => $this->service()->calculationBaseOptions(),
            'predictabilityOptions' => $this->service()->predictabilityOptions(),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de custo invalido.');
            $this->redirect('/cost-items');
        }

        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->update(
            $id,
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/cost-items/edit?id=' . $id);
        }

        flash('success', 'Tipo de custo atualizado com sucesso.');
        $this->redirect('/cost-items/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Tipo de custo invalido.');
            $this->redirect('/cost-items');
        }

        $deleted = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Tipo de custo nao encontrado ou ja removido.');
            $this->redirect('/cost-items');
        }

        flash('success', 'Tipo de custo removido com sucesso.');
        $this->redirect('/cost-items');
    }

    /** @return array<string, mixed> */
    private function emptyItem(): array
    {
        return [
            'cost_code' => '',
            'name' => '',
            'type_description' => '',
            'macro_category' => 'remuneracao_direta',
            'subcategory' => 'Remuneracao Base',
            'expense_nature' => 'remuneratoria',
            'calculation_base' => 'salario_base',
            'charge_incidence' => 1,
            'reimbursability' => 'nao_reembolsavel',
            'predictability' => 'fixa',
            'linkage_code' => 309,
            'payment_periodicity' => 'mensal',
        ];
    }

    private function normalizeReimbursabilityFilter(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            'reimbursable', 'reembolsavel' => 'reembolsavel',
            'partial', 'partial_reimbursable', 'parcialmente_reembolsavel' => 'parcialmente_reembolsavel',
            'non_reimbursable', 'nao_reembolsavel' => 'nao_reembolsavel',
            default => '',
        };
    }

    private function service(): CostItemCatalogService
    {
        return new CostItemCatalogService(
            new CostItemCatalogRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
