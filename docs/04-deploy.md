# 04 - Deploy (Bash no servidor)

## 1) Objetivo
Padronizar deploy executado no proprio servidor via bash, sem segredos em codigo/documentacao.

## 2) Pre-requisitos
- Acesso shell ao servidor
- `git`, `php`, `curl`
- Banco de dados provisionado
- `.env` configurado no servidor (fora do git)

## 3) Setup de segredos (fora do git)
1. Crie `.env` a partir de `.env.example`:
```bash
cp .env.example .env
```
2. Preencha apenas no servidor com credenciais reais.
3. Proteja o arquivo:
```bash
chmod 600 .env
```

## 4) Primeiro deploy (recomendado)
```bash
# 1) obter codigo
cd /var/www

git clone <REPO_URL_PRIVADO> reembolso
cd reembolso

# 2) configurar ambiente
cp .env.example .env
# editar .env com valores reais
chmod 600 .env

# 3) validar preflight
./scripts/deploy.sh --preflight

# 4) aplicar deploy (inclui migrations)
./scripts/deploy.sh --apply --with-seed

# 5) validar saude
./scripts/healthcheck.sh
```

## 5) Deploy recorrente
```bash
cd /var/www/reembolso
./scripts/deploy.sh --apply
./scripts/healthcheck.sh
```

Comportamento do `--apply`:
1. preflight
2. `git fetch` + `git pull --ff-only` (padrao)
3. composer install (somente se `composer.json` existir)
4. garantia de pastas de runtime (`storage/logs`, `storage/uploads`)
5. migration (`php db/migrate.php`)
6. restart opcional (se `DEPLOY_RESTART_COMMAND` estiver definido)
7. health-check

Nota:
- `--apply` com pull habilitado exige working tree limpa no servidor.
- Se houver alteracoes locais intencionais no servidor, use `--skip-pull`.

## 6) Upload FTP via Visual Studio Code
Quando o fluxo for upload por FTP (sem `git pull` no servidor), use o script:

```bash
./scripts/ftp-upload.sh --dry-run
./scripts/ftp-upload.sh
```

Tasks prontas no VS Code:
- `FTP Upload: Dry Run`
- `FTP Upload: Push`
- `FTP Upload: Push + Delete`

Arquivo de tarefas:
- `.vscode/tasks.json`

Variaveis necessarias no `.env`:
- `FTP_HOST`
- `FTP_PORT`
- `FTP_USER`
- `FTP_PASS`
- `FTP_REMOTE_ROOT`

Variaveis de TLS/SSL (opcionais):
- `FTP_SSL_ALLOW` (default `0`)
- `FTP_SSL_FORCE` (default `0`)
- `FTP_SSL_VERIFY` (default `1`)

## 7) Flags importantes dos scripts
```bash
./scripts/deploy.sh --help
./scripts/ftp-upload.sh --help
```

Mais usadas:
- `--preflight`: valida ambiente
- `--apply`: fluxo completo
- `--migrate`: roda apenas migration
- `--seed`: roda apenas seed
- `--skip-pull`: nao executa `git pull`
- `--skip-healthcheck`: nao executa health-check ao final
- `--dry-run`: simula comandos (deploy e FTP)

## 8) Rollback
Rollback de codigo:
```bash
cd /var/www/reembolso
./scripts/rollback.sh --ref <git_ref>
```

Exemplo:
```bash
./scripts/rollback.sh --ref v1.4.2
```

Depois do rollback:
```bash
./scripts/healthcheck.sh
```

## 9) Troubleshooting rapido
- Erro de conexao DB: revisar `DB_*` em `.env`
- Health degradado por storage: revisar permissoes de `storage/logs` e `storage/uploads`
- `git pull` bloqueado: verificar branch, conflitos locais e permissao no repositorio
- Erro FTP de autenticacao: validar `FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASS`
- Erro FTP de caminho: validar `FTP_REMOTE_ROOT` (diretorio remoto existente)

Detalhamento completo em `docs/06-troubleshooting.md`.
