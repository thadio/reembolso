<?php
/** @var array $formData */
/** @var array $errors */
/** @var string $success */
/** @var bool $editing */
/** @var array $items */
/** @var array $vendors */
/** @var array $categoryOptions */
/** @var array $returns */
/** @var array $returnForm */
/** @var array $returnErrors */
/** @var string $returnSuccess */
/** @var array $categoryQuantities */
/** @var array $returnQuantities */
/** @var array $returnAvailable */
/** @var int|null $batchVendorId */
/** @var array $linkedProducts */
/** @var array $totals */
/** @var callable $esc */
?>
<?php
  $formData = is_array($formData ?? null)
    ? $formData
    : (is_array($consignment ?? null) ? $consignment : []);
  $formData += [
    'id' => '',
    'received_at' => date('Y-m-d'),
    'pessoa_id' => '',
    'notes' => '',
  ];
  $errors = is_array($errors ?? null) ? $errors : [];
  $success = (string) ($success ?? '');
  $editing = (bool) ($editing ?? ($isEdit ?? !empty($formData['id'])));
  $items = is_array($items ?? null) ? $items : (is_array($inventoryItems ?? null) ? $inventoryItems : []);
  $vendors = is_array($vendors ?? null)
    ? $vendors
    : (is_array($suppliers ?? null) ? $suppliers : []);
  foreach ($vendors as &$vendor) {
    if (!is_array($vendor)) {
      $vendor = [];
      continue;
    }
    if (empty($vendor['full_name']) && !empty($vendor['nome'])) {
      $vendor['full_name'] = (string) $vendor['nome'];
    }
  }
  unset($vendor);
  $categoryOptions = is_array($categoryOptions ?? null) ? $categoryOptions : [];
  $returns = is_array($returns ?? null) ? $returns : [];
  $returnForm = is_array($returnForm ?? null) ? $returnForm : [];
  $returnErrors = is_array($returnErrors ?? null) ? $returnErrors : [];
  $returnSuccess = (string) ($returnSuccess ?? '');
  $categoryQuantities = is_array($categoryQuantities ?? null) ? $categoryQuantities : [];
  $returnQuantities = is_array($returnQuantities ?? null) ? $returnQuantities : [];
  $returnAvailable = is_array($returnAvailable ?? null) ? $returnAvailable : [];
  $batchVendorId = isset($batchVendorId) ? (int) $batchVendorId : null;
  $linkedProducts = is_array($linkedProducts ?? null) ? $linkedProducts : [];
  $totals = is_array($totals ?? null) ? $totals : ['received' => 0, 'returned' => 0, 'remaining' => 0];
  $esc = is_callable($esc ?? null)
    ? $esc
    : static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

  $renderOptions = function ($selectedId) use ($categoryOptions, $esc): string {
    $options = '<option value="">Selecione uma categoria</option>';
    foreach ($categoryOptions as $id => $name) {
      $selected = ((int) $selectedId === (int) $id) ? 'selected' : '';
      $options .= '<option value="' . (int) $id . '" ' . $selected . '>' . $esc($name) . '</option>';
    }
    return $options;
  };

  $formatDate = function (?string $value): string {
    if (!$value) {
      return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
      return $value;
    }
    return date('d/m/Y', $ts);
  };

  $formatDateTime = function (?string $value): string {
    if (!$value) {
      return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
      return $value;
    }
    return date('d/m/Y H:i', $ts);
  };
