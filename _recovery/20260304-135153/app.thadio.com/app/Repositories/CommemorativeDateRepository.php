<?php

namespace App\Repositories;

use App\Models\CommemorativeDate;
use PDO;

use App\Support\AuditableTrait;
class CommemorativeDateRepository
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

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function all(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $sql = "SELECT id, name, day, month, year, scope, category, description, source, status, created_at
                FROM datas_comemorativas
                ORDER BY month ASC, day ASC, COALESCE(year, 9999) ASC, name ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?CommemorativeDate
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM datas_comemorativas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? CommemorativeDate::fromArray($row) : null;
    }

    public function save(CommemorativeDate $item): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        // Capturar dados antigos para auditoria
        $oldData = $item->id ? $this->find($item->id)?->toArray() : null;

        if ($item->id) {
            $sql = "UPDATE datas_comemorativas
                    SET name = :name,
                        day = :day,
                        month = :month,
                        year = :year,
                        scope = :scope,
                        category = :category,
                        description = :description,
                        source = :source,
                        status = :status
                    WHERE id = :id";
            $params = $item->toDbParams() + [':id' => $item->id];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO datas_comemorativas (name, day, month, year, scope, category, description, source, status)
                    VALUES (:name, :day, :month, :year, :scope, :category, :description, :source, :status)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($item->toDbParams());
            $item->id = (int) $this->pdo->lastInsertId();
        }

        // Auditoria
        $newData = $this->find($item->id)?->toArray();
        $this->auditLog($item->id ? 'UPDATE' : 'INSERT', 'datas_comemorativas', $item->id, $oldData, $newData);
    }

    public function delete(int $id): void
    {
        if (!$this->pdo) {
            return;
        }

        // Capturar dados antigos para auditoria
        $oldData = $this->find($id)?->toArray();

        $stmt = $this->pdo->prepare("DELETE FROM datas_comemorativas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // Auditoria
        $this->auditLog('DELETE', 'datas_comemorativas', $id, $oldData, null);
    }

    public function listForDates(array $dates, int $limit = 0): array
    {
        if (!$this->pdo || empty($dates)) {
            return [];
        }

        $conditions = [];
        $params = [];
        $index = 0;

        foreach ($dates as $date) {
            $dayKey = ':day' . $index;
            $monthKey = ':month' . $index;
            $yearKey = ':year' . $index;

            $conditions[] = "((year IS NULL AND day = {$dayKey} AND month = {$monthKey}) OR (year = {$yearKey} AND day = {$dayKey} AND month = {$monthKey}))";
            $params[$dayKey] = (int) ($date['day'] ?? 0);
            $params[$monthKey] = (int) ($date['month'] ?? 0);
            $params[$yearKey] = (int) ($date['year'] ?? 0);
            $index++;
        }

        $useLimit = $limit > 0;
        $sql = "SELECT id, name, day, month, year, scope, category, description, source, status
                FROM datas_comemorativas
                WHERE status = 'ativo'";
        if ($conditions) {
            $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        }
        $sql .= " ORDER BY month ASC, day ASC, name ASC";
        if ($useLimit) {
            $sql .= " LIMIT :limit";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        if ($useLimit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS datas_comemorativas (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(190) NOT NULL,
          day TINYINT UNSIGNED NOT NULL,
          month TINYINT UNSIGNED NOT NULL,
          year SMALLINT UNSIGNED NULL,
          scope VARCHAR(30) NOT NULL DEFAULT 'brasil',
          category VARCHAR(80) NULL,
          description TEXT NULL,
          source VARCHAR(120) NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_datas_comemorativas_date (month, day),
          INDEX idx_datas_comemorativas_year (year),
          INDEX idx_datas_comemorativas_scope (scope),
          INDEX idx_datas_comemorativas_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
