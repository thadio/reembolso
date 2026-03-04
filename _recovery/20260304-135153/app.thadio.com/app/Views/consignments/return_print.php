<?php
/** @var array $errors */
/** @var array|null $return */
/** @var array $rows */
/** @var int $totalReturned */
/** @var array $consolidatedRows */
/** @var array $consolidatedTotals */
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

<h1>Termo de devolução de consignação</h1>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if ($return): ?>
  <div class="doc-meta">
    <div>
      <strong>Devolucao</strong>
      #<?php echo (int) $return['id']; ?>
    </div>
    <div>
      <strong>Pre-lote</strong>
      #<?php echo (int) $return['intake_id']; ?>
    </div>
    <div>
      <strong>Fornecedor</strong>
      <?php echo $esc((string) ($return['supplier_name'] ?? 'Sem fornecedor')); ?>
    </div>
    <div>
      <strong>Data de recebimento</strong>
      <?php echo $esc($formatDate($return['received_at'] ?? null)); ?>
    </div>
    <div>
      <strong>Data da devolução</strong>
      <?php echo $esc($formatDate($return['returned_at'] ?? null)); ?>
    </div>
    <div>
      <strong>Data de emissao</strong>
      <?php echo $esc(date('d/m/Y')); ?>
    </div>
  </div>

  <?php if (!empty($return['notes'])): ?>
    <div class="doc-section">
      <strong>Observacoes</strong>
      <div style="margin-top:6px;white-space:pre-wrap;">
        <?php echo $esc((string) $return['notes']); ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="doc-section">
    <h2 style="margin:0 0 10px;font-size:16px;">Itens devolvidos</h2>
    <table>
      <thead>
        <tr>
          <th>Categoria</th>
          <th>Quantidade devolvida</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="2">Nenhuma categoria registrada.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?php echo $esc($row['category']); ?></td>
              <td><?php echo number_format((int) $row['quantity'], 0, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th>Total</th>
          <th><?php echo number_format((int) $totalReturned, 0, ',', '.'); ?></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="doc-section">
    <h2 style="margin:0 0 10px;font-size:16px;">Consolidado do recebimento</h2>
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
        <?php if (empty($consolidatedRows)): ?>
          <tr><td colspan="4">Nenhuma categoria registrada.</td></tr>
        <?php else: ?>
          <?php foreach ($consolidatedRows as $row): ?>
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
          <th><?php echo number_format((int) ($consolidatedTotals['received'] ?? 0), 0, ',', '.'); ?></th>
          <th><?php echo number_format((int) ($consolidatedTotals['returned'] ?? 0), 0, ',', '.'); ?></th>
          <th><?php echo number_format((int) ($consolidatedTotals['remaining'] ?? 0), 0, ',', '.'); ?></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="signature-grid">
    <div class="signature-line">Assinatura da fornecedora</div>
    <div class="signature-line">Assinatura da loja</div>
  </div>
<?php endif; ?>
