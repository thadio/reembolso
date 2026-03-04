#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
ENV_FILE="$ROOT_DIR/.env"
MIGRATE_PHP="$ROOT_DIR/db/migrate.php"
SEED_PHP="$ROOT_DIR/db/seed.php"
HEALTHCHECK_SCRIPT="$ROOT_DIR/scripts/healthcheck.sh"

DRY_RUN=false
RUN_PREFLIGHT=true
RUN_APPLY=false
RUN_MIGRATE=false
RUN_SEED=false
RUN_HEALTHCHECK=false
WITH_SEED=false
SKIP_PULL=false
SKIP_HEALTHCHECK=false
SKIP_COMPOSER=false

log() {
  printf '[deploy] %s\n' "$*"
}

warn() {
  printf '[deploy][warn] %s\n' "$*" >&2
}

fail() {
  printf '[deploy][error] %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage: ./scripts/deploy.sh [options]

Options:
  --preflight         Executa apenas validacoes (default)
  --apply             Fluxo completo de deploy no servidor atual
  --migrate           Executa apenas migration
  --seed              Executa apenas seed
  --healthcheck       Executa apenas health-check
  --with-seed         Junto com --apply, executa seed apos migration

  --skip-pull         Nao executa git fetch/pull
  --skip-composer     Nao executa composer install
  --skip-healthcheck  Nao executa health-check ao final do --apply
  --no-preflight      Pula validacoes iniciais
  --dry-run           Apenas mostra comandos, sem executar
  --help              Mostra esta ajuda

Examples:
  ./scripts/deploy.sh --preflight
  ./scripts/deploy.sh --apply
  ./scripts/deploy.sh --apply --with-seed
  ./scripts/deploy.sh --migrate
  ./scripts/deploy.sh --healthcheck
USAGE
}

trim() {
  local value="$1"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf '%s' "$value"
}

strip_quotes() {
  local value="$1"
  local length="${#value}"

  if [ "$length" -lt 2 ]; then
    printf '%s' "$value"
    return
  fi

  if [ "${value:0:1}" = '"' ] && [ "${value: -1}" = '"' ]; then
    printf '%s' "${value:1:length-2}"
    return
  fi

  if [ "${value:0:1}" = "'" ] && [ "${value: -1}" = "'" ]; then
    printf '%s' "${value:1:length-2}"
    return
  fi

  printf '%s' "$value"
}

