#!/usr/bin/env bash
set -euo pipefail

# Pequeno helper de deploy para este projeto.
# - modo --dry-run (padrão) mostra as ações sem executar
# - --apply executa migrações e seed
# - --migrate executa apenas migrações
# - --seed executa apenas seed

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
MIGRATE_PHP="$ROOT_DIR/db/migrate.php"
SEED_PHP="$ROOT_DIR/db/seed.php"
ENV_FILE="$ROOT_DIR/.env"

usage() {
  cat <<EOF
Usage: $(basename "$0") [--dry-run] [--apply|--migrate|--seed] [--help]

Helper: valida ambiente e (opcionalmente) executa migrações e seed.

Options:
  --dry-run    (default) mostra o que seria executado
  --apply      executa migrações e seed
  --migrate    executa apenas migrações
  --seed       executa apenas seed
  --help       mostra esta ajuda
EOF
}

DRY_RUN=true
DO_MIGRATE=false
DO_SEED=false

if [ "$#" -eq 0 ]; then
  DRY_RUN=true
fi

while [ "$#" -gt 0 ]; do
  case "$1" in
    --dry-run)
      DRY_RUN=true; shift ;;
    --apply)
      DRY_RUN=false; DO_MIGRATE=true; DO_SEED=true; shift ;;
    --migrate)
      DRY_RUN=false; DO_MIGRATE=true; shift ;;
    --seed)
      DRY_RUN=false; DO_SEED=true; shift ;;
    --help)
      usage; exit 0 ;;
    *)
      echo "Unknown option: $1"; usage; exit 2 ;;
  esac
done

echo "[deploy-helper] raiz do projeto: $ROOT_DIR"

echo "[deploy-helper] checando presença do PHP..."
if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php não encontrado no PATH." >&2
  exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)
echo "[deploy-helper] versão do PHP: ${PHP_VERSION:-desconhecida}"

PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1 || echo 0)
if [ -z "$PHP_MAJOR" ] || [ "$PHP_MAJOR" -lt 8 ]; then
  echo "WARN: recomendado PHP 8.0+ (detected: $PHP_VERSION)"
fi

echo "[deploy-helper] checando arquivos essenciais..."
[ -f "$MIGRATE_PHP" ] || { echo "WARN: $MIGRATE_PHP não encontrado."; }
[ -f "$SEED_PHP" ] || { echo "WARN: $SEED_PHP não encontrado."; }

if [ -f "$ENV_FILE" ]; then
  echo "[deploy-helper] .env encontrado: $ENV_FILE"
  # mostra apenas chaves relevantes
  grep -E '^(DB_|APP_|SEED_)' "$ENV_FILE" || true
else
  echo "WARN: .env não encontrado na raiz do projeto. Configure variáveis de ambiente ou crie $ENV_FILE";
fi

run_cmd() {
  echo "=> $*"
  if [ "$DRY_RUN" = false ]; then
    "$@"
  fi
}

if [ "$DO_MIGRATE" = true ]; then
  if [ -f "$MIGRATE_PHP" ]; then
    echo "[deploy-helper] executando migrations: php $MIGRATE_PHP"
    run_cmd php "$MIGRATE_PHP"
  else
    echo "ERROR: arquivo de migração não encontrado: $MIGRATE_PHP" >&2
    exit 1
  fi
fi

if [ "$DO_SEED" = true ]; then
  if [ -f "$SEED_PHP" ]; then
    echo "[deploy-helper] executando seed: php $SEED_PHP"
    run_cmd php "$SEED_PHP"
  else
    echo "ERROR: arquivo de seed não encontrado: $SEED_PHP" >&2
    exit 1
  fi
fi

if [ "$DRY_RUN" = true ]; then
  echo "[deploy-helper] modo dry-run — nenhuma ação modificadora foi executada. Use --apply para executar migrações e seed." 
else
  echo "[deploy-helper] ações concluídas." 
fi

exit 0
