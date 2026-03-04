<?php

namespace App\Repositories;

use App\Models\Vendor;
use App\Repositories\PeopleCompatViewRepository;
use PDO;

use App\Support\AuditableTrait;
class VendorRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            try {
                PeopleCompatViewRepository::ensure($this->pdo);
            } catch (\Throwable $e) {
                error_log('Falha ao preparar views de pessoas/fornecedores: ' . $e->getMessage());
            }
        }
    }

    public function nextVendorId(int $base = 1): int
    {
        if (!$this->pdo) {
            return $base;
        }

        $stmt = $this->pdo->query("SELECT MAX(id_vendor) AS max_vendor FROM vw_fornecedores_compat");
        $row = $stmt ? $stmt->fetch() : null;
        $max = $row && $row['max_vendor'] !== null ? (int) $row['max_vendor'] : null;
        if ($max === null || $max < $base) {
            return $base;
        }
        return $max + 1;
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, id_vendor, full_name, email, phone, city, state FROM vw_fornecedores_compat ORDER BY updated_at DESC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function allWithCommission(): array
    {
        if (!$this->pdo) {
            return [];
        }
    $sql = "SELECT id, id_vendor, full_name, commission_rate, email, phone, city, state
        FROM vw_fornecedores_compat
        ORDER BY updated_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?Vendor
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM vw_fornecedores_compat WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Vendor::fromArray($row) : null;
    }

    public function findByVendorCode(int $vendorCode): ?Vendor
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM vw_fornecedores_compat WHERE id_vendor = :code LIMIT 1");
        $stmt->execute([':code' => $vendorCode]);
        $row = $stmt->fetch();
        return $row ? Vendor::fromArray($row) : null;
    }

    public function findByPersonId(int $personId): ?Vendor
    {
        if (!$this->pdo || $personId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM vw_fornecedores_compat WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $personId]);
        $row = $stmt->fetch();
        return $row ? Vendor::fromArray($row) : null;
    }

    /**
     * @param int[] $personIds
     * @return array<int, array<string, mixed>>
     */
    public function listByPersonIds(array $personIds): array
    {
        if (!$this->pdo) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $personIds), function (int $id): bool {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $this->pdo->prepare("SELECT * FROM vw_fornecedores_compat WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $personId = (int) ($row['id'] ?? 0);
            if ($personId > 0) {
                $map[$personId] = $row;
            }
        }

        return $map;
    }

    public function findByEmail(string $email): ?Vendor
    {
        if (!$this->pdo) {
            return null;
        }
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM vw_fornecedores_compat WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ? Vendor::fromArray($row) : null;
    }

    public function save(Vendor $vendor): void
    {
                throw new \RuntimeException('VendorRepository agora é somente leitura. Use o módulo Pessoas/fornecedor via pessoas_papeis.');
    }

    public function updatePersonId(int $id, int $personId): void
    {
        throw new \RuntimeException('VendorRepository é somente leitura; sincronização ocorre via PersonSyncService.');
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('VendorRepository é somente leitura. Use o módulo Pessoas para exclusão.');
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

}
