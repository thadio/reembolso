<?php

namespace App\Repositories;

use App\Models\PaymentTerminal;
use PDO;

use App\Support\AuditableTrait;
class PaymentTerminalRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, name, type, description, status, created_at FROM terminais_pagamento ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, name, type, description, status FROM terminais_pagamento WHERE status = 'ativo' ORDER BY name ASC");
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?PaymentTerminal
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM terminais_pagamento WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? PaymentTerminal::fromArray($row) : null;
    }

    public function save(PaymentTerminal $terminal): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $terminal->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM maquininhas WHERE id = :id");
            $stmt->execute([':id' => $terminal->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($terminal->id) {
            $sql = "UPDATE maquininhas SET name = :name, type = :type, description = :description, status = :status WHERE id = :id";
            $params = $terminal->toDbParams() + [':id' => $terminal->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO maquininhas (name, type, description, status) VALUES (:name, :type, :description, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($terminal->toDbParams());
            $terminal->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM maquininhas WHERE id = :id");
        $stmt->execute([':id' => $terminal->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'maquininhas',
            $terminal->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM maquininhas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM maquininhas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'maquininhas', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS terminais_pagamento (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(160) NOT NULL UNIQUE,
          type VARCHAR(30) NOT NULL DEFAULT 'both',
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_terminais_pagamento_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
