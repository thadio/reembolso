<?php

namespace App\Seeds;

use App\Repositories\SalesChannelRepository;

class SalesChannelSeeder
{
    public static function seed(SalesChannelRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $existingStmt = $pdo->query("SELECT name FROM canais_venda");
        $existing = $existingStmt ? $existingStmt->fetchAll(\PDO::FETCH_COLUMN) : [];
        $existing = array_map(static function ($name): string {
            return trim(strtolower((string) $name));
        }, $existing);

        $sql = "INSERT INTO canais_venda (name, description, status) VALUES (:name, :description, :status)";
        $insert = $pdo->prepare($sql);

        foreach (self::defaults() as $row) {
            $normalizedName = trim(strtolower($row['name']));
            if (in_array($normalizedName, $existing, true)) {
                continue;
            }
            try {
                $insert->execute([
                    ':name' => $row['name'],
                    ':description' => $row['description'],
                    ':status' => $row['status'],
                ]);
                $existing[] = $normalizedName;
            } catch (\PDOException $e) {
                $errorInfo = $e->errorInfo ?? [];
                if ((int) ($errorInfo[1] ?? 0) === 1062) {
                    $existing[] = $normalizedName;
                    continue;
                }
                throw $e;
            }
        }
    }

    private static function defaults(): array
    {
        return [
            ['name' => 'Instagram', 'description' => null, 'status' => 'ativo'],
            ['name' => 'WhatsApp', 'description' => null, 'status' => 'ativo'],
            ['name' => 'Site', 'description' => null, 'status' => 'ativo'],
            ['name' => 'Loja física', 'description' => null, 'status' => 'ativo'],
        ];
    }
}
