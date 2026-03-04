<?php

namespace App\Repositories;

use App\Models\User;
use App\Support\AuditableTrait;
use PDO;

class UserRepository
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

    public function list(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $sql = "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status, u.profile_id, p.name AS profile_name
                FROM usuarios u
                LEFT JOIN perfis p ON p.id = u.profile_id
                ORDER BY u.created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?User
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    public function nextId(): int
    {
        if (!$this->pdo) {
            return 1;
        }
        $stmt = $this->pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'");
        $row = $stmt ? $stmt->fetch() : null;
        return $row && $row['AUTO_INCREMENT'] ? (int) $row['AUTO_INCREMENT'] : 1;
    }

    public function save(User $user): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        // Capturar old values para auditoria
        $oldValues = $user->id ? $this->find($user->id)?->toArray() : null;
        $action = $user->id ? 'UPDATE' : 'INSERT';

        if ($user->id) {
            $sql = "UPDATE usuarios SET full_name=:full_name, email=:email, phone=:phone, role=:role, status=:status, profile_id=:profile_id"
                . ($user->passwordHash ? ", password_hash=:password_hash" : "")
                . " WHERE id=:id";

            $params = $user->toDbParams() + [':id' => $user->id];
            if (!$user->passwordHash) {
                unset($params[':password_hash']);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO usuarios (full_name, email, phone, role, status, password_hash, profile_id, verification_token, verification_expires_at, verified_at)
                    VALUES (:full_name, :email, :phone, :role, :status, :password_hash, :profile_id, :verification_token, :verification_expires_at, :verified_at)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($user->toDbParams(true));
            $user->id = (int) $this->pdo->lastInsertId();
        }

        // Auditoria
        $newValues = $this->find($user->id)?->toArray();
        $this->auditLog($action, 'usuarios', $user->id, $oldValues, $newValues);
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }
        
        // Capturar old values para auditoria
        $oldValues = $this->find($id)?->toArray();
        
        $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Auditoria
        $this->auditLog('DELETE', 'usuarios', $id, $oldValues, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS usuarios (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          full_name VARCHAR(200) NOT NULL,
          email VARCHAR(180) NOT NULL UNIQUE,
          phone VARCHAR(50) NULL,
          role VARCHAR(40) NOT NULL DEFAULT 'colaborador',
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          password_hash VARCHAR(255) NOT NULL,
          profile_id INT UNSIGNED NULL,
          verification_token VARCHAR(120) NULL,
          verification_expires_at DATETIME NULL,
          verified_at DATETIME NULL,
          reset_token VARCHAR(120) NULL,
          reset_expires_at DATETIME NULL,
          reset_requested_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
        $this->ensureColumn('profile_id', "ALTER TABLE usuarios ADD COLUMN profile_id INT UNSIGNED NULL AFTER password_hash");
        $this->ensureColumn('verification_token', "ALTER TABLE usuarios ADD COLUMN verification_token VARCHAR(120) NULL AFTER profile_id");
        $this->ensureColumn('verification_expires_at', "ALTER TABLE usuarios ADD COLUMN verification_expires_at DATETIME NULL AFTER verification_token");
        $this->ensureColumn('verified_at', "ALTER TABLE usuarios ADD COLUMN verified_at DATETIME NULL AFTER verification_expires_at");
        $this->ensureColumn('reset_token', "ALTER TABLE usuarios ADD COLUMN reset_token VARCHAR(120) NULL AFTER verified_at");
        $this->ensureColumn('reset_expires_at', "ALTER TABLE usuarios ADD COLUMN reset_expires_at DATETIME NULL AFTER reset_token");
        $this->ensureColumn('reset_requested_at', "ALTER TABLE usuarios ADD COLUMN reset_requested_at DATETIME NULL AFTER reset_expires_at");
    }

    private function ensureColumn(string $column, string $ddl): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM usuarios LIKE :col");
        $stmt->execute([':col' => $column]);
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
            $this->pdo->exec($ddl);
        }
    }

    public function findByVerificationToken(string $token): ?User
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE verification_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    public function activateByToken(string $token): ?User
    {
        if (!$this->pdo) {
            return null;
        }
        $user = $this->findByVerificationToken($token);
        if (!$user || !$user->id) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET status = 'ativo', verification_token = NULL, verification_expires_at = NULL, verified_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':id' => $user->id]);
        $user->status = 'ativo';
        $user->verificationToken = null;
        $user->verificationExpiresAt = null;
        $user->verifiedAt = date('Y-m-d H:i:s');
        return $user;
    }

    public function findByResetToken(string $token): ?User
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE reset_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    public function requestPasswordReset(int $id, string $token, string $expiresAt): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET reset_token = :token, reset_expires_at = :expires_at, reset_requested_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':token' => $token,
            ':expires_at' => $expiresAt,
            ':id' => $id,
        ]);
    }

    public function resetPassword(int $id, string $passwordHash): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET password_hash = :password_hash,
                 reset_token = NULL,
                 reset_expires_at = NULL,
                 reset_requested_at = NULL
             WHERE id = :id"
        );
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $id,
        ]);
    }
}
