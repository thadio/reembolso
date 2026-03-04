<?php

namespace App\Support;

/**
 * Catálogo central de módulos e ações permitidas.
 */
class Permissions
{
    /**
     * @return array<string, array{label: string, actions: array<string, string>}>
     */
    public static function catalog(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'actions' => [
                    'view' => 'Visualizar painel',
                    'overview' => 'Resumo geral (legado)',
                    'inventory' => 'Widgets de disponibilidade (legado)',
                    'suppliers' => 'Widgets de fornecedores/clientes (legado)',
                    'collections' => 'Widgets de coleções (legado)',
                    'quick_links' => 'Ações rápidas',
                    'widget_overview_highlights' => 'Destaques iniciais',
                    'widget_stock_value' => 'Card valor potencial de disponibilidade',
                    'widget_margin' => 'Card margem média',
                    'widget_active_products' => 'Card produtos ativos',
                    'widget_active_customers' => 'Card clientes engajados',
                    'widget_suppliers_dependency' => 'Dependência de fornecedores/estados',
                    'widget_inventory_attention' => 'Itens que pedem atenção',
                    'widget_collections_strength' => 'Força das coleções',
                    'widget_inventory_missing_photos' => 'Disponibilidade sem fotos',
                    'widget_inventory_missing_costs' => 'Produtos de compra sem custo',
                    'widget_inventory_unpublished' => 'Com foto e não publicados',
                    'widget_inventory_old_consigned' => 'Produtos consignados antigos',
                    'widget_timeclock' => 'Relógio do ponto',
                    'widget_calendar' => 'Cronograma da semana',
                    'widget_ops_bags' => 'Sacolinha abertas',
                    'widget_ops_consignments' => 'Pré-lotes pendentes',
                    'widget_ops_deliveries' => 'Vendas pendentes de entrega',
                    'widget_ops_refunds' => 'Ressarcimentos a fazer',
                    'widget_ops_credits' => 'Créditos e cupons ativos',
                    'widget_ops_consign_vouchers' => 'Cupons por comissão de consignação',
                    'widget_finance_payables' => 'Despesas e projeções',
                    'widget_sales_performance' => 'Widget performance de vendas',
                    'layout_customize' => 'Customizar layout do dashboard',
                ],
            ],
            'products' => [
                'label' => 'Produtos',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'trash' => 'Visualizar lixeira',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Mover para lixeira',
                    'batch_intake' => 'Receber lote de produtos',
                    'bulk_publish' => 'Publicação em lote',
                    'restore' => 'Restaurar da lixeira',
                    'force_delete' => 'Excluir definitivamente',
                    'inventory' => 'Ajustar disponibilidade/visibilidade',
                    'writeoff' => 'Baixa de produto (destinação)',
                ],
            ],
            'consignments' => [
                'label' => 'Consignações (pré-lote)',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Registrar recebimento',
                    'edit' => 'Editar recebimento/devolução',
                    'delete' => 'Excluir recebimento',
                    'close' => 'Fechar consignação',
                ],
            ],
            'collections' => [
                'label' => 'Categorias',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'brands' => [
                'label' => 'Marcas',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'vendors' => [
                'label' => 'Fornecedores',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'report' => 'Relatórios',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'rules' => [
                'label' => 'Regras',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'auditoria' => [
                'label' => 'Auditoria',
                'actions' => [
                    'view' => 'Visualizar logs de auditoria',
                    'view_details' => 'Visualizar detalhes de um log',
                    'view_trail' => 'Visualizar histórico de um registro',
                    'search' => 'Pesquisar logs',
                    'filter' => 'Filtrar por tabela/ação/usuário',
                    'export' => 'Exportar logs (futuro)',
                ],
            ],
            'customers' => [
                'label' => 'Clientes',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Mover para lixeira',
                    'restore' => 'Restaurar da lixeira',
                    'force_delete' => 'Excluir definitivamente',
                ],
            ],
            'people' => [
                'label' => 'Pessoas',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Mover para lixeira',
                    'restore' => 'Restaurar da lixeira',
                    'force_delete' => 'Excluir definitivamente',
                ],
            ],
            'orders' => [
                'label' => 'Pedidos',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Abrir pedido',
                    'edit' => 'Atualizar status',
                    'payment' => 'Atualizar pagamento',
                    'fulfillment' => 'Atualizar envio',
                    'cancel' => 'Cancelar',
                    'delete' => 'Mover para lixeira',
                ],
            ],
            'order_returns' => [
                'label' => 'Devoluções de pedidos',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Registrar devolução',
                    'edit' => 'Editar/atualizar devolução',
                    'refund' => 'Registrar reembolso/crédito',
                    'restock' => 'Dar entrada e ajustar disponibilidade',
                ],
            ],
            'sales_channels' => [
                'label' => 'Canais de venda',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'delivery_types' => [
                'label' => 'Tipos de entrega',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'carriers' => [
                'label' => 'Transportadoras',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Desativar',
                ],
            ],
            'voucher_identification_patterns' => [
                'label' => 'Padroes de identificacao (cupom/credito)',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'payment_methods' => [
                'label' => 'Metodos de pagamento',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'finance_entries' => [
                'label' => 'Financeiro',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'finance_categories' => [
                'label' => 'Categorias financeiras',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'voucher_accounts' => [
                'label' => 'Cupons e creditos',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'trash' => 'Visualizar lixeira',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'payout' => 'Registrar pagamento (PIX)',
                    'delete' => 'Mover para lixeira',
                    'restore' => 'Restaurar da lixeira',
                    'force_delete' => 'Excluir definitivamente',
                ],
            ],
            'banks' => [
                'label' => 'Bancos',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'bank_accounts' => [
                'label' => 'Contas bancarias',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'payment_terminals' => [
                'label' => 'Maquininhas e sistemas',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'bags' => [
                'label' => 'Sacolinhas',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Abrir',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'users' => [
                'label' => 'Usuários',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'profiles' => [
                'label' => 'Perfis de acesso',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'timeclock' => [
                'label' => 'Gestão de ponto',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Registrar ponto',
                    'approve' => 'Aprovar registros',
                    'report' => 'Relatórios',
                ],
            ],
            'holidays' => [
                'label' => 'Calendário comemorativo',
                'actions' => [
                    'view' => 'Listar/visualizar',
                    'create' => 'Criar',
                    'edit' => 'Editar',
                    'delete' => 'Excluir',
                ],
            ],
            'inventory' => [
                'label' => 'Conferência de disponibilidade',
                'actions' => [
                    'view' => 'Visualizar batimentos',
                    'monitor' => 'Acompanhar batimentos',
                    'open' => 'Abrir batimento',
                    'count' => 'Ler/ajustar disponibilidade',
                    'close' => 'Fechar batimento',
                ],
            ],
            'consignment_module' => [
                'label' => 'Gestão de Consignação',
                'actions' => [
                    'view_dashboard'       => 'Visualizar painel de consignação',
                    'view_products'        => 'Listar produtos consignados',
                    'edit_product_state'   => 'Alterar estado do produto (devolução/doação/descarte)',
                    'view_sales'           => 'Listar vendas consignadas',
                    'create_payout'        => 'Criar pagamento de consignação',
                    'confirm_payout'       => 'Confirmar pagamento (efetivar PIX)',
                    'cancel_payout'        => 'Cancelar pagamento confirmado',
                    'view_payouts'         => 'Listar pagamentos de consignação',
                    'export_reports'       => 'Gerar e exportar relatórios',
                    'view_inconsistencies' => 'Visualizar tela de inconsistências',
                    'admin_override'       => 'Ações administrativas (reativar, ajustar, editar vínculo)',
                    'manage_locks'         => 'Gerenciar bloqueio de períodos',
                ],
            ],
        ];
    }

    /**
     * Retorna permissões padrão para perfis iniciais.
     */
    public static function defaultProfiles(): array
    {
        $full = self::fullAccess();
        return [
            'Sem Acesso' => [],
            'Administrador' => $full,
            'Administrador de Sistema' => $full,
            'Gestor' => self::normalize([
                'dashboard' => [
                    'view' => true,
                    'overview' => true,
                    'inventory' => true,
                    'suppliers' => true,
                    'collections' => true,
                    'quick_links' => true,
                    'layout_customize' => true,
                    'widget_overview_highlights' => true,
                    'widget_stock_value' => true,
                    'widget_margin' => true,
                    'widget_active_products' => true,
                    'widget_active_customers' => true,
                    'widget_suppliers_dependency' => true,
                    'widget_inventory_attention' => true,
                    'widget_collections_strength' => true,
                    'widget_inventory_missing_photos' => true,
                    'widget_inventory_missing_costs' => true,
                    'widget_inventory_unpublished' => true,
                    'widget_timeclock' => true,
                    'widget_calendar' => true,
                    'widget_ops_bags' => true,
                    'widget_ops_consignments' => true,
                    'widget_ops_deliveries' => true,
                    'widget_ops_refunds' => true,
                    'widget_ops_credits' => true,
                    'widget_ops_consign_vouchers' => true,
                    'widget_finance_payables' => true,
                    'widget_sales_performance' => true,
                ],
                'products' => [
                    'view' => true,
                    'trash' => true,
                    'create' => true,
                    'edit' => true,
                    'batch_intake' => true,
                    'bulk_publish' => true,
                ],
                'consignments' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'collections' => ['view' => true, 'create' => true, 'edit' => true],
                'brands' => ['view' => true, 'create' => true, 'edit' => true],
                'vendors' => ['view' => true, 'report' => true, 'create' => true, 'edit' => true],
                'rules' => ['view' => true, 'create' => true, 'edit' => true],
                'customers' => ['view' => true, 'create' => true, 'edit' => true],
                'people' => ['view' => true, 'create' => true, 'edit' => true, 'merge' => true],
                'orders' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'payment' => true,
                    'fulfillment' => true,
                    'cancel' => true,
                ],
                'order_returns' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'refund' => true,
                    'restock' => true,
                ],
                'sales_channels' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'delivery_types' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'carriers' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                ],
                'voucher_identification_patterns' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'payment_methods' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'finance_entries' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'finance_categories' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'voucher_accounts' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'payout' => true,
                ],
                'banks' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'bank_accounts' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'payment_terminals' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'bags' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'users' => ['view' => true, 'create' => true, 'edit' => true],
                'holidays' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                ],
                'timeclock' => ['view' => true, 'create' => true, 'approve' => true, 'report' => true],
                'inventory' => ['view' => true, 'monitor' => true, 'open' => true, 'count' => true, 'close' => true],
                'consignment_module' => [
                    'view_dashboard' => true,
                    'view_products' => true,
                    'edit_product_state' => true,
                    'view_sales' => true,
                    'create_payout' => true,
                    'confirm_payout' => true,
                    'view_payouts' => true,
                    'export_reports' => true,
                    'view_inconsistencies' => true,
                ],
            ]),
            'Editor' => self::normalize([
                'dashboard' => [
                    'view' => true,
                    'overview' => true,
                    'inventory' => true,
                    'collections' => true,
                    'quick_links' => true,
                    'layout_customize' => true,
                    'widget_overview_highlights' => true,
                    'widget_stock_value' => true,
                    'widget_margin' => true,
                    'widget_active_products' => true,
                    'widget_active_customers' => true,
                    'widget_suppliers_dependency' => true,
                    'widget_inventory_attention' => true,
                    'widget_collections_strength' => true,
                    'widget_inventory_missing_photos' => true,
                    'widget_inventory_missing_costs' => true,
                    'widget_inventory_unpublished' => true,
                    'widget_timeclock' => true,
                    'widget_calendar' => true,
                    'widget_ops_bags' => true,
                    'widget_ops_consignments' => true,
                    'widget_ops_deliveries' => true,
                    'widget_ops_refunds' => true,
                    'widget_ops_credits' => true,
                    'widget_finance_payables' => true,
                    'widget_sales_performance' => true,
                ],
                'products' => [
                    'view' => true,
                    'trash' => true,
                    'create' => true,
                    'edit' => true,
                    'batch_intake' => true,
                ],
                'consignments' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'collections' => ['view' => true, 'create' => true, 'edit' => true],
                'brands' => ['view' => true, 'create' => true, 'edit' => true],
                'vendors' => ['view' => true, 'report' => true, 'create' => true, 'edit' => true],
                'rules' => ['view' => true, 'create' => true, 'edit' => true],
                'customers' => ['view' => true, 'create' => true, 'edit' => true],
                'people' => ['view' => true, 'create' => true, 'edit' => true, 'merge' => true],
                'orders' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'payment' => true,
                    'fulfillment' => true,
                ],
                'order_returns' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'refund' => true,
                    'restock' => true,
                ],
                'sales_channels' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'delivery_types' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'carriers' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                ],
                'voucher_identification_patterns' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'payment_methods' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'finance_entries' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'finance_categories' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'voucher_accounts' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                    'payout' => true,
                ],
                'banks' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'bank_accounts' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'payment_terminals' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'bags' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'holidays' => [
                    'view' => true,
                    'create' => true,
                    'edit' => true,
                ],
                'timeclock' => ['view' => true, 'create' => true],
                'inventory' => ['view' => true, 'monitor' => true, 'count' => true],
                'consignment_module' => [
                    'view_dashboard' => true,
                    'view_products' => true,
                    'view_sales' => true,
                    'view_payouts' => true,
                ],
            ]),
            'Colaborador' => self::normalize([
                'dashboard' => [
                    'view' => true,
                    'overview' => true,
                    'inventory' => true,
                    'quick_links' => true,
                    'layout_customize' => true,
                    'widget_overview_highlights' => true,
                    'widget_stock_value' => true,
                    'widget_margin' => true,
                    'widget_active_products' => true,
                    'widget_active_customers' => true,
                    'widget_suppliers_dependency' => true,
                    'widget_inventory_attention' => true,
                    'widget_collections_strength' => true,
                    'widget_inventory_missing_photos' => true,
                    'widget_inventory_missing_costs' => true,
                    'widget_inventory_unpublished' => true,
                    'widget_timeclock' => true,
                    'widget_calendar' => true,
                    'widget_ops_bags' => true,
                    'widget_ops_consignments' => true,
                    'widget_ops_deliveries' => true,
                    'widget_ops_refunds' => true,
                    'widget_ops_credits' => true,
                    'widget_finance_payables' => true,
                    'widget_sales_performance' => true,
                ],
                'products' => ['view' => true, 'trash' => true],
                'consignments' => ['view' => true],
                'inventory' => ['view' => true],
                'collections' => ['view' => true],
                'brands' => ['view' => true],
                'vendors' => ['view' => true, 'report' => true],
                'rules' => ['view' => true],
                'customers' => ['view' => true],
                'people' => ['view' => true],
                'orders' => ['view' => true],
                'order_returns' => ['view' => true],
                'sales_channels' => ['view' => true],
                'delivery_types' => ['view' => true],
                'carriers' => ['view' => true],
                'voucher_identification_patterns' => ['view' => true],
                'payment_methods' => ['view' => true],
                'finance_entries' => ['view' => true],
                'finance_categories' => ['view' => true],
                'voucher_accounts' => ['view' => true],
                'banks' => ['view' => true],
                'bank_accounts' => ['view' => true],
                'payment_terminals' => ['view' => true],
                'bags' => ['view' => true],
                'holidays' => ['view' => true],
                'timeclock' => ['view' => true, 'create' => true],
                'inventory' => ['view' => true, 'monitor' => true, 'count' => true],
                'consignment_module' => [
                    'view_dashboard' => true,
                    'view_products' => true,
                ],
            ]),
        ];
    }

    /**
     * Todas as permissões possíveis já normalizadas.
     *
     * @return array<string, array<int, string>>
     */
    public static function fullAccess(): array
    {
        $all = [];
        foreach (self::catalog() as $module => $config) {
            $all[$module] = array_keys($config['actions']);
        }
        return $all;
    }

    /**
     * Normaliza input vindo do formulário (permissions[module][action] => on).
     *
     * @param array $raw
     * @return array<string, array<int, string>>
     */
    public static function normalize(array $raw): array
    {
        $clean = [];

        foreach (self::catalog() as $module => $config) {
            foreach (array_keys($config['actions']) as $action) {
                $value = $raw[$module][$action] ?? null;
                if ($value === 'on' || $value === true || $value === 1 || $value === '1') {
                    $clean[$module] = $clean[$module] ?? [];
                    $clean[$module][] = $action;
                }
            }
        }

        return $clean;
    }

    /**
     * Converte estruturas antigas (apenas edit) para o novo formato (create + edit).
     *
     * @param array $permissions
     * @return array
     */
    public static function upgradeLegacy(array $permissions): array
    {
        $catalog = self::catalog();
        $moduleAliases = [
            'consignacao' => 'consignments',
            'consignacoes' => 'consignments',
            'consignment' => 'consignments',
            'produto' => 'products',
            'produtos' => 'products',
            'product' => 'products',
            'colecao' => 'collections',
            'colecoes' => 'collections',
            'categoria' => 'collections',
            'categorias' => 'collections',
            'marca' => 'brands',
            'marcas' => 'brands',
            'brand' => 'brands',
            'fornecedor' => 'vendors',
            'fornecedores' => 'vendors',
            'vendor' => 'vendors',
            'regra' => 'rules',
            'regras' => 'rules',
            'rule' => 'rules',
            'cliente' => 'customers',
            'clientes' => 'customers',
            'customer' => 'customers',
            'pessoa' => 'people',
            'pessoas' => 'people',
            'person' => 'people',
            'pedido' => 'orders',
            'pedidos' => 'orders',
            'order' => 'orders',
            'canal_venda' => 'sales_channels',
            'canal_vendas' => 'sales_channels',
            'canais_venda' => 'sales_channels',
            'canais_vendas' => 'sales_channels',
            'sales_channel' => 'sales_channels',
            'tipo_entrega' => 'delivery_types',
            'tipos_entrega' => 'delivery_types',
            'delivery_type' => 'delivery_types',
            'transportadora' => 'carriers',
            'transportadoras' => 'carriers',
            'carrier' => 'carriers',
            'padrao_identificacao' => 'voucher_identification_patterns',
            'padroes_identificacao' => 'voucher_identification_patterns',
            'cupom_identificacao' => 'voucher_identification_patterns',
            'cupons_identificacao' => 'voucher_identification_patterns',
            'voucher_identification_pattern' => 'voucher_identification_patterns',
            'metodo_pagamento' => 'payment_methods',
            'metodos_pagamento' => 'payment_methods',
            'payment_method' => 'payment_methods',
            'financeiro' => 'finance_entries',
            'financeiros' => 'finance_entries',
            'finance' => 'finance_entries',
            'finance_entry' => 'finance_entries',
            'financeiro_categoria' => 'finance_categories',
            'financeiro_categorias' => 'finance_categories',
            'finance_category' => 'finance_categories',
            'cupom_credito' => 'voucher_accounts',
            'cupons_credito' => 'voucher_accounts',
            'voucher_account' => 'voucher_accounts',
            'banco' => 'banks',
            'bancos' => 'banks',
            'bank' => 'banks',
            'conta_bancaria' => 'bank_accounts',
            'contas_bancarias' => 'bank_accounts',
            'bank_account' => 'bank_accounts',
            'maquininha' => 'payment_terminals',
            'maquininhas' => 'payment_terminals',
            'payment_terminal' => 'payment_terminals',
            'sacolinha' => 'bags',
            'sacolinhas' => 'bags',
            'bag' => 'bags',
            'usuario' => 'users',
            'usuarios' => 'users',
            'user' => 'users',
            'perfil' => 'profiles',
            'perfis' => 'profiles',
            'profile' => 'profiles',
            'ponto' => 'timeclock',
            'pontos' => 'timeclock',
            'time_clock' => 'timeclock',
            'inventario' => 'inventory',
            'painel' => 'dashboard',
        ];
        $actionAliases = [
            'list' => 'view',
            'listar' => 'view',
            'visualizar' => 'view',
            'ver' => 'view',
            'read' => 'view',
            'new' => 'create',
            'novo' => 'create',
            'cadastrar' => 'create',
            'criar' => 'create',
            'update' => 'edit',
            'editar' => 'edit',
            'atualizar' => 'edit',
            'remove' => 'delete',
            'remover' => 'delete',
            'excluir' => 'delete',
            'trash' => 'trash',
            'lixeira' => 'trash',
            'restore' => 'restore',
            'restaurar' => 'restore',
            'force_delete' => 'force_delete',
            'excluir_definitivo' => 'force_delete',
            'bulk_publish' => 'bulk_publish',
            'publicacao_lote' => 'bulk_publish',
            'batch_intake' => 'batch_intake',
            'receber_lote' => 'batch_intake',
            'report' => 'report',
            'relatorio' => 'report',
            'relatorios' => 'report',
            'payment' => 'payment',
            'pagamento' => 'payment',
            'fulfillment' => 'fulfillment',
            'envio' => 'fulfillment',
            'shipping' => 'fulfillment',
            'cancel' => 'cancel',
            'cancelar' => 'cancel',
            'open' => 'open',
            'abrir' => 'open',
            'count' => 'count',
            'contar' => 'count',
            'close' => 'close',
            'fechar' => 'close',
            'monitor' => 'monitor',
            'acompanhar' => 'monitor',
            'approve' => 'approve',
            'aprovar' => 'approve',
            'overview' => 'overview',
            'resumo' => 'overview',
            'quick_links' => 'quick_links',
            'atalhos' => 'quick_links',
            'inventory' => 'inventory',
            'suppliers' => 'suppliers',
            'fornecedores' => 'suppliers',
            'collections' => 'collections',
            'colecoes' => 'collections',
        ];
        $updated = [];

        foreach ($permissions as $module => $actions) {
            if (!is_array($actions)) {
                continue;
            }

            $moduleKey = (string) $module;
            $normalizedModule = $moduleAliases[$moduleKey] ?? $moduleKey;
            $actionList = [];

            foreach ($actions as $key => $value) {
                if (is_int($key)) {
                    if (is_string($value)) {
                        $actionList[] = $value;
                    }
                    continue;
                }

                if ($value === 'on' || $value === true || $value === 1 || $value === '1') {
                    $actionList[] = (string) $key;
                }
            }

            if (empty($actionList)) {
                continue;
            }

            if ($normalizedModule === 'catalog') {
                foreach ($actionList as $legacyAction) {
                    [$targetModule, $targetAction] = self::mapLegacyCatalogPermission((string) $legacyAction);
                    if ($targetModule === null || $targetAction === null) {
                        continue;
                    }
                    $updated[$targetModule] = array_values(array_unique(array_merge(
                        $updated[$targetModule] ?? [],
                        [$targetAction]
                    )));
                }
                continue;
            }

            $mappedActions = [];
            foreach ($actionList as $action) {
                $mappedActions[] = $actionAliases[$action] ?? $action;
            }

            $updated[$normalizedModule] = array_values(array_unique(array_merge($updated[$normalizedModule] ?? [], $mappedActions)));
        }

        foreach ($updated as $module => $actions) {
            if (!is_array($actions)) {
                continue;
            }
            if (in_array('edit', $actions, true) && !in_array('create', $actions, true) && isset($catalog[$module]['actions']['create'])) {
                $updated[$module][] = 'create';
            }
            if ($module === 'products') {
                if (in_array('view', $actions, true) && !in_array('trash', $actions, true) && isset($catalog[$module]['actions']['trash'])) {
                    $updated[$module][] = 'trash';
                }
                if (in_array('create', $actions, true) && !in_array('batch_intake', $actions, true) && isset($catalog[$module]['actions']['batch_intake'])) {
                    $updated[$module][] = 'batch_intake';
                }
            }
            if ($module === 'vendors') {
                if (in_array('view', $actions, true) && !in_array('report', $actions, true) && isset($catalog[$module]['actions']['report'])) {
                    $updated[$module][] = 'report';
                }
            }
            if ($module === 'inventory') {
                if (in_array('view', $actions, true) && !in_array('monitor', $actions, true) && isset($catalog[$module]['actions']['monitor'])) {
                    $updated[$module][] = 'monitor';
                }
            }
            // Remove ações desconhecidas
            $allowed = array_keys($catalog[$module]['actions'] ?? []);
            $updated[$module] = array_values(array_unique(array_intersect($updated[$module], $allowed)));
        }

        return $updated;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function mapLegacyCatalogPermission(string $permission): array
    {
        $permission = strtolower(trim($permission));
        if ($permission === '') {
            return [null, null];
        }

        if (str_starts_with($permission, 'brands.')) {
            return ['brands', substr($permission, strlen('brands.')) ?: null];
        }

        if (str_starts_with($permission, 'categories.')) {
            return ['collections', substr($permission, strlen('categories.')) ?: null];
        }

        return [null, null];
    }

    /**
     * Verifica permissão no array normalizado.
     */
    public static function has(array $permissions, string $module, string $action = 'view'): bool
    {
        if (isset($permissions['*']) && in_array('*', $permissions['*'], true)) {
            return true;
        }

        if (empty($permissions[$module])) {
            return false;
        }

        return in_array($action, $permissions[$module], true);
    }
}
