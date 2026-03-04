<?php
/** @var array $form */
/** @var array<int, string> $errors */
/** @var array<int, string> $notices */
/** @var string $success */
/** @var array<string, string> $destinationOptions */
/** @var array<string, string> $reasonOptions */
/** @var array<string, array<string, mixed>> $productOptions */
/** @var array<int, array<string, mixed>> $recentWriteOffs */
/** @var array<int, array<string, string>> $termLinks */
/** @var array<string, mixed>|null $initialItem */
/** @var callable $esc */
?>
<?php
  $vendorSuggestionOptions = [];
  foreach ($vendorOptions as $vendor) {
    $vendorPessoaId = (int) ($vendor['id'] ?? 0);
    if ($vendorPessoaId <= 0) {
      continue;
    }
    $vendorSuggestionOptions[] = [
      'id' => $vendorPessoaId,
      'label' => sprintf('%s (Pessoa #%d)', $vendor['full_name'] ?? 'Fornecedor', $vendorPessoaId),
    ];
  }
?>
<div class="page-header">
  <div>
    <h1>Baixa de produto</h1>
    <div class="subtitle">Registrar destinação e motivo enquanto visualiza foto, nome e disponibilidade.</div>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert success"><?php echo $esc($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
<?php endif; ?>
<?php if (!empty($notices)): ?>
  <div class="alert warning"><?php echo $esc(implode(' ', $notices)); ?></div>
<?php endif; ?>

