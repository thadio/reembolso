<?php
/** @var array $errors */
/** @var array|null $intake */
/** @var array $rows */
/** @var array $totals */
/** @var array $returns */
/** @var callable $esc */
?>
<?php
  $formatDate = function (?string $value): string {
    if (!$value) {
      return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
      return $value;
    }
    return date('d/m/Y', $ts);
  };
?>
<div class="print-actions">
  <button class="btn ghost" type="button" onclick="window.print()">Imprimir</button>
</div>

<h1>Termo de recebimento de consignação</h1>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if ($intake): ?>
  <div class="doc-meta">
    <div>
      <strong>Pre-lote</strong>
      #<?php echo (int) $intake['id']; ?>
    </div>
    <div>
      <strong>Fornecedor</strong>
      <?php echo $esc((string) ($intake['supplier_name'] ?? 'Sem fornecedor')); ?>
    </div>
    <div>
      <strong>Codigo fornecedor</strong>
      <?php echo $esc((string) ($intake['supplier_code'] ?? '-')); ?>
    </div>
    <div>
      <strong>Data de recebimento</strong>
      <?php echo $esc($formatDate($intake['received_at'] ?? null)); ?>
    </div>
    <div>
      <strong>Data de emissao</strong>
      <?php echo $esc(date('d/m/Y')); ?>
    </div>
  </div>

  <?php if (!empty($intake['notes'])): ?>
    <div class="doc-section">
      <strong>Observacoes</strong>
      <div style="margin-top:6px;white-space:pre-wrap;">
        <?php echo $esc((string) $intake['notes']); ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="doc-section">
    <h2 style="margin:0 0 10px;font-size:16px;">Resumo por categoria</h2>
    <table>
      <thead>
        <tr>
          <th>Categoria</th>
          <th>Qtd recebida</th>
          <th>Qtd devolvida</th>
          <th>Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="4">Nenhuma categoria registrada.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?php echo $esc($row['category']); ?></td>
              <td><?php echo number_format((int) $row['received'], 0, ',', '.'); ?></td>
              <td><?php echo number_format((int) $row['returned'], 0, ',', '.'); ?></td>
              <td><?php echo number_format((int) $row['remaining'], 0, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th>Total</th>
          <th><?php echo number_format((int) ($totals['received'] ?? 0), 0, ',', '.'); ?></th>
          <th><?php echo number_format((int) ($totals['returned'] ?? 0), 0, ',', '.'); ?></th>
          <th><?php echo number_format((int) ($totals['remaining'] ?? 0), 0, ',', '.'); ?></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <?php if (!empty($returns)): ?>
    <div class="doc-section">
      <h2 style="margin:0 0 10px;font-size:16px;">Aditamentos (devolucoes)</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Data</th>
            <th>Qtd devolvida</th>
            <th>Observacoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($returns as $return): ?>
            <tr>
              <td>#<?php echo (int) $return['id']; ?></td>
              <td><?php echo $esc($formatDate($return['returned_at'] ?? null)); ?></td>
              <td><?php echo number_format((int) ($return['total_returned'] ?? 0), 0, ',', '.'); ?></td>
              <td><?php echo $esc((string) ($return['notes'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="signature-grid">
    <div class="signature-line">Assinatura da fornecedora</div>
    <div class="signature-line">Assinatura da loja</div>
  </div>
<?php endif; ?>
