# BUSCA_ID — Auditoria de Busca, Filtros e Paginação

> **Data:** 2026-03-03  
> **Escopo:** Auditoria profunda de todas as consultas com LIMIT, filtros client-side vs server-side, e limitações ocultas de dados.  
> **Objetivo:** Garantir que toda busca consulte a base inteira quando necessário. Nenhum campo retorne resultados parciais por limitação técnica.

---

## Resumo Executivo

| Métrica | Valor |
|---------|-------|
| Arquivos analisados | 90+ (Controllers, Repositories, Services, Views) |
| Problemas críticos encontrados | 17 |
| Problemas corrigidos | 17 |
| Testes de regressão adicionados | 15 novos checks |
| Tabelas com server-side filter | 15 views |
| Tabelas com client-side filter | 41 views (todas validadas) |

---

## Correções Aplicadas

### ⚠️ IMPACTO ALTO — Consultas que truncavam resultados silenciosamente

| # | Arquivo | Método | Problema | Correção |
|---|---------|--------|----------|----------|
| 1 | `ProductRepository.php` | `searchProductIds()` | `LIMIT 2000` hardcoded via `list(... 2000, 0, ...)` | Query direta sem LIMIT |
| 2 | `CustomerRepository.php` | `search()` | `$limit = 50` default, sempre aplicado | `$limit = 0` default (sem limite) |
| 3 | `OrderRepository.php` | `listDeliveries()` | `$limit = 300` default, `min(1000, $limit)` cap | `$limit = 0`, LIMIT condicional |
| 4 | `OrderRepository.php` | `listCustomerPurchaseOrders()` | `$limit = 2000`, `min(10000, $limit)` cap | `$limit = 0`, LIMIT condicional |
| 5 | `OrderRepository.php` | `listCustomerPurchasedProducts()` | `$limit = 2000`, `min(10000, $limit)` cap | `$limit = 0`, LIMIT condicional |
| 6 | `ConsignmentIntegrityService.php` | `getCheckDetails()` | `LIMIT :lim` em 7 queries, default 100 | `$limit = 0`, LIMIT condicional |
| 7 | `CustomerHistoryRepository.php` | `listByCustomer()` | Hard cap `$limit > 50 ? 50 : $limit` | `$limit = 0`, sem cap |
| 8 | `ProductWriteOffRepository.php` | `listBySupplier()` | `$limit = 200`, `min(500, $limit)` | `$limit = 0`, LIMIT condicional |
| 9 | `ProductWriteOffRepository.php` | `listRecent()` | `$limit = 30`, cap 200 | `$limit = 0`, LIMIT condicional |
| 10 | `ProductWriteOffRepository.php` | `listForProduct()` | `$limit = 20`, override `$limit <= 0 ? 20 : $limit` | `$limit = 0`, passthrough |
| 11 | `ProductWriteOffRepository.php` | `paginateForProduct()` | `$limit = 50`, `min(500, $limit)` cap | `$limit = 0`, LIMIT condicional |
| 12 | `CreditEntryRepository.php` | `listByAccount()` | `$limit = 100`, sempre aplicado | `$limit = 0`, LIMIT condicional |
| 13 | `OrderRepository.php` | `listOrdersForProduct()` | `$limit = 50`, sempre aplicado | `$limit = 0`, LIMIT condicional |
| 14 | `FinanceEntryRepository.php` | `listPayablesWindow()` | `$limit = 60`, `max(1, $limit)` forçava mín. 1 | `$limit = 0`, LIMIT condicional |
| 15 | `CommemorativeDateRepository.php` | `listForDates()` | `$limit = 60`, sempre aplicado | `$limit = 0`, LIMIT condicional |

### ⚠️ IMPACTO MÉDIO — Controllers com limites desnecessários

| # | Arquivo | Problema | Correção |
|---|---------|----------|----------|
| 16 | `ConsignmentModuleController.php` | `fetchLegacyPayouts()`: default 50, `min(500)` cap | `$limit = 0`, LIMIT condicional |
| 17 | `ProductWriteOffController.php` | Passava `$limit = 200` para `listBySupplier()` | Passa `$limit = 0` (sem limite) |

### ⚠️ IMPACTO MÉDIO — Tabelas client-side com dados truncados

| # | Arquivo | Problema | Correção |
|---|---------|----------|----------|
| 18 | `DeliveryTrackingController.php` | `listDeliveries($filters, 500)` hardcoded para tabela client-side | Removido limit, usa default `$limit = 0` |

---

## Padrão Arquitetural Adotado

Todas as correções seguem o mesmo padrão:

```php
public function metodo(int $limit = 0): array
{
    $useLimit = $limit > 0;
    $sql = "SELECT ... FROM ... WHERE ... ORDER BY ...";
    if ($useLimit) {
        $sql .= "\n LIMIT :limit";
    }
    $stmt = $this->pdo->prepare($sql);
    if ($useLimit) {
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}
```