<form method="post" action="produto-baixa.php">
  <div class="card">
    <div class="flow-step-label">1. Buscar e preparar</div>
    <div class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
      <div class="field">
        <label>SKU/Produto</label>
        <div class="autocomplete-picker">
          <input
            type="text"
            id="writeoffProductSku"
            class="autocomplete-input"
            data-writeoff-input="sku"
            data-product-sku
            autocomplete="off"
            aria-autocomplete="list"
            aria-controls="writeoffSkuSuggestions"
            aria-expanded="false"
            placeholder="Informe SKU, código ou nome"
          >
          <div
            id="writeoffSkuSuggestions"
            class="autocomplete-suggestions"
            data-writeoff-suggestions="sku"
            hidden
          ></div>
        </div>
      </div>
      <div class="field">
        <label for="writeoffQuantity">Quantidade</label>
        <input type="number" id="writeoffQuantity" min="1" value="1">
      </div>
      <div class="field">
        <label for="writeoffDestinationDefault">Destinação padrão</label>
        <select id="writeoffDestinationDefault" name="destination_default">
          <?php foreach ($destinationOptions as $key => $label): ?>
            <option value="<?php echo $esc($key); ?>" <?php echo (($form['destination_default'] ?? 'nao_localizado') === $key) ? 'selected' : ''; ?>>
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="writeoffReasonDefault">Motivo padrão</label>
        <select id="writeoffReasonDefault" name="reason_default">
          <?php foreach ($reasonOptions as $key => $label): ?>
            <option value="<?php echo $esc($key); ?>" <?php echo (($form['reason_default'] ?? 'perdido') === $key) ? 'selected' : ''; ?>>
              <?php echo $esc($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="writeoff-product-preview-card">
      <div class="writeoff-product-preview" data-writeoff-preview hidden>
        <div class="writeoff-product-preview__thumb" data-writeoff-preview-thumb></div>
        <div class="writeoff-product-preview__info">
          <div class="writeoff-product-preview__name" data-writeoff-preview-name></div>
          <div class="writeoff-product-preview__meta" data-writeoff-preview-meta></div>
        </div>
      </div>
      <div class="writeoff-product-preview-empty" data-writeoff-preview-empty>
        Selecione um produto para visualizar foto, nome e disponibilidade antes de adicionar.
      </div>
    </div>
    <div class="writeoff-actions">
      <div class="order-item-helper" id="writeoffHelper">Selecione um produto válido.</div>
      <button type="button" class="btn primary" id="writeoffAddProduct">Adicionar à lista</button>
    </div>
  </div>

  <div class="card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
      <div>
        <h2>Lista de baixa</h2>
        <div class="subtitle">Adicione produtos, ajuste quantidades e escolha destinação/motivo antes de enviar.</div>
      </div>
      <button type="button" class="btn ghost" id="writeoffApplyDefaults">Aplicar destino/motivo padrão</button>
    </div>
    <div id="writeoffItemList" class="writeoff-item-list"></div>
  </div>

  <div class="card">
    <div class="field">
      <label for="notes">Observações (opcional)</label>
      <textarea id="notes" name="notes" rows="2" maxlength="500" placeholder="Ex: baixa relacionada a amostra ou lote"><?php echo $esc($form['notes'] ?? ''); ?></textarea>
    </div>
    <div class="footer">
      <button type="submit" class="primary">Registrar baixas</button>
    </div>
  </div>
</form>

<?php if (!empty($termLinks)): ?>
  <div class="card">
    <div>
      <h2>Termos de consignação</h2>
      <div class="subtitle">Clique para abrir o termo de ciência de cada fornecedor.</div>
    </div>
    <ul class="writeoff-term-links">
      <?php foreach ($termLinks as $link): ?>
        <li>
          <a href="<?php echo $esc($link['link'] ?? '#'); ?>" target="_blank" rel="noopener">
            <?php echo $esc($link['product_label'] ?? 'Produto'); ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form
  id="writeoffVendorTermForm"
  method="post"
  action="produto-baixa-termo-fornecedor.php"
  target="_blank"
>
  <div class="card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
      <div>
        <h2>Termo de devolução por fornecedor</h2>
        <div class="subtitle">Escolha quais baixas de cada fornecedor devem constar no documento.</div>
      </div>
    </div>
    <div class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
      <div class="field">
        <label for="writeoffVendorTermSearch">Fornecedor</label>
        <input
          type="text"
          id="writeoffVendorTermSearch"
          class="form-autocomplete"
          placeholder="Digite para buscar..."
          list="writeoffVendorTermList"
          autocomplete="off"
          required
        >
        <datalist id="writeoffVendorTermList">
          <?php foreach ($vendorOptions as $vendor): ?>
            <?php $vendorPessoaId = (int) ($vendor['id'] ?? 0); ?>
            <?php if ($vendorPessoaId <= 0) {
              continue;
            } ?>
            <?php $label = sprintf('%s (Pessoa #%d)', $vendor['full_name'] ?? 'Fornecedor', $vendorPessoaId); ?>
            <option value="<?php echo $esc($label); ?>" data-id="<?php echo $vendorPessoaId; ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <input type="hidden" id="writeoffVendorTermId" name="supplier_pessoa_id" value="">
      </div>
    </div>
    <div id="writeoffVendorTermItems" class="writeoff-vendor-items" style="margin-top:12px;">
      <div class="help-text">Selecione um fornecedor para carregar as baixas devolvidas a ele.</div>
    </div>
    <div class="footer" style="margin-top:12px;">
      <button type="submit" class="primary" id="writeoffVendorTermSubmit" disabled>Gerar termo do fornecedor</button>
    </div>
  </div>
</form>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
    <h2>Últimas baixas</h2>
    <div class="subtitle">Histórico recente das baixas registradas.</div>
  </div>
  <div class="table-wrapper">
    <table data-table="interactive">
      <thead>
        <tr>
          <th data-sort-key="id" aria-sort="none">#</th>
          <th data-sort-key="created_at" aria-sort="none">Data</th>
          <th data-sort-key="sku" aria-sort="none">SKU</th>
          <th data-sort-key="product" aria-sort="none">Produto</th>
          <th data-sort-key="destination" aria-sort="none">Destinação</th>
          <th data-sort-key="reason" aria-sort="none">Motivo</th>
          <th data-sort-key="quantity" aria-sort="none">Qtd</th>
          <th data-sort-key="supplier" aria-sort="none">Fornecedor</th>
          <th class="col-actions">Termo</th>
        </tr>
        <tr>
          <th><input type="search" data-filter-col="id" placeholder="#" aria-label="Filtrar por id"></th>
          <th><input type="search" data-filter-col="created_at" placeholder="Data" aria-label="Filtrar por data"></th>
          <th><input type="search" data-filter-col="sku" placeholder="SKU" aria-label="Filtrar por SKU"></th>
          <th><input type="search" data-filter-col="product" placeholder="Produto" aria-label="Filtrar por produto"></th>
          <th><input type="search" data-filter-col="destination" placeholder="Destinação" aria-label="Filtrar por destinação"></th>
          <th><input type="search" data-filter-col="reason" placeholder="Motivo" aria-label="Filtrar por motivo"></th>
          <th><input type="search" data-filter-col="quantity" placeholder="Qtd" aria-label="Filtrar por quantidade"></th>
          <th><input type="search" data-filter-col="supplier" placeholder="Fornecedor" aria-label="Filtrar por fornecedor"></th>
          <th class="col-actions"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentWriteOffs as $row): ?>
          <?php
            $rowSku = trim((string) ($row['sku'] ?? ''));
            $rowProductLabel = trim((string) ($row['product_name'] ?? ''));
            if ($rowProductLabel === '') {
              $rowRefSku = (string) ($row['sku'] ?? $row['product_sku'] ?? '—');
              $rowProductLabel = $rowSku !== ''
                ? ('SKU ' . $rowSku)
                : ('Produto SKU ' . $rowRefSku);
            }
          ?>
          <tr>
            <td data-value="<?php echo $esc((string) $row['id']); ?>"><?php echo $esc((string) $row['id']); ?></td>
            <td data-value="<?php echo $esc((string) $row['created_at']); ?>"><?php echo $esc((string) $row['created_at']); ?></td>
            <td data-value="<?php echo $esc($rowSku); ?>"><?php echo $esc($rowSku); ?></td>
            <td data-value="<?php echo $esc($rowProductLabel); ?>"><?php echo $esc($rowProductLabel); ?></td>
            <td data-value="<?php echo $esc((string) $row['destination']); ?>"><?php echo $esc($destinationOptions[$row['destination']] ?? $row['destination']); ?></td>
            <td data-value="<?php echo $esc((string) $row['reason']); ?>"><?php echo $esc($reasonOptions[$row['reason']] ?? $row['reason']); ?></td>
            <td data-value="<?php echo $esc((string) $row['quantity']); ?>"><?php echo $esc((string) $row['quantity']); ?></td>
            <td data-value="<?php echo $esc((string) ($row['supplier_name'] ?? '')); ?>"><?php echo $esc($row['supplier_name'] ?? '—'); ?></td>
            <td class="col-actions">
              <?php if (($row['destination'] ?? '') === 'devolucao_fornecedor' && !empty($row['supplier_pessoa_id'])): ?>
                <div class="actions">
                  <a
                    class="icon-btn neutral"
                    href="produto-baixa-termo.php?id=<?php echo (int) $row['id']; ?>"
                    target="_blank"
                    rel="noopener"
                    aria-label="Termo de devolução"
                    title="Termo de devolução"
                  >
                    <svg aria-hidden="true"><use href="#icon-file"></use></svg>
                  </a>
                </div>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  </div>
  <script>
  (function () {
    const productOptions = <?php echo json_encode($productOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
    const destinationOptions = <?php echo json_encode($destinationOptions, JSON_UNESCAPED_UNICODE); ?> || {};
    const reasonOptions = <?php echo json_encode($reasonOptions, JSON_UNESCAPED_UNICODE); ?> || {};
    const vendorTermOptions = <?php echo json_encode($vendorSuggestionOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  const skuInput = document.getElementById('writeoffProductSku');
  const skuSuggestionsElement = document.querySelector('[data-writeoff-suggestions="sku"]');
  const quantityInput = document.getElementById('writeoffQuantity');
  const defaultDestination = document.getElementById('writeoffDestinationDefault');
  const defaultReason = document.getElementById('writeoffReasonDefault');
  const addButton = document.getElementById('writeoffAddProduct');
  const applyDefaultsButton = document.getElementById('writeoffApplyDefaults');
  const itemList = document.getElementById('writeoffItemList');
  const helper = document.getElementById('writeoffHelper');
  const preview = document.querySelector('[data-writeoff-preview]');
  const previewThumb = document.querySelector('[data-writeoff-preview-thumb]');
  const previewName = document.querySelector('[data-writeoff-preview-name]');
  const previewMeta = document.querySelector('[data-writeoff-preview-meta]');
  const previewEmpty = document.querySelector('[data-writeoff-preview-empty]');
  const useCustomProductSuggestions = true;
  const thumbSize = 150;
  const items = [];
  let selectedProductId = '';
  let selectedVariationId = '';
  let selectedSkuValue = '';
  let selectedProductName = '';
  let selectedAvailableQuantity = '';
  let selectedImage = '';
  const skuLookup = {};
  const productNameLookup = {};
  const skuSuggestions = [];
  const productNameSuggestions = [];
  const unifiedSuggestions = [];
  const initialWriteoffItem = <?php echo json_encode($initialItem ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const normalizedProducts = productOptions || {};
  const hasProducts = Object.keys(normalizedProducts).length > 0;

  const normalizeSearch = (value) => {
    let normalized = String(value || '').toLowerCase().trim();
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

  const filterSuggestions = (itemsList, tokens, includeAllWhenEmpty = true) => {
    if (!tokens.length) {
      return includeAllWhenEmpty ? itemsList : [];
    }
    return itemsList.filter((item) => matchesSearchTokens(tokens, item.search));
  };

  const clearNode = (node) => {
    if (!node) return;
    while (node.firstChild) {
      node.removeChild(node.firstChild);
    }
  };

  const closeAllSuggestions = () => {
    document.querySelectorAll('.autocomplete-suggestions').forEach((list) => {
      list.hidden = true;
    });
    document.querySelectorAll('.autocomplete-input').forEach((input) => {
      input.setAttribute('aria-expanded', 'false');
    });
  };

  const bindSuggestionSelect = (button, onSelect) => {
    let handled = false;
    let touchStart = null;

    const trigger = () => {
      if (handled) return;
      handled = true;
      onSelect();
      setTimeout(() => {
        handled = false;
      }, 0);
    };

    button.addEventListener('pointerdown', (event) => {
      if (event.pointerType !== 'touch') return;
      touchStart = { x: event.clientX, y: event.clientY };
    });

    button.addEventListener('pointerup', (event) => {
      if (event.pointerType !== 'touch') return;
      if (!touchStart) return;
      const moved = Math.abs(event.clientX - touchStart.x) > 8
        || Math.abs(event.clientY - touchStart.y) > 8;
      touchStart = null;
      if (moved) return;
      event.preventDefault();
      trigger();
    });

    button.addEventListener('click', (event) => {
      if (handled) {
        event.preventDefault();
        return;
      }
      trigger();
    });
  };

  const setupAutocomplete = (input, suggestions, items, onSelect, emptyMessage, enabled = useCustomProductSuggestions) => {
    if (!enabled || !input || !suggestions) return null;
    const hideSuggestions = () => {
      suggestions.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    };
    const renderSuggestions = (value) => {
      closeAllSuggestions();
      clearNode(suggestions);
      const tokens = buildSearchTokens(value);
      const filtered = filterSuggestions(items, tokens, true);
      if (filtered.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'autocomplete-suggestion autocomplete-suggestion--empty';
        empty.textContent = emptyMessage;
        suggestions.appendChild(empty);
      } else {
        const fragment = document.createDocumentFragment();
        filtered.forEach((item) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'autocomplete-suggestion';
          button.textContent = item.label;
          bindSuggestionSelect(button, () => {
            onSelect(item);
            hideSuggestions();
          });
          fragment.appendChild(button);
        });
        suggestions.appendChild(fragment);
      }
      suggestions.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    input.classList.add('autocomplete-input');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.addEventListener('focus', () => renderSuggestions(input.value));
    input.addEventListener('click', () => renderSuggestions(input.value));
    input.addEventListener('input', () => renderSuggestions(input.value));
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        hideSuggestions();
      }
    });

    return {
      hide: hideSuggestions,
      render: renderSuggestions,
    };
  };

  const buildSkuLabel = (sku, name, variationLabel) => {
    const parts = [];
    if (sku) {
      parts.push('SKU ' + sku);
    }
    if (name) {
      parts.push(name);
    }
    if (variationLabel) {
      parts.push(variationLabel);
    }
    return parts.length ? parts.join(' - ') : 'SKU';
  };

  const buildNameLabel = (name, sku) => {
    if (name && sku) return `${name} (SKU ${sku})`;
    if (name) return name;
    if (sku) return `SKU ${sku}`;
    return 'Produto';
  };

  const buildVariationLabel = (variation) => {
    if (!variation) return 'Variação';
    const name = String(variation.name || variation.post_title || '').trim();
    const sku = String(variation.sku || '').trim();
    const id = String(variation.id || variation.ID || '').trim();
    if (name && sku) return `${name} (SKU ${sku})`;
    if (name) return name;
    if (sku) return `SKU ${sku}`;
    if (id) return `Variação #${id}`;
    return 'Variação';
  };

  const addLookupValue = (lookup, key, id) => {
    const normalized = normalizeSearch(key);
    if (normalized === '') return;
    if (!lookup[normalized]) {
      lookup[normalized] = [];
    }
    lookup[normalized].push(String(id));
  };

  const addSkuMatch = (lookup, suggestions, sku, productId, variationId, name, variationLabel) => {
    const normalized = normalizeSearch(sku);
    if (normalized === '') return;
    if (!lookup[normalized]) {
      lookup[normalized] = [];
    }
    lookup[normalized].push({
      productId: String(productId),
      variationId: variationId ? String(variationId) : '',
      sku,
    });
    if (suggestions) {
      suggestions.push({
        kind: 'sku',
        id: String(productId),
        variationId: variationId ? String(variationId) : '',
        value: sku,
        sku,
        label: buildSkuLabel(sku, name, variationLabel),
        search: normalizeSearch(`${sku} ${name} ${variationLabel || ''}`),
      });
    }
  };

  Object.entries(normalizedProducts).forEach(([id, product]) => {
    const normalizedId = String(id);
    const sku = String(product && product.sku ? product.sku : '').trim();
    const name = String(product && product.post_title ? product.post_title : '').trim();
    if (sku !== '') {
      addSkuMatch(skuLookup, skuSuggestions, sku, normalizedId, '', name, '');
    }
    if (name !== '') {
      addLookupValue(productNameLookup, name, id);
      productNameSuggestions.push({
        kind: 'name',
        id: normalizedId,
        variationId: '',
        value: name,
        sku: sku || '',
        label: buildNameLabel(name, sku),
        search: normalizeSearch(`${name} ${sku}`),
      });
    }
    const variations = product && Array.isArray(product.variations) ? product.variations : [];
    variations.forEach((variation) => {
      const variationId = String(variation && (variation.id || variation.ID) ? (variation.id || variation.ID) : '').trim();
      const variationSku = String(variation && variation.sku ? variation.sku : '').trim();
      if (!variationId || variationSku === '') {
        return;
      }
      const variationLabel = buildVariationLabel(variation);
      addSkuMatch(skuLookup, skuSuggestions, variationSku, normalizedId, variationId, name, variationLabel);
    });
  });
  unifiedSuggestions.push(...skuSuggestions);
  productNameSuggestions.forEach((item) => {
    if (!item.sku) {
      unifiedSuggestions.push(item);
    }
  });

  const matchLookup = (value) => {
    const normalized = normalizeSearch(value);
    const ids = productNameLookup[normalized] || null;
    if (ids && ids.length === 1) {
      return { id: ids[0], state: 'exact' };
    }
    if (ids && ids.length > 1) {
      return { id: '', state: 'multiple' };
    }
    const tokens = buildSearchTokens(value);
    if (!tokens.length) {
      return { id: '', state: 'none' };
    }
    const filtered = filterSuggestions(productNameSuggestions, tokens, false);
    if (filtered.length) {
      const uniqueIds = new Set(filtered.map((item) => String(item.id)));
      if (uniqueIds.size === 1) {
        return { id: Array.from(uniqueIds)[0], state: 'approx' };
      }
      if (uniqueIds.size > 1) {
        return { id: '', state: 'multiple' };
      }
    }
    return { id: '', state: 'none' };
  };

  const matchSku = (value) => {
    const normalized = normalizeSearch(value);
    let matches = skuLookup[normalized] || null;
    if ((!matches || matches.length === 0)) {
      const normalizedSku = value.replace(/^sku\s*/i, '').trim();
      const fallback = normalizedSku ? normalizeSearch(normalizedSku) : '';
      matches = fallback ? (skuLookup[fallback] || null) : null;
    }
    if (matches && matches.length === 1) {
      return {
        productId: matches[0].productId,
        variationId: matches[0].variationId,
        sku: matches[0].sku,
        state: 'exact',
      };
    }
    if (matches && matches.length > 1) {
      return { productId: '', variationId: '', sku: '', state: 'multiple' };
    }
    return { productId: '', variationId: '', sku: '', state: 'none' };
  };

  const resolveProductLabel = (product, variation) => {
    if (!product) return '';
    const baseName = String(product.post_title || '').trim();
    const variationLabel = variation ? buildVariationLabel(variation) : '';
    if (variationLabel && baseName) {
      return `${baseName} • ${variationLabel}`;
    }
    if (variationLabel) return variationLabel;
    if (baseName) return baseName;
    const baseSku = String(product.sku || '').trim();
    if (baseSku) return `SKU ${baseSku}`;
    return 'Produto selecionado';
  };

  const resolveItemImage = (product, variation) => {
    const variationSrc = variation && variation.image_src ? String(variation.image_src).trim() : '';
    if (variationSrc) return variationSrc;
    const productSrc = product && product.image_src ? String(product.image_src).trim() : '';
    return productSrc;
  };

  const resolveAvailableQuantity = (product, variation) => {
    const source = variation || product;
    if (!source) return '';
    const qty = source.quantity ?? '';
    if (qty === null || qty === undefined || qty === '') {
      return '';
    }
    return String(qty);
  };

  const buildThumbUrl = (raw, size) => {
    if (!raw) return '';
    let base = raw;
    let suffix = '';
    const queryIndex = raw.indexOf('?');
    const hashIndex = raw.indexOf('#');
    const cutIndex = Math.min(
      queryIndex === -1 ? raw.length : queryIndex,
      hashIndex === -1 ? raw.length : hashIndex
    );
    if (cutIndex < raw.length) {
      base = raw.slice(0, cutIndex);
      suffix = raw.slice(cutIndex);
    }
    const extMatch = base.match(/\.[^./]+$/);
    if (!extMatch) return raw;
    const ext = extMatch[0];
    let stem = base.slice(0, -ext.length);
    if (!stem) return raw;
    if (/-\d+x\d+$/.test(stem)) {
      stem = stem.replace(/-\d+x\d+$/, `-${size}x${size}`);
    } else {
      stem += `-${size}x${size}`;
    }
    return `${stem}${ext}${suffix}`;
  };

  const updateProductPreview = () => {
    if (!preview || !previewThumb || !previewName || !previewMeta || !previewEmpty) return;
    if (!selectedProductId) {
      preview.hidden = true;
      previewEmpty.hidden = false;
      previewThumb.innerHTML = '';
      previewName.textContent = '';
      previewMeta.textContent = '';
      return;
    }
    preview.hidden = false;
    previewEmpty.hidden = true;
    previewName.textContent = selectedProductName || 'Produto';
    const metaParts = [];
    if (selectedSkuValue) {
      metaParts.push(`SKU ${selectedSkuValue}`);
    }
    if (selectedAvailableQuantity !== '' && selectedAvailableQuantity !== null) {
      metaParts.push(`Disponível ${selectedAvailableQuantity}`);
    }
    previewMeta.textContent = metaParts.join(' • ');
    previewThumb.innerHTML = '';
    if (selectedImage) {
      const img = document.createElement('img');
      const thumbSrc = buildThumbUrl(selectedImage, thumbSize);
      img.src = thumbSrc || selectedImage;
      img.alt = selectedProductName || 'Produto';
      img.loading = 'lazy';
      previewThumb.appendChild(img);
    } else {
      const placeholder = document.createElement('span');
      placeholder.textContent = 'Sem imagem';
      previewThumb.appendChild(placeholder);
    }
  };

  const setHelperMessage = (message, isError = false) => {
    if (!helper) return;
    helper.textContent = message;
    helper.classList.toggle('order-item-helper--error', Boolean(isError));
  };

  const clearSelectedProductState = () => {
    selectedProductId = '';
    selectedVariationId = '';
    selectedSkuValue = '';
    selectedProductName = '';
    selectedAvailableQuantity = '';
    selectedImage = '';
    updateProductPreview();
  };

  const clearSelection = () => {
    clearSelectedProductState();
    if (skuInput) skuInput.value = '';
  };

  const applySelection = (productId, variationId, skuValue, preferredName) => {
    if (!productId || !normalizedProducts[productId]) {
      setHelperMessage('Produto não encontrado.', true);
      return;
    }
    selectedProductId = String(productId);
    selectedVariationId = variationId ? String(variationId) : '';
    const product = normalizedProducts[selectedProductId];
    const variation = selectedVariationId
      ? (product.variations || []).find((variationItem) => String(variationItem.id || variationItem.ID) === selectedVariationId)
      : null;
    selectedSkuValue = skuValue || (variation && variation.sku ? String(variation.sku).trim() : String(product.sku || '').trim());
    selectedProductName = preferredName || resolveProductLabel(product, variation);
    selectedAvailableQuantity = resolveAvailableQuantity(product, variation);
    selectedImage = resolveItemImage(product, variation);
    updateProductPreview();
    setHelperMessage('Produto pronto para adicionar.', false);
  };

  const renderItems = () => {
    if (!itemList) return;
    clearNode(itemList);
    if (!items.length) {
      const empty = document.createElement('div');
      empty.className = 'order-items-empty';
      empty.textContent = 'Nenhum item adicionado.';
      itemList.appendChild(empty);
      return;
    }
    items.forEach((item, index) => {
      const row = document.createElement('div');
      row.className = 'writeoff-item-row';
      const thumb = document.createElement('div');
      thumb.className = 'writeoff-item-thumb';
      if (item.image) {
        const img = document.createElement('img');
        const thumbSrc = buildThumbUrl(item.image, 80);
        img.src = thumbSrc || item.image;
        img.alt = item.name || 'Produto';
        img.loading = 'lazy';
        thumb.appendChild(img);
      } else {
        const placeholder = document.createElement('span');
        placeholder.textContent = 'Sem imagem';
        thumb.appendChild(placeholder);
      }
      const info = document.createElement('div');
      info.className = 'writeoff-item-info';
      const title = document.createElement('div');
      title.className = 'writeoff-item-title';
      title.textContent = item.name || 'Produto';
      const meta = document.createElement('div');
      meta.className = 'writeoff-item-meta';
      const metaParts = [];
      if (item.sku) {
        metaParts.push('SKU ' + item.sku);
      }
      if (item.available_quantity !== '' && item.available_quantity !== null) {
        metaParts.push('Disponível ' + item.available_quantity);
      }
      meta.textContent = metaParts.join(' • ');
      info.appendChild(title);
      info.appendChild(meta);
      const fields = document.createElement('div');
      fields.className = 'writeoff-item-fields';
      const qtyField = document.createElement('div');
      qtyField.className = 'field field--compact';
      const qtyLabel = document.createElement('label');
      qtyLabel.textContent = 'Qtd';
      const qtyInput = document.createElement('input');
      qtyInput.type = 'number';
      qtyInput.min = '1';
      qtyInput.value = String(item.quantity || 1);
      qtyInput.name = `items[${index}][quantity]`;
      qtyInput.addEventListener('change', () => {
        const parsed = Math.max(1, parseInt(qtyInput.value, 10) || 1);
        item.quantity = parsed;
        qtyInput.value = parsed;
      });
      qtyField.appendChild(qtyLabel);
      qtyField.appendChild(qtyInput);
      fields.appendChild(qtyField);
      const destField = document.createElement('div');
      destField.className = 'field field--compact';
      const destLabel = document.createElement('label');
      destLabel.textContent = 'Destinação';
      const destSelect = document.createElement('select');
      destSelect.name = `items[${index}][destination]`;
      populateSelect(destSelect, item.destination, destinationOptions);
      destSelect.addEventListener('change', () => {
        item.destination = destSelect.value;
      });
      destField.appendChild(destLabel);
      destField.appendChild(destSelect);
      fields.appendChild(destField);
      const reasonField = document.createElement('div');
      reasonField.className = 'field field--compact';
      const reasonLabel = document.createElement('label');
      reasonLabel.textContent = 'Motivo';
      const reasonSelect = document.createElement('select');
      reasonSelect.name = `items[${index}][reason]`;
      populateSelect(reasonSelect, item.reason, reasonOptions);
      reasonSelect.addEventListener('change', () => {
        item.reason = reasonSelect.value;
      });
      reasonField.appendChild(reasonLabel);
      reasonField.appendChild(reasonSelect);
      fields.appendChild(reasonField);
      const actions = document.createElement('div');
      actions.className = 'writeoff-item-actions';
      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'btn ghost';
      removeButton.textContent = 'Remover';
      removeButton.addEventListener('click', () => {
        items.splice(index, 1);
        renderItems();
      });
      actions.appendChild(removeButton);
      const hiddenProductSku = document.createElement('input');
      hiddenProductSku.type = 'hidden';
      hiddenProductSku.name = `items[${index}][product_sku]`;
      hiddenProductSku.value = item.productSku || '';
      const hiddenVariationId = document.createElement('input');
      hiddenVariationId.type = 'hidden';
      hiddenVariationId.name = `items[${index}][variation_id]`;
      hiddenVariationId.value = item.variationId || '';
      const hiddenSku = document.createElement('input');
      hiddenSku.type = 'hidden';
      hiddenSku.name = `items[${index}][sku]`;
      hiddenSku.value = item.sku || '';
      row.appendChild(thumb);
      row.appendChild(info);
      row.appendChild(fields);
      row.appendChild(actions);
      row.appendChild(hiddenProductSku);
      row.appendChild(hiddenVariationId);
      row.appendChild(hiddenSku);
      itemList.appendChild(row);
    });
  };

  const addItem = () => {
    if (!selectedProductId) {
      setHelperMessage('Selecione um produto antes de adicionar.', true);
      return;
    }
    const product = normalizedProducts[selectedProductId];
    if (!product) {
      setHelperMessage('Produto não encontrado.', true);
      return;
    }
    const variation = selectedVariationId
      ? (product.variations || []).find((variationItem) => String(variationItem.id || variationItem.ID) === selectedVariationId)
      : null;
    const destinationValue = defaultDestination ? defaultDestination.value : '';
    const reasonValue = defaultReason ? defaultReason.value : '';
    const quantityValue = quantityInput ? Math.max(1, parseInt(quantityInput.value, 10) || 1) : 1;
    items.push({
      productSku: selectedProductId,
      variationId: selectedVariationId,
      sku: selectedSkuValue,
      name: selectedProductName,
      image: selectedImage || resolveItemImage(product, variation),
      available_quantity: selectedAvailableQuantity || resolveAvailableQuantity(product, variation),
      quantity: quantityValue,
      destination: destinationValue,
      reason: reasonValue,
    });
    renderItems();
    if (quantityInput) {
      quantityInput.value = '1';
    }
    clearSelection();
    setHelperMessage('Produto adicionado à lista.', false);
  };

  const applyDefaults = () => {
    if (!items.length) return;
    const destinationValue = defaultDestination ? defaultDestination.value : '';
    const reasonValue = defaultReason ? defaultReason.value : '';
    items.forEach((item) => {
      if (destinationValue) {
        item.destination = destinationValue;
      }
      if (reasonValue) {
        item.reason = reasonValue;
      }
    });
    renderItems();
  };

  if (applyDefaultsButton) {
    applyDefaultsButton.addEventListener('click', applyDefaults);
  }

  if (addButton) {
    addButton.addEventListener('click', addItem);
  }

  const handleProductInput = (finalize = false) => {
    if (!skuInput) return;
    const value = skuInput.value.trim();
    if (value === '') {
      clearSelection();
      setHelperMessage('Selecione um produto válido.', false);
      return;
    }
    const skuMatch = matchSku(value);
    if (skuMatch.productId) {
      applySelection(skuMatch.productId, skuMatch.variationId, skuMatch.sku || value);
      return;
    }
    const nameMatch = matchLookup(value);
    if (nameMatch.id) {
      applySelection(nameMatch.id, '', '', value);
      return;
    }
    clearSelectedProductState();
    if (skuMatch.state === 'multiple') {
      setHelperMessage('Mais de um SKU encontrado. Refine o valor.', true);
    } else if (nameMatch.state === 'multiple') {
      setHelperMessage('Mais de um produto com esse nome. Refine o valor.', true);
    } else if (finalize) {
      setHelperMessage('Produto não encontrado.', true);
    } else {
      setHelperMessage('Digite um SKU ou nome válido.', false);
    }
  };

  const tryAddInitialItem = () => {
    if (!initialWriteoffItem) {
      return;
    }
    const skuValue = initialWriteoffItem.sku ? String(initialWriteoffItem.sku).trim() : '';
    const productSku = initialWriteoffItem.product_sku ? String(initialWriteoffItem.product_sku) : '';
    const variationId = initialWriteoffItem.variation_id ? String(initialWriteoffItem.variation_id) : '';
    let selectionMade = false;
    if (skuValue && skuInput) {
      skuInput.value = skuValue;
      handleProductInput(true);
      selectionMade = Boolean(selectedProductId);
    }
    if (!selectionMade && productSku && normalizedProducts[productSku]) {
      applySelection(productSku, variationId, skuValue || undefined);
      selectionMade = Boolean(selectedProductId);
    }
    if (!selectionMade) {
      return;
    }
    if (quantityInput) {
      const requestedQty = parseInt(String(initialWriteoffItem.quantity || '1'), 10);
      quantityInput.value = String(requestedQty > 0 ? requestedQty : 1);
    }
    if (initialWriteoffItem.destination && defaultDestination) {
      defaultDestination.value = initialWriteoffItem.destination;
    }
    if (initialWriteoffItem.reason && defaultReason) {
      defaultReason.value = initialWriteoffItem.reason;
    }
    addItem();
  };

  const populateSelect = (select, value, options) => {
    if (!select) return;
    clearNode(select);
    Object.entries(options).forEach(([optionValue, optionLabel]) => {
      const option = document.createElement('option');
      option.value = optionValue;
      option.textContent = optionLabel;
      if (optionValue === value) {
        option.selected = true;
      }
      select.appendChild(option);
    });
  };

  if (skuInput) {
    skuInput.addEventListener('input', () => handleProductInput(false));
    skuInput.addEventListener('change', () => handleProductInput(true));
  }

  setupAutocomplete(skuInput, skuSuggestionsElement, unifiedSuggestions, (item) => {
    if (!skuInput) return;
    skuInput.value = item.value;
    if (item.kind === 'sku') {
      applySelection(item.id, item.variationId || '', item.sku || item.value);
      return;
    }
    applySelection(item.id, '', '', item.value);
  }, 'Nenhum produto encontrado.', useCustomProductSuggestions);

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.autocomplete-picker')) {
      closeAllSuggestions();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAllSuggestions();
    }
  });

  if (!hasProducts) {
    setHelperMessage('Nenhum produto disponível no momento.', true);
    if (addButton) {
      addButton.disabled = true;
    }
  }

  updateProductPreview();
  renderItems();
  if (initialWriteoffItem) {
    tryAddInitialItem();
  }

  const vendorTermSearch = document.getElementById('writeoffVendorTermSearch');
  const vendorTermHidden = document.getElementById('writeoffVendorTermId');
  const vendorTermItems = document.getElementById('writeoffVendorTermItems');
  const vendorTermSubmit = document.getElementById('writeoffVendorTermSubmit');
  const vendorTermEndpoint = 'produto-baixa-supplier-returns.php';

  const formatVendorDate = (value) => {
    if (!value) {
      return '—';
    }
    const timestamp = Date.parse(value);
    if (Number.isNaN(timestamp)) {
      return value;
    }
    const date = new Date(timestamp);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}/${date.getFullYear()}`;
  };

  const updateVendorSubmitState = () => {
    if (!vendorTermSubmit) return;
    if (!vendorTermItems) {
      vendorTermSubmit.disabled = true;
      return;
    }
    const anyChecked = vendorTermItems.querySelectorAll('input[type="checkbox"]:checked').length > 0
      && Boolean(vendorTermHidden && vendorTermHidden.value);
    vendorTermSubmit.disabled = !anyChecked;
  };

  const renderVendorMessage = (message, className = 'help-text') => {
    if (!vendorTermItems) return;
    clearNode(vendorTermItems);
    const wrapper = document.createElement('div');
    wrapper.className = className;
    wrapper.textContent = message;
    vendorTermItems.appendChild(wrapper);
    updateVendorSubmitState();
  };

  const renderVendorRows = (rows) => {
    if (!vendorTermItems) return;
    clearNode(vendorTermItems);
    rows.forEach((row) => {
      const container = document.createElement('div');
      container.className = 'writeoff-vendor-item';
      const label = document.createElement('label');
      label.className = 'writeoff-vendor-item__label';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.name = 'item_ids[]';
      checkbox.value = String(row.id || '');
      checkbox.addEventListener('change', updateVendorSubmitState);
      const summary = document.createElement('span');
      summary.className = 'writeoff-vendor-item__summary';
      const sku = row.sku || '—';
      const rawQuantity = Number(row.quantity);
      const quantity = Number.isFinite(rawQuantity) ? rawQuantity : 0;
      const dateLabel = formatVendorDate(row.created_at);
      summary.textContent = `#${row.id || '—'} • SKU ${sku} • ${quantity} un. • ${dateLabel}`;
      label.appendChild(checkbox);
      label.appendChild(summary);
      container.appendChild(label);
      const reasonLine = document.createElement('div');
      reasonLine.className = 'writeoff-vendor-item__reason';
      const reasonLabel = reasonOptions[row.reason] || row.reason || '—';
      reasonLine.textContent = `Motivo: ${reasonLabel}`;
      container.appendChild(reasonLine);
      if (row.notes) {
        const notesLine = document.createElement('div');
        notesLine.className = 'writeoff-vendor-item__notes';
        notesLine.textContent = `Observações: ${row.notes}`;
        container.appendChild(notesLine);
      }
      vendorTermItems.appendChild(container);
    });
    updateVendorSubmitState();
  };

  const loadVendorReturns = async (supplierId) => {
    if (!vendorTermItems) return;
    if (!supplierId) {
      renderVendorMessage('Selecione um fornecedor para carregar as baixas devolvidas a ele.');
      return;
    }
    renderVendorMessage('Carregando baixas devolvidas...', 'help-text');
    try {
      const params = new URLSearchParams({ supplier_pessoa_id: supplierId, limit: '200' });
      const response = await fetch(`${vendorTermEndpoint}?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) {
        throw new Error('Não foi possível carregar as baixas.');
      }
      const payload = await response.json();
      if (Array.isArray(payload.errors) && payload.errors.length) {
        renderVendorMessage(payload.errors.join(' '), 'alert error');
        return;
      }
      const rows = Array.isArray(payload.rows) ? payload.rows : [];
      if (!rows.length) {
        renderVendorMessage('Nenhuma baixa de devolução registrada para esse fornecedor.', 'help-text');
        return;
      }
      renderVendorRows(rows);
    } catch (error) {
      renderVendorMessage(error.message || 'Erro ao carregar as baixas.', 'alert error');
    }
  };

  const resetVendorSelection = () => {
    if (vendorTermHidden) {
      vendorTermHidden.value = '';
    }
  };

  const handleVendorInput = () => {
    if (!vendorTermSearch || !vendorTermHidden) {
      return;
    }
    const query = vendorTermSearch.value.trim();
    if (query === '') {
      resetVendorSelection();
      renderVendorMessage('Selecione um fornecedor para carregar as baixas devolvidas a ele.');
      return;
    }
    const match = vendorTermOptions.find((option) => option.label === query);
    if (!match) {
      resetVendorSelection();
      renderVendorMessage('Selecione um fornecedor válido da lista.', 'help-text');
      return;
    }
    vendorTermHidden.value = String(match.id);
    loadVendorReturns(match.id);
  };

  if (vendorTermSearch) {
    vendorTermSearch.addEventListener('input', () => {
      if (vendorTermHidden) {
        vendorTermHidden.value = '';
      }
    });
    vendorTermSearch.addEventListener('change', handleVendorInput);
    vendorTermSearch.addEventListener('blur', () => {
      if (vendorTermSearch.value.trim() === '') {
        resetVendorSelection();
        renderVendorMessage('Selecione um fornecedor para carregar as baixas devolvidas a ele.');
        return;
      }
      handleVendorInput();
    });
  }

  if (vendorTermItems) {
    renderVendorMessage('Selecione um fornecedor para carregar as baixas devolvidas a ele.');
    if (vendorTermHidden && vendorTermHidden.value) {
      loadVendorReturns(vendorTermHidden.value);
    }
  }
})();
</script>
