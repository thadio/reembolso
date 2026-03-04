<?php

namespace App\Services;

use PDO;
use App\Repositories\DashSalesDailyRepository;
use App\Repositories\DashStockSnapshotRepository;
use App\Repositories\DashRefreshLogRepository;

/**
 * DashRefreshService
 * 
 * Serviço para atualizar tabelas agregadas (materializações) do dashboard.
 */
class DashRefreshService
{
    private PDO $pdo;
    private DashSalesDailyRepository $salesRepo;
    private DashStockSnapshotRepository $stockRepo;
    private ?DashRefreshLogRepository $logRepo;

    public function __construct(
        PDO $pdo,
        DashSalesDailyRepository $salesRepo,
        DashStockSnapshotRepository $stockRepo,
        ?DashRefreshLogRepository $logRepo = null
    ) {
        $this->pdo = $pdo;
        $this->salesRepo = $salesRepo;
        $this->stockRepo = $stockRepo;
        $this->logRepo = $logRepo ?? new DashRefreshLogRepository($pdo);
    }

    /**
     * Atualiza agregação de vendas para uma data específica
     * 
     * @param string $date Data no formato YYYY-MM-DD
     * @return array Métricas calculadas
     */
    public function refreshSalesDaily(string $date): array
    {
        $startTime = microtime(true);
        $error = null;
        $metrics = [];

        try {
            $itemValueExpr = $this->orderItemsValueExpr('oi');
            $itemQuantityExpr = $this->orderItemsQuantityExpr('oi');
            $itemProfitExpr = $this->orderItemsProfitExpr('oi');
            $customerKeyExpr = $this->orderCustomerKeySql('o');

            // Query agregada em orders + order_items
            $sql = "
                SELECT 
                    COUNT(DISTINCT o.id) as orders_count,
                    COUNT(DISTINCT {$customerKeyExpr}) as customers,
                    COALESCE(SUM({$itemValueExpr}), 0) as revenue,
                    COALESCE(SUM({$itemQuantityExpr}), 0) as quantity,
                    COALESCE(SUM({$itemProfitExpr}), 0) as profit,
                    CASE 
                        WHEN SUM({$itemValueExpr}) > 0 
                        THEN (SUM({$itemProfitExpr}) / SUM({$itemValueExpr}))
                        ELSE 0 
                    END as margin
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN products p ON p.sku = oi.product_sku
                WHERE DATE(o.date_created) = :date
                    AND o.payment_status NOT IN ('cancelado', 'trash')
                    AND (o.archived = 0 OR o.archived IS NULL)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':date' => $date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $metrics = [
                'orders_count' => (int)($result['orders_count'] ?? 0),
                'customers' => (int)($result['customers'] ?? 0),
                'revenue' => (float)($result['revenue'] ?? 0),
                'quantity' => (int)($result['quantity'] ?? 0),
                'profit' => (float)($result['profit'] ?? 0),
                'margin' => (float)($result['margin'] ?? 0),
            ];

            // Upsert na tabela de agregação
            $this->salesRepo->upsert($date, $metrics);

            // Log success
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logRepo->log('sales_daily', 'success', [
                'duration_ms' => $duration,
                'rows_affected' => 1,
                'refresh_date' => $date,
                'metadata' => [
                    'date' => $date,
                    'metrics' => $metrics,
                ],
            ]);

        } catch (\Exception $e) {
            $error = $e->getMessage();
            
            // Log error
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logRepo->log('sales_daily', 'error', [
                'duration_ms' => $duration,
                'rows_affected' => 0,
                'refresh_date' => $date,
                'error_message' => $error,
                'metadata' => ['date' => $date],
            ]);

            throw $e;
        }

        return $metrics;
    }

    /**
     * Atualiza snapshot de disponibilidade
     *
     * @param string|null $snapshotDate Data/hora (YYYY-MM-DD HH:MM:SS), null = NOW()
     * @return array Métricas calculadas
     */
    public function refreshStockSnapshot(?string $snapshotDate = null): array
    {
        $startTime = microtime(true);
        $error = null;
        $metrics = [];

        if ($snapshotDate === null) {
            $snapshotDate = date('Y-m-d H:i:s');
        }

        try {
            // Query agregada no modelo unificado (products)
            $sql = "
                SELECT 
                    COALESCE(SUM(quantity), 0) as total_units,
                    COALESCE(SUM(CASE 
                        WHEN price > 0 THEN price * quantity
                        ELSE 0 
                    END), 0) as potential_value,
                    COALESCE(SUM(CASE
                        WHEN cost IS NOT NULL THEN cost * quantity
                        ELSE 0
                    END), 0) as invested_value,
                    COALESCE(SUM(CASE WHEN source = 'consignacao' THEN quantity ELSE 0 END), 0) as consigned_units,
                    COALESCE(SUM(CASE WHEN source = 'compra' THEN quantity ELSE 0 END), 0) as units_compra,
                    COALESCE(SUM(CASE WHEN source = 'consignacao' THEN quantity ELSE 0 END), 0) as units_consignacao,
                    COALESCE(SUM(CASE WHEN source = 'doacao' THEN quantity ELSE 0 END), 0) as units_doacao,
                    COALESCE(AVG(CASE 
                        WHEN price > 0 AND cost IS NOT NULL
                        THEN ((price - cost) / price)
                        ELSE NULL
                    END), 0) as avg_margin
                FROM products
                WHERE quantity > 0
                  AND status <> 'baixado'
            ";

            $stmt = $this->pdo->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $metrics = [
                'total_units' => (int)($result['total_units'] ?? 0),
                'potential_value' => (float)($result['potential_value'] ?? 0),
                'invested_value' => (float)($result['invested_value'] ?? 0),
                'consigned_units' => (int)($result['consigned_units'] ?? 0),
                'units_compra' => (int)($result['units_compra'] ?? 0),
                'units_consignacao' => (int)($result['units_consignacao'] ?? 0),
                'units_doacao' => (int)($result['units_doacao'] ?? 0),
                'avg_margin' => (float)($result['avg_margin'] ?? 0),
            ];

            // Insert snapshot
            $this->stockRepo->upsert($snapshotDate, $metrics);

            // Log success
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logRepo->log('stock_snapshot', 'success', [
                'duration_ms' => $duration,
                'rows_affected' => 1,
                'metadata' => [
                    'snapshot_date' => $snapshotDate,
                    'metrics' => $metrics,
                ],
            ]);

        } catch (\Exception $e) {
            $error = $e->getMessage();
            
            // Log error
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logRepo->log('stock_snapshot', 'error', [
                'duration_ms' => $duration,
                'rows_affected' => 0,
                'error_message' => $error,
                'metadata' => ['snapshot_date' => $snapshotDate],
            ]);

            throw $e;
        }

        return $metrics;
    }

