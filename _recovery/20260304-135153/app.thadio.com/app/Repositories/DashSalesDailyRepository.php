<?php

namespace App\Repositories;

use PDO;

/**
 * DashSalesDailyRepository
 * 
 * Gerencia tabela de agregações diárias de vendas (materialização).
 */
class DashSalesDailyRepository
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
            CREATE TABLE IF NOT EXISTS dash_sales_daily (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                revenue DECIMAL(12,2) DEFAULT 0,
                quantity INT DEFAULT 0,
                customers INT DEFAULT 0,
                profit DECIMAL(12,2) DEFAULT 0,
                margin DECIMAL(12,4) DEFAULT 0,
                orders_count INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_date (date),
                INDEX idx_date (date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $this->pdo->exec($sql);
    }

    /**
     * Insere ou atualiza registro para uma data
     * 
     * @param string $date Data no formato YYYY-MM-DD
     * @param array $data Dados: revenue, quantity, customers, profit, margin, orders_count
     * @return bool
     */
    public function upsert(string $date, array $data): bool
    {
        $sql = "
            INSERT INTO dash_sales_daily (
                date,
                revenue,
                quantity,
                customers,
                profit,
                margin,
                orders_count,
                updated_at
            ) VALUES (
                :date,
                :revenue,
                :quantity,
                :customers,
                :profit,
                :margin,
                :orders_count,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                revenue = VALUES(revenue),
                quantity = VALUES(quantity),
                customers = VALUES(customers),
                profit = VALUES(profit),
                margin = VALUES(margin),
                orders_count = VALUES(orders_count),
                updated_at = NOW()
        ";

        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            ':date' => $date,
            ':revenue' => $data['revenue'] ?? 0,
            ':quantity' => $data['quantity'] ?? 0,
            ':customers' => $data['customers'] ?? 0,
            ':profit' => $data['profit'] ?? 0,
            ':margin' => $data['margin'] ?? 0,
            ':orders_count' => $data['orders_count'] ?? 0,
        ]);
    }

    /**
     * Busca agregação de uma data específica
     * 
     * @param string $date Data no formato YYYY-MM-DD
     * @return array|null
     */
    public function get(string $date): ?array
    {
        $sql = "
            SELECT 
                id,
                date,
                revenue,
                quantity,
                customers,
                profit,
                margin,
                orders_count,
                created_at,
                updated_at
            FROM dash_sales_daily
            WHERE date = :date
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca agregações em um intervalo de datas
     * 
     * @param string $dateFrom Data inicial (YYYY-MM-DD)
     * @param string $dateTo Data final (YYYY-MM-DD)
     * @return array Array de registros ordenados por data
     */
    public function getRange(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT 
                id,
                date,
                revenue,
                quantity,
                customers,
                profit,
                margin,
                orders_count,
                created_at,
                updated_at
            FROM dash_sales_daily
            WHERE date BETWEEN :date_from AND :date_to
            ORDER BY date ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula agregações de um período
     * 
     * @param string $dateFrom Data inicial (YYYY-MM-DD)
     * @param string $dateTo Data final (YYYY-MM-DD)
     * @return array Agregações: sum revenue/quantity/profit, avg margin, count orders
     */
    public function aggregate(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT 
                SUM(revenue) as total_revenue,
                SUM(quantity) as total_quantity,
                SUM(profit) as total_profit,
                AVG(margin) as avg_margin,
                SUM(orders_count) as total_orders,
                SUM(customers) as total_customers,
                COUNT(*) as days_with_data
            FROM dash_sales_daily
            WHERE date BETWEEN :date_from AND :date_to
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_revenue' => (float)($result['total_revenue'] ?? 0),
            'total_quantity' => (int)($result['total_quantity'] ?? 0),
            'total_profit' => (float)($result['total_profit'] ?? 0),
            'avg_margin' => (float)($result['avg_margin'] ?? 0),
            'total_orders' => (int)($result['total_orders'] ?? 0),
            'unique_customers' => (int)($result['total_customers'] ?? 0),
            'days_with_data' => (int)($result['days_with_data'] ?? 0),
        ];
    }

    /**
     * Remove registros antigos (limpeza de cache)
     * 
     * @param int $daysToKeep Manter apenas N dias recentes
     * @return int Número de registros deletados
     */
    public function cleanup(int $daysToKeep = 365): int
    {
        $sql = "
            DELETE FROM dash_sales_daily
            WHERE date < DATE_SUB(CURDATE(), INTERVAL :days DAY)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $daysToKeep]);
        
        return $stmt->rowCount();
    }

    /**
     * Verifica se há dados para um período
     * 
     * @param string $dateFrom Data inicial (YYYY-MM-DD)
     * @param string $dateTo Data final (YYYY-MM-DD)
     * @return bool
     */
    public function hasData(string $dateFrom, string $dateTo): bool
    {
        $sql = "
            SELECT
                COUNT(*) as total,
                (DATEDIFF(:date_to, :date_from) + 1) as expected_days
            FROM dash_sales_daily
            WHERE date BETWEEN :date_from AND :date_to
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int)($result['total'] ?? 0);
        $expected = max(1, (int)($result['expected_days'] ?? 0));
        return $total >= $expected;
    }
}
