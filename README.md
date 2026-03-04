# Reembolso

Aplicação web em PHP para gestão de movimentação de força de trabalho, orçamento (CDO) e reembolsos.

## Status
- Fase implementada: **Fase 0 (Etapas 0.1 a 0.3)**
- MVP navegável: login, menu, dashboard, listas vazias de Pessoas e Órgãos

## Requisitos
- PHP 8.1+
- MySQL/Percona 5.7+

## Setup local
1. Copie `.env.example` para `.env` e ajuste credenciais.
2. Execute migrations:
```bash
php db/migrate.php
```
3. Execute seed inicial:
```bash
php db/seed.php
```
4. Suba servidor PHP apontando para `public/`:
```bash
php -S localhost:8000 -t public
```
5. Acesse `http://localhost:8000/login`.

## Credenciais iniciais
- Definidas por `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD` no `.env`.
- Altere a senha após o primeiro login.

## Rotas principais
- `GET /health`
- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /dashboard`
- `GET /people`
- `GET /organs`

## Estrutura
- `public/`: front controller e assets
- `app/Controllers`: controladores
- `app/Core`: bootstrap interno, roteador, sessão, CSRF, auth
- `app/Services`: auditoria e eventos
- `app/Repositories`: acesso a dados
- `db/migrations`: scripts SQL idempotentes
- `db/seed.php`: seed RBAC/catálogos/admin
- `storage/`: logs e uploads
