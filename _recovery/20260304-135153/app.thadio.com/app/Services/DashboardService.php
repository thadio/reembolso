<?php

namespace App\Services;

use App\Repositories\BagRepository;
use App\Repositories\CatalogCategoryRepository;
use App\Repositories\CommemorativeDateRepository;
use App\Repositories\ConsignmentRepository;
use App\Repositories\CreditAccountRepository;
use App\Repositories\CreditEntryRepository;
use App\Repositories\DashSalesDailyRepository;
use App\Repositories\DashStockSnapshotRepository;
use App\Repositories\FinanceEntryRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderReturnRepository;
use App\Repositories\PersonRepository;
use App\Repositories\PersonRoleRepository;
use App\Repositories\ProductRepository;
use DateInterval;
use DateTimeImmutable;
use PDO;

class DashboardService
{
    private ?PDO $pdo;
    private ?DashSalesDailyRepository $salesDailyRepo;
    private ?DashStockSnapshotRepository $stockSnapshotRepo;
    private ?bool $hasMediaLinksTable = null;
    private ?array $legacyProductRegistrationsByDay = null;
    private ?array $legacySupplyBySku = null;
    private const CUSTOMER_ENGAGEMENT_ROW_LIMIT = 60;
    private const CUSTOMER_ENGAGEMENT_SIX_MONTHS_DAYS = 180;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->salesDailyRepo = $pdo ? new DashSalesDailyRepository($pdo) : null;
        $this->stockSnapshotRepo = $pdo ? new DashStockSnapshotRepository($pdo) : null;
        
