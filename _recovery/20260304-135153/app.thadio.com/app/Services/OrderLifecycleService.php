<?php

namespace App\Services;

class OrderLifecycleService
{
    private const EPSILON = 0.01;

    public const ORDER_STATUS_LABELS = [
        'draft' => 'Rascunho',
        'open' => 'Em aberto',
        'completed' => 'Concluido',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'none' => 'Sem cobranca',
        'pending' => 'Pendente',
        'partial' => 'Parcial',
        'paid' => 'Pago',
        'refunded' => 'Reembolsado',
        'partially_refunded' => 'Parcialmente reembolsado',
    ];

    public const FULFILLMENT_STATUS_LABELS = [
        'not_required' => 'Nao aplicavel',
        'pending' => 'Pendente',
        'shipped' => 'Enviado',
        'delivered' => 'Entregue',
        'returned' => 'Devolvido',
    ];

    public const DELIVERY_MODE_LABELS = [
        'immediate_in_hand' => 'Em mãos agora',
        'store_pickup' => 'Retirada na loja',
        'shipment' => 'Enviar/Entregar',
    ];

    public const SHIPMENT_KIND_LABELS = [
        'tracked' => 'Rastreável (Correios/Transportadora)',
        'local_courier' => 'Entrega local (motoboy)',
        'bag_deferred' => 'Sacolinha (enviar depois)',
    ];

