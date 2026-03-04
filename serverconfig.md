# serverconfig.md

## Ambiente alvo
- Hospedagem compartilhada HostGator (cPanel)
- Apache 2.4
- PHP 8.1
- Percona/MySQL 5.7

## Estrutura recomendada no cPanel
- `public_html/` ou subdomínio apontando para `public/`
- Código da aplicação fora da pasta pública quando possível
- `storage/` com permissão de escrita para logs/uploads

## Configuração essencial
- Usar `.env` local no servidor com credenciais reais
- Não versionar `.env`
- Habilitar `display_errors=Off` em produção
- Garantir timezone `America/Sao_Paulo`

## Deploy rápido
1. Subir arquivos via Git/FTP/rsync
2. Ajustar DocumentRoot para `public/`
3. Rodar `php db/migrate.php`
4. Rodar `php db/seed.php`
5. Validar `GET /health`

## Segurança mínima
- CSRF ativo em formulários
- Prepared statements via PDO
- Sessões com cookie HttpOnly e SameSite=Lax
- Uploads fora de `public/` e com `.htaccess` bloqueando execução
