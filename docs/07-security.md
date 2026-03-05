# 07 - Security

## Politica de segredos
Nunca versionar:
- `.env` e variantes com credenciais reais
- chaves privadas (`*.pem`, `*.key`, `id_*`)
- dumps/backups de banco com dados reais
- tokens de API, senhas e usuarios reais de infraestrutura

## Se houver vazamento
1. Rotacionar imediatamente:
- senha de banco
- senhas de FTP/SSH
- tokens/API keys
- contas administrativas afetadas

2. Invalidar sessoes e revisar acessos.
3. Registrar incidente e impacto.

## Historico git com segredos
Se segredo entrou no historico do Git:
- priorizar **rotacao imediata**
- avaliar limpeza de historico com `git filter-repo`
- **nao executar reescrita de historico sem alinhamento explicito do time**

## Checklist de PR (anti-vazamento)
- [ ] Nao ha credenciais em `.md`, `.php`, `.sh`, `.sql`
- [ ] `.env` nao foi alterado/adicionado
- [ ] `.env.example` usa placeholders
- [ ] Sem IP/usuario sensivel em docs
- [ ] Sem arquivos de backup/dump/chave no commit

## Seguranca aplicacional (runtime)
- Middlewares ativos: `auth`, `csrf`, `permission:*`
- Uploads armazenados fora de `public/` em `storage/uploads`
- Bloqueio de execucao em upload via `storage/uploads/.htaccess`
- Politicas configuraveis em `security_settings`:
  - senha: tamanho minimo/maximo, complexidade (upper/lower/numero/simbolo) e expiracao em dias
  - login: tentativas maximas, janela e bloqueio (`lockout`) por segundos
  - upload: limite global maximo por arquivo (MB)
- Hardening de upload aplicado em `DocumentService`, `PipelineService`, `InvoiceService` e `ProcessMetadataService`:
  - validacao de nome original seguro
  - validacao nativa de upload HTTP (`is_uploaded_file`)
  - validacao de extensao + MIME + assinatura binaria real (PDF/PNG/JPEG)
  - armazenamento com nome aleatorio e `move_uploaded_file` (sem fallback por `rename`)
- Endpoints sensiveis:
  - `POST /people/pending/status` exige `people.manage`
  - `POST /people/reimbursements/store` exige `people.manage` e CSRF (incluindo uso da calculadora automatica `use_calculator`/`calc_*`)
  - `POST /people/reimbursements/mark-paid` exige `people.manage` e CSRF
  - `POST /people/process-comments/store` exige `people.manage` e CSRF
  - `POST /people/process-comments/update` exige `people.manage` e CSRF
  - `POST /people/process-comments/delete` exige `people.manage` e CSRF
  - `POST /people/process-admin-timeline/store` exige `people.manage` e CSRF
  - `POST /people/process-admin-timeline/update` exige `people.manage` e CSRF
  - `POST /people/process-admin-timeline/delete` exige `people.manage` e CSRF
  - `POST /people/pipeline/queue/update` exige `people.manage`
  - `POST /people/pipeline/checklist/update` exige `people.manage`
  - `POST /people/timeline/store` e `POST /people/timeline/rectify` exigem `people.manage`
  - `POST /people/documents/store` exige `people.manage`; classificacao `restricted/sensitive` exige `people.documents.sensitive`
  - `POST /people/documents/intelligence/run` exige `people.manage` e CSRF (conferencia assistida por IA no Perfil 360)
  - `POST /people/documents/version/store` exige `people.manage`; versionamento de documentos `restricted/sensitive` exige `people.documents.sensitive`
  - `POST /people/import-csv` exige `people.manage` e validacao CSRF
  - `POST /organs/import-csv` exige `organs.manage` e validacao CSRF
  - `GET /people/timeline/attachment` e `GET /people/timeline/print` exigem `people.view`
  - `GET /people/documents/download` exige `people.view`; download de documentos `restricted/sensitive` exige `people.documents.sensitive`
  - `GET /people/documents/version/download` exige `people.view`; download de versoes `restricted/sensitive` exige `people.documents.sensitive`
  - `GET /people/dossier/export` exige `people.view`; inclui apenas dados/anexos permitidos ao perfil (documentos sensiveis dependem de `people.documents.sensitive`)
  - `GET /global-search` exige `people.view` e aplica filtro de sensibilidade documental (apenas `public` sem `people.documents.sensitive`)
  - `GET /invoices/payment-batches` e `GET /invoices/payment-batches/show` exigem `invoice.view`
  - `POST /invoices/payment-batches/store` exige `invoice.manage` e CSRF
  - `POST /invoices/payment-batches/final-approval/simulate` exige `invoice.manage` e CSRF
  - `POST /invoices/payment-batches/status/update` exige `invoice.manage` e CSRF
  - transicoes para status final de lote (`pago`/`cancelado`) exigem token de simulacao previa valida e nao expirada
  - `GET /reports/export/audit-zip` exige `report.view`
  - `GET /organs/audit/export` exige `audit.view`
  - `GET /security` exige `security.view`
  - `GET /ops/health-panel` exige `security.view`
  - `POST /security/update` exige `security.manage`
  - `GET /lgpd` e `GET /lgpd/export/access-csv` exigem `lgpd.view`
  - `POST /lgpd/policies/upsert` e `POST /lgpd/retention/run` exigem `lgpd.manage`
  - `GET /users` exige `users.view`
  - `POST /users/store`, `POST /users/update`, `POST /users/delete`, `POST /users/toggle-active`, `POST /users/roles/update` e `POST /users/reset-password` exigem `users.manage`
  - `POST /users/password/update` exige sessao autenticada, validacao da senha atual e respeita politica de senha ativa
  - usuarios com senha expirada sao forçados para `GET /users/password` ate concluir troca

## LGPD avancado (fase 6.2)
- Trilhas de acesso sensivel gravadas em `sensitive_access_logs` (CPF e downloads de documentos/anexos).
- Downloads negados de documentos sensiveis tambem sao rastreados (`document_download_denied`).
- Politicas de retencao e anonimizacao parametrizadas em `lgpd_retention_policies`.
- Execucoes de rotina registradas em `lgpd_retention_runs` para auditoria operacional.

## Seguranca reforcada (fase 6.3)
- Politicas centrais em `security_settings` (senha/expiracao, lockout e upload).
- Novas permissoes dedicadas: `security.view` e `security.manage`.
- Tela administrativa: `GET /security` com atualizacao via `POST /security/update`.
- Hardening aplicado em uploads de dossie, timeline, boletos/comprovantes e anexo DOU.
