<?php

namespace App\Repositories;

use App\Models\Bank;
use PDO;

use App\Support\AuditableTrait;
class BankRepository
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
        $stmt = $this->pdo->query("SELECT id, name, code, description, status, created_at FROM bancos ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, name, code, description, status FROM bancos WHERE status = 'ativo' ORDER BY name ASC");
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?Bank
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM bancos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Bank::fromArray($row) : null;
    }

    public function save(Bank $bank): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $bank->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM bancos WHERE id = :id");
            $stmt->execute([':id' => $bank->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($bank->id) {
            $sql = "UPDATE bancos SET name = :name, code = :code, description = :description, status = :status WHERE id = :id";
            $params = $bank->toDbParams() + [':id' => $bank->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO bancos (name, code, description, status) VALUES (:name, :code, :description, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bank->toDbParams());
            $bank->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM bancos WHERE id = :id");
        $stmt->execute([':id' => $bank->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'bancos',
            $bank->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM bancos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM bancos WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'bancos', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS bancos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(160) NOT NULL UNIQUE,
          code VARCHAR(20) NULL,
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_bancos_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
