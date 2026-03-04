<?php
/** @var array $product */
/** @var string $statusLabel */
/** @var string $visibilityLabel */
/** @var callable $esc */
?>
<?php
  $product = $product ?? [];
  $statusLabel = $statusLabel ?? '';
  $visibilityLabel = $visibilityLabel ?? '';
  $productRef = (int) ($product['sku'] ?? ($product['id'] ?? 0));
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
  <div>
    <h1><?php echo $esc($product['name'] ?? 'Produto'); ?></h1>
    <div class="subtitle">Detalhes do produto no catálogo interno</div>
  </div>
  <div style="display:flex;gap:12px;">
    <?php if (userCan('products.edit')): ?>
      <a class="btn primary" href="produto-cadastro.php?id=<?php echo $productRef; ?>">Editar</a>
    <?php endif; ?>
    <a class="btn ghost" href="produto-list.php">Voltar para lista</a>
  </div>
</div>

<div class="product-details" style="max-width:1200px;margin:0 auto;">
  <!-- Informações Básicas -->
  <section class="detail-section">
    <h2>Informações Básicas</h2>
    <dl class="detail-list">
      <dt>SKU</dt>
      <dd><?php echo $esc($product['sku'] ?? ($productRef > 0 ? (string) $productRef : '—')); ?></dd>

      <dt>Nome</dt>
      <dd><?php echo $esc($product['name'] ?? ''); ?></dd>

      <?php if (!empty($product['short_description'])): ?>
        <dt>Descrição Curta</dt>
        <dd><?php echo nl2br($esc($product['short_description'])); ?></dd>
      <?php endif; ?>

      <?php if (!empty($product['description'])): ?>
        <dt>Descrição Completa</dt>
        <dd><?php echo nl2br($esc($product['description'])); ?></dd>
      <?php endif; ?>
    </dl>
  </section>

  <!-- Classificação -->
  <section class="detail-section">
    <h2>Classificação</h2>
    <dl class="detail-list">
      <dt>Marca</dt>
      <dd><?php echo $esc($product['brand_name'] ?? '—'); ?></dd>

      <dt>Categoria</dt>
      <dd><?php echo $esc($product['category_name'] ?? '—'); ?></dd>
    </dl>
  </section>

  <!-- Preços e Custos -->
  <section class="detail-section">
    <h2>Preços e Custos</h2>
    <dl class="detail-list">
      <dt>Preço de Venda</dt>
      <dd>
        <?php if (isset($product['price']) && $product['price'] !== null): ?>
          R$ <?php echo number_format((float)$product['price'], 2, ',', '.'); ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </dd>

      <dt>Custo</dt>
      <dd>
        <?php if (isset($product['cost']) && $product['cost'] !== null): ?>
          R$ <?php echo number_format((float)$product['cost'], 2, ',', '.'); ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </dd>

      <dt>Preço Sugerido</dt>
      <dd>
        <?php if (isset($product['suggested_price']) && $product['suggested_price'] !== null): ?>
          R$ <?php echo number_format((float)$product['suggested_price'], 2, ',', '.'); ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </dd>

      <dt>Margem</dt>
      <dd>
        <?php if (isset($product['margin']) && $product['margin'] !== null): ?>
          <?php echo number_format((float)$product['margin'], 2, ',', '.'); ?>%
        <?php else: ?>
          —
        <?php endif; ?>
      </dd>
    </dl>
  </section>

  <!-- Status e Visibilidade -->
  <section class="detail-section">
    <h2>Status e Visibilidade</h2>
    <dl class="detail-list">
      <dt>Status</dt>
      <dd>
        <span class="badge badge-<?php echo $esc($product['status'] ?? 'draft'); ?>">
          <?php echo $esc($statusLabel); ?>
        </span>
      </dd>

      <dt>Visibilidade</dt>
      <dd>
        <span class="badge badge-<?php echo $esc($product['visibility'] ?? 'public'); ?>">
          <?php echo $esc($visibilityLabel); ?>
        </span>
      </dd>

      <dt>Quantidade Disponível</dt>
      <dd><?php echo isset($product['quantity']) ? (int) $product['quantity'] : 0; ?></dd>
    </dl>
  </section>

  <!-- Disponibilidade -->
  <section class="detail-section">
    <h2>Disponibilidade de Venda</h2>
    <dl class="detail-list">
      <dt>Pode vender agora</dt>
      <dd>
        <?php
          $qty = isset($product['quantity']) ? (int) $product['quantity'] : 0;
          $status = (string) ($product['status'] ?? 'draft');
          $canSell = $status === 'disponivel' && $qty > 0;
        ?>
        <?php echo $canSell ? 'Sim' : 'Não'; ?>
      </dd>
    </dl>
  </section>

  <!-- Dados Físicos -->
  <section class="detail-section">
    <h2>Dados Físicos</h2>
    <dl class="detail-list">
      <dt>Peso</dt>
      <dd>
        <?php if (isset($product['weight']) && $product['weight'] !== null): ?>
          <?php echo number_format((float)$product['weight'], 2, ',', '.'); ?> kg
        <?php else: ?>
          —
        <?php endif; ?>
      </dd>
    </dl>
  </section>

  <!-- Metadados do Sistema -->
  <section class="detail-section">
    <h2>Metadados do Sistema</h2>
    <dl class="detail-list">
      <dt>Criado em</dt>
      <dd><?php echo $esc($product['created_at'] ?? '—'); ?></dd>

      <dt>Atualizado em</dt>
      <dd><?php echo $esc($product['updated_at'] ?? '—'); ?></dd>
    </dl>
  </section>
</div>

<style>
.product-details {
  display: grid;
  gap: 24px;
}

.detail-section {
  border: 1px solid var(--border, #ddd);
  border-radius: 8px;
  padding: 20px;
}

.detail-section h2 {
  margin: 0 0 16px 0;
  font-size: 18px;
  font-weight: 600;
  color: var(--text-primary, #333);
}

.detail-list {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 12px 24px;
  margin: 0;
}

.detail-list dt {
  font-weight: 500;
  color: var(--text-secondary, #666);
}

.detail-list dd {
  margin: 0;
  color: var(--text-primary, #333);
}

.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.badge-draft {
  background-color: #ffc107;
  color: #000;
}

.badge-active {
  background-color: #28a745;
  color: #fff;
}

.badge-disponivel {
  background-color: #28a745;
  color: #fff;
}

.badge-reservado {
  background-color: #f59e0b;
  color: #fff;
}

.badge-esgotado {
  background-color: #ef4444;
  color: #fff;
}

.badge-baixado {
  background-color: #7c3aed;
  color: #fff;
}

.badge-archived {
  background-color: #6c757d;
  color: #fff;
}

.badge-public {
  background-color: #007bff;
  color: #fff;
}

.badge-catalog,
.badge-search {
  background-color: #17a2b8;
  color: #fff;
}

.badge-hidden {
  background-color: #6c757d;
  color: #fff;
}

@media (max-width: 768px) {
  .detail-list {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .detail-list dt {
    font-weight: 600;
    margin-top: 12px;
  }
  
  .detail-list dt:first-child {
    margin-top: 0;
  }
}
</style>
