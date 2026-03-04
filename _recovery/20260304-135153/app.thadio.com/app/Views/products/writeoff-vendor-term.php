<?php
/** @var array $errors */
/** @var array<int, array<string, mixed>> $writeoffs */
/** @var array|null $vendor */
/** @var array<int, array<string, mixed>> $products */
/** @var array $destinationOptions */
/** @var array $reasonOptions */
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

  $supplierLabel = $vendor ? $vendor->fullName : ('Fornecedor #' . ($writeoffs[0]['supplier_pessoa_id'] ?? '—'));
  $totalQuantity = 0;
  $notes = [];
  foreach ($writeoffs as $item) {
    $quantity = (int) ($item['quantity'] ?? 0);
    $totalQuantity += $quantity;
    $noteText = trim((string) ($item['notes'] ?? ''));
    if ($noteText !== '') {
      $notes[] = [
        'id' => (int) ($item['id'] ?? 0),
        'value' => $noteText,
      ];
    }
  }
?>
<div class="print-actions">
  <button type="button" onclick="window.print()" class="primary">Imprimir</button>
</div>

<h1>Termo de devolução por fornecedor</h1>
<p>Documento que consolida as devoluções realizadas ao fornecedor nas baixas selecionadas.</p>

<?php if (!empty($errors)): ?>
  <div class="alert error" role="alert"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if (empty($writeoffs)): ?>
  <div class="alert warning">Nenhuma baixa registrada para este termo.</div>
<?php else: ?>
  <div class="doc-meta">
    <div><strong>Fornecedor</strong><?php echo $esc($supplierLabel); ?></div>
    <div><strong>Destinação</strong><?php echo $esc($destinationOptions['devolucao_fornecedor'] ?? 'Devolução para fornecedor'); ?></div>
    <div><strong>Data da emissão</strong><?php echo $esc(date('d/m/Y')); ?></div>
    <div><strong>Total de linhas</strong><?php echo $esc((string) count($writeoffs)); ?></div>
    <div><strong>Total devolvido</strong><?php echo $esc((string) $totalQuantity); ?></div>
  </div>

  <div class="doc-section">
    <table class="table-compact">
      <thead>
        <tr>
          <th>Produto</th>
          <th>SKU</th>
          <th>Quantidade</th>
          <th>Motivo</th>
          <th>Data</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($writeoffs as $item): ?>
          <?php
            $product = $products[$item['id']] ?? null;
            $productLabel = trim((string) ($product['name'] ?? $product['post_title'] ?? $item['product_name'] ?? ''));
            if ($productLabel === '') {
              $fallbackSku = (string) ($item['sku'] ?? $item['product_sku'] ?? '—');
              $productLabel = 'Produto SKU ' . $fallbackSku;
            }
            $skuLabel = (string) ($item['sku'] ?? $item['product_sku'] ?? '—');
          ?>
          <tr>
            <td><?php echo $esc($productLabel); ?></td>
            <td><?php echo $esc($skuLabel); ?></td>
            <td><?php echo $esc((string) ($item['quantity'] ?? 0)); ?></td>
            <td><?php echo $esc($reasonOptions[$item['reason']] ?? ($item['reason'] ?? '—')); ?></td>
            <td><?php echo $esc($formatDate($item['created_at'] ?? null)); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($notes)): ?>
    <div class="doc-section">
      <h2 style="margin-bottom:8px;">Observações</h2>
      <ul>
        <?php foreach ($notes as $note): ?>
          <li>#<?php echo (int) $note['id']; ?>: <?php echo $esc($note['value']); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="doc-section">
    <p>Declaro que estou ciente dos itens listados acima, que foram devolvidos ao fornecedor conforme as quantidades registradas.</p>
  </div>

  <div class="signature-grid">
    <div class="signature-line">
      <div>Fornecedor</div>
    </div>
    <div class="signature-line">
      <div>Retrato Brechó</div>
    </div>
  </div>
<?php endif; ?>
