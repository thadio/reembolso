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
$projectedBalanceYearEnd = (float) ($summary['projected_balance_year_end'] ?? 0.0);
$projectedSpentNextYear = (float) ($summary['projected_spent_next_year'] ?? 0.0);
$nextYear = $year + 1;
?>
<div class="card">
  <div class="header-row">
    <div>
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
    <p class="kpi-label">Saldo projetado em 31/12/<?= e((string) $year) ?></p>
    <p class="kpi-value <?= $projectedBalanceYearEnd < 0 ? 'text-danger' : 'text-success' ?>"><?= e($formatMoney($projectedBalanceYearEnd)) ?></p>
  </article>
  <article class="card kpi-card">
    <p class="kpi-label">Gasto total projetado em <?= e((string) $nextYear) ?></p>
    <p class="kpi-value"><?= e($formatMoney($projectedSpentNextYear)) ?></p>
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
  <div class="dashboard-chart-legend" aria-hidden="true">
    <span class="dashboard-legend-item"><span class="dashboard-legend-dot is-planned"></span>Planejado</span>
    <span class="dashboard-legend-item"><span class="dashboard-legend-dot is-real"></span>Real</span>
    <span class="dashboard-legend-item"><span class="dashboard-legend-dot is-limit"></span>Limite mensal</span>
  </div>
  <div class="dashboard-chart-wrap" id="financial-chart-wrap">
    <canvas id="financial-chart" class="dashboard-chart-canvas" tabindex="0" aria-label="Gráfico mensal real x planejado"></canvas>
    <div id="financial-chart-tooltip" class="dashboard-chart-tooltip" hidden></div>
  </div>
  <div id="financial-chart-detail" class="dashboard-chart-detail" aria-live="polite">Clique em uma barra ou ponto para ver o detalhamento mensal.</div>
</div>

<div class="card">
  <div class="header-row">
    <div>
      <h3>Projeções de pessoas (ativo + pipeline)</h3>
      <p class="muted">Composição empilhada prevista para o ciclo anual.</p>
    </div>
  </div>
  <div class="dashboard-chart-legend" aria-hidden="true">
    <span class="dashboard-legend-item"><span class="dashboard-legend-dot is-active"></span>Ativo</span>
    <span class="dashboard-legend-item"><span class="dashboard-legend-dot is-pipeline"></span>Pipeline</span>
    <span class="dashboard-legend-item"><span class="dashboard-legend-dot is-total"></span>Total</span>
  </div>
  <div class="dashboard-chart-wrap" id="people-chart-wrap">
    <canvas id="people-chart" class="dashboard-chart-canvas" tabindex="0" aria-label="Gráfico de projeções de pessoas"></canvas>
    <div id="people-chart-tooltip" class="dashboard-chart-tooltip" hidden></div>
  </div>
  <div id="people-chart-detail" class="dashboard-chart-detail" aria-live="polite">Clique em uma barra para ver a composição do mês.</div>
</div>

