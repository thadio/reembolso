<?php

namespace App\Seeds;

use App\Support\Permissions;
use App\Repositories\ProfileRepository;

class ProfileSeeder
{
    public static function seed(ProfileRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO perfis (name, description, status, permissions) VALUES (:name, :description, :status, :permissions)";
        $insert = $pdo->prepare($sql);

        $existingStmt = $pdo->query("SELECT name FROM perfis");
        $existing = $existingStmt ? $existingStmt->fetchAll(\PDO::FETCH_COLUMN) : [];

        foreach (self::defaults() as $row) {
            if (in_array($row['name'], $existing, true)) {
                continue;
            }
            $insert->execute([
                ':name' => $row['name'],
                ':description' => $row['description'],
                ':status' => $row['status'],
                ':permissions' => json_encode($row['permissions'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private static function defaults(): array
    {
        $profiles = Permissions::defaultProfiles();

        return [
            [
                'name' => 'Sem Acesso',
                'description' => 'Usuário sem permissões liberadas.',
                'status' => 'ativo',
                'permissions' => [],
            ],
            [
                'name' => 'Administrador',
                'description' => 'Acesso irrestrito a todos os módulos.',
                'status' => 'ativo',
                'permissions' => $profiles['Administrador'],
            ],
            [
                'name' => 'Administrador de Sistema',
                'description' => 'Acesso irrestrito com foco em parâmetros do sistema.',
                'status' => 'ativo',
                'permissions' => $profiles['Administrador de Sistema'],
            ],
            [
                'name' => 'Gestor',
                'description' => 'Pode operar cadastros principais, sem exclusões críticas.',
                'status' => 'ativo',
                'permissions' => $profiles['Gestor'],
            ],
            [
                'name' => 'Colaborador',
                'description' => 'Acesso somente leitura aos cadastros.',
                'status' => 'ativo',
                'permissions' => $profiles['Colaborador'],
            ],
        ];
    }
}
