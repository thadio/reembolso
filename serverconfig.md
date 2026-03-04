# serverconfig.md

## Ambiente alvo
- Hospedagem compartilhada HostGator (cPanel)
- Apache 2.4
- PHP 8.1
- Percona/MySQL 5.7
- Sem workers/daemons permanentes (modelo request/response + cron)

## Compatibilidade minima
- PHP 8.1+ com extensoes: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`
- Banco em `utf8mb4` / `utf8mb4_unicode_ci`
- Aplicacao sem dependencia de Redis, filas ou processos longos

## Estrutura recomendada
- DocumentRoot apontando para `public/`
- Codigo da aplicacao fora da pasta publica quando possivel
- `storage/logs` e `storage/uploads` com permissao de escrita
- Uploads fora de `public/` (ja atendido por `storage/uploads`)

## Configuracao essencial
- `.env` no servidor e fora de versionamento
- `APP_ENV=production`
- `APP_DEBUG=0`
- `TIMEZONE=America/Sao_Paulo`
- `display_errors=Off`

## Deploy recomendado
1. Rodar preflight:
   - `./scripts/deploy.sh --preflight`
2. Simular:
   - `./scripts/deploy.sh --dry-run --remote-apply --remote-health`
3. Executar:
   - `./scripts/deploy.sh --remote-apply --remote-health`
4. Primeiro deploy (com seed):
   - `./scripts/deploy.sh --remote-apply --with-seed --remote-health`

## Seguranca minima obrigatoria
- CSRF ativo em formularios
- Prepared statements via PDO
- Sessoes com cookie `HttpOnly` e `SameSite=Lax`
- Uploads com bloqueio de execucao por `.htaccess`
- Segredos somente em `.env` (nunca em repositorio)
