<?php
/** @var array $checks */
/** @var array $periodLocks */
/** @var array $errors */
/** @var callable $esc */

$locks = $periodLocks ?? [];
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Inconsistências & Controles</h1>
    <div class="subtitle">Verificação de integridade e gerenciamento de períodos.</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="btn ghost" href="consignacao-painel.php">← Painel</a>
    <?php if (userCan('consignment_module.admin_override')): ?>
      <a class="btn primary" href="consignacao-inconsistencias.php?action=legacy">🔗 Reconciliação Legados</a>
      <form method="post" action="consignacao-inconsistencias.php?action=reindex" style="display:inline;" onsubmit="return confirm('Reindexar consignment_status em todos os produtos?');">
        <button type="submit" class="btn warning">Reindexar produtos</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' | ', $errors)); ?></div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
  <?php if ($_GET['msg'] === 'reindexed'): ?>
    <div class="alert success">Reindexação concluída com sucesso.</div>
  <?php elseif ($_GET['msg'] === 'locked'): ?>
    <div class="alert success">Período bloqueado com sucesso.</div>
  <?php elseif ($_GET['msg'] === 'unlocked'): ?>
    <div class="alert success">Período desbloqueado com sucesso.</div>
  <?php endif; ?>
<?php endif; ?>

<!-- Integrity Checks -->
<h2 style="margin:24px 0 12px;">Verificação de integridade</h2>
<?php if (!empty($checks)): ?>
  <?php
    $hasIssues = false;
    foreach ($checks as $check) {
      if ((int) ($check['count'] ?? 0) > 0) { $hasIssues = true; break; }
    }
  ?>
  <?php if (!$hasIssues): ?>
    <div class="alert success">Todas as verificações passaram sem inconsistências.</div>
  <?php endif; ?>

  <?php foreach ($checks as $check): ?>
    <?php $hasCheckIssues = (int) ($check['count'] ?? 0) > 0; ?>
    <div style="margin:12px 0;padding:14px;border-radius:8px;background:<?php echo $hasCheckIssues ? '#fef2f2' : '#f0fdf4'; ?>;border:1px solid <?php echo $hasCheckIssues ? '#fecaca' : '#bbf7d0'; ?>;">
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:18px;"><?php echo $hasCheckIssues ? '⚠️' : '✅'; ?></span>
        <strong><?php echo $esc($check['label'] ?? $check['check'] ?? ''); ?></strong>
        <?php if (!empty($check['count'])): ?>
          <span class="badge <?php echo (int)$check['count'] > 0 ? 'danger' : 'success'; ?>"><?php echo (int)$check['count']; ?> item(ns)</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($check['description'])): ?>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;"><?php echo $esc($check['description']); ?></div>
      <?php endif; ?>
      <?php if ($hasCheckIssues): ?>
        <details style="margin-top:8px;">
          <summary style="cursor:pointer;font-size:13px;color:#dc2626;">Ver detalhes (<?php echo max((int) ($check['count'] ?? 0), count($check['issues'] ?? [])); ?>)</summary>
          <div style="margin-top:8px;overflow:auto;">
            <?php if (!empty($check['issues'])): ?>
              <table style="font-size:12px;width:100%;border-collapse:collapse;">
                <tbody>
                  <?php foreach ($check['issues'] as $issue): ?>
                    <tr style="border-bottom:1px solid #fecaca;">
                      <?php if (is_array($issue)): ?>
                        <?php foreach ($issue as $k => $v): ?>
                          <td style="padding:3px 6px;"><strong><?php echo $esc($k); ?>:</strong> <?php echo $esc((string)$v); ?></td>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <td style="padding:3px 6px;"><?php echo $esc((string)$issue); ?></td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div style="font-size:12px;color:#6b7280;">Detalhamento não disponível para este check nesta tela.</div>
            <?php endif; ?>
          </div>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="alert info">Nenhuma verificação executada. Execute a verificação de integridade para ver os resultados.</div>
<?php endif; ?>

<!-- Period Locks -->
<h2 style="margin:24px 0 12px;">Controle de períodos</h2>
<p style="font-size:13px;color:#6b7280;margin-bottom:12px;">
  Períodos bloqueados impedem criação/edição de pagamentos com data dentro do período.
</p>

<?php if (userCan('consignment_module.admin_override')): ?>
  <form method="post" action="consignacao-inconsistencias.php?action=period_lock" style="display:flex;gap:10px;align-items:end;margin-bottom:16px;">
    <div>
      <label style="font-size:13px;display:block;margin-bottom:4px;">Mês (YYYY-MM)</label>
      <input type="month" name="year_month" required style="min-width:160px;">
    </div>
    <div>
      <label style="font-size:13px;display:block;margin-bottom:4px;">Motivo</label>
      <input type="text" name="notes" placeholder="Ex: Fechamento mensal" style="min-width:250px;">
    </div>
    <button type="submit" class="btn primary">Bloquear período</button>
  </form>
<?php endif; ?>

<?php if (!empty($locks)): ?>
  <div class="table-scroll" data-table-scroll>
    <div class="table-scroll-top" aria-hidden="true"><div class="table-scroll-top-inner"></div></div>
    <div class="table-scroll-body">
      <table data-table="interactive">
        <thead>
          <tr>
            <th>Período</th>
            <th>Bloqueado em</th>
            <th>Por</th>
            <th>Motivo</th>
            <?php if (userCan('consignment_module.admin_override')): ?>
              <th>Ação</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($locks as $lock): ?>
            <tr>
              <td><strong><?php echo $esc($lock['year_month'] ?? ''); ?></strong></td>
              <td><?php echo !empty($lock['locked_at']) ? date('d/m/Y H:i', strtotime($lock['locked_at'])) : '<span style="color:var(--muted);">—</span>'; ?></td>
              <td><?php echo $esc($lock['locked_by_name'] ?? (string)($lock['locked_by'] ?? '')); ?></td>
              <td><?php echo $esc($lock['notes'] ?? ''); ?></td>
              <?php if (userCan('consignment_module.admin_override')): ?>
                <td>
                  <form method="post" action="consignacao-inconsistencias.php?action=period_unlock" style="display:inline-flex;gap:6px;align-items:center;" onsubmit="return confirm('Desbloquear este período?');">
                    <input type="hidden" name="year_month" value="<?php echo $esc($lock['year_month'] ?? ''); ?>">
                    <input type="text" name="reason" placeholder="Motivo da reabertura" required style="min-width:160px;font-size:12px;">
                    <button type="submit" class="btn ghost small">Desbloquear</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="alert info" style="margin-top:8px;">Nenhum período bloqueado.</div>
<?php endif; ?>
