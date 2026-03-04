<?php
/** @var array $errors */
/** @var string $success */
/** @var array|null $openInventory */
/** @var array|null $closedInventory */
/** @var array $pendingRows */
/** @var array $recentScans */
/** @var array|null $summary */
/** @var string $suggestedReason */
/** @var array $categoryOptions */
/** @var array $brandOptions */
/** @var array $tagOptions */
/** @var array $imageUploadInfo */
/** @var array $currentUser */
/** @var callable $resolveUserName */
/** @var callable $formatDate */
/** @var callable $esc */
?>
<?php
  $categoryOptions = $categoryOptions ?? [];
  $brandOptions = $brandOptions ?? [];
  $tagOptions = $tagOptions ?? [];
  $imageUploadInfo = $imageUploadInfo ?? [];
  $imageMaxFiles = (int) ($imageUploadInfo['maxFiles'] ?? 6);
  $imageMaxSizeLabel = (string) ($imageUploadInfo['maxSizeLabel'] ?? '5');
  $imageExtensions = $imageUploadInfo['allowedExtensions'] ?? ['jpg', 'png', 'webp'];
  $imageAccept = (string) ($imageUploadInfo['accept'] ?? 'image/jpeg,image/png,image/webp');
  $imageExtensionsLabel = strtoupper(implode(', ', $imageExtensions));
  $availabilityLabel = static function ($raw): string {
      $value = strtolower(trim((string) $raw));
      $labels = [
          'instock' => 'Disponível',
          'disponivel' => 'Disponível',
          'reservado' => 'Reservado',
          'outofstock' => 'Indisponível',
          'esgotado' => 'Esgotado',
          'indisponivel' => 'Indisponível',
          'baixado' => 'Baixado',
          'archived' => 'Arquivado',
          'draft' => 'Rascunho',
      ];
      return $labels[$value] ?? ($value !== '' ? ucfirst($value) : '—');
  };
