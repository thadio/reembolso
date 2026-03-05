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
  - `POST /people/pipeline/queue/update` exige `people.manage`
  - `POST /people/pipeline/checklist/update` exige `people.manage`
  - `POST /people/timeline/store` e `POST /people/timeline/rectify` exigem `people.manage`
  - `POST /people/documents/store` exige `people.manage`; classificacao `restricted/sensitive` exige `people.documents.sensitive`
  - `POST /people/import-csv` exige `people.manage` e validacao CSRF
  - `GET /people/timeline/attachment` e `GET /people/timeline/print` exigem `people.view`
  - `GET /people/documents/download` exige `people.view`; download de documentos `restricted/sensitive` exige `people.documents.sensitive`
  - `GET /security` exige `security.view`
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
