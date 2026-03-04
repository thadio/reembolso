<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentNavFilters = [
  'status' => isset($_GET['status']) ? (string) $_GET['status'] : '',
  'role' => isset($_GET['role']) ? (string) $_GET['role'] : '',
  'view' => isset($_GET['view']) ? (string) $_GET['view'] : '',
];
$user = currentUser();
if (!function_exists('navItemIsActive')) {
  function navItemIsActive(array $item, string $currentPage, array $currentNavFilters): bool {
    if (!empty($item['href'])) {
      $parts = parse_url($item['href']);
      $hrefPage = basename($parts['path'] ?? $item['href']);
      $hrefFilters = [
        'status' => '',
        'role' => '',
        'view' => '',
      ];
      if (!empty($parts['query'])) {
        $hrefQuery = [];
        parse_str($parts['query'], $hrefQuery);
        foreach ($hrefFilters as $key => $_value) {
          if (isset($hrefQuery[$key])) {
            $hrefFilters[$key] = (string) $hrefQuery[$key];
          }
        }
      }
      if ($hrefPage === $currentPage) {
        foreach ($hrefFilters as $key => $hrefValue) {
          $currentValue = isset($currentNavFilters[$key]) ? (string) $currentNavFilters[$key] : '';
          if ($hrefValue !== $currentValue) {
            return false;
          }
        }
        return true;
      }
    }

    if (!empty($item['children'])) {
      foreach ($item['children'] as $child) {
        if (navItemIsActive($child, $currentPage, $currentNavFilters)) {
          return true;
        }
      }
    }

    return false;
  }
}

if (!function_exists('navItemVisible')) {
  function navItemVisible(array $item): bool {
    $hasChildren = !empty($item['children']);
    $hasLink = !empty($item['href']);
    $hasPermission = !empty($item['permission']);
    $canSeeSelf = !$hasPermission || userCan($item['permission']);

    if (!$canSeeSelf) {
      if (!$hasChildren) {
        return false;
      }
    } elseif (!$hasChildren || $hasLink) {
      return true;
    }

    foreach ($item['children'] as $child) {
      if (navItemVisible($child)) {
        return true;
      }
    }

    return false;
  }
}

if (!function_exists('navIconId')) {
  function navIconId(string $label): string {
    $normalized = strtolower(trim($label));
    $map = [
      'início' => 'nav-icon-home',
      'inicio' => 'nav-icon-home',
      'pedidos' => 'nav-icon-orders',
      'produtos' => 'nav-icon-products',
      'produtos e disponibilidade' => 'nav-icon-products',
      'disponibilidade' => 'nav-icon-inventory',
      'logística' => 'nav-icon-misc',
      'logistica' => 'nav-icon-misc',
      'financeiro' => 'nav-icon-misc',
      'fornecedores' => 'nav-icon-vendors',
      'clientes' => 'nav-icon-customers',
      'pessoas' => 'nav-icon-customers',
      'diversos' => 'nav-icon-misc',
      'parâmetros' => 'nav-icon-params',
      'parametros' => 'nav-icon-params',
      'administração' => 'nav-icon-admin',
      'administracao' => 'nav-icon-admin',
      'ponto' => 'nav-icon-time',
    ];
    return $map[$normalized] ?? 'nav-icon-misc';
  }
}

