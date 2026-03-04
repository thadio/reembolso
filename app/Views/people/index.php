<?php

declare(strict_types=1);
?>
<div class="card">
  <div class="header-row">
    <h2>Lista de Pessoas</h2>
    <span class="badge badge-neutral">Vazio</span>
  </div>
  <?php if (($organIdFilter ?? 0) > 0): ?>
    <p class="muted">Filtro recebido: pessoas vinculadas ao órgão #<?= e((string) $organIdFilter) ?>.</p>
  <?php endif; ?>
  <p class="muted">Nenhuma pessoa cadastrada nesta fase.</p>
  <div class="empty-state">
    <p>O módulo completo será entregue na Fase 1 (Etapa 1.2).</p>
  </div>
</div>
