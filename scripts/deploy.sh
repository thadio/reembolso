#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
MIGRATE_PHP="$ROOT_DIR/db/migrate.php"
SEED_PHP="$ROOT_DIR/db/seed.php"
ENV_FILE="$ROOT_DIR/.env"

DRY_RUN=false
RUN_PREFLIGHT=true

RUN_LOCAL_MIGRATE=false
RUN_LOCAL_SEED=false

RUN_REMOTE_SYNC=false
RUN_REMOTE_MIGRATE=false
RUN_REMOTE_SEED=false
RUN_REMOTE_HEALTH=false

FORCE_RSYNC_DELETE=false
HAS_ACTION=false
HAVE_SSHPASS=false

usage() {
  cat <<EOF
Usage: $(basename "$0") [options]

Options:
  --preflight       valida dependencias e configuracoes (default)
  --no-preflight    nao executa validacoes antes das acoes
  --dry-run         simula comandos sem alterar nada

  --migrate         executa migration local (compatibilidade)
  --seed            executa seed local (compatibilidade)
  --apply           executa migration + seed local (compatibilidade)

  --remote-sync     sincroniza codigo local para remoto via rsync/ssh
  --remote-migrate  executa migration no servidor remoto
  --remote-seed     executa seed no servidor remoto
  --remote-health   valida endpoint BASE_URL/health
  --remote-apply    executa remote-sync + remote-migrate
  --with-seed       junto com --remote-apply, executa remote-seed tambem
  --force-delete    usa --delete no rsync (alem do DEPLOY_RSYNC_DELETE)

  --help            mostra esta ajuda

Exemplos:
  $(basename "$0") --preflight
  $(basename "$0") --remote-apply --remote-health
  $(basename "$0") --remote-apply --with-seed --force-delete
  $(basename "$0") --migrate --seed
EOF
}

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

trim() {
  local value="$1"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf '%s' "$value"
}

strip_quotes() {
  local value="$1"
  local first_char last_char
  local length="${#value}"

  if [ "$length" -lt 2 ]; then
    printf '%s' "$value"
    return
  fi

  first_char="${value:0:1}"
  last_char="${value: -1}"

  if [ "$first_char" = '"' ] && [ "$last_char" = '"' ]; then
    printf '%s' "${value:1:length-2}"
    return
  fi
  if [ "$first_char" = "'" ] && [ "$last_char" = "'" ]; then
    printf '%s' "${value:1:length-2}"
    return
  fi

  printf '%s' "$value"
}

escape_single_quotes() {
  printf '%s' "$1" | sed "s/'/'\"'\"'/g"
}

load_env() {
  if [ ! -f "$ENV_FILE" ]; then
    warn "arquivo .env nao encontrado em $ENV_FILE"
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
      warn "chave invalida ignorada no .env: $key"
      continue
    fi

    export "$key=$value"
  done < "$ENV_FILE"
}

is_truthy() {
  case "${1:-}" in
    1|true|TRUE|yes|YES|on|ON) return 0 ;;
    *) return 1 ;;
  esac
}

require_command() {
  local command_name="$1"
  if ! command -v "$command_name" >/dev/null 2>&1; then
    fail "comando obrigatorio nao encontrado: $command_name"
  fi
}

require_file() {
  local file="$1"
  if [ ! -f "$file" ]; then
    fail "arquivo obrigatorio nao encontrado: $file"
  fi
}

require_env() {
  local key="$1"
  if [ -z "${!key:-}" ]; then
    fail "variavel obrigatoria nao configurada: $key"
  fi
}

ssh_target() {
  printf '%s@%s' "${DEPLOY_SSH_USER}" "${DEPLOY_SSH_HOST}"
}

setup_ssh_auth() {
  if [ -n "${DEPLOY_SSH_PASS:-}" ]; then
    if command -v sshpass >/dev/null 2>&1; then
      HAVE_SSHPASS=true
    else
      warn "DEPLOY_SSH_PASS definido, mas sshpass nao esta instalado. Tentando autenticacao por chave/agente."
    fi
  fi
}

run_local_step() {
  local description="$1"
  shift

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] $description"
    log "[dry-run] comando local: $*"
    return
  fi

  log "$description"
  "$@"
}

