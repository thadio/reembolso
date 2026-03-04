<?php

namespace App\Services;

use PDO;

/**
 * Verificações de consistência e integridade do módulo de consignação.
 */
class ConsignmentIntegrityService
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Run all integrity checks and return their counters.
     *
     * @return array<int, array{type: string, description: string, count: int, details: array}>
     */
    public function runAllChecks(): array
    {
        if (!$this->pdo) {
            return [];
        }

        $checks = [
            'ledger_without_sales' => $this->checkLedgerWithoutSales(),
            'consignacao_without_supplier' => $this->checkConsignacaoWithoutSupplier(),
            'sale_paid_but_product_not_pago' => $this->checkSalePaidProductNotPago(),
            'product_vendido_pendente_without_sale' => $this->checkProductVendidoPendenteWithoutSale(),
            'active_sale_without_product' => $this->checkActiveSaleWithoutProduct(),
            'active_sale_without_registry' => $this->checkActiveSaleWithoutRegistry(),
            'registry_without_product' => $this->checkRegistryWithoutProduct(),
            'legacy_payouts_unlinked' => $this->checkLegacyPayoutsUnlinked(),
            'voucher_balance_divergence' => $this->checkVoucherBalanceDivergence(),
            'source_status_mismatch' => $this->checkSourceStatusMismatch(),
            'quitada_with_supplier' => $this->checkQuitadaWithSupplier(),
            'registry_missing_original_supplier' => $this->checkRegistryMissingOriginalSupplier(),
        ];

        $results = [];
        foreach ($checks as $type => $checkResult) {
            $results[] = array_merge(['type' => $type], $checkResult);
        }

        return $results;
    }

    /**
     * Ledger credit entries without matching consignment_sales.
     */
    private function checkLedgerWithoutSales(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM cupons_creditos_movimentos m
                WHERE m.event_type = 'sale'
                  AND m.type = 'credito'
                  AND m.product_id IS NOT NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM consignment_sales s
                    WHERE s.order_id = m.order_id
                      AND s.order_item_id = m.order_item_id
                      AND s.product_id = m.product_id
                  )";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Movimentos de crédito no ledger sem registro em consignment_sales',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Products with source=consignacao but no supplier.
     */
    private function checkConsignacaoWithoutSupplier(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM products
                WHERE source = 'consignacao'
                  AND (supplier_pessoa_id IS NULL OR supplier_pessoa_id = 0)";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Produto source=consignacao sem fornecedora',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Sales marked as paid but product not in vendido_pago status.
     */
    private function checkSalePaidProductNotPago(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM consignment_sales s
                JOIN products p ON p.sku = s.product_id
                WHERE s.payout_status = 'pago'
                  AND s.sale_status = 'ativa'
                  AND (p.consignment_status IS NULL OR p.consignment_status != 'vendido_pago')";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Venda marcada como paga mas produto sem status vendido_pago',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Products with vendido_pendente but no active sale.
     */
    private function checkProductVendidoPendenteWithoutSale(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM products p
                WHERE p.consignment_status = 'vendido_pendente'
                  AND NOT EXISTS (
                    SELECT 1 FROM consignment_sales s
                    WHERE s.product_id = p.sku
                      AND s.sale_status = 'ativa'
                  )";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Produto vendido_pendente sem venda ativa',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Active sales without matching product row.
     */
    private function checkActiveSaleWithoutProduct(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM consignment_sales s
                WHERE s.sale_status = 'ativa'
                  AND NOT EXISTS (
                    SELECT 1 FROM products p WHERE p.sku = s.product_id
                  )";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Venda ativa sem produto correspondente em products',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Active sales without matching consignment registry row.
     */
    private function checkActiveSaleWithoutRegistry(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM consignment_sales s
                WHERE s.sale_status = 'ativa'
                  AND NOT EXISTS (
                    SELECT 1 FROM consignment_product_registry r WHERE r.product_id = s.product_id
                  )";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Venda ativa sem registro correspondente em consignment_product_registry',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Registry entries pointing to non-existent products.
     */
    private function checkRegistryWithoutProduct(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM consignment_product_registry r
                WHERE NOT EXISTS (
                    SELECT 1 FROM products p WHERE p.sku = r.product_id
                )";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Registry sem produto válido',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Legacy payout movements without payout_id.
     */
    private function checkLegacyPayoutsUnlinked(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM cupons_creditos_movimentos
                WHERE event_type = 'payout'
                  AND (payout_id IS NULL OR payout_id = 0)";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Pagamentos legados sem vínculo (payout_id ausente)',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Voucher balance divergent from calculated (credits - debits).
     */
    private function checkVoucherBalanceDivergence(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM (
                  SELECT v.id,
                         v.balance AS stored_balance,
                         COALESCE(
                           (SELECT SUM(CASE WHEN m.type = 'credito' THEN m.credit_amount ELSE 0 END)
                              - SUM(CASE WHEN m.type = 'debito' THEN m.credit_amount ELSE 0 END)
                            FROM cupons_creditos_movimentos m WHERE m.voucher_account_id = v.id), 0
                         ) AS calculated_balance
                  FROM cupons_creditos v
                  WHERE v.scope = 'consignacao'
                    AND v.deleted_at IS NULL
                  HAVING ABS(stored_balance - calculated_balance) > 0.01
                ) AS divergent";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Saldo do voucher divergente do calculado (créditos - débitos)',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * source='consignacao' with consignment_status='proprio_pos_pgto'.
     */
    private function checkSourceStatusMismatch(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM products
                WHERE source = 'consignacao'
                  AND consignment_status = 'proprio_pos_pgto'";
        $count = $this->queryCount($sql);
        return [
            'description' => "source='consignacao' com status='proprio_pos_pgto' — deveria ser 'consignacao_quitada'",
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * source='consignacao_quitada' but supplier_pessoa_id is still set.
     */
    private function checkQuitadaWithSupplier(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM products
                WHERE source = 'consignacao_quitada'
                  AND supplier_pessoa_id IS NOT NULL
                  AND supplier_pessoa_id > 0";
        $count = $this->queryCount($sql);
        return [
            'description' => "source='consignacao_quitada' com supplier_pessoa_id NOT NULL — reclassificação incompleta",
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Registry missing consignment_supplier_original_id after reclassification.
     */
    private function checkRegistryMissingOriginalSupplier(): array
    {
        $sql = "SELECT COUNT(*) AS cnt FROM consignment_product_registry
                WHERE consignment_status IN ('proprio_pos_pgto', 'devolvido')
                  AND (consignment_supplier_original_id IS NULL OR consignment_supplier_original_id = 0)";
        $count = $this->queryCount($sql);
        return [
            'description' => 'Registry sem consignment_supplier_original_id após reclassificação',
            'count' => $count,
            'details' => [],
        ];
    }

    /**
     * Get detailed results for a specific check type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCheckDetails(string $type, int $limit = 0): array
    {
        if (!$this->pdo) {
            return [];
        }

        // Queries for each integrity check type.
        // When $limit is 0 (default), no LIMIT clause is added – all rows are returned.
        $queries = [
            'ledger_without_sales' => "SELECT m.id, m.order_id, m.order_item_id, m.product_id, m.credit_amount, m.sold_at
                FROM cupons_creditos_movimentos m
                WHERE m.event_type = 'sale' AND m.type = 'credito' AND m.product_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM consignment_sales s WHERE s.order_id = m.order_id AND s.order_item_id = m.order_item_id AND s.product_id = m.product_id)
                ORDER BY m.id DESC",
            'consignacao_without_supplier' => "SELECT sku, name, source, supplier_pessoa_id FROM products WHERE source = 'consignacao' AND (supplier_pessoa_id IS NULL OR supplier_pessoa_id = 0)",
            'sale_paid_but_product_not_pago' => "SELECT s.id AS sale_id, s.product_id, s.order_id, p.consignment_status FROM consignment_sales s JOIN products p ON p.sku = s.product_id WHERE s.payout_status = 'pago' AND s.sale_status = 'ativa' AND (p.consignment_status IS NULL OR p.consignment_status != 'vendido_pago')",
            'product_vendido_pendente_without_sale' => "SELECT p.sku, p.name, p.consignment_status FROM products p WHERE p.consignment_status = 'vendido_pendente' AND NOT EXISTS (SELECT 1 FROM consignment_sales s WHERE s.product_id = p.sku AND s.sale_status = 'ativa')",
            'active_sale_without_product' => "SELECT s.id AS sale_id, s.product_id, s.order_id, s.order_item_id, s.payout_status, s.net_amount, s.credit_amount, s.sold_at
                FROM consignment_sales s
                WHERE s.sale_status = 'ativa'
                  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.sku = s.product_id)
                ORDER BY s.id DESC",
            'active_sale_without_registry' => "SELECT s.id AS sale_id, s.product_id, s.order_id, s.order_item_id, s.payout_status, s.net_amount, s.credit_amount, s.sold_at,
                       p.name AS product_name, p.source, p.consignment_status
                FROM consignment_sales s
                LEFT JOIN products p ON p.sku = s.product_id
                WHERE s.sale_status = 'ativa'
                  AND NOT EXISTS (SELECT 1 FROM consignment_product_registry r WHERE r.product_id = s.product_id)
                ORDER BY s.id DESC",
            'legacy_payouts_unlinked' => "SELECT id, voucher_account_id, credit_amount, event_at, event_notes FROM cupons_creditos_movimentos WHERE event_type = 'payout' AND (payout_id IS NULL OR payout_id = 0) ORDER BY id DESC",
            'source_status_mismatch' => "SELECT sku, name, source, consignment_status FROM products WHERE source = 'consignacao' AND consignment_status = 'proprio_pos_pgto'",
            'quitada_with_supplier' => "SELECT sku, name, source, supplier_pessoa_id FROM products WHERE source = 'consignacao_quitada' AND supplier_pessoa_id IS NOT NULL AND supplier_pessoa_id > 0",
        ];

        $sql = $queries[$type] ?? null;
        if (!$sql) {
            return [];
        }

        if ($limit > 0) {
            $sql .= ' LIMIT :lim';
        }

        $stmt = $this->pdo->prepare($sql);
        if ($limit > 0) {
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get legacy (unlinked) payout movements for reconciliation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLegacyPayouts(int $supplierPessoaId = 0): array
    {
        if (!$this->pdo) {
            return [];
        }

        $sql = "SELECT m.id, m.voucher_account_id, m.vendor_pessoa_id, m.credit_amount,
                       m.event_at, m.event_notes,
                       COALESCE(f.full_name, CONCAT('Fornecedor #', m.vendor_pessoa_id)) AS supplier_name
                FROM cupons_creditos_movimentos m
                LEFT JOIN vw_fornecedores_compat f ON f.id = m.vendor_pessoa_id
                WHERE m.event_type = 'payout'
                  AND (m.payout_id IS NULL OR m.payout_id = 0)";
        $params = [];
        if ($supplierPessoaId > 0) {
            $sql .= " AND m.vendor_pessoa_id = :sid";
            $params[':sid'] = $supplierPessoaId;
        }
        $sql .= " ORDER BY m.event_at DESC, m.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function queryCount(string $sql): int
    {
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt ? (int) $stmt->fetchColumn() : 0;
        } catch (\Throwable $e) {
            error_log('ConsignmentIntegrityService check error: ' . $e->getMessage());
            return 0;
        }
    }
}
