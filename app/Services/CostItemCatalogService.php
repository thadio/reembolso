<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CostItemCatalogRepository;

final class CostItemCatalogService
{
    private const LINKAGE_CODES = [309, 510];
    private const PERIODICITY_VALUES = ['mensal', 'anual', 'eventual', 'unico'];
    private const MACRO_CATEGORY_VALUES = [
        'remuneracao_direta',
        'encargos_obrigacoes_legais',
        'beneficios_provisoes_indiretos',
    ];
    private const SUBCATEGORY_VALUES = [
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
    private const EXPENSE_NATURE_VALUES = ['remuneratoria', 'indenizatoria', 'encargos', 'provisoes'];
    private const CALCULATION_BASE_VALUES = ['salario_base', 'total_folha', 'valor_fixo', 'total'];
    private const REIMBURSABILITY_VALUES = ['reembolsavel', 'parcialmente_reembolsavel', 'nao_reembolsavel'];
    private const PREDICTABILITY_VALUES = ['fixa', 'variavel', 'eventual'];

    public function __construct(
        private CostItemCatalogRepository $items,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(
        string $query,
        string $linkage,
        string $reimbursability,
        string $periodicity,
        string $macroCategory,
        string $subcategory,
        string $expenseNature,
        string $predictability,
        string $sort,
        string $dir,
        int $page,
        int $perPage
    ): array {
        return $this->items->paginate(
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
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->items->findById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeList(): array
    {
        return $this->items->activeList();
    }

    /** @return array<int, array{value: string, label: string}> */
    public function linkageOptions(): array
    {
        return [
            ['value' => '309', 'label' => 'Remuneracao (309)'],
            ['value' => '510', 'label' => 'Beneficios e auxilios (custeio) (510)'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function periodicityOptions(): array
    {
        return [
            ['value' => 'mensal', 'label' => 'Mensal'],
            ['value' => 'anual', 'label' => 'Anual'],
            ['value' => 'eventual', 'label' => 'Eventual'],
            ['value' => 'unico', 'label' => 'Unico (legado)'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function macroCategoryOptions(): array
    {
        return [
            ['value' => 'remuneracao_direta', 'label' => 'Remuneracao direta'],
            ['value' => 'encargos_obrigacoes_legais', 'label' => 'Encargos e obrigacoes legais'],
            ['value' => 'beneficios_provisoes_indiretos', 'label' => 'Beneficios, provisoes e custos indiretos'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function subcategoryOptions(): array
    {
        return [
            ['value' => 'Remuneracao Base', 'label' => 'Remuneracao Base'],
            ['value' => 'Adicionais', 'label' => 'Adicionais'],
            ['value' => 'Gratificacoes', 'label' => 'Gratificacoes'],
            ['value' => 'Complementos', 'label' => 'Complementos'],
            ['value' => 'Beneficios', 'label' => 'Beneficios'],
            ['value' => 'Encargos Sociais e Trabalhistas', 'label' => 'Encargos Sociais e Trabalhistas'],
            ['value' => 'Provisoes Trabalhistas', 'label' => 'Provisoes Trabalhistas'],
            ['value' => 'Remuneracoes Variaveis', 'label' => 'Remuneracoes Variaveis'],
            ['value' => 'Custos de Pessoal Indiretos', 'label' => 'Custos de Pessoal Indiretos'],
            ['value' => 'Cessao ou Cooperacao', 'label' => 'Cessao ou Cooperacao'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function expenseNatureOptions(): array
    {
        return [
            ['value' => 'remuneratoria', 'label' => 'Remuneratoria'],
            ['value' => 'indenizatoria', 'label' => 'Indenizatoria'],
            ['value' => 'encargos', 'label' => 'Encargos'],
            ['value' => 'provisoes', 'label' => 'Provisoes'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function calculationBaseOptions(): array
    {
        return [
            ['value' => 'salario_base', 'label' => 'Salario base'],
            ['value' => 'total_folha', 'label' => 'Total da folha'],
            ['value' => 'valor_fixo', 'label' => 'Valor fixo'],
            ['value' => 'total', 'label' => 'Total'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function reimbursabilityOptions(): array
    {
        return [
            ['value' => 'reembolsavel', 'label' => 'Reembolsavel'],
            ['value' => 'parcialmente_reembolsavel', 'label' => 'Parcialmente reembolsavel'],
            ['value' => 'nao_reembolsavel', 'label' => 'Nao reembolsavel'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function predictabilityOptions(): array
    {
        return [
            ['value' => 'fixa', 'label' => 'Fixa'],
            ['value' => 'variavel', 'label' => 'Variavel'],
            ['value' => 'eventual', 'label' => 'Eventual'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validate($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $data = $validation['data'];
        if ($this->items->combinationExists(
            costCode: (int) $data['cost_code'],
            name: (string) $data['name'],
            linkageCode: (int) $data['linkage_code'],
            reimbursability: (string) $data['reimbursability'],
            paymentPeriodicity: (string) $data['payment_periodicity']
        )) {
            return [
                'ok' => false,
                'errors' => ['Ja existe item de custo com este codigo ou combinacao principal.'],
                'data' => $data,
            ];
        }

        $data['created_by'] = $userId > 0 ? $userId : null;
        $id = $this->items->create($data);

        $this->audit->log(
            entity: 'cost_item_catalog',
            entityId: $id,
            action: 'create',
            beforeData: null,
            afterData: $data,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'cost_item_catalog',
            type: 'cost_item_catalog.created',
            payload: $this->eventPayload($data),
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $data,
            'id' => $id,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function update(int $id, array $input, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->items->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Item de custo nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validate($input, $before);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $data = $validation['data'];
        if ($this->items->combinationExists(
            costCode: (int) $data['cost_code'],
            name: (string) $data['name'],
            linkageCode: (int) $data['linkage_code'],
            reimbursability: (string) $data['reimbursability'],
            paymentPeriodicity: (string) $data['payment_periodicity'],
            ignoreId: $id
        )) {
            return [
                'ok' => false,
                'errors' => ['Ja existe item de custo com este codigo ou combinacao principal.'],
                'data' => $data,
            ];
        }

        $this->items->update($id, $data);

        $this->audit->log(
            entity: 'cost_item_catalog',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $data,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'cost_item_catalog',
            type: 'cost_item_catalog.updated',
            payload: $this->eventPayload($data),
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $data,
        ];
    }

    public function delete(int $id, int $userId, string $ip, string $userAgent): bool
    {
        $before = $this->items->findById($id);
        if ($before === null) {
            return false;
        }

        $this->items->softDelete($id);

        $this->audit->log(
            entity: 'cost_item_catalog',
            entityId: $id,
            action: 'delete',
            beforeData: $before,
            afterData: null,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'cost_item_catalog',
            type: 'cost_item_catalog.deleted',
            payload: [
                'cost_code' => (int) ($before['cost_code'] ?? 0),
                'name' => (string) ($before['name'] ?? ''),
            ],
            entityId: $id,
            userId: $userId
        );

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @param ?array<string, mixed> $before
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validate(array $input, ?array $before = null): array
    {
        $costCode = (int) ($input['cost_code'] ?? ($before['cost_code'] ?? 0));
        $name = $this->clean($input['name'] ?? ($before['name'] ?? null), 190);
        $typeDescription = $this->clean($input['type_description'] ?? ($before['type_description'] ?? null), 255);
        $macroCategory = $this->normalizeEnum($input['macro_category'] ?? ($before['macro_category'] ?? null));
        $subcategory = $this->normalizeSubcategory($input['subcategory'] ?? ($before['subcategory'] ?? null));
        $expenseNature = $this->normalizeEnum($input['expense_nature'] ?? ($before['expense_nature'] ?? null));
        $calculationBase = $this->normalizeEnum($input['calculation_base'] ?? ($before['calculation_base'] ?? null));
        $chargeIncidence = $this->normalizeBoolInt($input['charge_incidence'] ?? ($before['charge_incidence'] ?? 0));
        $reimbursability = $this->normalizeEnum($input['reimbursability'] ?? ($before['reimbursability'] ?? null));
        $predictability = $this->normalizeEnum($input['predictability'] ?? ($before['predictability'] ?? null));
        $linkageCode = (int) ($input['linkage_code'] ?? ($before['linkage_code'] ?? 0));
        $paymentPeriodicity = $this->normalizeEnum($input['payment_periodicity'] ?? ($before['payment_periodicity'] ?? null));

        $errors = [];

        if ($costCode <= 0 || $costCode > 9999) {
            $errors[] = 'Codigo do tipo de custo invalido (1 a 9999).';
        }

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do tipo de custo e obrigatorio (minimo 3 caracteres).';
        }

        if (!in_array($macroCategory, self::MACRO_CATEGORY_VALUES, true)) {
            $errors[] = 'Categoria macro invalida.';
        }

        if (!in_array($subcategory, self::SUBCATEGORY_VALUES, true)) {
            $errors[] = 'Subcategoria invalida.';
        }

        if (!in_array($expenseNature, self::EXPENSE_NATURE_VALUES, true)) {
            $errors[] = 'Natureza da despesa invalida.';
        }

        if (!in_array($calculationBase, self::CALCULATION_BASE_VALUES, true)) {
            $errors[] = 'Base de calculo invalida.';
        }

        if (!in_array($reimbursability, self::REIMBURSABILITY_VALUES, true)) {
            $errors[] = 'Reembolsabilidade invalida.';
        }

        if (!in_array($predictability, self::PREDICTABILITY_VALUES, true)) {
            $errors[] = 'Previsibilidade invalida.';
        }

        if (!in_array($linkageCode, self::LINKAGE_CODES, true)) {
            $errors[] = 'Vinculo invalido. Use 309 (remuneracao) ou 510 (beneficios/auxilios).';
        }

        if (!in_array($paymentPeriodicity, self::PERIODICITY_VALUES, true)) {
            $errors[] = 'Periodicidade de pagamento invalida.';
        }

        $benefitSubcategories = ['Beneficios', 'Custos de Pessoal Indiretos'];
        if ($linkageCode === 510 && !in_array($subcategory, $benefitSubcategories, true)) {
            $errors[] = 'Vinculo 510 deve ser usado para subcategorias de beneficios/auxilios.';
        }

        if ($linkageCode === 309 && in_array($subcategory, $benefitSubcategories, true)) {
            $errors[] = 'Vinculo 309 nao deve ser usado para subcategorias de beneficios/auxilios.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'cost_code' => $costCode,
                'name' => $name,
                'type_description' => $typeDescription,
                'macro_category' => $macroCategory,
                'subcategory' => $subcategory,
                'expense_nature' => $expenseNature,
                'calculation_base' => $calculationBase,
                'charge_incidence' => $chargeIncidence,
                'reimbursability' => $reimbursability,
                'predictability' => $predictability,
                'linkage_code' => $linkageCode,
                'is_reimbursable' => $reimbursability === 'nao_reembolsavel' ? 0 : 1,
                'payment_periodicity' => $paymentPeriodicity,
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function eventPayload(array $data): array
    {
        return [
            'cost_code' => $data['cost_code'],
            'name' => $data['name'],
            'macro_category' => $data['macro_category'],
            'subcategory' => $data['subcategory'],
            'expense_nature' => $data['expense_nature'],
            'calculation_base' => $data['calculation_base'],
            'charge_incidence' => $data['charge_incidence'],
            'reimbursability' => $data['reimbursability'],
            'predictability' => $data['predictability'],
            'linkage_code' => $data['linkage_code'],
            'is_reimbursable' => $data['is_reimbursable'],
            'payment_periodicity' => $data['payment_periodicity'],
        ];
    }

    private function normalizeBoolInt(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true) ? 1 : 0;
    }

    private function normalizeEnum(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalizeSubcategory(mixed $value): string
    {
        $subcategory = trim((string) $value);
        if ($subcategory === '') {
            return '';
        }

        foreach (self::SUBCATEGORY_VALUES as $allowed) {
            if (mb_strtolower($allowed) === mb_strtolower($subcategory)) {
                return $allowed;
            }
        }

        return $subcategory;
    }

    private function clean(mixed $value, int $maxLength): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : mb_substr($string, 0, $maxLength);
    }
}