run_remote_step() {
  local description="$1"
  local remote_command="$2"
  local target
  target="$(ssh_target)"

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] $description"
    log "[dry-run] comando remoto: $remote_command"
    return
  fi

  log "$description"
  if [ "$HAVE_SSHPASS" = true ]; then
    SSHPASS="${DEPLOY_SSH_PASS}" sshpass -e ssh \
      -p "${DEPLOY_SSH_PORT}" \
      -o StrictHostKeyChecking=accept-new \
      -o ServerAliveInterval=30 \
      "$target" \
      "$remote_command"
  else
    ssh \
      -p "${DEPLOY_SSH_PORT}" \
      -o StrictHostKeyChecking=accept-new \
      -o ServerAliveInterval=30 \
      "$target" \
      "$remote_command"
  fi
}

check_php_version() {
  local version major
  version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;')"
  major="$(echo "$version" | cut -d. -f1)"

  log "PHP local detectado: $version"
  if [ "$major" -lt 8 ]; then
    warn "PHP 8+ recomendado para este projeto."
  fi
}

preflight_common() {
  require_command php
  require_file "$MIGRATE_PHP"
  require_file "$SEED_PHP"
  check_php_version

  if [ "${APP_ENV:-}" = "production" ] && [ "${APP_DEBUG:-0}" = "1" ]; then
    warn "APP_ENV=production com APP_DEBUG=1. Recomendado APP_DEBUG=0 em producao."
  fi

  if [ "${SEED_ADMIN_PASSWORD:-}" = "ChangeMe123!" ]; then
    warn "SEED_ADMIN_PASSWORD esta com valor padrao. Recomendado trocar antes de deploy."
  fi

  if [ "$RUN_LOCAL_MIGRATE" = true ] || [ "$RUN_LOCAL_SEED" = true ]; then
    require_env DB_HOST
    require_env DB_PORT
    require_env DB_NAME
    require_env DB_USER
    require_env DB_CHARSET
  fi
}

