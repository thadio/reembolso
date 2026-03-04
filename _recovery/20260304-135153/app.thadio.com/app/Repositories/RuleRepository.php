<?php

namespace App\Repositories;

use App\Models\Rule;
use App\Seeds\RuleSeeder;
use PDO;

use App\Support\AuditableTrait;
class RuleRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            RuleSeeder::seed($this);
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, title, content, status, created_at FROM regras ORDER BY title ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?Rule
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM regras WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Rule::fromArray($row) : null;
    }

    public function save(Rule $rule): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = $rule->id !== null;

        // Capturar dados antigos para auditoria
        $oldData = $isUpdate ? $this->find($rule->id)?->toArray() : null;

        if ($rule->id) {
            $sql = "UPDATE regras SET title = :title, content = :content, status = :status WHERE id = :id";
            $params = $rule->toDbParams() + [':id' => $rule->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO regras (title, content, status) VALUES (:title, :content, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($rule->toDbParams());
            $rule->id = (int) $this->pdo->lastInsertId();
        }

        // Auditoria
        $newData = $this->find($rule->id)?->toArray();
        $this->auditLog($isUpdate ? 'UPDATE' : 'INSERT', 'regras', $rule->id, $oldData, $newData);
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        // Capturar dados antigos para auditoria
        $oldData = $this->find($id)?->toArray();

        $stmt = $this->pdo->prepare("DELETE FROM regras WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // Auditoria
        $this->auditLog('DELETE', 'regras', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS regras (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          title VARCHAR(200) NOT NULL UNIQUE,
          content TEXT NOT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_regras_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
