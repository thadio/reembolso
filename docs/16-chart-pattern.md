# 16 - Padrao de Graficos Interativos

Este documento define o padrao visual/interativo para novos graficos da aplicacao.

## Base disponivel

A API global fica em `window.ReembolsoChartPattern` e e carregada por `public/assets/js/app.js`.

Funcoes utilitarias principais:
- `createFormatters(locale, currency)`
- `setupCanvas(canvas, options)`
- `buildScale(maxValue, lines)`
- `buildLayout(width, height, leftPadding, options)`
- `yByValue(layout, scale, value)`
- `drawAxesAndGrid(ctx, layout, scale, slots, yFormatter, options)`
- `drawSlotHighlights(ctx, layout, slots, selectedKey, hoverKey, options)`
- `drawXLabels(ctx, layout, slots, options)`
- `findSlotByX(slots, x)`
- `findElementByPoint(elements, x, y)`
- `showTooltip(node, wrap, x, y, title, rows, options)`
- `hideTooltip(node)`
- `createResizeScheduler(callback)`

## Classes CSS reutilizaveis

Use as classes genericas:
- `chart-pattern-legend`
- `chart-pattern-legend-item`
- `chart-pattern-legend-dot`
- `chart-pattern-wrap`
- `chart-pattern-canvas`
- `chart-pattern-tooltip`
- `chart-pattern-detail`

As classes antigas `dashboard-chart-*` continuam suportadas.

## Estrutura minima (HTML)

```html
<div class="chart-pattern-legend" aria-hidden="true">
  <span class="chart-pattern-legend-item">
    <span class="chart-pattern-legend-dot is-real"></span>Real
  </span>
</div>
<div class="chart-pattern-wrap" id="meu-chart-wrap">
  <canvas id="meu-chart" class="chart-pattern-canvas" tabindex="0"></canvas>
  <div id="meu-chart-tooltip" class="chart-pattern-tooltip" hidden></div>
</div>
<div id="meu-chart-detail" class="chart-pattern-detail" aria-live="polite"></div>
```

## Checklist para novos graficos

- Responsivo em desktop e mobile.
- Eixo Y com valores visiveis.
- Tooltip no hover.
- Clique para detalhamento.
- Navegacao por teclado (`ArrowLeft`/`ArrowRight`) quando aplicavel.
