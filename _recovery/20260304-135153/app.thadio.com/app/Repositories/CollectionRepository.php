<?php

namespace App\Repositories;

use App\Models\Collection;
use PDO;

use App\Support\AuditableTrait;
class CollectionRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela colecoes: ' . $e->getMessage());
            }
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT * FROM colecoes ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?Collection
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM colecoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Collection::fromArray($row) : null;
    }

    public function save(Collection $collection): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $collection->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM colecoes WHERE id = :id");
            $stmt->execute([':id' => $collection->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($collection->id) {
            $sql = "UPDATE colecoes SET
              name = :name, main_menu_image = :main_menu_image, external_id = :external_id,
              slug = :slug, page_url = :page_url
              WHERE id = :id";
            $params = $collection->toDbParams() + [':id' => $collection->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO colecoes (
              name, main_menu_image, external_id, slug, page_url
            ) VALUES (
              :name, :main_menu_image, :external_id, :slug, :page_url
            )";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($collection->toDbParams());
            $collection->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM colecoes WHERE id = :id");
        $stmt->execute([':id' => $collection->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'colecoes',
            $collection->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM colecoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM colecoes WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'colecoes', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        if (!$this->pdo) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS colecoes (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(200) NOT NULL,
          main_menu_image TEXT NULL,
          external_id VARCHAR(120) NULL,
          slug VARCHAR(200) NULL,
          page_url VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_colecoes_external_id (external_id),
          UNIQUE KEY uniq_colecoes_slug (slug),
          INDEX idx_colecoes_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
