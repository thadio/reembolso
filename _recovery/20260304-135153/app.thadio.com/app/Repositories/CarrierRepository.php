<?php

namespace App\Repositories;

use App\Models\Carrier;
use App\Support\AuditableTrait;
use PDO;

class CarrierRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query(
            "SELECT id, name, carrier_type, site_url, tracking_url_template, status, notes, created_at
             FROM carriers
             ORDER BY name ASC"
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function active(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, name, carrier_type, site_url, tracking_url_template, status, notes
             FROM carriers
             WHERE status = 'ativo'
             ORDER BY name ASC"
        );
        $stmt->execute();
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function find(int $id): ?Carrier
    {
        if (!$this->pdo || $id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM carriers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Carrier::fromArray($row) : null;
    }

    public function save(Carrier $carrier): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $isUpdate = (bool) $carrier->id;
        $oldData = null;
        if ($isUpdate) {
            $stmt = $this->pdo->prepare('SELECT * FROM carriers WHERE id = :id');
            $stmt->execute([':id' => $carrier->id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($carrier->id) {
            $sql = "UPDATE carriers
                    SET name = :name,
                        carrier_type = :carrier_type,
                        site_url = :site_url,
                        tracking_url_template = :tracking_url_template,
                        status = :status,
                        notes = :notes
                    WHERE id = :id";
            $params = $carrier->toDbParams() + [':id' => $carrier->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO carriers
                    (name, carrier_type, site_url, tracking_url_template, status, notes)
                    VALUES
                    (:name, :carrier_type, :site_url, :tracking_url_template, :status, :notes)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($carrier->toDbParams());
            $carrier->id = (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare('SELECT * FROM carriers WHERE id = :id');
        $stmt->execute([':id' => $carrier->id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $this->auditLog(
            $isUpdate ? 'UPDATE' : 'INSERT',
            'carriers',
            (int) $carrier->id,
            $oldData,
            $newData
        );
    }

    public function deactivate(int $id): void
    {
        if (!$this->pdo || $id <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM carriers WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $this->pdo->prepare("UPDATE carriers SET status = 'inativo' WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $stmt = $this->pdo->prepare('SELECT * FROM carriers WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $this->auditLog('UPDATE', 'carriers', $id, $oldData, $newData);
    }

    public function buildTrackingUrl(int $carrierId, string $trackingCode): ?string
    {
        $carrier = $this->find($carrierId);
        if (!$carrier || !$carrier->trackingUrlTemplate || trim($trackingCode) === '') {
            return null;
        }
        return str_replace('{{tracking_code}}', urlencode(trim($trackingCode)), $carrier->trackingUrlTemplate);
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS carriers (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(160) NOT NULL UNIQUE,
          carrier_type VARCHAR(30) NOT NULL DEFAULT 'transportadora',
          site_url VARCHAR(255) NULL,
          tracking_url_template VARCHAR(255) NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          notes TEXT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_carriers_status (status),
          INDEX idx_carriers_type (carrier_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->pdo->exec($sql);
    }
}
