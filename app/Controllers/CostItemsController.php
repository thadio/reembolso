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
        $macroCategory = (string) $request->input('macro_category', '');
        $linkage = (string) $request->input('linkage', '');
        $itemKind = (string) $request->input('item_kind', '');

        if (!in_array($itemKind, ['', 'aggregator', 'child'], true)) {
            $itemKind = '';
        }

        if (!in_array($linkage, ['', '309', '510'], true)) {
            $linkage = '';
        }

        if (!in_array($macroCategory, ['', 'remuneracao_direta', 'encargos_obrigacoes_legais', 'beneficios_provisoes_indiretos'], true)) {
            $macroCategory = '';
        }

        $hierarchy = $this->service()->hierarchy($query);
        if ($macroCategory !== '' || $linkage !== '' || $itemKind !== '') {
            $filteredHierarchy = [];
            foreach ($hierarchy as $group) {
                $category = is_array($group['category'] ?? null) ? $group['category'] : [];
                $children = is_array($group['children'] ?? null) ? $group['children'] : [];

                $categoryMatches = true;
                if ($macroCategory !== '') {
                    $categoryMatches = $categoryMatches
                        && (string) ($category['macro_category'] ?? '') === $macroCategory;
                }
                if ($linkage !== '') {
                    $categoryMatches = $categoryMatches
                        && (string) ((int) ($category['linkage_code'] ?? 0)) === $linkage;
                }

                $filteredChildren = array_values(array_filter(
                    $children,
                    static function (array $child) use ($macroCategory, $linkage): bool {
                        if ($macroCategory !== '' && (string) ($child['macro_category'] ?? '') !== $macroCategory) {
                            return false;
                        }

                        if ($linkage !== '' && (string) ((int) ($child['linkage_code'] ?? 0)) !== $linkage) {
                            return false;
                        }

                        return true;
                    }
                ));

                if ($itemKind === 'aggregator') {
                    if ($categoryMatches) {
                        $group['children'] = [];
                        $group['children_count'] = 0;
                        $filteredHierarchy[] = $group;
                    }
                    continue;
                }

                if ($itemKind === 'child') {
                    if ($filteredChildren !== []) {
                        $group['children'] = $filteredChildren;
                        $group['children_count'] = count($filteredChildren);
                        $filteredHierarchy[] = $group;
                    }
                    continue;
                }

                if ($categoryMatches || $filteredChildren !== []) {
                    $group['children'] = $filteredChildren;
                    $group['children_count'] = count($filteredChildren);
                    $filteredHierarchy[] = $group;
                }
            }

            $hierarchy = $filteredHierarchy;
        }

        $this->view('cost_items/index', [
            'title' => 'Tipologia de Custos de Pessoal',
            'hierarchy' => $hierarchy,
            'filters' => [
                'q' => $query,
                'linkage' => $linkage,
                'macro_category' => $macroCategory,
                'item_kind' => $itemKind,
            ],
            'canManage' => $this->app->auth()->hasPermission('cost_item.manage'),
            'linkageOptions' => $this->service()->linkageOptions(),
            'macroCategoryOptions' => $this->service()->macroCategoryOptions(),
            'itemKindOptions' => $this->service()->itemKindOptions(),
        ]);
    }

    public function create(Request $request): void
    {
        $item = $this->emptyItem();
        $requestedKind = (string) $request->input('item_kind', 'child');
        if (in_array($requestedKind, ['aggregator', 'child'], true)) {
            $item['item_kind'] = $requestedKind;
            $item['is_aggregator'] = $requestedKind === 'aggregator' ? 1 : 0;
        }

        $requestedParent = max(0, (int) $request->input('parent_cost_item_id', '0'));
        if ($requestedParent > 0) {
            $item['parent_cost_item_id'] = $requestedParent;
        }

        $this->view('cost_items/create', [
            'title' => 'Novo Tipo de Custo',
            'item' => $item,
            'itemKindOptions' => $this->service()->itemKindOptions(),
            'aggregatorOptions' => $this->service()->aggregatorOptions(),
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
            'itemKindOptions' => $this->service()->itemKindOptions(),
            'aggregatorOptions' => $this->service()->aggregatorOptions(),
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
            flash('error', 'Tipo de custo nao encontrado, ja removido ou possui itens filhos ativos.');
            $this->redirect('/cost-items');
        }

        flash('success', 'Tipo de custo removido com sucesso.');
        $this->redirect('/cost-items');
    }

    /** @return array<string, mixed> */
    private function emptyItem(): array
    {
        return [
            'parent_cost_item_id' => null,
            'is_aggregator' => 0,
            'hierarchy_sort' => '',
            'item_kind' => 'child',
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

    private function service(): CostItemCatalogService
    {
        return new CostItemCatalogService(
            new CostItemCatalogRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
