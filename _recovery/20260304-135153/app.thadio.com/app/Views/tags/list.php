<?php
/** @var array $tags */
/** @var string $status */
/** @var string|null $connectionError */
/** @var callable $esc */
$tags = is_array($tags ?? null) ? $tags : [];
$status = (string) ($status ?? '');
?>
<section class="panel">
  <div class="panel-header">
    <h1>Tags</h1>
    <div class="panel-actions">
      <a class="btn primary" href="tag-cadastro.php">Nova tag</a>
      <a class="btn ghost" href="tag-list.php">Ativas</a>
      <a class="btn ghost" href="tag-list.php?status=trash">Lixeira</a>
    </div>
  </div>

  <?php if (!empty($connectionError)): ?>
    <div class="alert error"><?php echo $esc($connectionError); ?></div>
  <?php endif; ?>

  <?php if ($status === 'trash'): ?>
    <div class="alert info">A lixeira de tags não é persistida separadamente neste modelo.</div>
  <?php endif; ?>

  <p class="muted">Total de tags detectadas nos produtos: <strong><?php echo count($tags); ?></strong></p>

  <div class="table-wrap">
    <table class="table" data-table="interactive">
      <thead>
        <tr>
          <th data-sort-key="name" aria-sort="none">Nome da tag</th>
          <th data-sort-key="slug" aria-sort="none">Slug</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tags)): ?>
          <tr>
            <td colspan="2" class="muted">Nenhuma tag encontrada.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($tags as $tag): ?>
            <?php $name = (string) ($tag['name'] ?? ''); ?>
            <?php $slug = (string) ($tag['slug'] ?? $name); ?>
            <tr>
              <td><?php echo $esc($name); ?></td>
              <td><?php echo $esc($slug); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

