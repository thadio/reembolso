<?php

declare(strict_types=1);

$mirror = is_array($mirror ?? null) ? $mirror : [];
$items = is_array($items ?? null) ? $items : [];
$sourceLabel = is_callable($sourceLabel ?? null)
    ? $sourceLabel
    : static fn (string $source): string => ucfirst($source);
$canManage = (bool) ($canManage ?? false);

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
};

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
};

$formatMonth = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('m/Y', $timestamp);
};

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$statusLabel = static function (string $status): string {
    return match ($status) {
        'aberto' => 'Aberto',
        'conferido' => 'Conferido',
        'conciliado' => 'Conciliado',
        default => ucfirst($status),
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'aberto' => 'badge-info',
        'conferido' => 'badge-warning',
        'conciliado' => 'badge-success',
        default => 'badge-neutral',
    };
};

$status = (string) ($mirror['status'] ?? '');
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2><?= e((string) ($mirror['title'] ?? 'Espelho')) ?></h2>
      <p class="muted">Pessoa <?= e((string) ($mirror['person_name'] ?? '-')) ?> · Competencia <?= e($formatMonth((string) ($mirror['reference_month'] ?? ''))) ?></p>
    </div>
    <div class="actions-inline">
      <span class="badge <?= e($statusBadgeClass($status)) ?>"><?= e($statusLabel($status)) ?></span>
      <a class="btn btn-outline" href="<?= e(url('/cost-mirrors')) ?>">Voltar</a>
      <?php if ($canManage): ?>
        <a class="btn btn-primary" href="<?= e(url('/cost-mirrors/edit?id=' . (int) ($mirror['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div>
      <strong>Pessoa:</strong>
      <a href="<?= e(url('/people/show?id=' . (int) ($mirror['person_id'] ?? 0))) ?>">
        <?= e((string) ($mirror['person_name'] ?? '-')) ?>
      </a>
    </div>
    <div><strong>Orgao:</strong> <?= e((string) ($mirror['organ_name'] ?? '-')) ?></div>
    <div><strong>Competencia:</strong> <?= e($formatMonth((string) ($mirror['reference_month'] ?? ''))) ?></div>
    <div><strong>Fonte:</strong> <?= e($sourceLabel((string) ($mirror['source'] ?? 'manual'))) ?></div>
    <div><strong>Criado em:</strong> <?= e($formatDateTime((string) ($mirror['created_at'] ?? ''))) ?></div>
    <div><strong>Criado por:</strong> <?= e((string) ($mirror['created_by_name'] ?? 'N/I')) ?></div>
    <div>
      <strong>Boleto vinculado:</strong>
      <?php if (!empty($mirror['invoice_id'])): ?>
        <a href="<?= e(url('/invoices/show?id=' . (int) ($mirror['invoice_id'] ?? 0))) ?>">
          <?= e((string) ($mirror['invoice_number'] ?? '-')) ?>
        </a>
      <?php else: ?>
        <span class="muted">Nao vinculado</span>
      <?php endif; ?>
    </div>
    <div><strong>Ultima atualizacao:</strong> <?= e($formatDateTime((string) ($mirror['updated_at'] ?? ''))) ?></div>
    <div class="details-wide"><strong>Observacoes:</strong> <?= nl2br(e((string) ($mirror['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Total espelho</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($mirror['total_amount'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Itens cadastrados</p>
    <p class="kpi-value"><?= e((string) (int) ($mirror['items_count'] ?? 0)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Ultima competencia</p>
    <p class="kpi-value"><?= e($formatMonth((string) ($mirror['reference_month'] ?? ''))) ?></p>
  </article>
</div>

<?php if ($canManage): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Adicionar item manual</h3>
        <p class="muted">Preencha valor total ou quantidade + valor unitario.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/cost-mirrors/items/store')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="cost_mirror_id" value="<?= e((string) ($mirror['id'] ?? 0)) ?>">

      <div class="field field-wide">
        <label for="item_name">Nome do item *</label>
        <input id="item_name" name="item_name" type="text" minlength="3" maxlength="190" required placeholder="Ex.: Auxilio transporte">
      </div>

      <div class="field">
        <label for="item_code">Codigo/rubrica</label>
        <input id="item_code" name="item_code" type="text" maxlength="80" placeholder="Ex.: VT-001">
      </div>

      <div class="field">
        <label for="quantity">Quantidade *</label>
        <input id="quantity" name="quantity" type="text" value="1" placeholder="1">
      </div>

      <div class="field">
        <label for="unit_amount">Valor unitario (R$)</label>
        <input id="unit_amount" name="unit_amount" type="text" placeholder="0,00">
      </div>

      <div class="field">
        <label for="amount">Valor total (R$)</label>
        <input id="amount" name="amount" type="text" placeholder="0,00">
      </div>

      <div class="field field-wide">
        <label for="notes">Observacoes</label>
        <textarea id="notes" name="notes" rows="3"></textarea>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Adicionar item</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="header-row">
      <div>
        <h3>Importar itens por CSV</h3>
        <p class="muted">Cabecalhos aceitos: item_name, amount, quantity, unit_amount, item_code, notes.</p>
      </div>
    </div>

    <form method="post" action="<?= e(url('/cost-mirrors/items/import-csv')) ?>" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="cost_mirror_id" value="<?= e((string) ($mirror['id'] ?? 0)) ?>">

      <div class="field field-wide">
        <label for="csv_file">Arquivo CSV *</label>
        <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv,text/plain" required>
      </div>

      <div class="field field-wide">
        <p class="muted">
          Exemplo: <code>item_name;amount;quantity;unit_amount;item_code;notes</code>
        </p>
      </div>

      <div class="form-actions field-wide">
        <button type="submit" class="btn btn-primary">Importar CSV</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Itens do espelho</h3>
      <p class="muted">Detalhamento item a item desta competencia.</p>
    </div>
  </div>

  <?php if ($items === []): ?>
    <div class="empty-state">
      <p>Ainda nao ha itens cadastrados neste espelho.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Codigo</th>
            <th>Qtd.</th>
            <th>Unitario</th>
            <th>Total</th>
            <th>Observacoes</th>
            <th>Registro</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e((string) ($item['item_name'] ?? '-')) ?></td>
              <td><?= e((string) ($item['item_code'] ?? '-')) ?></td>
              <td><?= e((string) ($item['quantity'] ?? '0.00')) ?></td>
              <td><?= e($formatMoney((float) ($item['unit_amount'] ?? 0))) ?></td>
              <td><?= e($formatMoney((float) ($item['amount'] ?? 0))) ?></td>
              <td><?= nl2br(e((string) ($item['notes'] ?? '-'))) ?></td>
              <td>
                <?= e($formatDate((string) ($item['created_at'] ?? ''))) ?>
                <div class="muted">por <?= e((string) ($item['created_by_name'] ?? 'N/I')) ?></div>
              </td>
              <td class="actions-cell">
                <?php if ($canManage): ?>
                  <form method="post" action="<?= e(url('/cost-mirrors/items/delete')) ?>" onsubmit="return confirm('Remover este item do espelho?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cost_mirror_id" value="<?= e((string) ($mirror['id'] ?? 0)) ?>">
                    <input type="hidden" name="item_id" value="<?= e((string) ($item['id'] ?? 0)) ?>">
                    <button type="submit" class="btn btn-danger">Remover</button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
