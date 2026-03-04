<?php

namespace App\Repositories;

use App\Models\BankAccount;
use PDO;

use App\Support\AuditableTrait;
class BankAccountRepository
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
        $sql = "SELECT c.id, c.bank_id, c.label, c.holder, c.branch, c.account_number, c.pix_key, c.pix_key_type, c.status, c.description,
                       c.created_at, b.name AS bank_name, b.code AS bank_code
                FROM contas_bancarias c
                LEFT JOIN bancos b ON b.id = c.bank_id
                ORDER BY b.name ASC, c.label ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $sql = "SELECT c.id, c.bank_id, c.label, c.holder, c.branch, c.account_number, c.pix_key, c.pix_key_type, c.status, c.description,
                       b.name AS bank_name, b.code AS bank_code
                FROM contas_bancarias c
                LEFT JOIN bancos b ON b.id = c.bank_id
                WHERE c.status = 'ativo'
                ORDER BY b.name ASC, c.label ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?BankAccount
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM contas_bancarias WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? BankAccount::fromArray($row) : null;
    }

    public function save(BankAccount $account): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $account->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM contas_bancarias WHERE id = :id");
            $stmt->execute([':id' => $account->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($account->id) {
            $sql = "UPDATE contas_bancarias
                    SET bank_id = :bank_id, label = :label, holder = :holder, branch = :branch, account_number = :account_number,
                        pix_key = :pix_key, pix_key_type = :pix_key_type, description = :description, status = :status
                    WHERE id = :id";
            $params = $account->toDbParams() + [':id' => $account->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO contas_bancarias (bank_id, label, holder, branch, account_number, pix_key, pix_key_type, description, status)
                    VALUES (:bank_id, :label, :holder, :branch, :account_number, :pix_key, :pix_key_type, :description, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($account->toDbParams());
            $account->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM contas_bancarias WHERE id = :id");
        $stmt->execute([':id' => $account->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'contas_bancarias',
            $account->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM contas_bancarias WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM contas_bancarias WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'contas_bancarias', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS contas_bancarias (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          bank_id INT UNSIGNED NOT NULL,
          label VARCHAR(160) NOT NULL,
          holder VARCHAR(160) NULL,
          branch VARCHAR(40) NULL,
          account_number VARCHAR(60) NULL,
          pix_key VARCHAR(160) NULL,
          pix_key_type VARCHAR(40) NULL,
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_contas_bancarias_status (status),
          INDEX idx_contas_bancarias_bank (bank_id),
          CONSTRAINT fk_contas_bancarias_bank FOREIGN KEY (bank_id) REFERENCES bancos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
