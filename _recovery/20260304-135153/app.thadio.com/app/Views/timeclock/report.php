<?php
/** @var array $rows */
/** @var array $errors */
/** @var array $filters */
/** @var array $userOptions */
/** @var array $userTotals */
/** @var callable $formatDuration */
/** @var callable $esc */
?>
<?php
  $userNames = [];
  foreach ($rows as $row) {
    $userNames[$row['user_id']] = $row['user_name'];
  }
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Relatório de ponto</h1>
    <div class="subtitle">Apuração diária de jornada com base nos registros.</div>
  </div>
  <div class="actions">
    <a class="btn ghost" href="ponto-list.php">Voltar ao ponto</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<form method="get" class="table-tools" style="justify-content:flex-start;gap:8px;">
  <select name="status" aria-label="Filtrar status">
    <option value="aprovado" <?php echo $filters['status'] === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
    <option value="pendente" <?php echo $filters['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
    <option value="rejeitado" <?php echo $filters['status'] === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
    <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>Todos os status</option>
  </select>
  <input type="date" name="start" value="<?php echo $esc((string) $filters['start']); ?>" aria-label="Data inicial">
  <input type="date" name="end" value="<?php echo $esc((string) $filters['end']); ?>" aria-label="Data final">
  <select name="user_id" aria-label="Filtrar usuário">
    <option value="">Todos os usuários</option>
    <?php foreach ($userOptions as $user): ?>
      <option value="<?php echo (int) $user['id']; ?>" <?php echo (int) $filters['user_id'] === (int) $user['id'] ? 'selected' : ''; ?>>
        <?php echo $esc($user['full_name']); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn ghost" type="submit">Filtrar</button>
  <a class="btn ghost" href="ponto-relatorio.php">Limpar</a>
</form>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="user_name" aria-sort="none">Usuário</th>
        <th data-sort-key="date" aria-sort="none">Data</th>
        <th data-sort-key="first_entry" aria-sort="none">Primeira entrada</th>
        <th data-sort-key="last_exit" aria-sort="none">Última saída</th>
        <th data-sort-key="entry_count" aria-sort="none">Entradas</th>
        <th data-sort-key="exit_count" aria-sort="none">Saídas</th>
        <th data-sort-key="total_hours" aria-sort="none">Total (h)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="7">Nenhum dado encontrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td data-value="<?php echo $esc((string) $row['user_name']); ?>"><?php echo $esc((string) $row['user_name']); ?></td>
            <td data-value="<?php echo $esc((string) $row['date']); ?>"><?php echo $esc((string) $row['date']); ?></td>
            <td data-value="<?php echo $esc((string) $row['first_entry']); ?>"><?php echo $esc((string) $row['first_entry']); ?></td>
            <td data-value="<?php echo $esc((string) $row['last_exit']); ?>"><?php echo $esc((string) $row['last_exit']); ?></td>
            <td data-value="<?php echo (int) $row['entry_count']; ?>"><?php echo (int) $row['entry_count']; ?></td>
            <td data-value="<?php echo (int) $row['exit_count']; ?>"><?php echo (int) $row['exit_count']; ?></td>
            <td data-value="<?php echo $esc((string) $row['total_hours']); ?>">
              <strong><?php echo $esc((string) $row['total_hours']); ?></strong>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($userTotals)): ?>
  <div style="margin-top:18px;">
    <h2 style="margin:0 0 8px;">Total por usuário</h2>
    <div style="overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>Usuário</th>
            <th>Total (h)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($userTotals as $userId => $seconds): ?>
            <tr>
              <td><?php echo $esc($userNames[$userId] ?? ('Usuário #' . $userId)); ?></td>
              <td><strong><?php echo $esc($formatDuration((int) $seconds)); ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