if (!function_exists('renderNavItems')) {
  function renderNavItems(array $items, int $level, string $currentPage, array $currentNavFilters): void {
    foreach ($items as $item) {
      if (!navItemVisible($item)) {
        continue;
      }
      $visibleChildren = [];
      if (!empty($item['children'])) {
        foreach ($item['children'] as $child) {
          if (navItemVisible($child)) {
            $visibleChildren[] = $child;
          }
        }
      }
      $hasVisibleChildren = !empty($visibleChildren);
      $isActive = navItemIsActive($item, $currentPage, $currentNavFilters);
      $canLink = !empty($item['href']) && (empty($item['permission']) || userCan($item['permission']));
      $nodeClasses = 'nav-node nav-level-' . $level;
      if ($hasVisibleChildren) {
        $nodeClasses .= ' nav-has-children';
      }
      if ($isActive) {
        $nodeClasses .= ' active';
      }
      $iconId = navIconId((string) ($item['label'] ?? ''));
      ?>
      <div class="<?php echo htmlspecialchars($nodeClasses, ENT_QUOTES, 'UTF-8'); ?>" data-level="<?php echo (int) $level; ?>">
        <?php if ($canLink && !$hasVisibleChildren): ?>
          <a class="nav-link<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>">
            <span class="nav-icon" aria-hidden="true">
              <svg>
                <use href="#<?php echo htmlspecialchars($iconId, ENT_QUOTES, 'UTF-8'); ?>" xlink:href="#<?php echo htmlspecialchars($iconId, ENT_QUOTES, 'UTF-8'); ?>"></use>
              </svg>
            </span>
            <span class="nav-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($isActive): ?>
              <span class="nav-meta" aria-hidden="true">
                <span class="nav-active-dot">•</span>
              </span>
            <?php endif; ?>
          </a>
        <?php else: ?>
          <button
            class="nav-link nav-link--toggle<?php echo $isActive ? ' active' : ''; ?>"
            type="button"
            <?php echo $hasVisibleChildren ? 'data-nav-trigger' : ''; ?>
            <?php if ($hasVisibleChildren): ?>
              aria-expanded="false"
              aria-haspopup="true"
            <?php endif; ?>
            title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>"
          >
            <span class="nav-icon" aria-hidden="true">
              <svg>
                <use href="#<?php echo htmlspecialchars($iconId, ENT_QUOTES, 'UTF-8'); ?>" xlink:href="#<?php echo htmlspecialchars($iconId, ENT_QUOTES, 'UTF-8'); ?>"></use>
              </svg>
            </span>
            <span class="nav-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($isActive || $hasVisibleChildren): ?>
              <span class="nav-meta" aria-hidden="true">
                <?php if ($isActive): ?><span class="nav-active-dot">•</span><?php endif; ?>
                <?php if ($hasVisibleChildren): ?><span class="nav-caret"></span><?php endif; ?>
              </span>
            <?php endif; ?>
          </button>
        <?php endif; ?>
        <?php if ($hasVisibleChildren): ?>
          <div class="nav-flyout nav-flyout-level-<?php echo (int) ($level + 1); ?>">
            <?php renderNavItems($visibleChildren, $level + 1, $currentPage, $currentNavFilters); ?>
          </div>
        <?php endif; ?>
      </div>
      <?php
    }
  }
}

