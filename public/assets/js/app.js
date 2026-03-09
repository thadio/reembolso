(() => {
  window.setTimeout(() => {
    document.querySelectorAll('.toast').forEach((toast) => {
      toast.style.transition = 'opacity 0.4s ease';
      toast.style.opacity = '0';
      window.setTimeout(() => toast.remove(), 450);
    });
  }, 4500);

  const updateDropzoneText = (zone, input) => {
    const textNode = zone.querySelector('.dropzone-text');
    if (!textNode) {
      return;
    }

    const fallback = zone.getAttribute('data-default-text') || 'Arraste e solte arquivos aqui ou clique para selecionar.';
    const count = input.files ? input.files.length : 0;

    if (count <= 0) {
      textNode.textContent = fallback;
      return;
    }

    textNode.textContent = `${count} arquivo(s) selecionado(s).`;
  };

  document.querySelectorAll('.dropzone').forEach((zone) => {
    const inputId = zone.getAttribute('data-input-id');
    if (!inputId) {
      return;
    }

    const input = document.getElementById(inputId);
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    const textNode = zone.querySelector('.dropzone-text');
    if (textNode) {
      zone.setAttribute('data-default-text', textNode.textContent || '');
    }

    zone.addEventListener('click', (event) => {
      if (event.target === input) {
        return;
      }

      input.click();
    });

    zone.addEventListener('dragover', (event) => {
      event.preventDefault();
      zone.classList.add('is-dragover');
    });

    zone.addEventListener('dragleave', () => {
      zone.classList.remove('is-dragover');
    });

    zone.addEventListener('drop', (event) => {
      event.preventDefault();
      zone.classList.remove('is-dragover');

      if (!event.dataTransfer || event.dataTransfer.files.length === 0) {
        return;
      }

      try {
        if (typeof DataTransfer === 'function') {
          const transfer = new DataTransfer();
          Array.from(event.dataTransfer.files).forEach((file) => transfer.items.add(file));
          input.files = transfer.files;
        } else {
          input.files = event.dataTransfer.files;
        }
      } catch (error) {
        return;
      }

      updateDropzoneText(zone, input);
    });

    input.addEventListener('change', () => updateDropzoneText(zone, input));
  });

  const createChartPatternApi = () => {
    const toNumber = (value) => {
      const numeric = Number(value ?? 0);
      return Number.isFinite(numeric) ? numeric : 0;
    };

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    const createFormatters = (locale = 'pt-BR', currency = 'BRL') => {
      let numberFormatter = null;
      let moneyFormatter = null;

      try {
        numberFormatter = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });
        moneyFormatter = new Intl.NumberFormat(locale, {
          style: 'currency',
          currency,
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      } catch (error) {
      }

      const formatNumber = (value) => {
        const numeric = Math.round(Math.max(0, toNumber(value)));
        return numberFormatter ? numberFormatter.format(numeric) : String(numeric);
      };

      const formatCurrency = (value) => {
        const numeric = Math.max(0, toNumber(value));
        if (moneyFormatter) {
          return moneyFormatter.format(numeric);
        }

        return `R$ ${numeric.toFixed(2)}`;
      };

      const formatPercent = (value, digits = 1) => {
        const numeric = Math.max(0, toNumber(value));
        return `${numeric.toFixed(digits).replace('.', ',')}%`;
      };

      const formatCompactMoney = (value) => {
        const numeric = Math.max(0, toNumber(value));
        if (numeric >= 1000000) {
          return `R$ ${(numeric / 1000000).toFixed(1).replace('.', ',')} mi`;
        }
        if (numeric >= 1000) {
          return `R$ ${(numeric / 1000).toFixed(1).replace('.', ',')} mil`;
        }

        return `R$ ${Math.round(numeric).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
      };

      return {
        formatNumber,
        formatCurrency,
        formatPercent,
        formatCompactMoney,
      };
    };

    const setupCanvas = (canvas, options = {}) => {
      if (!(canvas instanceof HTMLCanvasElement)) {
        return null;
      }

      const rect = canvas.getBoundingClientRect();
      const minWidth = Math.max(1, Number(options.minWidth ?? 280));
      const minHeight = Math.max(1, Number(options.minHeight ?? 220));
      const maxDpr = Math.max(1, Number(options.maxDpr ?? 2));
      const width = Math.max(minWidth, Math.floor(rect.width));
      const height = Math.max(minHeight, Math.floor(rect.height || 320));
      const dpr = clamp(window.devicePixelRatio || 1, 1, maxDpr);
      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;

      const ctx = canvas.getContext('2d');
      if (!ctx) {
        return null;
      }

      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      return {
        ctx,
        width,
        height,
      };
    };

    const niceStep = (maxValue, lines = 5) => {
      const raw = Math.max(1, toNumber(maxValue)) / Math.max(1, toNumber(lines));
      const exponent = Math.floor(Math.log10(raw));
      const magnitude = 10 ** exponent;
      const residual = raw / magnitude;
      let niceResidual = 1;

      if (residual > 5) {
        niceResidual = 10;
      } else if (residual > 2) {
        niceResidual = 5;
      } else if (residual > 1) {
        niceResidual = 2;
      }

      return niceResidual * magnitude;
    };

    const buildScale = (maxValue, lines = 5) => {
      const step = niceStep(maxValue, lines);
      const max = Math.max(step, Math.ceil(Math.max(1, toNumber(maxValue)) / step) * step);

      return {
        max,
        step,
        lines,
      };
    };

    const buildLayout = (width, height, leftPadding, options = {}) => {
      const rightPadding = toNumber(options.rightPadding ?? 16);
      const topPadding = toNumber(options.topPadding ?? 20);
      const bottomPadding = toNumber(options.bottomPadding ?? 42);
      const left = toNumber(leftPadding);
      const right = Math.max(left + 100, toNumber(width) - rightPadding);
      const top = topPadding;
      const bottom = toNumber(height) - bottomPadding;

      return {
        left,
        right,
        top,
        bottom,
        plotWidth: right - left,
        plotHeight: bottom - top,
      };
    };

    const yByValue = (layout, scale, value) => {
      return layout.bottom - ((Math.max(0, toNumber(value)) / Math.max(1, toNumber(scale.max))) * layout.plotHeight);
    };

    const drawAxesAndGrid = (ctx, layout, scale, slots, yFormatter, options = {}) => {
      const palette = {
        grid: '#dce6f0',
        axis: '#8fa0b3',
        marker: '#4f6275',
        verticalGrid: '#d3e1ec',
        ...(typeof options.palette === 'object' && options.palette ? options.palette : {}),
      };
      const font = String(options.font || '11px sans-serif');

      ctx.save();
      ctx.strokeStyle = palette.grid;
      ctx.lineWidth = 1;
      ctx.fillStyle = palette.marker;
      ctx.font = font;
      ctx.textAlign = 'right';
      ctx.textBaseline = 'middle';

      for (let line = 0; line <= scale.lines; line += 1) {
        const value = scale.step * line;
        const y = yByValue(layout, scale, value);
        ctx.beginPath();
        ctx.moveTo(layout.left, y);
        ctx.lineTo(layout.right, y);
        ctx.stroke();
        ctx.fillText(yFormatter(value), layout.left - 10, y);
      }

      if (Array.isArray(slots) && slots.length > 0) {
        ctx.save();
        ctx.setLineDash([3, 5]);
        ctx.strokeStyle = palette.verticalGrid;
        slots.forEach((slot) => {
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
    };

    const drawSlotHighlights = (ctx, layout, slots, selectedKey, hoverKey, options = {}) => {
      const selectedFill = String(options.selectedFill || 'rgba(15, 111, 168, 0.12)');
      const hoverFill = String(options.hoverFill || 'rgba(15, 111, 168, 0.18)');
      const keyField = String(options.keyField || 'month');

      slots.forEach((slot) => {
        if (slot[keyField] === selectedKey) {
          ctx.fillStyle = selectedFill;
          ctx.fillRect(slot.startX + 1, layout.top + 1, slot.width - 2, layout.plotHeight - 2);
        }
      });

      slots.forEach((slot) => {
        if (slot[keyField] === hoverKey) {
          ctx.fillStyle = hoverFill;
          ctx.fillRect(slot.startX + 1, layout.top + 1, slot.width - 2, layout.plotHeight - 2);
        }
      });
    };

    const drawXLabels = (ctx, layout, slots, options = {}) => {
      const color = String(options.color || '#334155');
      const font = String(options.font || '12px sans-serif');
      const keyField = String(options.keyField || 'label');
      const offsetY = toNumber(options.offsetY ?? 10);

      ctx.save();
      ctx.fillStyle = color;
      ctx.font = font;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';
      slots.forEach((slot) => {
        ctx.fillText(String(slot[keyField] || ''), slot.centerX, layout.bottom + offsetY);
      });
      ctx.restore();
    };

    const hideTooltip = (node) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }

      node.hidden = true;
      node.innerHTML = '';
    };

    const renderTooltip = (node, title, lines) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }

      node.innerHTML = '';
      const titleNode = document.createElement('div');
      titleNode.className = 'dashboard-chart-tooltip-title';
      titleNode.textContent = String(title || '');
      node.appendChild(titleNode);

      (Array.isArray(lines) ? lines : []).forEach((line) => {
        const lineNode = document.createElement('div');
        lineNode.className = 'dashboard-chart-tooltip-row';
        lineNode.textContent = String(line || '');
        node.appendChild(lineNode);
      });
    };

    const showTooltip = (node, wrap, pointerX, pointerY, title, lines, options = {}) => {
      if (!(node instanceof HTMLElement) || !(wrap instanceof HTMLElement)) {
        return;
      }

      const margin = toNumber(options.margin ?? 10);
      renderTooltip(node, title, lines);
      node.hidden = false;

      let left = toNumber(pointerX) + 16;
      let top = toNumber(pointerY) - 10;
      const maxLeft = wrap.clientWidth - node.offsetWidth - margin;
      const maxTop = wrap.clientHeight - node.offsetHeight - margin;

      if (left > maxLeft) {
        left = toNumber(pointerX) - node.offsetWidth - 16;
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

      node.style.left = `${left}px`;
      node.style.top = `${top}px`;
    };

    const pointFromEvent = (canvas, event) => {
      const rect = canvas.getBoundingClientRect();
      return {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
      };
    };

    const findSlotByX = (slots, x) => {
      if (!Array.isArray(slots)) {
        return null;
      }

      for (let index = 0; index < slots.length; index += 1) {
        const slot = slots[index];
        if (x >= slot.startX && x <= slot.endX) {
          return slot;
        }
      }

      return null;
    };

    const findElementByPoint = (elements, x, y) => {
      if (!Array.isArray(elements)) {
        return null;
      }

      for (let index = elements.length - 1; index >= 0; index -= 1) {
        const element = elements[index];
        if (element.type === 'point') {
          const dx = x - toNumber(element.x);
          const dy = y - toNumber(element.y);
          const limit = toNumber(element.radius || 5) + 4;
          if ((dx * dx) + (dy * dy) <= limit * limit) {
            return element;
          }
        } else if (
          x >= toNumber(element.x)
          && x <= toNumber(element.x) + toNumber(element.width)
          && y >= toNumber(element.y)
          && y <= toNumber(element.y) + toNumber(element.height)
        ) {
          return element;
        }
      }

      return null;
    };

    const isInsidePlot = (layout, x, y) => {
      if (!layout) {
        return false;
      }

      return x >= layout.left && x <= layout.right && y >= layout.top && y <= layout.bottom;
    };

    const bindChartInteractions = (options) => {
      const canvas = options.canvas;
      const wrap = options.wrap;
      const tooltip = options.tooltip;
      const state = options.state;
      const draw = options.draw;
      const data = Array.isArray(options.data) ? options.data : [];
      const keyField = String(options.keyField || 'month');
      const findItem = typeof options.findItem === 'function'
        ? options.findItem
        : (items, key) => items.find((item) => item[keyField] === key) || null;
      const getTooltipData = typeof options.getTooltipData === 'function' ? options.getTooltipData : () => null;
      const onSelect = typeof options.onSelect === 'function' ? options.onSelect : () => {};
      const getNextKey = typeof options.getNextKey === 'function'
        ? options.getNextKey
        : (items, currentKey, direction) => {
          const currentIndex = items.findIndex((item) => item[keyField] === currentKey);
          const startIndex = currentIndex >= 0 ? currentIndex : 0;
          const nextIndex = clamp(startIndex + direction, 0, Math.max(0, items.length - 1));
          return items[nextIndex] ? items[nextIndex][keyField] : null;
        };
      const getSlotAtX = typeof options.getSlotAtX === 'function'
        ? options.getSlotAtX
        : (x) => findSlotByX(state.slots, x);
      const getElementAtPoint = typeof options.getElementAtPoint === 'function'
        ? options.getElementAtPoint
        : (x, y) => findElementByPoint(state.elements, x, y);
      const getSlotKey = typeof options.getSlotKey === 'function'
        ? options.getSlotKey
        : (slot) => slot[keyField];

      if (!(canvas instanceof HTMLCanvasElement)) {
        return () => {};
      }

      const handleMove = (event) => {
        if (!state.layout) {
          return;
        }

        const point = pointFromEvent(canvas, event);
        if (!isInsidePlot(state.layout, point.x, point.y)) {
          if (state.hoverKey !== null || state.hoverElementKey !== null) {
            state.hoverKey = null;
            state.hoverElementKey = null;
            draw();
          }
          hideTooltip(tooltip);
          return;
        }

        const slot = getSlotAtX(point.x);
        if (!slot) {
          hideTooltip(tooltip);
          return;
        }

        const key = getSlotKey(slot);
        const element = getElementAtPoint(point.x, point.y);
        state.hoverKey = key;
        state.hoverElementKey = element ? element.key : null;
        draw();

        const item = findItem(data, key);
        if (!item) {
          hideTooltip(tooltip);
          return;
        }

        const tooltipData = getTooltipData(item, element, key, slot);
        if (!tooltipData || !(wrap instanceof HTMLElement) || !(tooltip instanceof HTMLElement)) {
          hideTooltip(tooltip);
          return;
        }

        showTooltip(tooltip, wrap, point.x, point.y, tooltipData.title, tooltipData.rows);
      };

      const handleClick = (event) => {
        if (!state.layout) {
          return;
        }

        const point = pointFromEvent(canvas, event);
        if (!isInsidePlot(state.layout, point.x, point.y)) {
          return;
        }

        const slot = getSlotAtX(point.x);
        if (!slot) {
          return;
        }

        const key = getSlotKey(slot);
        state.selectedKey = key;
        onSelect(key, slot);
        draw();
      };

      const handleKeydown = (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
          return;
        }

        event.preventDefault();
        const direction = event.key === 'ArrowRight' ? 1 : -1;
        const nextKey = getNextKey(data, state.selectedKey, direction);
        if (nextKey === null || nextKey === undefined) {
          return;
        }

        state.selectedKey = nextKey;
        state.hoverKey = nextKey;
        state.hoverElementKey = null;
        onSelect(nextKey, null);
        draw();
      };

      const handleMouseLeave = () => {
        state.hoverKey = null;
        state.hoverElementKey = null;
        hideTooltip(tooltip);
        draw();
      };

      const handleBlur = () => {
        hideTooltip(tooltip);
      };

      canvas.addEventListener('mousemove', handleMove);
      canvas.addEventListener('click', handleClick);
      canvas.addEventListener('keydown', handleKeydown);
      canvas.addEventListener('mouseleave', handleMouseLeave);
      canvas.addEventListener('blur', handleBlur);

      return () => {
        canvas.removeEventListener('mousemove', handleMove);
        canvas.removeEventListener('click', handleClick);
        canvas.removeEventListener('keydown', handleKeydown);
        canvas.removeEventListener('mouseleave', handleMouseLeave);
        canvas.removeEventListener('blur', handleBlur);
      };
    };

    const createResizeScheduler = (callback) => {
      let token = null;

      return () => {
        if (token !== null) {
          window.cancelAnimationFrame(token);
        }
        token = window.requestAnimationFrame(() => {
          token = null;
          callback();
        });
      };
    };

    const monthIndex = (items, key, keyField = 'month') => {
      if (!Array.isArray(items)) {
        return 0;
      }

      for (let index = 0; index < items.length; index += 1) {
        if (items[index] && items[index][keyField] === key) {
          return index;
        }
      }

      return 0;
    };

    return {
      toNumber,
      clamp,
      createFormatters,
      setupCanvas,
      niceStep,
      buildScale,
      buildLayout,
      yByValue,
      drawAxesAndGrid,
      drawSlotHighlights,
      drawXLabels,
      hideTooltip,
      renderTooltip,
      showTooltip,
      pointFromEvent,
      findSlotByX,
      findElementByPoint,
      isInsidePlot,
      bindChartInteractions,
      createResizeScheduler,
      monthIndex,
    };
  };

  if (!window.ReembolsoChartPattern) {
    window.ReembolsoChartPattern = createChartPatternApi();
  }

  const initializePipelineBpmnEditor = async () => {
    const payload = window.__PIPELINE_BPMN_EDITOR__;
    const container = document.getElementById('pipeline-bpmn-editor');
    if (!payload || !(container instanceof HTMLElement)) {
      return;
    }

    const fitButton = document.getElementById('pipeline-bpmn-fit');
    const saveButton = document.getElementById('pipeline-bpmn-save');
    const statusNode = document.getElementById('pipeline-bpmn-status');
    const saveForm = document.getElementById('pipeline-bpmn-save-form');
    const xmlField = document.getElementById('pipeline-bpmn-xml');

    if (!(saveForm instanceof HTMLFormElement) || !(xmlField instanceof HTMLTextAreaElement)) {
      return;
    }

    const setStatus = (message, isError = false) => {
      if (!(statusNode instanceof HTMLElement)) {
        return;
      }

      statusNode.textContent = message;
      statusNode.classList.toggle('is-error', isError);
    };

    if (typeof window.BpmnJS !== 'function') {
      setStatus('Falha ao carregar a biblioteca BPMN.', true);
      return;
    }

    const toInt = (value, fallback = 0) => {
      const parsed = Number.parseInt(String(value ?? ''), 10);
      return Number.isFinite(parsed) ? parsed : fallback;
    };

    const cleanText = (value) => String(value ?? '').trim();

    const toNodeType = (nodeKind) => {
      if (nodeKind === 'gateway') {
        return 'bpmn:ExclusiveGateway';
      }

      if (nodeKind === 'final') {
        return 'bpmn:EndEvent';
      }

      return 'bpmn:Task';
    };

    const steps = Array.isArray(payload.steps)
      ? payload.steps
        .map((step) => ({
          status_id: toInt(step.status_id, 0),
          status_code: cleanText(step.status_code),
          status_label: cleanText(step.status_label),
          node_kind: cleanText(step.node_kind) || 'activity',
          sort_order: toInt(step.sort_order, 10),
          is_initial: toInt(step.is_initial, 0),
          is_active: toInt(step.is_active, 0),
        }))
        .filter((step) => step.status_id > 0 && step.is_active === 1)
        .sort((a, b) => a.sort_order - b.sort_order || a.status_id - b.status_id)
      : [];

    const transitions = Array.isArray(payload.transitions)
      ? payload.transitions
        .map((transition) => ({
          id: toInt(transition.id, 0),
          from_status_id: toInt(transition.from_status_id, 0),
          to_status_id: toInt(transition.to_status_id, 0),
          transition_label: cleanText(transition.transition_label),
          action_label: cleanText(transition.action_label),
          sort_order: toInt(transition.sort_order, 10),
          is_active: toInt(transition.is_active, 0),
        }))
        .filter((transition) => transition.id > 0 && transition.is_active === 1)
        .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
      : [];

    const modeler = new window.BpmnJS({
      container,
      keyboard: {
        bind: document,
      },
    });

    const fitViewport = () => {
      const canvas = modeler.get('canvas');
      canvas.zoom('fit-viewport', 'auto');
    };

    const createDiagramFromFlowData = async () => {
      await modeler.createDiagram();

      const modeling = modeler.get('modeling');
      const elementRegistry = modeler.get('elementRegistry');
      const canvas = modeler.get('canvas');
      const root = canvas.getRootElement();
      const startEvent = elementRegistry.filter((element) => element.type === 'bpmn:StartEvent')[0] || null;

      if (startEvent) {
        modeling.updateProperties(startEvent, { name: 'Inicio' });
      }

      if (steps.length === 0) {
        setStatus('Sem etapas ativas para desenhar. Cadastre etapas para iniciar o diagrama.', true);
        return;
      }

      const nodeMap = new Map();
      const columns = 4;
      const horizontalGap = 220;
      const verticalGap = 170;

      steps.forEach((step, index) => {
        const x = 220 + ((index % columns) * horizontalGap);
        const y = 190 + (Math.floor(index / columns) * verticalGap);
        const shape = modeling.createShape({ type: toNodeType(step.node_kind) }, { x, y }, root);
        const shapeId = `FlowNode_${step.status_id}`;
        const shapeLabel = step.status_label !== '' ? step.status_label : (step.status_code || shapeId);
        modeling.updateProperties(shape, { id: shapeId, name: shapeLabel });
        nodeMap.set(step.status_id, shape);
      });

      const initialStep = steps.find((step) => step.is_initial === 1) || steps[0];
      if (startEvent && initialStep) {
        const initialShape = nodeMap.get(initialStep.status_id);
        if (initialShape) {
          try {
            modeling.connect(startEvent, initialShape, { type: 'bpmn:SequenceFlow' });
          } catch (error) {
          }
        }
      }

      let hasValidTransition = false;
      transitions.forEach((transition) => {
        const source = nodeMap.get(transition.from_status_id);
        const target = nodeMap.get(transition.to_status_id);
        if (!source || !target || source === target) {
          return;
        }

        try {
          const connection = modeling.connect(source, target, { type: 'bpmn:SequenceFlow' });
          if (connection) {
            hasValidTransition = true;
            const connectionLabel = transition.transition_label || transition.action_label;
            if (connectionLabel !== '') {
              modeling.updateProperties(connection, { name: connectionLabel });
            }
          }
        } catch (error) {
        }
      });

      if (!hasValidTransition && steps.length > 1) {
        for (let index = 0; index < steps.length - 1; index += 1) {
          const source = nodeMap.get(steps[index].status_id);
          const target = nodeMap.get(steps[index + 1].status_id);
          if (!source || !target) {
            continue;
          }

          try {
            modeling.connect(source, target, { type: 'bpmn:SequenceFlow' });
          } catch (error) {
          }
        }
      }
    };

    const saveDiagram = async () => {
      if (!(saveButton instanceof HTMLButtonElement)) {
        return;
      }

      saveButton.disabled = true;
      if (fitButton instanceof HTMLButtonElement) {
        fitButton.disabled = true;
      }

      try {
        setStatus('Gerando XML BPMN...');
        const { xml } = await modeler.saveXML({ format: true });
        if (cleanText(xml) === '') {
          throw new Error('empty_xml');
        }

        xmlField.value = xml;
        setStatus('Salvando diagrama BPMN...');
        saveForm.submit();
      } catch (error) {
        setStatus('Nao foi possivel salvar o diagrama BPMN.', true);
        saveButton.disabled = false;
        if (fitButton instanceof HTMLButtonElement) {
          fitButton.disabled = false;
        }
      }
    };

    if (fitButton instanceof HTMLButtonElement) {
      fitButton.addEventListener('click', () => {
        fitViewport();
      });
    }

    if (saveButton instanceof HTMLButtonElement) {
      saveButton.addEventListener('click', () => {
        void saveDiagram();
      });
    }

    try {
      const storedXml = cleanText(payload.diagram_xml);
      if (storedXml !== '') {
        await modeler.importXML(storedXml);
        setStatus('Diagrama carregado. Arraste elementos para editar e clique em salvar.');
      } else {
        await createDiagramFromFlowData();
        setStatus('Diagrama inicial gerado pelas etapas/transicoes atuais.');
      }
    } catch (error) {
      setStatus('Falha ao importar o XML salvo. Gerando diagrama inicial...', true);

      try {
        await createDiagramFromFlowData();
        setStatus('Diagrama inicial carregado. Ajuste e salve para persistir.');
      } catch (innerError) {
        setStatus('Nao foi possivel inicializar o editor BPMN.', true);
      }
    }

    fitViewport();
  };

  void initializePipelineBpmnEditor();
})();
