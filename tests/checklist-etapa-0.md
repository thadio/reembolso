# Checklist de Testes — Fase 0

## Ambiente
- [ ] `.env` configurado
- [ ] `php db/migrate.php` executado sem erros
- [ ] `php db/seed.php` executado sem erros

## Segurança
- [ ] Formulário de login rejeita POST sem `_token`
- [ ] 5 tentativas inválidas bloqueiam novo login temporariamente
- [ ] Senha inválida registra `login.failed` em `audit_log`
- [ ] Login válido registra `login.success` em `audit_log`
- [ ] Logout registra `logout` em `audit_log`

## RBAC
- [ ] Usuário sem permissão recebe 403 nas rotas protegidas
- [ ] Usuário com permissão acessa `/dashboard`, `/people` e `/organs`

## Saúde e infraestrutura
- [ ] `GET /health` retorna `status=ok` com banco e storage válidos
- [ ] `storage/logs/app.log` está sendo escrito
- [ ] `storage/uploads/.htaccess` bloqueia execução de scripts

## MVP navegável
- [ ] Login renderiza corretamente em desktop e mobile
- [ ] Dashboard abre após autenticação
- [ ] Menu lateral funciona
- [ ] Telas de Pessoas e Órgãos carregam estado vazio
