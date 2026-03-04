<?php

namespace App\Services;

use App\Repositories\BagRepository;
use App\Repositories\BankAccountRepository;
use App\Repositories\BankRepository;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\CollectionRepository;
use App\Repositories\CommemorativeDateRepository;
use App\Repositories\ConsignmentItemRepository;
use App\Repositories\ConsignmentCreditRepository;
use App\Repositories\ConsignmentIntakeRepository;
use App\Repositories\ConsignmentPayoutItemRepository;
use App\Repositories\ConsignmentPayoutRepository;
use App\Repositories\ConsignmentPeriodLockRepository;
use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ConsignmentReportViewRepository;
use App\Repositories\ConsignmentRepository;
use App\Repositories\ConsignmentSaleRepository;
use App\Repositories\CreditAccountRepository;
use App\Repositories\CreditEntryRepository;
use App\Repositories\CustomerHistoryRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DashboardLayoutRepository;
use App\Repositories\DashRefreshLogRepository;
use App\Repositories\DashSalesDailyRepository;
use App\Repositories\DashStockSnapshotRepository;
use App\Repositories\DeliveryTypeRepository;
use App\Repositories\CarrierRepository;
use App\Repositories\FinanceEntriesRepository;
use App\Repositories\FinanceCategoryRepository;
use App\Repositories\FinanceEntryRepository;
use App\Repositories\FinanceTransactionRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\InventoryMovementRepository;
use App\Repositories\MediaFileRepository;
use App\Repositories\MediaLinkRepository;
use App\Repositories\OrderAddressRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderPaymentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderReturnRepository;
use App\Repositories\OrderShipmentRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentTerminalRepository;
use App\Repositories\PeopleCompatViewRepository;
use App\Repositories\PersonRepository;
use App\Repositories\PersonRoleRepository;
use App\Repositories\PieceLotRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\ProductWriteOffRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\RuleRepository;
use App\Repositories\RuleVersionRepository;
use App\Repositories\SalesChannelRepository;
use App\Repositories\ShipmentEventRepository;
use App\Repositories\SkuReservationRepository;
use App\Repositories\TimeEntryRepository;
use App\Repositories\UserRepository;
use App\Repositories\VendorRepository;
use App\Repositories\CatalogBrandRepository;
use App\Repositories\CatalogCategoryRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Repositories\VoucherIdentificationPatternRepository;
use App\Support\SchemaBootstrapper;
use PDO;
use Throwable;

/**
 * Orquestra a execução única das rotinas de criação de esquema.
 */
class SchemaBootstrapService
{
    /**
     * @var array<string, string>
     */
    private const REPOSITORIES = [
        'rules' => RuleRepository::class,
        'rule_versions' => RuleVersionRepository::class,
        'sku_reservations' => SkuReservationRepository::class,
        'customers' => CustomerRepository::class,
        'dashboard_layouts' => DashboardLayoutRepository::class,
        'dash_refresh_log' => DashRefreshLogRepository::class,
        'dash_sales_daily' => DashSalesDailyRepository::class,
        'dash_stock_snapshot' => DashStockSnapshotRepository::class,
        'users' => UserRepository::class,
        'order_returns' => OrderReturnRepository::class,
        'finance_entries' => FinanceEntryRepository::class,
        'delivery_types' => DeliveryTypeRepository::class,
        'carriers' => CarrierRepository::class,
        'voucher_identification_patterns' => VoucherIdentificationPatternRepository::class,
        'banks' => BankRepository::class,
        'bank_accounts' => BankAccountRepository::class,
        'voucher_accounts' => VoucherAccountRepository::class,
        'credit_accounts' => CreditAccountRepository::class,
        'credit_entries' => CreditEntryRepository::class,
        'consignment_intakes' => ConsignmentIntakeRepository::class,
        'consignments' => ConsignmentRepository::class,
        'consignment_items' => ConsignmentItemRepository::class,
        'sales_channels' => SalesChannelRepository::class,
        'orders' => OrderRepository::class,
        'order_items' => OrderItemRepository::class,
        'order_addresses' => OrderAddressRepository::class,
        'order_payments' => OrderPaymentRepository::class,
        'order_shipments' => OrderShipmentRepository::class,
        'shipment_events' => ShipmentEventRepository::class,
        'collections' => CollectionRepository::class,
        'brands' => BrandRepository::class,
        'categories' => CategoryRepository::class,
        'time_entries' => TimeEntryRepository::class,
        'customer_history' => CustomerHistoryRepository::class,
        'products' => ProductRepository::class,
        'media_files' => MediaFileRepository::class,
        'media_links' => MediaLinkRepository::class,
        'product_supply' => ProductSupplyRepository::class,
        'payment_methods' => PaymentMethodRepository::class,
        'piece_lots' => PieceLotRepository::class,
        'catalog_brands' => CatalogBrandRepository::class,
        'catalog_categories' => CatalogCategoryRepository::class,
        'bags' => BagRepository::class,
        'inventory' => InventoryRepository::class,
        'inventory_movements' => InventoryMovementRepository::class,
        'profiles' => ProfileRepository::class,
        'commemorative_dates' => CommemorativeDateRepository::class,
        'voucher_credit_entries' => VoucherCreditEntryRepository::class,
        'consignment_credit' => ConsignmentCreditRepository::class,
        'payment_terminals' => PaymentTerminalRepository::class,
        'vendors' => VendorRepository::class,
        'finance_categories' => FinanceCategoryRepository::class,
        'finance_entries_core' => FinanceEntriesRepository::class,
        'finance_transactions' => FinanceTransactionRepository::class,
        'product_writeoffs' => ProductWriteOffRepository::class,
        'people' => PersonRepository::class,
        'people_roles' => PersonRoleRepository::class,
        // Módulo de consignação
        'consignment_product_registry' => ConsignmentProductRegistryRepository::class,
        'consignment_sales' => ConsignmentSaleRepository::class,
        'consignment_payouts' => ConsignmentPayoutRepository::class,
        'consignment_payout_items' => ConsignmentPayoutItemRepository::class,
        'consignment_period_locks' => ConsignmentPeriodLockRepository::class,
        'consignment_report_views' => ConsignmentReportViewRepository::class,
    ];