<script id="dashboard-monthly-data" type="application/json"><?= (string) json_encode($monthlyChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script id="dashboard-people-data" type="application/json"><?= (string) json_encode($peopleProjection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
  (function () {
    var boot = function () {
      var monthlyDataNode = document.getElementById('dashboard-monthly-data');
      var peopleDataNode = document.getElementById('dashboard-people-data');
      var financialCanvas = document.getElementById('financial-chart');
      var peopleCanvas = document.getElementById('people-chart');
      var financialWrap = document.getElementById('financial-chart-wrap');
      var peopleWrap = document.getElementById('people-chart-wrap');
      var financialTooltip = document.getElementById('financial-chart-tooltip');
      var peopleTooltip = document.getElementById('people-chart-tooltip');
      var financialDetail = document.getElementById('financial-chart-detail');
      var peopleDetail = document.getElementById('people-chart-detail');
      var chartPattern = window.ReembolsoChartPattern || null;

      if (
        !monthlyDataNode
        || !peopleDataNode
        || !financialCanvas
        || !peopleCanvas
        || !financialWrap
        || !peopleWrap
        || !financialTooltip
        || !peopleTooltip
        || !financialDetail
        || !peopleDetail
      ) {
        return;
      }

      function toNumber(value) {
        if (chartPattern && typeof chartPattern.toNumber === 'function') {
          return chartPattern.toNumber(value);
        }

        var numeric = Number(value || 0);
        return Number.isFinite(numeric) ? numeric : 0;
      }

      function normalizeMonthlyData(source) {
        if (!Array.isArray(source)) {
          return [];
        }

        return source.map(function (item, index) {
          var month = Math.max(1, Math.min(12, Math.floor(toNumber(item.month || (index + 1)))));
          return {
            month: month,
            label: String(item.label || ''),
            real_amount: Math.max(0, toNumber(item.real_amount)),
            planned_amount: Math.max(0, toNumber(item.planned_amount)),
            budget_limit: Math.max(0, toNumber(item.budget_limit))
          };
        }).sort(function (a, b) {
          return a.month - b.month;
        });
      }

      function normalizePeopleData(source) {
        if (!Array.isArray(source)) {
          return [];
        }

        return source.map(function (item, index) {
          var month = Math.max(1, Math.min(12, Math.floor(toNumber(item.month || (index + 1)))));
          var active = Math.max(0, Math.floor(toNumber(item.active_people)));
          var pipeline = Math.max(0, Math.floor(toNumber(item.pipeline_people)));
          var total = Math.max(active + pipeline, Math.floor(toNumber(item.total_people)));
          return {
            month: month,
            label: String(item.label || ''),
            active_people: active,
            pipeline_people: pipeline,
            total_people: total
          };
        }).sort(function (a, b) {
          return a.month - b.month;
        });
      }

    var monthlyData = [];
    var peopleData = [];
    try {
      monthlyData = normalizeMonthlyData(JSON.parse(monthlyDataNode.textContent || '[]'));
      peopleData = normalizePeopleData(JSON.parse(peopleDataNode.textContent || '[]'));
    } catch (error) {
      return;
    }

    if (monthlyData.length === 0 || peopleData.length === 0) {
      return;
    }

    var palette = {
      axis: '#8fa0b3',
      grid: '#dce6f0',
      marker: '#4f6275',
      real: '#0f6fa8',
      realStroke: '#094b71',
      planned: '#8bc8ea',
      plannedStroke: '#5aa8d2',
      limit: '#d97706',
      limitPoint: '#f59e0b',
      selectedSlot: 'rgba(15, 111, 168, 0.12)',
      hoverSlot: 'rgba(15, 111, 168, 0.18)',
      active: '#0f766e',
      activeStroke: '#0a504b',
      pipeline: '#b45309',
      pipelineStroke: '#7c3f08',
      totalLine: '#475569'
    };

      var formatters = chartPattern && typeof chartPattern.createFormatters === 'function'
        ? chartPattern.createFormatters('pt-BR', 'BRL')
        : null;

      function formatNumber(value) {
        if (formatters && typeof formatters.formatNumber === 'function') {
          return formatters.formatNumber(value);
        }

        var numeric = Math.round(Math.max(0, toNumber(value)));
        return String(numeric);
      }

      function formatCurrency(value) {
        if (formatters && typeof formatters.formatCurrency === 'function') {
          return formatters.formatCurrency(value);
        }

        var numeric = Math.max(0, toNumber(value));
        return 'R$ ' + numeric.toFixed(2);
      }

      function formatPercent(value) {
        if (formatters && typeof formatters.formatPercent === 'function') {
          return formatters.formatPercent(value, 1);
        }

        var numeric = Number(Math.max(0, toNumber(value)));
        return numeric.toFixed(1).replace('.', ',') + '%';
      }

      function formatCompactMoney(value) {
        if (formatters && typeof formatters.formatCompactMoney === 'function') {
          return formatters.formatCompactMoney(value);
        }

        var numeric = Math.max(0, toNumber(value));
        if (numeric >= 1000000) {
          return 'R$ ' + (numeric / 1000000).toFixed(1).replace('.', ',') + ' mi';
        }
        if (numeric >= 1000) {
          return 'R$ ' + (numeric / 1000).toFixed(1).replace('.', ',') + ' mil';
        }

        return 'R$ ' + Math.round(numeric).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }

      function clamp(value, min, max) {
        if (chartPattern && typeof chartPattern.clamp === 'function') {
          return chartPattern.clamp(value, min, max);
        }

        return Math.min(max, Math.max(min, value));
      }

    function resolveInitialMonth(items) {
      var currentMonth = new Date().getMonth() + 1;
      var hasCurrent = items.some(function (item) {
        return item.month === currentMonth;
      });

      if (hasCurrent) {
        return currentMonth;
      }

      return items.length > 0 ? items[0].month : null;
    }

      function monthIndex(items, month) {
        if (chartPattern && typeof chartPattern.monthIndex === 'function') {
          return chartPattern.monthIndex(items, month, 'month');
        }

        for (var index = 0; index < items.length; index += 1) {
          if (items[index].month === month) {
            return index;
          }
        }

        return 0;
      }

    function findItemByMonth(items, month) {
      for (var index = 0; index < items.length; index += 1) {
        if (items[index].month === month) {
          return items[index];
        }
      }

      return null;
    }

      function setupCanvas(canvas) {
        if (chartPattern && typeof chartPattern.setupCanvas === 'function') {
          return chartPattern.setupCanvas(canvas, {
            minWidth: 280,
            minHeight: 220,
            maxDpr: 2
          });
        }

        var rect = canvas.getBoundingClientRect();
        var width = Math.max(280, Math.floor(rect.width));
        var height = Math.max(220, Math.floor(rect.height || 320));
        var dpr = clamp(window.devicePixelRatio || 1, 1, 2);
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

    function roundedRectPath(ctx, x, y, width, height, radius) {
      var safeWidth = Math.max(0, width);
      var safeHeight = Math.max(0, height);
      if (safeWidth === 0 || safeHeight === 0) {
        return;
      }

      var safeRadius = Math.max(0, Math.min(radius, safeWidth / 2, safeHeight / 2));
      ctx.beginPath();
      ctx.moveTo(x + safeRadius, y);
      ctx.lineTo(x + safeWidth - safeRadius, y);
      ctx.quadraticCurveTo(x + safeWidth, y, x + safeWidth, y + safeRadius);
      ctx.lineTo(x + safeWidth, y + safeHeight - safeRadius);
      ctx.quadraticCurveTo(x + safeWidth, y + safeHeight, x + safeWidth - safeRadius, y + safeHeight);
      ctx.lineTo(x + safeRadius, y + safeHeight);
      ctx.quadraticCurveTo(x, y + safeHeight, x, y + safeHeight - safeRadius);
      ctx.lineTo(x, y + safeRadius);
      ctx.quadraticCurveTo(x, y, x + safeRadius, y);
      ctx.closePath();
    }

    function fillRoundedRect(ctx, x, y, width, height, radius, fillStyle) {
      roundedRectPath(ctx, x, y, width, height, radius);
      ctx.fillStyle = fillStyle;
      ctx.fill();
    }

    function strokeRoundedRect(ctx, x, y, width, height, radius, strokeStyle, lineWidth) {
      roundedRectPath(ctx, x, y, width, height, radius);
      ctx.strokeStyle = strokeStyle;
      ctx.lineWidth = lineWidth;
      ctx.stroke();
    }

    function niceStep(maxValue, lines) {
      var raw = Math.max(1, maxValue) / Math.max(1, lines);
      var exponent = Math.floor(Math.log10(raw));
      var magnitude = Math.pow(10, exponent);
      var residual = raw / magnitude;
      var niceResidual = 1;

      if (residual > 5) {
        niceResidual = 10;
      } else if (residual > 2) {
        niceResidual = 5;
      } else if (residual > 1) {
        niceResidual = 2;
      }

      return niceResidual * magnitude;
    }

      function buildScale(maxValue, lines) {
        if (chartPattern && typeof chartPattern.buildScale === 'function') {
          return chartPattern.buildScale(maxValue, lines);
        }

        var step = niceStep(maxValue, lines);
        var max = Math.max(step, Math.ceil(Math.max(1, maxValue) / step) * step);
        return {
          max: max,
          step: step,
          lines: lines
        };
      }

      function buildLayout(width, height, leftPadding) {
        if (chartPattern && typeof chartPattern.buildLayout === 'function') {
          return chartPattern.buildLayout(width, height, leftPadding, {
            rightPadding: 16,
            topPadding: 20,
            bottomPadding: 42
          });
        }

        var left = leftPadding;
        var right = Math.max(left + 100, width - 16);
        var top = 20;
        var bottom = height - 42;

        return {
          left: left,
          right: right,
          top: top,
          bottom: bottom,
          plotWidth: right - left,
          plotHeight: bottom - top
        };
      }

      function yByValue(layout, scale, value) {
        if (chartPattern && typeof chartPattern.yByValue === 'function') {
          return chartPattern.yByValue(layout, scale, value);
        }

        return layout.bottom - (Math.max(0, value) / Math.max(1, scale.max)) * layout.plotHeight;
      }

      function drawAxesAndGrid(ctx, layout, scale, yFormatter, slots) {
        if (chartPattern && typeof chartPattern.drawAxesAndGrid === 'function') {
          chartPattern.drawAxesAndGrid(ctx, layout, scale, slots, yFormatter, {
            palette: {
              grid: palette.grid,
              axis: palette.axis,
              marker: palette.marker,
              verticalGrid: '#d3e1ec'
            }
          });
          return;
        }

      ctx.save();
      ctx.strokeStyle = palette.grid;
      ctx.lineWidth = 1;
      ctx.fillStyle = palette.marker;
      ctx.font = '11px sans-serif';
      ctx.textAlign = 'right';
      ctx.textBaseline = 'middle';

      for (var line = 0; line <= scale.lines; line += 1) {
        var value = scale.step * line;
        var y = yByValue(layout, scale, value);

        ctx.beginPath();
        ctx.moveTo(layout.left, y);
        ctx.lineTo(layout.right, y);
        ctx.stroke();
        ctx.fillText(yFormatter(value), layout.left - 10, y);
      }

      if (slots.length > 0) {
        ctx.save();
        ctx.setLineDash([3, 5]);
        ctx.strokeStyle = '#d3e1ec';
        slots.forEach(function (slot) {
          ctx.beginPath();
          ctx.moveTo(slot.centerX, layout.top);
          ctx.lineTo(slot.centerX, layout.bottom);
          ctx.stroke();
        });
        ctx.restore();
      }

      ctx.strokeStyle = palette.axis;
      ctx.lineWidth = 1.2;
      ctx.beginPath();
      ctx.moveTo(layout.left, layout.top);
      ctx.lineTo(layout.left, layout.bottom);
      ctx.lineTo(layout.right, layout.bottom);
      ctx.stroke();
      ctx.restore();
      }

      function drawSlotHighlights(ctx, layout, slots, selectedMonth, hoverMonth) {
        if (chartPattern && typeof chartPattern.drawSlotHighlights === 'function') {
          chartPattern.drawSlotHighlights(ctx, layout, slots, selectedMonth, hoverMonth, {
            keyField: 'month',
            selectedFill: palette.selectedSlot,
            hoverFill: palette.hoverSlot
          });
          return;
        }

      slots.forEach(function (slot) {
        if (slot.month === selectedMonth) {
          ctx.fillStyle = palette.selectedSlot;
          ctx.fillRect(slot.startX + 1, layout.top + 1, slot.width - 2, layout.plotHeight - 2);
        }
      });

      slots.forEach(function (slot) {
        if (slot.month === hoverMonth) {
          ctx.fillStyle = palette.hoverSlot;
          ctx.fillRect(slot.startX + 1, layout.top + 1, slot.width - 2, layout.plotHeight - 2);
        }
      });
      }

      function drawXLabels(ctx, layout, slots) {
        if (chartPattern && typeof chartPattern.drawXLabels === 'function') {
          chartPattern.drawXLabels(ctx, layout, slots, {
            color: '#334155',
            font: '12px sans-serif',
            keyField: 'label',
            offsetY: 10
          });
          return;
        }

      ctx.save();
      ctx.fillStyle = '#334155';
      ctx.font = '12px sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';
      slots.forEach(function (slot) {
        ctx.fillText(slot.label, slot.centerX, layout.bottom + 10);
      });
      ctx.restore();
      }

      function isInsidePlot(layout, x, y) {
        if (chartPattern && typeof chartPattern.isInsidePlot === 'function') {
          return chartPattern.isInsidePlot(layout, x, y);
        }

      return x >= layout.left && x <= layout.right && y >= layout.top && y <= layout.bottom;
      }

    var financialState = {
      selectedMonth: resolveInitialMonth(monthlyData),
      hoverMonth: null,
      hoverElementKey: null,
      elements: [],
      slots: [],
      layout: null
    };

    var peopleState = {
      selectedMonth: resolveInitialMonth(peopleData),
      hoverMonth: null,
      hoverElementKey: null,
      elements: [],
      slots: [],
      layout: null
    };

    function drawFinancialChart() {
      var setup = setupCanvas(financialCanvas);
      if (!setup) {
        return;
      }

      var ctx = setup.ctx;
      var width = setup.width;
      var height = setup.height;
      var layout = buildLayout(width, height, width < 560 ? 72 : 88);
      var count = Math.max(1, monthlyData.length);
      var slotWidth = layout.plotWidth / count;
      var maxValue = 1;

      monthlyData.forEach(function (item) {
        maxValue = Math.max(maxValue, item.real_amount, item.planned_amount, item.budget_limit);
      });

      var scale = buildScale(maxValue, 5);
      var hoveredMonth = financialState.hoverMonth;
      var selectedMonth = financialState.selectedMonth;
      var slots = [];
      var elements = [];

      ctx.clearRect(0, 0, width, height);
      var plotGradient = ctx.createLinearGradient(0, layout.top, 0, layout.bottom);
      plotGradient.addColorStop(0, '#f9fcff');
      plotGradient.addColorStop(1, '#f3f8fd');
      ctx.fillStyle = plotGradient;
      ctx.fillRect(layout.left, layout.top, layout.plotWidth, layout.plotHeight);

      monthlyData.forEach(function (item, index) {
        var startX = layout.left + (slotWidth * index);
        slots.push({
          month: item.month,
          label: item.label,
          startX: startX,
          endX: startX + slotWidth,
          centerX: startX + (slotWidth / 2),
          width: slotWidth
        });
      });

      drawSlotHighlights(ctx, layout, slots, selectedMonth, hoveredMonth);
      drawAxesAndGrid(ctx, layout, scale, formatCompactMoney, slots);

      var barWidth = Math.max(8, Math.min(24, slotWidth * 0.28));
      var plannedGradient = ctx.createLinearGradient(0, layout.top, 0, layout.bottom);
      plannedGradient.addColorStop(0, '#9dd8f4');
      plannedGradient.addColorStop(1, palette.planned);
      var realGradient = ctx.createLinearGradient(0, layout.top, 0, layout.bottom);
      realGradient.addColorStop(0, '#1a8bcd');
      realGradient.addColorStop(1, palette.real);

      monthlyData.forEach(function (item, index) {
        var slot = slots[index];
        var plannedHeight = Math.max(0, (item.planned_amount / scale.max) * layout.plotHeight);
        var realHeight = Math.max(0, (item.real_amount / scale.max) * layout.plotHeight);
        var xPlanned = slot.centerX - barWidth - 2;
        var xReal = slot.centerX + 2;
        var yPlanned = layout.bottom - plannedHeight;
        var yReal = layout.bottom - realHeight;

        fillRoundedRect(ctx, xPlanned, yPlanned, barWidth, plannedHeight, 6, plannedGradient);
        fillRoundedRect(ctx, xReal, yReal, barWidth, realHeight, 6, realGradient);

        var plannedKey = 'financial-' + item.month + '-planned';
        var realKey = 'financial-' + item.month + '-real';
        elements.push({
          key: plannedKey,
          type: 'bar',
          month: item.month,
          x: xPlanned,
          y: yPlanned,
          width: barWidth,
          height: plannedHeight
        });
        elements.push({
          key: realKey,
          type: 'bar',
          month: item.month,
          x: xReal,
          y: yReal,
          width: barWidth,
          height: realHeight
        });

        if (financialState.hoverElementKey === plannedKey) {
          strokeRoundedRect(ctx, xPlanned, yPlanned, barWidth, plannedHeight, 6, palette.plannedStroke, 2);
        }

        if (financialState.hoverElementKey === realKey) {
          strokeRoundedRect(ctx, xReal, yReal, barWidth, realHeight, 6, palette.realStroke, 2);
        }
      });

      var limitPoints = [];
      ctx.save();
      ctx.strokeStyle = palette.limit;
      ctx.lineWidth = 2.5;
      ctx.beginPath();
      monthlyData.forEach(function (item, index) {
        var slot = slots[index];
        var point = {
          x: slot.centerX,
          y: yByValue(layout, scale, item.budget_limit),
          month: item.month
        };
        limitPoints.push(point);
        if (index === 0) {
          ctx.moveTo(point.x, point.y);
        } else {
          ctx.lineTo(point.x, point.y);
        }
      });
      ctx.stroke();
      ctx.restore();

      limitPoints.forEach(function (point) {
        var key = 'financial-' + point.month + '-limit';
        var isHovered = financialState.hoverElementKey === key;
        elements.push({
          key: key,
          type: 'point',
          month: point.month,
          x: point.x,
          y: point.y,
          radius: isHovered ? 6 : 5
        });

        ctx.beginPath();
        ctx.arc(point.x, point.y, isHovered ? 6 : 5, 0, Math.PI * 2);
        ctx.fillStyle = palette.limitPoint;
        ctx.fill();
        ctx.lineWidth = isHovered ? 3 : 2;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();
      });

      drawXLabels(ctx, layout, slots);

      financialState.elements = elements;
      financialState.slots = slots;
      financialState.layout = layout;
    }

    function drawPeopleChart() {
      var setup = setupCanvas(peopleCanvas);
      if (!setup) {
        return;
      }
      var ctx = setup.ctx;
      var width = setup.width;
      var height = setup.height;
      var layout = buildLayout(width, height, width < 560 ? 62 : 72);
      var count = Math.max(1, peopleData.length);
      var slotWidth = layout.plotWidth / count;
      var maxTotal = 1;
      var slots = [];
      var elements = [];

      peopleData.forEach(function (item) {
        maxTotal = Math.max(maxTotal, item.total_people);
      });

      var scale = buildScale(maxTotal, 5);
      var hoveredMonth = peopleState.hoverMonth;
      var selectedMonth = peopleState.selectedMonth;

      ctx.clearRect(0, 0, width, height);
      var plotGradient = ctx.createLinearGradient(0, layout.top, 0, layout.bottom);
      plotGradient.addColorStop(0, '#f9fcff');
      plotGradient.addColorStop(1, '#f3f8fd');
      ctx.fillStyle = plotGradient;
      ctx.fillRect(layout.left, layout.top, layout.plotWidth, layout.plotHeight);

      peopleData.forEach(function (item, index) {
        var startX = layout.left + (slotWidth * index);
        slots.push({
          month: item.month,
          label: item.label,
          startX: startX,
          endX: startX + slotWidth,
          centerX: startX + (slotWidth / 2),
          width: slotWidth
        });
      });

      drawSlotHighlights(ctx, layout, slots, selectedMonth, hoveredMonth);
      drawAxesAndGrid(ctx, layout, scale, formatNumber, slots);

      var barWidth = Math.max(12, Math.min(32, slotWidth * 0.5));
      var totalPoints = [];

      peopleData.forEach(function (item, index) {
        var slot = slots[index];
        var active = item.active_people;
        var pipeline = item.pipeline_people;
        var total = Math.max(0, item.total_people);
        var totalHeight = (total / scale.max) * layout.plotHeight;
        var pipelineHeight = (pipeline / scale.max) * layout.plotHeight;
        var activeHeight = (active / scale.max) * layout.plotHeight;
        var x = slot.centerX - (barWidth / 2);
        var yTop = layout.bottom - totalHeight;
        var yPipeline = layout.bottom - pipelineHeight;
        var yActive = yPipeline - activeHeight;

        roundedRectPath(ctx, x, yTop, barWidth, totalHeight, 8);
        ctx.save();
        ctx.clip();
        ctx.fillStyle = palette.pipeline;
        ctx.fillRect(x, yPipeline, barWidth, pipelineHeight);
        ctx.fillStyle = palette.active;
        ctx.fillRect(x, yActive, barWidth, activeHeight);
        ctx.restore();
        strokeRoundedRect(ctx, x, yTop, barWidth, totalHeight, 8, '#ffffff', 1);

        var pipelineKey = 'people-' + item.month + '-pipeline';
        var activeKey = 'people-' + item.month + '-active';
        elements.push({
          key: pipelineKey,
          type: 'bar',
          month: item.month,
          x: x,
          y: yPipeline,
          width: barWidth,
          height: Math.max(0, pipelineHeight)
        });
        elements.push({
          key: activeKey,
          type: 'bar',
          month: item.month,
          x: x,
          y: yActive,
          width: barWidth,
          height: Math.max(0, activeHeight)
        });

        if (peopleState.hoverElementKey === pipelineKey) {
          strokeRoundedRect(ctx, x, yPipeline, barWidth, Math.max(0, pipelineHeight), 6, palette.pipelineStroke, 2);
        }
        if (peopleState.hoverElementKey === activeKey) {
          strokeRoundedRect(ctx, x, yActive, barWidth, Math.max(0, activeHeight), 6, palette.activeStroke, 2);
        }

        totalPoints.push({
          x: slot.centerX,
          y: yTop,
          month: item.month
        });
      });

      ctx.save();
      ctx.strokeStyle = palette.totalLine;
      ctx.lineWidth = 2;
      ctx.beginPath();
      totalPoints.forEach(function (point, index) {
        if (index === 0) {
          ctx.moveTo(point.x, point.y);
        } else {
          ctx.lineTo(point.x, point.y);
        }
      });
      ctx.stroke();
      ctx.restore();

      totalPoints.forEach(function (point) {
        var key = 'people-' + point.month + '-total';
        var isHovered = peopleState.hoverElementKey === key;
        elements.push({
          key: key,
          type: 'point',
          month: point.month,
          x: point.x,
          y: point.y,
          radius: isHovered ? 6 : 5
        });

        ctx.beginPath();
        ctx.arc(point.x, point.y, isHovered ? 6 : 5, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.lineWidth = isHovered ? 3 : 2;
        ctx.strokeStyle = palette.totalLine;
        ctx.stroke();
      });

      drawXLabels(ctx, layout, slots);

      peopleState.elements = elements;
      peopleState.slots = slots;
      peopleState.layout = layout;
    }

      function hideTooltip(node) {
        if (chartPattern && typeof chartPattern.hideTooltip === 'function') {
          chartPattern.hideTooltip(node);
          return;
        }

        node.hidden = true;
        node.innerHTML = '';
      }

      function renderTooltip(node, title, lines) {
        if (chartPattern && typeof chartPattern.renderTooltip === 'function') {
          chartPattern.renderTooltip(node, title, lines);
          return;
        }

        node.innerHTML = '';

        var titleNode = document.createElement('div');
        titleNode.className = 'dashboard-chart-tooltip-title';
        titleNode.textContent = title;
        node.appendChild(titleNode);

        lines.forEach(function (line) {
          var lineNode = document.createElement('div');
          lineNode.className = 'dashboard-chart-tooltip-row';
          lineNode.textContent = line;
          node.appendChild(lineNode);
        });
      }

      function showTooltip(node, wrap, pointerX, pointerY, title, lines) {
        if (chartPattern && typeof chartPattern.showTooltip === 'function') {
          chartPattern.showTooltip(node, wrap, pointerX, pointerY, title, lines, { margin: 10 });
          return;
        }

        renderTooltip(node, title, lines);
        node.hidden = false;

        var margin = 10;
        var left = pointerX + 16;
        var top = pointerY - 10;
        var maxLeft = wrap.clientWidth - node.offsetWidth - margin;
        var maxTop = wrap.clientHeight - node.offsetHeight - margin;
        if (left > maxLeft) {
          left = pointerX - node.offsetWidth - 16;
        }
        if (top > maxTop) {
          top = maxTop;
        }
        if (top < margin) {
          top = margin;
        }
        if (left < margin) {
          left = margin;
        }

        node.style.left = left + 'px';
        node.style.top = top + 'px';
      }

      function pointFromEvent(canvas, event) {
        if (chartPattern && typeof chartPattern.pointFromEvent === 'function') {
          return chartPattern.pointFromEvent(canvas, event);
        }

        var rect = canvas.getBoundingClientRect();
        return {
          x: event.clientX - rect.left,
          y: event.clientY - rect.top
        };
      }

      function findSlotByX(state, x) {
        if (chartPattern && typeof chartPattern.findSlotByX === 'function') {
          return chartPattern.findSlotByX(state.slots, x);
        }

        for (var index = 0; index < state.slots.length; index += 1) {
          var slot = state.slots[index];
          if (x >= slot.startX && x <= slot.endX) {
            return slot;
          }
        }

        return null;
      }

      function findElementByPoint(state, x, y) {
        if (chartPattern && typeof chartPattern.findElementByPoint === 'function') {
          return chartPattern.findElementByPoint(state.elements, x, y);
        }

        for (var index = state.elements.length - 1; index >= 0; index -= 1) {
          var element = state.elements[index];
          if (element.type === 'point') {
            var dx = x - element.x;
            var dy = y - element.y;
            var limit = (element.radius || 5) + 4;
            if ((dx * dx) + (dy * dy) <= limit * limit) {
              return element;
            }
          } else if (
            x >= element.x
            && x <= element.x + element.width
            && y >= element.y
            && y <= element.y + element.height
          ) {
            return element;
          }
        }

        return null;
      }

    function updateFinancialDetail(month) {
      var item = findItemByMonth(monthlyData, month);
      if (!item) {
        financialDetail.textContent = 'Clique em uma barra ou ponto para ver o detalhamento mensal.';
        return;
      }

      var delta = item.real_amount - item.planned_amount;
      var deltaText = delta >= 0
        ? 'Desvio: +' + formatCurrency(delta)
        : 'Desvio: -' + formatCurrency(Math.abs(delta));
      var limitGap = item.budget_limit - item.real_amount;
      var limitText = limitGap >= 0
        ? 'Folga para o limite: ' + formatCurrency(limitGap)
        : 'Acima do limite em: ' + formatCurrency(Math.abs(limitGap));

      financialDetail.textContent = item.label + ' · Real: ' + formatCurrency(item.real_amount) + ' · Planejado: ' + formatCurrency(item.planned_amount) + ' · Limite: ' + formatCurrency(item.budget_limit) + ' · ' + deltaText + ' · ' + limitText + '.';
    }

    function updatePeopleDetail(month) {
      var item = findItemByMonth(peopleData, month);
      if (!item) {
        peopleDetail.textContent = 'Clique em uma barra para ver a composição do mês.';
        return;
      }

      var total = Math.max(1, item.total_people);
      var pipelineShare = (item.pipeline_people / total) * 100;
      peopleDetail.textContent = item.label + ' · Ativo: ' + formatNumber(item.active_people) + ' · Pipeline: ' + formatNumber(item.pipeline_people) + ' · Total: ' + formatNumber(item.total_people) + ' · Participação do pipeline: ' + formatPercent(pipelineShare) + '.';
    }

    function financialTooltipData(item, element) {
      var line = 'Mês: ' + item.label;
      if (element && element.key.indexOf('-planned') !== -1) {
        line = 'Elemento: Planejado';
      } else if (element && element.key.indexOf('-real') !== -1) {
        line = 'Elemento: Real';
      } else if (element && element.key.indexOf('-limit') !== -1) {
        line = 'Elemento: Limite';
      }

      return {
        title: line,
        rows: [
          'Planejado: ' + formatCurrency(item.planned_amount),
          'Real: ' + formatCurrency(item.real_amount),
          'Limite: ' + formatCurrency(item.budget_limit)
        ]
      };
    }

    function peopleTooltipData(item, element) {
      var line = 'Mês: ' + item.label;
      if (element && element.key.indexOf('-active') !== -1) {
        line = 'Elemento: Ativo';
      } else if (element && element.key.indexOf('-pipeline') !== -1) {
        line = 'Elemento: Pipeline';
      } else if (element && element.key.indexOf('-total') !== -1) {
        line = 'Elemento: Total';
      }

      return {
        title: line,
        rows: [
          'Ativo: ' + formatNumber(item.active_people),
          'Pipeline: ' + formatNumber(item.pipeline_people),
          'Total: ' + formatNumber(item.total_people)
        ]
      };
    }

    function bindChartInteractions(options) {
      var canvas = options.canvas;
      var wrap = options.wrap;
      var tooltip = options.tooltip;
      var data = options.data;
      var state = options.state;
      var draw = options.draw;
      var buildTooltipData = options.buildTooltipData;
      var updateDetail = options.updateDetail;

      var handleMove = function (event) {
        if (!state.layout) {
          return;
        }

        var point = pointFromEvent(canvas, event);
        if (!isInsidePlot(state.layout, point.x, point.y)) {
          if (state.hoverMonth !== null || state.hoverElementKey !== null) {
            state.hoverMonth = null;
            state.hoverElementKey = null;
            draw();
          }
          hideTooltip(tooltip);
          return;
        }

        var slot = findSlotByX(state, point.x);
        if (!slot) {
          hideTooltip(tooltip);
          return;
        }

        var element = findElementByPoint(state, point.x, point.y);
        state.hoverMonth = slot.month;
        state.hoverElementKey = element ? element.key : null;
        draw();

        var item = findItemByMonth(data, slot.month);
        if (!item) {
          hideTooltip(tooltip);
          return;
        }

        var tooltipData = buildTooltipData(item, element);
        showTooltip(tooltip, wrap, point.x, point.y, tooltipData.title, tooltipData.rows);
      };

      var handleClick = function (event) {
        if (!state.layout) {
          return;
        }

        var point = pointFromEvent(canvas, event);
        if (!isInsidePlot(state.layout, point.x, point.y)) {
          return;
        }

        var slot = findSlotByX(state, point.x);
        if (!slot) {
          return;
        }

        state.selectedMonth = slot.month;
        updateDetail(slot.month);
        draw();
      };

      var handleKeydown = function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
          return;
        }

        event.preventDefault();
        var currentIndex = monthIndex(data, state.selectedMonth);
        var nextIndex = currentIndex + (event.key === 'ArrowRight' ? 1 : -1);
        nextIndex = clamp(nextIndex, 0, data.length - 1);
        var nextMonth = data[nextIndex] ? data[nextIndex].month : null;
        if (nextMonth === null) {
          return;
        }

        state.selectedMonth = nextMonth;
        state.hoverMonth = nextMonth;
        state.hoverElementKey = null;
        updateDetail(nextMonth);
        draw();
      };

      canvas.addEventListener('mousemove', handleMove);
      canvas.addEventListener('click', handleClick);
      canvas.addEventListener('keydown', handleKeydown);
      canvas.addEventListener('mouseleave', function () {
        state.hoverMonth = null;
        state.hoverElementKey = null;
        hideTooltip(tooltip);
        draw();
      });
      canvas.addEventListener('blur', function () {
        hideTooltip(tooltip);
      });
    }

    function renderAll() {
      drawFinancialChart();
      drawPeopleChart();
    }

    updateFinancialDetail(financialState.selectedMonth);
    updatePeopleDetail(peopleState.selectedMonth);
    renderAll();

    bindChartInteractions({
      canvas: financialCanvas,
      wrap: financialWrap,
      tooltip: financialTooltip,
      data: monthlyData,
      state: financialState,
      draw: drawFinancialChart,
      buildTooltipData: financialTooltipData,
      updateDetail: updateFinancialDetail
    });

    bindChartInteractions({
      canvas: peopleCanvas,
      wrap: peopleWrap,
      tooltip: peopleTooltip,
      data: peopleData,
      state: peopleState,
      draw: drawPeopleChart,
      buildTooltipData: peopleTooltipData,
      updateDetail: updatePeopleDetail
    });

      var scheduleRender = (chartPattern && typeof chartPattern.createResizeScheduler === 'function')
        ? chartPattern.createResizeScheduler(renderAll)
        : (function () {
            var resizeToken = null;
            return function () {
              if (resizeToken !== null) {
                window.cancelAnimationFrame(resizeToken);
              }
              resizeToken = window.requestAnimationFrame(function () {
                resizeToken = null;
                renderAll();
              });
            };
          })();

      window.addEventListener('resize', scheduleRender);
      if (typeof window.ResizeObserver === 'function') {
        var observer = new window.ResizeObserver(scheduleRender);
        observer.observe(financialWrap);
        observer.observe(peopleWrap);
      }
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
  })();
</script>
