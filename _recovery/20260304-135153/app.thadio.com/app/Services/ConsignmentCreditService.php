<?php

namespace App\Services;

use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\VoucherAccount;
use App\Repositories\ConsignmentCreditRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductSupplyRepository;
use App\Repositories\VendorRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Services\ConsignmentSalesService;
use PDO;

class ConsignmentCreditService
{
    private const EVENT_LABELS = [
        'sale' => 'Venda',
        'return' => 'Devolução',
        'return_cancel' => 'Cancelamento de devolução',
        'order_cancel' => 'Cancelamento do pedido',
        'order_trash' => 'Pedido enviado para lixeira',
        'order_delete' => 'Pedido excluído',
        'payment_refund' => 'Estorno de pagamento',
        'payment_failed' => 'Pagamento falhou',
    ];

    private ?PDO $pdo;
    private VendorRepository $vendors;
    private OrderRepository $orders;
    private ProductSupplyRepository $supplies;
    private VoucherAccountRepository $vouchers;
    private ConsignmentCreditRepository $credits;
    private VoucherCreditEntryRepository $creditEntries;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->vendors = new VendorRepository($pdo);
        $this->orders = new OrderRepository($pdo);
        $this->supplies = new ProductSupplyRepository($pdo);
        $this->vouchers = new VoucherAccountRepository($pdo);
        $this->credits = new ConsignmentCreditRepository($pdo);
        $this->creditEntries = new VoucherCreditEntryRepository($pdo);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public function generateForOrder(int $orderId, ?string $paymentStatus, bool $dryRun = false): array
    {
        // Fluxo local: sem dependências de integração legada.
        $messages = [];
        $errors = [];

        if (!$this->pdo) {
            return [$messages, ['Sem conexão com banco local.']];
        }
        if ($orderId <= 0) {
            return [$messages, ['Pedido inválido para crédito consignado.']];
        }
        if ($paymentStatus !== 'paid') {
            return [$messages, $errors];
        }

        // MIGRADO: Usar OrderRepository->listOrderItemsWithProducts()
        $items = $this->orders->listOrderItemsWithProducts([$orderId]);
        if (empty($items)) {
            return [$messages, ['Pedido sem itens para crédito consignado.']];
        }

        // MIGRADO: Usar OrderRepository->findOrderSummary()
        $summary = $this->orders->findOrderSummary($orderId);
        $orderStatus = strtolower(trim((string) ($summary['status'] ?? 'open')));
        if (in_array($orderStatus, ['trash', 'deleted', 'cancelled', 'cancelado', 'refunded', 'reembolsado'], true)) {
            return [$messages, $errors];
        }
        [$buyerName, $buyerEmail, $soldAt] = $this->resolveBuyerInfo($summary);

        // Idempotência por item vendido: um crédito de venda por order_item_id.
        // Isso evita duplicidade caso a conta de voucher mude entre reprocessamentos.
        $existingSaleByOrderItem = [];
        $vendorTotals = [];
        $vendorUnits = [];
        $existingCredits = $this->creditEntries->listByOrder($orderId, 'credito');
        foreach ($existingCredits as $existingCredit) {
            if (strtolower((string) ($existingCredit['event_type'] ?? '')) !== 'sale') {
                continue;
            }
            if ((int) ($existingCredit['event_id'] ?? 0) !== 0) {
                continue;
            }
            $existingOrderItemId = (int) ($existingCredit['order_item_id'] ?? 0);
            if ($existingOrderItemId <= 0 || isset($existingSaleByOrderItem[$existingOrderItemId])) {
                continue;
            }
            $existingSaleByOrderItem[$existingOrderItemId] = $existingCredit;

            $existingVendorPersonId = (int) ($existingCredit['vendor_pessoa_id'] ?? 0);
            if ($existingVendorPersonId <= 0) {
                continue;
            }
            $existingAmount = (float) ($existingCredit['credit_amount'] ?? 0);
            if ($existingAmount > 0) {
                $vendorTotals[$existingVendorPersonId] = ($vendorTotals[$existingVendorPersonId] ?? 0.0) + $existingAmount;
            }
            $existingQty = (int) ($existingCredit['quantity'] ?? 0);
            if ($existingQty > 0) {
                $vendorUnits[$existingVendorPersonId] = ($vendorUnits[$existingVendorPersonId] ?? 0) + $existingQty;
            }
        }

        $productIds = [];
        $productSkus = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                $productId = (int) ($item['product_sku'] ?? 0);
            }
            $variationId = (int) ($item['variation_id'] ?? 0);
            $refId = $productId > 0 ? $productId : $variationId;
            if ($refId > 0) {
                $productIds[$refId] = true;
            }

