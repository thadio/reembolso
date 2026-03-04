<div class="main-content">
        <div class="page-header">
            <h1>Consignação #<?= str_pad($consignment['id'], 5, '0', STR_PAD_LEFT) ?></h1>
            <div class="actions">
                <?php if ($consignment['status'] === 'aberta'): ?>
                    <a href="/consignacao-produto-cadastro.php?action=edit&id=<?= $consignment['id'] ?>" 
                       class="btn btn-secondary">
                        ✏️ Editar
                    </a>
                    <a href="/consignacao-produto-cadastro.php?action=close&id=<?= $consignment['id'] ?>" 
                       class="btn btn-warning"
                       onclick="return confirm('Tem certeza que deseja fechar esta consignação? Esta ação não pode ser desfeita.')">
                        🔒 Fechar
                    </a>
                <?php endif; ?>
                <a href="/consignacao-produto-list.php" class="btn btn-outline">Voltar</a>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- Detalhes da Consignação -->
        <div class="details-grid">
            <div class="detail-card">
                <h3>Informações Gerais</h3>
                <dl class="detail-list">
                    <dt>ID:</dt>
                    <dd>#<?= str_pad($consignment['id'], 5, '0', STR_PAD_LEFT) ?></dd>

                    <dt>Fornecedor:</dt>
                    <dd><?= htmlspecialchars($consignment['supplier_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt>Status:</dt>
                    <dd>
                        <span class="badge badge-<?= $consignment['status'] ?>">
                            <?php 
                            $statusLabels = [
                                'aberta' => 'Aberta',
                                'fechada' => 'Fechada',
                                'pendente' => 'Pendente',
                                'liquidada' => 'Liquidada',
                            ];
                            echo htmlspecialchars($statusLabels[$consignment['status']] ?? $consignment['status'], ENT_QUOTES, 'UTF-8');
                            ?>
                        </span>
                    </dd>

                    <dt>Data Recebimento:</dt>
                    <dd><?= $consignment['received_at'] ? date('d/m/Y H:i', strtotime($consignment['received_at'])) : '-' ?></dd>

                    <dt>Data Fechamento:</dt>
                    <dd><?= $consignment['closed_at'] ? date('d/m/Y H:i', strtotime($consignment['closed_at'])) : '-' ?></dd>
                </dl>
            </div>

            <div class="detail-card">
                <h3>Resumo Financeiro</h3>
                <dl class="detail-list">
                    <dt>Total de Itens:</dt>
                    <dd><?= count($items) ?></dd>

                    <dt>Valor Total:</dt>
                    <dd class="highlight">
                        R$ <?php 
                        $total = array_reduce($items, function($carry, $item) {
                            return $carry + ($item['quantity'] * $item['unit_cost']);
                        }, 0);
                        echo number_format($total, 2, ',', '.');
                        ?>
                    </dd>

                    <dt>Custo Médio por Item:</dt>
                    <dd>
                        R$ <?php 
                        $avgCost = count($items) > 0 ? $total / count($items) : 0;
                        echo number_format($avgCost, 2, ',', '.');
                        ?>
                    </dd>
                </dl>
            </div>

            <?php if (!empty($consignment['notes'])): ?>
                <div class="detail-card full-width">
                    <h3>Observações</h3>
                    <p><?= nl2br(htmlspecialchars($consignment['notes'], ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Itens da Consignação -->
        <div class="items-section">
            <h2>Itens da Consignação</h2>
            
            <div class="table-container">
                <table class="data-table" data-table="interactive">
                    <thead>
                        <tr>
                            <th data-sort-key="internal_code" aria-sort="none">Código Interno</th>
                            <th data-sort-key="sku" aria-sort="none">SKU</th>
                            <th data-sort-key="product_name" aria-sort="none">Produto</th>
                            <th data-sort-key="quantity" aria-sort="none">Quantidade</th>
                            <th data-sort-key="unit_cost" aria-sort="none">Custo Unitário</th>
                            <th data-sort-key="subtotal" aria-sort="none">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6" class="no-results">Nenhum item encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <?php $subtotal = $item['quantity'] * $item['unit_cost']; ?>
                                <?php $productSku = (int) ($item['product_sku'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <?php if ($productSku > 0 && userCan('products.view')): ?>
                                            <a href="/produto-list.php?filter_sku=<?= $productSku ?>">
                                                <?= htmlspecialchars($item['internal_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['internal_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-value="<?= $item['sku'] ?>">
                                        <?= $item['sku'] ? str_pad($item['sku'], 6, '0', STR_PAD_LEFT) : '-' ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['product_name'] ?? 'Sem produto', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-value="<?= $item['quantity'] ?>">
                                        <?= number_format($item['quantity'], 0, ',', '.') ?>
                                    </td>
                                    <td data-value="<?= $item['unit_cost'] ?>">
                                        R$ <?= number_format($item['unit_cost'], 2, ',', '.') ?>
                                    </td>
                                    <td data-value="<?= $subtotal ?>">
                                        <strong>R$ <?= number_format($subtotal, 2, ',', '.') ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($items)): ?>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Total Geral:</strong></td>
                                <td data-value="<?= $total ?>">
                                    <strong>R$ <?= number_format($total, 2, ',', '.') ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Metadados -->
        <div class="metadata-section">
            <h3>Informações do Sistema</h3>
            <dl class="detail-list">
                <dt>Criado em:</dt>
                <dd><?= $consignment['created_at'] ? date('d/m/Y H:i:s', strtotime($consignment['created_at'])) : '-' ?></dd>

                <dt>Atualizado em:</dt>
                <dd><?= $consignment['updated_at'] ? date('d/m/Y H:i:s', strtotime($consignment['updated_at'])) : '-' ?></dd>
            </dl>
        </div>
</div>
