# Modelo de Dados (Fases 0, 1.1, 1.2, 1.3 e 1.4)

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
- `people`
- `assignment_statuses`
- `assignments`
- `timeline_events`
- `timeline_event_attachments`

## Observações
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`
- Migrations idempotentes com `CREATE TABLE IF NOT EXISTS`
- Chaves estrangeiras aplicadas para RBAC e auditoria
- `organs` usa soft delete (`deleted_at`) e índice para busca/paginação
- `people` usa soft delete (`deleted_at`) e vínculo obrigatório ao órgão
- `assignments` guarda o status atual do pipeline por pessoa
- `timeline_events` registra eventos cronológicos da pessoa e movimentação
- `timeline_event_attachments` guarda anexos por evento da timeline, com vínculo a pessoa e trilha de upload
