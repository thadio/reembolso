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
- Validacao de anexos/timeline e documentos:
  - extensoes permitidas: `pdf`, `jpg`, `jpeg`, `png`
  - MIME permitido: `application/pdf`, `image/jpeg`, `image/png`
  - limite de tamanho: `10MB` por arquivo
- Endpoints sensiveis:
  - `POST /people/timeline/store` e `POST /people/timeline/rectify` exigem `people.manage`
  - `POST /people/documents/store` exige `people.manage`
  - `GET /people/timeline/attachment`, `GET /people/timeline/print` e `GET /people/documents/download` exigem `people.view`
  - `GET /lgpd` e `GET /lgpd/export/access-csv` exigem `lgpd.view`
  - `POST /lgpd/policies/upsert` e `POST /lgpd/retention/run` exigem `lgpd.manage`
  - `GET /users` exige `users.view`
  - `POST /users/store`, `POST /users/update`, `POST /users/delete`, `POST /users/toggle-active`, `POST /users/roles/update` e `POST /users/reset-password` exigem `users.manage`
  - `POST /users/password/update` exige sessao autenticada e validacao da senha atual

## LGPD avancado (fase 6.2)
- Trilhas de acesso sensivel gravadas em `sensitive_access_logs` (CPF e downloads de documentos/anexos).
- Politicas de retencao e anonimizacao parametrizadas em `lgpd_retention_policies`.
- Execucoes de rotina registradas em `lgpd_retention_runs` para auditoria operacional.