?>
<style>
  .inventory-hero { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .inventory-panels { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr); gap: 14px; margin-top: 12px; }
  @media (max-width: 980px) { .inventory-panels { grid-template-columns: 1fr; } }
  .scan-row { display: grid; grid-template-columns: minmax(0, 1fr) 90px auto; gap: 8px; align-items: center; }
  .scan-row input[type="text"] { width: 100%; }
  .scan-row input[type="number"] { width: 90px; }
  .scan-actions { display: flex; gap: 8px; align-items: center; }
  .scan-actions button { white-space: nowrap; }
  .inventory-card { background: #fff; border: 1px solid #eef2f7; border-radius: 16px; padding: 14px; box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08); }
  .inventory-header { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
  .inventory-thumb { width: 86px; height: 86px; border-radius: 16px; background: #f1f5f9; overflow: hidden; display: grid; place-items: center; }
  .inventory-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .inventory-stats { display: flex; gap: 8px; flex-wrap: wrap; }
  .inventory-stats .pill { background: #f8fafc; color: #0f172a; border: 1px solid #e2e8f0; }
  .inventory-muted { color: var(--muted); font-size: 13px; }
  .inventory-supply { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 8px; font-size: 14px; }
  .inventory-category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-top: 8px; }
  .inventory-category-item { display: flex; gap: 8px; align-items: center; border: 1px solid #e2e8f0; border-radius: 10px; padding: 6px 8px; background: #f8fafc; }
  .inventory-image-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 8px; }
  .inventory-image-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 6px; display: grid; gap: 6px; background: #fff; }
  .inventory-image-card img { width: 100%; height: 110px; object-fit: cover; border-radius: 8px; }
  .inventory-image-card.is-removed { opacity: 0.6; border-style: dashed; }
  .inventory-image-card-footer { display: flex; gap: 6px; align-items: center; justify-content: space-between; font-size: 12px; }
  .inventory-image-card-footer button { padding: 4px 8px; font-size: 12px; }
  .camera-wrap { margin-top: 12px; border-radius: 16px; border: 1px dashed #cbd5f5; padding: 12px; background: #f8fafc; display: none; }
  .camera-wrap.is-active { display: block; }
  .camera-video { width: 100%; max-height: 320px; border-radius: 12px; background: #0f172a; }
  .duplicate-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
  .inventory-table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .inventory-table th, .inventory-table td { padding: 8px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
  .inventory-table th { text-transform: uppercase; font-size: 12px; color: var(--muted); letter-spacing: 0.04em; }
  .inventory-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
  .inventory-actions .primary { margin-left: auto; }
  .toast-stack { position: fixed; top: 18px; right: 18px; display: grid; gap: 8px; z-index: 9999; }
  .toast { border-radius: 12px; padding: 10px 12px; border: 1px solid; font-size: 13px; font-weight: 700; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12); background: #fff; transition: opacity 0.2s ease, transform 0.2s ease; }
  .toast.success { background: #ecfdf3; border-color: #4ade80; color: #065f46; }
  .toast.error { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
  .toast.is-hidden { opacity: 0; transform: translateY(-6px); }
  .recent-scan-row { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
  .recent-scan-meta { text-align: right; }
  .recent-scan-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
  .scan-delete { margin-top: 6px; }
  .scan-edit { margin-top: 6px; }
  @media (max-width: 720px) {
    .scan-row { grid-template-columns: 1fr; }
    .scan-row input[type="number"] { width: 100%; }
    .scan-actions { display: grid; grid-template-columns: 1fr 1fr; }
    .scan-actions button,
    .inventory-save-footer button,
    .inventory-close-button {
      width: 100%;
      padding: 14px 16px;
      font-size: 16px;
      border-radius: 16px;
    }
    .inventory-save-footer {
      position: sticky;
      bottom: 12px;
      background: #fff;
      padding: 10px;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
      z-index: 5;
    }
    .inventory-close-form {
      position: sticky;
      bottom: 12px;
      background: #fff;
      padding: 10px;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
      z-index: 4;
    }
    .recent-scan-row { flex-direction: column; align-items: flex-start; }
    .recent-scan-meta { width: 100%; text-align: left; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .recent-scan-actions { justify-content: flex-start; }
    .scan-delete { margin-top: 0; }
    .scan-edit { margin-top: 0; }
  }
</style>

<div>
  <div class="inventory-hero">
    <div>
      <h1>Conferência de disponibilidade</h1>
      <div class="subtitle">Leia SKUs, confirme dados e sincronize a disponibilidade em tempo real.</div>
    </div>
    <span class="pill">
      <?php if ($openInventory): ?>
        Batimento #<?php echo $esc((string) $openInventory['id']); ?> aberto
      <?php else: ?>
        Nenhum batimento aberto
      <?php endif; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>
  <div id="toastStack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>

  <?php if ($openInventory): ?>
    <div id="inventoryApp"
      data-inventory-id="<?php echo $esc((string) $openInventory['id']); ?>"
      data-blind-count="<?php echo !empty($openInventory['blind_count']) ? '1' : '0'; ?>"
      data-default-reason="<?php echo $esc((string) ($openInventory['default_reason'] ?? '')); ?>">
    </div>

    <div class="inventory-panels">
      <div class="inventory-card">
        <div class="inventory-header">
          <div>
            <div class="inventory-muted">Aberto em <?php echo $esc($formatDate((string) ($openInventory['opened_at'] ?? ''))); ?></div>
            <h2 style="margin:4px 0;">Leitura de SKUs</h2>
            <div class="inventory-muted">Responsável: <?php echo $esc($resolveUserName((int) ($openInventory['opened_by'] ?? 0))); ?></div>
          </div>
          <div class="pill"><?php echo !empty($openInventory['blind_count']) ? 'Contagem cega' : 'Contagem visível'; ?></div>
        </div>

        <form id="scanForm" class="inventory-section" style="margin-top:14px;">
          <div class="field">
            <label for="scanSku">SKU / Código de barras *</label>
            <div class="scan-row">
              <input id="scanSku" name="sku" type="text" placeholder="Digite ou leia o código" autocomplete="off" required>
              <input id="scanQty" name="quantity" type="number" min="1" value="1" aria-label="Quantidade">
              <div class="scan-actions">
                <button class="primary scan-submit" type="submit">Ler</button>
                <button class="ghost" type="button" id="cameraStart">Câmera</button>
              </div>
            </div>
            <small class="inventory-muted">Pressione Enter para ler mais rápido. A câmera funciona melhor com o SKU em foco.</small>
          </div>
        </form>

        <div id="cameraWrap" class="camera-wrap">
          <div class="inventory-actions" style="margin-bottom:8px;">
            <strong>Leitura por câmera</strong>
            <select id="cameraSelect" class="ghost" style="min-width:180px;"></select>
            <button class="ghost" type="button" id="cameraStop">Encerrar</button>
          </div>
          <video id="cameraVideo" class="camera-video" autoplay playsinline></video>
          <div id="cameraStatus" class="inventory-muted" style="margin-top:6px;"></div>
        </div>

        <div id="duplicateAlert" class="alert error" style="display:none;">
          <div><strong>SKU já lido neste batimento.</strong></div>
          <div id="duplicateMeta" class="inventory-muted" style="margin-top:4px;"></div>
          <div class="duplicate-actions">
            <button class="primary" type="button" data-dup-action="increment">Somar leitura</button>
            <button class="ghost" type="button" data-dup-action="override">Substituir contagem</button>
            <button class="ghost" type="button" data-dup-action="ignore">Ignorar</button>
          </div>
        </div>

        <div id="productPanel" class="inventory-card" style="margin-top:14px; display:none;">
          <div class="inventory-header">
              <div class="inventory-thumb" id="productImage"></div>
            <div>
              <h2 id="productName" style="margin:0;"></h2>
              <div class="inventory-muted">
                SKU <span id="productSku"></span>
                <span style="margin-left:6px;">(ID <span id="productId"></span>)</span>
              </div>
            </div>
          </div>

          <div class="inventory-stats" style="margin-top:10px;">
            <span class="pill" id="stockPill">Disponível no sistema: <strong id="productStock"></strong></span>
            <span class="pill">Contado: <strong id="countedQty"></strong></span>
            <span class="pill">Leituras: <strong id="scanCount"></strong></span>
          </div>

          <div class="inventory-supply">
            <div><strong>Fornecimento:</strong> <span id="productSupplySource">-</span></div>
            <div><strong>Fornecedor:</strong> <span id="productSupplySupplier">-</span></div>
          </div>

          <div id="productFeedback" class="alert success" style="display:none; margin-top:10px;"></div>

          <form id="productForm" style="margin-top:12px;">
            <input type="hidden" id="productIdInput" name="product_id" value="">
            <div class="grid">
              <div class="field">
                <label for="countedQtyInput">Quantidade lida</label>
                <input id="countedQtyInput" name="counted_quantity" type="number" min="0" value="0">
              </div>
              <div class="field">
                <label for="productNameInput">Nome</label>
                <input id="productNameInput" name="name" type="text" maxlength="120" value="">
              </div>
              <div class="field">
                <label for="productPriceInput">Preço (R$)</label>
                <input id="productPriceInput" name="price" type="text" inputmode="decimal" data-number-br step="0.01" min="0" value="">
              </div>
              <div class="field">
                <label for="productStatusInput">Status</label>
                <select id="productStatusInput" name="status">
                  <option value="draft">Rascunho</option>
                  <option value="disponivel">Disponível</option>
                  <option value="reservado">Reservado</option>
                  <option value="esgotado">Esgotado</option>
                  <option value="baixado">Baixado</option>
                  <option value="archived">Arquivado</option>
                </select>
              </div>
            </div>

            <details style="margin-top:10px;">
              <summary style="cursor:pointer;font-weight:600;">Campos extras</summary>
              <div class="grid" style="margin-top:10px;">
                <div class="field" style="grid-column:1 / -1;">
                  <label for="productDescriptionInput">Descrição</label>
                  <textarea id="productDescriptionInput" name="description" rows="3"></textarea>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                  <label for="productShortDescriptionInput">Resumo</label>
                  <textarea id="productShortDescriptionInput" name="short_description" rows="2"></textarea>
                </div>
                <div class="field">
                  <label for="productWeightInput">Peso (kg)</label>
                  <input id="productWeightInput" name="weight" type="text" inputmode="decimal" data-number-br step="0.01" min="0" value="">
                </div>
              </div>
            </details>

            <div class="inventory-section" style="margin-top:12px;">
              <div class="grid">
                <div class="field">
                  <label for="productBrandInput">Marca</label>
                  <select id="productBrandInput" name="brand" <?php echo empty($brandOptions) ? 'disabled' : ''; ?>>
                    <?php if (empty($brandOptions)): ?>
                      <option value="">Nenhuma marca encontrada</option>
                    <?php else: ?>
                      <option value="">Sem marca</option>
                      <?php foreach ($brandOptions as $brand): ?>
                        <?php $brandId = (string) ($brand['term_id'] ?? ''); ?>
                        <option value="<?php echo $esc($brandId); ?>">
                          <?php echo $esc($brand['name'] ?? ''); ?>
                        </option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                  <?php if (empty($brandOptions)): ?>
                    <small class="inventory-muted">Cadastre marcas no app para selecionar.</small>
                  <?php endif; ?>
                </div>
              </div>

              <div style="margin-top:10px;">
                <div style="font-weight:600;">Tags</div>
                <?php if (empty($tagOptions)): ?>
                  <div class="inventory-muted">Nenhuma tag encontrada.</div>
                <?php else: ?>
                  <div class="inventory-category-grid">
                    <?php foreach ($tagOptions as $tag): ?>
                      <?php $tagId = (string) ($tag['term_id'] ?? ''); ?>
                      <label class="inventory-category-item">
                        <input type="checkbox" name="tag_ids[]" value="<?php echo $esc($tagId); ?>">
                        <span><?php echo $esc($tag['name'] ?? ''); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="inventory-section" style="margin-top:12px;">
              <div style="font-weight:600;">Categorias</div>
              <?php if (empty($categoryOptions)): ?>
                <div class="inventory-muted">Nenhuma categoria encontrada.</div>
              <?php else: ?>
                <div class="inventory-category-grid">
                  <?php foreach ($categoryOptions as $category): ?>
                    <?php $categoryId = (string) ($category['term_id'] ?? ''); ?>
                    <label class="inventory-category-item">
                      <input type="checkbox" name="category_ids[]" value="<?php echo $esc($categoryId); ?>">
                      <span><?php echo $esc($category['name'] ?? ''); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="inventory-section" style="margin-top:12px;">
              <div class="inventory-actions" style="margin-bottom:6px;">
                <strong>Fotos do produto</strong>
                <button class="ghost" type="button" id="photoCameraStart">Tirar foto</button>
                <button class="ghost" type="button" id="clearNewImages">Limpar novas fotos</button>
              </div>
              <div id="productImagesList" class="inventory-image-grid"></div>
              <div id="productImagesEmpty" class="inventory-muted" style="display:none;">Sem imagens cadastradas.</div>
              <div class="field" style="margin-top:10px;">
                <label for="productImagesInput">Adicionar fotos</label>
                <input id="productImagesInput" name="product_images[]" type="file" accept="<?php echo $esc($imageAccept); ?>" multiple>
                <small class="inventory-muted">
                  Até <?php echo $imageMaxFiles; ?> imagens (<?php echo $esc($imageExtensionsLabel); ?>) com até <?php echo $esc($imageMaxSizeLabel); ?> MB cada.
                </small>
              </div>
              <div id="photoCameraWrap" class="camera-wrap">
                <div class="inventory-actions" style="margin-bottom:8px;">
                  <strong>Câmera para foto</strong>
                  <button class="ghost" type="button" id="photoCameraCapture">Capturar</button>
                  <button class="ghost" type="button" id="photoCameraStop">Encerrar</button>
                </div>
                <video id="photoCameraVideo" class="camera-video" autoplay playsinline></video>
                <div id="photoCameraStatus" class="inventory-muted" style="margin-top:6px;"></div>
              </div>
              <div class="inventory-muted" style="margin-top:8px;">Novas fotos (ainda não salvas)</div>
              <div id="newImagesList" class="inventory-image-grid"></div>
            </div>

            <div class="field" style="margin-top:10px;">
              <label for="productReasonInput">Motivo do ajuste</label>
              <input id="productReasonInput" name="reason" type="text" maxlength="255" value="<?php echo $esc((string) ($openInventory['default_reason'] ?? '')); ?>">
            </div>

            <div class="footer inventory-save-footer">
              <button class="primary inventory-save-button" type="submit">Salvar ajustes</button>
            </div>
          </form>
        </div>
      </div>

      <div class="inventory-card">
        <h2 style="margin-top:0;">Resumo do batimento</h2>
        <?php if ($summary): ?>
          <div class="inventory-stats" style="margin-bottom:12px;">
            <span class="pill">SKUs lidos: <strong id="summaryUnique"><?php echo $esc((string) ($summary['unique_items'] ?? 0)); ?></strong></span>
            <span class="pill">Total contado: <strong id="summaryTotalCounted"><?php echo $esc((string) ($summary['total_counted'] ?? 0)); ?></strong></span>
            <span class="pill">Leituras: <strong id="summaryTotalScans"><?php echo $esc((string) ($summary['total_scans'] ?? 0)); ?></strong></span>
            <span class="pill">Salvamentos pendentes: <strong id="pendingSaves">0</strong></span>
          </div>
        <?php endif; ?>

        <div class="inventory-muted" style="margin-bottom:8px;">Motivo padrão: <?php echo $esc((string) ($openInventory['default_reason'] ?? '')); ?></div>

        <div class="inventory-card" style="background:#f8fafc; border-style:dashed;">
          <h3 style="margin-top:0;">Últimas leituras</h3>
          <div id="recentScans">
            <?php if (!empty($recentScans)): ?>
              <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;">
                <?php foreach ($recentScans as $scan): ?>
                  <li class="recent-scan-row">
                    <div>
                      <strong><?php echo $esc((string) ($scan['sku'] ?? '')); ?></strong>
                      <div class="inventory-muted"><?php echo $esc((string) ($scan['product_name'] ?? '')); ?></div>
                    </div>
                    <div class="inventory-muted recent-scan-meta">
                      <div>Qtd <?php echo $esc((string) ($scan['quantity'] ?? 1)); ?></div>
                      <div><?php echo $esc($formatDate((string) ($scan['created_at'] ?? ''))); ?></div>
                      <div class="recent-scan-actions">
                        <?php $scanProductSku = (int) (($scan['product_sku'] ?? 0) ?: ($scan['product_id'] ?? 0)); ?>
                        <button class="ghost scan-edit" type="button" data-scan-action="edit" data-product-id="<?php echo $scanProductSku; ?>">Editar</button>
                        <button class="ghost scan-delete" type="button" data-scan-action="delete" data-scan-id="<?php echo (int) ($scan['id'] ?? 0); ?>">Excluir</button>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="inventory-muted">Nenhuma leitura registrada ainda.</div>
            <?php endif; ?>
          </div>
        </div>

        <details id="scanHistory" style="margin-top:12px;">
          <summary style="cursor:pointer;font-weight:600;">Histórico completo de leituras</summary>
          <div class="inventory-section" style="margin-top:10px;">
            <div class="field">
              <label for="scanHistorySearch">Buscar por SKU ou nome</label>
              <input id="scanHistorySearch" type="text" placeholder="Digite para filtrar">
            </div>
            <div id="scanHistoryList"></div>
            <div class="inventory-actions" style="margin-top:8px;">
              <button class="ghost" type="button" id="scanHistoryMore">Carregar mais</button>
              <span id="scanHistoryStatus" class="inventory-muted"></span>
            </div>
          </div>
        </details>

        <div class="inventory-muted" style="margin-top:12px;">
          Conclusão do batimento agora fica no acompanhamento.
          <?php if (userCan('inventory.monitor')): ?>
            <a href="disponibilidade-conferencia-acompanhamento.php">Ir para acompanhamento</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="inventory-card" style="margin-top:12px;">
      <h2 style="margin-top:0;">Iniciar novo batimento</h2>
      <?php if (userCan('inventory.open')): ?>
        <form method="post">
          <div class="grid">
            <div class="field">
              <label for="default_reason">Motivo padrão *</label>
              <input id="default_reason" name="default_reason" type="text" maxlength="255" value="<?php echo $esc($suggestedReason); ?>" required>
            </div>
            <div class="field">
              <label style="display:flex;gap:8px;align-items:center;">
                <input type="checkbox" name="blind_count" value="1">
                <span>Contagem cega (ocultar quantidade disponível no sistema)</span>
              </label>
            </div>
          </div>
          <div class="footer">
            <button class="primary" type="submit" name="open_inventory" value="1">Abrir batimento</button>
          </div>
        </form>
      <?php else: ?>
        <div class="inventory-muted">Você não tem permissão para abrir batimento.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($closedInventory): ?>
    <div class="inventory-card" style="margin-top:18px;">
      <div class="inventory-hero" style="margin-bottom:10px;">
        <div>
          <h2 style="margin:0;">Pendências do batimento #<?php echo $esc((string) $closedInventory['id']); ?></h2>
          <div class="inventory-muted">Itens não lidos que estavam com disponibilidade positiva.</div>
        </div>
        <span class="pill">Fechado em <?php echo $esc($formatDate((string) ($closedInventory['closed_at'] ?? ''))); ?></span>
      </div>

      <?php if (!empty($pendingRows)): ?>
        <form method="post">
          <input type="hidden" name="inventory_id" value="<?php echo $esc((string) $closedInventory['id']); ?>">
          <table class="inventory-table">
            <thead>
              <tr>
                <th><input type="checkbox" id="pendingSelectAll"></th>
                <th>SKU</th>
                <th>Produto</th>
                <th>Qtd disponível</th>
                <th>Disponibilidade</th>
                <th>Resolvido</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingRows as $row): ?>
                <?php
                  $resolved = !empty($row['resolved_action']);
                  $checkboxDisabled = $resolved ? 'disabled' : '';
                ?>
                <tr>
                  <td>
                    <input type="checkbox" name="pending_ids[]" value="<?php echo $esc((string) $row['id']); ?>" <?php echo $checkboxDisabled; ?>>
                  </td>
                  <td><?php echo $esc((string) ($row['sku'] ?? '')); ?></td>
                  <td><?php echo $esc((string) ($row['product_name'] ?? '')); ?></td>
                  <td><?php echo $esc((string) ($row['quantity'] ?? '0')); ?></td>
                  <td><?php echo $esc($availabilityLabel($row['availability_status'] ?? '')); ?></td>
                  <td>
                    <?php if ($resolved): ?>
                      <?php echo $esc((string) ($row['resolved_action'] ?? '')); ?>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="grid" style="margin-top:12px;">
            <div class="field">
              <label for="bulk_reason">Motivo do ajuste em massa</label>
              <input id="bulk_reason" name="bulk_reason" type="text" maxlength="255" value="<?php echo $esc((string) ($closedInventory['default_reason'] ?? '')); ?>">
            </div>
          </div>
          <div class="footer">
            <button class="primary" type="submit" name="bulk_zero" value="1">Zerar disponibilidade selecionada</button>
          </div>
        </form>
      <?php else: ?>
        <div class="inventory-muted">Nenhum item pendente para este batimento.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<script src="assets/vendor/zxing.min.js"></script>
<script>
  (() => {
    const app = document.getElementById('inventoryApp');
    if (!app) return;

    const inventoryId = app.dataset.inventoryId;
    const blindCount = app.dataset.blindCount === '1';
    const defaultReason = app.dataset.defaultReason || '';

    const scanForm = document.getElementById('scanForm');
    const scanSku = document.getElementById('scanSku');
    const scanQty = document.getElementById('scanQty');
    const duplicateAlert = document.getElementById('duplicateAlert');
    const duplicateMeta = document.getElementById('duplicateMeta');
    const cameraStart = document.getElementById('cameraStart');
    const cameraStop = document.getElementById('cameraStop');
    const cameraWrap = document.getElementById('cameraWrap');
    const cameraVideo = document.getElementById('cameraVideo');
    const cameraStatus = document.getElementById('cameraStatus');
    const cameraSelect = document.getElementById('cameraSelect');

    const productPanel = document.getElementById('productPanel');
    const productImage = document.getElementById('productImage');
    const productName = document.getElementById('productName');
    const productSku = document.getElementById('productSku');
    const productIdLabel = document.getElementById('productId');
    const productStock = document.getElementById('productStock');
    const stockPill = document.getElementById('stockPill');
    const countedQtyLabel = document.getElementById('countedQty');
    const scanCountLabel = document.getElementById('scanCount');

    const productForm = document.getElementById('productForm');
    const productIdInput = document.getElementById('productIdInput');
    const countedQtyInput = document.getElementById('countedQtyInput');
    const productNameInput = document.getElementById('productNameInput');
    const productPriceInput = document.getElementById('productPriceInput');
    const productStatusInput = document.getElementById('productStatusInput');
    const productDescriptionInput = document.getElementById('productDescriptionInput');
    const productShortDescriptionInput = document.getElementById('productShortDescriptionInput');
    const productWeightInput = document.getElementById('productWeightInput');
    const productReasonInput = document.getElementById('productReasonInput');
    const productFeedback = document.getElementById('productFeedback');
    const productSupplySource = document.getElementById('productSupplySource');
    const productSupplySupplier = document.getElementById('productSupplySupplier');
    const productBrandInput = document.getElementById('productBrandInput');
    const categoryInputs = Array.from(document.querySelectorAll('input[name="category_ids[]"]'));
    const tagInputs = Array.from(document.querySelectorAll('input[name="tag_ids[]"]'));
    const productImagesList = document.getElementById('productImagesList');
    const productImagesEmpty = document.getElementById('productImagesEmpty');
    const productImagesInput = document.getElementById('productImagesInput');
    const newImagesList = document.getElementById('newImagesList');
    const photoCameraStart = document.getElementById('photoCameraStart');
    const photoCameraWrap = document.getElementById('photoCameraWrap');
    const photoCameraVideo = document.getElementById('photoCameraVideo');
    const photoCameraStatus = document.getElementById('photoCameraStatus');
    const photoCameraCapture = document.getElementById('photoCameraCapture');
    const photoCameraStop = document.getElementById('photoCameraStop');
    const clearNewImages = document.getElementById('clearNewImages');

    const summaryUnique = document.getElementById('summaryUnique');
    const summaryTotalCounted = document.getElementById('summaryTotalCounted');
    const summaryTotalScans = document.getElementById('summaryTotalScans');
    const recentScans = document.getElementById('recentScans');
    const scanHistory = document.getElementById('scanHistory');
    const scanHistorySearch = document.getElementById('scanHistorySearch');
    const scanHistoryList = document.getElementById('scanHistoryList');
    const scanHistoryMore = document.getElementById('scanHistoryMore');
    const scanHistoryStatus = document.getElementById('scanHistoryStatus');
    const toastStack = document.getElementById('toastStack');
    const pendingSavesEl = document.getElementById('pendingSaves');

    let currentProduct = null;
    let lastScanSku = '';
    let feedbackTimer = null;
    let pendingSaves = 0;
    const removedImages = new Map();
    const pendingUploads = [];
    let photoStream = null;
    let photoLoading = false;
    let scanHistoryOffset = 0;
    let scanHistoryQuery = '';
    let scanHistoryLoading = false;
    let scanHistoryHasMore = true;
    let scanHistoryTimer = null;

    const imageUploadInfo = <?php echo json_encode($imageUploadInfo, JSON_UNESCAPED_UNICODE); ?>;
    const maxImageFiles = parseInt(imageUploadInfo.maxFiles || '6', 10) || 6;
    const maxImageSizeMb = parseFloat(imageUploadInfo.maxSizeMb || '5') || 5;
    const maxImageSizeLabel = imageUploadInfo.maxSizeLabel || String(maxImageSizeMb);
    const maxImageBytes = Math.round(maxImageSizeMb * 1024 * 1024);
    const allowedImageTypes = String(imageUploadInfo.accept || '')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean);
    const allowedImageTypeSet = new Set(allowedImageTypes);

    function escapeHtml(value) {
      const div = document.createElement('div');
      div.textContent = value || '';
      return div.innerHTML;
    }

    function formatDatetime(value) {
      if (!value) return '-';
      const parts = String(value).split(' ');
      if (parts.length < 2) return value;
      const dateParts = parts[0].split('-');
      if (dateParts.length !== 3) return value;
      return `${dateParts[2]}/${dateParts[1]}/${dateParts[0]} ${parts[1].slice(0, 5)}`;
    }

    function normalizeProductStatus(value) {
      const normalized = String(value || '').toLowerCase().trim();
      const legacyMap = {
        publish: 'disponivel',
        active: 'disponivel',
        pending: 'draft',
        private: 'archived',
        trash: 'archived',
      };
      const mapped = legacyMap[normalized] || normalized;
      const allowed = ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'];
      return allowed.includes(mapped) ? mapped : 'draft';
    }

    function buildThumbnailImage(src, alt, size = 110) {
      const trimmed = String(src || '').trim();
      if (!trimmed) {
        return null;
      }
      const helper = window.RetratoThumbnail;
      if (helper && typeof helper.createElement === 'function') {
        return helper.createElement({ fullSrc: trimmed, size, alt });
      }
      const img = document.createElement('img');
      img.src = trimmed;
      img.alt = alt || '';
      img.loading = 'lazy';
      img.decoding = 'async';
      return img;
    }

    function showFeedback(message, type = 'success') {
      if (!productFeedback) return;
      productFeedback.className = `alert ${type === 'error' ? 'error' : 'success'}`;
      productFeedback.textContent = message;
      productFeedback.style.display = 'block';
      if (feedbackTimer) {
        clearTimeout(feedbackTimer);
      }
      feedbackTimer = setTimeout(() => {
        productFeedback.style.display = 'none';
      }, 2800);
    }

    function showToast(message, type = 'success') {
      if (!toastStack) return;
      const toast = document.createElement('div');
      toast.className = `toast ${type === 'error' ? 'error' : 'success'}`;
      toast.textContent = message;
      toastStack.appendChild(toast);
      setTimeout(() => toast.classList.add('is-hidden'), 2200);
      setTimeout(() => toast.remove(), 2600);
    }

    function updatePendingSaves() {
      if (!pendingSavesEl) return;
      pendingSavesEl.textContent = pendingSaves;
    }

    function updateSummary(summary) {
      if (!summary) return;
      if (summaryUnique) summaryUnique.textContent = summary.unique_items ?? 0;
      if (summaryTotalCounted) summaryTotalCounted.textContent = summary.total_counted ?? 0;
      if (summaryTotalScans) summaryTotalScans.textContent = summary.total_scans ?? 0;
    }

    function focusScanInput() {
      if (!scanSku) return;
      try {
        scanSku.focus({ preventScroll: true });
      } catch (err) {
        scanSku.focus();
      }
      if (typeof scanSku.scrollIntoView === 'function') {
        scanSku.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }

    function renderRecentScans(scans) {
      if (!recentScans) return;
      if (!scans || scans.length === 0) {
        recentScans.innerHTML = '<div class=\"inventory-muted\">Nenhuma leitura registrada ainda.</div>';
        return;
      }
      let html = '<ul style=\"list-style:none;padding:0;margin:0;display:grid;gap:8px;\">';
      scans.forEach((scan) => {
        const scanId = scan.id || '';
        const productId = scan.product_sku || scan.product_id || '';
        const sku = escapeHtml(scan.sku || '');
        const name = escapeHtml(scan.product_name || '');
        const qty = scan.quantity || 1;
        const time = formatDatetime(scan.created_at || '');
        html += `<li class=\"recent-scan-row\">` +
          `<div><strong>${sku}</strong><div class=\"inventory-muted\">${name}</div></div>` +
          `<div class=\"inventory-muted recent-scan-meta\">` +
            `<div>Qtd ${qty}</div><div>${time}</div>` +
            `<div class=\"recent-scan-actions\">` +
              `<button class=\"ghost scan-edit\" type=\"button\" data-scan-action=\"edit\" data-product-id=\"${productId}\">Editar</button>` +
              `<button class=\"ghost scan-delete\" type=\"button\" data-scan-action=\"delete\" data-scan-id=\"${scanId}\">Excluir</button>` +
            `</div>` +
          `</div>` +
          `</li>`;
      });
      html += '</ul>';
      recentScans.innerHTML = html;
    }

    function ensureScanHistoryTable(reset = false) {
      if (!scanHistoryList) return null;
      const existing = scanHistoryList.querySelector('table');
      if (!existing || reset) {
        scanHistoryList.innerHTML = '' +
          '<table class=\"inventory-table\">' +
          '<thead><tr>' +
          '<th>SKU</th><th>Produto</th><th>Qtd</th><th>Ação</th><th>Usuário</th><th>Data</th><th></th>' +
          '</tr></thead>' +
          '<tbody></tbody>' +
          '</table>';
      }
      return scanHistoryList.querySelector('tbody');
    }

    function appendScanHistoryRows(rows, reset = false) {
      if (!scanHistoryList) return;
      if (reset && (!rows || rows.length === 0)) {
        scanHistoryList.innerHTML = '<div class=\"inventory-muted\">Nenhuma leitura encontrada.</div>';
        return;
      }

      const tbody = ensureScanHistoryTable(reset);
      if (!tbody || !rows) return;
      rows.forEach((scan) => {
        const row = document.createElement('tr');
        const sku = escapeHtml(scan.sku || '');
        const name = escapeHtml(scan.product_name || '');
        const qty = scan.quantity || 1;
        const action = escapeHtml(scan.action || '');
        const user = escapeHtml(scan.user_name || '-');
        const time = formatDatetime(scan.created_at || '');
        row.innerHTML = '' +
          `<td>${sku}</td>` +
          `<td>${name}</td>` +
          `<td>${qty}</td>` +
          `<td>${action}</td>` +
          `<td>${user}</td>` +
          `<td>${time}</td>` +
          `<td>` +
            `<div class=\"recent-scan-actions\">` +
              `<button class=\"ghost scan-edit\" type=\"button\" data-scan-action=\"edit\" data-product-id=\"${scan.product_sku || scan.product_id || ''}\">Editar</button>` +
              `<button class=\"ghost scan-delete\" type=\"button\" data-scan-action=\"delete\" data-scan-id=\"${scan.id || ''}\">Excluir</button>` +
            `</div>` +
          `</td>`;
        tbody.appendChild(row);
      });
    }

    function updateScanHistoryStatus() {
      if (!scanHistoryStatus || !scanHistoryMore) return;
      scanHistoryMore.style.display = scanHistoryHasMore ? 'inline-flex' : 'none';
      scanHistoryStatus.textContent = scanHistoryHasMore ? 'Carregue mais para ver outras leituras.' : 'Fim da lista.';
    }

    async function loadScanHistory(reset = false) {
      if (!scanHistoryList || scanHistoryLoading) return;
      if (!scanHistoryHasMore && !reset) return;

      if (reset) {
        scanHistoryOffset = 0;
        scanHistoryHasMore = true;
        scanHistoryList.innerHTML = '';
      }

      scanHistoryLoading = true;
      if (scanHistoryStatus) scanHistoryStatus.textContent = 'Carregando...';

      try {
        const data = await postAction({
          action: 'list_scans',
          limit: 50,
          offset: scanHistoryOffset,
          query: scanHistoryQuery,
        });

        if (!data.ok) {
          showToast(data.message || 'Erro ao carregar leituras.', 'error');
          return;
        }

        const rows = data.scans || [];
        appendScanHistoryRows(rows, reset);
        scanHistoryOffset += rows.length;
        scanHistoryHasMore = !!data.has_more && rows.length > 0;
        updateScanHistoryStatus();
      } catch (err) {
        console.error(err);
        showToast('Falha ao carregar leituras.', 'error');
      } finally {
        scanHistoryLoading = false;
      }
    }

    async function deleteScan(scanId) {
      if (!scanId) return;
      try {
        const data = await postAction({
          action: 'delete_scan',
          scan_id: scanId,
        });
        if (!data.ok) {
          showToast(data.message || 'Erro ao excluir leitura.', 'error');
          return;
        }
        updateSummary(data.summary);
        renderRecentScans(data.recent_scans);
        if (data.product && currentProduct && data.product.id === currentProduct.id) {
          setProductPanel(data.product, data.item || null);
        }
        if (scanHistory && scanHistory.open) {
          await loadScanHistory(true);
        }
        showToast(data.message || 'Leitura removida.');
      } catch (err) {
        console.error(err);
        showToast('Falha ao excluir leitura.', 'error');
      }
    }

    async function loadProductById(productId) {
      if (!productId) return;
      try {
        const data = await postAction({
          action: 'load_product',
          product_id: productId,
        });
        if (!data.ok) {
          showToast(data.message || 'Erro ao carregar produto.', 'error');
          return;
        }
        setProductPanel(data.product, data.item || {});
        showFeedback('Produto carregado para edição.');
      } catch (err) {
        console.error(err);
        showToast('Falha ao carregar produto.', 'error');
      }
    }

    function formatSupplySource(value) {
      const key = (value || '').toLowerCase();
      if (key === 'compra') return 'Compra/Garimpo';
      if (key === 'doacao') return 'Doação';
      if (key === 'consignacao') return 'Consignação';
      return value || '-';
    }

    function setSupplyInfo(supply) {
      if (!productSupplySource || !productSupplySupplier) return;
      const source = supply && supply.source ? supply.source : '';
      productSupplySource.textContent = formatSupplySource(source);
      let supplierLabel = '-';
      if (supply && supply.supplier_name) {
        supplierLabel = supply.supplier_name;
        if (supply.supplier_id) {
          supplierLabel += ` (ID ${supply.supplier_id})`;
        }
      } else if (supply && supply.supplier_id) {
        supplierLabel = `ID ${supply.supplier_id}`;
      }
      productSupplySupplier.textContent = supplierLabel;
    }

    function setBrandSelection(value) {
      if (!productBrandInput) return;
      productBrandInput.value = value ? String(value) : '';
    }

    function setCategorySelections(ids) {
      if (!categoryInputs.length) return;
      const idSet = new Set((ids || []).map((item) => String(item)));
      categoryInputs.forEach((input) => {
        input.checked = idSet.has(input.value);
      });
    }

    function setTagSelections(ids) {
      if (!tagInputs.length) return;
      const idSet = new Set((ids || []).map((item) => String(item)));
      tagInputs.forEach((input) => {
        input.checked = idSet.has(input.value);
      });
    }

    function renderExistingImages(images) {
      if (!productImagesList) return;
      productImagesList.innerHTML = '';
      removedImages.clear();

      if (!images || images.length === 0) {
        if (productImagesEmpty) productImagesEmpty.style.display = 'block';
        return;
      }
      if (productImagesEmpty) productImagesEmpty.style.display = 'none';

      images.forEach((image, index) => {
        const src = image && image.src ? image.src : '';
        if (!src) return;
        const label = image && image.name ? image.name : `Imagem ${index + 1}`;
        const card = document.createElement('div');
        card.className = 'inventory-image-card';
        card.dataset.imageId = image.id || '';
        card.dataset.imageSrc = src;

        const img = buildThumbnailImage(src, label, 110);

        const footer = document.createElement('div');
        footer.className = 'inventory-image-card-footer';

        const title = document.createElement('span');
        title.textContent = label;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ghost';
        button.dataset.imageAction = 'remove';
        button.textContent = 'Remover';

        footer.appendChild(title);
        footer.appendChild(button);
        if (img) {
          card.appendChild(img);
        }
        card.appendChild(footer);
        productImagesList.appendChild(card);
      });
    }

    function renderPendingUploads() {
      if (!newImagesList) return;
      newImagesList.innerHTML = '';
      if (!pendingUploads.length) {
        return;
      }

      pendingUploads.forEach((item) => {
        const card = document.createElement('div');
        card.className = 'inventory-image-card';
        card.dataset.uploadId = item.id;

        const img = document.createElement('img');
        img.src = item.preview;
        img.alt = item.file.name || 'Nova imagem';

        const footer = document.createElement('div');
        footer.className = 'inventory-image-card-footer';

        const title = document.createElement('span');
        title.textContent = item.file.name || 'Nova imagem';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ghost';
        button.dataset.uploadAction = 'remove';
        button.textContent = 'Remover';

        footer.appendChild(title);
        footer.appendChild(button);
        card.appendChild(img);
        card.appendChild(footer);
        newImagesList.appendChild(card);
      });
    }

    function cloneUploadFile(file) {
      if (!file || typeof File === 'undefined' || !(file instanceof File)) {
        return file;
      }
      try {
        return new File([file], file.name || 'imagem', {
          type: file.type || 'application/octet-stream',
          lastModified: file.lastModified || Date.now(),
        });
      } catch (err) {
        return file;
      }
    }

    function addPendingUpload(file) {
      if (!file) return;
      if (maxImageBytes > 0 && file.size && file.size > maxImageBytes) {
        showToast(`A imagem excede ${maxImageSizeLabel} MB.`, 'error');
        return;
      }
      if (file.type && allowedImageTypeSet.size && !allowedImageTypeSet.has(file.type)) {
        showToast('Formato de imagem não permitido.', 'error');
        return;
      }
      if (pendingUploads.length >= maxImageFiles) {
        showToast(`Limite de ${maxImageFiles} novas imagens por envio.`, 'error');
        return;
      }
      const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
      const normalizedFile = cloneUploadFile(file);
      const preview = URL.createObjectURL(normalizedFile);
      pendingUploads.push({ id, file: normalizedFile, preview });
      renderPendingUploads();
    }

    function clearPendingUploads() {
      pendingUploads.forEach((item) => {
        URL.revokeObjectURL(item.preview);
      });
      pendingUploads.length = 0;
      renderPendingUploads();
    }

    function resetImageState(images) {
      clearPendingUploads();
      if (productImagesInput) productImagesInput.value = '';
      renderExistingImages(images || []);
    }

    function ensureVideoPlayback(targetVideo) {
      const video = targetVideo || cameraVideo;
      if (!video) return;
      video.muted = true;
      const attemptPlay = () => {
        const playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(() => {});
        }
      };
      if (video.readyState >= 1) {
        attemptPlay();
      } else {
        video.addEventListener('loadedmetadata', attemptPlay, { once: true });
      }
    }

    async function startPhotoCamera() {
      if (!photoCameraWrap || !photoCameraVideo || !photoCameraStatus) return;
      photoCameraWrap.classList.add('is-active');
      photoCameraStatus.textContent = '';
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        photoCameraStatus.textContent = window.isSecureContext
          ? 'Câmera indisponível no navegador.'
          : 'A câmera precisa de uma conexão segura (HTTPS).';
        return;
      }
      if (!window.isSecureContext) {
        photoCameraStatus.textContent = 'A câmera precisa de uma conexão segura (HTTPS).';
        return;
      }
      if (photoLoading) return;
      photoLoading = true;

      try {
        photoStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' } },
          audio: false,
        });
        photoCameraVideo.srcObject = photoStream;
        ensureVideoPlayback(photoCameraVideo);
        photoCameraStatus.textContent = 'Câmera pronta para foto.';
      } catch (err) {
        photoCameraStatus.textContent = 'Falha ao acessar a câmera.';
      } finally {
        photoLoading = false;
      }
    }

    function stopPhotoCamera() {
      if (photoStream) {
        photoStream.getTracks().forEach((track) => track.stop());
        photoStream = null;
      }
      if (photoCameraVideo && photoCameraVideo.srcObject) {
        const tracks = photoCameraVideo.srcObject.getTracks ? photoCameraVideo.srcObject.getTracks() : [];
        tracks.forEach((track) => track.stop());
      }
      if (photoCameraVideo) {
        photoCameraVideo.srcObject = null;
      }
      if (photoCameraWrap) {
        photoCameraWrap.classList.remove('is-active');
      }
    }

    function capturePhoto() {
      if (!photoCameraVideo) return;
      const width = photoCameraVideo.videoWidth;
      const height = photoCameraVideo.videoHeight;
      if (!width || !height) {
        if (photoCameraStatus) photoCameraStatus.textContent = 'Aguarde o vídeo iniciar.';
        return;
      }
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const context = canvas.getContext('2d');
      if (!context) return;
      context.drawImage(photoCameraVideo, 0, 0, width, height);
      canvas.toBlob((blob) => {
        if (!blob) return;
        const fileName = `batimento_${Date.now()}.jpg`;
        const file = new File([blob], fileName, { type: 'image/jpeg' });
        addPendingUpload(file);
      }, 'image/jpeg', 0.92);
    }

    function setDuplicateState(payload) {
      if (!payload || !payload.duplicate) {
        duplicateAlert.style.display = 'none';
        duplicateMeta.textContent = '';
        return;
      }

      const item = payload.item || {};
      const userLabel = item.last_user_name ? `Por ${item.last_user_name}` : 'Leitura anterior';
      const timeLabel = item.last_scan_at ? `em ${item.last_scan_at}` : '';
      duplicateMeta.textContent = `${userLabel} ${timeLabel}.`;
      duplicateAlert.style.display = 'block';
      lastScanSku = payload.product ? payload.product.sku : lastScanSku;
    }

    function setProductPanel(product, item) {
      if (!product) return;
      currentProduct = product;
      productPanel.style.display = 'block';
      productName.textContent = product.name || 'Produto sem nome';
      productSku.textContent = product.sku || '-';
      productIdLabel.textContent = product.id || '-';

      const image = product.image && product.image.src ? product.image.src : '';
      if (image) {
        const thumb = buildThumbnailImage(image, product.name || 'Produto', 120);
        productImage.innerHTML = '';
        if (thumb) {
          productImage.appendChild(thumb);
        } else {
          productImage.innerHTML = '<span class="inventory-muted">Sem imagem</span>';
        }
      } else {
        productImage.innerHTML = '<span class="inventory-muted">Sem imagem</span>';
      }

      if (blindCount) {
        stockPill.style.display = 'none';
      } else {
        stockPill.style.display = 'inline-flex';
        const availableQty = product.quantity ?? null;
        productStock.textContent = availableQty !== null ? availableQty : '-';
      }

      const counted = item && typeof item.counted_quantity !== 'undefined' ? item.counted_quantity : 0;
      const scans = item && typeof item.scan_count !== 'undefined' ? item.scan_count : 0;
      countedQtyLabel.textContent = counted;
      scanCountLabel.textContent = scans;

      productIdInput.value = product.id || '';
      countedQtyInput.value = counted;
      productNameInput.value = product.name || '';
      productPriceInput.value = product.price || '';
      productStatusInput.value = normalizeProductStatus(product.status);
      productDescriptionInput.value = product.description || '';
      productShortDescriptionInput.value = product.short_description || '';
      productWeightInput.value = product.weight || '';
      if (window.RetratoNumber && typeof window.RetratoNumber.formatInput === 'function') {
        window.RetratoNumber.formatInput(productPriceInput);
        window.RetratoNumber.formatInput(productWeightInput);
      }
      productReasonInput.value = defaultReason;
      setSupplyInfo(product.supply || {});
      setBrandSelection(product.brand || '');
      setCategorySelections(product.category_ids || []);
      setTagSelections(product.tag_ids || []);
      resetImageState(product.images || []);
      stopPhotoCamera();
    }

    function resetProductPanel() {
      currentProduct = null;
      if (productPanel) productPanel.style.display = 'none';
      if (productName) productName.textContent = '';
      if (productSku) productSku.textContent = '';
      if (productIdLabel) productIdLabel.textContent = '';
      if (productImage) productImage.innerHTML = '';
      if (stockPill) stockPill.style.display = blindCount ? 'none' : 'inline-flex';
      if (productStock) productStock.textContent = '';
      if (countedQtyLabel) countedQtyLabel.textContent = '0';
      if (scanCountLabel) scanCountLabel.textContent = '0';
      if (productIdInput) productIdInput.value = '';
      if (countedQtyInput) countedQtyInput.value = 0;
      if (productNameInput) productNameInput.value = '';
      if (productPriceInput) productPriceInput.value = '';
      if (productStatusInput) productStatusInput.value = 'draft';
      if (productDescriptionInput) productDescriptionInput.value = '';
      if (productShortDescriptionInput) productShortDescriptionInput.value = '';
      if (productWeightInput) productWeightInput.value = '';
      if (productReasonInput) productReasonInput.value = defaultReason;
      setSupplyInfo({});
      setBrandSelection('');
      setCategorySelections([]);
      setTagSelections([]);
      resetImageState([]);
      if (productFeedback) productFeedback.style.display = 'none';
      setDuplicateState(null);
      lastScanSku = '';
      stopPhotoCamera();
    }

    async function postAction(payload) {
      const formData = payload instanceof FormData ? payload : new FormData();
      if (!(payload instanceof FormData)) {
        Object.keys(payload).forEach((key) => {
          if (payload[key] !== undefined && payload[key] !== null) {
            formData.append(key, payload[key]);
          }
        });
      }

      const response = await fetch('disponibilidade-conferencia.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' },
      });

      return response.json();
    }

    async function handleScan(mode = '') {
      const sku = scanSku.value.trim();
      if (!sku) return;
      const quantity = parseInt(scanQty.value || '1', 10) || 1;

      try {
        const data = await postAction({
          action: 'scan',
          sku,
          quantity,
          mode,
        });

        if (!data.ok) {
          setDuplicateState(null);
          showToast(data.message || 'Erro ao ler SKU.', 'error');
          return;
        }

        setProductPanel(data.product, data.item || {});
        setDuplicateState(data);
        if (data.duplicate) {
          showFeedback(data.message || 'SKU já lido.', 'error');
          showToast(data.message || 'SKU já lido.', 'error');
          return;
        }
        updateSummary(data.summary);
        renderRecentScans(data.recent_scans);
        if (data.availability_error) {
          showFeedback(`Leitura registrada, mas a disponibilidade não foi atualizada: ${data.availability_error}`, 'error');
          showToast('Leitura registrada com alerta de disponibilidade.', 'error');
        } else {
          showFeedback(data.message || 'Leitura registrada.');
          showToast(data.message || 'Leitura registrada.');
        }
        scanSku.value = '';
        scanSku.focus();
      } catch (err) {
        console.error(err);
        showToast('Falha ao ler SKU.', 'error');
      }
    }

    scanForm.addEventListener('submit', (event) => {
      event.preventDefault();
      handleScan('');
    });

    duplicateAlert.addEventListener('click', (event) => {
      const button = event.target.closest('[data-dup-action]');
      if (!button) return;
      const action = button.dataset.dupAction;
      if (!lastScanSku) {
        lastScanSku = scanSku.value.trim();
      }
      if (lastScanSku) {
        scanSku.value = lastScanSku;
        handleScan(action);
      }
    });

    productForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!currentProduct) return;
      const submitButton = productForm.querySelector('button[type=\"submit\"]');
      const originalLabel = submitButton ? submitButton.textContent : '';
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Salvando...';
      }
      pendingSaves += 1;
      updatePendingSaves();

      const payload = new FormData();
      payload.append('action', 'update_product');
      payload.append('product_id', productIdInput.value);
      payload.append('counted_quantity', countedQtyInput.value);
      payload.append('name', productNameInput.value);
      payload.append('price', productPriceInput.value);
      payload.append('status', productStatusInput.value);
      payload.append('description', productDescriptionInput.value);
      payload.append('short_description', productShortDescriptionInput.value);
      payload.append('weight', productWeightInput.value);
      payload.append('reason', productReasonInput.value);

      if (productBrandInput && !productBrandInput.disabled) {
        payload.append('brand', productBrandInput.value || '');
      }

      if (categoryInputs.length) {
        payload.append('category_ids_present', '1');
        categoryInputs.forEach((input) => {
          if (input.checked) {
            payload.append('category_ids[]', input.value);
          }
        });
      }

      if (tagInputs.length) {
        payload.append('tag_ids_present', '1');
        tagInputs.forEach((input) => {
          if (input.checked) {
            payload.append('tag_ids[]', input.value);
          }
        });
      }

      if (removedImages.size) {
        removedImages.forEach((value) => {
          if (value.id) {
            payload.append('remove_image_ids[]', value.id);
          } else if (value.src) {
            payload.append('remove_image_srcs[]', value.src);
          }
        });
      }

      try {
        if (pendingUploads.length) {
          pendingUploads.forEach((item) => {
            payload.append('product_images[]', item.file, item.file.name);
          });
        }

        const data = await postAction(payload);
        if (!data.ok) {
          showFeedback(data.message || 'Erro ao atualizar produto.', 'error');
          showToast(data.message || 'Erro ao atualizar produto.', 'error');
          return;
        }
        setProductPanel(data.product, {
          counted_quantity: parseInt(countedQtyInput.value || '0', 10),
          scan_count: parseInt(scanCountLabel.textContent || '0', 10),
        });
        updateSummary(data.summary);
        renderRecentScans(data.recent_scans);
        showFeedback(data.message || 'Ajustes salvos.');
        showToast(data.message || 'Ajustes salvos.');
        clearPendingUploads();
        resetProductPanel();
        scanSku.value = '';
        focusScanInput();
      } catch (err) {
        console.error(err);
        showFeedback('Falha ao salvar ajustes.', 'error');
        showToast('Falha ao salvar ajustes.', 'error');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }
        pendingSaves = Math.max(0, pendingSaves - 1);
        updatePendingSaves();
      }
    });

    function supportsBarcodeDetector() {
      return 'BarcodeDetector' in window;
    }

    function supportsZXing() {
      return typeof ZXing !== 'undefined' && ZXing.BrowserMultiFormatReader;
    }

    let cameraStream = null;
    let cameraActive = false;
    let detector = null;
    let zxingReader = null;
    let zxingActive = false;
    let cameraMode = '';
    let cameraDevices = [];
    let selectedDeviceId = '';
    let isCameraLoading = false;

    async function startCamera() {
      cameraWrap.classList.add('is-active');
      cameraStatus.textContent = '';
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        cameraStatus.textContent = window.isSecureContext
          ? 'Câmera indisponível no navegador.'
          : 'A câmera precisa de uma conexão segura (HTTPS).';
        return;
      }
      if (!window.isSecureContext) {
        cameraStatus.textContent = 'A câmera precisa de uma conexão segura (HTTPS).';
        return;
      }

      if (isCameraLoading) {
        return;
      }
      isCameraLoading = true;

      if (supportsBarcodeDetector()) {
        cameraMode = 'native';
        const started = await startNativeDetector();
        if (started) {
          ensureCameraDevices();
          isCameraLoading = false;
          return;
        }
      }

      if (supportsZXing()) {
        cameraMode = 'zxing';
        await startZXingDetector();
        ensureCameraDevices();
        isCameraLoading = false;
        return;
      }

      cameraStatus.textContent = 'Leitor de código não disponível. Atualize o navegador ou use a digitação.';
      isCameraLoading = false;
    }

    async function startNativeDetector() {
      try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
          video: buildVideoConstraints(),
          audio: false,
        });
        cameraVideo.srcObject = cameraStream;
        ensureVideoPlayback(cameraVideo);
        cameraActive = true;
        detector = detector || new BarcodeDetector({
          formats: ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'],
        });
        cameraStatus.textContent = 'Aguardando leitura...';
        requestAnimationFrame(scanCamera);
        return true;
      } catch (err) {
        cameraStatus.textContent = 'Falha ao acessar a câmera.';
        return false;
      }
    }

    async function startZXingDetector() {
      try {
        zxingReader = zxingReader || new ZXing.BrowserMultiFormatReader();
        zxingActive = true;
        cameraStatus.textContent = 'Aguardando leitura...';
        ensureVideoPlayback(cameraVideo);
        await zxingReader.decodeFromConstraints(
          { video: buildVideoConstraints() },
          cameraVideo,
          (result) => {
            if (!zxingActive) return;
            if (result) {
              const value = typeof result.getText === 'function' ? result.getText() : (result.text || '');
              if (value) {
                scanSku.value = value;
                stopCamera();
                handleScan('');
              }
            }
          }
        );
      } catch (err) {
        cameraStatus.textContent = 'Falha ao acessar a câmera.';
      }
    }

    function stopCamera() {
      cameraActive = false;
      zxingActive = false;
      if (zxingReader) {
        zxingReader.reset();
      }
      if (cameraStream) {
        cameraStream.getTracks().forEach((track) => track.stop());
        cameraStream = null;
      }
      if (cameraVideo && cameraVideo.srcObject) {
        const tracks = cameraVideo.srcObject.getTracks ? cameraVideo.srcObject.getTracks() : [];
        tracks.forEach((track) => track.stop());
      }
      cameraVideo.srcObject = null;
      cameraWrap.classList.remove('is-active');
      cameraMode = '';
    }

    async function scanCamera() {
      if (!cameraActive || !detector || cameraMode !== 'native') return;
      try {
        const barcodes = await detector.detect(cameraVideo);
        if (barcodes && barcodes.length) {
          const value = barcodes[0].rawValue || '';
          if (value) {
            scanSku.value = value;
            stopCamera();
            handleScan('');
            return;
          }
        }
      } catch (err) {
        cameraStatus.textContent = 'Erro ao ler código.';
      }
      requestAnimationFrame(scanCamera);
    }

    function buildVideoConstraints() {
      if (selectedDeviceId) {
        return { deviceId: { exact: selectedDeviceId } };
      }
      return { facingMode: { ideal: 'environment' } };
    }

    async function ensureCameraDevices() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
        cameraStatus.textContent = 'Não foi possível listar as câmeras.';
        return;
      }

      if (!cameraDevices.length && !(cameraStream || (cameraVideo && cameraVideo.srcObject))) {
        try {
          const tempStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
          tempStream.getTracks().forEach((track) => track.stop());
        } catch (err) {
          // ignora, sem permissao inicial
        }
      }

      const devices = await navigator.mediaDevices.enumerateDevices();
      cameraDevices = devices.filter((device) => device.kind === 'videoinput');
      populateCameraSelect();
    }

    function populateCameraSelect() {
      if (!cameraSelect) return;
      cameraSelect.innerHTML = '';
      if (!cameraDevices.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Nenhuma câmera encontrada';
        cameraSelect.appendChild(option);
        cameraSelect.disabled = true;
        return;
      }

      cameraSelect.disabled = false;
      cameraDevices.forEach((device, index) => {
        const option = document.createElement('option');
        option.value = device.deviceId;
        const label = device.label || `Câmera ${index + 1}`;
        option.textContent = label;
        cameraSelect.appendChild(option);
      });

      if (!selectedDeviceId || !cameraDevices.some((device) => device.deviceId === selectedDeviceId)) {
        selectedDeviceId = cameraDevices[0].deviceId;
      }
      cameraSelect.value = selectedDeviceId;
    }

    async function switchCamera(deviceId) {
      if (!deviceId || deviceId === selectedDeviceId) {
        return;
      }
      selectedDeviceId = deviceId;
      stopCamera();
      await startCamera();
    }

    if (cameraSelect) {
      cameraSelect.addEventListener('change', async (event) => {
        await switchCamera(event.target.value);
      });
    }

    cameraStart.addEventListener('click', startCamera);
    cameraStop.addEventListener('click', stopCamera);

    if (productImagesList) {
      productImagesList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-image-action]');
        if (!button) return;
        const card = button.closest('.inventory-image-card');
        if (!card) return;
        const imageId = parseInt(card.dataset.imageId || '0', 10);
        const imageSrc = card.dataset.imageSrc || '';
        if (!imageId && !imageSrc) return;
        const key = imageId ? `id:${imageId}` : `src:${imageSrc}`;
        if (card.classList.contains('is-removed')) {
          card.classList.remove('is-removed');
          button.textContent = 'Remover';
          removedImages.delete(key);
        } else {
          card.classList.add('is-removed');
          button.textContent = 'Desfazer';
          removedImages.set(key, { id: imageId, src: imageSrc });
        }
      });
    }

    if (newImagesList) {
      newImagesList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-upload-action]');
        if (!button) return;
        const card = button.closest('.inventory-image-card');
        if (!card) return;
        const uploadId = card.dataset.uploadId;
        const index = pendingUploads.findIndex((item) => item.id === uploadId);
        if (index >= 0) {
          const [removed] = pendingUploads.splice(index, 1);
          URL.revokeObjectURL(removed.preview);
          renderPendingUploads();
        }
      });
    }

    if (productImagesInput) {
      productImagesInput.addEventListener('change', () => {
        const files = Array.from(productImagesInput.files || []);
        files.forEach((file) => addPendingUpload(file));
        productImagesInput.value = '';
      });
    }

    if (photoCameraStart) {
      photoCameraStart.addEventListener('click', startPhotoCamera);
    }
    if (photoCameraStop) {
      photoCameraStop.addEventListener('click', stopPhotoCamera);
    }
    if (photoCameraCapture) {
      photoCameraCapture.addEventListener('click', capturePhoto);
    }
    if (clearNewImages) {
      clearNewImages.addEventListener('click', clearPendingUploads);
    }

    if (recentScans) {
      recentScans.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-scan-action]');
        if (!button) return;
        const action = button.dataset.scanAction;
        if (action === 'delete') {
          const scanId = button.dataset.scanId;
          if (!scanId) return;
          if (!confirm('Excluir esta leitura?')) return;
          await deleteScan(scanId);
          return;
        }
        if (action === 'edit') {
          const productId = button.dataset.productId;
          if (!productId) return;
          await loadProductById(productId);
        }
      });
    }

    if (scanHistory) {
      scanHistory.addEventListener('toggle', () => {
        if (scanHistory.open && scanHistoryOffset === 0) {
          loadScanHistory(true);
        }
      });
    }

    if (scanHistorySearch) {
      scanHistorySearch.addEventListener('input', () => {
        if (scanHistoryTimer) {
          clearTimeout(scanHistoryTimer);
        }
        scanHistoryQuery = scanHistorySearch.value.trim();
        scanHistoryTimer = setTimeout(() => {
          loadScanHistory(true);
        }, 300);
      });
    }

    if (scanHistoryMore) {
      scanHistoryMore.addEventListener('click', () => {
        loadScanHistory(false);
      });
    }

    if (scanHistoryList) {
      scanHistoryList.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-scan-action]');
        if (!button) return;
        const action = button.dataset.scanAction;
        if (action === 'delete') {
          const scanId = button.dataset.scanId;
          if (!scanId) return;
          if (!confirm('Excluir esta leitura?')) return;
          await deleteScan(scanId);
          return;
        }
        if (action === 'edit') {
          const productId = button.dataset.productId;
          if (!productId) return;
          await loadProductById(productId);
        }
      });
    }

    const pendingSelectAll = document.getElementById('pendingSelectAll');
    if (pendingSelectAll) {
      pendingSelectAll.addEventListener('change', () => {
        const checkboxes = document.querySelectorAll('input[name="pending_ids[]"]:not(:disabled)');
        checkboxes.forEach((checkbox) => {
          checkbox.checked = pendingSelectAll.checked;
        });
      });
    }
  })();
</script>
