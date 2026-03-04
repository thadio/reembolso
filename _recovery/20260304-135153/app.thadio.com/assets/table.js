(() => {
  const selectAll = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const normalizeSearch = (value) => {
    let normalized = String(value ?? '').toLowerCase().trim();
    if (typeof normalized.normalize === 'function') {
      normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    normalized = normalized.replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
    return normalized;
  };
  const buildSearchTokens = (value) => {
    const normalized = normalizeSearch(value);
    return normalized ? normalized.split(' ') : [];
  };
  const matchesSearchTokens = (tokens, searchValue) => {
    if (!tokens.length) return true;
    return tokens.every((token) => searchValue.includes(token));
  };
  let activeMultiPanel = null;
  const rowLinkIgnoreSelector = 'a, button, input, select, textarea, label, summary, details, [data-row-ignore]';
  const rowLinkSelector = 'tr[data-row-href]';

  const getEventElement = (target) => {
    if (target instanceof Element) {
      return target;
    }
    if (target && target.parentElement) {
      return target.parentElement;
    }
    return null;
  };

  const shouldIgnoreRowLink = (target) => {
    const element = getEventElement(target);
    return element ? element.closest(rowLinkIgnoreSelector) : false;
  };

  const getRowFromTarget = (target) => {
    const element = getEventElement(target);
    return element ? element.closest(rowLinkSelector) : null;
  };

  const activateRowLink = (row) => {
    const href = row?.dataset?.rowHref;
    if (href) {
      window.location.assign(href);
    }
  };

  function hasTextSelection() {
    if (typeof window.getSelection !== 'function') {
      return false;
    }
    const selection = window.getSelection();
    return selection && selection.toString().trim() !== '';
  }

  function bindRowLink(row) {
    if (!row || !row.dataset || !row.dataset.rowHref) {
      return;
    }
    if (row.dataset.rowLinkBound === 'true') {
      return;
    }
    row.dataset.rowLinkBound = 'true';
    if (!row.hasAttribute('tabindex')) {
      row.tabIndex = 0;
    }
    row.setAttribute('role', 'link');
    row.addEventListener('click', (event) => {
      if (event.defaultPrevented) {
        return;
      }
      if (shouldIgnoreRowLink(event.target)) {
        return;
      }
      if (hasTextSelection()) {
        return;
      }
      event.preventDefault();
      activateRowLink(row);
    });
    row.addEventListener('keydown', (event) => {
      if (event.defaultPrevented) {
        return;
      }
      if (shouldIgnoreRowLink(event.target)) {
        return;
      }
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      event.preventDefault();
      activateRowLink(row);
    });
  }

  function closeActiveMultiPanel() {
    if (!activeMultiPanel) {
      return;
    }
    const trigger = activeMultiPanel.__trigger;
    activeMultiPanel.dataset.open = 'false';
    if (trigger && typeof trigger.__updateState === 'function') {
      trigger.__updateState(false);
    }
    activeMultiPanel = null;
  }

  document.addEventListener('click', (event) => {
    if (!activeMultiPanel) {
      return;
    }
    if (activeMultiPanel.contains(event.target)) {
      return;
    }
    if (event.target.closest('.table-multiselect-trigger')) {
      return;
    }
    closeActiveMultiPanel();
  });

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented) {
      return;
    }
    const row = getRowFromTarget(event.target);
    if (!row) {
      return;
    }
    if (shouldIgnoreRowLink(event.target)) {
      return;
    }
    if (hasTextSelection()) {
      return;
    }
    event.preventDefault();
    activateRowLink(row);
  });

  function setupTableScroll(table) {
    let container = table.closest('.table-scroll');
    if (!container) {
      const parent = table.parentElement;
      const canReuseParent = parent && parent.tagName === 'DIV' && parent.children.length === 1 && parent.children[0] === table;
      if (canReuseParent) {
        container = parent;
      } else {
        container = document.createElement('div');
        if (table.parentElement) {
          table.parentElement.insertBefore(container, table);
        }
      }
      container.classList.add('table-scroll');
      container.dataset.tableScroll = 'true';
    }

    let topScroll = container.querySelector('.table-scroll-top');
    if (!topScroll) {
      topScroll = document.createElement('div');
      topScroll.className = 'table-scroll-top';
      topScroll.setAttribute('aria-hidden', 'true');
      const topInner = document.createElement('div');
      topInner.className = 'table-scroll-top-inner';
      topScroll.appendChild(topInner);
    }

    let topInner = topScroll.querySelector('.table-scroll-top-inner');
    if (!topInner) {
      topInner = document.createElement('div');
      topInner.className = 'table-scroll-top-inner';
      topScroll.appendChild(topInner);
    }

    let bodyScroll = container.querySelector('.table-scroll-body');
    if (!bodyScroll) {
      bodyScroll = document.createElement('div');
      bodyScroll.className = 'table-scroll-body';
    }

    if (!bodyScroll.contains(table)) {
      bodyScroll.appendChild(table);
    }

    if (topScroll.parentElement !== container) {
      container.insertBefore(topScroll, container.firstChild);
    }

    if (bodyScroll.parentElement !== container) {
      container.appendChild(bodyScroll);
    }

    container.style.overflow = 'visible';
    container.style.overflowX = 'visible';
    container.style.overflowY = 'visible';

    const syncWidth = () => {
      topInner.style.width = table.scrollWidth + 'px';
    };

    if (!container.dataset.scrollSynced) {
      let syncing = false;
      const syncScroll = (source, target) => {
        if (syncing) {
          return;
        }
        syncing = true;
        target.scrollLeft = source.scrollLeft;
        syncing = false;
      };
      topScroll.addEventListener('scroll', () => syncScroll(topScroll, bodyScroll));
      bodyScroll.addEventListener('scroll', () => syncScroll(bodyScroll, topScroll));
      container.dataset.scrollSynced = 'true';
    }

    syncWidth();
    if (typeof ResizeObserver !== 'undefined') {
      const observer = new ResizeObserver(syncWidth);
      observer.observe(table);
    } else {
      window.addEventListener('resize', syncWidth);
    }

    return syncWidth;
  }

  function initColumnResize(table, syncWidth) {
    const thead = table.querySelector('thead');
    if (!thead) return;
    const headerRow = thead.querySelector('tr');
    if (!headerRow) return;
    const headers = Array.from(headerRow.children).filter((cell) => cell.tagName === 'TH');
    if (headers.length === 0) return;

    const getCellsForColumn = (index) => {
      const cells = [];
      table.querySelectorAll('tr').forEach((row) => {
        const cell = row.children[index];
        if (!cell) {
          return;
        }
        if (cell.colSpan && cell.colSpan > 1) {
          return;
        }
        cells.push(cell);
      });
      return cells;
    };

    headers.forEach((th, index) => {
      if (th.querySelector('.table-resize-handle')) {
        return;
      }
      const handle = document.createElement('span');
      handle.className = 'table-resize-handle';
      th.classList.add('table-resizable');
      th.appendChild(handle);
      handle.addEventListener('click', (event) => event.stopPropagation());
      handle.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const startX = event.clientX;
        const startWidth = th.getBoundingClientRect().width;
        const minWidth = Math.max(60, parseFloat(getComputedStyle(th).minWidth) || 0);
        const cells = getCellsForColumn(index);
        document.body.classList.add('table-resizing');

        const onMove = (moveEvent) => {
          const nextWidth = Math.max(minWidth, startWidth + (moveEvent.clientX - startX));
          cells.forEach((cell) => {
            cell.style.width = `${nextWidth}px`;
          });
          if (syncWidth) {
            syncWidth();
          }
        };

        const onUp = () => {
          document.body.classList.remove('table-resizing');
          document.removeEventListener('pointermove', onMove);
          document.removeEventListener('pointerup', onUp);
          document.removeEventListener('pointercancel', onUp);
        };

        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
        document.addEventListener('pointercancel', onUp);
      });
    });
  }

  function initTable(table) {
    const syncWidth = setupTableScroll(table);
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    initColumnResize(table, syncWidth);

    const headers = selectAll('thead tr:nth-of-type(2) th[data-sort-key], thead tr th[data-sort-key]', table);
    headers.forEach((header) => {
      if (!header.dataset.headerLabel) {
        header.dataset.headerLabel = (header.textContent || '').trim();
      }
    });
    const filterInputs = new Map();
    selectAll('[data-filter-col]', table).forEach((el) => filterInputs.set(el.dataset.filterCol, el));
    let tableScope = table.closest('[data-table-scope]') || table.closest('.panel') || table.closest('main');
    if (!tableScope) {
      if (table.parentElement && table.parentElement.parentElement) {
        tableScope = table.parentElement.parentElement;
      } else {
        tableScope = document;
      }
    }
    const globalFilter = table.querySelector('[data-filter-global]') || tableScope.querySelector('[data-filter-global]');
    const serverMode = table.dataset.filterMode === 'server';
    const isTrashView = table.dataset.tableTrashView === 'true';
    const currentUrl = new URL(window.location.href);
    const pageParamName = table.dataset.pageParam || 'page';
    const defaultSearchParam = table.dataset.searchParam || 'q';
    const sortKeyParamName = table.dataset.sortKeyParam || 'sort_key';
    const sortDirParamName = table.dataset.sortDirParam || 'sort_dir';
    let remoteOptions = {};
    if (serverMode && table.dataset && table.dataset.remoteOptions) {
      try {
        const parsed = JSON.parse(table.dataset.remoteOptions || '{}');
        if (parsed && typeof parsed === 'object') {
          remoteOptions = parsed;
        }
      } catch (err) {
        remoteOptions = {};
      }
    }
    const hasRemoteOptions = Object.keys(remoteOptions).length > 0;
    const multiSelectEnabled = !serverMode || hasRemoteOptions;

    const assignIfChanged = (url) => {
      const target = url.toString();
      if (target === window.location.href) {
        return;
      }
      window.location.assign(target);
    };

    const rows = selectAll('tbody tr', table).filter((row) => !row.classList.contains('no-results'));
    const rowData = rows.map((row) => {
      const cells = selectAll('td', row);
      const rawValues = cells.map((cell) => {
        if (cell.dataset.value !== undefined) {
          return String(cell.dataset.value).trim();
        }
        return cell.textContent.trim();
      });
      return {
        row,
        text: normalizeSearch(row.textContent),
        values: rawValues.map((val) => normalizeSearch(val)),
        rawValues,
      };
    });

    const uniqueValueCache = new Map();

    const state = {
      sortKey: null,
      sortDir: 'asc',
      filters: {},
      globalTokens: [],
      multi: {},
      optionFilters: {},
    };

    if (serverMode) {
      const initialSortKey = currentUrl.searchParams.get(sortKeyParamName) || null;
      const initialSortDirRaw = currentUrl.searchParams.get(sortDirParamName) || '';
      const initialSortDir = String(initialSortDirRaw).toLowerCase() === 'desc' ? 'desc' : 'asc';
      if (initialSortKey) {
        state.sortKey = initialSortKey;
        state.sortDir = initialSortDir;
      }
    }

    const normalizeValue = (value) => String(value ?? '').trim().toLowerCase();
    const displayValue = (value) => {
      const text = String(value ?? '').trim();
      return text === '' ? 'Em branco' : text;
    };
    const parseNumberValue = (value) => {
      if (window.RetratoNumber && typeof window.RetratoNumber.parse === 'function') {
        return window.RetratoNumber.parse(value);
      }
      const cleaned = String(value ?? '').trim();
      if (!cleaned) return null;
      const normalized = cleaned.replace(/[^0-9,.\-+]/g, '');
      const parsed = parseFloat(normalized.replace(',', '.'));
      return Number.isFinite(parsed) ? parsed : null;
    };

    function getColumnIndex(key) {
      const header = headers.find((h) => h.dataset.sortKey === key);
      if (!header) return -1;
      const headerRow = header.parentElement;
      const ths = selectAll('th', headerRow);
      return ths.indexOf(header);
    }

    function getColumnValues(key) {
      if (uniqueValueCache.has(key)) {
        return uniqueValueCache.get(key);
      }
      const idx = getColumnIndex(key);
      if (idx === -1) return [];
      // In server mode, multi-select must only be enabled with remote options.
      // Reading values from the current page would create hidden truncation.
      if (serverMode) {
        if (!multiSelectEnabled) {
          uniqueValueCache.set(key, []);
          return [];
        }
        if (Array.isArray(remoteOptions[key])) {
          uniqueValueCache.set(key, remoteOptions[key]);
          return remoteOptions[key];
        }
      }
      const map = new Map();
      rowData.forEach(({ rawValues }) => {
        const raw = rawValues[idx] !== undefined && rawValues[idx] !== null ? rawValues[idx] : '';
        const normalized = normalizeValue(raw);
        const label = displayValue(raw);
        if (!map.has(normalized)) {
          map.set(normalized, { normalized, label, search: normalizeSearch(label), count: 0 });
        }
        map.get(normalized).count += 1;
      });
      const values = Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label, 'pt-BR', { sensitivity: 'base' }));
      uniqueValueCache.set(key, values);
      return values;
    }

    const isStatusColumn = (key) => {
      if (!key) return false;
      const header = headers.find((h) => h.dataset.sortKey === key);
      const headerText = (header?.dataset.headerLabel || header?.textContent || '').toLowerCase();
      return key.toLowerCase().includes('status') || headerText.includes('status');
    };

    function defaultSelectionForColumn(key) {
      const options = getColumnValues(key);
      if (options.length === 0) {
        return new Set();
      }
      const selection = new Set();
      if (isStatusColumn(key)) {
        const hasTrash = options.some((opt) => opt.normalized === 'trash');
        if (isTrashView && hasTrash) {
          selection.add('trash');
          return selection;
        }
        options.forEach((opt) => {
          if (opt.normalized !== 'trash') {
            selection.add(opt.normalized);
          }
        });
        if (selection.size === 0) {
          options.forEach((opt) => selection.add(opt.normalized));
        }
        return selection;
      }
      options.forEach((opt) => selection.add(opt.normalized));
      return selection;
    }

    headers.forEach((header) => {
      if (!multiSelectEnabled) {
        return;
      }
      const key = header.dataset.sortKey;
      state.multi[key] = defaultSelectionForColumn(key);
      state.optionFilters[key] = '';
    });

    if (serverMode) {
      if (globalFilter && !globalFilter.value) {
        const initialGlobal = currentUrl.searchParams.get(defaultSearchParam) || '';
        if (initialGlobal) {
          globalFilter.value = initialGlobal;
        }
      }
      filterInputs.forEach((input, key) => {
        const param = input.dataset.queryParam || `filter_${key}`;
        if (!input.value) {
          const raw = currentUrl.searchParams.get(param) || '';
          if (raw) {
            input.value = raw;
          }
        }
        const rawSelection = currentUrl.searchParams.get(param) || '';
        if (rawSelection) {
          const parsed = rawSelection
            .split(',')
            .map((value) => normalizeValue(value))
            .filter((value) => value !== '');
          if (parsed.length > 0) {
            // Only mirror URL values into multiselect state when they match
            // real options (avoids forcing typed free-text inputs as checkbox state).
            const options = getColumnValues(key);
            const optionSet = new Set(options.map((opt) => opt.normalized));
            const allMatch = parsed.every((value) => optionSet.has(value));
            if (allMatch) {
              state.multi[key] = new Set(parsed);
            }
          }
        }
      });
    }

    function wrapHeaderContent(header) {
      let content = header.querySelector('.table-header-content');
      if (!content) {
        content = document.createElement('span');
        content.className = 'table-header-content';
        while (header.firstChild) {
          content.appendChild(header.firstChild);
        }
        header.appendChild(content);
      }
      let label = content.querySelector('.table-header-label');
      if (!label) {
        label = document.createElement('span');
        label.className = 'table-header-label';
        while (content.firstChild) {
          label.appendChild(content.firstChild);
        }
        content.appendChild(label);
      }
      return { content, label };
    }

    const isColumnFiltered = (key) => {
      const options = getColumnValues(key);
      if (options.length === 0) {
        return false;
      }
      const selection = state.multi[key] || new Set();
      return selection.size > 0 && selection.size < options.length;
    };

    function updateTriggerState(trigger, key, isOpen = false) {
      const filtered = isColumnFiltered(key);
      const active = isOpen || filtered;
      trigger.classList.toggle('is-active', active);
      trigger.setAttribute('aria-pressed', String(active));
    }

    function applyAria() {
      headers.forEach((h) => {
        h.setAttribute('aria-sort', 'none');
        h.classList.remove('sorted-asc', 'sorted-desc');
        if (state.sortKey === h.dataset.sortKey) {
          const dir = state.sortDir === 'asc' ? 'ascending' : 'descending';
          h.setAttribute('aria-sort', dir);
          h.classList.add(state.sortDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
        }
      });
    }

    function compare(a, b) {
      if (!state.sortKey) return 0;
      const idx = getColumnIndex(state.sortKey);
      if (idx === -1) return 0;
      const va = a.rawValues[idx] !== undefined && a.rawValues[idx] !== null ? a.rawValues[idx] : '';
      const vb = b.rawValues[idx] !== undefined && b.rawValues[idx] !== null ? b.rawValues[idx] : '';
      const na = parseNumberValue(va);
      const nb = parseNumberValue(vb);
      const bothNumeric = na !== null && nb !== null;
      let res = bothNumeric ? na - nb : String(va).localeCompare(String(vb));
      return state.sortDir === 'asc' ? res : -res;
    }

    function matchesFilters(data) {
      if (!serverMode) {
        if (state.globalTokens.length) {
          if (!matchesSearchTokens(state.globalTokens, data.text)) return false;
        }
        for (const [key, tokens] of Object.entries(state.filters)) {
          if (!tokens || tokens.length === 0) continue;
          const idx = getColumnIndex(key);
          if (idx === -1) continue;
          if (!matchesSearchTokens(tokens, data.values[idx])) return false;
        }
      }
      for (const [key, selection] of Object.entries(state.multi)) {
        const options = getColumnValues(key);
        if (options.length === 0) {
          continue;
        }
        const idx = getColumnIndex(key);
        if (idx === -1) continue;
        if (!selection || selection.size === 0) {
          return false;
        }
        const raw = data.rawValues[idx] !== undefined && data.rawValues[idx] !== null ? data.rawValues[idx] : '';
        const normalized = normalizeValue(raw);
        if (!selection.has(normalized)) {
          return false;
        }
      }
      return true;
    }

    function render() {
      const filtered = rowData.filter(matchesFilters);
      filtered.sort(compare);
      tbody.innerHTML = '';
      if (filtered.length === 0) {
        const colSpan = table.querySelectorAll('thead tr:nth-of-type(2) th').length || headers.length || 1;
        const tr = document.createElement('tr');
        tr.className = 'no-results';
        const td = document.createElement('td');
        td.colSpan = colSpan;
        td.textContent = 'Nenhum resultado';
        tr.appendChild(td);
        tbody.appendChild(tr);
        applyAria();
        if (syncWidth) {
          syncWidth();
        }
        return;
      }
      filtered.forEach(({ row }) => tbody.appendChild(row));
      applyAria();
      if (syncWidth) {
        syncWidth();
      }
    }

    const serverFilterDelay = 300;
    const clientFilterDelay = 150;
    const filterDelay = serverMode ? serverFilterDelay : clientFilterDelay;
    const debounce = (fn, delay = filterDelay) => {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
      };
    };

    const applyServerFilters = () => {
      const url = new URL(window.location.href);
      url.searchParams.set(pageParamName, '1');
      if (globalFilter) {
        const value = globalFilter.value.trim();
        if (value) {
          url.searchParams.set(defaultSearchParam, value);
        } else {
          url.searchParams.delete(defaultSearchParam);
        }
      }
      filterInputs.forEach((input, key) => {
        const value = input.value.trim();
        const param = input.dataset.queryParam || `filter_${key}`;
        if (value) {
          url.searchParams.set(param, value);
        } else {
          url.searchParams.delete(param);
        }
      });
      assignIfChanged(url);
    };

    const applyServerFiltersDebounced = debounce(() => {
      applyServerFilters();
    }, serverFilterDelay);

    if (globalFilter) {
      if (serverMode) {
        globalFilter.addEventListener('input', () => applyServerFiltersDebounced());
        globalFilter.addEventListener('change', () => applyServerFilters());
        globalFilter.addEventListener('keydown', (e) => {
          if (e.key !== 'Enter') {
            return;
          }
          e.preventDefault();
          applyServerFilters();
        });
      } else {
        const handler = debounce((e) => {
          state.globalTokens = buildSearchTokens(e.target.value);
          render();
        });
        globalFilter.addEventListener('input', handler);
        globalFilter.addEventListener('keydown', (e) => {
          if (e.key !== 'Enter') {
            return;
          }
          e.preventDefault();
          state.globalTokens = buildSearchTokens(e.target.value);
          render();
        });
      }
    }

    filterInputs.forEach((input, key) => {
      if (serverMode) {
        input.addEventListener('input', () => applyServerFiltersDebounced());
        input.addEventListener('change', () => applyServerFilters());
        input.addEventListener('keydown', (e) => {
          if (e.key !== 'Enter') {
            return;
          }
          e.preventDefault();
          applyServerFilters();
        });
        return;
      }
      const handler = debounce((e) => {
        state.filters[key] = buildSearchTokens(e.target.value);
        render();
      });
      input.addEventListener('input', handler);
      input.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') {
          return;
        }
        e.preventDefault();
        state.filters[key] = buildSearchTokens(e.target.value);
        render();
      });
    });

    headers.forEach((header) => {
      const key = header.dataset.sortKey;
      const headerLabel = header.dataset.headerLabel || (header.textContent || '').trim() || key || 'Coluna';
      const { content } = wrapHeaderContent(header);

      const trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'table-multiselect-trigger';
      trigger.innerHTML = '<svg aria-hidden="true"><use href="#icon-filter-multi"></use></svg>';
      trigger.setAttribute('aria-label', `Filtro multiplo para ${headerLabel}`);
      trigger.title = 'Selecionar multiplos valores';
      trigger.__updateState = (isOpen = false) => updateTriggerState(trigger, key, isOpen);
      content.appendChild(trigger);

      const panel = document.createElement('div');
      const hasStatusShortcut = isStatusColumn(key);
      panel.className = 'table-multiselect-panel';
      panel.dataset.open = 'false';
      panel.dataset.columnKey = key;
      panel.innerHTML = `
        <div class="table-multiselect-header">
          <span class="table-multiselect-title">${headerLabel}</span>
          <button type="button" class="table-multiselect-close" aria-label="Fechar filtro">
            <svg aria-hidden="true"><use href="#icon-x"></use></svg>
          </button>
        </div>
        <div class="table-multiselect-actions">
          <button type="button" data-select-all>Selecionar tudo</button>
          <button type="button" data-clear>Limpar</button>
          ${hasStatusShortcut ? '<button type="button" data-select-active>Sem lixeira</button>' : ''}
        </div>
        <input type="search" class="table-multiselect-search" placeholder="Buscar valores" aria-label="Buscar valores desta coluna">
        <div class="table-multiselect-options" data-options></div>
        <div class="table-multiselect-footer">Selecao multipla ao estilo Excel.</div>
      `;
      panel.__trigger = trigger;
      header.appendChild(panel);

      const optionsEl = panel.querySelector('[data-options]');
      const searchInput = panel.querySelector('.table-multiselect-search');
      const closeBtn = panel.querySelector('.table-multiselect-close');
      const selectAllBtn = panel.querySelector('[data-select-all]');
      const clearBtn = panel.querySelector('[data-clear]');
      const activeBtn = panel.querySelector('[data-select-active]');

      const renderOptions = () => {
        const options = getColumnValues(key);
        const rawSearch = state.optionFilters[key] || '';
        const tokens = buildSearchTokens(rawSearch);
        optionsEl.innerHTML = '';
        const filteredOptions = tokens.length
          ? options.filter((opt) => matchesSearchTokens(tokens, opt.search || normalizeSearch(opt.label)))
          : options;
        if (filteredOptions.length === 0) {
          const empty = document.createElement('div');
          empty.className = 'table-multiselect-empty';
          empty.textContent = tokens.length ? 'Nenhum valor encontrado.' : 'Nenhum valor listado.';
          optionsEl.appendChild(empty);
          return;
        }
        filteredOptions.forEach((opt) => {
          const label = document.createElement('label');
          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.value = opt.normalized;
          checkbox.checked = (state.multi[key] || new Set()).has(opt.normalized);
          checkbox.addEventListener('change', () => {
            const selection = state.multi[key] instanceof Set ? new Set(state.multi[key]) : new Set();
            if (checkbox.checked) {
              selection.add(opt.normalized);
            } else {
              selection.delete(opt.normalized);
            }
            state.multi[key] = selection;
            trigger.__updateState(panel.dataset.open === 'true');
            // If table is in server mode, send selection to server so the filter
            // applies to the whole dataset (not only current page). We encode the
            // selected values as a comma-separated list in `filter_{key}`.
            if (serverMode) {
              const url = new URL(window.location.href);
              url.searchParams.set(pageParamName, '1');
              const param = filterInputs.get(key)?.dataset.queryParam || `filter_${key}`;
              url.searchParams.delete(param);
              if (selection && selection.size > 0) {
                url.searchParams.set(param, Array.from(selection).join(','));
              }
              assignIfChanged(url);
              return;
            }
            render();
          });
          label.appendChild(checkbox);
          const text = document.createElement('span');
          text.textContent = opt.label;
          label.appendChild(text);
          const count = document.createElement('small');
          count.textContent = `(${opt.count})`;
          label.appendChild(count);
          optionsEl.appendChild(label);
        });
      };

      trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = panel.dataset.open === 'true';
        if (activeMultiPanel && activeMultiPanel !== panel) {
          closeActiveMultiPanel();
        }
        if (isOpen) {
          closeActiveMultiPanel();
          return;
        }
        panel.dataset.open = 'true';
        activeMultiPanel = panel;
        if (searchInput) {
          searchInput.value = state.optionFilters[key] || '';
        }
        renderOptions();
        trigger.__updateState(true);
      });

      panel.addEventListener('click', (event) => event.stopPropagation());
      panel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeActiveMultiPanel();
          trigger.focus();
        }
      });

      if (closeBtn) {
        closeBtn.addEventListener('click', () => closeActiveMultiPanel());
      }
      if (searchInput) {
        searchInput.addEventListener('input', (e) => {
          state.optionFilters[key] = e.target.value.trim();
          renderOptions();
        });
      }
      if (selectAllBtn) {
        selectAllBtn.addEventListener('click', () => {
          const next = new Set(getColumnValues(key).map((opt) => opt.normalized));
          state.multi[key] = next;
          if (serverMode) {
            const url = new URL(window.location.href);
            const param = filterInputs.get(key)?.dataset.queryParam || `filter_${key}`;
            url.searchParams.set(pageParamName, '1');
            if (next.size > 0) {
              url.searchParams.set(param, Array.from(next).join(','));
            } else {
              url.searchParams.delete(param);
            }
            assignIfChanged(url);
            return;
          }
          renderOptions();
          trigger.__updateState(panel.dataset.open === 'true');
          render();
        });
      }
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          state.multi[key] = new Set();
          if (serverMode) {
            const input = filterInputs.get(key);
            if (input) {
              input.value = '';
            }
            renderOptions();
            trigger.__updateState(panel.dataset.open === 'true');
            return;
          }
          renderOptions();
          trigger.__updateState(panel.dataset.open === 'true');
          render();
        });
      }
      if (activeBtn) {
        activeBtn.addEventListener('click', () => {
          const options = getColumnValues(key);
          const next = new Set();
          options.forEach((opt) => {
            if (opt.normalized !== 'trash') {
              next.add(opt.normalized);
            }
          });
          if (next.size === 0) {
            options.forEach((opt) => next.add(opt.normalized));
          }
          state.multi[key] = next;
          if (serverMode) {
            const url = new URL(window.location.href);
            const param = filterInputs.get(key)?.dataset.queryParam || `filter_${key}`;
            url.searchParams.set(pageParamName, '1');
            if (next.size > 0) {
              url.searchParams.set(param, Array.from(next).join(','));
            } else {
              url.searchParams.delete(param);
            }
            assignIfChanged(url);
            return;
          }
          renderOptions();
          trigger.__updateState(panel.dataset.open === 'true');
          render();
        });
      }

      trigger.__updateState(false);

      header.addEventListener('click', () => {
        closeActiveMultiPanel();
        const sortKey = header.dataset.sortKey;
        if (serverMode) {
          // Ask server to sort full dataset. Preserve filters and global query, reset to page 1.
          const url = new URL(window.location.href);
          url.searchParams.set(pageParamName, '1');
          const nextDir = state.sortKey === sortKey ? (state.sortDir === 'asc' ? 'desc' : 'asc') : 'asc';
          url.searchParams.set(sortKeyParamName, sortKey);
          url.searchParams.set(sortDirParamName, nextDir);
          assignIfChanged(url);
          return;
        }
        if (state.sortKey === sortKey) {
          state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          state.sortKey = sortKey;
          state.sortDir = 'asc';
        }
        render();
      });
      header.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          header.click();
        }
      });
      header.tabIndex = 0;
    });

    applyAria();
    render();
  }

  document.addEventListener('DOMContentLoaded', () => {
    selectAll('tr[data-row-href]').forEach(bindRowLink);
    selectAll('table[data-table="interactive"]').forEach(initTable);
  });
})();
