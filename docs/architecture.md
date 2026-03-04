# Arquitetura

## Estilo
MVC leve em PHP sem framework, com separação por camadas:
- Controllers
- Services
- Repositories
- Core (router, sessão, segurança, bootstrap)

## Fluxo da requisição
1. `public/index.php` carrega `bootstrap.php`.
2. `routes/web.php` registra rotas.
3. `Router` executa middlewares (`auth`, `guest`, `csrf`, `permission:*`).
4. Controller responde renderizando view.

## Componentes principais
- `App`: contexto central (config, DB, auth, auditoria, eventos)
- `Auth`: login/logout, permissões e rate limit
- `AuditService`: trilha de auditoria
- `EventService`: função padrão `record_event(...)`

## Compatibilidade
- PHP 8.1
- MySQL/Percona 5.7
- Sem dependência de workers/queues

## Configuração e ambiente
- `bootstrap.php` carrega `/.env` com `App\Core\Env`.
- `Config::load()` centraliza leitura de variáveis de ambiente por domínio:
  - `app`
  - `db`
  - `security`
  - `rate_limit`
  - `paths`
  - `seed`
- O `.env` do servidor é a fonte de verdade de runtime e deploy.

## Deploy e operação
- Deploy remoto padronizado em `scripts/deploy.sh` (preflight, sync, migrate, seed opcional e health check).
- Endpoint `GET /health` verifica:
  - conectividade com banco
  - escrita em `storage/logs`
  - escrita em `storage/uploads`
