<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CostItemCatalogRepository;

final class CostItemCatalogService
{
    private const LINKAGE_CODES = [309, 510];
    private const PERIODICITY_VALUES = ['mensal', 'anual', 'unico'];

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
        string $reimbursable,
        string $periodicity,
        string $sort,
        string $dir,
        int $page,
        int $perPage
    ): array {
        return $this->items->paginate($query, $linkage, $reimbursable, $periodicity, $sort, $dir, $page, $perPage);
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
    public function reimbursableOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Parcela reembolsavel'],
            ['value' => '0', 'label' => 'Parcela nao-reembolsavel'],
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function periodicityOptions(): array
    {
        return [
            ['value' => 'mensal', 'label' => 'Mensal'],
            ['value' => 'anual', 'label' => 'Anual'],
            ['value' => 'unico', 'label' => 'Unico'],
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
            name: (string) $data['name'],
            linkageCode: (int) $data['linkage_code'],
            isReimbursable: (int) $data['is_reimbursable'],
            paymentPeriodicity: (string) $data['payment_periodicity']
        )) {
            return [
                'ok' => false,
                'errors' => ['Ja existe item de custo com esta combinacao de vinculo/parcela/periodicidade.'],
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
            payload: [
                'name' => $data['name'],
                'linkage_code' => $data['linkage_code'],
                'is_reimbursable' => $data['is_reimbursable'],
                'payment_periodicity' => $data['payment_periodicity'],
            ],
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
            name: (string) $data['name'],
            linkageCode: (int) $data['linkage_code'],
            isReimbursable: (int) $data['is_reimbursable'],
            paymentPeriodicity: (string) $data['payment_periodicity'],
            ignoreId: $id
        )) {
            return [
                'ok' => false,
                'errors' => ['Ja existe item de custo com esta combinacao de vinculo/parcela/periodicidade.'],
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
            payload: [
                'name' => $data['name'],
                'linkage_code' => $data['linkage_code'],
                'is_reimbursable' => $data['is_reimbursable'],
                'payment_periodicity' => $data['payment_periodicity'],
            ],
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
        $name = $this->clean($input['name'] ?? ($before['name'] ?? null));
        $linkageCode = (int) ($input['linkage_code'] ?? ($before['linkage_code'] ?? 0));
        $isReimbursable = $this->normalizeBoolInt($input['is_reimbursable'] ?? ($before['is_reimbursable'] ?? 1));
        $paymentPeriodicity = mb_strtolower(trim((string) ($input['payment_periodicity'] ?? ($before['payment_periodicity'] ?? ''))));

        $errors = [];
        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do item de custo e obrigatorio (minimo 3 caracteres).';
        }
        if (!in_array($linkageCode, self::LINKAGE_CODES, true)) {
            $errors[] = 'Vinculo invalido. Use 309 (remuneracao) ou 510 (beneficios/auxilios).';
        }
        if (!in_array($paymentPeriodicity, self::PERIODICITY_VALUES, true)) {
            $errors[] = 'Periodicidade de pagamento invalida.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'linkage_code' => $linkageCode,
                'is_reimbursable' => $isReimbursable,
                'payment_periodicity' => $paymentPeriodicity,
            ],
        ];
    }

    private function normalizeBoolInt(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true) ? 1 : 0;
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : mb_substr($string, 0, 190);
    }
}