    public const PENDING_LABELS = [
        'missing_payment_method' => 'Metodo de pagamento nao informado',
        'payment_due_now' => 'Pagamento pendente agora',
        'opening_fee_due_later' => 'Taxa de abertura pendente para depois',
        'no_items' => 'Pedido sem itens',
        'shipping_address_missing' => 'Endereco de entrega incompleto',
        'delivery_event_missing' => 'Evento de entrega pendente',
    ];

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function computeSnapshot(array $order, array $context = []): array
    {
        $meta = $this->extractMeta((array) ($order['meta_data'] ?? []));
        $entries = $this->extractPaymentEntries($order, $meta);
        $normalizedEntries = $this->normalizeLedgerEntries($entries);

        $itemsTotal = $this->computeItemsTotal($order);
        $shippingTotal = $this->computeShippingTotal($order);
        $openingFeeDueLater = $this->toMoney($context['opening_fee_due_later'] ?? 0.0);
        $openingFeeDueNow = $this->toMoney($context['opening_fee_due_now'] ?? 0.0);

        $dueNow = max(0.0, $itemsTotal + $shippingTotal + $openingFeeDueNow);
        $dueLater = max(0.0, $openingFeeDueLater);

        $explicitPaymentStatus = $this->extractExplicitPaymentStatus($order, $meta);
        [$paidTotal, $refundedTotal, $entriesCount] = $this->summarizeLedger($normalizedEntries);
        $netPaid = max(0.0, $paidTotal - $refundedTotal);
        $explicitStatusForComputation = $this->shouldHonorExplicitPaymentStatus($explicitPaymentStatus, $entriesCount)
            ? $explicitPaymentStatus
            : '';
        [$paidTotal, $refundedTotal, $entriesCount, $netPaid] = $this->applyExplicitPaymentFallback(
            $explicitStatusForComputation,
            $dueNow,
            $entriesCount,
            $paidTotal,
            $refundedTotal,
            $netPaid
        );
        $balanceDueNow = max(0.0, $dueNow - $netPaid);

        $paymentStatus = $explicitStatusForComputation !== ''
            ? $explicitStatusForComputation
            : $this->derivePaymentStatus(
                $dueNow,
                $entriesCount,
                $paidTotal,
                $refundedTotal,
                $netPaid,
                $explicitStatusForComputation
            );
        $deliveryMode = $this->deriveDeliveryMode($order, $meta);
        $shipmentKind = $this->deriveShipmentKind($order, $meta, $deliveryMode);
        $fulfillmentStatus = $this->deriveFulfillmentStatus($order, $context, $meta, $deliveryMode);

        $pendingCodes = $this->derivePendingCodes(
            $order,
            $context,
            $dueNow,
            $balanceDueNow,
            $dueLater,
            $entriesCount,
            $paymentStatus,
            $fulfillmentStatus,
            $deliveryMode
        );
        $nonBlockingCodes = $this->deriveNonBlockingCodes($context);
        $blockingCodes = array_values(array_filter($pendingCodes, static function (string $code) use ($nonBlockingCodes): bool {
            return !in_array($code, $nonBlockingCodes, true);
        }));

        $orderStatus = $this->deriveOrderStatus($order, $paymentStatus, $fulfillmentStatus, $pendingCodes, $blockingCodes, $balanceDueNow);

        return [
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'fulfillment_status' => $fulfillmentStatus,
            'delivery_mode' => $deliveryMode,
            'shipment_kind' => $shipmentKind,
            'totals' => [
                'items_total' => $this->roundMoney($itemsTotal),
                'shipping_total' => $this->roundMoney($shippingTotal),
                'opening_fee_due_now' => $this->roundMoney($openingFeeDueNow),
                'opening_fee_due_later' => $this->roundMoney($openingFeeDueLater),
                'due_now' => $this->roundMoney($dueNow),
                'due_later' => $this->roundMoney($dueLater),
                'due_total' => $this->roundMoney($dueNow + $dueLater),
                'paid_total' => $this->roundMoney($paidTotal),
                'refunded_total' => $this->roundMoney($refundedTotal),
                'net_paid' => $this->roundMoney($netPaid),
                'balance_due_now' => $this->roundMoney($balanceDueNow),
            ],
            'pending_codes' => $pendingCodes,
            'blocking_pending_codes' => $blockingCodes,
            'pending_count' => count($pendingCodes),
            'blocking_pending_count' => count($blockingCodes),
            'pending' => $this->mapPendingDetails($pendingCodes),
            'labels' => [
                'order_status' => self::ORDER_STATUS_LABELS[$orderStatus] ?? $orderStatus,
                'payment_status' => self::PAYMENT_STATUS_LABELS[$paymentStatus] ?? $paymentStatus,
                'fulfillment_status' => self::FULFILLMENT_STATUS_LABELS[$fulfillmentStatus] ?? $fulfillmentStatus,
                'delivery_mode' => self::DELIVERY_MODE_LABELS[$deliveryMode] ?? $deliveryMode,
                'shipment_kind' => $shipmentKind !== null
                    ? (self::SHIPMENT_KIND_LABELS[$shipmentKind] ?? $shipmentKind)
                    : '-',
            ],
            'ledger' => [
                'entries_count' => $entriesCount,
                'entries' => $normalizedEntries,
            ],
            'timeline' => $this->buildTimeline($order, $normalizedEntries, $fulfillmentStatus, $deliveryMode),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function toPersistencePayload(array $snapshot, array $order = []): array
    {
        $status = self::normalizeOrderStatus((string) ($snapshot['order_status'] ?? 'open'));
        $paymentStatus = self::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? 'pending'));
        $fulfillmentStatus = self::normalizeFulfillmentStatus((string) ($snapshot['fulfillment_status'] ?? 'pending'));
        $deliveryMode = OrderService::normalizeDeliveryMode((string) ($snapshot['delivery_mode'] ?? 'shipment'));
        $shipmentKind = OrderService::normalizeShipmentKind(
            (string) ($snapshot['shipment_kind'] ?? ''),
            $deliveryMode
        );

        $shippingInfo = $order['shipping_info'] ?? [];
        if (!is_array($shippingInfo)) {
            $shippingInfo = [];
        }
        $shippingInfo['status'] = $fulfillmentStatus;
        $shippingInfo['delivery_mode'] = $deliveryMode;
        $shippingInfo['shipment_kind'] = $shipmentKind;

        return [
            'status' => $status,
            'payment_status' => $paymentStatus,
            'fulfillment_status' => $fulfillmentStatus,
            'delivery_mode' => $deliveryMode,
            'shipment_kind' => $shipmentKind,
            'delivery_status' => $fulfillmentStatus,
            'shipping_info' => $shippingInfo,
            'skip_completed_at' => true,
            'keep_completed_at' => true,
        ];
    }

