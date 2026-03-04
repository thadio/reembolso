<?php
/** @var array $aging */
/** @var array $ranking */
/** @var array $ownItems */
/** @var array $legacyAnalysis */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var array|null $periodBadge */
/** @var callable $esc */
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Relatório Interno</h1>
    <div class="subtitle">Visão gerencial do módulo de consignação.</div>
    <?php if (!empty($periodBadge)): ?>
      <div style="margin-top:6px;">
        <span class="badge <?php echo $esc((string) ($periodBadge['badge'] ?? 'secondary')); ?>">
          <?php echo $esc((string) ($periodBadge['label'] ?? '')); ?> (<?php echo $esc((string) ($periodBadge['year_month'] ?? '')); ?>)
        </span>
      </div>
    <?php endif; ?>
  </div>
  <a class="btn ghost" href="consignacao-painel.php">← Painel</a>
</div>

<!-- Filters -->
<div class="table-tools">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <form method="get" action="consignacao-relatorio-interno.php" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <input type="date" name="date_from" value="<?php echo $esc($dateFrom); ?>" title="Data de" aria-label="Data de">
      <input type="date" name="date_to" value="<?php echo $esc($dateTo); ?>" title="Data até" aria-label="Data até">
      <button type="submit" class="btn primary">Atualizar</button>
    </form>
  </div>
</div>

<!-- Aging Analysis -->
<h2 style="margin:24px 0 12px;">Aging — Distribuição de peças em estoque</h2>
<?php if (!empty($aging)): ?>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <?php
      $normalizedAging = [];
      if (array_keys($aging) !== range(0, count($aging) - 1)) {
        $labelMap = [
          '0_30' => '0-30d',
          '31_60' => '31-60d',
          '61_90' => '61-90d',
          'over_90' => '90+d',
        ];
        foreach ($labelMap as $key => $label) {
          $normalizedAging[] = [
            'label' => $label,
            'count' => (int) ($aging[$key] ?? 0),
            'total_value' => 0.0,
          ];
        }
      } else {
        $normalizedAging = $aging;
      }
    ?>
    <?php foreach ($normalizedAging as $bucket): ?>
      <div style="flex:1;min-width:130px;background:#f3f4f6;border-radius:8px;padding:14px;text-align:center;">
        <div style="font-size:12px;color:#6b7280;"><?php echo $esc($bucket['label'] ?? $bucket['bracket'] ?? ''); ?></div>
        <div style="font-size:24px;font-weight:700;"><?php echo (int)($bucket['count'] ?? 0); ?></div>
        <div style="font-size:12px;color:#9ca3af;">R$ <?php echo number_format((float)($bucket['total_value'] ?? 0), 2, ',', '.'); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="alert info">Sem dados de aging.</div>
<?php endif; ?>

<!-- Supplier Ranking -->
<h2 style="margin:24px 0 12px;">Ranking de fornecedoras</h2>
<?php if (!empty($ranking)): ?>
  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>
    <div class="table-scroll-body">
      <table data-table="interactive">
        <thead>
          <tr>
            <th data-sort-key="rank" aria-sort="none">#</th>
            <th data-sort-key="full_name" aria-sort="none">Fornecedora</th>
            <th data-sort-key="total_received" aria-sort="none">Recebidos</th>
            <th data-sort-key="total_sold" aria-sort="none">Vendidos</th>
            <th data-sort-key="conversion_rate" aria-sort="none">Conversão</th>
            <th data-sort-key="total_returned" aria-sort="none">Devolvidos</th>
            <th data-sort-key="total_revenue" aria-sort="none">Receita total</th>
            <th data-sort-key="avg_aging" aria-sort="none">Aging médio</th>
          </tr>
        </thead>
        <tbody>
          <?php $rank = 0; foreach ($ranking as $r): $rank++; ?>
            <tr>
              <td><?php echo $rank; ?></td>
              <td><?php echo $esc($r['full_name'] ?? '(sem nome)'); ?></td>
              <td><?php echo (int)($r['total_received'] ?? 0); ?></td>
              <td><?php echo (int)($r['total_sold'] ?? 0); ?></td>
              <td>
                <?php
                  $recv = (int)($r['total_received'] ?? 0);
                  $sold = (int)($r['total_sold'] ?? 0);
                  echo $recv > 0 ? number_format(($sold / $recv) * 100, 1) . '%' : '<span style="color:var(--muted);">—</span>';
                ?>
              </td>
              <td><?php echo (int)($r['total_returned'] ?? 0); ?></td>
              <td>R$ <?php echo number_format((float)($r['total_revenue'] ?? 0), 2, ',', '.'); ?></td>
              <td><?php echo (int)($r['avg_aging'] ?? 0); ?>d</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="alert info">Sem dados de ranking.</div>
