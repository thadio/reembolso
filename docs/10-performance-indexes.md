# 10 - Performance Indexes

Guia de validacao dos indices de alto volume da etapa 7.2.

## Escopo
Migration: `db/migrations/014_phase7_performance_indexes.sql`

Tabelas cobertas:
- `people`
- `timeline_events`
- `documents`
- `cost_plans`
- `cost_plan_items`
- `reimbursement_entries`
- `audit_log`
- `cdos`
- `cdo_people`

## Aplicacao
```bash
php db/migrate.php
```

## Conferencia rapida
```sql
SHOW INDEX FROM people;
SHOW INDEX FROM timeline_events;
SHOW INDEX FROM documents;
SHOW INDEX FROM cost_plans;
SHOW INDEX FROM cost_plan_items;
SHOW INDEX FROM reimbursement_entries;
SHOW INDEX FROM audit_log;
SHOW INDEX FROM cdos;
SHOW INDEX FROM cdo_people;
```

## EXPLAIN recomendado
Use `EXPLAIN` nas consultas de:
- dashboard (resumos de `people`, `reimbursement_entries`, `cdos`);
- auditoria paginada (`audit_log` com ordenacao por data);
- timeline recente (`timeline_events` ordenada por data);
- resumo/listagem de reembolso por pessoa.

Objetivo: reduzir `rows` estimadas e evitar `filesort`/`temporary` quando possivel.
