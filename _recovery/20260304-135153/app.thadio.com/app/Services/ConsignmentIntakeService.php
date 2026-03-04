<?php

namespace App\Services;

use App\Models\ConsignmentIntake;
use App\Support\Input;

class ConsignmentIntakeService
{
    /**
     * @return array{0: ConsignmentIntake, 1: array<int, array{category_id: int, quantity: int}>, 2: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $personId = isset($input['pessoa_id']) ? (int) $input['pessoa_id'] : 0;
        if ($personId <= 0) {
            $personId = isset($input['vendor_id']) ? (int) $input['vendor_id'] : 0; // compat de payload legado
        }

        if ($personId <= 0) {
            $errors[] = 'Selecione um fornecedor válido.';
        }

        $receivedAt = $this->normalizeDate($input['received_at'] ?? '');
        if ($receivedAt === null) {
            $errors[] = 'Data de recebimento inválida.';
            $receivedAt = (string) ($input['received_at'] ?? '');
        }

        $items = $this->parseItems($input['category_id'] ?? [], $input['category_qty'] ?? [], $errors);
        if (empty($items)) {
            $errors[] = 'Informe ao menos uma categoria com quantidade.';
        }

        $intake = ConsignmentIntake::fromArray([
            'id' => $input['id'] ?? null,
            'pessoa_id' => $personId,
            'received_at' => $receivedAt,
            'notes' => $input['notes'] ?? null,
        ]);

        return [$intake, $items, $errors];
    }

    /**
     * @return array{0: array{returned_at: string, notes: ?string}, 1: array<int, array{category_id: int, quantity: int}>, 2: array<int, string>}
     */
    public function validateReturn(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $returnedAt = $this->normalizeDate($input['return_date'] ?? '');
        if ($returnedAt === null) {
            $errors[] = 'Data de devolução inválida.';
            $returnedAt = (string) ($input['return_date'] ?? '');
        }

        $items = $this->parseItems($input['return_category_id'] ?? [], $input['return_category_qty'] ?? [], $errors);
        if (empty($items)) {
            $errors[] = 'Informe ao menos uma categoria para devolução.';
        }

        return [
            [
                'returned_at' => $returnedAt,
                'notes' => $input['return_notes'] ?? null,
            ],
            $items,
            $errors,
        ];
    }

    /**
     * @param array<int, mixed> $categoryIds
     * @param array<int, mixed> $quantities
     * @param array<int, string> $errors
     * @return array<int, array{category_id: int, quantity: int}>
     */
    private function parseItems(array $categoryIds, array $quantities, array &$errors): array
    {
        $items = [];
        $count = max(count($categoryIds), count($quantities));

        for ($i = 0; $i < $count; $i++) {
            $rawCategory = $categoryIds[$i] ?? '';
            $rawQty = $quantities[$i] ?? '';

            if ($rawCategory === '' && $rawQty === '') {
                continue;
            }

            if ($rawQty === '' || $rawQty === null) {
                continue;
            }

            $categoryId = (int) $rawCategory;
            if ($categoryId <= 0) {
                $errors[] = 'Selecione uma categoria válida.';
                continue;
            }

            $qty = (int) $rawQty;
            if ($qty <= 0) {
                $errors[] = 'Quantidade deve ser maior que zero.';
                continue;
            }

            if (!isset($items[$categoryId])) {
                $items[$categoryId] = 0;
            }
            $items[$categoryId] += $qty;
        }

        $normalized = [];
        foreach ($items as $categoryId => $qty) {
            $normalized[] = [
                'category_id' => (int) $categoryId,
                'quantity' => (int) $qty,
            ];
        }

        return $normalized;
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return null;
        }

        return $date;
    }
}
