<?php

namespace App\Repositories;

use App\Models\SalesChannel;
use App\Seeds\SalesChannelSeeder;
use PDO;

use App\Support\AuditableTrait;
class SalesChannelRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            SalesChannelSeeder::seed($this);
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, name, description, status, created_at FROM canais_venda ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, name, description, status FROM canais_venda WHERE status = 'ativo' ORDER BY name ASC");
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function activeNames(): array
    {
        $rows = $this->active();
        $options = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $options[] = $name;
            }
        }
        return $options;
    }

    public function find(int $id): ?SalesChannel
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM canais_venda WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? SalesChannel::fromArray($row) : null;
    }

    public function save(SalesChannel $channel): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $channel->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM canais_venda WHERE id = :id");
            $stmt->execute([':id' => $channel->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($channel->id) {
            $sql = "UPDATE canais_venda SET name = :name, description = :description, status = :status WHERE id = :id";
            $params = $channel->toDbParams() + [':id' => $channel->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO canais_venda (name, description, status) VALUES (:name, :description, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($channel->toDbParams());
            $channel->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM canais_venda WHERE id = :id");
        $stmt->execute([':id' => $channel->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'canais_venda',
            $channel->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM canais_venda WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM canais_venda WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'canais_venda', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS canais_venda (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(120) NOT NULL UNIQUE,
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_canais_venda_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