    /**
     * Tabelas que devem existir após o bootstrap completo.
     *
     * @var array<int, string>
     */
    private const EXPECTED_TABLES = [
        'audit_log',
        'bancos',
        'brands',
        'canais_venda',
        'carriers',
        'catalog_brands',
        'catalog_categories',
        'cliente_historico',
        'colecoes',
        'consignacao_creditos',
        'consignacao_devolucao_itens',
        'consignacao_devolucoes',
        'consignacao_recebimento_itens',
        'consignacao_recebimento_produtos',
        'consignacao_recebimentos',
        'consignment_items',
        'consignment_payout_items',
        'consignment_payouts',
        'consignment_period_locks',
        'consignment_product_registry',
        'consignment_report_views',
        'consignment_sales',
        'consignments',
        'contas_bancarias',
        'credit_accounts',
        'credit_entries',
        'cupons_creditos',
        'cupons_creditos_identificacoes',
        'cupons_creditos_movimentos',
        'dash_refresh_log',
        'dash_sales_daily',
        'dash_stock_snapshot',
        'dashboard_layouts',
        'datas_comemorativas',
        'finance_entries',
        'finance_transactions',
        'financeiro_categorias',
        'financeiro_lancamentos',
        'inventario_itens',
        'inventario_logs',
        'inventario_pendentes',
        'inventario_scans',
        'inventarios',
        'inventory_movements',
        'media_files',
        'media_links',
        'metodos_pagamento',
        'order_addresses',
        'order_items',
        'order_payments',
        'order_return_items',
        'order_returns',
        'order_shipments',
        'orders',
        'perfis',
        'pessoas',
        'pessoas_papeis',
        'ponto_registros',
        'product_categories',
        'products',
        'produto_baixas',
        'produto_lotes',
        'sacolinha_itens',
        'sacolinhas',
        'bag_shipments',
        'regras',
        'regras_versoes',
        'shipment_events',
        'sku_reservations',
        'terminais_pagamento',
        'tipos_entrega',
        'usuarios',
    ];

    /**
     * Views que devem existir após o bootstrap completo.
     *
     * @var array<int, string>
     */
    private const EXPECTED_VIEWS = [
        'vw_clientes_compat',
        'vw_fornecedores_compat',
    ];

    private PDO $pdo;

    /**
     * @var array<string, string>
     */
    private array $repositories;

    /**
     * @param PDO $pdo
     * @param array<string, string>|null $repositories
     */
    public function __construct(PDO $pdo, ?array $repositories = null)
    {
        $this->pdo = $pdo;
        $this->repositories = $repositories ?? self::REPOSITORIES;
    }

    /**
     * @return array<int, array{key: string, class: string, status: string, message?: string}>
     */
    public function run(): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com o banco de dados.');
        }

        $results = [];
        try {
            $this->ensureAuditLogTable();
            $results[] = [
                'key' => 'audit_log',
                'class' => self::class . '::ensureAuditLogTable',
                'status' => 'ok',
            ];
        } catch (Throwable $e) {
            $results[] = [
                'key' => 'audit_log',
                'class' => self::class . '::ensureAuditLogTable',
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        SchemaBootstrapper::enable();
        try {
            foreach ($this->repositories as $key => $class) {
                if (!class_exists($class)) {
                    $results[] = ['key' => $key, 'class' => $class, 'status' => 'missing'];
                    continue;
                }
                try {
                    new $class($this->pdo);
                    $results[] = ['key' => $key, 'class' => $class, 'status' => 'ok'];
                } catch (Throwable $e) {
                    $results[] = [
                        'key' => $key,
                        'class' => $class,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            try {
                PeopleCompatViewRepository::ensure($this->pdo);
                $results[] = [
                    'key' => 'people_compat_views',
                    'class' => PeopleCompatViewRepository::class,
                    'status' => 'ok',
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'key' => 'people_compat_views',
                    'class' => PeopleCompatViewRepository::class,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        } finally {
            SchemaBootstrapper::disable();
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    public static function expectedTables(): array
    {
        return self::EXPECTED_TABLES;
    }

    /**
     * @return array<int, string>
     */
    public static function expectedViews(): array
    {
        return self::EXPECTED_VIEWS;
    }

    private function ensureAuditLogTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(20) NOT NULL,
            table_name VARCHAR(100) NOT NULL,
            record_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            user_email VARCHAR(200) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            remote_addr VARCHAR(100) NULL,
            request_uri VARCHAR(500) NULL,
            old_values LONGTEXT NULL,
            new_values LONGTEXT NULL,
            INDEX idx_audit_table (table_name, created_at),
            INDEX idx_audit_user (user_id, created_at),
            INDEX idx_audit_record (table_name, record_id),
            INDEX idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }
}
