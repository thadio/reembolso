# Runbooks

## Subida inicial (local ou servidor novo)
1. Configurar `.env` com base no `.env.example`.
2. Executar `php db/migrate.php`.
3. Executar `php db/seed.php`.
4. Validar `GET /health`.
5. Validar login em `/login`.

## Deploy remoto com script
1. Preflight:
   - `./scripts/deploy.sh --preflight`
2. Simulacao:
   - `./scripts/deploy.sh --dry-run --remote-apply --remote-health`
3. Execucao:
   - `./scripts/deploy.sh --remote-apply --remote-health`
4. Primeiro deploy (com seed):
   - `./scripts/deploy.sh --remote-apply --with-seed --remote-health`

## Incidentes comuns

### 1) Erro de conexao com banco
Sintomas:
- `GET /health` retorna `503`.
- Logs indicam erro PDO.

Resposta:
1. Revisar `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`.
2. Testar conexao no servidor remoto.
3. Confirmar privilegios do usuario de banco.

### 2) Erro de escrita em logs/uploads
Sintomas:
- `GET /health` degradado em `storage_logs` ou `storage_uploads`.

Resposta:
1. Ajustar permissoes de `storage/`, `storage/logs/`, `storage/uploads/`.
2. Garantir dono/grupo compativel com processo PHP.
3. Repetir health check.

### 3) Falha de autenticacao SSH no deploy
Sintomas:
- `remote-sync` falha com `Permission denied`.

Resposta:
1. Confirmar `DEPLOY_SSH_HOST`, `DEPLOY_SSH_PORT`, `DEPLOY_SSH_USER`.
2. Se usar senha, validar `DEPLOY_SSH_PASS` e `sshpass`.
3. Se usar chave, validar chave publica autorizada no host.

### 4) Seed executado sem acesso ao admin
Sintomas:
- Usuario admin nao consegue login.

Resposta:
1. Confirmar `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD` no `.env` remoto.
2. Rodar `php db/seed.php` novamente (idempotente para papeis/permissoes).
3. Validar se usuario admin existe e esta ativo na tabela `users`.

### 5) Login bloqueado por rate limit
Sintomas:
- Tentativas de login recusadas apos multiplos erros.

Resposta:
1. Aguardar janela de bloqueio (`LOGIN_DECAY_SECONDS`, default `900`).
2. Reduzir erros de senha e confirmar credenciais corretas no `.env`.
