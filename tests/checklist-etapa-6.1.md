# Checklist de Testes - Fase 6.1 (Admin de usuarios e acessos)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `024_phase6_user_admin_access.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `users.manage` para administrar contas

## CRUD de usuarios
- [ ] `GET /users` abre listagem com filtros e paginacao
- [ ] `GET /users/create` abre formulario de cadastro
- [ ] `POST /users/store` cria usuario com nome, email, senha e pelo menos um papel
- [ ] `GET /users/show?id={id}` exibe dados da conta, papeis e permissoes efetivas
- [ ] `GET /users/edit?id={id}` abre formulario de edicao
- [ ] `POST /users/update` salva alteracoes cadastrais e papeis
- [ ] `POST /users/delete` remove conta por exclusao logica

## Papeis e permissoes via UI
- [ ] `GET /users/roles` lista papeis e permissoes disponiveis
- [ ] `POST /users/roles/update` atualiza `role_permissions` para o papel selecionado
- [ ] Mudancas de permissao refletem nas permissoes efetivas dos usuarios vinculados ao papel

## Ativacao e desativacao de conta
- [ ] `POST /users/toggle-active` alterna conta ativa/inativa na listagem
- [ ] Usuario nao consegue desativar a propria conta
- [ ] Conta desativada nao autentica no login

## Fluxo de senha (troca + reset)
- [ ] `GET /users/password` abre formulario de troca da propria senha
- [ ] `POST /users/password/update` exige senha atual valida
- [ ] Troca de senha exige confirmacao e politica minima (8+ com letra e numero)
- [ ] `POST /users/reset-password` redefine senha de outro usuario via tela de detalhe

## Seguranca de acesso
- [ ] Usuario sem `users.view` recebe `403` em `GET /users`
- [ ] Usuario sem `users.manage` recebe `403` em create/edit/delete/toggle/roles/reset

## Auditoria e eventos
- [ ] `audit_log` registra `user:create`, `user:update`, `user:delete` e `user:status`
- [ ] `audit_log` registra `user_password:change_own` e `user_password:admin_reset`
- [ ] `audit_log` registra `role:update_permissions`
- [ ] `system_events` registra `user.created`, `user.updated`, `user.deleted`, `user.status_changed`
- [ ] `system_events` registra `user.password_changed`, `user.password_reset` e `role.permissions_updated`
