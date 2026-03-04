<?php

namespace App\Repositories;

use PDO;

class PeopleCompatViewRepository
{
    public static function ensure(?PDO $pdo): void
    {
        if (!$pdo || !\function_exists('shouldRunSchemaMigrations') || !\shouldRunSchemaMigrations()) {
            return;
        }

        // Garante que as tabelas-base existem
        new PersonRepository($pdo);
        new PersonRoleRepository($pdo);

        self::createCustomersView($pdo);
        self::createVendorsView($pdo);
    }

    private static function createCustomersView(PDO $pdo): void
    {
        $sql = "CREATE OR REPLACE VIEW vw_clientes_compat AS
            SELECT
              p.id,
              p.full_name,
              p.email,
              p.email2,
              p.phone,
              p.status,
              p.cpf_cnpj,
              p.pix_key,
              p.instagram,
              p.street,
              p.street2,
              p.number,
              p.neighborhood,
              p.city,
              p.state,
              p.zip,
              p.country,
              p.metadata,
              p.created_at,
              p.updated_at
            FROM pessoas p
            INNER JOIN pessoas_papeis pr ON pr.pessoa_id = p.id AND pr.role = 'cliente';";
        $pdo->exec($sql);
    }

    private static function createVendorsView(PDO $pdo): void
    {
        $sql = "CREATE OR REPLACE VIEW vw_fornecedores_compat AS
            SELECT
              p.id,
              COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.vendor_code')) AS UNSIGNED), p.id) AS id_vendor,
              p.full_name,
              p.email,
              p.email2,
              p.phone,
              p.instagram,
              p.cpf_cnpj,
              p.pix_key,
              CAST(JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.vendor_commission_rate')) AS DECIMAL(5,2)) AS commission_rate,
              p.street,
              p.street2,
              p.number,
              p.neighborhood,
              p.city,
              p.state,
              p.zip,
              p.country AS pais,
              p.metadata,
              p.created_at,
              p.updated_at
            FROM pessoas p
            INNER JOIN pessoas_papeis pr ON pr.pessoa_id = p.id AND pr.role = 'fornecedor';";
        $pdo->exec($sql);
    }
}
