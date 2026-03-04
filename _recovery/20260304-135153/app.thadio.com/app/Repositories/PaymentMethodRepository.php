<?php

namespace App\Repositories;

use App\Models\PaymentMethod;
use App\Seeds\PaymentMethodSeeder;
use PDO;

use App\Support\AuditableTrait;
class PaymentMethodRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            PaymentMethodSeeder::seed($this);
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, name, type, description, status, fee_type, fee_value, requires_bank_account, requires_terminal, created_at FROM metodos_pagamento ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, name, type, description, status, fee_type, fee_value, requires_bank_account, requires_terminal FROM metodos_pagamento WHERE status = 'ativo' ORDER BY name ASC");
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?PaymentMethod
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM metodos_pagamento WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? PaymentMethod::fromArray($row) : null;
    }

    public function save(PaymentMethod $method): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $method->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM metodos_pagamento WHERE id = :id");
            $stmt->execute([':id' => $method->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($method->id) {
            $sql = "UPDATE metodos_pagamento SET name = :name, type = :type, description = :description, status = :status, fee_type = :fee_type, fee_value = :fee_value, requires_bank_account = :requires_bank_account, requires_terminal = :requires_terminal WHERE id = :id";
            $params = $method->toDbParams() + [':id' => $method->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO metodos_pagamento (name, type, description, status, fee_type, fee_value, requires_bank_account, requires_terminal)
                    VALUES (:name, :type, :description, :status, :fee_type, :fee_value, :requires_bank_account, :requires_terminal)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($method->toDbParams());
            $method->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM metodos_pagamento WHERE id = :id");
        $stmt->execute([':id' => $method->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'metodos_pagamento',
            $method->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM metodos_pagamento WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM metodos_pagamento WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'metodos_pagamento', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS metodos_pagamento (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(160) NOT NULL UNIQUE,
          type VARCHAR(40) NOT NULL DEFAULT 'cash',
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          fee_type VARCHAR(20) NOT NULL DEFAULT 'none',
          fee_value DECIMAL(10,2) NOT NULL DEFAULT 0,
          requires_bank_account TINYINT(1) NOT NULL DEFAULT 0,
          requires_terminal TINYINT(1) NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_metodos_pagamento_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