<?php endif; ?>

<!-- Own items sold as consignment -->
<h2 style="margin:24px 0 12px;">Peças próprias vendidas como consignação</h2>
<p style="font-size:13px;color:#6b7280;">Produtos com source='proprio' (ou vazio) que possuem vendas consignadas — possível erro de classificação.</p>
<?php if (!empty($ownItems)): ?>
  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>
    <div class="table-scroll-body">
      <table data-table="interactive">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Produto</th>
            <th>Source</th>
            <th>Fornecedora</th>
            <th>Consignment Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ownItems as $oi): ?>
            <tr>
              <td><a href="produto-cadastro.php?id=<?php echo (int)($oi['id'] ?? 0); ?>"><?php echo $esc($oi['sku'] ?? ''); ?></a></td>
              <td><?php echo $esc($oi['name'] ?? ''); ?></td>
              <td><?php echo $esc($oi['source'] ?? '(vazio)'); ?></td>
              <td><?php echo $esc($oi['supplier_name'] ?? '—'); ?></td>
              <td><?php echo $esc($oi['consignment_status'] ?? '—'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="alert success" style="margin-top:8px;">Nenhuma peça própria identificada com vendas de consignação.</div>
<?php endif; ?>

<!-- Legacy Analysis -->
<h2 style="margin:24px 0 12px;">Análise de pagamentos legados</h2>
<p style="font-size:13px;color:#6b7280;">Movimentações de pagamento a fornecedoras anteriores ao módulo de consignação.</p>
<?php if (!empty($legacyAnalysis)): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:12px 0;">
    <div style="background:#f3f4f6;border-radius:8px;padding:14px;">
      <div style="font-size:12px;color:#6b7280;">Total movimentações</div>
      <div style="font-size:22px;font-weight:700;"><?php echo (int)($legacyAnalysis['total_movements'] ?? 0); ?></div>
    </div>
    <div style="background:#f3f4f6;border-radius:8px;padding:14px;">
      <div style="font-size:12px;color:#6b7280;">Não vinculadas</div>
      <div style="font-size:22px;font-weight:700;color:#dc2626;"><?php echo (int)($legacyAnalysis['unlinked_count'] ?? 0); ?></div>
    </div>
    <div style="background:#f3f4f6;border-radius:8px;padding:14px;">
      <div style="font-size:12px;color:#6b7280;">Valor não vinculado</div>
      <div style="font-size:18px;font-weight:700;">R$ <?php echo number_format((float)($legacyAnalysis['unlinked_value'] ?? 0), 2, ',', '.'); ?></div>
    </div>
    <div style="background:#d1fae5;border-radius:8px;padding:14px;">
      <div style="font-size:12px;color:#6b7280;">Já vinculadas</div>
      <div style="font-size:22px;font-weight:700;color:#065f46;"><?php echo (int)($legacyAnalysis['linked_count'] ?? 0); ?></div>
    </div>
  </div>
<?php else: ?>
  <div class="alert info">Nenhum dado de legado disponível.</div>
<?php endif; ?>
