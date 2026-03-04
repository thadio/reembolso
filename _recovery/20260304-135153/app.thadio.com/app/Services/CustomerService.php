<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\Input;
use App\Support\Phone;

class CustomerService
{
    /**
     * @return array{0: Customer, 1: array<int, string>}
     */
    public function validate(array $input, bool $editing): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        if (empty($input['fullName'])) {
            $errors[] = 'Nome completo é obrigatório.';
        }

        if (empty($input['email'])) {
            $errors[] = 'E-mail é obrigatório.';
        } elseif (!$this->isCatalogEmailValid($input['email'])) {
            $errors[] = 'E-mail deve ser válido.';
        }

        if (!empty($input['email2']) && !$this->isCatalogEmailValid($input['email2'])) {
            $errors[] = 'E-mail secundário deve ser válido.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        if (!empty($input['pixKey']) && mb_strlen($input['pixKey']) > 180) {
            $errors[] = 'Chave PIX muito longa.';
        }

        $input['phone'] = Phone::normalizeBrazilCell($input['phone'] ?? null);

        $customer = Customer::fromArray($input);

        return [$customer, $errors];
    }

    private function isCatalogEmailValid(string $email): bool
    {
        return (bool) preg_match(
            '/^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            $email
        );
    }
}
