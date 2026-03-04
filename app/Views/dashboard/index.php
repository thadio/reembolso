<?php

declare(strict_types=1);
?>
<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Pessoas no pipeline</p>
    <p class="kpi-value">0</p>
    <span class="badge badge-neutral">Fase 0</span>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Órgãos cadastrados</p>
    <p class="kpi-value">0</p>
    <span class="badge badge-neutral">Fase 0</span>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Saúde do sistema</p>
    <p class="kpi-value"><a href="<?= e(url('/health')) ?>" target="_blank" rel="noopener">/health</a></p>
    <span class="badge badge-info">Monitoramento</span>
  </article>
</div>

<div class="card">
  <h2>Próxima ação recomendada</h2>
  <p>Iniciar Fase 1 com CRUD de Órgãos e Pessoas aproveitando a base de segurança e auditoria já ativa.</p>
</div>
