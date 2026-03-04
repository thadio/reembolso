<?php
/** @var array $writeoff */
/** @var array|null $vendor */
/** @var array|null $product */
/** @var array $errors */
/** @var array $destinationOptions */
/** @var array $reasonOptions */
/** @var bool $isReturnTerm */
/** @var callable $esc */
?>
<?php
  $isReturnTerm = !empty($isReturnTerm);
  $destinationLabel = $destinationOptions[$writeoff['destination'] ?? ''] ?? ($writeoff['destination'] ?? '');
  $reasonLabel = $reasonOptions[$writeoff['reason'] ?? ''] ?? ($writeoff['reason'] ?? '');
  $productLabel = trim((string) ($product['name'] ?? $product['post_title'] ?? $writeoff['product_name'] ?? ''));
  if ($productLabel === '') {
    $fallbackSku = (string) ($writeoff['sku'] ?? $writeoff['product_sku'] ?? '—');
    $productLabel = 'Produto SKU ' . $fallbackSku;
  }
  $skuLabel = (string) ($writeoff['sku'] ?? $writeoff['product_sku'] ?? '—');
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
  <button type="button" onclick="window.print()" class="primary">Imprimir</button>
</div>

<?php if ($isReturnTerm): ?>
  <h1>Termo de devolução ao fornecedor</h1>
  <p>Documento para ciência do fornecedor sobre a devolução do produto listado abaixo.</p>
<?php else: ?>
  <h1>Termo de baixa de produto (consignação)</h1>
  <p>Documento para ciência do fornecedor sobre a baixa de item consignado.</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert error" role="alert"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

  <div class="doc-meta">
    <div><strong>Fornecedor</strong><?php echo $esc($vendor ? $vendor->fullName : ('Fornecedor #' . ($writeoff['supplier_pessoa_id'] ?? '—'))); ?></div>
    <div><strong>Produto</strong><?php echo $esc($productLabel); ?></div>
    <div><strong>SKU</strong><?php echo $esc($skuLabel); ?></div>
    <div><strong>Quantidade baixada</strong><?php echo $esc((string) ($writeoff['quantity'] ?? 0)); ?></div>
    <div><strong>Destinação</strong><?php echo $esc($destinationLabel); ?></div>
    <div><strong>Motivo</strong><?php echo $esc($reasonLabel); ?></div>
    <div><strong>Data</strong><?php echo $esc($formatDate($writeoff['created_at'] ?? null)); ?></div>
  </div>

<?php if ($isReturnTerm): ?>
  <div class="doc-section">
    <p>Declaro que estou ciente da devolução do produto acima, conforme registrado na disponibilidade. O item foi emitido de volta ao fornecedor conforme o motivo informado.</p>
  </div>
<?php else: ?>
  <div class="doc-section">
    <p>Declaro que estou ciente da baixa do produto acima, anteriormente fornecido em regime de consignação. A destinação escolhida segue os termos acordados e a quantidade indicada foi atualizada na disponibilidade.</p>
  </div>
<?php endif; ?>

<div class="signature-grid">
  <div class="signature-line">
    <div>Fornecedor</div>
  </div>
  <div class="signature-line">
    <div>Retrato Brechó</div>
  </div>
</div>