preflight_remote() {
  require_env DEPLOY_SSH_HOST
  require_env DEPLOY_SSH_PORT
  require_env DEPLOY_SSH_USER
  require_env DEPLOY_SSH_REMOTE_ROOT

  require_command ssh
  setup_ssh_auth

  if [ "$RUN_REMOTE_SYNC" = true ]; then
    require_command rsync
    local ignore_file
    ignore_file="${DEPLOY_IGNORE_FILE:-.ftpignore}"
    if [[ "$ignore_file" != /* ]]; then
      ignore_file="$ROOT_DIR/$ignore_file"
    fi
    require_file "$ignore_file"
  fi

  if [ "$RUN_REMOTE_HEALTH" = true ]; then
    require_command curl
    require_env BASE_URL
  fi

  if [ "$DRY_RUN" = false ]; then
    local escaped_remote_root
    escaped_remote_root="$(escape_single_quotes "${DEPLOY_SSH_REMOTE_ROOT}")"
    run_remote_step "validando acesso remoto (SSH + pasta alvo)" "test -d '${escaped_remote_root}'"
  fi
}

run_remote_sync() {
  local ignore_file delete_enabled remote_root target
  local rsh_cmd
  local -a command

  ignore_file="${DEPLOY_IGNORE_FILE:-.ftpignore}"
  if [[ "$ignore_file" != /* ]]; then
    ignore_file="$ROOT_DIR/$ignore_file"
  fi

  delete_enabled=false
  if is_truthy "${DEPLOY_RSYNC_DELETE:-0}"; then
    delete_enabled=true
  fi
  if [ "$FORCE_RSYNC_DELETE" = true ]; then
    delete_enabled=true
  fi

  remote_root="${DEPLOY_SSH_REMOTE_ROOT%/}/"
  target="$(ssh_target):${remote_root}"

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] sincronizacao remota planejada"
    log "[dry-run] origem local: $ROOT_DIR/"
    log "[dry-run] destino remoto: $remote_root"
    log "[dry-run] ignore file: $ignore_file"
    if [ "$delete_enabled" = true ]; then
      log "[dry-run] rsync --delete: habilitado"
    else
      log "[dry-run] rsync --delete: desabilitado"
    fi
    return
  fi

  rsh_cmd="ssh -p ${DEPLOY_SSH_PORT} -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30"
  if [ "$HAVE_SSHPASS" = true ]; then
    rsh_cmd="sshpass -e ${rsh_cmd}"
  fi

  command=(
    rsync
    -az
    --human-readable
    --omit-dir-times
    --no-perms
    --no-owner
    --no-group
    --exclude-from "$ignore_file"
    --rsh "$rsh_cmd"
  )

  if [ "$delete_enabled" = true ]; then
    command+=(--delete)
  fi

  command+=("$ROOT_DIR/" "$target")

  log "sincronizando codigo local para o remoto (rsync)"
  if [ "$HAVE_SSHPASS" = true ]; then
    SSHPASS="${DEPLOY_SSH_PASS}" "${command[@]}"
  else
    "${command[@]}"
  fi
}

run_remote_migrate() {
  local escaped_remote_root
  escaped_remote_root="$(escape_single_quotes "${DEPLOY_SSH_REMOTE_ROOT}")"
  run_remote_step "executando migration no servidor remoto" "cd '${escaped_remote_root}' && php db/migrate.php"
}

run_remote_seed() {
  local escaped_remote_root
  escaped_remote_root="$(escape_single_quotes "${DEPLOY_SSH_REMOTE_ROOT}")"
  run_remote_step "executando seed no servidor remoto" "cd '${escaped_remote_root}' && php db/seed.php"
}

run_remote_health() {
  local health_url response
  health_url="${BASE_URL%/}/health"

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] validando health check em: $health_url"
    return
  fi

  response="$(curl -fsS --max-time 20 "$health_url")" || fail "falha ao consultar health check: $health_url"

  if printf '%s' "$response" | grep -q '"status":"ok"'; then
    log "health check concluido com status ok"
  else
    warn "health check retornou estado diferente de ok"
    printf '%s\n' "$response"
  fi
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
    --dry-run)
      DRY_RUN=true
      shift
      ;;
    --migrate|--local-migrate)
      RUN_LOCAL_MIGRATE=true
      HAS_ACTION=true
      shift
      ;;
    --seed|--local-seed)
      RUN_LOCAL_SEED=true
      HAS_ACTION=true
      shift
      ;;
    --apply)
      RUN_LOCAL_MIGRATE=true
      RUN_LOCAL_SEED=true
      HAS_ACTION=true
      shift
      ;;
    --remote-sync)
      RUN_REMOTE_SYNC=true
      HAS_ACTION=true
      shift
      ;;
    --remote-migrate)
      RUN_REMOTE_MIGRATE=true
      HAS_ACTION=true
      shift
      ;;
    --remote-seed)
      RUN_REMOTE_SEED=true
      HAS_ACTION=true
      shift
      ;;
    --remote-health)
      RUN_REMOTE_HEALTH=true
      HAS_ACTION=true
      shift
      ;;
    --remote-apply)
      RUN_REMOTE_SYNC=true
      RUN_REMOTE_MIGRATE=true
      HAS_ACTION=true
      shift
      ;;
    --with-seed)
      RUN_REMOTE_SEED=true
      HAS_ACTION=true
      shift
      ;;
    --force-delete)
      FORCE_RSYNC_DELETE=true
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
log "raiz do projeto: $ROOT_DIR"

if [ "$RUN_PREFLIGHT" = true ]; then
  preflight_common

  if [ "$RUN_REMOTE_SYNC" = true ] || [ "$RUN_REMOTE_MIGRATE" = true ] || [ "$RUN_REMOTE_SEED" = true ] || [ "$RUN_REMOTE_HEALTH" = true ]; then
    preflight_remote
  fi

  log "preflight concluido"
fi

if [ "$RUN_LOCAL_MIGRATE" = true ]; then
  run_local_step "executando migration local" php "$MIGRATE_PHP"
fi

if [ "$RUN_LOCAL_SEED" = true ]; then
  run_local_step "executando seed local" php "$SEED_PHP"
fi

if [ "$RUN_REMOTE_SYNC" = true ]; then
  run_remote_sync
fi

if [ "$RUN_REMOTE_MIGRATE" = true ]; then
  run_remote_migrate
fi

if [ "$RUN_REMOTE_SEED" = true ]; then
  run_remote_seed
fi

if [ "$RUN_REMOTE_HEALTH" = true ]; then
  run_remote_health
fi

if [ "$HAS_ACTION" = false ]; then
  log "nenhuma acao de deploy solicitada. Apenas preflight foi executado."
fi

log "processo finalizado."
exit 0
