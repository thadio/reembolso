<?php
/** @var array $formData */
/** @var array $errors */
/** @var array $notices */
/** @var array $warnings */
/** @var string $success */
/** @var string $successSku */
/** @var string $successWcId */
/** @var bool $editing */
/** @var string $currentSku */
/** @var int $skuReservationId */
/** @var string $skuReservationSku */
/** @var string $skuContextKey */
/** @var bool $canAdjustInventory */
/** @var array $vendorOptions */
/** @var array $categoryOptions */
/** @var array $brandOptions */
/** @var string $brandTaxonomy */
/** @var array $existingImages */
/** @var array $imageUploadInfo */
/** @var bool $replaceImages */
/** @var array $removeImageIds */
/** @var array $removeImageSrcs */
/** @var string $coverImageSelection */
/** @var bool $canCreateBrand */
/** @var array $ordersForProduct */
/** @var array $orderReturnsByOrder */
/** @var int $ordersPage */
/** @var int $ordersPerPage */
/** @var array $ordersPerPageOptions */
/** @var int $ordersTotal */
/** @var int $ordersTotalPages */
/** @var array $orderStatusOptions */
/** @var array $paymentStatusOptions */
/** @var array $fulfillmentStatusOptions */
/** @var array $writeoffHistory */
/** @var int $writeoffPage */
/** @var int $writeoffPerPage */
/** @var array $writeoffPerPageOptions */
/** @var int $writeoffTotal */
/** @var int $writeoffTotalPages */
/** @var array $writeoffFilters */
/** @var string $writeoffSortKey */
/** @var string $writeoffSortDir */
/** @var array $writeoffDestinationOptions */
/** @var array $writeoffReasonOptions */
/** @var callable $esc */
?>
<?php
  $selectedCategoryIds = array_map('strval', $formData['categoryIdsArray'] ?? []);
  $brandValue = trim((string) ($formData['brand'] ?? ''));
  $brandLookupValue = trim((string) ($formData['brand_lookup'] ?? ''));
  $brandSuggestionList = [];
  $selectedBrandLabel = '';
  foreach (($brandOptions ?? []) as $brandOption) {
      $brandId = trim((string) ($brandOption['term_id'] ?? $brandOption['id'] ?? ''));
      $brandName = trim((string) ($brandOption['name'] ?? ''));
      if ($brandId === '' || $brandName === '') {
          continue;
      }
      $brandSuggestionList[] = [
          'id' => $brandId,
          'name' => $brandName,
      ];
      if ($selectedBrandLabel === '' && $brandId === $brandValue) {
          $selectedBrandLabel = $brandName;
      }
  }
  if ($brandLookupValue === '' && $selectedBrandLabel !== '') {
      $brandLookupValue = $selectedBrandLabel;
  }
  $brandSuggestionsJson = json_encode(
      $brandSuggestionList,
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  );
  if (!is_string($brandSuggestionsJson)) {
      $brandSuggestionsJson = '[]';
  }
  $formTitle = $editing ? 'Editar Produto' : 'Cadastro de Produto';
  $submitLabel = $editing ? 'Atualizar' : 'Salvar';
  $submitBusyLabel = $editing ? 'Atualizando...' : 'Salvando...';
  $canCreateProduct = userCan('products.create');
  $showNewProductButton = $success !== '' && stripos($success, 'produto criado') !== false;
  $lockInventory = empty($canAdjustInventory);
  $existingImages = $existingImages ?? [];
  $imageUploadInfo = $imageUploadInfo ?? [];
  $imageMaxFiles = (int) ($imageUploadInfo['maxFiles'] ?? 6);
  $imageMaxSizeBytes = (int) ($imageUploadInfo['maxSizeBytes'] ?? 0);
  if ($imageMaxSizeBytes <= 0) {
      $imageMaxSizeBytes = (int) round(((float) ($imageUploadInfo['maxSizeMb'] ?? 0)) * 1024 * 1024);
  }
  $imageMaxSizeLabel = (string) ($imageUploadInfo['maxSizeLabel'] ?? '5');
  $imageExtensions = $imageUploadInfo['allowedExtensions'] ?? ['jpg', 'png', 'webp'];
  $imageAccept = (string) ($imageUploadInfo['accept'] ?? 'image/jpeg,image/png,image/webp');
  $imageExtensionsLabel = strtoupper(implode(', ', $imageExtensions));
  $removeImageIds = $removeImageIds ?? [];
  $removeImageSrcs = $removeImageSrcs ?? [];
  $coverImageSelection = $coverImageSelection ?? '';
  $ordersForProduct = $ordersForProduct ?? [];
  $orderReturnsByOrder = $orderReturnsByOrder ?? [];
  $ordersPage = $ordersPage ?? 1;
  $ordersPerPage = $ordersPerPage ?? 120;
  $ordersPerPageOptions = $ordersPerPageOptions ?? [50, 100, 120, 200];
  $ordersTotal = $ordersTotal ?? 0;
  $ordersTotalPages = $ordersTotalPages ?? 1;
  $orderStatusOptions = $orderStatusOptions ?? [];
  $paymentStatusOptions = $paymentStatusOptions ?? [];
  $fulfillmentStatusOptions = $fulfillmentStatusOptions ?? [];
  $writeoffHistory = $writeoffHistory ?? [];
  $writeoffPage = $writeoffPage ?? 1;
  $writeoffPerPage = $writeoffPerPage ?? 50;
  $writeoffPerPageOptions = $writeoffPerPageOptions ?? [25, 50, 100, 200];
  $writeoffTotal = $writeoffTotal ?? 0;
  $writeoffTotalPages = $writeoffTotalPages ?? 1;
  $writeoffFilters = $writeoffFilters ?? [];
  $writeoffSortKey = $writeoffSortKey ?? 'created_at';
  $writeoffSortDir = strtoupper((string) ($writeoffSortDir ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
  $writeoffDestinationOptions = $writeoffDestinationOptions ?? [];
  $writeoffReasonOptions = $writeoffReasonOptions ?? [];
  $ordersRangeStart = $ordersTotal > 0 ? (($ordersPage - 1) * $ordersPerPage + 1) : 0;
  $ordersRangeEnd = $ordersTotal > 0 ? min($ordersTotal, $ordersPage * $ordersPerPage) : 0;
  $writeoffRangeStart = $writeoffTotal > 0 ? (($writeoffPage - 1) * $writeoffPerPage + 1) : 0;
  $writeoffRangeEnd = $writeoffTotal > 0 ? min($writeoffTotal, $writeoffPage * $writeoffPerPage) : 0;
  $productSaleDefaultQuantity = max(1, (int) ($_GET['quantity'] ?? $_GET['qty'] ?? 1));
  $headerSku = trim((string) ($successSku !== '' ? $successSku : ($currentSku !== '' ? $currentSku : ($formData['sku'] ?? ''))));
  if ($headerSku === '' && !empty($skuReservationSku)) {
      $headerSku = trim((string) $skuReservationSku);
  }
  $showSkuRetryButton = !$editing && $headerSku === '';
  $statusValue = strtolower(trim((string) ($formData['status'] ?? 'draft')));
  if ($statusValue === 'publish' || $statusValue === 'active') {
      $statusValue = 'disponivel';
  } elseif ($statusValue === 'pending') {
      $statusValue = 'draft';
  } elseif ($statusValue === 'private') {
      $statusValue = 'archived';
  }
  if (!in_array($statusValue, ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'], true)) {
      $statusValue = 'draft';
  }
  $visibilityValue = strtolower(trim((string) ($formData['visibility'] ?? ($formData['catalogVisibility'] ?? 'public'))));
  if ($visibilityValue === 'visible') {
      $visibilityValue = 'public';
  }
  if (!in_array($visibilityValue, ['public', 'catalog', 'search', 'hidden'], true)) {
      $visibilityValue = 'public';
  }
  $sellableQuantity = max(0, (int) ($formData['quantity'] ?? 0));
  $canOpenSale = $statusValue === 'disponivel' && $sellableQuantity > 0;
  $writeoffFilterParamMap = [
    'filter_created_at' => 'writeoff_filter_created_at',
    'filter_destination' => 'writeoff_filter_destination',
    'filter_reason' => 'writeoff_filter_reason',
    'filter_quantity' => 'writeoff_filter_quantity',
    'filter_supplier' => 'writeoff_filter_supplier',
    'filter_stock_after' => 'writeoff_filter_stock_after',
    'filter_notes' => 'writeoff_filter_notes',
  ];
  $writeoffQueryCarry = [
    'writeoff_page' => $writeoffPage,
    'writeoff_per_page' => $writeoffPerPage,
  ];
  if (!empty($writeoffFilters['search'])) {
    $writeoffQueryCarry['writeoff_q'] = (string) $writeoffFilters['search'];
  }
  foreach ($writeoffFilterParamMap as $filterKey => $paramName) {
    $value = trim((string) ($writeoffFilters[$filterKey] ?? ''));
    if ($value === '') {
      continue;
    }
    $writeoffQueryCarry[$paramName] = $value;
  }
  if ($writeoffSortKey !== '') {
    $writeoffQueryCarry['writeoff_sort_key'] = $writeoffSortKey;
  }
  if ($writeoffSortDir !== '') {
    $writeoffQueryCarry['writeoff_sort_dir'] = strtolower($writeoffSortDir);
  }
  $buildOrdersLink = function (int $targetPage) use ($ordersPerPage, $formData, $writeoffQueryCarry): string {
    $query = ['id' => $formData['id'] ?? null, 'orders_page' => $targetPage, 'orders_per_page' => $ordersPerPage] + $writeoffQueryCarry;
    if (empty($query['id'])) {
      unset($query['id']);
    }
    return 'produto-cadastro.php?' . http_build_query($query);
  };
  $buildWriteoffLink = function (int $targetPage) use ($writeoffPerPage, $formData, $ordersPage, $ordersPerPage, $writeoffQueryCarry): string {
    $query = [
      'id' => $formData['id'] ?? null,
      'orders_page' => $ordersPage,
      'orders_per_page' => $ordersPerPage,
      'writeoff_page' => $targetPage,
      'writeoff_per_page' => $writeoffPerPage,
    ];
    foreach ($writeoffQueryCarry as $key => $value) {
      if ($key === 'writeoff_page' || $key === 'writeoff_per_page') {
        continue;
      }
      $query[$key] = $value;
    }
    if (empty($query['id'])) {
      unset($query['id']);
    }
    return 'produto-cadastro.php?' . http_build_query($query);
  };
?>
<form id="productForm" class="product-form" action="produto-cadastro.php" method="post" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
  <input type="hidden" name="sku_context_key" value="<?php echo $esc($skuContextKey ?? ''); ?>">
  <input type="hidden" name="sku_reservation_id" value="<?php echo $skuReservationId ? (int) $skuReservationId : ''; ?>">
  <input type="hidden" name="reserved_sku" value="<?php echo $esc($skuReservationSku ?? ''); ?>">

  <div class="product-form__header">
    <div class="product-form__title">
      <h1><?php echo $esc($formTitle); ?></h1>
      <?php if ($editing): ?>
        <div class="subtitle">Edicao via app.</div>
        <?php if ($currentSku !== ''): ?>
          <div class="subtitle">SKU atual: <?php echo $esc($currentSku); ?></div>
        <?php endif; ?>
      <?php elseif (!empty($skuReservationSku)): ?>
        <div class="subtitle">SKU reservado: <?php echo $esc($skuReservationSku); ?></div>
      <?php endif; ?>
    </div>
    <div class="product-form__actions" style="display:flex;align-items:flex-end;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
      <?php if ($editing && (!empty($formData['dateCreated']) || !empty($formData['dateModified']))): ?>
        <div class="product-form__meta" style="display:flex;flex-direction:column;align-items:flex-end;text-align:right;gap:4px;min-width:180px;">
          <?php if (!empty($formData['dateCreated'])): ?>
            <div class="subtitle" style="margin:0;">Data de cadastro: <?php echo $esc($formData['dateCreated']); ?></div>
          <?php endif; ?>
          <?php if (!empty($formData['dateModified'])): ?>
            <div class="subtitle" style="margin:0;">Último ajuste: <?php echo $esc($formData['dateModified']); ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <span class="pill product-form__pill" data-sku-pill>
        <?php if ($headerSku !== ''): ?>
          SKU <?php echo $esc($headerSku); ?>
        <?php else: ?>
          SKU indisponível
        <?php endif; ?>
      </span>
      <?php if ($showSkuRetryButton): ?>
        <button type="button" class="btn ghost product-form__sku-retry-btn" data-sku-retry-button>
          Tentar gerar SKU
        </button>
        <span class="product-form__sku-retry-status" data-sku-retry-status hidden></span>
      <?php endif; ?>
      <button class="primary" type="submit" form="productForm" data-submit-button>
        <?php echo $submitLabel; ?>
      </button>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php endif; ?>
  <?php if ($showNewProductButton && $canCreateProduct): ?>
    <div style="margin:10px 0;">
      <a class="btn ghost" href="produto-cadastro.php">Cadastrar novo produto</a>
    </div>
  <?php endif; ?>
  <?php if (!empty($warnings)): ?>
    <div class="alert error" style="font-size:16px;font-weight:700;padding:16px;">
      <?php echo $esc(implode(' ', $warnings)); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($notices)): ?>
    <div class="alert muted"><?php echo $esc(implode(' ', $notices)); ?></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <div class="product-form__section product-form__section--stacked">
    <div class="product-form__grid" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:flex-start;">
      <div class="field">
        <label for="name">Nome do Produto *</label>
        <input id="name" name="name" type="text" value="<?php echo $esc($formData['name']); ?>" required maxlength="80">
      </div>
      <div class="field">
        <label for="slug">Slug</label>
        <input id="slug" name="slug" type="text" value="<?php echo $esc($formData['slug']); ?>" maxlength="100">
      </div>
    </div>
  </div>

  <div class="product-form__section product-form__section--stacked">
    <div class="product-form__grid product-form__grid--sourcing">
      <div class="field">
        <label for="source">Fonte *</label>
        <select id="source" name="source" required>
          <option value="">Selecione a origem</option>
          <option value="compra" <?php echo $formData['source'] === 'compra' ? 'selected' : ''; ?>>Compra/Garimpo</option>
          <option value="doacao" <?php echo $formData['source'] === 'doacao' ? 'selected' : ''; ?>>Doação</option>
          <option value="consignacao" <?php echo $formData['source'] === 'consignacao' ? 'selected' : ''; ?>>Consignação</option>
        </select>
      </div>
      <div class="field">
        <label for="supplier">Fornecedor *</label>
        <?php if (empty($vendorOptions)): ?>
          <input id="supplier" name="supplier" type="text" required placeholder="Cadastre um fornecedor primeiro" value="">
        <?php else: ?>
          <input id="supplier" name="supplier" type="text" list="supplierOptions" required placeholder="Digite para buscar" value="<?php echo $esc($formData['supplier']); ?>">
          <datalist id="supplierOptions">
            <?php foreach ($vendorOptions as $vendor): ?>
              <option value="<?php echo (int) $vendor['id']; ?>" label="<?php echo $esc($vendor['full_name']); ?> — Pessoa <?php echo (int) $vendor['id']; ?>"><?php echo $esc($vendor['full_name']); ?> — Pessoa <?php echo (int) $vendor['id']; ?></option>
            <?php endforeach; ?>
          </datalist>
          <?php
            $selectedVendorLabel = '';
            foreach ($vendorOptions as $vendor) {
                if ((string) $vendor['id'] === (string) $formData['supplier']) {
                    $selectedVendorLabel = $vendor['full_name'] . ' — Pessoa ' . (int) $vendor['id'];
                    break;
                }
            }
          ?>
          <?php if ($selectedVendorLabel): ?>
            <small id="supplierHint" style="color:var(--muted);display:block;margin-top:6px;">
              <?php echo 'Selecionado: ' . $esc($selectedVendorLabel); ?>
            </small>
          <?php else: ?>
            <small id="supplierHint" style="display:none;"></small>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="lotSelect">Lote *</label>
        <?php
          $lotIdValue = (string) ($formData['lot_id'] ?? '');
          $lotNameValue = trim((string) ($formData['lot_name'] ?? ''));
          $lotStatusValue = trim((string) ($formData['lot_status'] ?? ''));
          $lotDisplay = $lotNameValue !== '' ? $lotNameValue : ($lotIdValue !== '' ? ('Lote #' . $lotIdValue) : '');
        ?>
        <div class="lot-field">
          <select id="lotSelect" name="lot_id" required <?php echo $lotIdValue !== '' ? '' : 'disabled'; ?>>
            <?php if ($lotDisplay !== ''): ?>
              <option value="<?php echo $esc($lotIdValue); ?>" selected>
                <?php echo $esc($lotDisplay . ($lotStatusValue !== '' ? ' (' . $lotStatusValue . ')' : '')); ?>
              </option>
            <?php else: ?>
              <option value="">Selecione um fornecedor</option>
            <?php endif; ?>
          </select>
          <div class="lot-actions">
            <button type="button" class="lot-action-btn lot-action-btn--open" id="lotOpenNew" title="Abrir novo lote">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1z"/>
              </svg>
            </button>
            <button type="button" class="lot-action-btn lot-action-btn--close" id="lotCloseOnly" title="Encerrar lote selecionado">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M6 7a2 2 0 0 1 2-2h5l5 5v7a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7zm7 0v3h3"/>
                <path fill="currentColor" d="M9 15h6a1 1 0 0 1 0 2H9a1 1 0 1 1 0-2z"/>
              </svg>
            </button>
            <button type="button" class="lot-action-btn lot-action-btn--cycle" id="lotCloseAndOpen" title="Encerrar lote e abrir novo">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M6 7a2 2 0 0 1 2-2h5l5 5v5a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7zm7 0v3h3"/>
                <path fill="currentColor" d="M9 15h3a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2zm7 3a1 1 0 0 1-1-1v-1h-1a1 1 0 1 1 0-2h1v-1a1 1 0 1 1 2 0v1h1a1 1 0 1 1 0 2h-1v1a1 1 0 0 1-1 1z"/>
              </svg>
            </button>
          </div>
        </div>
        <small class="help-text lot-hint" id="lotHint" data-status="<?php echo $esc($lotStatusValue); ?>">
          <?php if ($lotDisplay !== ''): ?>
            <?php echo $esc('Lote selecionado: ' . $lotDisplay . ($lotStatusValue !== '' ? ' (' . $lotStatusValue . ')' : '')); ?>
          <?php else: ?>
            Selecione um fornecedor para carregar o último lote aberto (ou criar um novo automaticamente).
          <?php endif; ?>
        </small>
      </div>
      <div class="field">
        <div class="product-form__label-row">
          <label for="brand_lookup">Marca</label>
          <?php if (!empty($canCreateBrand)): ?>
            <button type="button" class="icon-button" data-brand-open aria-label="Cadastrar nova marca" title="Cadastrar nova marca">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M11 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1z"/>
              </svg>
            </button>
          <?php endif; ?>
        </div>
        <input type="hidden" id="brand" name="brand" value="<?php echo $esc($brandValue); ?>">
        <input
          id="brand_lookup"
          type="text"
          list="brandOptionsList"
          placeholder="Digite para buscar marca"
          value="<?php echo $esc($brandLookupValue); ?>"
          autocomplete="off"
        >
        <datalist id="brandOptionsList">
          <?php foreach ($brandSuggestionList as $brand): ?>
            <?php
              $brandId = (string) ($brand['id'] ?? '');
              $brandName = (string) ($brand['name'] ?? '');
            ?>
            <option value="<?php echo $esc($brandName); ?>" label="<?php echo $esc($brandName . ' — ID ' . $brandId); ?>">
              <?php echo $esc($brandName . ' — ID ' . $brandId); ?>
            </option>
          <?php endforeach; ?>
        </datalist>
        <small
          id="brandHint"
          class="product-form__helper"
          <?php echo $selectedBrandLabel !== '' ? '' : 'style="display:none;"'; ?>
        >
          <?php if ($selectedBrandLabel !== ''): ?>
            <?php echo 'Selecionada: ' . $esc($selectedBrandLabel . ' — ID ' . $brandValue); ?>
          <?php endif; ?>
        </small>
        <?php if (empty($brandSuggestionList)): ?>
          <small class="product-form__helper">
            Cadastre marcas no app<?php echo !empty($canCreateBrand) ? ' ou use o +' : ''; ?>.
          </small>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="product-form__section product-form__section--stacked">
    <div class="product-form__grid" style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:16px;align-items:flex-start;">
      <div class="field" id="priceWrap">
        <label for="price">Preço de Venda (R$) *</label>
        <input id="price" name="price" type="text" inputmode="decimal" data-number-br step="0.01" value="<?php echo $esc($formData['price']); ?>" required>
      </div>
      <div class="field" id="costWrap">
        <label for="cost">Custo (R$)</label>
        <input id="cost" name="cost" type="text" inputmode="decimal" data-number-br step="0.01" value="<?php echo $esc($formData['cost']); ?>">
      </div>
      <div class="field" id="percentualConsignacaoWrap">
        <label for="percentualConsignacao">% Consignação (para fornecedor)</label>
        <input id="percentualConsignacao" name="percentualConsignacao" type="text" inputmode="decimal" data-number-br step="0.01" min="0" max="100" value="<?php echo $esc($formData['percentualConsignacao']); ?>">
      </div>
    </div>
  </div>

  <div class="product-form__section product-form__section--stacked">
    <div class="product-form__grid" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:flex-start;">
      <div class="field">
        <label for="description">Descrição</label>
        <textarea id="description" name="description" maxlength="8000" rows="3" placeholder="Texto completo do produto (aba Descrição na página do produto)." style="resize:vertical;"><?php echo $esc($formData['description']); ?></textarea>
      </div>
      <div class="field">
        <label for="shortDescription">Resumo (short description)</label>
        <textarea id="shortDescription" name="shortDescription" maxlength="800" rows="3" placeholder="Texto curto perto do título e do preço (Descrição curta)." style="resize:vertical;"><?php echo $esc($formData['shortDescription']); ?></textarea>
      </div>
    </div>
  </div>

  <div class="section product-form__section">
    <h2 class="product-form__section-title">Imagens do produto</h2>
    <div class="product-form__images">
      <div class="field">
        <label for="product_images">Upload de imagens</label>
        <input id="product_images" name="product_images[]" type="file" accept="<?php echo $esc($imageAccept); ?>" multiple>
        <small class="product-form__helper">
          Até <?php echo $imageMaxFiles; ?> imagens (<?php echo $esc($imageExtensionsLabel); ?>) com até <?php echo $esc($imageMaxSizeLabel); ?> MB cada.
        </small>
      </div>

      <?php if ($editing && !empty($existingImages)): ?>
        <label class="product-form__checkbox">
          <input type="checkbox" name="replace_images" value="1" <?php echo !empty($replaceImages) ? 'checked' : ''; ?>>
          Substituir imagens existentes
        </label>
        <small class="product-form__helper">
          Novas imagens serão adicionadas ao final, a menos que você marque a substituição.
        </small>
        <div class="product-form__image-toolbar">
          <button type="submit" class="btn ghost" name="apply_image_changes" value="1">Excluir imagens selecionadas</button>
          <span class="product-form__helper">Marque a capa: a primeira imagem é a capa do produto.</span>
        </div>
        <div class="product-form__images-grid">
          <?php
            $defaultCoverValue = '';
            if ($coverImageSelection === '' && !empty($existingImages)) {
                $firstImage = $existingImages[0];
                $firstId = (int) ($firstImage['id'] ?? 0);
                $firstSrc = (string) ($firstImage['src'] ?? '');
                if ($firstId > 0) {
                    $defaultCoverValue = 'id:' . $firstId;
                } elseif ($firstSrc !== '') {
                    $defaultCoverValue = 'src:' . $firstSrc;
                }
            }
          ?>
          <?php foreach ($existingImages as $image): ?>
            <?php
              $imageSrc = (string) ($image['src'] ?? '');
              if ($imageSrc === '') {
                continue;
              }
              $imageId = (int) ($image['id'] ?? 0);
              $coverValue = $imageId > 0 ? ('id:' . $imageId) : ('src:' . $imageSrc);
              $isCover = $coverImageSelection !== '' ? $coverValue === $coverImageSelection : $coverValue === $defaultCoverValue;
              $removeChecked = $imageId > 0
                ? in_array($imageId, $removeImageIds, true)
                : in_array($imageSrc, $removeImageSrcs, true);
              $imageName = trim((string) ($image['name'] ?? ''));
              $imageLabel = $imageName !== '' ? $imageName : ('Imagem #' . (int) ($image['id'] ?? 0));
              if ($imageLabel === 'Imagem #0') {
                $imageLabel = 'Imagem do produto';
              }
            ?>
            <div class="product-form__image-card" data-image-viewer data-image-src="<?php echo $esc($imageSrc); ?>" data-image-label="<?php echo $esc($imageLabel); ?>" data-image-editable="true">
              <img src="<?php echo $esc($imageSrc); ?>" alt="<?php echo $esc($imageLabel); ?>">
              <span><?php echo $esc($imageLabel); ?></span>
              <div class="product-form__image-actions" data-image-viewer-ignore>
                <label>
                  <input type="radio" name="cover_image" value="<?php echo $esc($coverValue); ?>" <?php echo $isCover ? 'checked' : ''; ?>>
                  Capa
                </label>
                <label>
                  <input type="checkbox" name="<?php echo $imageId > 0 ? 'remove_image_ids[]' : 'remove_image_srcs[]'; ?>" value="<?php echo $esc($imageId > 0 ? (string) $imageId : $imageSrc); ?>" <?php echo $removeChecked ? 'checked' : ''; ?>>
                  Excluir
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="section product-form__section">
    <?php if ($lockInventory): ?>
      <input type="hidden" name="quantity" value="<?php echo $esc((string) $formData['quantity']); ?>">
      <input type="hidden" name="status" value="<?php echo $esc($statusValue); ?>">
      <input type="hidden" name="visibility" value="<?php echo $esc($visibilityValue); ?>">
    <?php endif; ?>
    <div class="product-form__inventory">
      <div class="field">
        <label for="weight">Peso (kg)</label>
  <input id="weight" name="weight" type="text" inputmode="decimal" data-number-br step="0.01" min="0" value="<?php echo $esc($formData['weight']); ?>">
      </div>
      <div class="field">
        <label for="quantity">Quantidade</label>
        <input id="quantity" name="quantity" type="number" min="0" value="<?php echo $esc($formData['quantity']); ?>" <?php echo $lockInventory ? 'disabled' : ''; ?>>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" <?php echo $lockInventory ? 'disabled' : ''; ?>>
          <option value="draft" <?php echo $statusValue === 'draft' ? 'selected' : ''; ?>>Rascunho</option>
          <option value="disponivel" <?php echo $statusValue === 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
          <option value="reservado" <?php echo $statusValue === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
          <option value="esgotado" <?php echo $statusValue === 'esgotado' ? 'selected' : ''; ?>>Esgotado</option>
          <option value="baixado" <?php echo $statusValue === 'baixado' ? 'selected' : ''; ?>>Baixado</option>
          <option value="archived" <?php echo $statusValue === 'archived' ? 'selected' : ''; ?>>Arquivado</option>
        </select>
      </div>
      <div class="field">
        <label for="visibility">Visibilidade no catálogo</label>
        <select id="visibility" name="visibility" <?php echo $lockInventory ? 'disabled' : ''; ?>>
          <option value="public" <?php echo $visibilityValue === 'public' ? 'selected' : ''; ?>>Catálogo e busca</option>
          <option value="catalog" <?php echo $visibilityValue === 'catalog' ? 'selected' : ''; ?>>Somente catálogo</option>
          <option value="search" <?php echo $visibilityValue === 'search' ? 'selected' : ''; ?>>Somente busca</option>
          <option value="hidden" <?php echo $visibilityValue === 'hidden' ? 'selected' : ''; ?>>Oculto</option>
        </select>
      </div>
    </div>
  </div>

  <?php if ($editing): ?>
    <div class="section product-form__section product-orders">
      <div class="product-form__section-heading">
        <h2 class="product-form__section-title">Pedidos com este produto</h2>
        <?php if (!empty($formData['id'])): ?>
          <div class="product-form__sale-actions">
            <label class="product-form__sale-actions-label" for="productSaleQuantity">Qtd</label>
            <input
              id="productSaleQuantity"
              class="product-form__sale-quantity"
              type="number"
              min="1"
              step="1"
              inputmode="numeric"
              aria-label="Quantidade a vender"
              value="<?php echo $esc($productSaleDefaultQuantity); ?>"
              data-product-sale-quantity
            >
            <button
              type="button"
              class="btn primary"
              data-open-product-sale
              data-product-sku="<?php echo (int) $formData['id']; ?>"
              aria-label="Abrir pop-up para vender este produto"
              <?php echo $canOpenSale ? '' : 'disabled title="Produto indisponível para venda"'; ?>
            >
              Vender este produto
            </button>
          </div>
        <?php endif; ?>
      </div>
      <div class="table-tools" style="margin:10px 0;">
        <span style="color:var(--muted);font-size:13px;">Mostrando <?php echo $ordersRangeStart; ?>–<?php echo $ordersRangeEnd; ?> de <?php echo $ordersTotal; ?></span>
        <form method="get" id="ordersPerPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="hidden" name="id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
          <input type="hidden" name="orders_page" value="1">
          <input type="hidden" name="writeoff_page" value="<?php echo (int) $writeoffPage; ?>">
          <input type="hidden" name="writeoff_per_page" value="<?php echo (int) $writeoffPerPage; ?>">
          <?php if (!empty($writeoffFilters['search'])): ?>
            <input type="hidden" name="writeoff_q" value="<?php echo $esc((string) $writeoffFilters['search']); ?>">
          <?php endif; ?>
          <?php foreach ($writeoffFilterParamMap as $filterKey => $paramName): ?>
            <?php $value = trim((string) ($writeoffFilters[$filterKey] ?? '')); ?>
            <?php if ($value !== ''): ?>
              <input type="hidden" name="<?php echo $esc($paramName); ?>" value="<?php echo $esc($value); ?>">
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($writeoffSortKey !== ''): ?>
            <input type="hidden" name="writeoff_sort_key" value="<?php echo $esc($writeoffSortKey); ?>">
          <?php endif; ?>
          <?php if ($writeoffSortDir !== ''): ?>
            <input type="hidden" name="writeoff_sort_dir" value="<?php echo $esc(strtolower($writeoffSortDir)); ?>">
          <?php endif; ?>
          <label for="ordersPerPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
          <select id="ordersPerPage" name="orders_per_page">
            <?php foreach ($ordersPerPageOptions as $option): ?>
              <option value="<?php echo (int) $option; ?>" <?php echo $ordersPerPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <?php if (empty($ordersForProduct)): ?>
        <div class="alert muted">Nenhum pedido encontrado para este produto.</div>
      <?php else: ?>
        <div class="product-orders__list">
          <?php foreach ($ordersForProduct as $orderRow): ?>
            <?php
              $orderId = (int) ($orderRow['order_id'] ?? 0);
              $statusKeyRaw = (string) ($orderRow['order_status'] ?? '');
              $statusKey = strpos($statusKeyRaw, 'wc-') === 0 ? substr($statusKeyRaw, 3) : $statusKeyRaw;
              $statusLabel = $orderStatusOptions[$statusKey] ?? $statusKey;
              if ($statusLabel === '') {
                $statusLabel = 'Sem status';
              }
              $paymentStatusKey = trim((string) ($orderRow['payment_status'] ?? ''));
              if ($paymentStatusKey === '') {
                if (in_array($statusKey, ['processing', 'completed'], true)) {
                  $paymentStatusKey = 'paid';
                } elseif ($statusKey === 'refunded') {
                  $paymentStatusKey = 'refunded';
                } elseif ($statusKey === 'failed') {
                  $paymentStatusKey = 'failed';
                } else {
                  $paymentStatusKey = 'pending';
                }
              }
              $paymentStatusLabel = $paymentStatusOptions[$paymentStatusKey] ?? $paymentStatusKey;
              $paymentStatusClass = $paymentStatusKey === 'paid' ? '' : ' order-payment-status--pending';
              $paymentStatusDisplay = $paymentStatusKey === 'pending'
                ? 'Aguardando pagamento'
                : 'Pagamento: ' . $paymentStatusLabel;
              $fulfillmentStatusKey = trim((string) ($orderRow['fulfillment_status'] ?? ''));
              if ($fulfillmentStatusKey === '') {
                if ($statusKey === 'completed') {
                  $fulfillmentStatusKey = 'entregue';
                } elseif ($statusKey === 'processing') {
                  $fulfillmentStatusKey = 'separacao';
                } elseif ($statusKey === 'cancelled') {
                  $fulfillmentStatusKey = 'cancelado';
                } else {
                  $fulfillmentStatusKey = 'novo';
                }
              }
              $fulfillmentStatusLabel = $fulfillmentStatusOptions[$fulfillmentStatusKey] ?? $fulfillmentStatusKey;
              $fulfillmentStatusClass = $fulfillmentStatusKey === 'entregue' ? '' : ' order-payment-status--pending';
              $fulfillmentStatusDisplay = 'Entrega: ' . $fulfillmentStatusLabel;
              $statusPillClass = '';
              if (in_array($statusKey, ['cancelled', 'canceled'], true)) {
                $statusPillClass = ' product-orders__status-pill--danger';
              } elseif ($statusKey === 'refunded') {
                $statusPillClass = ' product-orders__status-pill--warning';
              } elseif ($statusKey === 'completed') {
                $statusPillClass = ' product-orders__status-pill--success';
              } elseif ($statusKey === 'processing') {
                $statusPillClass = ' product-orders__status-pill--info';
              }
              $qty = (int) ($orderRow['product_qty'] ?? 0);
              $netValue = $orderRow['product_net_revenue'] ?? null;
              $netLabel = $netValue !== null ? 'R$ ' . number_format((float) $netValue, 2, ',', '.') : '-';
              $totalValue = $orderRow['total_sales'] ?? null;
              $totalLabel = $totalValue !== null ? 'R$ ' . number_format((float) $totalValue, 2, ',', '.') : '-';
              $dateRaw = (string) ($orderRow['date_created'] ?? '');
              $dateLabel = $dateRaw !== '' ? date('d/m/Y H:i', strtotime($dateRaw)) : '-';
              $returnSummary = $orderReturnsByOrder[$orderId] ?? [];
              $returnedQty = (int) ($returnSummary['returned_qty'] ?? 0);
              $hasReturn = $returnedQty > 0;
              $isTotalReturn = $hasReturn && $qty > 0 && $returnedQty >= $qty;
              $refundPending = !empty($returnSummary['refund_pending']);
              $refundDone = !empty($returnSummary['refund_done']);
              $isCancelled = in_array($statusKey, ['cancelled', 'canceled'], true);
              $isRefunded = $statusKey === 'refunded' || $paymentStatusKey === 'refunded';
            ?>
            <div class="product-orders__row">
              <div class="product-orders__main">
                <div class="product-orders__title">
                  <a class="product-orders__link" href="pedido-cadastro.php?id=<?php echo $orderId; ?>">Pedido #<?php echo $orderId; ?></a>
                  <span class="product-orders__date"><?php echo $esc($dateLabel); ?></span>
                </div>
                <div class="product-orders__meta">
                  <span><?php echo 'Qtd: ' . $qty; ?></span>
                  <span><?php echo 'Item: ' . $esc($netLabel); ?></span>
                  <span><?php echo 'Total pedido: ' . $esc($totalLabel); ?></span>
                </div>
              </div>
              <div class="product-orders__status">
                <div class="product-orders__badges">
                  <span class="product-orders__status-pill<?php echo $statusPillClass; ?>"><?php echo $esc($statusLabel); ?></span>
                  <span class="order-payment-status<?php echo $paymentStatusClass; ?>"><?php echo $esc($paymentStatusDisplay); ?></span>
                  <span class="order-payment-status<?php echo $fulfillmentStatusClass; ?>"><?php echo $esc($fulfillmentStatusDisplay); ?></span>
                </div>
                <div class="order-status-icons">
                  <?php if ($isCancelled): ?>
                    <span class="order-status-icon order-status-icon--cancelled" title="Pedido cancelado">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 0 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 1 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4l-4.9-4.9 4.9-4.9a1 1 0 0 0 0-1.4z"/>
                      </svg>
                    </span>
                  <?php endif; ?>
                  <?php if ($hasReturn): ?>
                    <?php if ($isTotalReturn): ?>
                      <span class="order-status-icon order-status-icon--return-total" title="Devolução total">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                          <path fill="currentColor" d="M12 5v2a5 5 0 1 1-4.58 7H5a7 7 0 1 0 7-9v2l3-3-3-3z"/>
                        </svg>
                      </span>
                    <?php else: ?>
                      <span class="order-status-icon order-status-icon--return-partial" title="Devolução parcial">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                          <path fill="currentColor" d="M12 5v2a5 5 0 1 1-4.58 7H5a7 7 0 1 0 7-9v2l3-3-3-3z M12 12h7v2h-7z"/>
                        </svg>
                        <span class="order-status-icon__label">Devolução parcial</span>
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($hasReturn && ($refundPending || $refundDone)): ?>
                    <span class="order-status-icon <?php echo $refundDone ? 'order-status-icon--refund-done' : 'order-status-icon--refund-pending'; ?>" title="<?php echo $refundDone ? 'Reembolso feito' : 'Reembolso pendente'; ?>">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="<?php echo $refundDone ? 'M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z' : 'M12 8v5l3 3-1.2 1.2L10 13V8h2zm0-6a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16z'; ?>"/>
                      </svg>
                    </span>
                  <?php elseif ($isRefunded): ?>
                    <span class="order-status-icon order-status-icon--refund-done" title="Pedido reembolsado">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
                      </svg>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
          <span style="color:var(--muted);font-size:13px;">Página <?php echo $ordersPage; ?> de <?php echo $ordersTotalPages; ?></span>
          <div style="display:flex;gap:8px;align-items:center;">
            <?php if ($ordersPage > 1): ?>
              <a class="btn ghost" href="<?php echo $esc($buildOrdersLink(1)); ?>">Primeira</a>
              <a class="btn ghost" href="<?php echo $esc($buildOrdersLink($ordersPage - 1)); ?>">Anterior</a>
            <?php else: ?>
              <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
              <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
            <?php endif; ?>

            <?php if ($ordersPage < $ordersTotalPages): ?>
              <a class="btn ghost" href="<?php echo $esc($buildOrdersLink($ordersPage + 1)); ?>">Próxima</a>
              <a class="btn ghost" href="<?php echo $esc($buildOrdersLink($ordersTotalPages)); ?>">Última</a>
            <?php else: ?>
              <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
              <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="section product-form__section product-writeoffs">
      <div class="product-writeoffs__header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <h2 class="product-form__section-title" style="margin:0;">Histórico de baixas</h2>
        <?php if ($editing && $formData['id'] !== ''): ?>
          <button
            type="button"
            class="btn ghost"
            data-writeoff-popup
            data-product-id="<?php echo $esc($formData['id']); ?>"
            data-product-sku="<?php echo $esc($formData['sku']); ?>"
          >Registrar baixa neste produto</button>
        <?php endif; ?>
      </div>
      <div class="table-tools" style="margin:10px 0;">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input
            type="search"
            data-filter-global
            placeholder="Buscar no histórico de baixas"
            aria-label="Busca geral no histórico de baixas"
            value="<?php echo $esc((string) ($writeoffFilters['search'] ?? '')); ?>"
          >
        </div>
        <form method="get" id="writeoffPerPageForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="hidden" name="id" value="<?php echo $esc((string) ($formData['id'] ?? '')); ?>">
          <input type="hidden" name="orders_page" value="<?php echo (int) $ordersPage; ?>">
          <input type="hidden" name="orders_per_page" value="<?php echo (int) $ordersPerPage; ?>">
          <input type="hidden" name="writeoff_page" value="1">
          <?php if (!empty($writeoffFilters['search'])): ?>
            <input type="hidden" name="writeoff_q" value="<?php echo $esc((string) $writeoffFilters['search']); ?>">
          <?php endif; ?>
          <?php foreach ($writeoffFilterParamMap as $filterKey => $paramName): ?>
            <?php $value = trim((string) ($writeoffFilters[$filterKey] ?? '')); ?>
            <?php if ($value !== ''): ?>
              <input type="hidden" name="<?php echo $esc($paramName); ?>" value="<?php echo $esc($value); ?>">
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($writeoffSortKey !== ''): ?>
            <input type="hidden" name="writeoff_sort_key" value="<?php echo $esc($writeoffSortKey); ?>">
          <?php endif; ?>
          <?php if ($writeoffSortDir !== ''): ?>
            <input type="hidden" name="writeoff_sort_dir" value="<?php echo $esc(strtolower($writeoffSortDir)); ?>">
          <?php endif; ?>
          <label for="writeoffPerPage" style="font-size:13px;color:var(--muted);">Itens por página</label>
          <select id="writeoffPerPage" name="writeoff_per_page">
            <?php foreach ($writeoffPerPageOptions as $option): ?>
              <option value="<?php echo (int) $option; ?>" <?php echo $writeoffPerPage === (int) $option ? 'selected' : ''; ?>><?php echo (int) $option; ?></option>
            <?php endforeach; ?>
          </select>
          <span style="font-size:13px;color:var(--muted);">Mostrando <?php echo $writeoffRangeStart; ?>–<?php echo $writeoffRangeEnd; ?> de <?php echo $writeoffTotal; ?></span>
        </form>
      </div>

      <div class="table-wrapper">
        <table
          data-table="interactive"
          data-filter-mode="server"
          data-page-param="writeoff_page"
          data-search-param="writeoff_q"
          data-sort-key-param="writeoff_sort_key"
          data-sort-dir-param="writeoff_sort_dir"
        >
          <thead>
            <tr>
              <th data-sort-key="created_at" aria-sort="none">Data</th>
              <th data-sort-key="destination" aria-sort="none">Destinação</th>
              <th data-sort-key="reason" aria-sort="none">Motivo</th>
              <th data-sort-key="quantity" aria-sort="none">Qtd</th>
              <th data-sort-key="supplier" aria-sort="none">Fornecedor</th>
              <th data-sort-key="stock_after" aria-sort="none">Disponibilidade (antes → depois)</th>
              <th data-sort-key="notes" aria-sort="none">Observações</th>
            </tr>
            <tr>
              <th><input type="search" data-filter-col="created_at" data-query-param="writeoff_filter_created_at" value="<?php echo $esc((string) ($writeoffFilters['filter_created_at'] ?? '')); ?>" placeholder="Data"></th>
              <th><input type="search" data-filter-col="destination" data-query-param="writeoff_filter_destination" value="<?php echo $esc((string) ($writeoffFilters['filter_destination'] ?? '')); ?>" placeholder="Destinação"></th>
              <th><input type="search" data-filter-col="reason" data-query-param="writeoff_filter_reason" value="<?php echo $esc((string) ($writeoffFilters['filter_reason'] ?? '')); ?>" placeholder="Motivo"></th>
              <th><input type="search" data-filter-col="quantity" data-query-param="writeoff_filter_quantity" value="<?php echo $esc((string) ($writeoffFilters['filter_quantity'] ?? '')); ?>" placeholder="Qtd"></th>
              <th><input type="search" data-filter-col="supplier" data-query-param="writeoff_filter_supplier" value="<?php echo $esc((string) ($writeoffFilters['filter_supplier'] ?? '')); ?>" placeholder="Fornecedor"></th>
              <th><input type="search" data-filter-col="stock_after" data-query-param="writeoff_filter_stock_after" value="<?php echo $esc((string) ($writeoffFilters['filter_stock_after'] ?? '')); ?>" placeholder="Disponibilidade"></th>
              <th><input type="search" data-filter-col="notes" data-query-param="writeoff_filter_notes" value="<?php echo $esc((string) ($writeoffFilters['filter_notes'] ?? '')); ?>" placeholder="Observações"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($writeoffHistory)): ?>
              <tr class="no-results"><td colspan="7">Nenhuma baixa registrada para este produto.</td></tr>
            <?php else: ?>
              <?php foreach ($writeoffHistory as $entry): ?>
                <?php
                  $createdAt = (string) ($entry['created_at'] ?? '');
                  $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
                  $dateLabel = $timestamp ? date('d/m/Y H:i', $timestamp) : ($createdAt !== '' ? $createdAt : '—');
                  $destinationLabel = $writeoffDestinationOptions[$entry['destination'] ?? ''] ?? ($entry['destination'] ?? '—');
                  $reasonLabel = $writeoffReasonOptions[$entry['reason'] ?? ''] ?? ($entry['reason'] ?? '—');
                  $quantityLabel = isset($entry['quantity']) ? (string) ((int) $entry['quantity']) : '—';
                  $supplierLabel = $entry['supplier_name'] ?? '—';
                  $stockBefore = isset($entry['stock_before']) ? (string) $entry['stock_before'] : '—';
                  $stockAfter = isset($entry['stock_after']) ? (string) $entry['stock_after'] : '—';
                  $stockLabel = $stockBefore . ' → ' . $stockAfter;
                  $notes = (string) ($entry['notes'] ?? '');
                ?>
                <tr>
                  <td data-value="<?php echo $esc($createdAt); ?>"><?php echo $esc($dateLabel); ?></td>
                  <td data-value="<?php echo $esc($entry['destination'] ?? ''); ?>"><?php echo $esc($destinationLabel); ?></td>
                  <td data-value="<?php echo $esc($entry['reason'] ?? ''); ?>"><?php echo $esc($reasonLabel); ?></td>
                  <td data-value="<?php echo $esc($quantityLabel); ?>"><?php echo $esc($quantityLabel); ?></td>
                  <td data-value="<?php echo $esc($supplierLabel); ?>"><?php echo $esc($supplierLabel); ?></td>
                  <td data-value="<?php echo $esc($stockAfter); ?>"><?php echo $esc($stockLabel); ?></td>
                  <td data-value="<?php echo $esc($notes); ?>"><?php echo $notes !== '' ? nl2br($esc($notes)) : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
        <span style="color:var(--muted);font-size:13px;">Página <?php echo $writeoffPage; ?> de <?php echo $writeoffTotalPages; ?></span>
        <div style="display:flex;gap:8px;align-items:center;">
          <?php if ($writeoffPage > 1): ?>
            <a class="btn ghost" href="<?php echo $esc($buildWriteoffLink(1)); ?>">Primeira</a>
            <a class="btn ghost" href="<?php echo $esc($buildWriteoffLink($writeoffPage - 1)); ?>">Anterior</a>
          <?php else: ?>
            <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Primeira</span>
            <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Anterior</span>
          <?php endif; ?>

          <?php if ($writeoffPage < $writeoffTotalPages): ?>
            <a class="btn ghost" href="<?php echo $esc($buildWriteoffLink($writeoffPage + 1)); ?>">Próxima</a>
            <a class="btn ghost" href="<?php echo $esc($buildWriteoffLink($writeoffTotalPages)); ?>">Última</a>
          <?php else: ?>
            <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Próxima</span>
            <span class="btn ghost" style="opacity:0.5;pointer-events:none;">Última</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="section product-form__section">
    <h2 class="product-form__section-title">Categorias</h2>
    <?php if (empty($categoryOptions)): ?>
      <div class="alert muted">Nenhuma categoria encontrada.</div>
    <?php else: ?>
      <div class="product-form__category-grid">
        <?php foreach ($categoryOptions as $category): ?>
          <label class="product-form__category-item">
            <?php
              $currentId = (string) ($category['term_id'] ?? '');
              $isSelected = in_array($currentId, $selectedCategoryIds, true);
            ?>
            <input type="checkbox" name="categoryIdsSelected[]" value="<?php echo $esc($currentId); ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
            <span class="product-form__category-label"><?php echo $esc($category['name'] ?? ''); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="footer">
    <button class="ghost" type="reset">Limpar</button>
    <button class="primary" type="submit" form="productForm" data-submit-button><?php echo $submitLabel; ?></button>
  </div>
</form>

<?php if ($editing && $formData['id'] !== ''): ?>
  <script>
    (function () {
      const trigger = document.querySelector('[data-writeoff-popup]');
      if (!trigger) {
        return;
      }
      const popupFeatures = 'width=1120,height=820,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes';
      trigger.addEventListener('click', () => {
        const sku = (trigger.dataset.productSku || trigger.dataset.productId || '').trim();
        if (!sku) {
          alert('Produto inválido para baixa.');
          return;
        }
        const params = new URLSearchParams();
        params.set('sku', sku);
        params.set('product_sku', sku);
        const query = params.toString();
        const url = query ? `produto-baixa.php?${query}` : 'produto-baixa.php';
        window.open(url, 'writeoffPopup', popupFeatures);
      });
    })();
  </script>
<?php endif; ?>

<?php if (!empty($canCreateBrand)): ?>
  <div class="modal-backdrop" data-brand-backdrop hidden></div>
  <div class="modal" data-brand-modal role="dialog" aria-modal="true" aria-labelledby="brandModalTitle" hidden>
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 id="brandModalTitle" style="margin:0;">Nova marca</h3>
          <div class="subtitle" style="margin:4px 0 0;">Cadastro rapido no app.</div>
        </div>
        <button type="button" class="icon-button" data-brand-close aria-label="Fechar janela">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 0 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 1 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4l-4.9-4.9 4.9-4.9a1 1 0 0 0 0-1.4z"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="modal-error" data-brand-error hidden></div>
        <div class="grid">
          <div class="field">
            <label for="brand_new_name">Nome *</label>
            <input type="text" id="brand_new_name" maxlength="200" required>
          </div>
          <div class="field">
            <label for="brand_new_slug">Slug</label>
            <input type="text" id="brand_new_slug" maxlength="200">
          </div>
          <div class="field" style="grid-column:1 / -1;">
            <label for="brand_new_description">Descrição</label>
            <textarea id="brand_new_description" rows="3" maxlength="1000"></textarea>
          </div>
        </div>
      </div>
      <div class="footer">
        <button type="button" class="btn ghost" data-brand-cancel>Cancelar</button>
        <button type="button" class="primary" data-brand-save>Criar marca</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  (function() {
    const successMessage = <?php echo json_encode($success ?? '', JSON_UNESCAPED_UNICODE); ?>;
    if (successMessage) {
      alert(successMessage);
    }
    const hasErrors = <?php echo json_encode(!empty($errors) || !empty($warnings)); ?>;
    if (hasErrors) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    const form = document.getElementById('productForm');
    const submitButtons = form ? Array.from(form.querySelectorAll('[data-submit-button]')) : [];
    const busyLabel = <?php echo json_encode($submitBusyLabel, JSON_UNESCAPED_UNICODE); ?>;
    const uploadInput = document.getElementById('product_images');
    const uploadMaxBytes = <?php echo (int) ($imageMaxSizeBytes ?? 0); ?>;
    const uploadTargetBytes = uploadMaxBytes > 0 ? Math.max(1024, uploadMaxBytes - (32 * 1024)) : 0;
    let submitBypassOptimization = false;

    const setSubmitButtonsBusy = (busy) => {
      submitButtons.forEach((button) => {
        if (busy) {
          if (button.disabled) return;
          button.dataset.originalLabel = button.textContent.trim();
          button.textContent = busyLabel;
          button.disabled = true;
          return;
        }
        button.disabled = false;
        const original = button.dataset.originalLabel || '';
        if (original !== '') {
          button.textContent = original;
        }
      });
    };

    const supportsWebp = (() => {
      try {
        const canvas = document.createElement('canvas');
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
      } catch (error) {
        return false;
      }
    })();

    const formatMegabytes = (bytes) => {
      if (!bytes || bytes <= 0) return '0';
      return (bytes / (1024 * 1024)).toFixed(2).replace(/\.00$/, '');
    };

    const replaceFileExtension = (fileName, extension) => {
      if (!fileName || !extension) return fileName;
      const dotIndex = fileName.lastIndexOf('.');
      if (dotIndex <= 0) return `${fileName}.${extension}`;
      return `${fileName.substring(0, dotIndex)}.${extension}`;
    };

    const extensionForMime = (mime) => {
      if (mime === 'image/webp') return 'webp';
      if (mime === 'image/png') return 'png';
      return 'jpg';
    };

    const canvasToBlob = (canvas, mimeType, quality) => new Promise((resolve) => {
      if (!canvas.toBlob) {
        resolve(null);
        return;
      }
      canvas.toBlob((blob) => resolve(blob), mimeType, quality);
    });

    const loadImageFromFile = (file) => new Promise((resolve, reject) => {
      const image = new Image();
      const objectUrl = URL.createObjectURL(file);
      image.onload = () => resolve(image);
      image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        reject(new Error(`Falha ao ler a imagem ${file.name}.`));
      };
      image.src = objectUrl;
    });

    const compressImageFile = async (file, targetBytes) => {
      if (!file.type.startsWith('image/')) {
        return file;
      }

      const outputMime = supportsWebp ? 'image/webp' : 'image/jpeg';
      const image = await loadImageFromFile(file);
      const sourceUrl = image.src;
      let scale = 1;
      let quality = 0.92;
      let bestBlob = null;
      let bestMime = outputMime;

      try {
        for (let attempt = 0; attempt < 12; attempt += 1) {
          const width = Math.max(1, Math.round(image.naturalWidth * scale));
          const height = Math.max(1, Math.round(image.naturalHeight * scale));
          const canvas = document.createElement('canvas');
          canvas.width = width;
          canvas.height = height;
          const context = canvas.getContext('2d', { alpha: outputMime !== 'image/jpeg' });
          if (!context) {
            break;
          }

          if (outputMime === 'image/jpeg') {
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, width, height);
          }
          context.drawImage(image, 0, 0, width, height);

          const blob = await canvasToBlob(canvas, outputMime, quality);
          if (blob && (!bestBlob || blob.size < bestBlob.size)) {
            bestBlob = blob;
            bestMime = blob.type || outputMime;
          }
          if (blob && blob.size <= targetBytes) {
            bestBlob = blob;
            bestMime = blob.type || outputMime;
            break;
          }

          if (quality > 0.72) {
            quality = Math.max(0.72, quality - 0.06);
          } else {
            scale *= 0.9;
            quality = 0.9;
            if (scale < 0.55) {
              break;
            }
          }
        }
      } finally {
        URL.revokeObjectURL(sourceUrl);
      }

      if (!bestBlob || bestBlob.size >= file.size) {
        return file;
      }

      const outputExtension = extensionForMime(bestMime);
      const outputName = replaceFileExtension(file.name, outputExtension);

      return new File([bestBlob], outputName, {
        type: bestMime,
        lastModified: Date.now(),
      });
    };

    const optimizeUploadFilesIfNeeded = async () => {
      if (!uploadInput || !uploadInput.files || uploadInput.files.length === 0 || uploadTargetBytes <= 0) {
        return;
      }
      if (typeof DataTransfer === 'undefined') {
        throw new Error('Seu navegador não suporta otimização automática de upload.');
      }

      const files = Array.from(uploadInput.files);
      const oversized = files.filter((file) => file.size > uploadTargetBytes);
      if (oversized.length === 0) {
        return;
      }

      const dataTransfer = new DataTransfer();
      for (const file of files) {
        if (file.size <= uploadTargetBytes) {
          dataTransfer.items.add(file);
          continue;
        }

        const optimized = await compressImageFile(file, uploadTargetBytes);
        if (optimized.size > uploadTargetBytes) {
          const limitLabel = formatMegabytes(uploadMaxBytes || uploadTargetBytes);
          throw new Error(`Não foi possível reduzir ${file.name} para ${limitLabel} MB automaticamente.`);
        }
        dataTransfer.items.add(optimized);
      }

      uploadInput.files = dataTransfer.files;
    };

    if (form && submitButtons.length) {
      form.addEventListener('submit', async (event) => {
        if (submitBypassOptimization) {
          return;
        }

        const hasUploadToOptimize = !!uploadInput
          && !!uploadInput.files
          && uploadInput.files.length > 0
          && uploadTargetBytes > 0
          && Array.from(uploadInput.files).some((file) => file.size > uploadTargetBytes);

        if (!hasUploadToOptimize) {
          setSubmitButtonsBusy(true);
          return;
        }

        event.preventDefault();
        setSubmitButtonsBusy(true);
        try {
          await optimizeUploadFilesIfNeeded();
          submitBypassOptimization = true;
          form.submit();
        } catch (error) {
          setSubmitButtonsBusy(false);
          const message = error instanceof Error
            ? error.message
            : 'Falha ao otimizar imagens para upload.';
          alert(message);
        }
      });
    }

    const skuPill = document.querySelector('[data-sku-pill]');
    const skuRetryButton = document.querySelector('[data-sku-retry-button]');
    const skuRetryStatus = document.querySelector('[data-sku-retry-status]');
    const skuContextKeyInput = document.querySelector('input[name="sku_context_key"]');
    const skuReservationIdInput = document.querySelector('input[name="sku_reservation_id"]');
    const reservedSkuInput = document.querySelector('input[name="reserved_sku"]');

    if (skuPill && skuRetryButton && skuContextKeyInput && skuReservationIdInput && reservedSkuInput) {
      let retryRunning = false;
      let retryStopRequested = false;
      const retryDefaultLabel = skuRetryButton.textContent || 'Tentar gerar SKU';
      const retryUrl = (form && form.getAttribute('action')) ? form.getAttribute('action') : 'produto-cadastro.php';

      const setRetryStatus = (message, state = '') => {
        if (!skuRetryStatus) return;
        skuRetryStatus.textContent = message || '';
        skuRetryStatus.hidden = message === '';
        skuRetryStatus.dataset.state = state;
      };

      const setRetryButtonState = (running) => {
        retryRunning = running;
        if (running) {
          skuRetryButton.textContent = 'Tentando... (clique para parar)';
          skuRetryButton.disabled = false;
          return;
        }
        skuRetryButton.textContent = retryDefaultLabel;
        skuRetryButton.disabled = false;
      };

      const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

      const requestSkuReservation = async () => {
        const payload = new URLSearchParams();
        payload.set('action', 'retry_sku_reservation');
        payload.set('sku_context_key', skuContextKeyInput.value || '');
        payload.set('sku_reservation_id', skuReservationIdInput.value || '');
        payload.set('reserved_sku', reservedSkuInput.value || '');

        const response = await fetch(retryUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: payload.toString(),
        });

        let data = null;
        try {
          data = await response.json();
        } catch (error) {
          data = null;
        }

        if (!response.ok || !data || data.ok !== true) {
          const error = new Error((data && data.message) ? data.message : 'Falha ao tentar reservar SKU.');
          error.retryable = !data || data.retryable !== false;
          throw error;
        }

        return data;
      };

      const applySkuReservation = (data) => {
        const sku = String((data && data.sku) || '').trim();
        if (sku === '') {
          return false;
        }
        skuPill.textContent = `SKU ${sku}`;
        reservedSkuInput.value = sku;
        skuReservationIdInput.value = String((data && data.reservation_id) || '');
        if (data && data.context_key) {
          skuContextKeyInput.value = String(data.context_key);
        }
        skuRetryButton.hidden = true;
        setRetryStatus('SKU gerado com sucesso.', 'success');
        return true;
      };

      const runRetryLoop = async () => {
        if (retryRunning) {
          retryStopRequested = true;
          setRetryStatus('Tentativas pausadas.', 'warning');
          return;
        }

        retryStopRequested = false;
        setRetryButtonState(true);
        setRetryStatus('Tentando gerar SKU...', 'loading');

        while (!retryStopRequested) {
          try {
            const data = await requestSkuReservation();
            if (applySkuReservation(data)) {
              break;
            }
            setRetryStatus('SKU ainda indisponível. Tentando novamente...', 'loading');
          } catch (error) {
            const message = error instanceof Error ? error.message : 'Falha ao tentar reservar SKU.';
            setRetryStatus(message, 'error');
            if (error && error.retryable === false) {
              break;
            }
          }
          await wait(1000);
        }

        setRetryButtonState(false);
      };

      skuRetryButton.addEventListener('click', runRetryLoop);
    }

    const costInput = document.getElementById('cost');
    const sourceSelect = document.getElementById('source');
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const SLUG_AUTO = 'autoslug';

    if (slugInput) {
      slugInput.dataset[SLUG_AUTO] = slugInput.value.trim() === '' ? 'true' : 'false';
    }

    function slugify(value) {
      return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ç/gi, 'c')
        .replace(/[^a-zA-Z0-9]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_+|_+$/g, '')
        .toLowerCase();
    }

    function updateSlug() {
      if (!nameInput || !slugInput || slugInput.dataset[SLUG_AUTO] === 'false') return;
      const newSlug = slugify(nameInput.value);
      slugInput.value = newSlug;
    }

    function toggleCost() {
      if (!sourceSelect || !costInput) return;
      const costWrap = document.getElementById('costWrap');
      const isCompra = sourceSelect.value === 'compra';
      if (costWrap) costWrap.style.display = isCompra ? '' : 'none';
      costInput.disabled = !isCompra;
      if (!isCompra) {
        costInput.value = '';
      }
    }

    function toggleConsignacao() {
      const wrap = document.getElementById('percentualConsignacaoWrap');
      const input = document.getElementById('percentualConsignacao');
      if (!sourceSelect || !wrap || !input) return;
      const isConsignacao = sourceSelect.value === 'consignacao';
      wrap.style.display = isConsignacao ? '' : 'none';
      if (!isConsignacao) {
        input.value = '';
      } else if (input.value.trim() === '') {
        input.value = '40';
      }
    }

    sourceSelect && sourceSelect.addEventListener('change', () => { toggleCost(); toggleConsignacao(); });
    nameInput && nameInput.addEventListener('input', updateSlug);
    slugInput && slugInput.addEventListener('input', () => { slugInput.dataset[SLUG_AUTO] = 'false'; });
    toggleCost();
    toggleConsignacao();
    updateSlug();

    <?php if (!empty($vendorOptions)): ?>
    const supplierInput = document.getElementById('supplier');
    const hint = document.getElementById('supplierHint');
    const lotSelect = document.getElementById('lotSelect');
    const lotHint = document.getElementById('lotHint');
    const lotOpenNewBtn = document.getElementById('lotOpenNew');
    const lotCloseOnlyBtn = document.getElementById('lotCloseOnly');
    const lotCloseAndOpenBtn = document.getElementById('lotCloseAndOpen');
    let lastSupplierValue = '';
    const initialSupplierValue = supplierInput ? supplierInput.value : '';
    const initialLotId = lotSelect ? lotSelect.value : '';
    const setLotDisplay = (lot, lots = []) => {
      if (!lotSelect || !lotHint) return;
      lotSelect.innerHTML = '';
      const optionDefault = document.createElement('option');
      optionDefault.value = '';
      optionDefault.textContent = supplierInput && supplierInput.value.trim() !== '' ? 'Selecione um lote' : 'Selecione um fornecedor';
      lotSelect.appendChild(optionDefault);
      const list = Array.isArray(lots) ? lots : [];
      list.forEach((row) => {
        const opt = document.createElement('option');
        opt.value = row.id ? String(row.id) : '';
        const status = row.status ? ` (${row.status})` : '';
        opt.textContent = `${row.name || `Lote #${row.id}`}${status}`;
        opt.dataset.status = row.status || '';
        opt.dataset.opened = row.opened_at || '';
        opt.dataset.closed = row.closed_at || '';
        lotSelect.appendChild(opt);
      });
      lotSelect.disabled = !supplierInput || supplierInput.value.trim() === '';
      if (!lot) {
        lotSelect.value = '';
        lotHint.textContent = 'Selecione um fornecedor para carregar o último lote aberto (ou criar um novo automaticamente).';
        lotHint.dataset.status = '';
        updateLotActions(null);
        return;
      }
      const selectedValue = lot.id ? String(lot.id) : '';
      lotSelect.value = selectedValue;
      const name = lot.name || (lot.id ? `Lote #${lot.id}` : '');
      const status = lot.status ? ` (${lot.status})` : '';
      lotHint.textContent = name ? `Lote selecionado: ${name}${status}` : 'Lote carregado.';
      lotHint.dataset.status = lot.status || '';
      updateLotActions(lot);
    };
    const updateLotActions = (lot) => {
      const isOpen = lot && lot.status === 'aberto';
      if (lotOpenNewBtn) lotOpenNewBtn.disabled = !supplierInput || supplierInput.value.trim() === '';
      if (lotCloseOnlyBtn) lotCloseOnlyBtn.disabled = !isOpen;
      if (lotCloseAndOpenBtn) lotCloseAndOpenBtn.disabled = !isOpen;
    };
    const fetchLot = (supplierId, currentLotId = '') => {
      if (!supplierId || !/^\d+$/.test(supplierId)) {
        setLotDisplay(null);
        return;
      }
      if (lotHint) {
        lotHint.textContent = 'Carregando lote aberto...';
      }
      const url = new URL('produto-lote-lookup.php', window.location.href);
      url.searchParams.set('supplier_pessoa_id', supplierId);
      if (currentLotId) {
        url.searchParams.set('current_lot_id', currentLotId);
      }
      fetch(url.toString(), {
        credentials: 'same-origin',
      })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
          if (!ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'Erro ao carregar lote.');
          }
          setLotDisplay(data.data || null, data.lots || []);
        })
        .catch((err) => {
          setLotDisplay(null);
          if (lotHint) {
            lotHint.textContent = err && err.message ? err.message : 'Erro ao carregar lote.';
          }
        });
    };
    const postLotAction = (action) => {
      const supplierId = supplierInput ? supplierInput.value.trim() : '';
      const lotId = lotSelect ? lotSelect.value : '';
      if (!supplierId || !/^\d+$/.test(supplierId)) {
        setLotDisplay(null);
        return;
      }
      const body = new URLSearchParams();
      body.set('supplier_pessoa_id', supplierId);
      body.set('action', action);
      if (lotId) {
        body.set('lot_id', lotId);
      }
      if (lotHint) lotHint.textContent = 'Atualizando lote...';
      fetch('produto-lote-lookup.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: body.toString(),
      })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
          if (!ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'Erro ao atualizar lote.');
          }
          setLotDisplay(data.data || null, data.lots || []);
        })
        .catch((err) => {
          if (lotHint) {
            lotHint.textContent = err && err.message ? err.message : 'Erro ao atualizar lote.';
          }
        });
    };
    if (supplierInput && hint) {
      const vendorMap = <?php echo json_encode(array_column($vendorOptions, 'full_name', 'id'), JSON_UNESCAPED_UNICODE); ?>;
      const updateHint = (value) => {
        const name = vendorMap[value];
        if (name) {
          hint.textContent = `Selecionado: ${name} — Pessoa ${value}`;
          hint.style.display = 'block';
        } else {
          hint.textContent = 'Pessoa não encontrada. Digite novamente ou selecione da lista.';
          hint.style.display = value.trim() === '' ? 'none' : 'block';
        }
        if (value !== lastSupplierValue) {
          lastSupplierValue = value;
          if (value.trim() === '') {
            setLotDisplay(null);
          } else if (!initialLotId || value !== initialSupplierValue) {
            fetchLot(value.trim());
          }
        }
      };
      supplierInput.addEventListener('input', () => updateHint(supplierInput.value));
      supplierInput.addEventListener('change', () => updateHint(supplierInput.value));
      updateHint(supplierInput.value);
      if ((!lotSelect || !lotSelect.value) && supplierInput.value.trim() !== '') {
        fetchLot(supplierInput.value.trim());
      }
      if (lotSelect && lotSelect.value) {
        const option = lotSelect.options[lotSelect.selectedIndex];
        updateLotActions(option && option.value ? { status: option.dataset.status || '' } : null);
      }
    }
    if (lotSelect) {
      lotSelect.addEventListener('change', () => {
        const option = lotSelect.options[lotSelect.selectedIndex];
        const selected = option && option.value ? {
          id: option.value,
          name: option.textContent || '',
          status: option.dataset.status || '',
        } : null;
        if (selected) {
          const label = selected.name.replace(/\s*\((aberto|fechado)\)\s*$/i, '');
          const statusLabel = selected.status ? ` (${selected.status})` : '';
          lotHint.textContent = `Lote selecionado: ${label}${statusLabel}`;
          lotHint.dataset.status = selected.status || '';
        } else {
          lotHint.textContent = 'Selecione um lote.';
          lotHint.dataset.status = '';
        }
        updateLotActions(selected);
      });
    }
    lotOpenNewBtn && lotOpenNewBtn.addEventListener('click', () => postLotAction('create'));
    lotCloseOnlyBtn && lotCloseOnlyBtn.addEventListener('click', () => {
      if (window.confirm('Encerrar o lote selecionado?')) {
        postLotAction('close');
      }
    });
    lotCloseAndOpenBtn && lotCloseAndOpenBtn.addEventListener('click', () => {
      if (window.confirm('Encerrar o lote selecionado e abrir um novo?')) {
        postLotAction('close_and_create');
      }
    });
    <?php endif; ?>

    const brandIdInput = document.getElementById('brand');
    const brandLookupInput = document.getElementById('brand_lookup');
    const brandHint = document.getElementById('brandHint');
    const brandDatalist = document.getElementById('brandOptionsList');
    const brandSuggestions = <?php echo $brandSuggestionsJson; ?>;
    const brandById = new Map();
    const brandByName = new Map();

    const normalizeBrandKey = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');

    const registerBrandOption = (brand) => {
      if (!brand) return null;
      const id = String(brand.id || '').trim();
      const name = String(brand.name || '').trim();
      if (!id || !name) return null;
      const normalized = normalizeBrandKey(name);
      const normalizedBrand = { id, name };
      brandById.set(id, normalizedBrand);
      if (!brandByName.has(normalized)) {
        brandByName.set(normalized, normalizedBrand);
      }
      return normalizedBrand;
    };

    const setBrandHint = (brand) => {
      if (!brandHint) return;
      if (!brand || !brand.id || !brand.name) {
        brandHint.textContent = '';
        brandHint.style.display = 'none';
        return;
      }
      brandHint.textContent = `Selecionada: ${brand.name} — ID ${brand.id}`;
      brandHint.style.display = 'block';
    };

    const resolveBrandByLookup = (value) => {
      const raw = String(value || '').trim();
      if (!raw) return null;
      if (brandById.has(raw)) {
        return brandById.get(raw);
      }
      const normalized = normalizeBrandKey(raw);
      if (brandByName.has(normalized)) {
        return brandByName.get(normalized);
      }
      return null;
    };

    const syncBrandLookup = () => {
      if (!brandLookupInput || !brandIdInput) return;
      const raw = brandLookupInput.value.trim();
      if (raw === '') {
        brandIdInput.value = '';
        setBrandHint(null);
        return;
      }
      const resolved = resolveBrandByLookup(raw);
      if (!resolved) {
        brandIdInput.value = '';
        if (brandHint) {
          brandHint.textContent = 'Selecione uma marca da lista para vincular ao cadastro.';
          brandHint.style.display = 'block';
        }
        return;
      }
      brandLookupInput.value = resolved.name;
      brandIdInput.value = resolved.id;
      setBrandHint(resolved);
    };

    if (Array.isArray(brandSuggestions)) {
      brandSuggestions.forEach(registerBrandOption);
    }

    if (brandLookupInput) {
      brandLookupInput.addEventListener('input', syncBrandLookup);
      brandLookupInput.addEventListener('change', syncBrandLookup);
    }
    if (brandLookupInput && brandLookupInput.value.trim() !== '') {
      syncBrandLookup();
    } else if (brandIdInput && brandIdInput.value.trim() !== '') {
      setBrandHint(brandById.get(brandIdInput.value.trim()) || null);
    }

    <?php if (!empty($canCreateBrand)): ?>
    const brandOpen = document.querySelector('[data-brand-open]');
    const brandModal = document.querySelector('[data-brand-modal]');
    const brandBackdrop = document.querySelector('[data-brand-backdrop]');
    const brandCloseButtons = document.querySelectorAll('[data-brand-close]');
    const brandCancel = document.querySelector('[data-brand-cancel]');
    const brandSave = document.querySelector('[data-brand-save]');
    const brandError = document.querySelector('[data-brand-error]');
    const brandNameInput = document.getElementById('brand_new_name');
    const brandSlugInput = document.getElementById('brand_new_slug');
    const brandDescriptionInput = document.getElementById('brand_new_description');
    const BRAND_SLUG_AUTO = 'brandSlugAuto';

    const setBrandInputsEnabled = (enabled) => {
      [brandNameInput, brandSlugInput, brandDescriptionInput].forEach((input) => {
        if (!input) return;
        input.disabled = !enabled;
      });
    };

    if (brandSlugInput) {
      brandSlugInput.dataset[BRAND_SLUG_AUTO] = 'true';
    }
    setBrandInputsEnabled(false);

    const resetBrandModal = () => {
      if (brandError) {
        brandError.textContent = '';
        brandError.hidden = true;
      }
      if (brandNameInput) brandNameInput.value = '';
      if (brandSlugInput) {
        brandSlugInput.value = '';
        brandSlugInput.dataset[BRAND_SLUG_AUTO] = 'true';
      }
      if (brandDescriptionInput) brandDescriptionInput.value = '';
      setBrandInputsEnabled(false);
    };

    const openBrandModal = () => {
      if (!brandModal || !brandBackdrop) return;
      resetBrandModal();
      setBrandInputsEnabled(true);
      brandModal.hidden = false;
      brandBackdrop.hidden = false;
      document.body.classList.add('modal-open');
      if (brandNameInput) brandNameInput.focus();
    };

    const closeBrandModal = () => {
      if (!brandModal || !brandBackdrop) return;
      brandModal.hidden = true;
      brandBackdrop.hidden = true;
      setBrandInputsEnabled(false);
      document.body.classList.remove('modal-open');
    };

    const updateBrandSlug = () => {
      if (!brandNameInput || !brandSlugInput || brandSlugInput.dataset[BRAND_SLUG_AUTO] === 'false') return;
      brandSlugInput.value = slugify(brandNameInput.value);
    };

    const showBrandError = (message) => {
      if (!brandError) return;
      brandError.textContent = message;
      brandError.hidden = false;
    };

    const applyBrandToSelect = (brand) => {
      if (!brand) return;
      const brandId = String(brand.id || '').trim();
      const brandName = String(brand.name || '').trim();
      if (!brandId || !brandName) return;
      const normalizedBrand = registerBrandOption({ id: brandId, name: brandName });
      if (!normalizedBrand) return;

      if (brandDatalist) {
        const exists = Array.from(brandDatalist.options).some((option) => {
          return normalizeBrandKey(option.value) === normalizeBrandKey(normalizedBrand.name);
        });
        if (!exists) {
          const option = document.createElement('option');
          option.value = normalizedBrand.name;
          const label = `${normalizedBrand.name} — ID ${normalizedBrand.id}`;
          option.label = label;
          option.textContent = label;
          brandDatalist.appendChild(option);
        }
      }
      if (brandLookupInput) {
        if (brandLookupInput.disabled) {
          brandLookupInput.disabled = false;
        }
        brandLookupInput.value = normalizedBrand.name;
      }
      if (brandIdInput) {
        brandIdInput.value = normalizedBrand.id;
      }
      setBrandHint(normalizedBrand);
    };

    const saveBrand = async () => {
      if (!brandSave || !brandNameInput) return;
      const name = brandNameInput.value.trim();
      if (!name) {
        showBrandError('Informe o nome da marca.');
        return;
      }
      if (brandError) {
        brandError.hidden = true;
      }

      brandSave.disabled = true;
      const originalLabel = brandSave.textContent;
      brandSave.textContent = 'Salvando...';

      try {
        const payload = new FormData();
        payload.set('name', name);
        const slug = brandSlugInput ? brandSlugInput.value.trim() : '';
        const description = brandDescriptionInput ? brandDescriptionInput.value.trim() : '';
        if (slug) payload.set('slug', slug);
        if (description) payload.set('description', description);

        const response = await fetch('marca-quick-create.php', {
          method: 'POST',
          body: payload,
          headers: { 'Accept': 'application/json' },
        });
        const rawText = await response.text();
        let data = null;
        if (rawText) {
          try {
            data = JSON.parse(rawText);
          } catch (error) {
            data = null;
          }
        }
        if (!response.ok || !data || data.ok !== true) {
          const fallbackMessage = rawText && rawText.trim() !== '' ? rawText : 'Erro ao criar marca.';
          throw new Error((data && data.message) ? data.message : fallbackMessage);
        }
        applyBrandToSelect(data.brand || {});
        closeBrandModal();
      } catch (error) {
        showBrandError(error instanceof Error ? error.message : 'Erro ao criar marca.');
      } finally {
        brandSave.disabled = false;
        brandSave.textContent = originalLabel || 'Criar marca';
      }
    };

    brandNameInput && brandNameInput.addEventListener('input', updateBrandSlug);
    brandSlugInput && brandSlugInput.addEventListener('input', () => { brandSlugInput.dataset[BRAND_SLUG_AUTO] = 'false'; });
    brandOpen && brandOpen.addEventListener('click', openBrandModal);
    brandBackdrop && brandBackdrop.addEventListener('click', closeBrandModal);
    brandCancel && brandCancel.addEventListener('click', closeBrandModal);
    brandSave && brandSave.addEventListener('click', saveBrand);
    brandCloseButtons.forEach((button) => button.addEventListener('click', closeBrandModal));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && brandModal && !brandModal.hidden) {
        closeBrandModal();
      }
    });
    <?php endif; ?>

    const productSaleButton = document.querySelector('[data-open-product-sale]');
    if (productSaleButton) {
      const productSaleQuantityInput = document.querySelector('[data-product-sale-quantity]');
      const openProductSalePopup = () => {
        const targetProductSku = productSaleButton.dataset.productSku;
        if (!targetProductSku) {
          return;
        }
        let quantity = 1;
        if (productSaleQuantityInput) {
          const parsed = Number.parseInt(productSaleQuantityInput.value, 10);
          if (Number.isFinite(parsed) && parsed > 0) {
            quantity = parsed;
          } else {
            productSaleQuantityInput.value = '1';
          }
        }
        const popupUrl = new URL('pedido-cadastro.php', window.location.href);
        popupUrl.searchParams.set('sku', targetProductSku);
        popupUrl.searchParams.set('quantity', String(quantity));
        const popup = window.open(popupUrl.toString(), 'retrato-product-sale', 'width=1200,height=900,resizable,scrollbars');
        if (popup) {
          popup.focus();
        }
      };
      productSaleButton.addEventListener('click', openProductSalePopup);
      if (productSaleQuantityInput) {
        productSaleQuantityInput.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            openProductSalePopup();
          }
        });
      }
    }
  })();
</script>

<script>
  (function() {
    const ordersPerPage = document.getElementById('ordersPerPage');
    const ordersForm = document.getElementById('ordersPerPageForm');
    if (ordersPerPage && ordersForm) {
      ordersPerPage.addEventListener('change', () => ordersForm.submit());
    }
    const writeoffPerPage = document.getElementById('writeoffPerPage');
    const writeoffForm = document.getElementById('writeoffPerPageForm');
    if (writeoffPerPage && writeoffForm) {
      writeoffPerPage.addEventListener('change', () => writeoffForm.submit());
    }
  })();
</script>
