# 01 - Getting Started

## Objetivo
Subir o projeto localmente e validar o fluxo basico (login + health-check).

## Pre-requisitos
- PHP 8.1+
- MySQL/Percona 5.7+
- Extensoes PHP: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`

## Setup
1. Copie o arquivo de ambiente:
```bash
cp .env.example .env
```
2. Preencha as variaveis obrigatorias em `.env`:
- `BASE_URL`
- `TIMEZONE`
- `APP_ENV`
- `APP_DEBUG`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `SEED_ADMIN_NAME`
- `SEED_ADMIN_EMAIL`
- `SEED_ADMIN_PASSWORD`

3. Rode as migrations:
```bash
php db/migrate.php
```

4. Rode o seed inicial:
```bash
php db/seed.php
```

5. Suba o servidor local:
```bash
php -S localhost:8000 -t public
```

## Validacao rapida
- `GET /health` deve retornar `200` e `status: ok`
- `GET /login` deve abrir a tela de login

## Comandos uteis
```bash
# preflight de deploy
./scripts/deploy.sh --preflight

# deploy local no servidor atual (migrate)
./scripts/deploy.sh --apply

# health-check manual
./scripts/healthcheck.sh
```
