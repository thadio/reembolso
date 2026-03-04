<?php

namespace App\Repositories;

use PDO;

/**
 * DashStockSnapshotRepository
 *
 * Gerencia snapshots diários de disponibilidade para performance do dashboard.
 */
class DashStockSnapshotRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        if (\function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    /**
     * Cria tabela se não existir
     */
    public function ensureTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS dash_stock_snapshot (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                snapshot_date DATETIME NOT NULL,
                total_units INT DEFAULT 0,
                potential_value DECIMAL(12,2) DEFAULT 0,
                invested_value DECIMAL(12,2) DEFAULT 0,
                consigned_units INT DEFAULT 0,
                units_compra INT DEFAULT 0,
                units_consignacao INT DEFAULT 0,
                units_doacao INT DEFAULT 0,
                avg_margin DECIMAL(12,4) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_snapshot_date (snapshot_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $this->pdo->exec($sql);
    }

    /**
     * Insere ou atualiza snapshot para uma data/hora específica
     * 
     * @param string $snapshotDate Data/hora no formato YYYY-MM-DD HH:MM:SS
     * @param array $data Dados do snapshot
     * @return bool
     */
    public function upsert(string $snapshotDate, array $data): bool
    {
        // Para snapshots, normalmente inserimos novo registro ao invés de update
        // Mas vamos manter a interface consistente
        $sql = "
            INSERT INTO dash_stock_snapshot (
                snapshot_date,
                total_units,
                potential_value,
                invested_value,
                consigned_units,
                units_compra,
                units_consignacao,
                units_doacao,
                avg_margin
            ) VALUES (
                :snapshot_date,
                :total_units,
                :potential_value,
                :invested_value,
                :consigned_units,
                :units_compra,
                :units_consignacao,
                :units_doacao,
                :avg_margin
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            ':snapshot_date' => $snapshotDate,
            ':total_units' => $data['total_units'] ?? 0,
            ':potential_value' => $data['potential_value'] ?? 0,
            ':invested_value' => $data['invested_value'] ?? 0,
            ':consigned_units' => $data['consigned_units'] ?? 0,
            ':units_compra' => $data['units_compra'] ?? 0,
            ':units_consignacao' => $data['units_consignacao'] ?? 0,
            ':units_doacao' => $data['units_doacao'] ?? 0,
            ':avg_margin' => $data['avg_margin'] ?? 0,
        ]);
    }

    /**
     * Busca o snapshot mais recente
     * 
     * @return array|null
     */
    public function getCurrent(): ?array
    {
        $sql = "
            SELECT 
                id,
                snapshot_date,
                total_units,
                potential_value,
                invested_value,
                consigned_units,
                units_compra,
                units_consignacao,
                units_doacao,
                avg_margin,
                created_at
            FROM dash_stock_snapshot
            ORDER BY snapshot_date DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Busca histórico de snapshots
     * 
     * @param int $days Número de dias de histórico
     * @return array Array de snapshots ordenados por data DESC
     */
    public function getHistory(int $days = 30): array
    {
        $sql = "
            SELECT 
                id,
                snapshot_date,
                total_units,
                potential_value,
                invested_value,
                consigned_units,
                units_compra,
                units_consignacao,
                units_doacao,
                avg_margin,
                created_at
            FROM dash_stock_snapshot
            WHERE snapshot_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY snapshot_date DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca snapshot em uma data/hora específica
     * 
     * @param string $snapshotDate Data/hora (YYYY-MM-DD HH:MM:SS)
     * @return array|null
     */
    public function get(string $snapshotDate): ?array
    {
        $sql = "
            SELECT 
                id,
                snapshot_date,
                total_units,
                potential_value,
                invested_value,
                consigned_units,
                units_compra,
                units_consignacao,
                units_doacao,
                avg_margin,
                created_at
            FROM dash_stock_snapshot
            WHERE snapshot_date = :snapshot_date
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':snapshot_date' => $snapshotDate]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Remove snapshots antigos (limpeza)
     * 
     * @param int $daysToKeep Manter apenas N dias recentes
     * @return int Número de registros deletados
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        $sql = "
            DELETE FROM dash_stock_snapshot
            WHERE snapshot_date < DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $daysToKeep]);
        
        return $stmt->rowCount();
    }

    /**
     * Verifica se há snapshot recente (útil para decidir refresh)
     * 
     * @param int $maxAgeMinutes Idade máxima em minutos
     * @return bool
     */
    public function hasRecentSnapshot(int $maxAgeMinutes = 60): bool
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM dash_stock_snapshot
            WHERE snapshot_date >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':minutes' => $maxAgeMinutes]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0) > 0;
    }

    /**
     * Calcula tendência comparando snapshots
     * 
     * @param int $days Comparar últimos N dias
     * @return array Tendências: total_units, potential_value, etc.
     */
    public function getTrend(int $days = 7): array
    {
        $sql = "
            SELECT 
                MIN(total_units) as min_units,
                MAX(total_units) as max_units,
                AVG(total_units) as avg_units,
                MIN(potential_value) as min_value,
                MAX(potential_value) as max_value,
                AVG(potential_value) as avg_value,
                COUNT(*) as snapshots_count
            FROM dash_stock_snapshot
            WHERE snapshot_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'min_units' => (int)($result['min_units'] ?? 0),
            'max_units' => (int)($result['max_units'] ?? 0),
            'avg_units' => (float)($result['avg_units'] ?? 0),
            'min_value' => (float)($result['min_value'] ?? 0),
            'max_value' => (float)($result['max_value'] ?? 0),
            'avg_value' => (float)($result['avg_value'] ?? 0),
            'snapshots_count' => (int)($result['snapshots_count'] ?? 0),
        ];
    }
}
