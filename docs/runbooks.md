# Runbooks

## Subida inicial
1. Configurar `.env`.
2. Rodar `php db/migrate.php`.
3. Rodar `php db/seed.php`.
4. Validar `GET /health`.

## Problemas comuns
- Erro de conexão DB:
  - revisar host/porta/usuário/senha no `.env`
  - validar acesso remoto/liberação de IP no cPanel
- Erro de escrita em logs/uploads:
  - ajustar permissões de `storage/`
- Falha de login por bloqueio:
  - aguardar janela de rate limit (default 15 min)

## Deploy HostGator
- Apontar DocumentRoot para `public/`
- Manter `storage/` persistente entre deploys
- Executar migrations após upload