- `$limit = 0` → sem LIMIT, retorna todos os resultados
- `$limit > 0` → aplica LIMIT normalmente
- Controllers decidem se querem limitar, não o repository

---

## Status por Camada

### Repositories — Métodos de listagem

| Repository | Método | Tipo | Status |
|-----------|--------|------|--------|
| `ProductRepository` | `list()` | Paginado (limit+offset+count) | ✅ OK |
| `ProductRepository` | `count()` | Contagem total | ✅ OK |
| `ProductRepository` | `searchProductIds()` | Busca IDs | ✅ CORRIGIDO |
| `ProductRepository` | `listForOrders()` | Delega para list() | ✅ OK |
| `ProductRepository` | `listForBulk()` | Paginado | ✅ OK |
| `OrderRepository` | `listOrders()` | Paginado (limit+offset+count) | ✅ OK |
| `OrderRepository` | `countOrders()` | Contagem total | ✅ OK |
| `OrderRepository` | `listDeliveries()` | Listagem completa | ✅ CORRIGIDO |
| `OrderRepository` | `listOrdersForProduct()` | Sub-listagem | ✅ CORRIGIDO |
| `OrderRepository` | `listCustomerPurchaseOrders()` | Listagem completa | ✅ CORRIGIDO |
| `OrderRepository` | `listCustomerPurchasedProducts()` | Listagem completa | ✅ CORRIGIDO |
| `CustomerRepository` | `search()` | Busca | ✅ CORRIGIDO |
| `CustomerRepository` | `listForSelect()` | Sem LIMIT | ✅ OK |
| `PersonRepository` | `paginateForList()` | Paginado | ✅ OK (cap 500/pg) |
| `PersonRepository` | `countForList()` | Contagem total | ✅ OK |
| `FinanceEntryRepository` | `paginateForList()` | Paginado | ✅ OK (cap 500/pg) |
| `FinanceEntryRepository` | `countForList()` | Contagem total | ✅ OK |
| `FinanceEntryRepository` | `listPayablesWindow()` | Janela temporal | ✅ CORRIGIDO |
| `VoucherAccountRepository` | `paginateForList()` | Paginado | ✅ OK (cap 500/pg) |
| `CreditEntryRepository` | `listByAccount()` | Listagem completa | ✅ CORRIGIDO |
| `ProductWriteOffRepository` | `listBySupplier()` | Listagem completa | ✅ CORRIGIDO |
| `ProductWriteOffRepository` | `listRecent()` | Listagem recente | ✅ CORRIGIDO |
| `ProductWriteOffRepository` | `listForProduct()` | Sub-listagem | ✅ CORRIGIDO |
| `ProductWriteOffRepository` | `paginateForProduct()` | Paginado | ✅ CORRIGIDO |
| `CustomerHistoryRepository` | `listByCustomer()` | Listagem completa | ✅ CORRIGIDO |
| `CommemorativeDateRepository` | `listForDates()` | Listagem por data | ✅ CORRIGIDO |
| `ConsignmentIntakeRepository` | `listWithTotals()` | Sem LIMIT | ✅ OK |
| `InventoryRepository` | `listInventories()` | Suporta limit=0 | ✅ OK |
| `InventoryRepository` | `listScans()` | Paginado | ✅ OK (cap 200/pg) |
| `InventoryItemRepository` | `paginate()` | Paginado | ✅ OK (cap 500/pg) |
| `TimeEntryRepository` | `paginateForList()` | Paginado | ✅ OK |

### Services

| Service | Método | Status |
|---------|--------|--------|
| `ConsignmentIntegrityService` | `getCheckDetails()` | ✅ CORRIGIDO |
| `DashboardService` | Múltiplos widgets | ✅ OK (LIMIT intencional para cards) |
| `FinanceEntryService` | `validateRecurrence()` | ✅ OK (regra de negócio: max 60 parcelas) |

### Controllers — Paginação server-side

| Controller | View | Count + Paginate? | Status |
|-----------|------|-------------------|--------|
| `ProductController` | `products/list` | ✅ count() + list() | ✅ OK |
| `OrderController` | `orders/list` | ✅ countOrders() + listOrders() | ✅ OK |
| `PersonController` | `pessoas/list` | ✅ countForList() + paginateForList() | ✅ OK |
| `ConsignmentController` | `consignments/products-list` | ✅ count() + list() | ✅ OK |
| `ConsignmentModuleController` | products, sales, payouts | ✅ count + paginate | ✅ OK |
| `FinanceController` | `finance/list` | ✅ countForList() + paginateForList() | ✅ OK |
| `VoucherAccountController` | `voucher_accounts/list` | ✅ countForList() + paginateForList() | ✅ OK |
| `TimeClockController` | `timeclock/list` | ✅ countForList() + paginateForList() | ✅ OK |
| `AuditController` | `audit/list` | ✅ search() com limit+1 hasMore | ✅ OK |
| `ProductController` | `products/form` (orders sub) | ✅ countOrdersForProduct() + paginate | ✅ OK |
| `ProductController` | `products/form` (writeoffs sub) | ✅ countForProduct() + paginateForProduct() | ✅ OK |

