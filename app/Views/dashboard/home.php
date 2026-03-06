<?php

declare(strict_types=1);

$homeDashboard = is_array($homeDashboard ?? null) ? $homeDashboard : [];
$year = (int) ($homeDashboard['year'] ?? date('Y'));
$summary = is_array($homeDashboard['summary'] ?? null) ? $homeDashboard['summary'] : [];
$monthlyChart = is_array($homeDashboard['monthly_chart'] ?? null) ? $homeDashboard['monthly_chart'] : [];
$peopleProjection = is_array($homeDashboard['people_projection'] ?? null) ? $homeDashboard['people_projection'] : [];
$links = is_array($homeDashboard['links'] ?? null) ? $homeDashboard['links'] : [];
$generatedAt = (string) ($homeDashboard['generated_at'] ?? '');

$formatMoney = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatPercent = static function (float|int|string $value): string {
    $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

    return number_format($numeric, 2, ',', '.') . '%';
};

$formatDateTime = static function (string $value): string {
    if (trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y H:i', $timestamp);
};

$budgetPath = trim((string) ($links['budget'] ?? '/budget'));
$dashboard2Path = trim((string) ($links['dashboard2'] ?? '/dashboard2'));
$availableBalance = (float) ($summary['available_balance'] ?? 0.0);
?>
<div class="card">
  <div class="header-row">
    <div>
      <h2>Dashboard executivo de reembolso</h2>
      <p class="muted">Ciclo <?= e((string) $year) ?> · Atualizado em <?= e($formatDateTime($generatedAt)) ?></p>
    </div>
    <div class="actions-inline">
      <a class="btn btn-outline" href="<?= e(url($dashboard2Path)) ?>">Abrir dashboard2</a>
      <a class="btn btn-primary" href="<?= e(url($budgetPath)) ?>">Abrir orçamento</a>
    </div>
  </div>
</div>

<div class="grid-kpi">
  <article class="card kpi-card">
    <p class="kpi-label">Orçamento anual vigente</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($summary['total_budget'] ?? 0.0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Gasto acumulado no ano</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($summary['spent_year_to_date'] ?? 0.0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Saldo disponível</p>
    <p class="kpi-value <?= $availableBalance < 0 ? 'text-danger' : 'text-success' ?>"><?= e($formatMoney($availableBalance)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Comprometido</p>
    <p class="kpi-value"><?= e($formatMoney((float) ($summary['committed_amount'] ?? 0.0))) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Execução do orçamento</p>
    <p class="kpi-value"><?= e($formatPercent((float) ($summary['execution_percent'] ?? 0.0))) ?></p>
  </article>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Real x planejado por mês</h3>
      <p class="muted">Linha de limite orçamentário mensal sobre a execução e base planejada.</p>
    </div>
  </div>
  <div class="dashboard-chart-wrap">
    <canvas id="financial-chart" class="dashboard-chart-canvas" aria-label="Gráfico mensal real x planejado"></canvas>
  </div>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Projeções de pessoas (ativo + pipeline)</h3>
      <p class="muted">Composição empilhada prevista para o ciclo anual.</p>
    </div>
  </div>
  <div class="dashboard-chart-wrap">
    <canvas id="people-chart" class="dashboard-chart-canvas" aria-label="Gráfico de projeções de pessoas"></canvas>
  </div>
</div>

<script id="dashboard-monthly-data" type="application/json"><?= (string) json_encode($monthlyChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script id="dashboard-people-data" type="application/json"><?= (string) json_encode($peopleProjection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
  (function () {
    var monthlyDataNode = document.getElementById('dashboard-monthly-data');
    var peopleDataNode = document.getElementById('dashboard-people-data');
    var financialCanvas = document.getElementById('financial-chart');
    var peopleCanvas = document.getElementById('people-chart');
    if (!monthlyDataNode || !peopleDataNode || !financialCanvas || !peopleCanvas) {
      return;
    }

    var monthlyData = [];
    var peopleData = [];
    try {
      monthlyData = JSON.parse(monthlyDataNode.textContent || '[]');
      peopleData = JSON.parse(peopleDataNode.textContent || '[]');
    } catch (error) {
      return;
    }

    var palette = {
      axis: '#93a3b5',
      grid: '#e2e8f0',
      real: '#0b6fa4',
      planned: '#7dc3e7',
      limit: '#d97706',
      active: '#0f766e',
      pipeline: '#b45309'
    };

    function setupCanvas(canvas) {
      var rect = canvas.getBoundingClientRect();
      var width = Math.max(320, Math.floor(rect.width));
      var height = Math.max(220, Math.floor(rect.height || 320));
      var dpr = window.devicePixelRatio || 1;
      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      var ctx = canvas.getContext('2d');
      if (!ctx) {
        return null;
      }
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      return {
        ctx: ctx,
        width: width,
        height: height
      };
    }

    function drawFinancialChart() {
      var setup = setupCanvas(financialCanvas);
      if (!setup) {
        return;
      }
      var ctx = setup.ctx;
      var width = setup.width;
      var height = setup.height;
      ctx.clearRect(0, 0, width, height);

      var left = 44;
      var right = width - 18;
      var top = 16;
      var bottom = height - 34;
      var plotWidth = right - left;
      var plotHeight = bottom - top;
      var count = monthlyData.length || 1;

      var maxValue = 0;
      monthlyData.forEach(function (item) {
        maxValue = Math.max(
          maxValue,
          Number(item.real_amount || 0),
          Number(item.planned_amount || 0),
          Number(item.budget_limit || 0)
        );
      });
      maxValue = Math.max(1, maxValue);

      ctx.strokeStyle = palette.grid;
      ctx.lineWidth = 1;
      for (var line = 0; line <= 4; line += 1) {
        var y = top + (plotHeight * line / 4);
        ctx.beginPath();
        ctx.moveTo(left, y);
        ctx.lineTo(right, y);
        ctx.stroke();
      }

      ctx.strokeStyle = palette.axis;
      ctx.beginPath();
      ctx.moveTo(left, top);
      ctx.lineTo(left, bottom);
      ctx.lineTo(right, bottom);
      ctx.stroke();

      var slotWidth = plotWidth / count;
      var barWidth = Math.max(6, Math.min(20, slotWidth * 0.28));

      ctx.fillStyle = palette.planned;
      monthlyData.forEach(function (item, index) {
        var value = Number(item.planned_amount || 0);
        var barHeight = (value / maxValue) * plotHeight;
        var x = left + (slotWidth * index) + (slotWidth * 0.5) - barWidth - 1;
        var y = bottom - barHeight;
        ctx.fillRect(x, y, barWidth, barHeight);
      });

      ctx.fillStyle = palette.real;
      monthlyData.forEach(function (item, index) {
        var value = Number(item.real_amount || 0);
        var barHeight = (value / maxValue) * plotHeight;
        var x = left + (slotWidth * index) + (slotWidth * 0.5) + 1;
        var y = bottom - barHeight;
        ctx.fillRect(x, y, barWidth, barHeight);
      });

      ctx.strokeStyle = palette.limit;
      ctx.lineWidth = 2;
      ctx.beginPath();
      monthlyData.forEach(function (item, index) {
        var value = Number(item.budget_limit || 0);
        var x = left + (slotWidth * index) + (slotWidth * 0.5);
        var y = bottom - ((value / maxValue) * plotHeight);
        if (index === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.stroke();

      ctx.fillStyle = '#334155';
      ctx.font = '12px sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';
      monthlyData.forEach(function (item, index) {
        var x = left + (slotWidth * index) + (slotWidth * 0.5);
        ctx.fillText(String(item.label || ''), x, bottom + 8);
      });
    }

    function drawPeopleChart() {
      var setup = setupCanvas(peopleCanvas);
      if (!setup) {
        return;
      }
      var ctx = setup.ctx;
      var width = setup.width;
      var height = setup.height;
      ctx.clearRect(0, 0, width, height);

      var left = 44;
      var right = width - 18;
      var top = 16;
      var bottom = height - 34;
      var plotWidth = right - left;
      var plotHeight = bottom - top;
      var count = peopleData.length || 1;

      var maxTotal = 1;
      peopleData.forEach(function (item) {
        maxTotal = Math.max(maxTotal, Number(item.total_people || 0));
      });

      ctx.strokeStyle = palette.grid;
      ctx.lineWidth = 1;
      for (var line = 0; line <= 4; line += 1) {
        var y = top + (plotHeight * line / 4);
        ctx.beginPath();
        ctx.moveTo(left, y);
        ctx.lineTo(right, y);
        ctx.stroke();
      }

      ctx.strokeStyle = palette.axis;
      ctx.beginPath();
      ctx.moveTo(left, top);
      ctx.lineTo(left, bottom);
      ctx.lineTo(right, bottom);
      ctx.stroke();

      var slotWidth = plotWidth / count;
      var barWidth = Math.max(8, Math.min(24, slotWidth * 0.42));

      peopleData.forEach(function (item, index) {
        var active = Number(item.active_people || 0);
        var pipeline = Number(item.pipeline_people || 0);
        var activeHeight = (active / maxTotal) * plotHeight;
        var pipelineHeight = (pipeline / maxTotal) * plotHeight;
        var x = left + (slotWidth * index) + (slotWidth * 0.5) - (barWidth / 2);
        var yPipeline = bottom - pipelineHeight;
        var yActive = yPipeline - activeHeight;

        ctx.fillStyle = palette.pipeline;
        ctx.fillRect(x, yPipeline, barWidth, pipelineHeight);

        ctx.fillStyle = palette.active;
        ctx.fillRect(x, yActive, barWidth, activeHeight);
      });

      ctx.fillStyle = '#334155';
      ctx.font = '12px sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';
      peopleData.forEach(function (item, index) {
        var x = left + (slotWidth * index) + (slotWidth * 0.5);
        ctx.fillText(String(item.label || ''), x, bottom + 8);
      });
    }

    function renderAll() {
      drawFinancialChart();
      drawPeopleChart();
    }

    renderAll();
    window.addEventListener('resize', renderAll);
  })();
</script>
