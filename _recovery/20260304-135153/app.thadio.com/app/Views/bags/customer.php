<?php
/** @var array $rows */
/** @var array $errors */
/** @var int $pessoaId */
/** @var array $statusOptions */
/** @var callable $esc */
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Histórico de sacolinhas</h1>
    <div class="subtitle">Cliente #<?php echo (int) $pessoaId; ?></div>
  </div>
  <a class="btn ghost" href="sacolinha-list.php">Voltar para sacolinhas</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div style="overflow:auto;margin-top:12px;">
  <table>
    <thead>
      <tr>
        <th>Número</th>
        <th>Status</th>
        <th>Itens</th>
        <th>Total</th>
        <th>Abertura</th>
        <th>Fechamento previsto</th>
        <th>Taxa</th>
        <th class="col-actions">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8">Nenhuma sacolinha encontrada para esta cliente.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $itemsQty = (int) ($row['items_qty'] ?? 0);
            $itemsTotal = number_format((float) ($row['items_total'] ?? 0), 2, ',', '.');
            $openedAt = $row['opened_at'] ? date('d/m/Y', strtotime($row['opened_at'])) : '-';
            $expectedClose = $row['expected_close_at'] ? date('d/m/Y', strtotime($row['expected_close_at'])) : '-';
            $feeValue = number_format((float) ($row['opening_fee_value'] ?? 0), 2, ',', '.');
            $feePaid = !empty($row['opening_fee_paid']) ? 'Pago' : 'Pendente';
            $statusLabel = $statusOptions[$row['status'] ?? ''] ?? ($row['status'] ?? '');
          ?>
          <?php $rowLink = 'sacolinha-cadastro.php?id=' . (int) $row['id']; ?>
          <tr<?php echo ' data-row-href="' . $esc($rowLink) . '"'; ?>>
            <td>#<?php echo (int) $row['id']; ?></td>
            <td><span class="pill"><?php echo $esc($statusLabel); ?></span></td>
            <td><?php echo $itemsQty; ?></td>
            <td>R$ <?php echo $esc($itemsTotal); ?></td>
            <td><?php echo $esc($openedAt); ?></td>
            <td><?php echo $esc($expectedClose); ?></td>
            <td><?php echo $esc($feePaid); ?> (R$ <?php echo $esc($feeValue); ?>)</td>
            <td class="col-actions">
              <span class="muted">—</span>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
