<?php

namespace App\Services;

use App\Models\User;
use App\Support\Input;

class UserService
{
    /**
     * @return array{0: User, 1: array<int, string>}
     */
    public function validate(array $input, bool $editing): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $required = [
            'fullName' => 'Nome completo',
            'email' => 'E-mail',
        ];

        foreach ($required as $field => $label) {
            if (!isset($input[$field]) || $input[$field] === '') {
                $errors[] = "{$label} é obrigatório.";
            }
        }

        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail deve ser válido.';
        }

        if ($editing && empty($input['profileId'])) {
            $errors[] = 'Perfil é obrigatório.';
        }

        $passwordProvided = isset($input['password']) && $input['password'] !== '';
        if (!$editing || $passwordProvided) {
            if (empty($input['password'])) {
                $errors[] = 'Senha é obrigatória.';
            }
            if (($input['password'] ?? '') !== ($input['confirmPassword'] ?? '')) {
                $errors[] = 'As senhas não conferem.';
            }
        }

        $user = User::fromArray($input);
        $user->profileId = isset($input['profileId']) && $input['profileId'] !== '' ? (int) $input['profileId'] : null;
        if ($user->profileId && empty($user->role)) {
            $user->role = 'perfil';
        }
        $passwordRaw = (string) ($input['password'] ?? '');
        if ($passwordRaw !== '' && ($passwordProvided || !$editing)) {
            $user->passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
        }

        return [$user, $errors];
    }
}