?>
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <div>
      <h1>Recebimento de consignação</h1>
      <div class="subtitle">Registre o pré-lote com fornecedor, data e quantidade por categoria.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <?php if ($editing && !empty($formData['id'])): ?>
        <a class="btn ghost" href="consignacao-recebimento-termo.php?id=<?php echo (int) $formData['id']; ?>" target="_blank" rel="noopener">Termo de recebimento</a>
        <?php if (!empty($batchVendorId)): ?>
          <a class="btn ghost" href="lote-produtos.php?source=consignacao&vendor=<?php echo (int) $batchVendorId; ?>&consignment=<?php echo (int) $formData['id']; ?>">Abrir lote de produtos</a>
        <?php endif; ?>
      <?php endif; ?>
      <a class="btn ghost" href="consignacao-recebimento-list.php">Voltar</a>
      <span class="pill">
        <?php echo $editing ? 'Pré-lote #' . $esc((string) $formData['id']) : 'Novo pré-lote'; ?>
      </span>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><?php echo $esc($success); ?></div>
  <?php elseif (!empty($errors)): ?>
    <div class="alert error"><?php echo $esc(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="consignacao-recebimento-cadastro.php" id="intakeForm">
    <input type="hidden" name="id" value="<?php echo $esc((string) $formData['id']); ?>">
    <input type="hidden" name="action" value="save">

    <div class="grid">
      <div class="field">
        <label for="received_at">Data de recebimento *</label>
        <input type="date" id="received_at" name="received_at" required value="<?php echo $esc((string) $formData['received_at']); ?>">
      </div>
      <div class="field">
        <label for="pessoa_id">Fornecedor *</label>
        <?php if (empty($vendors)): ?>
          <input id="pessoa_id" name="pessoa_id" type="text" required placeholder="Cadastre um fornecedor primeiro" value="">
        <?php else: ?>
          <input id="pessoa_id" name="pessoa_id" type="text" list="vendorOptions" required placeholder="Digite para buscar" value="<?php echo $esc((string) ($formData['pessoa_id'] ?? '')); ?>">
          <datalist id="vendorOptions">
            <?php foreach ($vendors as $vendor): ?>
              <?php
                $pessoaId = (int) ($vendor['id'] ?? 0);
                $vendorCode = (int) ($vendor['id_vendor'] ?? 0);
                $labelParts = ($vendor['full_name'] ?? ('Fornecedor #' . $pessoaId));
                $codeLabel = $vendorCode > 0 ? (' — Código ' . $vendorCode) : '';
              ?>
              <option value="<?php echo $pessoaId; ?>" label="<?php echo $esc($labelParts . ' — Pessoa ' . $pessoaId . $codeLabel); ?>">
                <?php echo $esc($labelParts . ' — Pessoa ' . $pessoaId . $codeLabel); ?>
              </option>
            <?php endforeach; ?>
          </datalist>
        <?php endif; ?>
      </div>
      <div class="field" style="grid-column:1 / -1;">
        <label for="notes">Observações</label>
        <textarea id="notes" name="notes" rows="3" maxlength="500" placeholder="Ex.: itens delicados, observações do fornecedor."><?php echo $esc((string) $formData['notes']); ?></textarea>
      </div>
    </div>
  </form>

  <?php if ($editing): ?>
    <div class="card" style="margin-top:16px;padding:16px;display:flex;gap:16px;flex-wrap:wrap;">
      <div>
        <div class="subtitle">Qtd recebida</div>
        <strong><?php echo number_format((int) ($totals['received'] ?? 0), 0, ',', '.'); ?></strong>
      </div>
      <div>
        <div class="subtitle">Qtd devolvida</div>
        <strong><?php echo number_format((int) ($totals['returned'] ?? 0), 0, ',', '.'); ?></strong>
      </div>
      <div>
        <div class="subtitle">Saldo</div>
        <strong><?php echo number_format((int) ($totals['remaining'] ?? 0), 0, ',', '.'); ?></strong>
      </div>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:16px;padding:16px;">
    <div>
      <h2 style="margin:0;font-size:16px;">Recebimento e devoluções por categoria</h2>
      <div class="help-text">Preencha somente as categorias recebidas neste pré-lote e registre devoluções quando houver.</div>
    </div>
    <?php if (empty($categoryOptions)): ?>
      <div class="alert error" style="margin-top:12px;">Nenhuma categoria disponível para seleção.</div>
    <?php else: ?>
      <div style="margin-top:12px;overflow:auto;">
        <table class="table-compact">
          <thead>
            <tr>
              <th>Categoria</th>
              <th style="width:160px;background:#fff7ed;">Recebimento</th>
              <th style="width:160px;">Devoluções</th>
              <th style="width:140px;">Saldo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categoryOptions as $categoryId => $categoryName): ?>
              <?php $available = $editing ? (int) ($returnAvailable[$categoryId] ?? 0) : 0; ?>
              <tr>
                <td><?php echo $esc($categoryName); ?></td>
                <td style="background:#fff7ed;">
                  <input type="hidden" name="category_id[]" form="intakeForm" value="<?php echo (int) $categoryId; ?>">
                  <input
                    type="text"
                    inputmode="decimal"
                    data-number-br
                    name="category_qty[]"
                    form="intakeForm"
                    min="1"
                    step="0.01"
                    value="<?php echo $esc((string) ($categoryQuantities[$categoryId] ?? '')); ?>"
                    placeholder="0"
                    style="max-width:120px;font-weight:600;"
                  >
                </td>
                <td>
                  <?php if ($editing): ?>
                    <input type="hidden" name="return_category_id[]" form="returnForm" value="<?php echo (int) $categoryId; ?>">
                    <?php if ($available > 0): ?>
                      <input
                        type="text"
                        inputmode="decimal"
                        data-number-br
                        name="return_category_qty[]"
                        form="returnForm"
                        min="1"
                        step="0.01"
                        value="<?php echo $esc((string) ($returnQuantities[$categoryId] ?? '')); ?>"
                        max="<?php echo $available; ?>"
                        placeholder="0"
                        style="max-width:120px;color:#b91c1c;"
                      >
                    <?php else: ?>
                      <input type="hidden" name="return_category_qty[]" form="returnForm" value="">
                      <input
                        type="number"
                        value=""
                        placeholder="-"
                        disabled
                        style="max-width:120px;color:#b91c1c;opacity:0.6;"
                      >
                    <?php endif; ?>
                  <?php else: ?>
                    <span>-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($editing): ?>
                    <?php echo number_format($available, 0, ',', '.'); ?>
                  <?php else: ?>
                    <span>-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="footer">
    <button class="ghost" type="reset" form="intakeForm">Limpar</button>
    <button class="primary" type="submit" form="intakeForm">Salvar recebimento</button>
  </div>

  <?php if ($editing && !empty($formData['id'])): ?>
    <div class="card" style="margin-top:24px;padding:16px;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
          <h2 style="margin:0;font-size:16px;">Devoluções</h2>
          <div class="help-text">Registre aditamentos quando houver devolução de produtos.</div>
        </div>
      </div>

      <?php if ($returnSuccess): ?>
        <div class="alert success" style="margin-top:12px;">
          <?php echo $esc($returnSuccess); ?>
        </div>
      <?php elseif (!empty($returnErrors)): ?>
        <div class="alert error" style="margin-top:12px;">
          <?php echo $esc(implode(' ', $returnErrors)); ?>
        </div>
      <?php endif; ?>

      <div style="margin-top:12px;overflow:auto;">
        <table class="table-compact" data-table="interactive">
          <thead>
            <tr>
              <th data-sort-key="id" aria-sort="none">ID</th>
              <th data-sort-key="returned_at" aria-sort="none">Data</th>
              <th data-sort-key="total_returned" aria-sort="none">Qtd devolvida</th>
              <th data-sort-key="notes" aria-sort="none">Observações</th>
              <th class="col-actions">Ações</th>
            </tr>
            <tr class="filters-row">
              <th><input type="search" data-filter-col="id" placeholder="#" aria-label="Filtrar devolução"></th>
              <th><input type="search" data-filter-col="returned_at" placeholder="Data" aria-label="Filtrar data"></th>
              <th><input type="search" data-filter-col="total_returned" placeholder="Qtd" aria-label="Filtrar quantidade"></th>
              <th><input type="search" data-filter-col="notes" placeholder="Observações" aria-label="Filtrar observações"></th>
              <th class="col-actions"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($returns)): ?>
              <tr class="no-results"><td colspan="5">Nenhuma devolução registrada.</td></tr>
            <?php else: ?>
              <?php foreach ($returns as $return): ?>
                <?php
                  $notes = (string) ($return['notes'] ?? '');
                  $notesDisplay = $notes;
                  if (strlen($notes) > 80) {
                    $notesDisplay = substr($notes, 0, 77) . '...';
                  }
                ?>
                <tr>
                  <td data-value="<?php echo (int) $return['id']; ?>">#<?php echo (int) $return['id']; ?></td>
                  <td data-value="<?php echo $esc((string) $return['returned_at']); ?>"><?php echo $esc($formatDate($return['returned_at'] ?? null)); ?></td>
                  <td data-value="<?php echo (int) ($return['total_returned'] ?? 0); ?>"><?php echo number_format((int) ($return['total_returned'] ?? 0), 0, ',', '.'); ?></td>
                  <td data-value="<?php echo $esc($notes); ?>"><?php echo $esc($notesDisplay); ?></td>
                  <td class="col-actions">
                    <div class="actions">
                      <a class="icon-btn neutral" href="consignacao-devolucao-termo.php?id=<?php echo (int) $return['id']; ?>" target="_blank" rel="noopener" aria-label="Termo de devolução" title="Termo de devolução">
                        <svg aria-hidden="true"><use href="#icon-file"></use></svg>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <form method="post" action="consignacao-recebimento-cadastro.php?id=<?php echo (int) $formData['id']; ?>" style="margin-top:16px;" id="returnForm">
        <input type="hidden" name="id" value="<?php echo (int) $formData['id']; ?>">
        <input type="hidden" name="action" value="add_return">

        <div class="grid">
          <div class="field">
            <label for="return_date">Data da devolução *</label>
            <input type="date" id="return_date" name="return_date" required value="<?php echo $esc((string) ($returnForm['return_date'] ?? date('Y-m-d'))); ?>">
          </div>
          <div class="field" style="grid-column:1 / -1;">
            <label for="return_notes">Observações</label>
            <textarea id="return_notes" name="return_notes" rows="2" maxlength="500" placeholder="Motivo da devolução."><?php echo $esc((string) ($returnForm['return_notes'] ?? '')); ?></textarea>
          </div>
        </div>

        <div class="help-text" style="margin-top:12px;">Use a tabela acima para informar as quantidades devolvidas.</div>

        <div class="footer" style="margin-top:12px;">
          <button class="primary" type="submit">Registrar devolução</button>
        </div>
      </form>
    </div>

    <div class="card" style="margin-top:16px;padding:16px;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
          <h2 style="margin:0;font-size:16px;">Produtos cadastrados via lote</h2>
          <div class="help-text">Histórico dos produtos vinculados a este pré-lote.</div>
        </div>
        <?php if (!empty($batchVendorId)): ?>
          <a class="btn ghost" href="lote-produtos.php?source=consignacao&vendor=<?php echo (int) $batchVendorId; ?>&consignment=<?php echo (int) $formData['id']; ?>">Cadastrar novos produtos</a>
        <?php endif; ?>
      </div>

      <?php if (empty($linkedProducts)): ?>
        <div class="alert" style="margin-top:12px;">Nenhum produto cadastrado via lote neste pré-lote.</div>
      <?php else: ?>
        <div style="margin-top:12px;overflow:auto;">
          <table class="table-compact" data-table="interactive">
            <thead>
              <tr>
                <th data-sort-key="sku" aria-sort="none">SKU</th>
                <th data-sort-key="name" aria-sort="none">Nome</th>
                <th data-sort-key="status" aria-sort="none">Status</th>
                <th data-sort-key="price" aria-sort="none">Preço</th>
                <th data-sort-key="created_at" aria-sort="none">Criado em</th>
              </tr>
              <tr class="filters-row">
                <th><input type="search" data-filter-col="sku" placeholder="SKU" aria-label="Filtrar SKU"></th>
                <th><input type="search" data-filter-col="name" placeholder="Nome" aria-label="Filtrar nome"></th>
                <th><input type="search" data-filter-col="status" placeholder="Status" aria-label="Filtrar status"></th>
                <th><input type="search" data-filter-col="price" placeholder="Preço" aria-label="Filtrar preço"></th>
                <th><input type="search" data-filter-col="created_at" placeholder="Data" aria-label="Filtrar data"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($linkedProducts as $product): ?>
                <?php
                  $price = $product['price'] ?? null;
                  $priceLabel = $price !== null && $price !== '' ? 'R$ ' . number_format((float) $price, 2, ',', '.') : '-';
                ?>
                <tr>
                  <td data-value="<?php echo $esc((string) ($product['sku'] ?? '')); ?>"><?php echo $esc((string) ($product['sku'] ?? '-')); ?></td>
                  <td data-value="<?php echo $esc((string) ($product['name'] ?? '')); ?>"><?php echo $esc((string) ($product['name'] ?? '')); ?></td>
                  <td data-value="<?php echo $esc((string) ($product['status'] ?? '')); ?>">
                    <span class="pill"><?php echo $esc((string) ($product['status'] ?? '')); ?></span>
                  </td>
                  <td data-value="<?php echo $esc((string) ($product['price'] ?? '')); ?>"><?php echo $esc($priceLabel); ?></td>
                  <td data-value="<?php echo $esc((string) ($product['created_at'] ?? '')); ?>"><?php echo $esc($formatDateTime($product['created_at'] ?? null)); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
