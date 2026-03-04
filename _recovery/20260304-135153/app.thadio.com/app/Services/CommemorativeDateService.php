<?php

namespace App\Services;

use App\Models\CommemorativeDate;
use App\Support\Input;

class CommemorativeDateService
{
    /**
     * @return array{0: CommemorativeDate, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $name = (string) ($input['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        $day = (int) ($input['day'] ?? 0);
        if ($day < 1 || $day > 31) {
            $errors[] = 'Dia inválido.';
        }

        $month = (int) ($input['month'] ?? 0);
        if ($month < 1 || $month > 12) {
            $errors[] = 'Mês inválido.';
        }

        $year = null;
        $rawYear = $input['year'] ?? '';
        if ($rawYear !== '') {
            $year = (int) $rawYear;
            if ($year < 1900 || $year > 2200) {
                $errors[] = 'Ano inválido.';
            }
        }

        if ($day >= 1 && $month >= 1) {
            $checkYear = $year ?? 2000;
            if (!checkdate($month, $day, $checkYear)) {
                $errors[] = 'Data inválida.';
            }
        }

        $scope = (string) ($input['scope'] ?? 'brasil');
        $allowedScopes = ['brasil', 'mundial', 'regional', 'setorial', 'local'];
        if (!in_array($scope, $allowedScopes, true)) {
            $errors[] = 'Escopo inválido.';
        }

        $status = (string) ($input['status'] ?? 'ativo');
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $item = CommemorativeDate::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'day' => $day,
            'month' => $month,
            'year' => $year,
            'scope' => $scope,
            'category' => $input['category'] ?? null,
            'description' => $input['description'] ?? null,
            'source' => $input['source'] ?? null,
            'status' => $status,
        ]);

        return [$item, $errors];
    }
}