        if ($pdo) {
            // Garante que as tabelas existem antes das consultas agregadas.
            new ProductRepository($pdo);
            new CatalogCategoryRepository($pdo);
            new ConsignmentRepository($pdo);
            new CreditAccountRepository($pdo);
            new CreditEntryRepository($pdo);
            new OrderRepository($pdo);
            new BagRepository($pdo);
            new OrderReturnRepository($pdo);
            new FinanceEntryRepository($pdo);
            new PersonRepository($pdo);
            new PersonRoleRepository($pdo);
        }
    }

    public function insights(bool $withTimings = false): array
    {
        if (!$this->pdo) {
            $empty = $this->emptyInsights();
            if ($withTimings) {
                $empty['__timings'] = [];
            }
            return $empty;
        }

        $timings = [];
        $profile = function (string $key, callable $fn) use (&$timings, $withTimings) {
            $start = microtime(true);
            $result = $fn();
            if ($withTimings) {
                $timings[$key] = round((microtime(true) - $start) * 1000, 2);
            }
            return $result;
        };

        $collectionStats = $profile('collections', fn () => $this->collectionStats());
        $salesMetrics = $profile('salesMetrics', fn () => $this->salesMetrics());
        $customerStats = $profile('customerStats', fn () => $this->customerStats());

        $totals = $profile('totals', fn () => $this->totals($collectionStats['total'] ?? 0));
        $inventory = $profile('inventory', fn () => $this->inventory());
        $status = $profile('status', fn () => $this->productsByStatus());
        $sources = $profile('sources', fn () => $this->productsBySource());
        $topVendors = $profile('topVendors', fn () => $this->topVendors());
        $lowStock = $profile('lowStock', fn () => $this->lowStock());
        $customerEngagement = $profile('customerEngagement', fn () => $this->customerEngagement($customerStats));
        $geo = $profile('customerGeo', fn () => $this->customerByState());
        $openBags = $profile('openBags', fn () => $this->openBags());
        $pendingConsignments = $profile('pendingConsignments', fn () => $this->pendingConsignments());
        $pendingDeliveries = $profile('pendingDeliveries', fn () => $this->pendingDeliveries());
        $pendingRefunds = $profile('pendingRefunds', fn () => $this->pendingRefunds());
        $creditBalances = $profile('creditBalances', fn () => $this->creditBalances());
        $consignmentVoucherTotals = $profile('consignmentVoucherTotals', fn () => $this->consignmentVoucherTotals());
        $productsWithoutPhotos = $profile('productsWithoutPhotos', fn () => $this->productsWithStockWithoutPhotos());
        $productsWithPhotosUnpublished = $profile('productsWithPhotosUnpublished', fn () => $this->productsWithStockWithPhotosUnpublished());
        $productsMissingCost = $profile('productsMissingCost', fn () => $this->productsPurchasedMissingCost());
        $oldConsignedProducts = $profile('oldConsignedProducts', fn () => $this->oldConsignedProducts());
        $commemorativeWeek = $profile('commemorativeWeek', fn () => $this->commemorativeWeek());
        $payables = $profile('payables', fn () => $this->payablesWidget());
        $productActivity = $profile('productActivity', fn () => $this->productActivity($salesMetrics));

        $result = [
            'totals' => $totals,
            'inventory' => $inventory,
            'status' => $status,
            'sources' => $sources,
            'topVendors' => $topVendors,
            'lowStock' => $lowStock,
            'customers' => $customerStats,
            'customerEngagement' => $customerEngagement,
            'geo' => $geo,
            'collections' => $collectionStats,
            'openBags' => $openBags,
            'pendingConsignments' => $pendingConsignments,
            'pendingDeliveries' => $pendingDeliveries,
            'pendingRefunds' => $pendingRefunds,
            'creditBalances' => $creditBalances,
            'consignmentVoucherTotals' => $consignmentVoucherTotals,
            'productsWithoutPhotos' => $productsWithoutPhotos,
            'productsWithPhotosUnpublished' => $productsWithPhotosUnpublished,
            'productsMissingCost' => $productsMissingCost,
            'oldConsignedProducts' => $oldConsignedProducts,
            'commemorativeWeek' => $commemorativeWeek,
            'payables' => $payables,
            'salesMetrics' => $salesMetrics,
            'productActivity' => $productActivity,
        ];

        if ($withTimings) {
            $result['__timings'] = $timings;
        }

        return $result;
    }

    private function totals(int $collectionTotal = 0): array
    {
        // Count products ativos no modelo unificado.
        // Inclui 'draft' (rascunho = em estoque, não publicado) alinhado com o legado.
        $products = $this->scalar(
            "SELECT COUNT(*)
             FROM products
             WHERE status NOT IN ('archived', 'baixado')"
        );
        
        // Count vendors (fornecedor role)
        $vendors = $this->countPeopleByRole('fornecedor');
        
        // Count customers (cliente role)
        $customers = $this->countPeopleByRole('cliente');
        
        // Count active categories
        $collections = $this->scalar("SELECT COUNT(*) FROM catalog_categories WHERE status = 'ativa'");
        
        return [
            'products' => $products,
            'vendors' => $vendors,
            'customers' => $customers,
            'collections' => $collections,
        ];
    }

    private function inventory(): array
    {
        if (!$this->pdo) {
            return $this->emptyInventoryMetrics();
        }

        $legacySupply = $this->loadLegacySupplyBySku();

        // Try to use recent snapshot (< 1 hour old)
        if (empty($legacySupply) && $this->stockSnapshotRepo && $this->stockSnapshotRepo->hasRecentSnapshot(60)) {
            $snapshot = $this->stockSnapshotRepo->getCurrent();
            if ($snapshot) {
                return $this->inventoryFromSnapshot($snapshot);
            }
        }

        // Fallback: query live data
        return $this->inventoryFromLive($legacySupply);
    }

    private function inventoryFromSnapshot(array $snapshot): array
    {
        // Map snapshot to expected format
        $totalUnits = (int)($snapshot['total_units'] ?? 0);
        $potentialValue = (float)($snapshot['potential_value'] ?? 0);
        $investedValue = (float)($snapshot['invested_value'] ?? 0);
        $avgMargin = (float)($snapshot['avg_margin'] ?? 0);
        $consignados = (int)($snapshot['consigned_units'] ?? 0);
        
        $stockFilter = $this->stockFilterSql('p');
        $totalProducts = $this->scalar("SELECT COUNT(*) FROM products p WHERE {$stockFilter}");

        $investedPurchases = $this->scalarFloat("SELECT COALESCE(SUM(p.cost * p.quantity), 0)
            FROM products p
            WHERE {$stockFilter} AND p.source = 'compra' AND p.cost IS NOT NULL");
        
        $futureConsignmentExpense = $this->scalarFloat(
            "SELECT COALESCE(SUM(
                p.price
                * (COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                   ) / 100)
                * p.quantity
            ), 0)
            FROM products p
            LEFT JOIN (
                SELECT ci.product_sku,
                       COALESCE(NULLIF(ci.percent_override, 0), c.percent_default) AS effective_percent
                FROM consignment_items ci
                JOIN consignments c ON c.id = ci.consignment_id
            ) ci_eff ON ci_eff.product_sku = p.sku
            WHERE {$stockFilter} AND p.source = 'consignacao'
              AND p.price IS NOT NULL
              AND COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                  ) IS NOT NULL"
        );
        
        $avgMarginPurchased = $this->scalarFloat("SELECT AVG((p.price - p.cost) / p.price)
            FROM products p
            WHERE {$stockFilter} AND p.source = 'compra' AND p.price > 0 AND p.cost IS NOT NULL");
        
        $avgMarginConsigned = $this->scalarFloat(
            "SELECT AVG(1 - (COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                   ) / 100))
            FROM products p
            LEFT JOIN (
                SELECT ci.product_sku,
                       COALESCE(NULLIF(ci.percent_override, 0), c.percent_default) AS effective_percent
                FROM consignment_items ci
                JOIN consignments c ON c.id = ci.consignment_id
            ) ci_eff ON ci_eff.product_sku = p.sku
            WHERE {$stockFilter} AND p.source = 'consignacao'
              AND COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                  ) IS NOT NULL"
        );

        $sourceUnits = [
            'compra' => (int)($snapshot['units_compra'] ?? 0),
            'consignacao' => (int)($snapshot['units_consignacao'] ?? 0),
            'doacao' => (int)($snapshot['units_doacao'] ?? 0),
            'sem_origem' => 0,
            'outros' => 0,
        ];

        return [
            'totalProducts' => $totalProducts,
            'totalUnits' => $totalUnits,
            'potentialValue' => $potentialValue,
            'investedValue' => $investedValue,
            'avgMargin' => $avgMargin,
            'consignados' => $consignados,
            'unitsBySource' => $sourceUnits,
            'investedPurchases' => $investedPurchases,
            'futureConsignmentExpense' => $futureConsignmentExpense,
            'avgMarginPurchased' => $avgMarginPurchased !== null ? (float) $avgMarginPurchased : null,
            'avgMarginConsigned' => $avgMarginConsigned !== null ? (float) $avgMarginConsigned : null,
            'source' => 'materialized',
        ];
    }

    private function inventoryFromLive(array $legacySupplyBySku = []): array
    {
        if (!empty($legacySupplyBySku)) {
            return $this->inventoryFromLiveWithLegacySupply($legacySupplyBySku);
        }

        $stockFilter = $this->stockFilterSql('p');

        // Aggregate metrics from products (modelo unificado)
        $totalUnits = $this->scalar("SELECT COALESCE(SUM(p.quantity), 0) FROM products p WHERE {$stockFilter}");
        $totalProducts = $this->scalar("SELECT COUNT(*) FROM products p WHERE {$stockFilter}");
        $potentialValue = $this->scalarFloat("SELECT COALESCE(SUM(p.price * p.quantity), 0) FROM products p WHERE {$stockFilter} AND p.price IS NOT NULL");
        $investedValue = $this->scalarFloat("SELECT COALESCE(SUM(p.cost * p.quantity), 0) FROM products p WHERE {$stockFilter} AND p.cost IS NOT NULL");
        
        $avgMargin = $this->scalarFloat("SELECT AVG((p.price - p.cost) / p.price)
            FROM products p
            WHERE {$stockFilter} AND p.price > 0 AND p.cost IS NOT NULL");
        
        $consignados = $this->scalar("SELECT COALESCE(SUM(p.quantity), 0) FROM products p WHERE {$stockFilter} AND p.source = 'consignacao'");
        
        $investedPurchases = $this->scalarFloat("SELECT COALESCE(SUM(p.cost * p.quantity), 0)
            FROM products p
            WHERE {$stockFilter} AND p.source = 'compra' AND p.cost IS NOT NULL");
        
        // Percentual de consignação: usa products.percentual_consignacao se preenchido,
        // senão busca via consignment_items -> consignments.percent_default.
        $futureConsignmentExpense = $this->scalarFloat(
            "SELECT COALESCE(SUM(
                p.price
                * (COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                   ) / 100)
                * p.quantity
            ), 0)
            FROM products p
            LEFT JOIN (
                SELECT ci.product_sku,
                       COALESCE(NULLIF(ci.percent_override, 0), c.percent_default) AS effective_percent
                FROM consignment_items ci
                JOIN consignments c ON c.id = ci.consignment_id
            ) ci_eff ON ci_eff.product_sku = p.sku
            WHERE {$stockFilter} AND p.source = 'consignacao'
              AND p.price IS NOT NULL
              AND COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                  ) IS NOT NULL"
        );
        
        $avgMarginPurchased = $this->scalarFloat("SELECT AVG((p.price - p.cost) / p.price)
            FROM products p
            WHERE {$stockFilter} AND p.source = 'compra' AND p.price > 0 AND p.cost IS NOT NULL");
        
        // Margem de consignados: usa percentual_consignacao do produto ou do lote via JOIN.
        $avgMarginConsigned = $this->scalarFloat(
            "SELECT AVG(1 - (COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                   ) / 100))
            FROM products p
            LEFT JOIN (
                SELECT ci.product_sku,
                       COALESCE(NULLIF(ci.percent_override, 0), c.percent_default) AS effective_percent
                FROM consignment_items ci
                JOIN consignments c ON c.id = ci.consignment_id
            ) ci_eff ON ci_eff.product_sku = p.sku
            WHERE {$stockFilter} AND p.source = 'consignacao'
              AND COALESCE(
                    NULLIF(p.percentual_consignacao, 0),
                    ci_eff.effective_percent
                  ) IS NOT NULL"
        );

        // Group units by origin (compra/consignacao/doacao/outro)
        $sourceUnits = [
            'compra' => 0,
            'consignacao' => 0,
            'doacao' => 0,
            'sem_origem' => 0,
            'outros' => 0,
        ];
        try {
            $stmt = $this->pdo->query("SELECT p.source, COALESCE(SUM(p.quantity), 0) AS total
                FROM products p
                WHERE {$stockFilter}
                GROUP BY p.source");
            $rows = $stmt ? $stmt->fetchAll() : [];
            foreach ($rows as $row) {
                $origin = strtolower(trim((string) ($row['source'] ?? '')));
                $key = $origin !== '' ? $origin : 'sem_origem';
                if (!array_key_exists($key, $sourceUnits)) {
                    $key = 'outros';
                }
                $sourceUnits[$key] = (int) ($row['total'] ?? 0);
            }
        } catch (\Throwable $e) {
            // Keep default zeros if query fails
        }

        return [
            'totalProducts' => $totalProducts,
            'totalUnits' => $totalUnits,
            'potentialValue' => $potentialValue,
            'investedValue' => $investedValue,
            'avgMargin' => $avgMargin !== null ? (float) $avgMargin : null,
            'consignados' => $consignados,
            'unitsBySource' => array_map('intval', $sourceUnits),
            'investedPurchases' => $investedPurchases,
            'futureConsignmentExpense' => $futureConsignmentExpense,
            'avgMarginPurchased' => $avgMarginPurchased !== null ? (float) $avgMarginPurchased : null,
            'avgMarginConsigned' => $avgMarginConsigned !== null ? (float) $avgMarginConsigned : null,
            'source' => 'live',
        ];
    }

    private function inventoryFromLiveWithLegacySupply(array $legacySupplyBySku): array
    {
        if (!$this->pdo) {
            return $this->emptyInventoryMetrics();
        }

        $stockFilter = $this->stockFilterSql('p');
        $sql = "SELECT p.sku, p.source, p.price, p.cost, p.quantity, p.percentual_consignacao
                FROM products p
                WHERE {$stockFilter}";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        $totalProducts = 0;
        $totalUnits = 0;
        $potentialValue = 0.0;
        $investedValue = 0.0;
        $consignados = 0;
        $investedPurchases = 0.0;
        $futureConsignmentExpense = 0.0;
        $sumMargin = 0.0;
        $countMargin = 0;
        $sumMarginPurchased = 0.0;
        $countMarginPurchased = 0;
        $sumMarginConsigned = 0.0;
        $countMarginConsigned = 0;
        $sourceUnits = [
            'compra' => 0,
            'consignacao' => 0,
            'doacao' => 0,
            'sem_origem' => 0,
            'outros' => 0,
        ];

        foreach ($rows as $row) {
            $totalProducts++;
            $sku = trim((string) ($row['sku'] ?? ''));
            $quantity = max(0, (int) ($row['quantity'] ?? 0));
            $totalUnits += $quantity;

            $legacy = $sku !== '' ? ($legacySupplyBySku[$sku] ?? null) : null;
            $source = strtolower(trim((string) ($row['source'] ?? '')));
            if ($source === '' && is_array($legacy)) {
                $source = strtolower(trim((string) ($legacy['source'] ?? '')));
            }
            $sourceKey = $source !== '' ? $source : 'sem_origem';
            if (!array_key_exists($sourceKey, $sourceUnits)) {
                $sourceKey = 'outros';
            }
            $sourceUnits[$sourceKey] += $quantity;

            $price = $row['price'] !== null ? (float) $row['price'] : null;
            $cost = $row['cost'] !== null
                ? (float) $row['cost']
                : (is_array($legacy) && isset($legacy['cost']) ? (float) $legacy['cost'] : null);

            $percentualConsignacao = $row['percentual_consignacao'] !== null
                ? (float) $row['percentual_consignacao']
                : null;
            if (($percentualConsignacao === null || $percentualConsignacao == 0.0) && is_array($legacy)) {
                $legacyPercent = isset($legacy['percent']) ? (float) $legacy['percent'] : null;
                if ($legacyPercent !== null && $legacyPercent > 0) {
                    $percentualConsignacao = $legacyPercent;
                }
            }

            if ($price !== null) {
                $potentialValue += $price * $quantity;
            }
            if ($cost !== null) {
                $investedValue += $cost * $quantity;
            }

            if ($source === 'consignacao') {
                $consignados += $quantity;
                if ($price !== null && $percentualConsignacao !== null) {
                    $futureConsignmentExpense += $price * ($percentualConsignacao / 100) * $quantity;
                }
                if ($percentualConsignacao !== null) {
                    $sumMarginConsigned += 1 - ($percentualConsignacao / 100);
                    $countMarginConsigned++;
                }
            }

            if ($source === 'compra' && $cost !== null) {
                $investedPurchases += $cost * $quantity;
            }

            if ($price !== null && $price > 0 && $cost !== null) {
                $margin = ($price - $cost) / $price;
                $sumMargin += $margin;
                $countMargin++;
                if ($source === 'compra') {
                    $sumMarginPurchased += $margin;
                    $countMarginPurchased++;
                }
            }
        }

        return [
            'totalProducts' => $totalProducts,
            'totalUnits' => $totalUnits,
            'potentialValue' => $potentialValue,
            'investedValue' => $investedValue,
            'avgMargin' => $countMargin > 0 ? ($sumMargin / $countMargin) : null,
            'consignados' => $consignados,
            'unitsBySource' => array_map('intval', $sourceUnits),
            'investedPurchases' => $investedPurchases,
            'futureConsignmentExpense' => $futureConsignmentExpense,
            'avgMarginPurchased' => $countMarginPurchased > 0 ? ($sumMarginPurchased / $countMarginPurchased) : null,
            'avgMarginConsigned' => $countMarginConsigned > 0 ? ($sumMarginConsigned / $countMarginConsigned) : null,
            'source' => 'live_with_legacy_supply',
        ];
    }

    private function emptyInventoryMetrics(): array
    {
        return [
            'totalProducts' => 0,
            'totalUnits' => 0,
            'potentialValue' => 0,
            'investedValue' => 0,
            'avgMargin' => null,
            'consignados' => 0,
            'unitsBySource' => [
                'compra' => 0,
                'consignacao' => 0,
                'doacao' => 0,
                'sem_origem' => 0,
                'outros' => 0,
            ],
            'investedPurchases' => 0,
            'futureConsignmentExpense' => 0,
            'avgMarginPurchased' => null,
            'avgMarginConsigned' => null,
        ];
    }

    private function productsByStatus(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $stmt = $this->pdo->query(
            "SELECT p.status AS label, COUNT(*) AS total
             FROM products p
             GROUP BY status
             ORDER BY total DESC"
        );

        return $stmt ? $stmt->fetchAll() : [];
    }

    private function productsBySource(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $stmt = $this->pdo->query(
            "SELECT COALESCE(NULLIF(p.source, ''), 'sem origem') AS label, COALESCE(SUM(p.quantity), 0) AS total
             FROM products p
             WHERE " . $this->stockFilterSql('p') . "
             GROUP BY label
             ORDER BY total DESC"
        );

        return $stmt ? $stmt->fetchAll() : [];
    }

    private function topVendors(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT p.supplier_pessoa_id,
                       COUNT(*) AS product_count,
                       COALESCE(SUM(p.price * p.quantity), 0) AS potential_value,
                       COALESCE(SUM(p.cost * p.quantity), 0) AS invested_value
                FROM products p
                WHERE " . $this->stockFilterSql('p') . "
                GROUP BY p.supplier_pessoa_id
                ORDER BY potential_value DESC, product_count DESC
                LIMIT 10";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (empty($rows)) {
            return [];
        }

        $personIds = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['supplier_pessoa_id'] ?? 0);
            if ($pid > 0) {
                $personIds[] = $pid;
            }
        }
        $personMap = $this->loadPeopleByIds($personIds);

        $result = [];
        foreach ($rows as $row) {
            $supplierPessoaId = (int) ($row['supplier_pessoa_id'] ?? 0);
            $name = 'Sem fornecedor';
            if ($supplierPessoaId > 0 && isset($personMap[$supplierPessoaId])) {
                $name = $personMap[$supplierPessoaId]['full_name'] ?? $name;
            }
            $result[] = [
                'name' => $name,
                'supplier_pessoa_id' => $supplierPessoaId,
                'product_count' => (int) ($row['product_count'] ?? 0),
                'potential_value' => (float) ($row['potential_value'] ?? 0),
                'invested_value' => (float) ($row['invested_value'] ?? 0),
            ];
        }

        return $result;
    }

    private function lowStock(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT p.sku AS product_id,
                       p.name AS name,
                       p.quantity,
                       p.price
                FROM products p
                WHERE p.status = 'disponivel'
                  AND p.quantity BETWEEN 1 AND 2
                ORDER BY p.quantity ASC, p.updated_at DESC
                LIMIT 10";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        return $rows ?: [];
    }

    private function openBags(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $repo = new BagRepository($this->pdo);
        return $repo->listOpenWithTotals();
    }

    private function pendingDeliveries(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT id AS order_id, date_created, fulfillment_status, payment_status, billing_info, shipping_info, buyer_info, NULL AS status
                FROM orders
                WHERE (archived = 0 OR archived IS NULL)
                  AND (fulfillment_status IS NULL OR fulfillment_status NOT IN ('entregue','delivered','concluido','finalizado'))
                ORDER BY date_created ASC
                LIMIT 8";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (empty($rows)) {
            return [];
        }

        foreach ($rows as $index => $row) {
            $rows[$index] = $this->hydrateOrderNames($row);
        }

        return $rows;
    }

    private function pendingConsignments(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT c.id,
                       c.supplier_pessoa_id,
                       c.received_at,
                       COUNT(ci.id) AS total_received
                FROM consignments c
                LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
                WHERE c.status IN ('aberta','pendente')
                GROUP BY c.id
                ORDER BY c.received_at ASC
                LIMIT 6";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (empty($rows)) {
            return [];
        }

        $personIds = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['supplier_pessoa_id'] ?? 0);
            if ($pid > 0) {
                $personIds[] = $pid;
            }
        }
        $personMap = $this->loadPeopleByIds($personIds);

        $result = [];
        foreach ($rows as $row) {
            $supplierId = (int) ($row['supplier_pessoa_id'] ?? 0);
            $supplierName = $supplierId > 0 && isset($personMap[$supplierId])
                ? (string) ($personMap[$supplierId]['full_name'] ?? 'Sem fornecedor')
                : 'Sem fornecedor';
            $result[] = [
                'id' => (int) ($row['id'] ?? 0),
                'supplier_name' => $supplierName,
                'supplier_pessoa_id' => $supplierId,
                'received_at' => $row['received_at'] ?? null,
                'total_received' => (int) ($row['total_received'] ?? 0),
                'total_returned' => 0, // TODO: mapear devolucoes de consignacao.
                'total_linked' => 0, // TODO: mapear itens vinculados a produtos.
            ];
        }

        return $result;
    }

    private function pendingRefunds(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $repo = new OrderReturnRepository($this->pdo);
        return $repo->listRefundPending();
    }

    private function creditBalances(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT ca.id,
                       ca.pessoa_id,
                       ca.account_type AS type,
                       SUM(CASE WHEN ce.entry_type = 'credito' THEN ce.amount ELSE -ce.amount END) AS balance
                FROM credit_accounts ca
                JOIN credit_entries ce ON ce.credit_account_id = ca.id
                WHERE ca.status = 'ativo'
                GROUP BY ca.id
                HAVING balance > 0
                ORDER BY balance DESC
                LIMIT 6";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (empty($rows)) {
            return [];
        }

        $personIds = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['pessoa_id'] ?? 0);
            if ($pid > 0) {
                $personIds[] = $pid;
            }
        }
        $personMap = $this->loadPeopleByIds($personIds);

        foreach ($rows as $index => $row) {
            $pid = (int) ($row['pessoa_id'] ?? 0);
            $rows[$index]['person_name'] = $pid > 0 && isset($personMap[$pid])
                ? ($personMap[$pid]['full_name'] ?? '')
                : '';
            $rows[$index]['person_email'] = $pid > 0 && isset($personMap[$pid])
                ? ($personMap[$pid]['email'] ?? '')
                : '';
            $rows[$index]['balance'] = (float) ($row['balance'] ?? 0);
        }

        return $rows;
    }

    private function consignmentVoucherTotals(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT c.supplier_pessoa_id,
                       SUM(ce.amount) AS total_credit
                FROM credit_entries ce
                JOIN consignments c ON ce.ref_type = 'consignment' AND ce.ref_id = c.id
                WHERE ce.entry_type = 'credito'
                GROUP BY c.supplier_pessoa_id
                ORDER BY total_credit DESC
                LIMIT 8";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (empty($rows)) {
            return [];
        }

        $personIds = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['supplier_pessoa_id'] ?? 0);
            if ($pid > 0) {
                $personIds[] = $pid;
            }
        }
        $personMap = $this->loadPeopleByIds($personIds);

        foreach ($rows as $index => $row) {
            $pid = (int) ($row['supplier_pessoa_id'] ?? 0);
            $rows[$index]['supplier_name'] = $pid > 0 && isset($personMap[$pid])
                ? ($personMap[$pid]['full_name'] ?? '')
                : 'Fornecedor';
            $rows[$index]['voucher_account_id'] = null; // TODO: vincular conta de credito quando houver.
            $rows[$index]['total_credit'] = (float) ($row['total_credit'] ?? 0);
        }

        return $rows;
    }

    private function productsWithStockWithoutPhotos(int $limit = 8): array
    {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $stockFilter = $this->stockFilterSql('p');
        $sql = "SELECT p.sku AS ID,
                       p.name AS post_title,
                       CAST(p.sku AS CHAR) AS sku,
                       p.quantity AS quantity
                FROM products p
                WHERE {$stockFilter}
                  AND NOT (" . $this->productHasImageSql('p') . ")
                ORDER BY p.quantity DESC, p.updated_at DESC
                LIMIT {$limit}";

        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        return $rows ?: [];
    }

    private function productsWithStockWithPhotosUnpublished(int $limit = 8): array
    {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $imageExpr = $this->productMetadataImageExprSql('p');
        $sql = "SELECT p.sku AS ID,
                       p.name AS post_title,
                       CAST(p.sku AS CHAR) AS sku,
                       p.status AS status_unified,
                       COALESCE({$imageExpr}, '') AS thumb_url
                FROM products p
                WHERE p.status = 'draft' AND p.quantity > 0
                  AND (" . $this->productHasImageSql('p') . ")
                ORDER BY p.quantity DESC, p.updated_at DESC
                LIMIT {$limit}";

        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        return $rows ?: [];
    }

    private function productsPurchasedMissingCost(int $limit = 8): array
    {
        if (!$this->pdo) {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $sql = "SELECT p.sku AS product_id,
                       p.name AS post_title,
                       CAST(p.sku AS CHAR) AS sku,
                       p.quantity AS quantity
                FROM products p
                WHERE " . $this->stockFilterSql('p') . "
                  AND p.source = 'compra'
                  AND p.cost IS NULL
                ORDER BY p.quantity DESC, p.updated_at DESC
                LIMIT {$limit}";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        return $rows ?: [];
    }

    private function oldConsignedProducts(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT p.sku AS product_id,
                       p.name AS title,
                       CAST(p.sku AS CHAR) AS sku,
                       p.quantity AS quantity,
                       p.created_at AS entry_date
                FROM products p
                WHERE " . $this->stockFilterSql('p') . "
                  AND p.source = 'consignacao'
                ORDER BY p.created_at ASC
                LIMIT 12";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (empty($rows)) {
            return [];
        }

        foreach ($rows as $index => $row) {
            $rows[$index]['thumb_url'] = '';
        }

        return $rows;
    }

    private function commemorativeWeek(): array
    {
        if (!$this->pdo) {
            return [
                'start' => '',
                'days' => [],
            ];
        }

        $repo = new CommemorativeDateRepository($this->pdo);
        $today = new \DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week');
        $weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
        $days = [];
        $dateMap = [];
        $monthDayMap = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->modify('+' . $i . ' days');
            $key = $date->format('Y-m-d');
            $month = (int) $date->format('n');
            $day = (int) $date->format('j');
            $year = (int) $date->format('Y');
            $weekdayIndex = (int) $date->format('w');
            $label = ($weekdays[$weekdayIndex] ?? '') . ' ' . $date->format('d/m');

            $days[] = [
                'date' => $key,
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'label' => $label,
            ];
            $dateMap[$key] = [];
            $monthDayMap[sprintf('%02d-%02d', $month, $day)] = $key;
        }

        $rows = [];
        try {
            $rows = $repo->listForDates($days, 200);
        } catch (\Throwable $e) {
            $rows = [];
        }
        foreach ($rows as $row) {
            $month = (int) ($row['month'] ?? 0);
            $day = (int) ($row['day'] ?? 0);
            $year = $row['year'] ?? null;
            if ($month < 1 || $day < 1) {
                continue;
            }

            $key = '';
            if ($year) {
                $key = sprintf('%04d-%02d-%02d', (int) $year, $month, $day);
            } else {
                $key = $monthDayMap[sprintf('%02d-%02d', $month, $day)] ?? '';
            }

            if ($key === '' || !array_key_exists($key, $dateMap)) {
                continue;
            }

            $dateMap[$key][] = $row;
        }

        foreach ($days as $index => $info) {
            $days[$index]['items'] = $dateMap[$info['date']] ?? [];
        }

        return [
            'start' => $weekStart->format('Y-m-d'),
            'days' => $days,
        ];
    }

    private function customerStats(): array
    {
        if (!$this->pdo) {
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'newLast30' => 0,
            ];
        }

        try {
            // Usa relacionamento nativo orders.pessoa_id para evitar inflar/fragmentar clientes por JSON/email.
            $sql = "SELECT
                      COUNT(*) AS total,
                      SUM(CASE WHEN EXISTS (
                          SELECT 1 FROM orders o
                          WHERE o.pessoa_id = p.id
                          AND o.date_created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 180 DAY)
                          AND (o.archived = 0 OR o.archived IS NULL)
                      ) THEN 1 ELSE 0 END) AS active,
                      SUM(CASE WHEN NOT EXISTS (
                          SELECT 1 FROM orders o
                          WHERE o.pessoa_id = p.id
                          AND o.date_created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 180 DAY)
                          AND (o.archived = 0 OR o.archived IS NULL)
                      ) THEN 1 ELSE 0 END) AS inactive,
                      SUM(CASE WHEN p.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_last_30
                    FROM pessoas p
                    WHERE EXISTS (SELECT 1 FROM pessoas_papeis r WHERE r.pessoa_id = p.id AND r.role = 'cliente')";
            $stmt = $this->pdo->query($sql);
            $row = $stmt ? $stmt->fetch() : [];
        } catch (\Throwable $e) {
            $row = [];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'newLast30' => (int) ($row['new_last_30'] ?? 0),
        ];
    }

    private function customerByState(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT state AS label, COUNT(*) AS total
                FROM pessoas p
                WHERE state IS NOT NULL AND state <> ''
                  AND EXISTS (SELECT 1 FROM pessoas_papeis r WHERE r.pessoa_id = p.id AND r.role = 'cliente')
                GROUP BY state
                ORDER BY total DESC
                LIMIT 5";
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    private function customerEngagement(array $customerStats): array
    {
        $segmentDefinitions = $this->customerEngagementSegmentDefinitions();
        $rows = [];
        foreach (array_keys($segmentDefinitions) as $segmentKey) {
            $rows[$segmentKey] = $this->customerEngagementRowsForSegment($segmentKey);
        }

        $totalCustomers = (int) ($customerStats['total'] ?? 0);
        $engagedLast30 = $this->countCustomersWithRecentOrders(30);
        $engagedLast180 = $this->countCustomersWithRecentOrders(self::CUSTOMER_ENGAGEMENT_SIX_MONTHS_DAYS);
        $inactiveOverSixMonths = max(0, $totalCustomers - $engagedLast180);

        return [
            'totals' => [
                'all' => $totalCustomers,
                'last30' => $engagedLast30,
                'last180' => $engagedLast180,
                'inactive' => $inactiveOverSixMonths,
            ],
            'rows' => $rows,
            'segments' => $segmentDefinitions,
            'defaultSegment' => 'last30',
        ];
    }

    private function customerEngagementSegmentDefinitions(): array
    {
        return [
            'all' => [
                'label' => 'Total de clientes',
                'hint' => 'Clientes registrados no app.',
            ],
            'last30' => [
                'label' => 'Clientes engajados 30 dias',
                'hint' => 'Compraram nos ultimos 30 dias.',
            ],
            'last180' => [
                'label' => 'Clientes engajados 6 meses',
                'hint' => 'Pelo menos uma compra nos ultimos 6 meses.',
            ],
            'inactive' => [
                'label' => 'Mais de 6 meses sem comprar',
                'hint' => 'Sem compras nos ultimos 6 meses.',
            ],
        ];
    }

    private function customerEngagementRowsForSegment(string $segmentKey): array
    {
        return match ($segmentKey) {
            'last30' => $this->customerEngagementRows(
                '(value_last_30 > 0 OR qty_last_30 > 0)',
                "value_last_30 DESC, qty_last_30 DESC, COALESCE(last_purchase_date, '1970-01-01') DESC",
                self::CUSTOMER_ENGAGEMENT_ROW_LIMIT
            ),
            'last180' => $this->customerEngagementRows(
                "last_purchase_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . self::CUSTOMER_ENGAGEMENT_SIX_MONTHS_DAYS . " DAY)",
                "COALESCE(last_purchase_date, '1970-01-01') DESC",
                self::CUSTOMER_ENGAGEMENT_ROW_LIMIT
            ),
            'inactive' => $this->customerEngagementRows(
                "(last_purchase_date IS NULL OR last_purchase_date < DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . self::CUSTOMER_ENGAGEMENT_SIX_MONTHS_DAYS . " DAY))",
                "COALESCE(last_purchase_date, '1970-01-01') ASC",
                self::CUSTOMER_ENGAGEMENT_ROW_LIMIT
            ),
            default => $this->customerEngagementRows(
                '',
                "COALESCE(last_purchase_date, '1970-01-01') DESC, value_last_30 DESC, qty_last_30 DESC",
                self::CUSTOMER_ENGAGEMENT_ROW_LIMIT
            ),
        };
    }

    private function customerEngagementRows(string $filter, string $orderBy, int $limit): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = 'SELECT base.* FROM (' . $this->customerEngagementBaseQuery() . ') base';
        if ($filter !== '') {
            $sql .= ' WHERE ' . $filter;
        }
        $sql .= ' ORDER BY ' . $orderBy;
        $sql .= ' LIMIT :limit';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $rows = [];
        }

        return array_map([$this, 'normalizeCustomerEngagementRow'], $rows);
    }

    private function customerEngagementBaseQuery(): string
    {
        $itemQuantityExpr = $this->orderItemsQuantityExpr('oi');
        $itemValueExpr = $this->orderItemsValueExpr('oi');
        $customerKey = $this->orderCustomerKeySql('o');

        return "SELECT
          {$customerKey} AS customer_key,
          COALESCE(NULLIF(o.billing_name, ''), pe.full_name) AS billing_name,
          JSON_UNQUOTE(JSON_EXTRACT(o.buyer_info, '$.first_name')) AS first_name,
          JSON_UNQUOTE(JSON_EXTRACT(o.buyer_info, '$.last_name')) AS last_name,
          COALESCE(NULLIF(pe.email, ''),
                   NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.buyer_info, '$.email')), ''),
                   NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.billing_info, '$.email')), ''),
                   NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.shipping_info, '$.email')), '')) AS email,
          MAX(o.date_created) AS last_purchase_date,
          SUM(CASE WHEN o.date_created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) THEN {$itemQuantityExpr} ELSE 0 END) AS qty_last_30,
          SUM(CASE WHEN o.date_created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 365 DAY) THEN {$itemQuantityExpr} ELSE 0 END) AS qty_last_365,
          SUM(CASE WHEN o.date_created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) THEN {$itemValueExpr} ELSE 0 END) AS value_last_30,
          SUM(CASE WHEN o.date_created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 365 DAY) THEN {$itemValueExpr} ELSE 0 END) AS value_last_365
        FROM orders o
        LEFT JOIN pessoas pe ON pe.id = o.pessoa_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.date_created IS NOT NULL
          AND (o.archived = 0 OR o.archived IS NULL)
        GROUP BY customer_key, billing_name, first_name, last_name, email";
    }

    private function countCustomersWithRecentOrders(int $days): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $customerKey = $this->orderCustomerKeySql('o');
        $sql = "SELECT COUNT(DISTINCT {$customerKey}) AS total
                FROM orders o
                WHERE o.date_created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$days} DAY)
                  AND (o.archived = 0 OR o.archived IS NULL)";
        try {
            $stmt = $this->pdo->query($sql);
            $row = $stmt ? $stmt->fetch() : [];
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function normalizeCustomerEngagementRow(array $row): array
    {
        // Priorizar billing_name (campo principal na tabela orders)
        $name = trim((string) ($row['billing_name'] ?? ''));
        if ($name === '') {
            // Legacy fallback: first_name + last_name (de buyer_info JSON)
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $name = trim($firstName . ' ' . $lastName);
        }
        $email = trim((string) ($row['email'] ?? ''));
        if ($name === '') {
            $name = $email;
        }

        return [
            'customer_key' => (string) ($row['customer_key'] ?? ''),
            'name' => $name,
            'email' => $email,
            'last_purchase_date' => $this->normalizeCustomerEngagementDate($row['last_purchase_date'] ?? null),
            'last_active_date' => null,
            'qty_last_30' => (int) ($row['qty_last_30'] ?? 0),
            'qty_last_365' => (int) ($row['qty_last_365'] ?? 0),
            'value_last_30' => (float) ($row['value_last_30'] ?? 0),
            'value_last_365' => (float) ($row['value_last_365'] ?? 0),
        ];
    }

    private function normalizeCustomerEngagementDate(?string $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '0000-00-00' || $text === '0000-00-00 00:00:00') {
            return null;
        }
        return $text;
    }

    private function collectionStats(): array
    {
        if (!$this->pdo) {
            return [
                'total' => 0,
                'withProducts' => 0,
            ];
        }

        $total = $this->scalar("SELECT COUNT(*) FROM catalog_categories WHERE status = 'ativa'");

        // Tenta via category_id (relação direta) e fallback via JSON metadata (wc_category_term_ids).
        $withProductsDirect = $this->scalar("SELECT COUNT(DISTINCT c.id)
            FROM catalog_categories c
            INNER JOIN products p ON p.category_id = c.id
            WHERE c.status = 'ativa'
              AND " . $this->stockFilterSql('p'));

        // Se o resultado direto é baixo (dados migrados armazenam IDs reais no metadata),
        // tenta via JSON para cobrir o mapeamento WooCommerce -> catalog_categories.
        if ($withProductsDirect < 3) {
            try {
                $withProductsJson = $this->scalar(
                    "SELECT COUNT(DISTINCT c.id)
                     FROM catalog_categories c
                     WHERE c.status = 'ativa'
                       AND EXISTS (
                           SELECT 1 FROM products p,
                           JSON_TABLE(
                               JSON_EXTRACT(p.metadata, '$.wc_category_term_ids'),
                               '\$[*]' COLUMNS (cat_id BIGINT UNSIGNED PATH '\$')
                           ) jt
                           WHERE " . $this->stockFilterSql('p') . "
                             AND jt.cat_id = c.id
                       )"
                );
                $withProducts = max($withProductsDirect, $withProductsJson);
            } catch (\Throwable $e) {
                $withProducts = $withProductsDirect;
            }
        } else {
            $withProducts = $withProductsDirect;
        }

        return [
            'total' => $total,
            'withProducts' => $withProducts,
        ];
    }

    private function payablesWidget(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $repo = new FinanceEntryRepository($this->pdo);
        return $repo->listPayablesWindow();
    }

    private function salesMetrics(): array
    {
        if (!$this->pdo) {
            return [
                'today' => $this->emptySalesMetrics(),
                'last7' => $this->emptySalesMetrics(),
                'last30' => $this->emptySalesMetrics(),
                'months' => [],
            ];
        }

        $ranges = $this->salesRanges(new DateTimeImmutable('now'));
        
        // Try to use materialized data
        $today = $this->getSalesMetricsForPeriod(
            $ranges['todayStart']->format('Y-m-d'),
            $ranges['todayEnd']->format('Y-m-d'),
            'today'
        );
        
        $last7 = $this->getSalesMetricsForPeriod(
            $ranges['last7Start']->format('Y-m-d'),
            $ranges['todayEnd']->format('Y-m-d'),
            'last7'
        );
        
        $last30 = $this->getSalesMetricsForPeriod(
            $ranges['last30Start']->format('Y-m-d'),
            $ranges['todayEnd']->format('Y-m-d'),
            'last30'
        );

        $monthlySeries = $this->querySalesSummaryMonthly($ranges['monthsStart'], $ranges['todayEnd'], 12);

        return [
            'today' => $today,
            'last7' => $last7,
            'last30' => $last30,
            'months' => $monthlySeries,
        ];
    }

    private function getSalesMetricsForPeriod(string $dateFrom, string $dateTo, string $label): array
    {
        // Try materialized data first
        if ($this->salesDailyRepo && $this->salesDailyRepo->hasData($dateFrom, $dateTo)) {
            $agg = $this->salesDailyRepo->aggregate($dateFrom, $dateTo);
            $customers = $this->countOrderCustomersInPeriod($dateFrom . ' 00:00:00', $dateTo . ' 23:59:59');
            
            return [
                'value' => $agg['total_revenue'],
                'quantity' => $agg['total_quantity'],
                'customers' => $customers,
                'marginAverage' => $agg['avg_margin'],
                'marginTotal' => $agg['total_profit'],
                'marginBreakdown' => [],
                'source' => 'materialized',
            ];
        }

        // Fallback to live query
        $ranges = $this->salesRanges(new DateTimeImmutable('now'));
        $summaryRow = $this->querySalesSummaryRanges($ranges)[0] ?? [];
        
        $value = (float) ($summaryRow['value_' . $label] ?? 0);
        $quantity = max(0, (int) round($summaryRow['quantity_' . $label] ?? 0));
        $customers = max(0, (int) round($summaryRow['customers_' . $label] ?? 0));
        $profit = (float) ($summaryRow['profit_' . $label] ?? 0);
        $margin = $value > 0 ? ($profit / $value) : null;

        return [
            'value' => $value,
            'quantity' => $quantity,
            'customers' => $customers,
            'marginAverage' => $margin,
            'marginTotal' => $profit,
            'marginBreakdown' => [],
            'source' => 'live',
        ];
    }

    private function salesRanges(DateTimeImmutable $reference): array
    {
        $todayStart = $reference->setTime(0, 0, 0);
        $todayEnd = $reference->setTime(23, 59, 59);
        $last7Start = $todayStart->sub(new DateInterval('P6D'));
        $last30Start = $todayStart->sub(new DateInterval('P29D'));
        $monthsStart = $todayStart->modify('first day of this month')->sub(new DateInterval('P11M'))->setTime(0, 0, 0);

        return [
            'todayStart' => $todayStart,
            'todayEnd' => $todayEnd,
            'last7Start' => $last7Start,
            'last30Start' => $last30Start,
            'monthsStart' => $monthsStart,
        ];
    }

    private function querySalesSummaryRanges(array $ranges): array
    {
        if (!$this->pdo) {
            return [];
        }

        $customerKey = $this->orderCustomerKeySql('o');
        $itemValueExpr = $this->orderItemsValueExpr('oi');
        $itemQuantityExpr = $this->orderItemsQuantityExpr('oi');
        $itemProfitExpr = $this->orderItemsProfitExpr('oi');
        $sql = "SELECT
                SUM(CASE WHEN o.date_created BETWEEN :today_start AND :today_end THEN {$itemValueExpr} ELSE 0 END) AS value_today,
                SUM(CASE WHEN o.date_created BETWEEN :today_start AND :today_end THEN {$itemQuantityExpr} ELSE 0 END) AS quantity_today,
                COUNT(DISTINCT CASE WHEN o.date_created BETWEEN :today_start AND :today_end THEN {$customerKey} END) AS customers_today,
                SUM(CASE WHEN o.date_created BETWEEN :today_start AND :today_end THEN {$itemProfitExpr} ELSE 0 END) AS profit_today,
                SUM(CASE WHEN o.date_created BETWEEN :last7_start AND :today_end THEN {$itemValueExpr} ELSE 0 END) AS value_last7,
                SUM(CASE WHEN o.date_created BETWEEN :last7_start AND :today_end THEN {$itemQuantityExpr} ELSE 0 END) AS quantity_last7,
                COUNT(DISTINCT CASE WHEN o.date_created BETWEEN :last7_start AND :today_end THEN {$customerKey} END) AS customers_last7,
                SUM(CASE WHEN o.date_created BETWEEN :last7_start AND :today_end THEN {$itemProfitExpr} ELSE 0 END) AS profit_last7,
                SUM(CASE WHEN o.date_created BETWEEN :last30_start AND :today_end THEN {$itemValueExpr} ELSE 0 END) AS value_last30,
                SUM(CASE WHEN o.date_created BETWEEN :last30_start AND :today_end THEN {$itemQuantityExpr} ELSE 0 END) AS quantity_last30,
                COUNT(DISTINCT CASE WHEN o.date_created BETWEEN :last30_start AND :today_end THEN {$customerKey} END) AS customers_last30,
                SUM(CASE WHEN o.date_created BETWEEN :last30_start AND :today_end THEN {$itemProfitExpr} ELSE 0 END) AS profit_last30
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             LEFT JOIN products p ON p.sku = oi.product_sku
             WHERE o.date_created BETWEEN :months_start AND :today_end
               AND (o.archived = 0 OR o.archived IS NULL)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':today_start' => $ranges['todayStart']->format('Y-m-d H:i:s'),
            ':today_end' => $ranges['todayEnd']->format('Y-m-d H:i:s'),
            ':last7_start' => $ranges['last7Start']->format('Y-m-d H:i:s'),
            ':last30_start' => $ranges['last30Start']->format('Y-m-d H:i:s'),
            ':months_start' => $ranges['monthsStart']->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll();
    }

    private function querySalesSummaryMonthly(DateTimeImmutable $start, DateTimeImmutable $end, int $count): array
    {
        if (!$this->pdo || $count <= 0) {
            return [];
        }

        $customerKey = $this->orderCustomerKeySql('o');
        $itemValueExpr = $this->orderItemsValueExpr('oi');
        $itemQuantityExpr = $this->orderItemsQuantityExpr('oi');
        $itemProfitExpr = $this->orderItemsProfitExpr('oi');
        $sql = "SELECT
                DATE_FORMAT(o.date_created, '%m/%Y') AS label,
                SUM({$itemValueExpr}) AS value,
                SUM({$itemQuantityExpr}) AS quantity,
                COUNT(DISTINCT {$customerKey}) AS customers,
                SUM({$itemProfitExpr}) AS profit
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             LEFT JOIN products p ON p.sku = oi.product_sku
             WHERE o.date_created BETWEEN :start AND :end
               AND (o.archived = 0 OR o.archived IS NULL)
             GROUP BY DATE_FORMAT(o.date_created, '%m/%Y')
             ORDER BY MIN(o.date_created) DESC
             LIMIT {$count}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end' => $end->format('Y-m-d H:i:s'),
        ]);
        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            $value = (float) ($row['value'] ?? 0);
            $profit = (float) ($row['profit'] ?? 0);
            $margin = $value > 0 ? ($profit / $value) : null;
            
        return [
            'label' => trim((string) ($row['label'] ?? '')),
            'metrics' => [
                'value' => $value,
                'quantity' => max(0, (int) round($row['quantity'] ?? 0)),
                    'customers' => max(0, (int) round($row['customers'] ?? 0)),
                    'marginAverage' => $margin,
                    'marginTotal' => $profit,
                    'marginBreakdown' => [],
                ],
            ];
        }, $rows);
    }

    private function countOrderCustomersInPeriod(string $dateTimeFrom, string $dateTimeTo): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $customerKey = $this->orderCustomerKeySql('o');
        $sql = "SELECT COUNT(DISTINCT {$customerKey}) AS total
                FROM orders o
                WHERE o.date_created BETWEEN :date_from AND :date_to
                  AND (o.archived = 0 OR o.archived IS NULL)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':date_from' => $dateTimeFrom,
                ':date_to' => $dateTimeTo,
            ]);
            $row = $stmt->fetch();
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function orderCustomerKeySql(string $alias = 'o'): string
    {
        return "COALESCE(NULLIF(CAST({$alias}.pessoa_id AS CHAR), '0'),
                         NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.buyer_info, '$.email')), ''),
                         NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.billing_info, '$.email')), ''),
                         NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.shipping_info, '$.email')), ''),
                         CONCAT('order:', {$alias}.id))";
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

    private function emptySalesMetrics(): array
    {
        return [
            'value' => 0.0,
            'quantity' => 0,
            'customers' => 0,
            'marginAverage' => null,
            'marginTotal' => null,
        ];
    }

    private function productActivity(array $salesMetrics): array
    {
        $stock = $this->activeStockSummary();
        $reference = new DateTimeImmutable('now');
        $recentRanges = $this->buildRecentRanges($reference);
        $periods = [];
        foreach ($recentRanges as $key => $range) {
            $periods[] = [
                'label' => $range['label'],
                'registered' => $this->countProductRegistrationsInRange($range['start'], $range['end']),
                'sold' => max(0, (int) ($salesMetrics[$key]['quantity'] ?? 0)),
                'writeOff' => $this->countWriteOffsInRange($range['start'], $range['end']),
            ];
        }

        $monthlyRanges = $this->buildMonthlyRanges(12, $reference);
        $salesMonthMap = $this->mapSalesMonths($salesMetrics['months'] ?? []);
        $months = [];
        foreach ($monthlyRanges as $range) {
            $label = $range['label'];
            $months[] = [
                'label' => $label,
                'registered' => $this->countProductRegistrationsInRange($range['start'], $range['end']),
                'sold' => $salesMonthMap[$label] ?? 0,
                'writeOff' => $this->countWriteOffsInRange($range['start'], $range['end']),
            ];
        }

        return [
            'availability' => $stock,
            'periods' => $periods,
            'months' => $months,
        ];
    }

    private function activeStockSummary(): array
    {
        if (!$this->pdo) {
            return ['total' => 0, 'statuses' => []];
        }

        $sql = "SELECT p.status AS status, COUNT(*) AS total
                FROM products p
                WHERE " . $this->stockFilterSql('p') . "
                GROUP BY status
                ORDER BY total DESC, status ASC";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        $total = 0;
        $statuses = [];
        foreach ($rows as $row) {
            $count = max(0, (int) ($row['total'] ?? 0));
            $status = (string) ($row['status'] ?? '');
            if ($status === '') {
                continue;
            }
            $statuses[] = [
                'status' => $status,
                'total' => $count,
            ];
            $total += $count;
        }

        return [
            'total' => $total,
            'statuses' => $statuses,
        ];
    }

    private function buildRecentRanges(DateTimeImmutable $reference): array
    {
        $todayStart = $reference->setTime(0, 0, 0);
        $todayEnd = $reference->setTime(23, 59, 59);
        $last7Start = $todayStart->sub(new DateInterval('P6D'));
        $last30Start = $todayStart->sub(new DateInterval('P29D'));

        return [
            'today' => [
                'label' => 'Hoje',
                'start' => $todayStart,
                'end' => $todayEnd,
            ],
            'last7' => [
                'label' => 'Ultimos 7 dias',
                'start' => $last7Start,
                'end' => $todayEnd,
            ],
            'last30' => [
                'label' => 'Ultimos 30 dias',
                'start' => $last30Start,
                'end' => $todayEnd,
            ],
        ];
    }

    private function buildMonthlyRanges(int $count, DateTimeImmutable $reference): array
    {
        if ($count <= 0) {
            return [];
        }

        $anchor = $reference->modify('first day of last month')->setTime(0, 0, 0);
        $earliest = $anchor->sub(new DateInterval('P' . ($count - 1) . 'M'));
        $ranges = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $earliest->add(new DateInterval('P' . $i . 'M'));
            $end = $start->modify('last day of this month')->setTime(23, 59, 59);
            $ranges[] = [
                'label' => $start->format('m/Y'),
                'start' => $start,
                'end' => $end,
            ];
        }
        return $ranges;
    }

    private function countProductRegistrationsInRange(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if (!$this->pdo) {
            return 0;
        }

        $legacyFromFile = $this->countLegacyProductRegistrationsFromFile($start, $end);
        if ($legacyFromFile !== null) {
            return $legacyFromFile;
        }

        // Após importações em lote, products.created_at pode refletir só o momento da migração.
        // Quando disponível, prioriza metadata.legacy_updated_at (ISO8601 do legado).
        $legacyTimestampExpr = "STR_TO_DATE(
            REPLACE(SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.legacy_updated_at')), 1, 19), 'T', ' '),
            '%Y-%m-%d %H:%i:%s'
        )";
        $registrationExpr = "COALESCE({$legacyTimestampExpr}, created_at)";

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM products
             WHERE {$registrationExpr} BETWEEN :start AND :end"
        );
        $stmt->execute([
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end' => $end->format('Y-m-d H:i:s'),
        ]);
        $row = $stmt->fetch();
        return (int) ($row['total'] ?? 0);
    }

    private function countLegacyProductRegistrationsFromFile(DateTimeImmutable $start, DateTimeImmutable $end): ?int
    {
        $byDay = $this->loadLegacyProductRegistrationsByDay();
        if (empty($byDay)) {
            return null;
        }

        $cursor = $start->setTime(0, 0, 0);
        $until = $end->setTime(0, 0, 0);
        $total = 0;
        while ($cursor <= $until) {
            $total += (int) ($byDay[$cursor->format('Y-m-d')] ?? 0);
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $total;
    }

    private function loadLegacyProductRegistrationsByDay(): array
    {
        if ($this->legacyProductRegistrationsByDay !== null) {
            return $this->legacyProductRegistrationsByDay;
        }

        $this->legacyProductRegistrationsByDay = [];
        $filePath = $this->findLegacyProductsJsonlFile();
        if ($filePath === null) {
            return $this->legacyProductRegistrationsByDay;
        }

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return $this->legacyProductRegistrationsByDay;
        }

        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (($decoded['type'] ?? '') !== 'data' || ($decoded['entity'] ?? '') !== 'products') {
                continue;
            }

            $legacyUpdatedAt = trim((string) ($decoded['legacy_updated_at'] ?? ''));
            if ($legacyUpdatedAt === '') {
                continue;
            }

            try {
                $date = new DateTimeImmutable($legacyUpdatedAt);
            } catch (\Throwable $e) {
                continue;
            }

            $key = $date->format('Y-m-d');
            $this->legacyProductRegistrationsByDay[$key] = (int) ($this->legacyProductRegistrationsByDay[$key] ?? 0) + 1;
        }

        fclose($handle);

        return $this->legacyProductRegistrationsByDay;
    }

    private function loadLegacySupplyBySku(): array
    {
        if ($this->legacySupplyBySku !== null) {
            return $this->legacySupplyBySku;
        }

        $this->legacySupplyBySku = [];
        $filePath = $this->findLegacySupplyJsonlFile();
        if ($filePath === null) {
            return $this->legacySupplyBySku;
        }

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return $this->legacySupplyBySku;
        }

        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (($decoded['type'] ?? '') !== 'data' || ($decoded['entity'] ?? '') !== 'produto_fornecimentos') {
                continue;
            }

            $sku = trim((string) ($decoded['data']['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $source = strtolower(trim((string) ($decoded['data']['source'] ?? '')));
            $cost = array_key_exists('cost', $decoded['data']) ? (float) $decoded['data']['cost'] : null;
            $percent = array_key_exists('percentual_consignacao', $decoded['data'])
                ? (float) $decoded['data']['percentual_consignacao']
                : null;

            $this->legacySupplyBySku[$sku] = [
                'source' => $source,
                'cost' => $cost,
                'percent' => $percent,
            ];
        }

        fclose($handle);

        return $this->legacySupplyBySku;
    }

    private function findLegacyProductsJsonlFile(): ?string
    {
        foreach ($this->legacyJsonlCandidates() as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            $firstLine = fgets($handle);
            fclose($handle);
            if (!is_string($firstLine) || $firstLine === '') {
                continue;
            }

            $manifest = json_decode($firstLine, true);
            if (!is_array($manifest) || ($manifest['type'] ?? '') !== 'manifest') {
                continue;
            }

            $productsCount = (int) ($manifest['counts']['products'] ?? 0);
            if ($productsCount > 0) {
                return $file;
            }
        }

        return null;
    }

    private function findLegacySupplyJsonlFile(): ?string
    {
        foreach ($this->legacyJsonlCandidates(false) as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }

            $found = false;
            while (($line = fgets($handle)) !== false) {
                if (str_contains($line, '"entity":"produto_fornecimentos"')) {
                    $found = true;
                    break;
                }
            }
            fclose($handle);

            if ($found) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function legacyJsonlCandidates(bool $excludeDelta = true): array
    {
        $root = dirname(__DIR__, 2);
        $patterns = [
            $root . '/import/erp_migracao_LEGACY_RETRATOAPP2_MAIN_*.jsonl',
            $root . '/uploads/migration-imports/erp_migracao_LEGACY_RETRATOAPP2_MAIN_*.jsonl',
            dirname($root) . '/retratoapp2/scripts/reports/erp_migracao_LEGACY_RETRATOAPP2_MAIN_*.jsonl',
        ];

        $candidates = [];
        foreach ($patterns as $pattern) {
            $files = glob($pattern) ?: [];
            foreach ($files as $file) {
                if (!is_string($file)) {
                    continue;
                }
                if ($excludeDelta && str_contains($file, '.delta.')) {
                    continue;
                }
                $candidates[] = $file;
            }
        }

        $candidates = array_values(array_unique($candidates));
        usort($candidates, static function (string $a, string $b): int {
            $aMtime = @filemtime($a) ?: 0;
            $bMtime = @filemtime($b) ?: 0;
            return $bMtime <=> $aMtime;
        });

        return $candidates;
    }

    private function countWriteOffsInRange(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if (!$this->pdo) {
            return 0;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(ABS(COALESCE(quantity_change, 0))), 0) AS total
                 FROM inventory_movements
                 WHERE movement_type = 'baixa'
                   AND COALESCE(occurred_at, created_at) BETWEEN :start AND :end"
            );
            $stmt->execute([
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s'),
            ]);
            $row = $stmt->fetch();
            $movementTotal = (int) ($row['total'] ?? 0);
            if ($movementTotal > 0) {
                return $movementTotal;
            }
        } catch (\Throwable $e) {
            // fallback abaixo
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(COALESCE(quantity, 0)), 0) AS total
                 FROM produto_baixas
                 WHERE created_at BETWEEN :start AND :end"
            );
            $stmt->execute([
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s'),
            ]);
            $row = $stmt->fetch();
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function stockFilterSql(string $alias = 'p'): string
    {
        // Mantém alinhamento com o legado: todo item com saldo positivo entra no estoque do painel.
        // Apenas "baixado" é excluído por representar saída definitiva.
        return "{$alias}.quantity > 0 AND {$alias}.status <> 'baixado'";
    }

    private function mediaLinksTableExists(): bool
    {
        if ($this->hasMediaLinksTable !== null) {
            return $this->hasMediaLinksTable;
        }

        try {
            $this->hasMediaLinksTable = (bool) $this->scalar(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'media_links'"
            );
        } catch (\Throwable $e) {
            $this->hasMediaLinksTable = false;
        }

        return $this->hasMediaLinksTable;
    }

    private function productMetadataImageExprSql(string $alias = 'p'): string
    {
        return "COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.metadata, '$.image_src')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.metadata, '$.image_url')), '')
        )";
    }

    private function productHasImageSql(string $alias = 'p'): string
    {
        $metadataImageExpr = $this->productMetadataImageExprSql($alias);
        $clauses = [
            "({$metadataImageExpr} IS NOT NULL AND {$metadataImageExpr} <> 'null')",
        ];

        if ($this->mediaLinksTableExists()) {
            $clauses[] = "EXISTS (
                SELECT 1 FROM media_links ml
                WHERE ml.entity_type = 'product'
                  AND CAST(ml.entity_id AS CHAR) = CAST({$alias}.sku AS CHAR)
            )";
        }

        return implode(' OR ', $clauses);
    }

    private function mapSalesMonths(array $months): array
    {
        $map = [];
        foreach ($months as $entry) {
            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $quantity = $entry['metrics']['quantity'] ?? ($entry['quantity'] ?? 0);
            $map[$label] = max(0, (int) $quantity);
        }
        return $map;
    }

    private function emptyProductActivity(): array
    {
        $reference = new DateTimeImmutable('now');
        $recentRanges = $this->buildRecentRanges($reference);
        $periods = [];
        foreach ($recentRanges as $range) {
            $periods[] = [
                'label' => $range['label'],
                'registered' => 0,
                'sold' => 0,
                'writeOff' => 0,
            ];
        }

        $months = [];
        foreach ($this->buildMonthlyRanges(12, $reference) as $range) {
            $months[] = [
                'label' => $range['label'],
                'registered' => 0,
                'sold' => 0,
                'writeOff' => 0,
            ];
        }

        return [
            'availability' => ['total' => 0, 'statuses' => []],
            'periods' => $periods,
            'months' => $months,
        ];
    }

    private function scalar(string $sql): int
    {
        if (!$this->pdo) {
            return 0;
        }
        $stmt = $this->pdo->query($sql);
        $value = $stmt ? $stmt->fetchColumn() : 0;
        return $value !== false ? (int) $value : 0;
    }

    private function scalarFloat(string $sql): ?float
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->query($sql);
        $value = $stmt ? $stmt->fetchColumn() : null;
        if ($value === null || $value === false) {
            return null;
        }
        return (float) $value;
    }

    private function countPeopleByRole(string $role): int
    {
        if (!$this->pdo) {
            return 0;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(DISTINCT pessoa_id) FROM pessoas_papeis WHERE role = :role');
            $stmt->execute([':role' => $role]);
            $value = $stmt->fetchColumn();
            return $value !== false ? (int) $value : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    private function loadPeopleByIds(array $ids): array
    {
        if (!$this->pdo) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, full_name, email FROM pessoas WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
            }
        }
        return $map;
    }

    private function hydrateOrderNames(array $row): array
    {
        foreach (['billing_info', 'shipping_info', 'buyer_info'] as $key) {
            if (!isset($row[$key]) || $row[$key] === '') {
                continue;
            }
            $decoded = json_decode((string) $row[$key], true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $field => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $normalizedKey = $key === 'billing_info' ? 'billing_' . $field : ($key === 'shipping_info' ? 'shipping_' . $field : 'customer_' . $field);
                if (!isset($row[$normalizedKey])) {
                    $row[$normalizedKey] = $value;
                }
            }
        }
        return $row;
    }

    private function emptyInsights(): array
    {
        return [
            'totals' => ['products' => 0, 'vendors' => 0, 'customers' => 0, 'collections' => 0],
            'inventory' => [
                'totalProducts' => 0,
                'totalUnits' => 0,
                'potentialValue' => 0,
                'investedValue' => 0,
                'avgMargin' => null,
                'consignados' => 0,
            ],
            'status' => [],
            'sources' => [],
            'topVendors' => [],
            'lowStock' => [],
            'customers' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'newLast30' => 0],
            'geo' => [],
            'collections' => ['total' => 0, 'withProducts' => 0],
            'openBags' => [],
            'pendingConsignments' => [],
            'pendingDeliveries' => [],
            'pendingRefunds' => [],
            'creditBalances' => [],
            'consignmentVoucherTotals' => [],
            'productsWithoutPhotos' => [],
            'productsWithPhotosUnpublished' => [],
            'productsMissingCost' => [],
            'commemorativeWeek' => ['start' => '', 'days' => []],
            'salesMetrics' => [
                'today' => $this->emptySalesMetrics(),
                'last7' => $this->emptySalesMetrics(),
                'last30' => $this->emptySalesMetrics(),
                'months' => [],
            ],
            'productActivity' => $this->emptyProductActivity(),
            'payables' => [],
        ];
    }
}
