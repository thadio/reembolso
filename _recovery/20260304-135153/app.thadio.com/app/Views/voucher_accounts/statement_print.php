<?php
/** @var array|null $account */
/** @var array $statement */
/** @var array $errors */
/** @var array $period */
/** @var callable $esc */
?>
<?php
  $formatDate = static function ($value): string {
    if (!$value) {
      return '-';
    }
    $timestamp = strtotime((string) $value);
    if (!$timestamp) {
      return (string) $value;
    }
    return date('d/m/Y', $timestamp);
  };
  $formatMoney = static function ($value): string {
    $value = (float) $value;
    $prefix = $value < 0 ? '-R$ ' : 'R$ ';
    return $prefix . number_format(abs($value), 2, ',', '.');
  };
  $formatBalance = static function ($value): string {
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
  };
  $periodStart = $period['start'] ?? null;
  $periodEnd = $period['end'] ?? null;
  $periodLabel = 'Todo o periodo';
  if ($periodStart && $periodEnd) {
    $periodLabel = $formatDate($periodStart) . ' ate ' . $formatDate($periodEnd);
  } elseif ($periodStart) {
    $periodLabel = 'A partir de ' . $formatDate($periodStart);
  } elseif ($periodEnd) {
    $periodLabel = 'Ate ' . $formatDate($periodEnd);
  }
  $customerName = $account ? trim((string) ($account->customerName ?? '')) : '';
  $customerEmail = $account ? trim((string) ($account->customerEmail ?? '')) : '';
  $personId = $account ? (int) ($account->personId ?? 0) : 0;
  $customerLabel = $customerName !== '' ? $customerName : ($personId > 0 ? 'Pessoa #' . $personId : 'Pessoa');
  if ($customerEmail !== '') {
    $customerLabel .= ' - ' . $customerEmail;
  }
  $accountCode = $account ? trim((string) ($account->code ?? '')) : '';
?>
<div class="print-actions">
  <button class="btn ghost" type="button" onclick="window.print()">Imprimir</button>
</div>

<h1>Extrato de cupom/credito</h1>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<?php if ($account): ?>
  <div class="doc-meta">
    <div>
      <strong>Cliente</strong>
      <?php echo $esc($customerLabel); ?>
    </div>
    <div>
      <strong>ID pessoa</strong>
      <?php echo $personId > 0 ? $esc((string) $personId) : '-'; ?>
    </div>
    <div>
      <strong>Periodo</strong>
      <?php echo $esc($periodLabel); ?>
    </div>
    <div>
      <strong>Emitido em</strong>
      <?php echo $esc(date('d/m/Y')); ?>
    </div>
    <div>
      <strong>Saldo inicial</strong>
      <?php echo $esc($formatBalance($statement['opening_balance'] ?? 0)); ?>
    </div>
    <div>
      <strong>Saldo final</strong>
      <?php echo $esc($formatBalance($statement['current_balance'] ?? 0)); ?>
    </div>
    <?php if ($accountCode !== ''): ?>
      <div>
        <strong>Codigo do cupom</strong>
        <?php echo $esc($accountCode); ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($statement['error'])): ?>
    <div class="alert error"><?php echo $esc((string) $statement['error']); ?></div>
  <?php endif; ?>

  <div class="doc-section">
    <table>
      <thead>
        <tr>
          <th>Data</th>
          <th>Tipo</th>
          <th>Descricao</th>
          <th>Valor</th>
          <th>Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($statement['entries'])): ?>
          <tr><td colspan="5">Nenhum lancamento registrado.</td></tr>
        <?php else: ?>
          <?php foreach ($statement['entries'] as $entry): ?>
            <?php
              $entryType = (string) ($entry['type'] ?? '');
              $entryLabel = $entryType === 'debito' ? 'Debito' : 'Credito';
              $entryDate = $entry['date'] ?? null;
              $entryAmount = $entry['amount'] ?? 0;
              $entryBalance = $entry['balance'] ?? 0;
              $entryDesc = (string) ($entry['description'] ?? '');
            ?>
            <tr>
              <td><?php echo $esc($formatDate($entryDate)); ?></td>
              <td><?php echo $esc($entryLabel); ?></td>
              <td><?php echo $esc($entryDesc); ?></td>
              <td><?php echo $esc($formatMoney($entryAmount)); ?></td>
              <td><?php echo $esc($formatBalance($entryBalance)); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
