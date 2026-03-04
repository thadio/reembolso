# Modelo de Dados (Fase 0)

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

## Observações
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`
- Migrations idempotentes com `CREATE TABLE IF NOT EXISTS`
- Chaves estrangeiras aplicadas para RBAC e auditoria
