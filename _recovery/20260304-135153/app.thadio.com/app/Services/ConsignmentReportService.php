<?php

namespace App\Services;

use App\Repositories\ConsignmentProductRegistryRepository;
use App\Repositories\ConsignmentReportViewRepository;
use App\Repositories\ConsignmentSaleRepository;
use PDO;

/**
 * ConsignmentReportService
 *
 * Serviço central para geração de relatórios dinâmicos de consignação.
 * Responsabilidades:
 *   - Definir metadados de todos os campos disponíveis
 *   - Gerar KPIs do resumo executivo
 *   - Gerar listagem detalhada por peça com campos configuráveis
 *   - Exportar CSV / Excel / PDF
 *   - Gerenciar modelos de visualização (report views)
 */
class ConsignmentReportService
{
    private PDO $pdo;
    private ConsignmentReportViewRepository $viewRepo;
    private ?string $salesChannelTable = null;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->viewRepo = new ConsignmentReportViewRepository($pdo);
    }

    // ─── FIELD METADATA ─────────────────────────────────────────

    /**
     * Retorna a definição completa de todos os campos disponíveis,
     * agrupados por categoria.
     *
     * @return array<string, array{label: string, category: string, required: bool, sortable: bool, type: string}>
     */
    public static function fieldMetadata(): array
    {
        return [
            // ── Identificação ──
            'photo'                => ['label' => 'Foto',                  'category' => 'identification', 'required' => false, 'sortable' => false, 'type' => 'image'],
            'sku'                  => ['label' => 'SKU',                   'category' => 'identification', 'required' => true,  'sortable' => true,  'type' => 'text'],
            'product_name'         => ['label' => 'Produto',               'category' => 'identification', 'required' => true,  'sortable' => true,  'type' => 'text'],
            'category_name'        => ['label' => 'Categoria',             'category' => 'identification', 'required' => false, 'sortable' => true,  'type' => 'text'],
            'brand_name'           => ['label' => 'Marca',                 'category' => 'identification', 'required' => false, 'sortable' => true,  'type' => 'text'],
            'received_at'          => ['label' => 'Data de entrada',       'category' => 'identification', 'required' => false, 'sortable' => true,  'type' => 'date'],
            'days_in_stock'        => ['label' => 'Dias em estoque',       'category' => 'identification', 'required' => false, 'sortable' => true,  'type' => 'integer'],
            'consignment_status'   => ['label' => 'Status',                'category' => 'identification', 'required' => true,  'sortable' => true,  'type' => 'status'],

            // ── Comercial ──
            'price'                => ['label' => 'Valor de venda',        'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'currency'],
            'percent_applied'      => ['label' => '% comissão',            'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'percent'],
            'credit_amount'        => ['label' => 'Valor líquido fornec.', 'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'currency'],
            'net_amount'           => ['label' => 'Valor líquido venda',   'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'currency'],
            'sold_at'              => ['label' => 'Data da venda',         'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'date'],
            'order_id'             => ['label' => 'Pedido #',              'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'link'],
            'customer_name'        => ['label' => 'Cliente',               'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'text'],
            'sales_channel'        => ['label' => 'Canal de venda',        'category' => 'commercial',     'required' => false, 'sortable' => true,  'type' => 'text'],

            // ── Financeiro ──
            'payout_status'        => ['label' => 'Status financeiro',     'category' => 'financial',      'required' => false, 'sortable' => true,  'type' => 'status'],
            'paid_at'              => ['label' => 'Data de pagamento',     'category' => 'financial',      'required' => false, 'sortable' => true,  'type' => 'date'],
            'payout_id'            => ['label' => 'Pagamento #',           'category' => 'financial',      'required' => false, 'sortable' => true,  'type' => 'link'],
            'gross_amount'         => ['label' => 'Valor bruto',           'category' => 'financial',      'required' => false, 'sortable' => true,  'type' => 'currency'],
            'discount_amount'      => ['label' => 'Desconto',              'category' => 'financial',      'required' => false, 'sortable' => true,  'type' => 'currency'],

            // ── Controle ──
            'donation_authorized'  => ['label' => 'Doação autorizada',     'category' => 'control',        'required' => false, 'sortable' => true,  'type' => 'boolean'],
            'returned_at'          => ['label' => 'Data de devolução',     'category' => 'control',        'required' => false, 'sortable' => true,  'type' => 'date'],
            'return_reason'        => ['label' => 'Motivo devolução',      'category' => 'control',        'required' => false, 'sortable' => false, 'type' => 'text'],
            'notes'                => ['label' => 'Observações',           'category' => 'control',        'required' => false, 'sortable' => false, 'type' => 'text'],
        ];
    }

    /**
     * Fields agrupados por categoria para UI.
     *
     * @return array<string, array{label: string, fields: array}>
     */
    public static function fieldsByCategory(): array
    {
        $metadata = self::fieldMetadata();
        $categories = [
            'identification' => ['label' => 'Identificação',  'fields' => []],
            'commercial'     => ['label' => 'Comercial',       'fields' => []],
            'financial'      => ['label' => 'Financeiro',      'fields' => []],
            'control'        => ['label' => 'Controle',        'fields' => []],
        ];

        foreach ($metadata as $key => $meta) {
            $cat = $meta['category'];
            if (isset($categories[$cat])) {
                $categories[$cat]['fields'][$key] = $meta;
            }
        }

        return $categories;
    }

    /**
     * Default field set for "Relatório Completo".
     *
     * @return string[]
     */
    public static function defaultFieldKeys(): array
    {
        return [
            'sku', 'product_name', 'category_name', 'received_at', 'days_in_stock',
            'consignment_status', 'price', 'percent_applied', 'credit_amount',
            'sold_at', 'order_id', 'payout_status', 'paid_at',
        ];
    }

    /**
     * System presets.
     *
     * @return array<string, array>
     */
    public static function systemPresets(): array
    {
        return [
            'completo' => [
                'name'        => 'Relatório Completo',
                'description' => 'Todos os campos relevantes com resumo e detalhe.',
                'detail_level' => 'both',
                'fields'      => self::defaultFieldKeys(),
            ],
            'simplificado' => [
                'name'        => 'Relatório Simplificado',
                'description' => 'Apenas SKU, produto, status, valor e comissão.',
                'detail_level' => 'both',
                'fields'      => ['sku', 'product_name', 'consignment_status', 'price', 'credit_amount', 'payout_status'],
            ],
            'financeiro' => [
                'name'        => 'Relatório Financeiro',
                'description' => 'Foco em valores, comissões e status de pagamento.',
                'detail_level' => 'both',
                'fields'      => ['sku', 'product_name', 'price', 'percent_applied', 'credit_amount', 'net_amount', 'gross_amount', 'discount_amount', 'payout_status', 'paid_at', 'payout_id'],
            ],
            'estoque' => [
                'name'        => 'Relatório de Estoque',
                'description' => 'Apenas itens em estoque com aging.',
                'detail_level' => 'items',
                'fields'      => ['photo', 'sku', 'product_name', 'category_name', 'brand_name', 'received_at', 'days_in_stock', 'consignment_status', 'price'],
            ],
            'envio_mensal' => [
                'name'        => 'Relatório Para Envio Mensal',
                'description' => 'Relatório pronto para enviar às fornecedoras mensalmente.',
                'detail_level' => 'both',
                'fields'      => ['sku', 'product_name', 'received_at', 'consignment_status', 'price', 'percent_applied', 'credit_amount', 'sold_at', 'order_id', 'payout_status', 'paid_at'],
            ],
        ];
    }

    // ─── EXECUTIVE SUMMARY (KPIs) ──────────────────────────────

    /**
     * Gera KPIs do resumo executivo para um fornecedor e período.
     *
     * @param int[] $supplierIds
     */
    public function generateSummary(array $supplierIds, string $dateFrom = '', string $dateTo = ''): array
    {
        if (empty($supplierIds)) {
            return $this->emptySummary();
        }

        $pdo = $this->pdo;

        // Build supplier IN clause
        $inPlaceholders = [];
        $inParams = [];
        foreach ($supplierIds as $i => $sid) {
            $key = ':sid_' . $i;
            $inPlaceholders[] = $key;
            $inParams[$key] = (int) $sid;
        }
        $inSql = implode(',', $inPlaceholders);

        // ── Registry-based counts ──
        $sql = "SELECT
                  SUM(1) AS total_received,
                  SUM(CASE WHEN r.consignment_status = 'em_estoque' THEN 1 ELSE 0 END) AS in_stock_count,
                  SUM(CASE WHEN r.consignment_status = 'em_estoque' THEN COALESCE(p.price, 0) ELSE 0 END) AS in_stock_value,
                  SUM(CASE WHEN r.consignment_status = 'devolvido' THEN 1 ELSE 0 END) AS returned_count,
                  SUM(CASE WHEN r.consignment_status = 'doado' THEN 1 ELSE 0 END) AS donated_count,
                  SUM(CASE WHEN r.consignment_status = 'descartado' THEN 1 ELSE 0 END) AS discarded_count,
                  SUM(CASE WHEN r.consignment_status IN ('vendido_pendente','vendido_pago','proprio_pos_pgto') THEN 1 ELSE 0 END) AS sold_count,
                  AVG(CASE WHEN r.consignment_status = 'em_estoque' AND r.received_at IS NOT NULL THEN DATEDIFF(NOW(), r.received_at) ELSE NULL END) AS avg_aging_days,
                  AVG(CASE WHEN r.consignment_status IN ('vendido_pendente','vendido_pago','proprio_pos_pgto') AND r.received_at IS NOT NULL AND r.status_changed_at IS NOT NULL THEN DATEDIFF(r.status_changed_at, r.received_at) ELSE NULL END) AS avg_days_to_sell
                FROM consignment_product_registry r
                LEFT JOIN products p ON p.sku = r.product_id
                WHERE (r.supplier_pessoa_id IN ({$inSql}) OR r.consignment_supplier_original_id IN ({$inSql}))";

        $params = $inParams;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        // ── Sales-based totals ──
        $saleDateFilter = '';
        $saleParams = $inParams;
        if ($dateFrom !== '') {
            $saleDateFilter .= " AND cs.sold_at >= :date_from";
            $saleParams[':date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $saleDateFilter .= " AND cs.sold_at <= :date_to";
            $saleParams[':date_to'] = $dateTo . ' 23:59:59';
        }

        $sql = "SELECT
                  COUNT(*) AS total_sold_records,
                  SUM(CASE WHEN cs.sale_status = 'ativa' THEN cs.net_amount ELSE 0 END) AS gross_sold_total,
                  SUM(CASE WHEN cs.sale_status = 'ativa' THEN cs.credit_amount ELSE 0 END) AS total_commission,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pago' THEN cs.credit_amount ELSE 0 END) AS total_paid,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pendente' THEN cs.credit_amount ELSE 0 END) AS total_pending,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pago' THEN 1 ELSE 0 END) AS sold_paid_count,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pendente' THEN 1 ELSE 0 END) AS sold_pending_count,
                  AVG(CASE WHEN cs.sale_status = 'ativa' THEN cs.net_amount ELSE NULL END) AS ticket_avg
                FROM consignment_sales cs
                WHERE cs.supplier_pessoa_id IN ({$inSql})
                {$saleDateFilter}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($saleParams);
        $sales = $stmt->fetch(PDO::FETCH_ASSOC);

        // ── Donation authorization count (from notes or special status) ──
        $sql = "SELECT
                  SUM(CASE WHEN r.notes LIKE '%doação autorizada%' OR r.notes LIKE '%doacao autorizada%' THEN 1 ELSE 0 END) AS donation_authorized_count
                FROM consignment_product_registry r
                WHERE (r.supplier_pessoa_id IN ({$inSql}) OR r.consignment_supplier_original_id IN ({$inSql}))
                  AND r.consignment_status = 'em_estoque'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($inParams);
        $donationRow = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_received'           => (int) ($reg['total_received'] ?? 0),
            'in_stock_count'           => (int) ($reg['in_stock_count'] ?? 0),
            'in_stock_value'           => (float) ($reg['in_stock_value'] ?? 0),
            'sold_count'               => (int) ($reg['sold_count'] ?? 0),
            'returned_count'           => (int) ($reg['returned_count'] ?? 0),
            'donated_count'            => (int) ($reg['donated_count'] ?? 0),
            'discarded_count'          => (int) ($reg['discarded_count'] ?? 0),
            'donation_authorized_count' => (int) ($donationRow['donation_authorized_count'] ?? 0),
            'gross_sold_total'         => (float) ($sales['gross_sold_total'] ?? 0),
            'total_commission'         => (float) ($sales['total_commission'] ?? 0),
            'total_paid'               => (float) ($sales['total_paid'] ?? 0),
            'total_pending'            => (float) ($sales['total_pending'] ?? 0),
            'sold_paid_count'          => (int) ($sales['sold_paid_count'] ?? 0),
            'sold_pending_count'       => (int) ($sales['sold_pending_count'] ?? 0),
            'ticket_avg'               => round((float) ($sales['ticket_avg'] ?? 0), 2),
            'avg_aging_days'           => round((float) ($reg['avg_aging_days'] ?? 0), 1),
            'avg_days_to_sell'         => round((float) ($reg['avg_days_to_sell'] ?? 0), 1),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_received' => 0, 'in_stock_count' => 0, 'in_stock_value' => 0,
            'sold_count' => 0, 'returned_count' => 0, 'donated_count' => 0,
            'discarded_count' => 0, 'donation_authorized_count' => 0,
            'gross_sold_total' => 0, 'total_commission' => 0,
            'total_paid' => 0, 'total_pending' => 0,
            'sold_paid_count' => 0, 'sold_pending_count' => 0,
            'ticket_avg' => 0, 'avg_aging_days' => 0, 'avg_days_to_sell' => 0,
        ];
    }

    // ─── DETAIL ITEMS ───────────────────────────────────────────

    /**
     * Gera a listagem detalhada por peça com todos os campos possíveis.
     *
     * @param int[] $supplierIds
     * @param string[] $visibleFields
     * @param array $filters
     * @return array{items: array, total: int}
     */
    public function generateDetailItems(
        array $supplierIds,
        array $visibleFields = [],
        array $filters = [],
        string $sortField = 'received_at',
        string $sortDir = 'DESC'
    ): array {
        if (empty($supplierIds)) {
            return ['items' => [], 'total' => 0];
        }

        $pdo = $this->pdo;

        $effectiveConsignmentStatusSql = "COALESCE(
            r.consignment_status,
            CASE
                WHEN ls.id IS NULL THEN ''
                WHEN ls.payout_status = 'pago' THEN 'vendido_pago'
                ELSE 'vendido_pendente'
            END
        )";
        $effectiveSoldAtSql = "COALESCE(
            ls.sold_at,
            CASE
                WHEN r.consignment_status IN ('vendido_pendente','vendido_pago','proprio_pos_pgto')
                    THEN r.status_changed_at
                ELSE NULL
            END
        )";
        $effectivePayoutStatusSql = "COALESCE(
            ls.payout_status,
            CASE
                WHEN r.consignment_status = 'vendido_pago' THEN 'pago'
                WHEN r.consignment_status = 'vendido_pendente' THEN 'pendente'
                ELSE ''
            END
        )";
        $effectivePaidAtSql = "COALESCE(
            ls.paid_at,
            CASE
                WHEN r.consignment_status = 'vendido_pago' THEN r.status_changed_at
                ELSE NULL
            END
        )";

        // Build supplier IN clause
        $inPlaceholders = [];
        $inParams = [];
        foreach ($supplierIds as $i => $sid) {
            $key = ':sid_' . $i;
            $inPlaceholders[] = $key;
            $inParams[$key] = (int) $sid;
        }
        $inSql = implode(',', $inPlaceholders);

        // Date filters
        $dateWhere = '';
        $dateParams = [];
        $dateField = $filters['date_field'] ?? 'sold_at';
        if (!in_array($dateField, ['sold_at', 'received_at', 'paid_at'], true)) {
            $dateField = 'sold_at';
        }
        if (!empty($filters['date_from'])) {
            if ($dateField === 'received_at') {
                $dateWhere .= " AND r.received_at >= :df";
            } elseif ($dateField === 'paid_at') {
                $dateWhere .= " AND {$effectivePaidAtSql} >= :df";
            } else {
                $dateWhere .= " AND {$effectiveSoldAtSql} >= :df";
            }
            $dateParams[':df'] = $filters['date_from'] . ($dateField !== 'received_at' ? ' 00:00:00' : '');
        }
        if (!empty($filters['date_to'])) {
            if ($dateField === 'received_at') {
                $dateWhere .= " AND r.received_at <= :dt";
            } elseif ($dateField === 'paid_at') {
                $dateWhere .= " AND {$effectivePaidAtSql} <= :dt";
            } else {
                $dateWhere .= " AND {$effectiveSoldAtSql} <= :dt";
            }
            $dateParams[':dt'] = $filters['date_to'] . ($dateField !== 'received_at' ? ' 23:59:59' : '');
        }

        // Status filters
        $statusWhere = '';
        $statusParams = [];
        if (!empty($filters['consignment_status'])) {
            $statusWhere .= " AND {$effectiveConsignmentStatusSql} = :fcs";
            $statusParams[':fcs'] = $filters['consignment_status'];
        }
        if (!empty($filters['payout_status'])) {
            $statusWhere .= " AND {$effectivePayoutStatusSql} = :fps";
            $statusParams[':fps'] = $filters['payout_status'];
        }
        if (!empty($filters['only_pending_payment'])) {
            $statusWhere .= " AND (
                (ls.payout_status = 'pendente' AND ls.sale_status = 'ativa')
                OR {$effectiveConsignmentStatusSql} = 'vendido_pendente'
            )";
        }
        if (!empty($filters['only_sold'])) {
            $statusWhere .= " AND (
                (ls.id IS NOT NULL AND ls.sale_status = 'ativa')
                OR {$effectiveConsignmentStatusSql} IN ('vendido_pendente','vendido_pago','proprio_pos_pgto')
            )";
        }
        if (!empty($filters['aging_min_days']) && (int) $filters['aging_min_days'] > 0) {
            $statusWhere .= " AND r.consignment_status = 'em_estoque' AND DATEDIFF(NOW(), r.received_at) >= :aging_min";
            $statusParams[':aging_min'] = (int) $filters['aging_min_days'];
        }
        if (!empty($filters['only_donation_authorized'])) {
            $statusWhere .= " AND (r.notes LIKE '%doação autorizada%' OR r.notes LIKE '%doacao autorizada%')";
        }

        // Search
        $searchWhere = '';
        $searchParams = [];
        if (!empty($filters['search'])) {
            $searchWhere = " AND (
                CAST(pool.product_id AS CHAR) LIKE :fsearch
                OR COALESCE(p.name, p2.name, oi.product_name, '') LIKE :fsearch
                OR CAST(COALESCE(p.sku, p2.sku, oi.product_sku, pool.product_id) AS CHAR) LIKE :fsearch
                OR CAST(COALESCE(ls.order_id, '') AS CHAR) LIKE :fsearch
            )";
            $searchParams[':fsearch'] = '%' . $filters['search'] . '%';
        }

        $allParams = $inParams + $dateParams + $statusParams + $searchParams;
        $salesChannelSql = $this->resolveSalesChannelSql();
        $categorySql = $this->resolveCategorySql();
        $brandSql = $this->resolveBrandSql();
        $photoSelect = $this->resolvePhotoSelectSql();
        $customerSelect = $this->tableHasColumn('orders', 'customer_name')
            ? "COALESCE(o.customer_name, '')"
            : ($this->tableHasColumn('orders', 'billing_name')
                ? "COALESCE(o.billing_name, '')"
                : "''");

        $sql = "SELECT
                    pool.product_id,
                    COALESCE(
                        NULLIF(TRIM(CAST(p.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(p2.sku AS CHAR)), ''),
                        NULLIF(TRIM(CAST(oi.product_sku AS CHAR)), ''),
                        TRIM(CAST(pool.product_id AS CHAR))
                    ) AS sku,
                    COALESCE(p.name, p2.name, oi.product_name, CONCAT('Produto #', pool.product_id)) AS product_name,
                    COALESCE(ls.net_amount, p.price, p2.price, 0) AS price,
                    COALESCE(p.status, p2.status) AS product_status,
                    {$categorySql['select']} AS category_name,
                    {$brandSql['select']} AS brand_name,
                    {$photoSelect} AS photo,
                    CASE WHEN r.product_id IS NULL THEN 0 ELSE 1 END AS has_registry_entry,
                    r.received_at,
                    CASE WHEN r.received_at IS NOT NULL AND r.consignment_status = 'em_estoque'
                         THEN DATEDIFF(NOW(), r.received_at) ELSE NULL END AS days_in_stock,
                    CASE WHEN r.consignment_status IN ('vendido_pendente','vendido_pago','proprio_pos_pgto')
                              AND r.received_at IS NOT NULL
                              AND r.status_changed_at IS NOT NULL
                         THEN DATEDIFF(r.status_changed_at, r.received_at) ELSE NULL END AS days_to_sell,
                    {$effectiveConsignmentStatusSql} AS consignment_status,
                    {$effectiveSoldAtSql} AS sold_at,
                    ls.order_id,
                    ls.net_amount,
                    ls.gross_amount,
                    ls.discount_amount,
                    ls.percent_applied,
                    ls.credit_amount,
                    {$effectivePayoutStatusSql} AS payout_status,
                    {$effectivePaidAtSql} AS paid_at,
                    ls.payout_id,
                    ls.sale_status,
                    {$customerSelect} AS customer_name,
                    {$salesChannelSql['select']} AS sales_channel,
                    r.notes,
                    CASE WHEN r.notes LIKE '%doação autorizada%' OR r.notes LIKE '%doacao autorizada%' THEN 1 ELSE 0 END AS donation_authorized,
                    CASE WHEN r.consignment_status = 'devolvido' THEN r.status_changed_at ELSE NULL END AS returned_at,
                    CASE WHEN r.consignment_status = 'devolvido' AND r.notes IS NOT NULL THEN r.notes ELSE NULL END AS return_reason
                FROM (
                    SELECT DISTINCT r.product_id
                    FROM consignment_product_registry r
                    WHERE (r.supplier_pessoa_id IN ({$inSql}) OR r.consignment_supplier_original_id IN ({$inSql}))
                    UNION
                    SELECT DISTINCT cs.product_id
                    FROM consignment_sales cs
                    WHERE cs.supplier_pessoa_id IN ({$inSql})
                ) pool
                LEFT JOIN (
                    SELECT r_inner.*
                    FROM consignment_product_registry r_inner
                    INNER JOIN (
                        SELECT product_id, MAX(id) AS latest_id
                        FROM consignment_product_registry
                        WHERE supplier_pessoa_id IN ({$inSql})
                           OR consignment_supplier_original_id IN ({$inSql})
                        GROUP BY product_id
                    ) latest_registry ON latest_registry.latest_id = r_inner.id
                ) r ON r.product_id = pool.product_id
                LEFT JOIN products p ON p.sku = pool.product_id
                LEFT JOIN (
                    SELECT cs_inner.*
                    FROM consignment_sales cs_inner
                    INNER JOIN (
                        SELECT product_id, MAX(id) AS latest_id
                        FROM consignment_sales
                        WHERE sale_status = 'ativa'
                          AND supplier_pessoa_id IN ({$inSql})
                        GROUP BY product_id
                    ) latest ON latest.latest_id = cs_inner.id
                ) ls ON ls.product_id = pool.product_id
                LEFT JOIN order_items oi ON oi.id = ls.order_item_id
                LEFT JOIN products p2 ON p2.sku = oi.product_sku
                LEFT JOIN orders o ON o.id = ls.order_id
                {$salesChannelSql['join']}
                {$categorySql['join']}
                {$brandSql['join']}
                WHERE 1=1
                {$dateWhere}
                {$statusWhere}
                {$searchWhere}";

        // Sorting
        $validSortFields = [
            'sku' => 'sku',
            'product_name' => 'product_name',
            'received_at' => 'r.received_at',
            'days_in_stock' => 'days_in_stock',
            'consignment_status' => 'consignment_status',
            'price' => 'COALESCE(ls.net_amount, p.price, p2.price, 0)',
            'sold_at' => $effectiveSoldAtSql,
            'credit_amount' => 'ls.credit_amount',
            'paid_at' => $effectivePaidAtSql,
            'payout_status' => $effectivePayoutStatusSql,
            'order_id' => 'ls.order_id',
            'category_name' => 'category_name',
            'brand_name' => 'brand_name',
        ];
        $sortColumn = $validSortFields[$sortField] ?? 'COALESCE(r.received_at, DATE(ls.sold_at), DATE(ls.paid_at))';
        $sortDirection = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$sortColumn} {$sortDirection}, pool.product_id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($allParams);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['items' => $items, 'total' => count($items)];
    }

    // ─── FULL REPORT GENERATION ─────────────────────────────────

    /**
     * Gera relatório completo (summary + items) baseado em um view config.
     *
     * @param int $supplierPessoaId
     * @param array $viewConfig  - fields_config, detail_level, sort_config, group_by, filters_config
     * @param array $runtimeFilters  - filtros adicionais passados na URL
     * @return array{supplier_pessoa_id: int, summary: array, summary_filtered: array, items: array, total: int, view_config: array}
     */
    public function generateReport(
        int $supplierPessoaId,
        array $viewConfig,
        array $runtimeFilters = []
    ): array {
        $supplierIds = $this->resolveSupplierIds($supplierPessoaId);
        $detailLevel = $viewConfig['detail_level'] ?? 'both';
        $visibleFields = $viewConfig['fields_config'] ?? self::defaultFieldKeys();

        // Merge saved filters with runtime filters (runtime takes precedence)
        $savedFilters = $viewConfig['filters_config'] ?? [];
        $filters = array_merge($savedFilters, $runtimeFilters);

        // Sort config
        $sortConfig = $viewConfig['sort_config'] ?? [];
        $sortField = $sortConfig['field'] ?? ($runtimeFilters['sort_field'] ?? 'received_at');
        $sortDir = $sortConfig['direction'] ?? ($runtimeFilters['sort_dir'] ?? 'DESC');

        $report = [
            'supplier_pessoa_id' => $supplierPessoaId,
            'summary' => [],
            'summary_filtered' => [],
            'items' => [],
            'total' => 0,
            'view_config' => $viewConfig,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $detailResult = null;

        // Generate summary
        if (in_array($detailLevel, ['summary', 'both'], true)) {
            // Historical full supplier summary (ignores runtime filters).
            $report['summary'] = $this->generateSummary($supplierIds);
            if ($detailResult === null) {
                $detailResult = $this->generateDetailItems(
                    $supplierIds,
                    $visibleFields,
                    $filters,
                    $sortField,
                    $sortDir
                );
            }
            $report['summary_filtered'] = $this->buildSummaryFromItems($detailResult['items'], $supplierIds);
        }

        // Generate detail items
        if (in_array($detailLevel, ['items', 'both'], true)) {
            if ($detailResult === null) {
                $detailResult = $this->generateDetailItems(
                    $supplierIds,
                    $visibleFields,
                    $filters,
                    $sortField,
                    $sortDir
                );
            }
            $report['items'] = $detailResult['items'];
            $report['total'] = $detailResult['total'];
        }

        // Apply grouping if configured
        if (!empty($viewConfig['group_by']) && !empty($report['items'])) {
            $report['grouped_items'] = $this->groupItems($report['items'], $viewConfig['group_by']);
        }

        return $report;
    }

    /**
     * Build summary KPIs from an already filtered item set.
     *
     * @param array<int, array<string, mixed>> $items
     * @param int[] $supplierIds
     */
    private function buildSummaryFromItems(array $items, array $supplierIds): array
    {
        if (empty($items)) {
            return $this->emptySummary();
        }

        $summary = $this->emptySummary();
        $agingSum = 0.0;
        $agingCount = 0;
        $daysToSellSum = 0.0;
        $daysToSellCount = 0;
        $productIds = [];
        $missingRegistryItems = 0;
        $negativeDaysIgnored = 0;
        $fallbackGrossSoldTotal = 0.0;
        $fallbackTotalCommission = 0.0;
        $fallbackTotalPaid = 0.0;
        $fallbackTotalPending = 0.0;
        $statusSoldPaidCount = 0;
        $statusSoldPendingCount = 0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = true;
            }

            $hasRegistryEntry = (int) ($item['has_registry_entry'] ?? 0) === 1;
            if (!$hasRegistryEntry) {
                $missingRegistryItems++;
                continue;
            }

            $summary['total_received']++;
            $status = (string) ($item['consignment_status'] ?? '');
            $price = (float) ($item['price'] ?? 0);
            $daysInStock = $item['days_in_stock'] ?? null;
            $daysToSell = $item['days_to_sell'] ?? null;

            if ($status === 'em_estoque') {
                $summary['in_stock_count']++;
                $summary['in_stock_value'] += $price;
                if ($daysInStock !== null && $daysInStock !== '') {
                    $agingSum += (float) $daysInStock;
                    $agingCount++;
                }
                if (!empty($item['donation_authorized'])) {
                    $summary['donation_authorized_count']++;
                }
            } elseif ($status === 'devolvido') {
                $summary['returned_count']++;
            } elseif ($status === 'doado') {
                $summary['donated_count']++;
            } elseif ($status === 'descartado') {
                $summary['discarded_count']++;
            }

            if (in_array($status, ['vendido_pendente', 'vendido_pago', 'proprio_pos_pgto'], true)) {
                $summary['sold_count']++;
                $fallbackGrossSoldTotal += $price;
                $creditAmount = (float) ($item['credit_amount'] ?? 0);
                $fallbackTotalCommission += $creditAmount;
                if ($status === 'vendido_pago') {
                    $statusSoldPaidCount++;
                    $fallbackTotalPaid += $creditAmount;
                } elseif ($status === 'vendido_pendente') {
                    $statusSoldPendingCount++;
                    $fallbackTotalPending += $creditAmount;
                }
                if ($daysToSell !== null && $daysToSell !== '') {
                    $daysToSellFloat = (float) $daysToSell;
                    if ($daysToSellFloat >= 0) {
                        $daysToSellSum += $daysToSellFloat;
                        $daysToSellCount++;
                    } else {
                        $negativeDaysIgnored++;
                    }
                }
            }
        }

        $salesSummary = $this->loadSalesSummaryForProducts($supplierIds, array_map('intval', array_keys($productIds)));
        $salesGrossSoldTotal = (float) ($salesSummary['gross_sold_total'] ?? 0);
        $salesTotalCommission = (float) ($salesSummary['total_commission'] ?? 0);
        $salesTotalPaid = (float) ($salesSummary['total_paid'] ?? 0);
        $salesTotalPending = (float) ($salesSummary['total_pending'] ?? 0);
        $salesSoldPaidCount = (int) ($salesSummary['sold_paid_count'] ?? 0);
        $salesSoldPendingCount = (int) ($salesSummary['sold_pending_count'] ?? 0);
        $salesTicketAvg = round((float) ($salesSummary['ticket_avg'] ?? 0), 2);
        $hasSalesFinancialData = ($salesGrossSoldTotal > 0.0)
            || ($salesTotalCommission > 0.0)
            || ($salesTotalPaid > 0.0)
            || ($salesTotalPending > 0.0)
            || ($salesSoldPaidCount > 0)
            || ($salesSoldPendingCount > 0);

        $summary['gross_sold_total'] = $hasSalesFinancialData ? $salesGrossSoldTotal : $fallbackGrossSoldTotal;
        $summary['total_commission'] = $hasSalesFinancialData ? $salesTotalCommission : $fallbackTotalCommission;
        $summary['total_paid'] = $hasSalesFinancialData ? $salesTotalPaid : $fallbackTotalPaid;
        $summary['total_pending'] = $hasSalesFinancialData ? $salesTotalPending : $fallbackTotalPending;
        $summary['sold_paid_count'] = max($statusSoldPaidCount, $salesSoldPaidCount);
        $summary['sold_pending_count'] = max($statusSoldPendingCount, $salesSoldPendingCount);
        $summary['ticket_avg'] = $salesTicketAvg > 0
            ? $salesTicketAvg
            : ($summary['sold_count'] > 0
                ? round($summary['gross_sold_total'] / $summary['sold_count'], 2)
                : 0.0);
        $summary['avg_aging_days'] = $agingCount > 0 ? round($agingSum / $agingCount, 1) : 0.0;
        $summary['avg_days_to_sell'] = $daysToSellCount > 0 ? round($daysToSellSum / $daysToSellCount, 1) : 0.0;
        $summary['audit_filtered_total_items'] = count($items);
        $summary['audit_registry_items'] = $summary['total_received'];
        $summary['audit_missing_registry_items'] = $missingRegistryItems;
        $summary['audit_negative_days_to_sell_ignored'] = $negativeDaysIgnored;

        return $summary;
    }

    /**
     * Sales metrics scoped by supplier and product set.
     *
     * @param int[] $supplierIds
     * @param int[] $productIds
     * @return array<string, mixed>
     */
    private function loadSalesSummaryForProducts(array $supplierIds, array $productIds): array
    {
        if (empty($supplierIds) || empty($productIds)) {
            return [
                'gross_sold_total' => 0,
                'total_commission' => 0,
                'total_paid' => 0,
                'total_pending' => 0,
                'sold_paid_count' => 0,
                'sold_pending_count' => 0,
                'ticket_avg' => 0,
            ];
        }

        $supplierIds = array_values(array_unique(array_filter(array_map('intval', $supplierIds))));
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (empty($supplierIds) || empty($productIds)) {
            return [
                'gross_sold_total' => 0,
                'total_commission' => 0,
                'total_paid' => 0,
                'total_pending' => 0,
                'sold_paid_count' => 0,
                'sold_pending_count' => 0,
                'ticket_avg' => 0,
            ];
        }

        $supplierPlaceholders = [];
        $productPlaceholders = [];
        $params = [];

        foreach ($supplierIds as $i => $sid) {
            $key = ':sidf_' . $i;
            $supplierPlaceholders[] = $key;
            $params[$key] = $sid;
        }
        foreach ($productIds as $i => $pid) {
            $key = ':pidf_' . $i;
            $productPlaceholders[] = $key;
            $params[$key] = $pid;
        }

        $supplierSql = implode(',', $supplierPlaceholders);
        $productSql = implode(',', $productPlaceholders);

        $sql = "SELECT
                  SUM(CASE WHEN cs.sale_status = 'ativa' THEN cs.net_amount ELSE 0 END) AS gross_sold_total,
                  SUM(CASE WHEN cs.sale_status = 'ativa' THEN cs.credit_amount ELSE 0 END) AS total_commission,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pago' THEN cs.credit_amount ELSE 0 END) AS total_paid,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pendente' THEN cs.credit_amount ELSE 0 END) AS total_pending,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pago' THEN 1 ELSE 0 END) AS sold_paid_count,
                  SUM(CASE WHEN cs.sale_status = 'ativa' AND cs.payout_status = 'pendente' THEN 1 ELSE 0 END) AS sold_pending_count,
                  AVG(CASE WHEN cs.sale_status = 'ativa' THEN cs.net_amount ELSE NULL END) AS ticket_avg
                FROM consignment_sales cs
                WHERE cs.supplier_pessoa_id IN ({$supplierSql})
                  AND cs.product_id IN ({$productSql})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * Group items by a specific field.
     */
    private function groupItems(array $items, string $groupBy): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = (string) ($item[$groupBy] ?? '(sem valor)');
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'label' => $key,
                    'items' => [],
                    'count' => 0,
                    'subtotal_price' => 0,
                    'subtotal_commission' => 0,
                ];
            }
            $groups[$key]['items'][] = $item;
            $groups[$key]['count']++;
            $groups[$key]['subtotal_price'] += (float) ($item['price'] ?? 0);
            $groups[$key]['subtotal_commission'] += (float) ($item['credit_amount'] ?? 0);
        }
        return array_values($groups);
    }

    // ─── EXPORT ─────────────────────────────────────────────────

    /**
     * Stream CSV export.
     */
    public function exportCsv(
        array $report,
        array $visibleFields,
        ?string $supplierName = null,
        string $dateFrom = '',
        string $dateTo = ''
    ): void {
        $safeName = preg_replace('/[^a-z0-9]+/i', '-', strtolower($supplierName ?? 'fornecedora'));
        $safeName = trim((string) $safeName, '-') ?: 'fornecedora';
        $filename = 'consignacao-relatorio-' . $safeName . '-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        $metadata = self::fieldMetadata();

        // Header rows
        fputcsv($out, ['Relatório dinâmico de consignação']);
        fputcsv($out, ['Fornecedora', $supplierName ?: '']);
        fputcsv($out, ['Período', ($dateFrom ?: 'início') . ' — ' . ($dateTo ?: 'atual')]);
        fputcsv($out, ['Gerado em', date('d/m/Y H:i:s')]);
        fputcsv($out, []);

        // Summary section
        if (!empty($report['summary'])) {
            $s = $report['summary'];
            fputcsv($out, ['RESUMO EXECUTIVO']);
            fputcsv($out, ['Métrica', 'Valor']);
            fputcsv($out, ['Total peças recebidas', $s['total_received'] ?? 0]);
            fputcsv($out, ['Em estoque', $s['in_stock_count'] ?? 0]);
            fputcsv($out, ['Valor em estoque', 'R$ ' . number_format((float) ($s['in_stock_value'] ?? 0), 2, ',', '.')]);
            fputcsv($out, ['Peças vendidas', $s['sold_count'] ?? 0]);
            fputcsv($out, ['Pendentes pgto', $s['sold_pending_count'] ?? 0]);
            fputcsv($out, ['Valor pendente', 'R$ ' . number_format((float) ($s['total_pending'] ?? 0), 2, ',', '.')]);
            fputcsv($out, ['Já pagas', $s['sold_paid_count'] ?? 0]);
            fputcsv($out, ['Valor pago', 'R$ ' . number_format((float) ($s['total_paid'] ?? 0), 2, ',', '.')]);
            fputcsv($out, ['Devolvidas', $s['returned_count'] ?? 0]);
            fputcsv($out, ['Doadas', $s['donated_count'] ?? 0]);
            fputcsv($out, ['Ticket médio', 'R$ ' . number_format((float) ($s['ticket_avg'] ?? 0), 2, ',', '.')]);
            fputcsv($out, ['Aging médio (dias)', $s['avg_aging_days'] ?? 0]);
            fputcsv($out, ['Tempo médio até venda (dias)', $s['avg_days_to_sell'] ?? 0]);
            fputcsv($out, []);
        }

        // Detail section
        if (!empty($report['items'])) {
            fputcsv($out, ['DETALHAMENTO POR PEÇA']);

            // Header
            $headerRow = [];
            foreach ($visibleFields as $fk) {
                $headerRow[] = $metadata[$fk]['label'] ?? $fk;
            }
            fputcsv($out, $headerRow);

            // Rows
            $totalCommission = 0;
            $totalPrice = 0;
            foreach ($report['items'] as $item) {
                $row = [];
                foreach ($visibleFields as $fk) {
                    $row[] = $this->formatCsvCell($fk, $item[$fk] ?? '', $metadata[$fk]['type'] ?? 'text');
                }
                $totalCommission += (float) ($item['credit_amount'] ?? 0);
                $totalPrice += (float) ($item['price'] ?? 0);
                fputcsv($out, $row);
            }

            // Totals
            fputcsv($out, []);
            fputcsv($out, ['TOTAIS']);
            fputcsv($out, ['Total itens', count($report['items'])]);
            fputcsv($out, ['Total valor venda', 'R$ ' . number_format($totalPrice, 2, ',', '.')]);
            fputcsv($out, ['Total comissão', 'R$ ' . number_format($totalCommission, 2, ',', '.')]);
        }

        fclose($out);
    }

    /**
     * Export Excel-compatible HTML.
     */
    public function exportExcel(
        array $report,
        array $visibleFields,
        ?string $supplierName = null,
        string $dateFrom = '',
        string $dateTo = ''
    ): void {
        $safeName = preg_replace('/[^a-z0-9]+/i', '-', strtolower($supplierName ?? 'fornecedora'));
        $safeName = trim((string) $safeName, '-') ?: 'fornecedora';
        $filename = 'consignacao-relatorio-' . $safeName . '-' . date('Ymd-His') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $metadata = self::fieldMetadata();

        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h2>Relatório de Consignação — ' . htmlspecialchars($supplierName ?? '') . '</h2>';
        echo '<p>Período: ' . ($dateFrom ?: 'início') . ' — ' . ($dateTo ?: 'atual') . '</p>';
        echo '<p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>';

        // Summary
        if (!empty($report['summary'])) {
            $s = $report['summary'];
            echo '<h3>Resumo Executivo</h3>';
            echo '<table border="1"><tr><th>Métrica</th><th>Valor</th></tr>';
            echo '<tr><td>Total peças recebidas</td><td>' . ($s['total_received'] ?? 0) . '</td></tr>';
            echo '<tr><td>Em estoque</td><td>' . ($s['in_stock_count'] ?? 0) . '</td></tr>';
            echo '<tr><td>Valor em estoque</td><td>R$ ' . number_format((float) ($s['in_stock_value'] ?? 0), 2, ',', '.') . '</td></tr>';
            echo '<tr><td>Vendidas</td><td>' . ($s['sold_count'] ?? 0) . '</td></tr>';
            echo '<tr><td>Pendentes pagamento</td><td>' . ($s['sold_pending_count'] ?? 0) . '</td></tr>';
            echo '<tr><td>Valor pendente</td><td>R$ ' . number_format((float) ($s['total_pending'] ?? 0), 2, ',', '.') . '</td></tr>';
            echo '<tr><td>Pagas</td><td>' . ($s['sold_paid_count'] ?? 0) . '</td></tr>';
            echo '<tr><td>Valor pago</td><td>R$ ' . number_format((float) ($s['total_paid'] ?? 0), 2, ',', '.') . '</td></tr>';
            echo '<tr><td>Devolvidas</td><td>' . ($s['returned_count'] ?? 0) . '</td></tr>';
            echo '<tr><td>Doadas</td><td>' . ($s['donated_count'] ?? 0) . '</td></tr>';
            echo '<tr><td>Ticket médio</td><td>R$ ' . number_format((float) ($s['ticket_avg'] ?? 0), 2, ',', '.') . '</td></tr>';
            echo '<tr><td>Aging médio (dias)</td><td>' . ($s['avg_aging_days'] ?? 0) . '</td></tr>';
            echo '</table>';
        }

        // Detail
        if (!empty($report['items'])) {
            echo '<h3>Detalhe por Peça</h3><table border="1"><thead><tr>';
            foreach ($visibleFields as $fk) {
                echo '<th>' . htmlspecialchars($metadata[$fk]['label'] ?? $fk) . '</th>';
            }
            echo '</tr></thead><tbody>';

            $totalCommission = 0;
            foreach ($report['items'] as $item) {
                echo '<tr>';
                foreach ($visibleFields as $fk) {
                    $val = $this->formatCsvCell($fk, $item[$fk] ?? '', $metadata[$fk]['type'] ?? 'text');
                    echo '<td>' . htmlspecialchars((string) $val) . '</td>';
                }
                echo '</tr>';
                $totalCommission += (float) ($item['credit_amount'] ?? 0);
            }

            echo '</tbody><tfoot><tr>';
            echo '<td colspan="' . count($visibleFields) . '" style="text-align:right;font-weight:bold;">';
            echo 'Total comissão: R$ ' . number_format($totalCommission, 2, ',', '.');
            echo '</td></tr></tfoot></table>';
        }

        echo '</body></html>';
    }

    private function formatCsvCell(string $fieldKey, $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        switch ($type) {
            case 'currency':
                return 'R$ ' . number_format((float) $value, 2, ',', '.');
            case 'percent':
                return number_format((float) $value, 0) . '%';
            case 'date':
                return $value !== '' ? date('d/m/Y', strtotime((string) $value)) : '';
            case 'boolean':
                return ((int) $value) ? 'Sim' : 'Não';
            case 'image':
                return (string) $value; // URL as text
            default:
                return (string) $value;
        }
    }

    // ─── VIEW MANAGEMENT ────────────────────────────────────────

    public function getViewRepository(): ConsignmentReportViewRepository
    {
        return $this->viewRepo;
    }

    /**
     * Ensure system presets exist in DB.
     */
    public function ensureSystemPresets(): void
    {
        $existing = $this->viewRepo->listAll(null, true);
        $existingNames = array_column($existing, 'name');

        foreach (self::systemPresets() as $key => $preset) {
            if (!in_array($preset['name'], $existingNames, true)) {
                $this->viewRepo->create([
                    'name'          => $preset['name'],
                    'description'   => $preset['description'],
                    'fields_config' => $preset['fields'],
                    'detail_level'  => $preset['detail_level'],
                    'is_system'     => 1,
                    'is_default'    => $key === 'completo' ? 1 : 0,
                ]);
            }
        }
    }

    // ─── HELPERS ────────────────────────────────────────────────

    /**
     * Resolve all supplier IDs including original supplier mappings.
     *
     * @return int[]
     */
    private function resolveSupplierIds(int $supplierPessoaId): array
    {
        $ids = [$supplierPessoaId];

        // Find all IDs mapped to this supplier (originals that were later transferred)
        $sql = "SELECT DISTINCT supplier_pessoa_id FROM consignment_product_registry
                WHERE consignment_supplier_original_id = :sid AND supplier_pessoa_id != :sid2
                UNION
                SELECT DISTINCT consignment_supplier_original_id FROM consignment_product_registry
                WHERE supplier_pessoa_id = :sid3 AND consignment_supplier_original_id IS NOT NULL AND consignment_supplier_original_id != :sid4";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sid' => $supplierPessoaId, ':sid2' => $supplierPessoaId, ':sid3' => $supplierPessoaId, ':sid4' => $supplierPessoaId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $val = (int) ($row['supplier_pessoa_id'] ?? $row['consignment_supplier_original_id'] ?? 0);
            if ($val > 0 && !in_array($val, $ids, true)) {
                $ids[] = $val;
            }
        }

        return $ids;
    }

    /**
     * Resolve qual tabela de canais de venda está disponível no schema atual.
     * Preferimos a tabela canônica `canais_venda`, com fallback para legado.
     */
    private function resolveSalesChannelTable(): string
    {
        if ($this->salesChannelTable !== null) {
            return $this->salesChannelTable;
        }

        foreach (['canais_venda', 'sales_channels'] as $candidate) {
            if ($this->tableExists($candidate)) {
                $this->salesChannelTable = $candidate;
                return $this->salesChannelTable;
            }
        }

        $this->salesChannelTable = '';
        return $this->salesChannelTable;
    }

    private function tableExists(string $tableName): bool
    {
        if ($tableName === '') {
            return false;
        }
        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
             LIMIT 1"
        );
        $stmt->execute([':table' => $tableName]);
        $exists = (bool) $stmt->fetchColumn();
        $this->tableExistsCache[$tableName] = $exists;
        return $exists;
    }

    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        if ($tableName === '' || $columnName === '') {
            return false;
        }
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1"
        );
        $stmt->execute([':table' => $tableName, ':column' => $columnName]);
        $exists = (bool) $stmt->fetchColumn();
        $this->columnExistsCache[$cacheKey] = $exists;
        return $exists;
    }

    /**
     * @return array{select: string, join: string}
     */
    private function resolveSalesChannelSql(): array
    {
        $table = $this->resolveSalesChannelTable();
        $hasOrderSalesChannelId = $this->tableHasColumn('orders', 'sales_channel_id');
        if ($table !== '' && $hasOrderSalesChannelId) {
            return [
                'select' => "COALESCE(sc.name, '')",
                'join' => "LEFT JOIN `{$table}` sc ON sc.id = o.sales_channel_id",
            ];
        }
        if ($this->tableHasColumn('orders', 'sales_channel')) {
            return [
                'select' => "COALESCE(o.sales_channel, '')",
                'join' => '',
            ];
        }
        return [
            'select' => "''",
            'join' => '',
        ];
    }

    /**
     * @return array{select: string, join: string}
     */
    private function resolveCategorySql(): array
    {
        if ($this->tableHasColumn('products', 'category_id')) {
            if ($this->tableExists('catalog_categories')) {
                return [
                    'select' => "COALESCE(cat.name, '')",
                    'join' => "LEFT JOIN `catalog_categories` cat ON cat.id = p.category_id",
                ];
            }
            if ($this->tableExists('product_categories')) {
                return [
                    'select' => "COALESCE(cat.name, '')",
                    'join' => "LEFT JOIN `product_categories` cat ON cat.id = p.category_id",
                ];
            }
        }
        if ($this->tableHasColumn('products', 'colecao_id')) {
            if ($this->tableExists('collections')) {
                return [
                    'select' => "COALESCE(cat.name, '')",
                    'join' => "LEFT JOIN `collections` cat ON cat.id = p.colecao_id",
                ];
            }
            if ($this->tableExists('colecoes')) {
                return [
                    'select' => "COALESCE(cat.name, '')",
                    'join' => "LEFT JOIN `colecoes` cat ON cat.id = p.colecao_id",
                ];
            }
        }
        return [
            'select' => "''",
            'join' => '',
        ];
    }

    /**
     * @return array{select: string, join: string}
     */
    private function resolveBrandSql(): array
    {
        if ($this->tableHasColumn('products', 'brand_id')) {
            if ($this->tableExists('brands')) {
                return [
                    'select' => "COALESCE(br.name, '')",
                    'join' => "LEFT JOIN `brands` br ON br.id = p.brand_id",
                ];
            }
            if ($this->tableExists('catalog_brands')) {
                return [
                    'select' => "COALESCE(br.name, '')",
                    'join' => "LEFT JOIN `catalog_brands` br ON br.id = p.brand_id",
                ];
            }
        }
        if ($this->tableHasColumn('products', 'marca_id')) {
            if ($this->tableExists('brands')) {
                return [
                    'select' => "COALESCE(br.name, '')",
                    'join' => "LEFT JOIN `brands` br ON br.id = p.marca_id",
                ];
            }
            if ($this->tableExists('marcas')) {
                return [
                    'select' => "COALESCE(br.name, '')",
                    'join' => "LEFT JOIN `marcas` br ON br.id = p.marca_id",
                ];
            }
        }
        return [
            'select' => "''",
            'join' => '',
        ];
    }

    private function productMetadataImageExpr(string $alias): string
    {
        return "COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.metadata, '$.image_src')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.metadata, '$.image_url')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$alias}.metadata, '$.thumbnail_url')), '')
        )";
    }

    private function resolvePhotoSelectSql(): string
    {
        $candidates = [];
        if ($this->tableHasColumn('products', 'image_url')) {
            $candidates[] = "NULLIF(TRIM(p.image_url), '')";
        }
        $candidates[] = $this->productMetadataImageExpr('p');

        if ($this->tableHasColumn('products', 'image_url')) {
            $candidates[] = "NULLIF(TRIM(p2.image_url), '')";
        }
        $candidates[] = $this->productMetadataImageExpr('p2');

        return "COALESCE(" . implode(', ', $candidates) . ", '')";
    }
}
