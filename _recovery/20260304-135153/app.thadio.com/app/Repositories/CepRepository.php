<?php

namespace App\Repositories;

use PDO;

class CepRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByCep(string $cep): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ceps.cep,
                    ceps.logradouro,
                    ceps.complemento,
                    ceps.bairro,
                    cities.name AS city,
                    states.abbr AS state,
                    states.name AS state_name
               FROM ceps
               LEFT JOIN cities ON cities.id = ceps.city_id
               LEFT JOIN states ON states.id = COALESCE(ceps.state_id, cities.state_id)
              WHERE ceps.cep = :cep
              LIMIT 1'
        );
        $stmt->bindValue(':cep', $cep);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
