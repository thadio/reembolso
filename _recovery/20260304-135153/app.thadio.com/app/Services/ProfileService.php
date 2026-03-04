<?php

namespace App\Services;

use App\Models\Profile;
use App\Support\Input;
use App\Support\Permissions;

class ProfileService
{
    /**
     * @return array{0: Profile, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        if (empty($input['name'])) {
            $errors[] = 'Nome é obrigatório.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $permissions = Permissions::normalize($input['permissions'] ?? []);
        if (empty($permissions)) {
            $errors[] = 'Selecione pelo menos uma permissão.';
        }

        $profile = Profile::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $input['name'] ?? '',
            'description' => $input['description'] ?? null,
            'status' => $status,
            'permissions' => $permissions,
        ]);

        return [$profile, $errors];
    }
}