load_env() {
  if [ ! -f "$ENV_FILE" ]; then
    return
  fi

  local line raw key value
  while IFS= read -r line || [ -n "$line" ]; do
    raw="${line%$'\r'}"
    raw="$(trim "$raw")"

    if [ -z "$raw" ] || [[ "$raw" == \#* ]]; then
      continue
    fi

    if [[ "$raw" != *=* ]]; then
      continue
    fi

    key="$(trim "${raw%%=*}")"
    value="$(trim "${raw#*=}")"
    value="$(strip_quotes "$value")"

    if [[ ! "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then
      continue
    fi

    export "$key=$value"
  done < "$ENV_FILE"
}

require_command() {
  local cmd="$1"
  command -v "$cmd" >/dev/null 2>&1 || fail "comando obrigatorio nao encontrado: $cmd"
}

require_file() {
  local file="$1"
  [ -f "$file" ] || fail "arquivo obrigatorio nao encontrado: $file"
}

require_env() {
  local key="$1"
  [ -n "${!key:-}" ] || fail "variavel obrigatoria ausente: $key"
}

run_cmd() {
  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] $*"
    return
  fi
  "$@"
}

check_php_version() {
  local version major
  version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;')"
  major="$(echo "$version" | cut -d. -f1)"
  log "PHP detectado: $version"

  if [ "$major" -lt 8 ]; then
    fail "PHP 8+ e obrigatorio para este projeto"
  fi
}

preflight() {
  require_command php
  require_file "$MIGRATE_PHP"
  require_file "$SEED_PHP"
  check_php_version

  if [ "$RUN_APPLY" = true ] && [ "$SKIP_PULL" = false ]; then
    require_command git
    git -C "$ROOT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "repositorio git invalido em $ROOT_DIR"

    if ! git -C "$ROOT_DIR" diff --quiet || ! git -C "$ROOT_DIR" diff --cached --quiet; then
      fail "working tree possui alteracoes locais. Commit/stash antes de executar --apply sem --skip-pull."
    fi
  fi

  if [ "$RUN_HEALTHCHECK" = true ] || { [ "$RUN_APPLY" = true ] && [ "$SKIP_HEALTHCHECK" = false ]; }; then
    require_command curl
  fi

  if [ "$RUN_APPLY" = true ] || [ "$RUN_MIGRATE" = true ] || [ "$RUN_SEED" = true ] || [ "$RUN_HEALTHCHECK" = true ]; then
    require_file "$ENV_FILE"
  fi

  if [ "$RUN_APPLY" = true ] || [ "$RUN_MIGRATE" = true ] || [ "$RUN_SEED" = true ]; then
    require_env DB_HOST
    require_env DB_PORT
    require_env DB_NAME
    require_env DB_USER
    require_env DB_CHARSET
  fi

  if [ "$RUN_HEALTHCHECK" = true ] || { [ "$RUN_APPLY" = true ] && [ "$SKIP_HEALTHCHECK" = false ]; }; then
    require_env BASE_URL
  fi

  if [ "${APP_ENV:-}" = "production" ] && [ "${APP_DEBUG:-0}" = "1" ]; then
    warn "APP_ENV=production com APP_DEBUG=1. Recomenda-se APP_DEBUG=0."
  fi

  if [ "${SEED_ADMIN_PASSWORD:-}" = "change_me" ] || [ "${SEED_ADMIN_PASSWORD:-}" = "ChangeMe123!" ]; then
    warn "SEED_ADMIN_PASSWORD esta com valor padrao. Ajuste antes de producao."
  fi

  log "preflight concluido"
}

git_pull() {
  log "atualizando codigo via git"
  run_cmd git -C "$ROOT_DIR" fetch --all --prune
  run_cmd git -C "$ROOT_DIR" pull --ff-only
}

install_dependencies() {
  if [ "$SKIP_COMPOSER" = true ]; then
    return
  fi

  if [ ! -f "$ROOT_DIR/composer.json" ]; then
    log "composer.json nao encontrado; etapa de dependencias ignorada"
    return
  fi

  require_command composer
  log "instalando dependencias com composer"

  if [ "${APP_ENV:-production}" = "production" ]; then
    run_cmd composer install --working-dir="$ROOT_DIR" --no-dev --prefer-dist --no-interaction --optimize-autoloader
  else
    run_cmd composer install --working-dir="$ROOT_DIR" --prefer-dist --no-interaction --optimize-autoloader
  fi
}

ensure_runtime_dirs() {
  log "garantindo diretorios de runtime"
  run_cmd mkdir -p "$ROOT_DIR/storage/logs" "$ROOT_DIR/storage/uploads"
  run_cmd chmod 775 "$ROOT_DIR/storage" "$ROOT_DIR/storage/logs" "$ROOT_DIR/storage/uploads"
}

run_migrate() {
  log "executando migrations"
  run_cmd php "$MIGRATE_PHP"
}

run_seed() {
  log "executando seed"
  run_cmd php "$SEED_PHP"
}

restart_services() {
  if [ -z "${DEPLOY_RESTART_COMMAND:-}" ]; then
    log "DEPLOY_RESTART_COMMAND nao definido; restart de servico ignorado"
    return
  fi

  log "executando restart configurado"
  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] bash -lc \"DEPLOY_RESTART_COMMAND\""
    return
  fi

  bash -lc "$DEPLOY_RESTART_COMMAND"
}

run_healthcheck() {
  log "executando health-check"

  if [ -x "$HEALTHCHECK_SCRIPT" ]; then
    run_cmd "$HEALTHCHECK_SCRIPT"
    return
  fi

  local health_path url body
  health_path="${DEPLOY_HEALTH_PATH:-/health}"
  url="${BASE_URL%/}${health_path}"

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] curl -fsS --max-time 20 $url"
    return
  fi

  body="$(curl -fsS --max-time 20 "$url")" || fail "health-check falhou em $url"
  if ! printf '%s' "$body" | grep -q '"status":"ok"'; then
    fail "health-check retornou status diferente de ok"
  fi

  log "health-check OK"
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --preflight)
      RUN_PREFLIGHT=true
      shift
      ;;
    --no-preflight)
      RUN_PREFLIGHT=false
      shift
      ;;
    --apply)
      RUN_APPLY=true
      shift
      ;;
    --migrate)
      RUN_MIGRATE=true
      shift
      ;;
    --seed)
      RUN_SEED=true
      shift
      ;;
    --healthcheck)
      RUN_HEALTHCHECK=true
      shift
      ;;
    --with-seed)
      WITH_SEED=true
      shift
      ;;
    --skip-pull)
      SKIP_PULL=true
      shift
      ;;
    --skip-composer)
      SKIP_COMPOSER=true
      shift
      ;;
    --skip-healthcheck)
      SKIP_HEALTHCHECK=true
      shift
      ;;
    --dry-run)
      DRY_RUN=true
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      fail "opcao desconhecida: $1"
      ;;
  esac
done

load_env

if [ "$RUN_APPLY" = true ]; then
  RUN_MIGRATE=true
  if [ "$WITH_SEED" = true ]; then
    RUN_SEED=true
  fi
fi

if [ "$RUN_APPLY" = true ] && [ "$SKIP_HEALTHCHECK" = false ]; then
  RUN_HEALTHCHECK=true
fi

if [ "$RUN_PREFLIGHT" = true ]; then
  preflight
fi

if [ "$RUN_APPLY" = true ]; then
  if [ "$SKIP_PULL" = false ]; then
    git_pull
  else
    log "git pull ignorado (--skip-pull)"
  fi

  install_dependencies
  ensure_runtime_dirs
fi

if [ "$RUN_MIGRATE" = true ]; then
  run_migrate
fi

if [ "$RUN_SEED" = true ]; then
  run_seed
fi

if [ "$RUN_APPLY" = true ]; then
  restart_services
fi

if [ "$RUN_HEALTHCHECK" = true ]; then
  run_healthcheck
fi

if [ "$RUN_APPLY" = false ] && [ "$RUN_MIGRATE" = false ] && [ "$RUN_SEED" = false ] && [ "$RUN_HEALTHCHECK" = false ]; then
  log "nenhuma acao executada alem do preflight"
fi

log "deploy finalizado"