    public static function normalizeOrderStatus(?string $status): string
    {
        $normalized = self::normalizeKey($status);
        if (str_starts_with($normalized, 'wc-')) {
            $normalized = substr($normalized, 3);
        }

        return match ($normalized) {
            '', 'open', 'pending', 'pendente', 'processing', 'processando', 'on_hold', 'onhold', 'hold', 'failed', 'falhou' => 'open',
            'draft', 'rascunho' => 'draft',
            'completed', 'concluido', 'finalizado' => 'completed',
            'cancelled', 'cancelado' => 'cancelled',
            'refunded', 'reembolsado', 'estornado', 'estorno' => 'refunded',
            'trash', 'lixeira' => 'trash',
            'deleted', 'excluido' => 'deleted',
            default => $normalized,
        };
    }

    public static function normalizePaymentStatus(?string $status): string
    {
        $normalized = self::normalizeKey($status);

        return match ($normalized) {
            '', 'none', 'sem-cobranca', 'sem-cobranca-agora', 'sem-cobrança' => 'none',
            'pending', 'pendente', 'aguardando', 'awaiting', 'failed', 'falhou' => 'pending',
            'partial', 'parcial', 'partially-paid' => 'partial',
            'paid', 'pago', 'approved', 'aprovado' => 'paid',
            'refunded', 'reembolsado', 'estornado', 'estorno' => 'refunded',
            'partially_refunded', 'parcialmente-reembolsado', 'partial_refunded' => 'partially_refunded',
            default => $normalized,
        };
    }

    public static function normalizeFulfillmentStatus(?string $status): string
    {
        $normalized = self::normalizeKey($status);

        return match ($normalized) {
            '', 'pending', 'pendente', 'novo', 'separacao', 'separando', 'embalado', 'cancelado', 'unfulfilled' => 'pending',
            'not_required', 'nao-aplicavel', 'nao_aplicavel', 'n/a', 'retirada', 'pickup' => 'not_required',
            'shipped', 'enviado', 'fulfilled', 'despachado' => 'shipped',
            'delivered', 'entregue', 'received', 'recebido', 'completed' => 'delivered',
            'returned', 'devolvido', 'devolucao', 'devolucao_total' => 'returned',
            default => $normalized,
        };
    }

    public static function deriveOrderStatusFromDimensions(string $currentStatus, string $paymentStatus, string $fulfillmentStatus): string
    {
        $currentStatus = self::normalizeOrderStatus($currentStatus);
        $paymentStatus = self::normalizePaymentStatus($paymentStatus);
        $fulfillmentStatus = self::normalizeFulfillmentStatus($fulfillmentStatus);

        // Terminal / manual statuses: always preserved
        if (in_array($currentStatus, ['cancelled', 'refunded', 'trash', 'deleted'], true)) {
            return $currentStatus;
        }

        // Full refund → refunded
        if ($paymentStatus === 'refunded') {
            return 'refunded';
        }

        // Payment settled (paid, partially_refunded, or free/none) + fulfillment done → completed
        $paymentSettled = in_array($paymentStatus, ['paid', 'partially_refunded', 'none'], true);
        $fulfillmentDone = in_array($fulfillmentStatus, ['delivered', 'not_required', 'returned'], true);
        if ($paymentSettled && $fulfillmentDone) {
            return 'completed';
        }

        // Everything else (pending, partial, awaiting shipment, etc.) → open
        return 'open';
    }

