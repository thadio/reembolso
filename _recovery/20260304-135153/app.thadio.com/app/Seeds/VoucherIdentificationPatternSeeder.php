<?php

namespace App\Seeds;

use App\Repositories\VoucherIdentificationPatternRepository;

class VoucherIdentificationPatternSeeder
{
    public static function seed(VoucherIdentificationPatternRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $existingStmt = $pdo->query("SELECT label FROM cupons_creditos_identificacoes");
        $existing = $existingStmt ? $existingStmt->fetchAll(\PDO::FETCH_COLUMN) : [];
        $existing = array_map(static function ($label): string {
            return trim(strtolower((string) $label));
        }, $existing);

        $sql = "INSERT INTO cupons_creditos_identificacoes (label, description, status) VALUES (:label, :description, :status)";
        $insert = $pdo->prepare($sql);

        foreach (self::defaults() as $row) {
            $normalizedLabel = trim(strtolower($row['label']));
            if (in_array($normalizedLabel, $existing, true)) {
                continue;
            }
            try {
                $insert->execute([
                    ':label' => $row['label'],
                    ':description' => $row['description'],
                    ':status' => $row['status'],
                ]);
                $existing[] = $normalizedLabel;
            } catch (\PDOException $e) {
                $errorInfo = $e->errorInfo ?? [];
                if ((int) ($errorInfo[1] ?? 0) === 1062) {
                    $existing[] = $normalizedLabel;
                    continue;
                }
                throw $e;
            }
        }
    }

    private static function defaults(): array
    {
        return [
            [
                'label' => 'Crédito por troca com diferença de valor (produto mais caro por mais barato)',
                'description' => 'Usar quando a cliente troca por um item de menor valor e fica com saldo.',
                'status' => 'ativo',
            ],
            [
                'label' => 'Desconto por avaria de produto',
                'description' => 'Concedido por pequenas avarias identificadas no produto.',
                'status' => 'ativo',
            ],
            [
                'label' => 'Cupom promocional (sorteios e ações de marketing)',
                'description' => 'Distribuído em campanhas, sorteios e ações promocionais.',
                'status' => 'ativo',
            ],
            [
                'label' => 'Cupom presente (cartão presente)',
                'description' => 'Crédito concedido como presente ou vale presente.',
                'status' => 'ativo',
            ],
        ];
    }
}
