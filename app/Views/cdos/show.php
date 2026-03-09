<?php

declare(strict_types=1);

$cdo = is_array($cdo ?? null) ? $cdo : [];
$links = is_array($links ?? null) ? $links : [];
$availablePeople = is_array($availablePeople ?? null) ? $availablePeople : [];
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

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$statusLabel = static function (string $status): string {
    return match ($status) {
        'aberto' => 'Aberto',
        'parcial' => 'Parcial',
        'alocado' => 'Alocado',
        'encerrado' => 'Encerrado',
        'cancelado' => 'Cancelado',
        default => ucfirst($status),
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'aberto' => 'badge-info',
        'parcial' => 'badge-warning',
        'alocado' => 'badge-success',
        'encerrado', 'cancelado' => 'badge-neutral',
        default => 'badge-neutral',
    };
};

$personStatusLabel = static function (string $status): string {
    return match ($status) {
        'interessado' => 'Interessado/Triagem',
        'triagem' => 'Triagem',
        'selecionado' => 'Selecionado',
        'oficio_orgao' => 'Oficio orgao',
        'custos_recebidos' => 'Custos recebidos',
        'cdo' => 'CDO',
        'mgi' => 'MGI',
        'dou' => 'DOU',
        'ativo' => 'Ativo',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
};

$status = (string) ($cdo['status'] ?? '');
$isFinalStatus = in_array($status, ['encerrado', 'cancelado'], true);
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>CDO <?= e((string) ($cdo['number'] ?? '-')) ?></h2>
      <p class="muted">Periodo <?= e($formatDate((string) ($cdo['period_start'] ?? ''))) ?> a <?= e($formatDate((string) ($cdo['period_end'] ?? ''))) ?></p>
    </div>
    <div class="actions-inline">
      <span class="badge <?= e($statusBadgeClass($status)) ?>"><?= e($statusLabel($status)) ?></span>
      <a class="btn btn-outline" href="<?= e(url('/cdos')) ?>">Voltar</a>
      <?php if ($canManage): ?>
        <a class="btn btn-primary" href="<?= e(url('/cdos/edit?id=' . (int) ($cdo['id'] ?? 0))) ?>">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="details-grid">
    <div><strong>UG:</strong> <?= e((string) ($cdo['ug_code'] ?? '-')) ?></div>
    <div><strong>Acao:</strong> <?= e((string) ($cdo['action_code'] ?? '-')) ?></div>
    <div><strong>Criado em:</strong> <?= e($formatDateTime((string) ($cdo['created_at'] ?? ''))) ?></div>
    <div><strong>Criado por:</strong> <?= e((string) ($cdo['created_by_name'] ?? 'Nao informado')) ?></div>
    <div class="details-wide"><strong>Observacoes:</strong> <?= nl2br(e((string) ($cdo['notes'] ?? '-'))) ?></div>
  </div>
</div>

<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Valor total</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($cdo['total_amount'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Valor alocado</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($cdo['allocated_amount'] ?? 0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Saldo disponivel</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($cdo['available_amount'] ?? 0))) ?></p>
    <p class="dashboard-kpi-note">Pessoas vinculadas: <?= e((string) (int) ($cdo['linked_people_count'] ?? 0)) ?></p>
  </article>
</div>

<?php if ($canManage): ?>
  <div class="card">
    <div class="header-row">
      <div>
        <h3>Vincular pessoa</h3>
        <p class="muted">Bloqueado automaticamente quando o valor informado excede o saldo do CDO.</p>
      </div>
    </div>

    <?php if ($isFinalStatus): ?>
      <div class="empty-state">
        <p>CDO em status final (<?= e($statusLabel($status)) ?>). Nao e possivel alterar vinculos.</p>
      </div>
    <?php elseif ($availablePeople === []): ?>
      <div class="empty-state">
        <p>Sem pessoas disponiveis para novo vinculo neste CDO.</p>
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('/cdos/people/link')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="cdo_id" value="<?= e((string) ($cdo['id'] ?? 0)) ?>">

        <div class="field field-wide">
          <label for="person_id">Pessoa *</label>
          <select id="person_id" name="person_id" required>
            <option value="">Selecione uma pessoa</option>
            <?php foreach ($availablePeople as $person): ?>
              <option value="<?= e((string) ($person['id'] ?? 0)) ?>">
                <?= e((string) ($person['name'] ?? '')) ?>
                (<?= e((string) ($person['organ_name'] ?? '-')) ?> · <?= e($personStatusLabel((string) ($person['status'] ?? ''))) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="allocated_amount">Valor para vinculo (R$) *</label>
          <input id="allocated_amount" name="allocated_amount" type="text" placeholder="0,00" required>
        </div>

        <div class="field field-wide">
          <label for="notes">Observacoes</label>
          <textarea id="notes" name="notes" rows="3"></textarea>
        </div>

        <div class="form-actions field-wide">
          <button type="submit" class="btn btn-primary">Vincular pessoa</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Pessoas vinculadas</h3>
      <p class="muted">Vinculos ativos para este CDO com valor individual.</p>
    </div>
  </div>

  <?php if ($links === []): ?>
    <div class="empty-state">
      <p>Ainda nao ha pessoas vinculadas a este CDO.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pessoa</th>
            <th>Status pipeline</th>
            <th>Valor vinculado</th>
            <th>Registro</th>
            <th>Observacoes</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($links as $link): ?>
            <tr>
              <td>
                <a href="<?= e(url('/people/show?id=' . (int) ($link['person_id'] ?? 0))) ?>"><?= e((string) ($link['person_name'] ?? '-')) ?></a>
                <div class="muted"><?= e((string) ($link['organ_name'] ?? '-')) ?></div>
              </td>
              <td><?= e($personStatusLabel((string) ($link['person_status'] ?? ''))) ?></td>
              <td><?= e($formatMoney((float) ($link['allocated_amount'] ?? 0))) ?></td>
              <td>
                <?= e($formatDateTime((string) ($link['created_at'] ?? ''))) ?>
                <div class="muted">por <?= e((string) ($link['created_by_name'] ?? 'Nao informado')) ?></div>
              </td>
              <td><?= nl2br(e((string) ($link['notes'] ?? '-'))) ?></td>
              <td class="actions-cell">
                <?php if ($canManage && !$isFinalStatus): ?>
                  <form method="post" action="<?= e(url('/cdos/people/unlink')) ?>" onsubmit="return confirm('Remover vinculo desta pessoa com o CDO?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cdo_id" value="<?= e((string) ($cdo['id'] ?? 0)) ?>">
                    <input type="hidden" name="link_id" value="<?= e((string) ($link['id'] ?? 0)) ?>">
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
