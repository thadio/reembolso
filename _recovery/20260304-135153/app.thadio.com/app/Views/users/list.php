<?php
/** @var array $rows */
/** @var array $errors */
/** @var string $success */
/** @var callable $esc */
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Usuários</h1>
    <div class="subtitle">Listagem, edição e exclusão de usuários.</div>
  </div>
  <a class="btn primary" href="usuario-cadastro.php">Novo usuário</a>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php elseif (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="table-tools">
  <input type="search" data-filter-global placeholder="Buscar em todas as colunas" aria-label="Busca geral em usuários">
  <span style="color:var(--muted);font-size:13px;">Clique nos cabeçalhos para ordenar</span>
</div>

<div style="overflow:auto;">
  <table data-table="interactive">
    <thead>
      <tr>
        <th data-sort-key="id" aria-sort="none">ID</th>
        <th data-sort-key="full_name" aria-sort="none">Nome</th>
        <th data-sort-key="email" aria-sort="none">E-mail</th>
        <th data-sort-key="phone" aria-sort="none">Telefone</th>
        <th data-sort-key="profile_name" aria-sort="none">Perfil</th>
        <th data-sort-key="status" aria-sort="none">Status</th>
        <th class="col-actions">Ações</th>
      </tr>
      <tr class="filters-row">
        <th><input type="search" data-filter-col="id" placeholder="Filtrar ID" aria-label="Filtrar ID"></th>
        <th><input type="search" data-filter-col="full_name" placeholder="Filtrar nome" aria-label="Filtrar nome"></th>
        <th><input type="search" data-filter-col="email" placeholder="Filtrar e-mail" aria-label="Filtrar e-mail"></th>
        <th><input type="search" data-filter-col="phone" placeholder="Filtrar telefone" aria-label="Filtrar telefone"></th>
        <th><input type="search" data-filter-col="profile_name" placeholder="Filtrar perfil" aria-label="Filtrar perfil"></th>
        <th><input type="search" data-filter-col="status" placeholder="Filtrar status" aria-label="Filtrar status"></th>
        <th class="col-actions"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="no-results"><td colspan="7">Nenhum usuário cadastrado.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php $rowLink = 'usuario-cadastro.php?id=' . (int) $row['id']; ?>
          <tr<?php echo ' data-row-href="' . $esc($rowLink) . '"'; ?>>
            <td data-value="<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></td>
            <td data-value="<?php echo $esc($row['full_name']); ?>"><?php echo $esc($row['full_name']); ?></td>
            <td data-value="<?php echo $esc($row['email']); ?>"><?php echo $esc($row['email']); ?></td>
            <td data-value="<?php echo $esc($row['phone'] ?? ''); ?>"><?php echo $esc($row['phone'] ?? ''); ?></td>
            <td data-value="<?php echo $esc($row['profile_name'] ?? $row['role'] ?? ''); ?>">
              <?php echo $esc($row['profile_name'] ?? $row['role'] ?? ''); ?>
            </td>
            <td data-value="<?php echo $esc($row['status']); ?>"><?php echo $esc($row['status']); ?></td>
            <td class="col-actions">
              <div class="actions">
                <form method="post" onsubmit="return confirm('Excluir este usuário?');" style="margin:0;">
                  <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                  <button class="icon-btn danger" type="submit" aria-label="Excluir" title="Excluir">
                    <svg aria-hidden="true"><use href="#icon-trash"></use></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
