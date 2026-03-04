<?php
/**
 * Orders Status Backfill — Recalcula order_status, payment_status e fulfillment_status
 * de todos os pedidos existentes usando OrderLifecycleService.
 *
 * Usage:
 *   php cli/orders_backfill_status.php [--dry-run] [--verbose] [--id=N]
 *
 * Options:
 *   --dry-run   Mostra mudanças sem gravar no banco
 *   --verbose   Exibe detalhes de cada pedido processado
 *   --id=N      Processa apenas o pedido com ID = N
 *
 * Contexto: Corrige pedidos que ficaram com status incorreto (ex: todos 'draft')
 * após mudanças na lógica de derivação de status do OrderLifecycleService.
 */

require __DIR__ . '/../bootstrap.php';

use App\Repositories\OrderRepository;
use App\Services\OrderLifecycleService;
use App\Services\OrderService;

// ── CLI args ────────────────────────────────────────────────
$dryRun  = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$onlyId  = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $onlyId = (int) substr($arg, 5);
    }
}

// ── Conexão ─────────────────────────────────────────────────
[$pdo, $err] = bootstrapPdo();
if (!$pdo) {
    fwrite(STDERR, "Erro de conexão: $err\n");
    exit(1);
}

$repo      = new OrderRepository($pdo);
$lifecycle = new OrderLifecycleService();

// ── Buscar pedidos ──────────────────────────────────────────
if ($onlyId) {
    $rows = [];
    $order = $repo->findOrderWithDetails($onlyId);
    if ($order) {
        $rows[] = $order;
    } else {
        fwrite(STDERR, "Pedido #{$onlyId} não encontrado.\n");
        exit(1);
    }
} else {
    // Buscar todos os IDs de pedidos não-arquivados/não-deletados
    $stmt = $pdo->query("SELECT id FROM orders WHERE status NOT IN ('trash','deleted') ORDER BY id ASC");
    $ids  = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $rows = [];
    foreach ($ids as $id) {
        $order = $repo->findOrderWithDetails((int) $id);
        if ($order) {
            $rows[] = $order;
        }
    }
}

$total     = count($rows);
$updated   = 0;
$skipped   = 0;
$errors    = 0;

$modeLabel = $dryRun ? '[DRY-RUN] ' : '';
echo "{$modeLabel}=== Orders Status Backfill ===\n";
echo "{$modeLabel}Pedidos a processar: {$total}\n\n";

