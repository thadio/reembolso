<?php

namespace App\Services;

use App\Models\Vendor;
use App\Support\Input;
use App\Support\Phone;

class VendorService
{
    /**
     * Normaliza e valida os dados do formulário.
     *
     * @return array{0: Vendor, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        if (empty($input['idVendor']) && empty($input['id_vendor'])) {
            $errors[] = 'idVendor é obrigatório.';
        }
        if (empty($input['fullName']) && empty($input['full_name'])) {
            $errors[] = 'Nome completo é obrigatório.';
        }

        $email = $input['email'] ?? '';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Por favor, insira um e-mail válido.';
        }

        $pixKey = $input['pixKey'] ?? $input['pix_key'] ?? '';
        if ($pixKey !== '' && mb_strlen($pixKey) > 180) {
            $errors[] = 'Chave PIX muito longa.';
        }

        $input['phone'] = Phone::normalizeBrazilCell($input['phone'] ?? null);

        $vendor = Vendor::fromArray($input);

        return [$vendor, $errors];
    }
}