    /**
     * Atualiza todas as materializações
     * 
     * @param array $options Opções: days (int, padrão 7)
     * @return array Resumo: total_refreshed, errors
     */
    public function refreshAll(array $options = []): array
    {
        $startTime = microtime(true);
        $days = $options['days'] ?? 7;
        $errors = [];
        $refreshed = 0;

        try {
            // 1. Refresh stock snapshot
            try {
                $this->refreshStockSnapshot();
                $refreshed++;
            } catch (\Exception $e) {
                $errors[] = "Stock snapshot: " . $e->getMessage();
            }

            // 2. Refresh sales daily para os últimos N dias
            $today = date('Y-m-d');
            for ($i = 0; $i < $days; $i++) {
                $date = date('Y-m-d', strtotime("-{$i} days", strtotime($today)));
                
                try {
                    $this->refreshSalesDaily($date);
                    $refreshed++;
                } catch (\Exception $e) {
                    $errors[] = "Sales daily {$date}: " . $e->getMessage();
                }
            }

            // Log success for refreshAll
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logRepo->log('all', 'success', [
                'duration_ms' => $duration,
                'rows_affected' => $refreshed,
                'metadata' => [
                    'days' => $days,
                    'total_refreshed' => $refreshed,
                    'errors_count' => count($errors),
                ],
            ]);

        } catch (\Exception $e) {
            // Log fatal error
            $duration = (int)((microtime(true) - $startTime) * 1000);
            $this->logRepo->log('all', 'error', [
                'duration_ms' => $duration,
                'rows_affected' => $refreshed,
                'error_message' => $e->getMessage(),
                'metadata' => [
                    'days' => $days,
                    'total_refreshed' => $refreshed,
                    'errors' => $errors,
                ],
            ]);

            throw $e;
        }

        return [
            'total_refreshed' => $refreshed,
            'days_processed' => $days,
            'errors' => $errors,
            'has_errors' => !empty($errors),
        ];
    }

    /**
     * Atualiza vendas para um range de datas
     * 
     * @param string $dateFrom Data inicial (YYYY-MM-DD)
     * @param string $dateTo Data final (YYYY-MM-DD)
     * @return array Resumo
     */
    public function refreshSalesRange(string $dateFrom, string $dateTo): array
    {
        $errors = [];
        $refreshed = 0;

        $currentDate = $dateFrom;
        while ($currentDate <= $dateTo) {
            try {
                $this->refreshSalesDaily($currentDate);
                $refreshed++;
            } catch (\Exception $e) {
                $errors[] = "Sales daily {$currentDate}: " . $e->getMessage();
            }

            $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
        }

        return [
            'total_refreshed' => $refreshed,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'errors' => $errors,
            'has_errors' => !empty($errors),
        ];
    }

    private function orderItemsQuantityExpr(string $alias = 'oi'): string
    {
        return "COALESCE({$alias}.quantity, 0)";
    }

    private function orderItemsValueExpr(string $alias = 'oi'): string
    {
        return "COALESCE({$alias}.total, ({$alias}.price * {$alias}.quantity), 0)";
    }

    private function orderItemsProfitExpr(string $alias = 'oi'): string
    {
        $valueExpr = $this->orderItemsValueExpr($alias);
        return "(($valueExpr) - (COALESCE(p.cost, 0) * COALESCE({$alias}.quantity, 0)))";
    }

    private function orderCustomerKeySql(string $alias = 'o'): string
    {
        return "COALESCE(NULLIF(CAST({$alias}.pessoa_id AS CHAR), '0'),
                         NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.buyer_info, '$.email')), ''),
                         NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.billing_info, '$.email')), ''),
                         NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.shipping_info, '$.email')), ''),
                         CONCAT('order:', {$alias}.id))";
    }
}