            $sku = trim((string) ($item['product_sku_from_catalog'] ?? $item['product_sku'] ?? $item['sku'] ?? ''));
            if ($sku !== '') {
                $productSkus[$sku] = true;
                if (ctype_digit($sku)) {
                    $productSkus[(int) $sku] = true;
                }
            }
        }

        $supplyMap = !empty($productIds)
            ? $this->supplies->listByProductIds(array_keys($productIds))
            : [];
        $supplyBySku = !empty($productSkus)
            ? $this->supplies->listBySkus(array_keys($productSkus))
            : [];

        if (empty($supplyMap) && empty($supplyBySku)) {
            return [$messages, []];
        }

        $vendorCache = [];
        $vendorLines = [];
        $missingOrderItemId = false;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                $productId = (int) ($item['product_sku'] ?? 0);
            }
            $variationId = (int) ($item['variation_id'] ?? 0);
            $refId = $productId > 0 ? $productId : $variationId;

            $orderItemId = (int) ($item['order_item_id'] ?? 0);
            if ($orderItemId <= 0) {
                $orderItemId = (int) ($item['id'] ?? 0);
            }
            if ($orderItemId <= 0) {
                $missingOrderItemId = true;
                continue;
            }
            if (isset($existingSaleByOrderItem[$orderItemId])) {
                continue;
            }

            $supply = null;
            if ($refId > 0) {
                $supply = $supplyMap[$refId] ?? null;
            }
            if (!$supply) {
                $skuKey = trim((string) ($item['product_sku_from_catalog'] ?? $item['product_sku'] ?? $item['sku'] ?? ''));
                if ($skuKey !== '') {
                    $supply = $supplyBySku[$skuKey] ?? null;
                    if (!$supply && ctype_digit($skuKey)) {
                        $supply = $supplyBySku[(int) $skuKey] ?? null;
                    }
                }
            }
            if (!$supply) {
                continue;
            }

            $source = (string) ($supply['source'] ?? '');
            if ($source !== 'consignacao') {
                continue;
            }

            $vendorPersonId = (int) ($supply['supplier_pessoa_id'] ?? 0);
            if ($vendorPersonId <= 0) {
                continue;
            }

            $percent = $this->resolveConsignPercent($supply, $vendorPersonId, $vendorCache);
            if ($percent === null || $percent <= 0) {
                continue;
            }

            $netRevenue = $item['product_net_revenue'] ?? null;
            $net = $netRevenue !== null && $netRevenue !== '' ? (float) $netRevenue : 0.0;
            if ($net <= 0) {
                $lineTotal = isset($item['total']) ? (float) $item['total'] : 0.0;
                if ($lineTotal > 0) {
                    $net = $lineTotal;
                }
            }
            if ($net <= 0) {
                $unit = isset($item['price']) ? (float) $item['price'] : 0.0;
                if ($unit > 0) {
                    $net = $unit;
                }
            }

            $credit = round($net * ($percent / 100), 2);
            if ($credit <= 0) {
                continue;
            }

            $qty = (int) ($item['product_qty'] ?? 0);
            if ($qty <= 0) {
                $qty = (int) ($item['quantity'] ?? 0);
            }
            $qty = $qty > 0 ? $qty : 1;
            $lineProductId = $productId > 0 ? $productId : (int) ($supply['product_id'] ?? 0);
            $vendorTotals[$vendorPersonId] = ($vendorTotals[$vendorPersonId] ?? 0.0) + $credit;
            $vendorUnits[$vendorPersonId] = ($vendorUnits[$vendorPersonId] ?? 0) + $qty;

            $sku = trim((string) ($supply['sku'] ?? $item['product_sku_from_catalog'] ?? $item['product_sku'] ?? $item['sku'] ?? ''));
            $productName = trim((string) ($item['product_name'] ?? $item['product_name_from_catalog'] ?? $item['name'] ?? ''));
            $unitPrice = $qty > 0 ? round($net / $qty, 2) : $net;

            $vendorLines[$vendorPersonId][] = [
                'order_item_id' => $orderItemId,
                'product_id' => $lineProductId > 0 ? $lineProductId : null,
                'variation_id' => $variationId > 0 ? $variationId : null,
                'sku' => $sku !== '' ? $sku : null,
                'product_name' => $productName !== '' ? $productName : null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $net,
                'percent' => $percent,
                'credit_amount' => $credit,
                'sold_at' => $soldAt,
                'buyer_name' => $buyerName,
                'buyer_email' => $buyerEmail,
            ];
        }

        if ($missingOrderItemId) {
            $errors[] = 'Pedido #' . $orderId . ' sem order_item_id em itens; crédito consignado pode ficar incompleto.';
        }

        if (empty($vendorLines)) {
            return [$messages, []];
        }

        foreach ($vendorLines as $vendorPersonId => $lines) {
            $vendor = $vendorCache[$vendorPersonId] ?? $this->vendors->find($vendorPersonId);
            if (!$vendor) {
                $errors[] = 'Fornecedor pessoa #' . $vendorPersonId . ' não encontrado para crédito consignado.';
                continue;
            }
            $vendorCache[$vendorPersonId] = $vendor;
            if ((int) ($vendor->id ?? 0) <= 0) {
                $errors[] = 'Fornecedor pessoa #' . $vendorPersonId . ' inválido para crédito consignado.';
                continue;
            }

            // O vendor.id JÁ É a pessoa_id correta (fonte de verdade).
            // Não precisa de VendorCustomerSyncService — vendor é uma pessoa.
            $vendorPessoaId = (int) ($vendor->id ?? 0);
            $vendorEmail = trim((string) ($vendor->email ?? ''));
            $account = $this->resolveVoucherAccountForVendor($vendor, $vendorEmail);
            $voucherId = (int) ($account->id ?? 0);

            if ($dryRun) {
                $insertedTotal = 0.0;
                $existingTotal = 0.0;
                foreach ($lines as $line) {
                    $orderItemId = (int) ($line['order_item_id'] ?? 0);
                    if ($orderItemId <= 0) {
                        continue;
                    }
                    $lineAmount = (float) ($line['credit_amount'] ?? 0);
                    if ($lineAmount <= 0) {
                        continue;
                    }
                    if ($voucherId > 0) {
                        $existing = $this->creditEntries->findByOrderItem($voucherId, $orderId, $orderItemId);
                        if ($existing) {
                            $existingTotal += $lineAmount;
                            continue;
                        }
                    }
                    $insertedTotal += $lineAmount;
                }

                $vendorName = $this->resolveVendorDisplayName($vendor, $vendorPersonId);
                if ($insertedTotal > 0) {
                    $messages[] = 'Prévia: crédito consignação seria atualizado para ' . $vendorName . ' (R$ ' . number_format($insertedTotal, 2, '.', '') . ').';
                } elseif ($existingTotal > 0) {
                    $messages[] = 'Prévia: crédito consignação já registrado para ' . $vendorName . ' (R$ ' . number_format($existingTotal, 2, '.', '') . ').';
                }
                continue;
            }

            try {
                $this->pdo->beginTransaction();
                $this->vouchers->save($account);
                $voucherId = (int) ($account->id ?? 0);
                if ($voucherId <= 0) {
                    throw new \RuntimeException('Cupom consignado sem ID para fornecedor pessoa #' . $vendorPersonId . '.');
                }

                $insertedTotal = 0.0;
                foreach ($lines as $line) {
                    $orderItemId = (int) ($line['order_item_id'] ?? 0);
                    if ($orderItemId <= 0) {
                        continue;
                    }
                    $inserted = $this->creditEntries->insert([
                        'voucher_account_id' => $voucherId,
                        'vendor_pessoa_id' => $vendorPersonId,
                        'order_id' => $orderId,
                        'order_item_id' => $orderItemId,
                        'product_id' => $line['product_id'],
                        'variation_id' => $line['variation_id'],
                        'sku' => $line['sku'],
                        'product_name' => $line['product_name'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'percent' => $line['percent'],
                        'credit_amount' => $line['credit_amount'],
                        'sold_at' => $line['sold_at'],
                        'buyer_name' => $line['buyer_name'],
                        'buyer_email' => $line['buyer_email'],
                        'type' => 'credito',
                        'event_type' => 'sale',
                        'event_id' => 0,
                        'event_label' => null,
                        'event_notes' => null,
                        'event_at' => $line['sold_at'],
                    ]);
                    if ($inserted) {
                        $insertedTotal += (float) $line['credit_amount'];
                    }
                }

                $orderTotal = $vendorTotals[$vendorPersonId] ?? 0.0;
                if ($orderTotal > 0) {
                    // customer_pessoa_id = vendor pessoa_id (mesma pessoa, sem clones)
                    $this->credits->upsert([
                        'order_id' => $orderId,
                        'vendor_pessoa_id' => $vendorPersonId,
                        'customer_pessoa_id' => $vendorPersonId,
                        'voucher_account_id' => $voucherId,
                        'amount' => round($orderTotal, 2),
                        'items_count' => $vendorUnits[$vendorPersonId] ?? 0,
                    ]);
                }

                if ($insertedTotal > 0) {
                    $this->vouchers->creditBalance($voucherId, $insertedTotal);
                }
                $this->pdo->commit();
                if ($insertedTotal > 0) {
                    $vendorName = $this->resolveVendorDisplayName($vendor, $vendorPersonId);
                    $messages[] = 'Crédito consignação atualizado para ' . $vendorName . ' (R$ ' . number_format($insertedTotal, 2, '.', '') . ').';
                }
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $errors[] = 'Erro ao criar crédito consignado para fornecedor pessoa #' . $vendorPersonId . ': ' . $e->getMessage();
            }
        }

        // Sync consignment sales module (registry + sales tracking)
        if (!$dryRun && !empty($vendorLines)) {
            try {
                $salesService = new ConsignmentSalesService($this->pdo);
                $salesService->syncFromOrder($orderId, $vendorLines);
            } catch (\Throwable $e) {
                $errors[] = 'Aviso: crédito registrado mas sync consignment_sales falhou: ' . $e->getMessage();
            }
        }

        return [$messages, $errors];
    }

    /**
     * @param OrderReturnItem[] $items
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public function debitForReturn(OrderReturn $return, array $items, bool $dryRun = false): array
    {
        $eventAt = $return->restockedAt ?: ($return->receivedAt ?: date('Y-m-d H:i:s'));
        $eventId = (int) ($return->id ?? 0);
        $eventLabel = $eventId > 0 ? 'Devolução #' . $eventId : 'Devolução';

        $itemMap = $this->mapReturnItems($items);
        if (empty($itemMap)) {
            return [[], ['Nenhum item válido para debitar em devolução.']];
        }

        return $this->applyMovementByItems(
            (int) $return->orderId,
            $itemMap,
            [
                'event_type' => 'return',
                'event_id' => $eventId,
                'event_label' => $eventLabel,
                'event_notes' => $return->notes ?: null,
                'event_at' => $eventAt,
            ],
            'debito',
            false,
            $dryRun
        );
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public function debitForOrderEvent(int $orderId, string $eventType, array $options = [], bool $dryRun = false): array
    {
        $eventType = trim($eventType);
        if ($eventType === '') {
            return [[], ['Evento de débito inválido.']];
        }
        $eventId = isset($options['event_id']) ? (int) $options['event_id'] : $orderId;
        $eventAt = isset($options['event_at']) ? (string) $options['event_at'] : date('Y-m-d H:i:s');
        $eventLabel = isset($options['event_label']) ? (string) $options['event_label'] : $this->resolveEventLabel($eventType, $eventId);
        $eventNotes = isset($options['event_notes']) ? (string) $options['event_notes'] : null;

        return $this->applyMovementByItems(
            $orderId,
            [],
            [
                'event_type' => $eventType,
                'event_id' => $eventId,
                'event_label' => $eventLabel,
                'event_notes' => $eventNotes,
                'event_at' => $eventAt,
            ],
            'debito',
            true,
            $dryRun
        );
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public function creditForReturnUndo(
        int $orderId,
        int $returnId,
        ?string $eventAt = null,
        ?string $notes = null,
        bool $dryRun = false
    ): array
    {
        $messages = [];
        $errors = [];

        if (!$this->pdo) {
            return [$messages, ['Sem conexão com banco local.']];
        }
        if ($orderId <= 0 || $returnId <= 0) {
            return [$messages, ['Devolução inválida para reverter crédito consignado.']];
        }

        $eventAt = $eventAt ?: date('Y-m-d H:i:s');
        $eventLabel = 'Cancelamento devolução #' . $returnId;
        $debitRows = $this->creditEntries->listByEvent($orderId, 'debito', 'return', $returnId);
        if (empty($debitRows)) {
            return [$messages, $errors];
        }

        if ($dryRun) {
            $existingCredits = $this->creditEntries->listByEvent($orderId, 'credito', 'return_cancel', $returnId);
            $existingMap = [];
            foreach ($existingCredits as $row) {
                $orderItemId = (int) ($row['order_item_id'] ?? 0);
                if ($orderItemId > 0) {
                    $existingMap[$orderItemId] = true;
                }
            }

            $totalsByVendor = [];
            foreach ($debitRows as $row) {
                $orderItemId = (int) ($row['order_item_id'] ?? 0);
                if ($orderItemId > 0 && isset($existingMap[$orderItemId])) {
                    continue;
                }
                $amount = (float) ($row['credit_amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $vendorPersonId = (int) ($row['vendor_pessoa_id'] ?? 0);
                if ($vendorPersonId > 0) {
                    $totalsByVendor[$vendorPersonId] = ($totalsByVendor[$vendorPersonId] ?? 0.0) + $amount;
                }
            }

            foreach ($totalsByVendor as $vendorPersonId => $amount) {
                $vendor = $this->vendors->find((int) $vendorPersonId);
                $vendorName = $vendor ? $this->resolveVendorDisplayName($vendor, (int) $vendorPersonId) : ('Fornecedor ' . $vendorPersonId);
                $messages[] = 'Prévia: crédito consignação seria reativado para ' . $vendorName . ' (R$ ' . number_format($amount, 2, '.', '') . ').';
            }

            return [$messages, $errors];
        }

        $totalsByVoucher = [];
        $vendorsTouched = [];

        try {
            $this->pdo->beginTransaction();
            foreach ($debitRows as $row) {
                $amount = (float) ($row['credit_amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $voucherId = (int) ($row['voucher_account_id'] ?? 0);
                if ($voucherId <= 0) {
                    continue;
                }

                $inserted = $this->creditEntries->insert([
                    'voucher_account_id' => $voucherId,
                    'vendor_pessoa_id' => $row['vendor_pessoa_id'] ?? null,
                    'order_id' => $row['order_id'] ?? $orderId,
                    'order_item_id' => $row['order_item_id'] ?? 0,
                    'product_id' => $row['product_id'] ?? null,
                    'variation_id' => $row['variation_id'] ?? null,
                    'sku' => $row['sku'] ?? null,
                    'product_name' => $row['product_name'] ?? null,
                    'quantity' => $row['quantity'] ?? 1,
                    'unit_price' => $row['unit_price'] ?? null,
                    'line_total' => $row['line_total'] ?? null,
                    'percent' => $row['percent'] ?? null,
                    'credit_amount' => $amount,
                    'sold_at' => $row['sold_at'] ?? null,
                    'buyer_name' => $row['buyer_name'] ?? null,
                    'buyer_email' => $row['buyer_email'] ?? null,
                    'type' => 'credito',
                    'event_type' => 'return_cancel',
                    'event_id' => $returnId,
                    'event_label' => $eventLabel,
                    'event_notes' => $notes,
                    'event_at' => $eventAt,
                ]);

                if ($inserted) {
                    $totalsByVoucher[$voucherId] = ($totalsByVoucher[$voucherId] ?? 0.0) + $amount;
                    $vendorPersonId = (int) ($row['vendor_pessoa_id'] ?? 0);
                    if ($vendorPersonId > 0) {
                        $vendorsTouched[$vendorPersonId] = true;
                    }
                }
            }

            foreach ($totalsByVoucher as $voucherId => $amount) {
                if ($amount > 0) {
                    $this->vouchers->creditBalance($voucherId, $amount);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errors[] = 'Erro ao reverter débito consignado da devolução: ' . $e->getMessage();
            return [$messages, $errors];
        }

        foreach (array_keys($vendorsTouched) as $vendorPersonId) {
            $vendor = $this->vendors->find((int) $vendorPersonId);
            $vendorName = $vendor ? $this->resolveVendorDisplayName($vendor, (int) $vendorPersonId) : ('Fornecedor ' . $vendorPersonId);
            $messages[] = 'Crédito consignação reativado para ' . $vendorName . '.';
        }

        return [$messages, $errors];
    }

    /**
     * @param array<int, int> $itemMap
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function applyMovementByItems(
        int $orderId,
        array $itemMap,
        array $event,
        string $movementType,
        bool $applyAll,
        bool $dryRun = false
    ): array {
        $messages = [];
        $errors = [];

        if (!$this->pdo) {
            return [$messages, ['Sem conexão com banco local.']];
        }
        if ($orderId <= 0) {
            return [$messages, ['Pedido inválido para movimento consignado.']];
        }

        $eventType = trim((string) ($event['event_type'] ?? ''));
        if ($eventType === '') {
            return [$messages, ['Evento consignado inválido.']];
        }

        $credits = $this->creditEntries->listByOrder($orderId, 'credito');
        if (empty($credits)) {
            return [$messages, $errors];
        }

        $movements = $this->creditEntries->listByOrder($orderId);
        [$debitedQty, $debitedAmount] = $this->summarizeReversals($movements);

        $entries = [];
        foreach ($credits as $credit) {
            $orderItemId = (int) ($credit['order_item_id'] ?? 0);
            if ($orderItemId <= 0) {
                continue;
            }

            $creditQty = (int) ($credit['quantity'] ?? 0);
            if ($creditQty <= 0) {
                continue;
            }

            $key = $this->entryKey($credit);
            $alreadyQty = max(0, (int) ($debitedQty[$key] ?? 0));
            $alreadyAmount = max(0.0, (float) ($debitedAmount[$key] ?? 0.0));
            $remainingQty = $creditQty - $alreadyQty;
            if ($remainingQty <= 0) {
                continue;
            }

            if (!$applyAll && !isset($itemMap[$orderItemId])) {
                continue;
            }

            $requestedQty = $applyAll ? $remainingQty : (int) ($itemMap[$orderItemId] ?? 0);
            if ($requestedQty <= 0) {
                continue;
            }
            $qtyToMove = min($requestedQty, $remainingQty);
            if ($qtyToMove <= 0) {
                continue;
            }

            $creditAmount = (float) ($credit['credit_amount'] ?? 0);
            if ($creditAmount <= 0) {
                continue;
            }

            $remainingAmount = $creditAmount - $alreadyAmount;
            if ($remainingAmount <= 0) {
                continue;
            }

            $unitPrice = $credit['unit_price'] !== null ? (float) $credit['unit_price'] : 0.0;
            $lineTotal = $credit['line_total'] !== null ? (float) $credit['line_total'] : 0.0;
            if ($unitPrice <= 0 && $lineTotal > 0 && $creditQty > 0) {
                $unitPrice = $lineTotal / $creditQty;
            }

            $perUnitCredit = $creditQty > 0 ? ($creditAmount / $creditQty) : 0.0;
            $moveAmount = round($perUnitCredit * $qtyToMove, 2);
            if ($qtyToMove >= $remainingQty) {
                $moveAmount = round($remainingAmount, 2);
            }
            if ($moveAmount <= 0) {
                continue;
            }

            $moveLineTotal = null;
            if ($unitPrice > 0) {
                $moveLineTotal = round($unitPrice * $qtyToMove, 2);
            } elseif ($lineTotal > 0 && $creditQty > 0) {
                $moveLineTotal = round(($lineTotal / $creditQty) * $qtyToMove, 2);
            }

            $entries[] = [
                'voucher_account_id' => $credit['voucher_account_id'] ?? null,
                'vendor_pessoa_id' => $credit['vendor_pessoa_id'] ?? null,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'product_id' => $credit['product_id'] ?? null,
                'variation_id' => $credit['variation_id'] ?? null,
                'sku' => $credit['sku'] ?? null,
                'product_name' => $credit['product_name'] ?? null,
                'quantity' => $qtyToMove,
                'unit_price' => $unitPrice > 0 ? $unitPrice : null,
                'line_total' => $moveLineTotal,
                'percent' => $credit['percent'] ?? null,
                'credit_amount' => $moveAmount,
                'sold_at' => $credit['sold_at'] ?? null,
                'buyer_name' => $credit['buyer_name'] ?? null,
                'buyer_email' => $credit['buyer_email'] ?? null,
                'type' => $movementType,
                'event_type' => $eventType,
                'event_id' => (int) ($event['event_id'] ?? 0),
                'event_label' => $event['event_label'] ?? $this->resolveEventLabel($eventType, (int) ($event['event_id'] ?? 0)),
                'event_notes' => $event['event_notes'] ?? null,
                'event_at' => $event['event_at'] ?? null,
            ];
        }

        if (empty($entries)) {
            return [$messages, $errors];
        }

        // ── REGRA DE OURO: for debits, check golden rule BEFORE persisting ──
        // If the item was already paid (payout_status='pago'), the debit must NOT
        // be applied to the supplier's ledger to avoid negative balance.
        $goldenRuleSkipped = [];
        if ($movementType === 'debito') {
            $salesService = new ConsignmentSalesService($this->pdo);
            $eventType = trim((string) ($event['event_type'] ?? ''));
            $filteredEntries = [];
            foreach ($entries as $entry) {
                $productId = (int) ($entry['product_id'] ?? 0);
                $entryOrderId = (int) ($entry['order_id'] ?? $orderId);
                $entryOrderItemId = (int) ($entry['order_item_id'] ?? 0);

                if ($productId > 0 && $entryOrderId > 0 && $entryOrderItemId > 0) {
                    $reversalResult = $salesService->handleReversal(
                        $entryOrderId, $entryOrderItemId, $eventType, $productId
                    );

                    if (!$reversalResult['should_debit_ledger']) {
                        // Golden rule: do NOT debit this entry
                        $goldenRuleSkipped[] = $entry;
                        $messages[] = $reversalResult['notes'] ?: '[REGRA DE OURO] Débito suprimido para produto #' . $productId . '.';
                        continue;
                    }
                }

                $filteredEntries[] = $entry;
            }
            $entries = $filteredEntries;
        }

        if (empty($entries) && !empty($goldenRuleSkipped)) {
            // All entries were suppressed by the golden rule
            return [$messages, $errors];
        }

        if (empty($entries)) {
            return [$messages, $errors];
        }

        if ($dryRun) {
            $totalsByVendor = [];
            foreach ($entries as $entry) {
                $vendorPersonId = (int) ($entry['vendor_pessoa_id'] ?? 0);
                if ($vendorPersonId <= 0) {
                    continue;
                }
                $amount = (float) ($entry['credit_amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $totalsByVendor[$vendorPersonId] = ($totalsByVendor[$vendorPersonId] ?? 0.0) + $amount;
            }

            foreach ($totalsByVendor as $vendorPersonId => $amount) {
                $vendor = $this->vendors->find((int) $vendorPersonId);
                $vendorName = $vendor ? $this->resolveVendorDisplayName($vendor, (int) $vendorPersonId) : ('Fornecedor ' . $vendorPersonId);
                if ($movementType === 'debito') {
                    $messages[] = 'Prévia: abatimento consignação seria registrado para ' . $vendorName . ' (R$ ' . number_format($amount, 2, '.', '') . ').';
                } else {
                    $messages[] = 'Prévia: crédito consignação seria registrado para ' . $vendorName . ' (R$ ' . number_format($amount, 2, '.', '') . ').';
                }
            }

            return [$messages, $errors];
        }

        $totalsByVoucher = [];
        $vendorsTouched = [];
        try {
            $this->pdo->beginTransaction();
            foreach ($entries as $entry) {
                $voucherId = (int) ($entry['voucher_account_id'] ?? 0);
                if ($voucherId <= 0) {
                    continue;
                }

                $inserted = $this->creditEntries->insert($entry);
                if (!$inserted) {
                    continue;
                }

                $amount = (float) ($entry['credit_amount'] ?? 0);
                if ($amount > 0) {
                    $totalsByVoucher[$voucherId] = ($totalsByVoucher[$voucherId] ?? 0.0) + $amount;
                }

                $vendorPersonId = (int) ($entry['vendor_pessoa_id'] ?? 0);
                if ($vendorPersonId > 0) {
                    $vendorsTouched[$vendorPersonId] = true;
                }
            }

            foreach ($totalsByVoucher as $voucherId => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                if ($movementType === 'debito') {
                    $this->vouchers->debitBalance($voucherId, $amount);
                } else {
                    $this->vouchers->creditBalance($voucherId, $amount);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $errors[] = 'Erro ao aplicar movimento consignado: ' . $e->getMessage();
            return [$messages, $errors];
        }

        foreach (array_keys($vendorsTouched) as $vendorPersonId) {
            $vendor = $this->vendors->find((int) $vendorPersonId);
            $vendorName = $vendor ? $this->resolveVendorDisplayName($vendor, (int) $vendorPersonId) : ('Fornecedor ' . $vendorPersonId);
            $messages[] = $movementType === 'debito'
                ? 'Abatimento consignação registrado para ' . $vendorName . '.'
                : 'Crédito consignação registrado para ' . $vendorName . '.';
        }

        return [$messages, $errors];
    }

    /**
     * @param OrderReturnItem[] $items
     * @return array<int, int>
     */
    private function mapReturnItems(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $orderItemId = $item->orderItemId ?? 0;
            if ($orderItemId <= 0) {
                continue;
            }
            $qty = $item->quantity ?? 0;
            if ($qty <= 0) {
                continue;
            }
            $map[$orderItemId] = ($map[$orderItemId] ?? 0) + $qty;
        }
        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: array<string, int>, 1: array<string, float>}
     */
    private function summarizeReversals(array $rows): array
    {
        $qtyMap = [];
        $amountMap = [];
        foreach ($rows as $row) {
            $type = strtolower((string) ($row['type'] ?? ''));
            $eventType = strtolower((string) ($row['event_type'] ?? ''));
            $sign = 0;
            if ($type === 'debito') {
                $sign = 1;
            } elseif ($type === 'credito' && $eventType !== '' && $eventType !== 'sale') {
                $sign = -1;
            } else {
                continue;
            }
            $key = $this->entryKey($row);
            $qtyMap[$key] = ($qtyMap[$key] ?? 0) + ($sign * (int) ($row['quantity'] ?? 0));
            $amountMap[$key] = ($amountMap[$key] ?? 0.0) + ($sign * (float) ($row['credit_amount'] ?? 0));
        }
        return [$qtyMap, $amountMap];
    }

    private function entryKey(array $row): string
    {
        $voucherId = (int) ($row['voucher_account_id'] ?? 0);
        $orderItemId = (int) ($row['order_item_id'] ?? 0);
        return $voucherId . ':' . $orderItemId;
    }

    private function resolveEventLabel(string $eventType, int $eventId): string
    {
        $base = self::EVENT_LABELS[$eventType] ?? 'Movimento';
        if ($eventId > 0 && in_array($eventType, ['return', 'return_cancel'], true)) {
            return $base . ' #' . $eventId;
        }
        return $base;
    }

    private function resolveConsignPercent(array $supply, int $vendorPersonId, array &$vendorCache): ?float
    {
        if (isset($supply['percentual_consignacao']) && $supply['percentual_consignacao'] !== '') {
            return (float) $supply['percentual_consignacao'];
        }

        if (!isset($vendorCache[$vendorPersonId])) {
            $vendorCache[$vendorPersonId] = $this->vendors->find($vendorPersonId);
        }

        $vendor = $vendorCache[$vendorPersonId] ?? null;
        if ($vendor && $vendor->commissionRate !== null) {
            return (float) $vendor->commissionRate;
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function resolveBuyerInfo(?array $summary): array
    {
        if (!$summary) {
            return [null, null, null];
        }

        $soldAt = $summary['date_created'] ?? ($summary['created_at'] ?? null);

        // Priorizar billing_name (campo principal na tabela orders)
        $billingName = trim((string) ($summary['billing_name'] ?? ''));
        if ($billingName === '') {
            // Legacy fallback: billing_first_name + billing_last_name
            $billingName = trim((string) (($summary['billing_first_name'] ?? '') . ' ' . ($summary['billing_last_name'] ?? '')));
        }

        $billingEmail = trim((string) ($summary['billing_email'] ?? ''));

        $customerName = trim((string) ($summary['customer_full_name'] ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) (($summary['customer_first_name'] ?? '') . ' ' . ($summary['customer_last_name'] ?? '')));
        }
        $customerEmail = trim((string) ($summary['customer_email'] ?? ''));
        $displayName = trim((string) ($summary['customer_display_name'] ?? ''));

        $buyerName = $billingName !== '' ? $billingName : ($customerName !== '' ? $customerName : $displayName);
        if ($buyerName === '') {
            $buyerName = $billingEmail !== '' ? $billingEmail : ($customerEmail !== '' ? $customerEmail : null);
        }
        $buyerEmail = $billingEmail !== '' ? $billingEmail : ($customerEmail !== '' ? $customerEmail : null);

        return [$buyerName ?: null, $buyerEmail ?: null, $soldAt ?: null];
    }

    private function resolveVendorDisplayName($vendor, int $vendorId): string
    {
        $name = trim((string) ($vendor->fullName ?? ''));
        if ($name !== '') {
            return $name;
        }
        return 'Fornecedor ' . $vendorId;
    }

    /**
     * Encontra ou cria conta de cupom de consignação para o fornecedor.
     * O cupom SEMPRE pertence ao vendor.id (fonte de verdade).
     */
    private function resolveVoucherAccountForVendor($vendor, ?string $vendorEmail = null): VoucherAccount
    {
        $vendorPessoaId = (int) ($vendor->id ?? 0);
        $vendorName = $this->resolveVendorDisplayName($vendor, $vendorPessoaId);

        $selected = null;
        $existing = $this->vouchers->listByPerson($vendorPessoaId, false);
        foreach ($existing as $row) {
            if ((string) ($row['type'] ?? '') !== 'credito') {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            if ($selected === null) {
                $selected = $row;
            }
            if ($status === 'ativo') {
                $selected = $row;
                break;
            }
        }

        $account = $selected ? VoucherAccount::fromArray($selected) : new VoucherAccount();

        $account->personId = $vendorPessoaId;
        $account->customerName = $vendorName;
        if ($vendorEmail !== null && $vendorEmail !== '') {
            $account->customerEmail = $vendorEmail;
        }
        $account->label = 'Crédito consignação - ' . $vendorName;
        $account->type = 'credito';
        $account->scope = 'consignacao';
        $account->status = 'ativo';
        $account->description = 'Crédito consignação de ' . $vendorName . '.';

        return $account;
    }

}
