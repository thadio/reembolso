# Segurança

## Controles implementados (Fase 0)
- Autenticação com senha hash (`password_hash`/`password_verify`)
- Sessão segura (`HttpOnly`, `SameSite=Lax`, `use_strict_mode`)
- CSRF obrigatório em formulários POST
- SQL com prepared statements (PDO)
- Rate limit de login por IP+usuário
- RBAC por permissão (`permission:*` em rotas)
- Auditoria de login/logout e eventos críticos

## Uploads
- Diretório `storage/uploads/` fora de `public/`
- `.htaccess` bloqueando execução de scripts

## LGPD (base)
- Mascaramento de CPF disponível via helper `mask_cpf()`
- Base de permissões preparada para restringir visualização de dados sensíveis
