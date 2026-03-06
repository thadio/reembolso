<?php

declare(strict_types=1);

$item = is_array($item ?? null) ? $item : [];
$itemId = (int) ($item['id'] ?? 0);

$linkageLabel = static function (int $code): string {
    return $code === 510
        ? 'Beneficios e auxilios (custeio) (510)'
        : 'Remuneracao (309)';
};

$reimbursableLabel = static function (int $flag): string {
    return $flag === 1 ? 'Parcela reembolsavel' : 'Parcela nao-reembolsavel';
};

$periodicityLabel = static function (string $value): string {
    return match ($value) {
        'mensal' => 'Mensal',
        'anual' => 'Anual',
        'unico' => 'Unico',
        default => ucfirst($value),
    };
};
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($item['name'] ?? 'Item de custo')) ?></h2>
      <p class="muted">Dados do catalogo de item de custo utilizado no planejamento financeiro.</p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url('/cost-items')) ?>">Voltar</a>
      <?php if (($canManage ?? false) === true): ?>
        <a class="btn btn-primary" href="<?= e(url('/cost-items/edit?id=' . $itemId)) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>Vinculo:</strong> <?= e($linkageLabel((int) ($item['linkage_code'] ?? 309))) ?></div>
    <div><strong>Parcela:</strong> <?= e($reimbursableLabel((int) ($item['is_reimbursable'] ?? 0))) ?></div>
    <div><strong>Periodicidade:</strong> <?= e($periodicityLabel((string) ($item['payment_periodicity'] ?? ''))) ?></div>
    <div><strong>Cadastro:</strong> <?= e((string) ($item['created_at'] ?? '-')) ?></div>
    <div><strong>Atualizacao:</strong> <?= e((string) ($item['updated_at'] ?? '-')) ?></div>
  </div>

  <?php if (($canManage ?? false) === true): ?>
    <form method="post" action="<?= e(url('/cost-items/delete')) ?>" class="actions-inline" onsubmit="return confirm('Confirmar remocao deste item de custo?');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string) $itemId) ?>">
      <button type="submit" class="btn btn-danger">Excluir item</button>
    </form>
  <?php endif; ?>
</div>
