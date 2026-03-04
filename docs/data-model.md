# Modelo de Dados (Fases 0 e 1.1)

## Tabelas
- `users`
- `roles`
- `permissions`
- `role_permissions`
- `user_roles`
- `audit_log`
- `document_types`
- `timeline_event_types`
- `modalities`
- `system_events`
- `login_attempts`
- `migrations`
- `organs`

## Observações
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`
- Migrations idempotentes com `CREATE TABLE IF NOT EXISTS`
- Chaves estrangeiras aplicadas para RBAC e auditoria
- `organs` usa soft delete (`deleted_at`) e índice para busca/paginação
