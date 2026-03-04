<?php

namespace App\Repositories;

use App\Models\FinanceCategory;
use PDO;

use App\Support\AuditableTrait;
class FinanceCategoryRepository
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
        $stmt = $this->pdo->query(
            "SELECT id, name, type, description, status, created_at FROM financeiro_categorias ORDER BY name ASC"
        );
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(?string $type = null): array
    {
        if (!$this->pdo) {
            return [];
        }

        $where = "WHERE status = 'ativo'";
        $params = [];
        if ($type !== null && $type !== '') {
            $where .= " AND (type = :type OR type = 'ambos')";
            $params[':type'] = $type;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, name, type, description, status FROM financeiro_categorias {$where} ORDER BY name ASC"
        );
        $stmt->execute($params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?FinanceCategory
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM financeiro_categorias WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? FinanceCategory::fromArray($row) : null;
    }

    public function save(FinanceCategory $category): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        // Capturar dados antigos para auditoria
        $oldData = $category->id ? $this->find($category->id)?->toArray() : null;

        if ($category->id) {
            $sql = "UPDATE financeiro_categorias
                    SET name = :name, type = :type, description = :description, status = :status
                    WHERE id = :id";
            $params = $category->toDbParams() + [':id' => $category->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO financeiro_categorias (name, type, description, status)
                    VALUES (:name, :type, :description, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($category->toDbParams());
            $category->id = (int) $this->pdo->lastInsertId();
        }

        // Auditoria
        $newData = $this->find($category->id)?->toArray();
        $this->auditLog($category->id ? 'UPDATE' : 'INSERT', 'financeiro_categorias', $category->id, $oldData, $newData);
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        // Capturar dados antigos para auditoria
        $oldData = $this->find($id)?->toArray();

        $stmt = $this->pdo->prepare("DELETE FROM financeiro_categorias WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // Auditoria
        $this->auditLog('DELETE', 'financeiro_categorias', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS financeiro_categorias (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(160) NOT NULL UNIQUE,
          type VARCHAR(20) NOT NULL DEFAULT 'ambos',
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_financeiro_categorias_status (status),
          INDEX idx_financeiro_categorias_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $this->pdo->exec($sql);
    }
}
