# Segurança

## Controles implementados (Fase 0)
- Autenticação com senha hash (`password_hash`/`password_verify`)
- Sessão segura (`HttpOnly`, `SameSite=Lax`, `use_strict_mode`)
- CSRF obrigatório em formulários POST
- SQL com prepared statements (PDO)
- Rate limit de login por IP+usuário
- RBAC por permissão (`permission:*` em rotas)
- Auditoria de login/logout e eventos críticos

## Controle de acesso aplicado
### Órgãos (Fase 1.1)
- `organs.view`: acesso à lista e detalhe
- `organs.manage`: criar, editar e excluir

### Pessoas (Fases 1.2, 1.3 e 1.4)
- `people.view`: acesso à lista e Perfil 360
- `people.manage`: criar, editar, excluir, avançar pipeline e registrar/retificar eventos da timeline
- `people.cpf.full`: visualizar CPF completo (sem essa permissão, CPF aparece mascarado)

### Endpoints da timeline (Etapa 1.4)
- `POST /people/timeline/store`: exige `auth`, `permission:people.manage` e `csrf`
- `POST /people/timeline/rectify`: exige `auth`, `permission:people.manage` e `csrf`
- `GET /people/timeline/attachment`: exige `auth` e `permission:people.view`
- `GET /people/timeline/print`: exige `auth` e `permission:people.view`

## Rastreabilidade
- Mudanças de status no pipeline registram:
  - `audit_log`
  - `system_events`
  - `timeline_events`

## Uploads
- Diretório `storage/uploads/` fora de `public/`
- `.htaccess` bloqueando execução de scripts
- Upload de anexos da timeline com whitelist:
  - extensões: `pdf`, `jpg`, `jpeg`, `png`
  - MIME: `application/pdf`, `image/jpeg`, `image/png`
  - tamanho máximo: `10MB` por arquivo

## LGPD (base)
- Mascaramento de CPF em listagens para perfis sem permissão sensível
- Base de permissões preparada para restringir visualização de dados sensíveis
