<?php

namespace App\Seeds;

use App\Repositories\DeliveryTypeRepository;

class DeliveryTypeSeeder
{
    public static function seed(DeliveryTypeRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $existsStmt = $pdo->prepare("SELECT 1 FROM tipos_entrega WHERE name = :name LIMIT 1");

        $sql = "INSERT INTO tipos_entrega (name, description, status, base_price, south_price, availability, bag_action)
                VALUES (:name, :description, :status, :base_price, :south_price, :availability, :bag_action)";
        $insert = $pdo->prepare($sql);

        foreach (self::defaults() as $row) {
            $existsStmt->execute([':name' => $row['name']]);
            if ($existsStmt->fetchColumn()) {
                continue;
            }
            $insert->execute([
                ':name' => $row['name'],
                ':description' => $row['description'],
                ':status' => $row['status'],
                ':base_price' => $row['base_price'],
                ':south_price' => $row['south_price'],
                ':availability' => $row['availability'],
                ':bag_action' => $row['bag_action'],
            ]);
        }
    }

    private static function defaults(): array
    {
        return [
            [
                'name' => 'Entrega imediata em mãos',
                'description' => 'Entrega presencial no ato da venda.',
                'status' => 'ativo',
                'base_price' => 0.00,
                'south_price' => null,
                'availability' => 'all',
                'bag_action' => 'none',
            ],
            [
                'name' => 'Reserva para retirada em loja',
                'description' => 'Cliente retira em até 5 dias.',
                'status' => 'ativo',
                'base_price' => 0.00,
                'south_price' => null,
                'availability' => 'all',
                'bag_action' => 'none',
            ],
            [
                'name' => 'Abrir sacolinha (frete de abertura)',
                'description' => 'Abertura de sacolinha com frete único.',
                'status' => 'ativo',
                'base_price' => 35.00,
                'south_price' => null,
                'availability' => 'all',
                'bag_action' => 'open_bag',
            ],
            [
                'name' => 'Adicionar a sacolinha (sem frete)',
                'description' => 'Inclui itens em sacolinha já aberta.',
                'status' => 'ativo',
                'base_price' => 0.00,
                'south_price' => null,
                'availability' => 'all',
                'bag_action' => 'add_to_bag',
            ],
            [
                'name' => 'Envio por logística (frete base)',
                'description' => 'Frete base nacional; Sul com valor diferenciado.',
                'status' => 'ativo',
                'base_price' => 29.00,
                'south_price' => 35.00,
                'availability' => 'all',
                'bag_action' => 'none',
            ],
            [
                'name' => 'Entrega local via Uber/Motoboy (DF)',
                'description' => 'Somente Distrito Federal.',
                'status' => 'ativo',
                'base_price' => 30.00,
                'south_price' => null,
                'availability' => 'df_only',
                'bag_action' => 'none',
            ],
        ];
    }
}
