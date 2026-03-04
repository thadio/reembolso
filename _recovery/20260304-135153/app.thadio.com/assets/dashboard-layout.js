(() => {
  const root = document.querySelector('[data-dashboard-layout-root]');
  if (!root) {
    return;
  }

  const layoutDataEl = document.getElementById('dashboard-layout-data');
  let initialData = {};
  if (layoutDataEl) {
    try {
      initialData = JSON.parse(layoutDataEl.textContent || '{}');
    } catch (err) {
      console.warn('Layout salvo inválido, usando padrão', err);
      initialData = {};
    }
  }
  const defaultColumns = Number(root.dataset.layoutColumns) || 3;
  const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
  const clampRows = (value) => clamp(Number.isFinite(value) ? value : 1, 1, 6);

  const state = {
    columns: clamp(Number(initialData.columns ?? defaultColumns), 1, 6),
    items: Array.isArray(initialData.items) ? initialData.items.slice() : [],
  };

  const readItems = () => Array.from(root.querySelectorAll('[data-layout-item]'));

  const ensureState = () => {
    const nodes = readItems();
    state.items = nodes.map((node) => {
      const id = node.dataset.layoutId;
      const existing = state.items.find((entry) => entry.id === id);
      const defaultSpan = parseInt(node.dataset.layoutDefaultSpan, 10) || 1;
      const defaultRows = parseInt(node.dataset.layoutDefaultRows, 10) || 1;
      const span = existing ? clamp(existing.span, 1, state.columns) : clamp(defaultSpan, 1, state.columns);
      const rows = existing && typeof existing.rows !== 'undefined'
        ? clampRows(existing.rows)
        : clampRows(defaultRows);
      return { id, span, rows };
    });
  };

  const applyState = () => {
    ensureState();
    root.style.setProperty('--dashboard-columns', state.columns);
    root.dataset.layoutColumns = state.columns;
    state.items.forEach(({ id, span, rows }) => {
      const node = root.querySelector(`[data-layout-id="${id}"]`);
      if (!node) {
        return;
      }
      const normalizedSpan = clamp(span, 1, state.columns);
      const normalizedRows = clampRows(rows);
      node.dataset.layoutSpan = normalizedSpan;
      node.dataset.layoutRows = normalizedRows;
      node.style.setProperty('--widget-span', normalizedSpan);
      node.style.setProperty('--widget-rows', normalizedRows);
      node.style.gridRowEnd = `span ${normalizedRows}`;
    });
    state.items.forEach(({ id }) => {
      const node = root.querySelector(`[data-layout-id="${id}"]`);
      if (node && node.parentElement === root) {
        root.appendChild(node);
      } else if (node) {
        root.appendChild(node);
      }
    });
  };

  const hasEditor = Boolean(document.querySelector('[data-layout-edit-toggle]'));
  let saveTimer = null;
  const scheduleSave = () => {
    if (!hasEditor) {
      return;
    }
    if (saveTimer) {
      clearTimeout(saveTimer);
    }
    saveTimer = setTimeout(() => {
      saveTimer = null;
      fetch('dashboard-layout-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          columns: state.columns,
          items: state.items,
        }),
        credentials: 'same-origin',
      }).catch((error) => {
        console.error('Não foi possível salvar o layout', error);
      });
    }, 380);
  };

  applyState();

  if (!hasEditor) {
    return;
  }

  const editButton = document.querySelector('[data-layout-edit-toggle]');
  const columnLabel = document.querySelector('[data-layout-column-label]');
  const columnDecrease = document.querySelector('[data-layout-column-decrease]');
  const columnIncrease = document.querySelector('[data-layout-column-increase]');
  const updateColumnLabel = () => {
    if (!columnLabel) {
      return;
    }
    const suffix = state.columns > 1 ? 'colunas' : 'coluna';
    columnLabel.textContent = `${state.columns} ${suffix}`;
  };

  const createControls = (node) => {
    if (node.querySelector('[data-layout-controls]')) {
      return;
    }
    const controls = document.createElement('div');
    controls.className = 'layout-controls';
    controls.dataset.layoutControls = '';
    controls.innerHTML = `
      <button type="button" class="layout-control" data-layout-size-adjust data-size="-1" aria-label="Diminuir largura">−</button>
      <button type="button" class="layout-control" data-layout-size-adjust data-size="1" aria-label="Aumentar largura">+</button>
      <button type="button" class="layout-control" data-layout-height-adjust data-size="-1" aria-label="Diminuir altura">^</button>
      <button type="button" class="layout-control" data-layout-height-adjust data-size="1" aria-label="Aumentar altura">v</button>
    `;
    node.appendChild(controls);
  };

  const setEditingMode = (active) => {
    root.dataset.editing = active ? 'true' : 'false';
    root.classList.toggle('dashboard-layout--editing', active);
    readItems().forEach((node) => {
      node.setAttribute('draggable', active ? 'true' : 'false');
      if (active) {
        createControls(node);
      } else {
        const controls = node.querySelector('[data-layout-controls]');
        if (controls) {
          controls.remove();
        }
        node.classList.remove('is-dragging');
      }
    });
    if (editButton) {
      editButton.classList.toggle('is-active', active);
      editButton.setAttribute('aria-pressed', active ? 'true' : 'false');
      editButton.textContent = active ? 'Salvar layout' : 'Editar layout';
    }
  };

  let editing = false;
  const toggleEditing = () => {
    editing = !editing;
    setEditingMode(editing);
    if (!editing) {
      applyState();
      scheduleSave();
    }
  };

  editButton?.addEventListener('click', () => toggleEditing());

  const adjustSpan = (node, delta) => {
    const id = node.dataset.layoutId;
    const entry = state.items.find((item) => item.id === id);
    if (!entry) {
      return;
    }
    entry.span = clamp(entry.span + delta, 1, state.columns);
    applyState();
    scheduleSave();
  };

  const adjustRows = (node, delta) => {
    const id = node.dataset.layoutId;
    const entry = state.items.find((item) => item.id === id);
    if (!entry) {
      return;
    }
    const current = Number.isFinite(entry.rows) ? entry.rows : 1;
    entry.rows = clampRows(current + delta);
    applyState();
    scheduleSave();
  };

  root.addEventListener('click', (event) => {
    const control = event.target.closest('[data-layout-size-adjust],[data-layout-height-adjust]');
    if (!control) {
      return;
    }
    const node = control.closest('[data-layout-item]');
    if (!node) {
      return;
    }
    event.preventDefault();
    const delta = Number(control.dataset.size) || 0;
    if (control.hasAttribute('data-layout-size-adjust')) {
      adjustSpan(node, delta);
    } else if (control.hasAttribute('data-layout-height-adjust')) {
      adjustRows(node, delta);
    }
  });

  columnIncrease?.addEventListener('click', () => {
    state.columns = clamp(state.columns + 1, 1, 6);
    applyState();
    updateColumnLabel();
    scheduleSave();
  });
  columnDecrease?.addEventListener('click', () => {
    state.columns = clamp(state.columns - 1, 1, 6);
    applyState();
    updateColumnLabel();
    scheduleSave();
  });

  updateColumnLabel();

  let dragItem = null;

  const finalizeDrag = () => {
    if (!dragItem) {
      return;
    }
    dragItem.classList.remove('is-dragging');
    dragItem = null;
    applyState();
    scheduleSave();
  };

  root.addEventListener('dragstart', (event) => {
    if (!editing) {
      event.preventDefault();
      return;
    }
    dragItem = event.target.closest('[data-layout-item]');
    if (!dragItem) {
      return;
    }
    dragItem.classList.add('is-dragging');
    event.dataTransfer?.setData('text/plain', dragItem.dataset.layoutId || '');
    event.dataTransfer?.setDragImage(dragItem, dragItem.offsetWidth / 2, dragItem.offsetHeight / 2);
  });

  root.addEventListener('dragover', (event) => {
    if (!editing || !dragItem) {
      return;
    }
    event.preventDefault();
    const target = event.target.closest('[data-layout-item]');
    if (!target || target === dragItem) {
      return;
    }
    const rect = target.getBoundingClientRect();
    const before = event.clientY < rect.top + rect.height / 2;
    const reference = before ? target : target.nextSibling;
    root.insertBefore(dragItem, reference);
  });

  root.addEventListener('drop', (event) => {
    if (!editing) {
      return;
    }
    event.preventDefault();
    finalizeDrag();
  });

  root.addEventListener('dragend', () => finalizeDrag());

  root.addEventListener('dragenter', (event) => {
    if (!editing || !dragItem) {
      return;
    }
    event.preventDefault();
  });

  root.addEventListener('drag', (event) => {
    if (!editing || !dragItem) {
      return;
    }
    event.preventDefault();
  });
})();
