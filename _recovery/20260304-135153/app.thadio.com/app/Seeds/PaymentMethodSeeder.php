<?php

namespace App\Seeds;

use App\Repositories\PaymentMethodRepository;

class PaymentMethodSeeder
{
    public static function seed(PaymentMethodRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $existingStmt = $pdo->query("SELECT name FROM metodos_pagamento");
        $existing = $existingStmt ? $existingStmt->fetchAll(\PDO::FETCH_COLUMN) : [];
        $existing = array_map('strtolower', $existing);

        $sql = "INSERT INTO metodos_pagamento (name, type, description, status, fee_type, fee_value, requires_bank_account, requires_terminal)
                VALUES (:name, :type, :description, :status, :fee_type, :fee_value, :requires_bank_account, :requires_terminal)";
        $insert = $pdo->prepare($sql);

        foreach (self::defaults() as $row) {
            if (in_array(strtolower($row['name']), $existing, true)) {
                continue;
            }
            $insert->execute([
                ':name' => $row['name'],
                ':type' => $row['type'],
                ':description' => $row['description'],
                ':status' => $row['status'],
                ':fee_type' => $row['fee_type'],
                ':fee_value' => $row['fee_value'],
                ':requires_bank_account' => $row['requires_bank_account'],
                ':requires_terminal' => $row['requires_terminal'],
            ]);
        }
    }

    private static function defaults(): array
    {
        return [
            [
                'name' => 'Dinheiro',
                'type' => 'cash',
                'description' => null,
                'status' => 'ativo',
                'fee_type' => 'none',
                'fee_value' => 0.0,
                'requires_bank_account' => 0,
                'requires_terminal' => 0,
            ],
            [
                'name' => 'PIX',
                'type' => 'pix',
                'description' => null,
                'status' => 'ativo',
                'fee_type' => 'none',
                'fee_value' => 0.0,
                'requires_bank_account' => 1,
                'requires_terminal' => 0,
            ],
            [
                'name' => 'Cartão de débito',
                'type' => 'debit_card',
                'description' => null,
                'status' => 'ativo',
                'fee_type' => 'percent',
                'fee_value' => 0.0,
                'requires_bank_account' => 0,
                'requires_terminal' => 1,
            ],
            [
                'name' => 'Cartão de crédito',
                'type' => 'credit_card',
                'description' => null,
                'status' => 'ativo',
                'fee_type' => 'percent',
                'fee_value' => 0.0,
                'requires_bank_account' => 0,
                'requires_terminal' => 1,
            ],
            [
                'name' => 'Cupom/crédito',
                'type' => 'voucher',
                'description' => null,
                'status' => 'ativo',
                'fee_type' => 'none',
                'fee_value' => 0.0,
                'requires_bank_account' => 0,
                'requires_terminal' => 0,
            ],
        ];
    }
}
