<?php

namespace App\Repositories;

use App\Models\VoucherIdentificationPattern;
use App\Seeds\VoucherIdentificationPatternSeeder;
use PDO;

use App\Support\AuditableTrait;
class VoucherIdentificationPatternRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
            VoucherIdentificationPatternSeeder::seed($this);
        }
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, label, description, status, created_at FROM cupons_creditos_identificacoes ORDER BY label ASC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, label, description, status FROM cupons_creditos_identificacoes WHERE status = 'ativo' ORDER BY label ASC");
        $stmt->execute();
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?VoucherIdentificationPattern
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM cupons_creditos_identificacoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? VoucherIdentificationPattern::fromArray($row) : null;
    }

    public function save(VoucherIdentificationPattern $pattern): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $pattern->id;
        $oldData = null;
        
        if ($isUpdate) {
            $stmt = $this->pdo->prepare("SELECT * FROM voucher_identification_patterns WHERE id = :id");
            $stmt->execute([':id' => $pattern->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($pattern->id) {
            $sql = "UPDATE voucher_identification_patterns SET label = :label, description = :description, status = :status WHERE id = :id";
            $params = $pattern->toDbParams() + [':id' => $pattern->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO voucher_identification_patterns (label, description, status) VALUES (:label, :description, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($pattern->toDbParams());
            $pattern->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM voucher_identification_patterns WHERE id = :id");
        $stmt->execute([':id' => $pattern->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'voucher_identification_patterns',
            $pattern->id,
            $oldData,
            $newData
        );
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM voucher_identification_patterns WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("DELETE FROM voucher_identification_patterns WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $this->auditLog('DELETE', 'voucher_identification_patterns', $id, $oldData, null);
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS cupons_creditos_identificacoes (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          label VARCHAR(160) NOT NULL UNIQUE,
          description TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_cupons_creditos_identificacoes_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
