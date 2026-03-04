<?php

namespace App\Repositories;

use App\Models\Profile;
use App\Seeds\ProfileSeeder;
use PDO;

use App\Support\AuditableTrait;
class ProfileRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            ProfileSeeder::seed($this);
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, name, description, status, permissions FROM perfis ORDER BY created_at DESC");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return $this->upgradeRows($rows);
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, name, description, status, permissions FROM perfis WHERE status = 'ativo' ORDER BY name");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $this->upgradeRows($rows);
    }

    public function find(int $id): ?Profile
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM perfis WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $this->hydrate($row);
    }

    public function findByName(string $name): ?Profile
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM perfis WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $this->hydrate($row);
    }

    public function save(Profile $profile): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $profile->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM perfis WHERE id = :id");
            $stmt->execute([':id' => $profile->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($profile->id) {
            $sql = "UPDATE perfis SET name=:name, description=:description, status=:status, permissions=:permissions WHERE id=:id";
            $params = $profile->toDbParams() + [':id' => $profile->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO perfis (name, description, status, permissions) VALUES (:name, :description, :status, :permissions)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($profile->toDbParams());
            $profile->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM perfis WHERE id = :id");
        $stmt->execute([':id' => $profile->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'perfis',
            $profile->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }
        if ($this->isInUse($id)) {
            throw new \RuntimeException('Perfil está associado a usuários e não pode ser excluído.');
        }

        $stmt = $this->pdo->prepare("SELECT * FROM perfis WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM perfis WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'perfis', $id, $oldData, null);
    }

    public function isInUse(int $id): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE profile_id = :id");
        $stmt->execute([':id' => $id]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS perfis (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(120) NOT NULL UNIQUE,
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          permissions JSON NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
        $this->ensureColumn('description', "ALTER TABLE perfis ADD COLUMN description TEXT NULL AFTER name");
        $this->ensureColumn('status', "ALTER TABLE perfis ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ativo' AFTER description");
        $this->ensureColumn('permissions', "ALTER TABLE perfis ADD COLUMN permissions JSON NOT NULL AFTER status");
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM perfis LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
            $this->pdo->exec($ddl);
        }
    }

    /**
     * Garante atualização das permissões legadas e devolve Profile.
     */
    private function hydrate($row): ?Profile
    {
        if (!$row) {
            return null;
        }
        $profile = Profile::fromArray($row);
        $upgraded = \App\Support\Permissions::upgradeLegacy($profile->permissions);

        if ($this->pdo && $profile->id && $upgraded !== $profile->permissions) {
            $profile->permissions = $upgraded;
            $stmt = $this->pdo->prepare("UPDATE perfis SET permissions = :permissions WHERE id = :id");
            $stmt->execute([
                ':permissions' => json_encode($upgraded, JSON_UNESCAPED_UNICODE),
                ':id' => $profile->id,
            ]);
        } else {
            $profile->permissions = $upgraded;
        }

        return $profile;
    }

    /**
     * Atualiza permissões em linhas simples (sem instanciar Model).
     *
     * @param array $rows
     * @return array
     */
    private function upgradeRows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $profile = $this->hydrate($row);
            if ($profile) {
                $result[] = [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'status' => $profile->status,
                    'permissions' => json_encode($profile->permissions, JSON_UNESCAPED_UNICODE),
                ];
            }
        }
        return $result;
    }
}
