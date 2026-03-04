<?php

namespace App\Services;

use App\Models\SalesChannel;
use App\Support\Input;

class SalesChannelService
{
    /**
     * @return array{0: SalesChannel, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $name = (string) ($input['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $channel = SalesChannel::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'description' => $input['description'] ?? null,
            'status' => $status,
        ]);

        return [$channel, $errors];
    }
}
