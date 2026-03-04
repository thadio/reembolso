<?php

namespace App\Repositories;

use App\Models\DeliveryType;
use App\Seeds\DeliveryTypeSeeder;
use PDO;

use App\Support\AuditableTrait;
class DeliveryTypeRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            DeliveryTypeSeeder::seed($this);
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, name, description, status, base_price, south_price, availability, bag_action, created_at FROM tipos_entrega ORDER BY name ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, name, description, status, base_price, south_price, availability, bag_action FROM tipos_entrega WHERE status = 'ativo' ORDER BY name ASC");
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?DeliveryType
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM tipos_entrega WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? DeliveryType::fromArray($row) : null;
    }

    public function save(DeliveryType $type): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $type->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM tipos_entrega WHERE id = :id");
            $stmt->execute([':id' => $type->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($type->id) {
            $sql = "UPDATE tipos_entrega SET name = :name, description = :description, status = :status, base_price = :base_price, south_price = :south_price, availability = :availability, bag_action = :bag_action WHERE id = :id";
            $params = $type->toDbParams() + [':id' => $type->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO tipos_entrega (name, description, status, base_price, south_price, availability, bag_action) VALUES (:name, :description, :status, :base_price, :south_price, :availability, :bag_action)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($type->toDbParams());
            $type->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM tipos_entrega WHERE id = :id");
        $stmt->execute([':id' => $type->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'tipos_entrega',
            $type->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM tipos_entrega WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM tipos_entrega WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'tipos_entrega', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS tipos_entrega (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(160) NOT NULL UNIQUE,
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
          south_price DECIMAL(10,2) NULL,
          availability VARCHAR(20) NOT NULL DEFAULT 'all',
          bag_action VARCHAR(20) NOT NULL DEFAULT 'none',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_tipos_entrega_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
        $added = $this->ensureColumn('bag_action', "ALTER TABLE tipos_entrega ADD COLUMN bag_action VARCHAR(20) NOT NULL DEFAULT 'none' AFTER availability");
        if ($added) {
            $this->seedBagActions();
        }
    }

    private function ensureColumn(string $column, string $ddl): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM tipos_entrega LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
            $this->pdo->exec($ddl);
            return true;
        }
        return false;
    }

    private function seedBagActions(): void
    {
        $defaults = [
            'Abrir sacolinha (frete de abertura)' => 'open_bag',
            'Adicionar a sacolinha (sem frete)' => 'add_to_bag',
        ];
        $stmt = $this->pdo->prepare("UPDATE tipos_entrega SET bag_action = :bag_action WHERE name = :name");
        foreach ($defaults as $name => $action) {
            $stmt->execute([
                ':bag_action' => $action,
                ':name' => $name,
            ]);
        }
    }
}
