<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var callable $esc */
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Cadastro de Regra</h1>
      <div class="subtitle">Registre regras e instrucoes para comunicados do negocio.</div>
    </div>
    <span class="pill">
      <?php echo $editing ? 'Editando #' . $esc((string) $formData['id']) : 'Nova regra'; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="regra-cadastro.php">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <div class="grid">
      <div class="field">
        <label for="title">Título *</label>
        <input type="text" id="title" name="title" required maxlength="200" value="<?php echo $esc($formData['title']); ?>">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="ativo" <?php echo $formData['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
          <option value="inativo" <?php echo $formData['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
        </select>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="content">Conteudo *</label>
        <textarea id="content" name="content" rows="12" required><?php echo $esc($formData['content']); ?></textarea>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="observations">
          Observações da versão
          <?php if ($observationRequired): ?>
            <span class="muted" style="margin-left:6px;">(obrigatório para versões seguintes)</span>
          <?php endif; ?>
        </label>
        <textarea id="observations" name="observations" rows="3" <?php echo $observationRequired ? 'required' : ''; ?>><?php echo $esc($formData['observations']); ?></textarea>
        <div class="muted">
          <?php echo $observationRequired ? 'Explique brevemente o ajuste feito em relação à versão anterior.' : 'Registre uma nota rápida sobre o que foi criado ou alterado nesta nova versão.'; ?>
        </div>
      </div>
    </div>

    <div class="footer">
      <button class="ghost" type="reset">Limpar</button>
      <button class="primary" type="submit">Salvar regra</button>
    </div>
  </form>

  <?php if ($editing): ?>
    <section class="card" style="margin-top:32px;padding:16px;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div>
          <h2>Histórico de versões</h2>
          <div class="subtitle">Cada salva gera uma versão com data, autor e comentários sobre as alterações.</div>
        </div>
        <div class="muted" style="font-size:0.85rem;">Clique na versão para visualizar o conteúdo anterior.</div>
      </div>

      <?php if (empty($versions)): ?>
        <div class="muted" style="margin-top:12px;">Nenhuma versão registrada ainda.</div>
      <?php else: ?>
        <div style="overflow:auto;margin-top:16px;">
          <table class="data-table" data-table="interactive" style="width:100%;">
            <thead>
              <tr>
                <th>Versão</th>
                <th>Criado por</th>
                <th>Observações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($versions as $version): ?>
                <?php
                  $createdAt = $version->createdAt ? date('d/m/Y H:i', strtotime($version->createdAt)) : '—';
                  $styledTitle = $version->title ? $esc($version->title) : 'Versão';
                ?>
                <tr>
                  <td>
                    <a class="link" href="regra-cadastro.php?id=<?php echo $esc($formData['id']); ?>&version_id=<?php echo $esc((string) $version->id); ?>">
                      <?php echo $esc($createdAt); ?>
                    </a>
                    <div class="muted"><?php echo $styledTitle; ?></div>
                  </td>
                  <td><?php echo $esc($version->createdByName ?? 'Sistema'); ?></td>
                  <td><?php echo $esc($version->observations ?: '—'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($viewingVersion && $selectedVersion): ?>
        <div class="card" style="margin-top:24px;padding:16px;background:#f9f9f9;">
          <div class="alert info" style="margin:0 0 12px;">
            Você está visualizando a versão registrada em <?php echo $esc($selectedVersion->createdAt ? date('d/m/Y H:i', strtotime($selectedVersion->createdAt)) : '—'); ?>.
            Versões anteriores só podem ser visualizadas e não podem ser editadas.
          </div>
          <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:12px;">
            <a class="ghost" href="regra-cadastro.php?id=<?php echo $esc($formData['id']); ?>">Voltar à versão atual</a>
          </div>
          <div class="grid">
            <div class="field">
              <label>Título</label>
              <div class="muted"><?php echo $esc($selectedVersion->title); ?></div>
            </div>
            <div class="field">
              <label>Status</label>
              <div class="muted"><?php echo $esc($selectedVersion->status); ?></div>
            </div>
            <div class="field" style="grid-column:1 / -1;">
              <label>Observações</label>
              <div class="muted"><?php echo $esc($selectedVersion->observations ?? '—'); ?></div>
            </div>
            <div class="field" style="grid-column:1 / -1;">
              <label>Conteúdo</label>
              <textarea rows="8" readonly style="background:#fff;border:1px solid #ddd;"><?php echo $esc($selectedVersion->content); ?></textarea>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
