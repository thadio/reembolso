<?php
/** @var array $summary */
/** @var array $agingDist */
/** @var array $agingBySupplier */
/** @var array $pendingBySupplier */
/** @var int $recentOwnItemsCount */
/** @var int $legacyUnlinked */
/** @var array $periodLocks */
/** @var array $errors */
/** @var callable $esc */

use App\Controllers\ConsignmentModuleController;

$statusLabels = ConsignmentModuleController::consignmentStatusLabels();
$recentOwnItemsCount = (int) ($recentOwnItemsCount ?? 0);
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Painel de Consignação</h1>
    <div class="subtitle">Visão geral do módulo de consignação.</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if (userCan('consignment_module.create_payout')): ?>
      <a class="btn primary" href="consignacao-pagamento-cadastro.php">Novo Pagamento</a>
    <?php endif; ?>
    <?php if (userCan('consignment_module.view_products')): ?>
      <a class="btn ghost" href="consignacao-produtos.php">Produtos</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="dashboard-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin:24px 0;">
  <?php
  $cards = [
      ['label' => 'Em estoque', 'key' => 'em_estoque', 'color' => '#3b82f6'],
      ['label' => 'Vendido (pendente)', 'key' => 'vendido_pendente', 'color' => '#f59e0b'],
      ['label' => 'Vendido (pago)', 'key' => 'vendido_pago', 'color' => '#10b981'],
      ['label' => 'Próprio (pós-pgto)', 'key' => 'proprio_pos_pgto', 'color' => '#6b7280'],
      ['label' => 'Devolvido', 'key' => 'devolvido', 'color' => '#374151'],
      ['label' => 'Doado', 'key' => 'doado', 'color' => '#374151'],
  ];
  foreach ($cards as $card):
    $count = (int) ($summary[$card['key']]['count'] ?? 0);
    $value = (float) ($summary[$card['key']]['value'] ?? 0);
    $commission = (float) ($summary[$card['key']]['commission'] ?? 0);
    $isSoldCard = in_array($card['key'], ['vendido_pendente', 'vendido_pago'], true);
  ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;border-left:4px solid <?php echo $card['color']; ?>;">
      <div style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;"><?php echo $card['label']; ?></div>
      <div style="font-size:28px;font-weight:700;margin:8px 0;"><?php echo $count; ?></div>
      <?php if ($isSoldCard): ?>
        <div style="font-size:14px;color:#6b7280;">R$ <?php echo number_format($value, 2, ',', '.'); ?> (líquido)</div>
        <div style="font-size:13px;color:#9ca3af;">R$ <?php echo number_format($commission, 2, ',', '.'); ?> (comissão)</div>
      <?php elseif ($value > 0): ?>
        <div style="font-size:14px;color:#6b7280;">R$ <?php echo number_format($value, 2, ',', '.'); ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- Alerts -->
<?php if ($legacyUnlinked > 0 || $recentOwnItemsCount > 0): ?>
<div style="margin:24px 0;">
  <h3 style="margin-bottom:12px;">⚠ Alertas</h3>
  <?php if ($legacyUnlinked > 0): ?>
    <div class="alert warning" style="margin-bottom:8px;">
      <strong><?php echo $legacyUnlinked; ?></strong> pagamento(s) legado(s) sem vínculo a itens.
      <?php if (userCan('consignment_module.admin_override')): ?>
        <a href="consignacao-inconsistencias.php?action=legacy">Conciliar</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($recentOwnItemsCount > 0): ?>
    <div class="alert info">
      <strong><?php echo (int) $recentOwnItemsCount; ?></strong> item(ns) pago(s) retornaram como próprio(s) nos últimos 30 dias.
      <a href="consignacao-produtos.php?consignment_status=proprio_pos_pgto">Ver lista</a>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Pending by Supplier -->
<?php if (!empty($pendingBySupplier)): ?>
<div style="margin:24px 0;">
  <h3 style="margin-bottom:12px;">Fornecedoras com saldo pendente</h3>
  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>
    <div class="table-scroll-body">
      <table data-table="interactive">
      <thead>
        <tr>
          <th>Fornecedora</th>
          <th>Itens pendentes</th>
          <th>Vendido (líquido)</th>
          <th>Comissão pendente</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingBySupplier as $ps): ?>
          <tr>
            <td><?php echo $esc($ps['supplier_name'] ?? '(sem nome)'); ?></td>
            <td><?php echo (int) ($ps['pending_count'] ?? 0); ?></td>
            <td>R$ <?php echo number_format((float) ($ps['pending_net_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td>R$ <?php echo number_format((float) ($ps['pending_amount'] ?? 0), 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Aging Distribution -->
<?php if (!empty($agingDist)): ?>
<div style="margin:24px 0;">
  <h3 style="margin-bottom:12px;">Distribuição de Aging (dias em estoque)</h3>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <?php
      $agingBuckets = [
        ['key' => '0_30', 'label' => '0-30d'],
        ['key' => '31_60', 'label' => '31-60d'],
        ['key' => '61_90', 'label' => '61-90d'],
        ['key' => 'over_90', 'label' => '90+d'],
      ];
      foreach ($agingBuckets as $bucket):
        $bucketCount = (int) ($agingDist[$bucket['key']] ?? 0);
    ?>
      <div style="background:#f3f4f6;border-radius:8px;padding:16px 20px;min-width:120px;text-align:center;">
        <div style="font-size:13px;color:#6b7280;"><?php echo $esc($bucket['label']); ?></div>
        <div style="font-size:22px;font-weight:700;margin-top:4px;"><?php echo $bucketCount; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Aging by Supplier -->
<?php if (!empty($agingBySupplier)): ?>
<div style="margin:24px 0;">
  <h3 style="margin-bottom:12px;">Aging por Fornecedora (top 10)</h3>
  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>
    <div class="table-scroll-body">
      <table data-table="interactive">
      <thead>
        <tr>
          <th>Fornecedora</th>
          <th>Peças em estoque</th>
          <th>Dias médios</th>
          <th>Peças &gt; 90d</th>
          <th>Valor potencial</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($agingBySupplier as $as): ?>
          <tr>
            <td><?php echo $esc($as['supplier_name'] ?? '(sem nome)'); ?></td>
            <td><?php echo (int) ($as['count_in_stock'] ?? 0); ?></td>
            <td><?php echo round((float) ($as['avg_days'] ?? 0), 0); ?>d</td>
            <td><?php echo (int) ($as['count_over_90'] ?? 0); ?></td>
            <td>R$ <?php echo number_format((float) ($as['potential_value'] ?? 0), 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php endif; ?>