    /**
     * @param array<int, mixed> $metaData
     * @return array<string, mixed>
     */
    private function extractMeta(array $metaData): array
    {
        $output = [];
        foreach ($metaData as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $key = $meta['key'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }
            $output[(string) $key] = $meta['value'] ?? null;
        }
        return $output;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $meta
     * @return array<int, array<string, mixed>>
     */
    private function extractPaymentEntries(array $order, array $meta): array
    {
        $raw = $meta['retrato_payment_entries'] ?? ($order['payment_entries'] ?? ($order['payments'] ?? null));
        if (is_array($raw)) {
            if (!empty($raw)) {
                return $raw;
            }
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLedgerEntries(array $entries): array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $amount = $this->toMoney($entry['amount'] ?? 0.0);
            $status = self::normalizeKey((string) ($entry['status'] ?? ''));
            $isRefund = $this->isTrueLike($entry['refunded'] ?? null)
                || $status === 'refunded'
                || self::normalizeKey((string) ($entry['type'] ?? '')) === 'refund'
                || $amount < 0;
            $isPaid = $this->isTrueLike($entry['paid'] ?? null);

            if ($isRefund && $amount > 0) {
                $amount = -$amount;
            }

            $normalized[] = [
                'method_id' => (int) ($entry['method_id'] ?? 0),
                'method_name' => trim((string) ($entry['method_name'] ?? '')),
                'method_type' => trim((string) ($entry['method_type'] ?? '')),
                'amount' => $this->roundMoney($amount),
                'paid' => $isPaid,
                'refunded' => $isRefund,
                'occurred_at' => (string) ($entry['paid_at'] ?? $entry['created_at'] ?? ''),
            ];
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function computeItemsTotal(array $order): float
    {
        $lineItems = $order['line_items'] ?? null;
        if (is_array($lineItems) && !empty($lineItems)) {
            $sum = 0.0;
            foreach ($lineItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $qty = max(0, (int) ($item['quantity'] ?? 0));
                $lineTotal = $this->toMoney($item['total'] ?? null);
                if ($lineTotal > 0) {
                    $sum += $lineTotal;
                    continue;
                }
                $price = $this->toMoney($item['price'] ?? null);
                $sum += $price * $qty;
            }
            return max(0.0, $sum);
        }
        return max(0.0, $this->toMoney($order['subtotal'] ?? 0.0));
    }

    /**
     * @param array<string, mixed> $order
     */
    private function computeShippingTotal(array $order): float
    {
        $shippingTotal = $this->toMoney($order['shipping_total'] ?? null);
        if ($shippingTotal > 0) {
            return $shippingTotal;
        }
        $shippingInfo = $order['shipping_info'] ?? null;
        if (is_array($shippingInfo)) {
            return max(0.0, $this->toMoney($shippingInfo['total'] ?? 0.0));
        }
        return 0.0;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array{0: float, 1: float, 2: int}
     */
    private function summarizeLedger(array $entries): array
    {
        $paid = 0.0;
        $refunded = 0.0;
        $count = 0;
        foreach ($entries as $entry) {
            $amount = (float) ($entry['amount'] ?? 0.0);
            if (abs($amount) < self::EPSILON) {
                continue;
            }
            $count++;
            $isRefund = !empty($entry['refunded']) || $amount < 0;
            if ($isRefund) {
                $refunded += abs($amount);
                continue;
            }
            if (!empty($entry['paid'])) {
                $paid += $amount;
            }
        }
        return [$paid, $refunded, $count];
    }

    private function derivePaymentStatus(
        float $dueNow,
        int $entriesCount,
        float $paidTotal,
        float $refundedTotal,
        float $netPaid,
        string $explicitPaymentStatus = ''
    ): string
    {
        if ($entriesCount <= 0 && $explicitPaymentStatus !== '') {
            return $explicitPaymentStatus;
        }

        if ($refundedTotal > self::EPSILON) {
            if ($dueNow > self::EPSILON && $refundedTotal + self::EPSILON >= $dueNow) {
                return 'refunded';
            }
            return 'partially_refunded';
        }
        if ($dueNow <= self::EPSILON && $entriesCount === 0) {
            return 'none';
        }
        if ($dueNow > self::EPSILON && $entriesCount === 0) {
            return 'pending';
        }
        if ($netPaid <= self::EPSILON || $paidTotal <= self::EPSILON) {
            return $dueNow > self::EPSILON ? 'pending' : 'none';
        }
        if ($dueNow > self::EPSILON && ($dueNow - $netPaid) > self::EPSILON) {
            return 'partial';
        }
        return 'paid';
    }

    /**
     * @return array{0: float, 1: float, 2: int, 3: float}
     */
    private function applyExplicitPaymentFallback(
        string $explicitPaymentStatus,
        float $dueNow,
        int $entriesCount,
        float $paidTotal,
        float $refundedTotal,
        float $netPaid
    ): array {
        if ($explicitPaymentStatus === '') {
            return [$paidTotal, $refundedTotal, $entriesCount, $netPaid];
        }

        // Fonte única de verdade: quando o status explícito informa pagamento,
        // o saldo financeiro precisa refletir esse estado, mesmo sem ledger completo.
        if ($explicitPaymentStatus === 'paid' && $dueNow > self::EPSILON && ($netPaid + self::EPSILON) < $dueNow) {
            $paidTotal = max($paidTotal, $dueNow);
            $refundedTotal = 0.0;
            $netPaid = max($netPaid, $dueNow);
            $entriesCount = max(1, $entriesCount);
        }

        if ($explicitPaymentStatus === 'refunded' && $dueNow > self::EPSILON && $refundedTotal + self::EPSILON < $dueNow) {
            $paidTotal = max($paidTotal, $dueNow);
            $refundedTotal = max($refundedTotal, $dueNow);
            $netPaid = max(0.0, $paidTotal - $refundedTotal);
            $entriesCount = max(1, $entriesCount);
        }

        return [$paidTotal, $refundedTotal, $entriesCount, $netPaid];
    }

    private function shouldHonorExplicitPaymentStatus(string $explicitPaymentStatus, int $entriesCount): bool
    {
        if ($explicitPaymentStatus === '' || $entriesCount > 0) {
            return false;
        }

        return in_array($explicitPaymentStatus, ['paid', 'refunded', 'partially_refunded'], true);
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $meta
     */
    private function extractExplicitPaymentStatus(array $order, array $meta): string
    {
        $candidates = [
            $order['payment_status'] ?? null,
            $order['payment']['status'] ?? null,
            $meta['payment_status'] ?? null,
            $meta['retrato_payment_status'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $normalized = self::normalizePaymentStatus((string) $candidate);
            if (array_key_exists($normalized, self::PAYMENT_STATUS_LABELS)) {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $context
     * @param array<string, mixed> $meta
     */
    private function deriveFulfillmentStatus(array $order, array $context, array $meta, string $deliveryMode): string
    {
        if (!empty($context['force_returned'])) {
            return 'returned';
        }
        if ($deliveryMode === 'immediate_in_hand') {
            return 'delivered';
        }

        $explicitStatus = '';
        if (isset($order['fulfillment_status'])) {
            $explicitStatus = (string) $order['fulfillment_status'];
        }
        if ($explicitStatus === '' && isset($order['shipping_info']) && is_array($order['shipping_info'])) {
            $explicitStatus = (string) ($order['shipping_info']['status'] ?? '');
        }
        if ($explicitStatus !== '') {
            $normalized = self::normalizeFulfillmentStatus($explicitStatus);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $status = self::normalizeOrderStatus((string) ($order['status'] ?? 'open'));
        if ($status === 'completed') {
            return 'delivered';
        }
        return 'pending';
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function derivePendingCodes(
        array $order,
        array $context,
        float $dueNow,
        float $balanceDueNow,
        float $dueLater,
        int $entriesCount,
        string $paymentStatus,
        string $fulfillmentStatus,
        string $deliveryMode
    ): array {
        $codes = [];
        $itemsCount = $this->extractItemsCount($order);
        if ($itemsCount <= 0) {
            $codes[] = 'no_items';
        }

        $paymentSettled = in_array($paymentStatus, ['paid', 'refunded', 'partially_refunded'], true);
        if ($dueNow > self::EPSILON && !$paymentSettled && $entriesCount <= 0) {
            $codes[] = 'missing_payment_method';
        }
        if ($balanceDueNow > self::EPSILON && !$paymentSettled) {
            $codes[] = 'payment_due_now';
        }
        if ($dueLater > self::EPSILON) {
            $codes[] = 'opening_fee_due_later';
        }

        $needsShippingAddress = !in_array($deliveryMode, ['immediate_in_hand', 'store_pickup'], true)
            && $fulfillmentStatus !== 'not_required';
        $isDelivered = in_array($fulfillmentStatus, ['delivered', 'returned', 'not_required'], true)
            || $deliveryMode === 'immediate_in_hand';
        if ($needsShippingAddress && !$isDelivered && $this->isShippingAddressMissing($order)) {
            $codes[] = 'shipping_address_missing';
        }

        if ($needsShippingAddress && !$isDelivered && $balanceDueNow <= self::EPSILON) {
            $codes[] = 'delivery_event_missing';
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function deriveNonBlockingCodes(array $context): array
    {
        $nonBlocking = ['opening_fee_due_later'];
        if (!empty($context['non_blocking_pending']) && is_array($context['non_blocking_pending'])) {
            foreach ($context['non_blocking_pending'] as $code) {
                $code = trim((string) $code);
                if ($code !== '') {
                    $nonBlocking[] = $code;
                }
            }
        }
        return array_values(array_unique($nonBlocking));
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, string> $pendingCodes
     * @param array<int, string> $blockingCodes
     */
    private function deriveOrderStatus(
        array $order,
        string $paymentStatus,
        string $fulfillmentStatus,
        array $pendingCodes,
        array $blockingCodes,
        float $balanceDueNow
    ): string {
        $rawStatus = self::normalizeOrderStatus((string) ($order['status'] ?? 'open'));

        // Terminal statuses: always preserved
        if (in_array($rawStatus, ['trash', 'deleted'], true)) {
            return $rawStatus;
        }
        if ($rawStatus === 'cancelled') {
            return 'cancelled';
        }

        // Full refund: order-level refunded
        if ($rawStatus === 'refunded' || $paymentStatus === 'refunded') {
            return 'refunded';
        }

        // No items yet: genuine draft
        if (in_array('no_items', $pendingCodes, true)) {
            return 'draft';
        }

        // Completed: payment settled + fulfillment done + no blocking issues
        $paymentSettled = in_array($paymentStatus, ['paid', 'none', 'partially_refunded'], true);
        $fulfillmentDone = in_array($fulfillmentStatus, ['delivered', 'not_required', 'returned'], true);
        if ($paymentSettled && $fulfillmentDone && empty($blockingCodes) && $balanceDueNow <= self::EPSILON) {
            return 'completed';
        }

        // Everything else is open (pending payment, partial payment,
        // partially refunded but not yet fulfilled, awaiting delivery, etc.)
        return 'open';
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, array{code:string,label:string}>
     */
    private function mapPendingDetails(array $codes): array
    {
        $items = [];
        foreach ($codes as $code) {
            $items[] = [
                'code' => $code,
                'label' => self::PENDING_LABELS[$code] ?? $code,
            ];
        }
        return $items;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, string>>
     */
    private function buildTimeline(array $order, array $entries, string $fulfillmentStatus, string $deliveryMode): array
    {
        $events = [];
        $createdAt = trim((string) ($order['date_created'] ?? ($order['ordered_at'] ?? ($order['created_at'] ?? ''))));
        if ($createdAt !== '') {
            $events[] = [
                'at' => $createdAt,
                'type' => 'created',
                'label' => 'Pedido criado',
            ];
        }

        foreach ($entries as $entry) {
            $amount = (float) ($entry['amount'] ?? 0);
            if (abs($amount) < self::EPSILON) {
                continue;
            }
            $method = trim((string) ($entry['method_name'] ?? 'Pagamento'));
            $absAmount = number_format(abs($amount), 2, ',', '.');
            $label = $amount < 0
                ? 'Reembolso: ' . $method . ' (R$ ' . $absAmount . ')'
                : 'Pagamento: ' . $method . ' (R$ ' . $absAmount . ')';
            $events[] = [
                'at' => (string) ($entry['occurred_at'] ?? ''),
                'type' => $amount < 0 ? 'refund' : 'payment',
                'label' => $label,
            ];
        }

        foreach ($this->extractShippingTimelineEvents($order) as $shippingEvent) {
            $events[] = $shippingEvent;
        }

        if ($deliveryMode === 'immediate_in_hand') {
            $events[] = [
                'at' => '',
                'type' => 'shipping',
                'label' => 'Entrega imediata em mãos',
            ];
        }

        if ($fulfillmentStatus === 'shipped') {
            $events[] = [
                'at' => '',
                'type' => 'shipping',
                'label' => 'Pedido enviado',
            ];
        } elseif ($fulfillmentStatus === 'delivered') {
            $events[] = [
                'at' => '',
                'type' => 'shipping',
                'label' => 'Pedido entregue',
            ];
        } elseif ($fulfillmentStatus === 'returned') {
            $events[] = [
                'at' => '',
                'type' => 'return',
                'label' => 'Pedido devolvido',
            ];
        }

        usort($events, static function (array $a, array $b): int {
            $atA = trim((string) ($a['at'] ?? ''));
            $atB = trim((string) ($b['at'] ?? ''));
            if ($atA === $atB) {
                return 0;
            }
            if ($atA === '') {
                return 1;
            }
            if ($atB === '') {
                return -1;
            }
            return strcmp($atA, $atB);
        });

        return $events;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function extractItemsCount(array $order): int
    {
        if (isset($order['items_count'])) {
            return max(0, (int) $order['items_count']);
        }
        $lineItems = $order['line_items'] ?? null;
        if (!is_array($lineItems)) {
            return 0;
        }
        $count = 0;
        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            $count += $qty > 0 ? $qty : 1;
        }
        return $count;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function isShippingAddressMissing(array $order): bool
    {
        $shipping = $order['shipping'] ?? [];
        if (!is_array($shipping)) {
            $shipping = [];
        }

        $address1 = trim((string) ($shipping['address_1'] ?? ($order['shipping_address_1'] ?? '')));
        $city = trim((string) ($shipping['city'] ?? ($order['shipping_city'] ?? '')));
        $state = trim((string) ($shipping['state'] ?? ($order['shipping_state'] ?? '')));
        return $address1 === '' || $city === '' || $state === '';
    }

    private function deriveDeliveryMode(array $order, array $meta): string
    {
        $shippingInfo = $order['shipping_info'] ?? [];
        if (!is_array($shippingInfo)) {
            $shippingInfo = [];
        }

        $rawMode = (string) ($order['delivery_mode'] ?? ($shippingInfo['delivery_mode'] ?? ($meta['retrato_delivery_mode'] ?? '')));
        return OrderService::normalizeDeliveryMode($rawMode);
    }

    private function deriveShipmentKind(array $order, array $meta, string $deliveryMode): ?string
    {
        $shippingInfo = $order['shipping_info'] ?? [];
        if (!is_array($shippingInfo)) {
            $shippingInfo = [];
        }
        $rawKind = (string) ($order['shipment_kind'] ?? ($shippingInfo['shipment_kind'] ?? ($meta['retrato_shipment_kind'] ?? '')));
        return OrderService::normalizeShipmentKind($rawKind, $deliveryMode);
    }

    /**
     * @param array<string, mixed> $order
     * @return array<int, array{at:string,type:string,label:string}>
     */
    private function extractShippingTimelineEvents(array $order): array
    {
        $shippingInfo = $order['shipping_info'] ?? null;
        if (!is_array($shippingInfo)) {
            return [];
        }
        $timeline = $shippingInfo['timeline'] ?? null;
        if (!is_array($timeline)) {
            return [];
        }

        $events = [];
        foreach ($timeline as $event) {
            if (!is_array($event)) {
                continue;
            }
            $label = trim((string) ($event['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $events[] = [
                'at' => trim((string) ($event['at'] ?? '')),
                'type' => trim((string) ($event['type'] ?? 'shipping')),
                'label' => $label,
            ];
        }

        return $events;
    }

    private function toMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $normalized = str_replace('.', '', $raw);
        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            return 0.0;
        }
        return (float) $normalized;
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private static function normalizeKey(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }
        $normalized = strtolower($normalized);
        if (function_exists('iconv')) {
            $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $normalized);
            if ($translit !== false) {
                $normalized = $translit;
            }
        }
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        $normalized = trim((string) $normalized);
        return str_replace(' ', '_', $normalized);
    }

    private function isTrueLike($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = self::normalizeKey((string) $value);
        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim', 'paid', 'pago'], true);
    }
}