$navItems = [
  ['href' => 'index.php', 'label' => 'Início', 'permission' => 'dashboard.view'],
  [
    'label' => 'Pedidos',
    'children' => [
      ['href' => 'pedido-cadastro.php', 'label' => 'Novo', 'permission' => 'orders.create'],
      ['href' => 'pedido-list.php', 'label' => 'Listar', 'permission' => 'orders.view'],
      ['href' => 'pedido-list.php?status=trash', 'label' => 'Lixeira', 'permission' => 'orders.view'],
      ['href' => 'pedido-devolucao-list.php', 'label' => 'Devoluções', 'permission' => 'order_returns.view'],
    ],
  ],
  [
    'label' => 'Produtos',
    'children' => [
      [
        'label' => 'Produtos',
        'children' => [
          ['href' => 'produto-cadastro.php', 'label' => 'Novo', 'permission' => 'products.create'],
          ['href' => 'produto-list.php', 'label' => 'Listar', 'permission' => 'products.view'],
          ['href' => 'produto-list.php?status=trash', 'label' => 'Lixeira', 'permission' => 'products.trash'],
          ['href' => 'produto-publicacao-lote.php', 'label' => 'Publicação em lote', 'permission' => 'products.bulk_publish'],
          ['href' => 'produto-baixa.php', 'label' => 'Baixa de produto', 'permission' => 'products.writeoff'],
        ],
      ],
      [
        'label' => 'Lotes e conferência',
        'children' => [
          ['href' => 'lote-produtos.php', 'label' => 'Novo lote', 'permission' => 'products.batch_intake'],
          ['href' => 'lote-produtos-gestao.php', 'label' => 'Gestão de lotes', 'permission' => 'products.batch_intake'],
          ['href' => 'disponibilidade-conferencia.php', 'label' => 'Nova conferência', 'permission' => 'inventory.view'],
          ['href' => 'disponibilidade-conferencia-acompanhamento.php', 'label' => 'Acompanhamento', 'permission' => 'inventory.monitor'],
        ],
      ],
      [
        'label' => 'Taxonomias do produto',
        'children' => [
          ['href' => 'colecao-cadastro.php', 'label' => 'Nova categoria', 'permission' => 'collections.create'],
          ['href' => 'colecao-list.php', 'label' => 'Categorias', 'permission' => 'collections.view'],
          ['href' => 'colecao-list.php?status=inativa', 'label' => 'Categorias arquivadas', 'permission' => 'collections.delete'],
          ['href' => 'marca-cadastro.php', 'label' => 'Nova marca', 'permission' => 'brands.create'],
          ['href' => 'marca-list.php', 'label' => 'Marcas', 'permission' => 'brands.view'],
          ['href' => 'marca-list.php?status=inativa', 'label' => 'Marcas arquivadas', 'permission' => 'brands.delete'],
          ['href' => 'tag-cadastro.php', 'label' => 'Nova tag', 'permission' => 'products.edit'],
          ['href' => 'tag-list.php', 'label' => 'Tags', 'permission' => 'products.view'],
          ['href' => 'tag-list.php?status=trash', 'label' => 'Tags na lixeira', 'permission' => 'products.edit'],
        ],
      ],
    ],
  ],
  [
    'label' => 'Consignação',
    'children' => [
      ['href' => 'consignacao-painel.php', 'label' => 'Painel', 'permission' => 'consignment_module.view_dashboard'],
      ['href' => 'consignacao-produtos.php', 'label' => 'Produtos consignados', 'permission' => 'consignment_module.view_products'],
      ['href' => 'consignacao-vendas.php', 'label' => 'Vendas consignadas', 'permission' => 'consignment_module.view_sales'],
      [
        'label' => 'Pagamentos',
        'children' => [
          ['href' => 'consignacao-pagamento-cadastro.php', 'label' => 'Novo pagamento', 'permission' => 'consignment_module.create_payout'],
          ['href' => 'consignacao-pagamento-list.php', 'label' => 'Listar pagamentos', 'permission' => 'consignment_module.view_payouts'],
        ],
      ],
      [
        'label' => 'Relatórios',
        'children' => [
          ['href' => 'consignacao-relatorio-fornecedora.php', 'label' => 'Por fornecedora', 'permission' => 'consignment_module.export_reports'],
          ['href' => 'consignacao-relatorio-interno.php', 'label' => 'Interno (gestão)', 'permission' => 'consignment_module.export_reports'],
          ['href' => 'consignacao-relatorio-dinamico.php', 'label' => 'Relatório dinâmico', 'permission' => 'consignment_module.export_reports'],
          ['href' => 'consignacao-relatorio-modelos.php', 'label' => 'Modelos de relatório', 'permission' => 'consignment_module.export_reports'],
        ],
      ],
      ['href' => 'consignacao-inconsistencias.php', 'label' => 'Inconsistências', 'permission' => 'consignment_module.admin_override'],
      [
        'label' => 'Recebimento',
        'children' => [
          ['href' => 'consignacao-recebimento-cadastro.php', 'label' => 'Novo recebimento (lote rápido)', 'permission' => 'consignments.create'],
          ['href' => 'consignacao-recebimento-list.php', 'label' => 'Listar recebimentos', 'permission' => 'consignments.view'],
          ['href' => 'consignacao-produto-cadastro.php?action=create', 'label' => 'Nova consignação (por SKU)', 'permission' => 'consignments.create'],
          ['href' => 'consignacao-produto-list.php', 'label' => 'Consignações por SKU', 'permission' => 'consignments.view'],
        ],
      ],
    ],
  ],
  [
    'label' => 'Fornecedores',
    'children' => [
      ['href' => 'pessoa-cadastro.php?role=fornecedor', 'label' => 'Novo', 'permission' => 'vendors.create'],
      ['href' => 'pessoa-list.php?role=fornecedor', 'label' => 'Listar', 'permission' => 'vendors.view'],
      ['href' => 'pessoa-list.php?status=trash&role=fornecedor', 'label' => 'Lixeira', 'permission' => 'vendors.delete'],
      ['href' => 'fornecedor-relatorio.php', 'label' => 'Produtos por fornecedor', 'permission' => 'vendors.report'],
      ['href' => 'fornecedor-vendas-relatorio.php', 'label' => 'Vendas por fornecedor', 'permission' => 'vendors.report'],
    ],
  ],
  [
    'label' => 'Clientes',
    'children' => [
      ['href' => 'pessoa-cadastro.php?role=cliente', 'label' => 'Novo', 'permission' => 'customers.create'],
      ['href' => 'pessoa-list.php?role=cliente', 'label' => 'Listar', 'permission' => 'customers.view'],
      ['href' => 'pessoa-list.php?status=trash&role=cliente', 'label' => 'Lixeira', 'permission' => 'customers.delete'],
      ['href' => 'cliente-compras.php', 'label' => 'Compras por clientes', 'permission' => 'customers.view'],
    ],
  ],
  [
    'label' => 'Pessoas',
    'children' => [
      ['href' => 'pessoa-cadastro.php', 'label' => 'Novo', 'permission' => 'people.create'],
      ['href' => 'pessoa-list.php', 'label' => 'Listar', 'permission' => 'people.view'],
      ['href' => 'pessoa-list.php?status=trash', 'label' => 'Lixeira', 'permission' => 'people.delete'],
    ],
  ],
  [
    'label' => 'Financeiro',
    'children' => [
      ['href' => 'financeiro-cadastro.php', 'label' => 'Novo lançamento', 'permission' => 'finance_entries.create'],
      ['href' => 'financeiro-list.php', 'label' => 'Listar', 'permission' => 'finance_entries.view'],
      ['href' => 'financeiro-categoria-cadastro.php', 'label' => 'Nova categoria', 'permission' => 'finance_categories.create'],
      ['href' => 'financeiro-categoria-list.php', 'label' => 'Categorias', 'permission' => 'finance_categories.view'],
      ['href' => 'cupom-credito-cadastro.php', 'label' => 'Novo cupom/crédito', 'permission' => 'voucher_accounts.create'],
      ['href' => 'cupom-credito-list.php', 'label' => 'Listar cupons/créditos', 'permission' => 'voucher_accounts.view'],
      ['href' => 'cupom-credito-list.php?status=trash', 'label' => 'Lixeira de cupons/créditos', 'permission' => 'voucher_accounts.delete'],
    ],
  ],
  [
    'label' => 'Logística',
    'children' => [
      ['href' => 'entrega-acompanhamento.php', 'label' => 'Acompanhamento de entregas', 'permission' => 'orders.view'],
      [
        'label' => 'Sacolinhas',
        'children' => [
          ['href' => 'sacolinha-cadastro.php', 'label' => 'Abrir sacolinha', 'permission' => 'bags.create'],
          ['href' => 'sacolinha-list.php', 'label' => 'Listar', 'permission' => 'bags.view'],
          ['href' => 'sacolinha-list.php?status=cancelada', 'label' => 'Canceladas', 'permission' => 'bags.view'],
        ],
      ],
    ],
  ],
  [
    'label' => 'Parâmetros',
    'children' => [
      [
        'label' => 'Canais de venda',
        'children' => [
          ['href' => 'canal-venda-cadastro.php', 'label' => 'Novo', 'permission' => 'sales_channels.create'],
          ['href' => 'canal-venda-list.php', 'label' => 'Listar', 'permission' => 'sales_channels.view'],
        ],
      ],
      [
        'label' => 'Transportadoras',
        'children' => [
          ['href' => 'transportadora-cadastro.php', 'label' => 'Novo', 'permission' => 'carriers.create'],
          ['href' => 'transportadora-list.php', 'label' => 'Listar', 'permission' => 'carriers.view'],
        ],
      ],
      [
        'label' => 'Padrões de cupom',
        'children' => [
          ['href' => 'cupom-credito-identificacao-cadastro.php', 'label' => 'Novo', 'permission' => 'voucher_identification_patterns.create'],
          ['href' => 'cupom-credito-identificacao-list.php', 'label' => 'Listar', 'permission' => 'voucher_identification_patterns.view'],
        ],
      ],
      [
        'label' => 'Bancos',
        'children' => [
          ['href' => 'banco-cadastro.php', 'label' => 'Novo', 'permission' => 'banks.create'],
          ['href' => 'banco-list.php', 'label' => 'Listar', 'permission' => 'banks.view'],
        ],
      ],
      [
        'label' => 'Contas',
        'children' => [
          ['href' => 'conta-bancaria-cadastro.php', 'label' => 'Novo', 'permission' => 'bank_accounts.create'],
          ['href' => 'conta-bancaria-list.php', 'label' => 'Listar', 'permission' => 'bank_accounts.view'],
        ],
      ],
      [
        'label' => 'Maquininhas',
        'children' => [
          ['href' => 'maquininha-cadastro.php', 'label' => 'Novo', 'permission' => 'payment_terminals.create'],
          ['href' => 'maquininha-list.php', 'label' => 'Listar', 'permission' => 'payment_terminals.view'],
        ],
      ],
      [
        'label' => 'Método de pagamento',
        'children' => [
          ['href' => 'metodo-pagamento-cadastro.php', 'label' => 'Novo', 'permission' => 'payment_methods.create'],
          ['href' => 'metodo-pagamento-list.php', 'label' => 'Listar', 'permission' => 'payment_methods.view'],
        ],
      ],
      [
        'label' => 'Calendário',
        'children' => [
          ['href' => 'data-comemorativa-cadastro.php', 'label' => 'Novo', 'permission' => 'holidays.create'],
          ['href' => 'data-comemorativa-list.php', 'label' => 'Listar', 'permission' => 'holidays.view'],
        ],
      ],
    ],
  ],
  [
    'label' => 'Administração',
    'children' => [
      ['href' => 'usuario-list.php', 'label' => 'Usuários', 'permission' => 'users.view'],
      ['href' => 'usuario-cadastro.php', 'label' => 'Novo usuário', 'permission' => 'users.create'],
      ['href' => 'perfil-list.php', 'label' => 'Perfis de acesso', 'permission' => 'profiles.view'],
      ['href' => 'perfil-cadastro.php', 'label' => 'Novo perfil', 'permission' => 'profiles.create'],
      ['href' => 'regra-list.php', 'label' => 'Regras e comunicados', 'permission' => 'rules.view'],
      ['href' => 'regra-cadastro.php', 'label' => 'Nova regra', 'permission' => 'rules.create'],
      ['href' => 'audit-log-list.php', 'label' => 'Auditoria', 'permission' => 'auditoria.view'],
      ['href' => 'migracao-tabelas-upload.php', 'label' => 'Migração de tabelas', 'permission' => 'users.view'],
    ],
  ],
  [
    'label' => 'Ponto',
    'children' => [
      ['href' => 'ponto-list.php', 'label' => 'Listar', 'permission' => 'timeclock.view'],
      ['href' => 'ponto-relatorio.php', 'label' => 'Relatórios', 'permission' => 'timeclock.report'],
    ],
  ],
];
?>
<aside id="mainNav" class="nav" aria-label="Menu principal">
  <?php if ($user): ?>
    <div class="nav-user">
      <div class="nav-avatar"><?php echo htmlspecialchars(substr($user['name'], 0, 1), ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="nav-user-meta">
        <div class="nav-user-name"><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-role"><?php echo htmlspecialchars($user['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <div class="nav-user-actions">
        <button class="nav-toggle nav-toggle--inside" type="button" aria-label="Recolher menu" aria-expanded="false" aria-controls="mainNav">
          <span></span>
          <span></span>
          <span></span>
        </button>
        <a class="nav-logout" href="logout.php" title="Sair">Sair</a>
      </div>
    </div>
  <?php endif; ?>
  <div class="nav-title">Navegação</div>
  <?php renderNavItems($navItems, 1, $currentPage, $currentNavFilters); ?>
</aside>
