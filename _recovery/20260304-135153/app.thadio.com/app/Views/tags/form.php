<?php
/** @var string $name */
/** @var string|null $notice */
/** @var string $noticeType */
/** @var string|null $connectionError */
/** @var callable $esc */
$name = (string) ($name ?? '');
$notice = $notice ?? null;
$noticeType = (string) ($noticeType ?? 'info');
?>
<section class="panel">
  <div class="panel-header">
    <h1>Cadastro de Tag</h1>
    <div class="panel-actions">
      <a class="btn ghost" href="tag-list.php">Voltar para lista</a>
    </div>
  </div>

  <?php if (!empty($connectionError)): ?>
    <div class="alert error"><?php echo $esc($connectionError); ?></div>
  <?php endif; ?>

  <?php if (!empty($notice)): ?>
    <div class="alert <?php echo $noticeType === 'error' ? 'error' : 'success'; ?>">
      <?php echo $esc($notice); ?>
    </div>
  <?php endif; ?>

  <p class="muted">As tags são refletidas a partir dos produtos cadastrados no sistema.</p>

  <form method="post" class="grid" style="grid-template-columns:1fr;">
    <div class="field">
      <label for="name">Nome da tag</label>
      <input
        type="text"
        id="name"
        name="name"
        maxlength="80"
        value="<?php echo $esc($name); ?>"
        placeholder="Ex.: vintage"
      >
    </div>
    <div class="footer">
      <button type="submit" class="btn primary">Validar uso da tag</button>
    </div>
  </form>
</section>

