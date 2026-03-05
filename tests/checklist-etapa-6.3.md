# Checklist de Testes - Fase 6.3 (Seguranca reforcada)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `027_phase6_security_hardening.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `security.view` para consultar o painel
- [ ] Usuario com permissao `security.manage` para editar politicas

## Painel de seguranca
- [ ] `GET /security` abre tela com politica atual de senha, lockout e upload
- [ ] `POST /security/update` valida limites e salva configuracao em `security_settings`
- [ ] Usuario sem `security.view` recebe `403` em `GET /security`
- [ ] Usuario sem `security.manage` recebe `403` em `POST /security/update`

## Politica de senha e expiracao
- [ ] Criacao de usuario em `/users/create` respeita regras dinamicas de senha configuradas
- [ ] `POST /users/password/update` respeita regras dinamicas de senha configuradas
- [ ] `POST /users/reset-password` respeita regras dinamicas de senha configuradas
- [ ] `password_changed_at` e `password_expires_at` sao atualizados em criacao/troca/reset
- [ ] Usuario com senha expirada e redirecionado para `/users/password` ate concluir troca

## Lockout de login configuravel
- [ ] `Auth` aplica `login_max_attempts`, `login_window_seconds` e `login_lockout_seconds`
- [ ] Excesso de tentativas retorna mensagem com tempo restante de bloqueio
- [ ] Apos periodo de lockout, novo login valido e permitido

## Hardening de upload
- [ ] Uploads de dossie (`POST /people/documents/store`) validam nome seguro, upload nativo e assinatura binaria
- [ ] Uploads de timeline (`POST /people/timeline/store` e `POST /people/timeline/rectify`) aplicam as mesmas validacoes
- [ ] Upload PDF de boleto (`POST /invoices/store`/`POST /invoices/update`) aplica hardening e bloqueia arquivos forjados
- [ ] Upload de comprovante (`POST /invoices/payments/store`) aplica hardening e bloqueia arquivos forjados
- [ ] Upload de anexo DOU (`POST /process-meta/store`/`POST /process-meta/update`) aplica hardening e bloqueia arquivos forjados
- [ ] Limite global de upload (MB) definido em `/security` e respeitado pelos modulos

## Auditoria e eventos
- [ ] `audit_log` registra `security_settings:update`
- [ ] `system_events` registra `security.settings_updated`
- [ ] Login com sucesso/falha continua registrando `auth:login.success` e `auth:login.failed`