// ── Processar cada pedido ───────────────────────────────────
foreach ($rows as $i => $order) {
    $orderId = (int) ($order['id'] ?? 0);
    $num     = $i + 1;

    if ($orderId <= 0) {
        $skipped++;
        continue;
    }

    $currentStatus            = OrderService::normalizeOrderStatus((string) ($order['status'] ?? 'open'));
    $currentPaymentStatus     = OrderService::normalizePaymentStatus((string) ($order['payment_status'] ?? 'none'));
    $currentFulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($order['fulfillment_status'] ?? 'pending'));

    // Pedidos cancelados/refunded: preservar (não recalcular)
    if (in_array($currentStatus, ['cancelled', 'trash', 'deleted'], true)) {
        if ($verbose) {
            echo "  [{$num}/{$total}] #{$orderId}: status={$currentStatus} — pulando (terminal)\n";
        }
        $skipped++;
        continue;
    }

    try {
        // Construir contexto mínimo (sem bag/opening-fee — esses são extras)
        $context = buildLifecycleContext($order);
        $snapshot = $lifecycle->computeSnapshot($order, $context);

        $newStatus            = OrderService::normalizeOrderStatus((string) ($snapshot['order_status'] ?? 'open'));
        $newPaymentStatus     = OrderService::normalizePaymentStatus((string) ($snapshot['payment_status'] ?? 'none'));
        $newFulfillmentStatus = OrderService::normalizeFulfillmentStatus((string) ($snapshot['fulfillment_status'] ?? 'pending'));

        // Se refunded foi aplicado manualmente, preservar
        if ($currentStatus === 'refunded' && $newStatus !== 'refunded') {
            $newStatus = 'refunded';
        }

        $changed = ($newStatus !== $currentStatus)
                || ($newPaymentStatus !== $currentPaymentStatus)
                || ($newFulfillmentStatus !== $currentFulfillmentStatus);

        if (!$changed) {
            if ($verbose) {
                echo "  [{$num}/{$total}] #{$orderId}: sem alteração (status={$currentStatus}, pay={$currentPaymentStatus}, ful={$currentFulfillmentStatus})\n";
            }
            $skipped++;
            continue;
        }

        // Montar detalhes da mudança
        $changes = [];
        if ($newStatus !== $currentStatus) {
            $changes[] = "status: {$currentStatus} → {$newStatus}";
        }
        if ($newPaymentStatus !== $currentPaymentStatus) {
            $changes[] = "payment: {$currentPaymentStatus} → {$newPaymentStatus}";
        }
        if ($newFulfillmentStatus !== $currentFulfillmentStatus) {
            $changes[] = "fulfillment: {$currentFulfillmentStatus} → {$newFulfillmentStatus}";
        }
        $changeStr = implode(' | ', $changes);

        if ($dryRun) {
            echo "  [{$num}/{$total}] #{$orderId}: {$changeStr}\n";
            $updated++;
            continue;
        }

        // Gravar no banco
        $payload = $lifecycle->toPersistencePayload($snapshot, $order);
        $payload['status'] = $newStatus;
        $repo->updateStatusComplete($orderId, $newStatus, null, $payload);

        echo "  [{$num}/{$total}] #{$orderId}: {$changeStr} ✓\n";
        $updated++;
    } catch (\Throwable $e) {
        fwrite(STDERR, "  [{$num}/{$total}] #{$orderId}: ERRO — {$e->getMessage()}\n");
        $errors++;
    }
}

// ── Resumo ──────────────────────────────────────────────────
echo "\n{$modeLabel}=== Resumo ===\n";
echo "  Total processados: {$total}\n";
echo "  Atualizados:       {$updated}\n";
echo "  Sem alteração:     {$skipped}\n";
echo "  Erros:             {$errors}\n";

if ($dryRun && $updated > 0) {
    echo "\n⚠  Modo --dry-run: nenhuma alteração foi gravada. Remova --dry-run para aplicar.\n";
}

exit($errors > 0 ? 1 : 0);

// ── Helpers ─────────────────────────────────────────────────

/**
 * Reproduz o buildLifecycleContext do OrderController para contexto do CLI.
 */
function buildLifecycleContext(array $order): array
{
    $context = [];
    $metaData = (array) ($order['meta_data'] ?? []);
    $meta = [];
    foreach ($metaData as $entry) {
        if (!is_array($entry)) continue;
        $key = $entry['key'] ?? null;
        if ($key !== null && $key !== '') {
            $meta[(string) $key] = $entry['value'] ?? null;
        }
    }

    $openingFeeDeferred = normalizeBoolLike($meta['retrato_opening_fee_deferred'] ?? null);
    $openingFeeValue    = normalizeMoney($meta['retrato_opening_fee_value'] ?? null);
    if ($openingFeeDeferred && $openingFeeValue > 0) {
        $context['opening_fee_due_later'] = $openingFeeValue;
    }
    $context['opening_fee_due_now'] = 0.0;

    return $context;
}

function normalizeBoolLike($value): bool
{
    if ($value === null || $value === '') return false;
    if (is_bool($value)) return $value;
    $str = strtolower(trim((string) $value));
    return in_array($str, ['1', 'true', 'on', 'yes', 'sim'], true);
}

function normalizeMoney($value): float
{
    if ($value === null || $value === '') return 0.0;
    if (is_numeric($value)) return (float) $value;
    $raw = trim((string) $value);
    $normalized = str_replace('.', '', $raw);
    $normalized = str_replace(',', '.', $normalized);
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}