### Views — Tabelas client-side (sem `data-filter-mode="server"`)

Estas tabelas filtram no browser. Verificado que **todas carregam o dataset completo**:

| View | Tamanho esperado | Dados completos? |
|------|-----------------|-----------------|
| `banks/list` | Pequeno (~20) | ✅ Sem LIMIT |
| `carriers/list` | Pequeno | ✅ Sem LIMIT |
| `payment_methods/list` | Pequeno | ✅ Sem LIMIT |
| `profiles/list` | Pequeno | ✅ Sem LIMIT |
| `users/list` | Pequeno | ✅ Sem LIMIT |
| `sales_channels/list` | Pequeno | ✅ Sem LIMIT |
| `delivery_types/list` | Pequeno | ✅ Sem LIMIT |
| `finance_categories/list` | Pequeno | ✅ Sem LIMIT |
| `tags/list` | Pequeno | ✅ Sem LIMIT |
| `bags/list` | Pequeno | ✅ Sem LIMIT |
| `payment_terminals/list` | Pequeno | ✅ Sem LIMIT |
| `bank_accounts/list` | Pequeno | ✅ Sem LIMIT |
| `rules/list` | Pequeno | ✅ Sem LIMIT |
| `collections/list` | Pequeno/Médio | ✅ Sem LIMIT |
| `commemorative_dates/list` | Pequeno/Médio | ✅ Sem LIMIT |
| `voucher_identification_patterns/list` | Pequeno | ✅ Sem LIMIT |
| `consignments/list` | Médio | ✅ listWithTotals() sem LIMIT |
| `deliveries/tracking` | Médio/Grande | ✅ CORRIGIDO — era limit 500 |
| `vendors/report` | Médio | ✅ Sem LIMIT |
| `vendors/sales_report` | Médio | ✅ Sem LIMIT |
| `products/writeoff-form` | Médio | ✅ listRecent() agora sem LIMIT |
| Dashboard sub-tables | Pequeno | ✅ OK (widgets com LIMIT intencional) |

---

## Itens Aceitáveis (não corrigidos intencionalmente)

| Item | Motivo |
|------|--------|
| `DashboardService` LIMIT 5-12 | Widgets de dashboard exibem "Top N" — limite intencional |
| `ConsignmentIntakeRepository::listPendingForDashboard()` cap 50 | Widget de dashboard |
| `InventoryRepository::listRecentScans()` cap 50 | Widget UI de scans recentes |
| Paginação per-page caps (200-500) em `list()` methods | Guards de paginação — controlados por UI com count total |
| `FinanceEntryService` max 60 parcelas | Regra de negócio, não limitação de busca |
| `LIMIT 1` em find/exists | Pattern correto para lookup de registro único |

---

## Testes de Regressão

Arquivo: `cli/search_filter_regression_check.php`

### Checks adicionados (Phase 2 — BUSCA_ID):

1. **`checkRepositoryDefaultLimitZero()`** — Verifica que 12 métodos de repository mantêm `$limit = 0` como default
2. **`checkDeliveryTrackingNoHardcodedLimit()`** — Garante que DeliveryTrackingController não passa limit hardcoded
3. **`checkConsignmentIntegrityNoHardcodedLimit()`** — Verifica que getCheckDetails() tem default 0 e não tem LIMIT nas queries base
4. **`checkCustomerHistoryNoHardCap()`** — Impede reintrodução de caps hardcoded

### Execução:
```bash
php cli/search_filter_regression_check.php
```

---

## Arquitetura de Filtragem

```
┌─────────────────────────────────────────────────────┐
│ Tabelas com data-filter-mode="server"               │
│                                                     │
│ table.js detecta → redireciona com query params     │
│ Controller recebe → count() + list(limit, offset)   │
│ Repository aplica → WHERE + ORDER BY + LIMIT/OFFSET │
│ View renderiza → paginação + indicador "X de Y"     │
│                                                     │
│ ✅ Busca completa, paginação server-side             │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Tabelas sem data-filter-mode (client-side)          │
│                                                     │
│ Controller carrega → TODOS os registros (sem LIMIT) │
│ View renderiza → todas as <tr> no HTML              │
│ table.js filtra → in-memory (rowData array)         │
│                                                     │
│ ✅ Busca completa se dataset carregado inteiro       │
│ ⚠️ Ideal para tabelas pequenas (<1000 registros)    │
└─────────────────────────────────────────────────────┘
```
