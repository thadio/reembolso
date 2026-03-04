#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
ENV_FILE="$ROOT_DIR/.env"

DRY_RUN=false
DELETE_MODE=""
REMOTE_ROOT_OVERRIDE=""
IGNORE_FILE_OVERRIDE=""
PARALLEL_OVERRIDE=""
VERBOSE=false

log() {
  printf '[ftp-upload] %s\n' "$*"
}

warn() {
  printf '[ftp-upload][warn] %s\n' "$*" >&2
}

fail() {
  printf '[ftp-upload][error] %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage: ./scripts/ftp-upload.sh [options]

Options:
  --dry-run               Simula upload sem enviar arquivos
  --delete                Remove arquivos remotos ausentes no local
  --no-delete             Nao remove arquivos remotos
  --remote-root <path>    Sobrescreve FTP_REMOTE_ROOT do .env
  --ignore-file <path>    Sobrescreve FTP_IGNORE_FILE (default .ftpignore)
  --parallel <n>          Numero de transfers paralelas (default 2)
  --verbose               Mostra mais detalhes de execucao
  --help                  Mostra esta ajuda

Environment (.env):
  FTP_HOST                Host FTP (obrigatorio)
  FTP_PORT                Porta FTP (default 21)
  FTP_USER                Usuario FTP (obrigatorio)
  FTP_PASS                Senha FTP (obrigatorio)
  FTP_REMOTE_ROOT         Diretorio remoto de destino (obrigatorio)
  FTP_IGNORE_FILE         Arquivo de exclusoes (default .ftpignore)
  FTP_DELETE              1=true, 0=false
  FTP_SSL_ALLOW           1=true, 0=false (default: igual a FTP_SSL_FORCE)
  FTP_SSL_FORCE           1=true, 0=false (default 0)
  FTP_SSL_VERIFY          1=true, 0=false (default 1)
  FTP_PARALLEL            Numero de uploads paralelos (default 2)

Examples:
  ./scripts/ftp-upload.sh --dry-run
  ./scripts/ftp-upload.sh
  ./scripts/ftp-upload.sh --delete
  ./scripts/ftp-upload.sh --remote-root reembolso.thadio.com
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
    fail "arquivo .env nao encontrado em $ENV_FILE"
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
  local command_name="$1"
  command -v "$command_name" >/dev/null 2>&1 || fail "comando obrigatorio nao encontrado: $command_name"
}

require_env() {
  local key="$1"
  [ -n "${!key:-}" ] || fail "variavel obrigatoria ausente no .env: $key"
}

is_truthy() {
  case "${1:-}" in
    1|true|TRUE|yes|YES|on|ON) return 0 ;;
    *) return 1 ;;
  esac
}

to_lftp_bool() {
  if is_truthy "$1"; then
    printf 'true'
  else
    printf 'false'
  fi
}

escape_single_quotes() {
  printf '%s' "$1" | sed "s/'/'\"'\"'/g"
}

build_quoted_command() {
  local quoted=""
  local arg
  for arg in "$@"; do
    quoted+=$(printf "%q " "$arg")
  done
  printf '%s' "$quoted"
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --dry-run)
      DRY_RUN=true
      shift
      ;;
    --delete)
      DELETE_MODE="true"
      shift
      ;;
    --no-delete)
      DELETE_MODE="false"
      shift
      ;;
    --remote-root)
      REMOTE_ROOT_OVERRIDE="$2"
      shift 2
      ;;
    --ignore-file)
      IGNORE_FILE_OVERRIDE="$2"
      shift 2
      ;;
    --parallel)
      PARALLEL_OVERRIDE="$2"
      shift 2
      ;;
    --verbose)
      VERBOSE=true
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
require_command lftp

require_env FTP_HOST
require_env FTP_USER
require_env FTP_PASS

FTP_PORT="${FTP_PORT:-21}"
FTP_REMOTE_ROOT="${REMOTE_ROOT_OVERRIDE:-${FTP_REMOTE_ROOT:-${DEPLOY_SSH_REMOTE_ROOT:-}}}"
FTP_IGNORE_FILE="${IGNORE_FILE_OVERRIDE:-${FTP_IGNORE_FILE:-.ftpignore}}"
FTP_PARALLEL="${PARALLEL_OVERRIDE:-${FTP_PARALLEL:-2}}"
FTP_SSL_FORCE="${FTP_SSL_FORCE:-0}"
FTP_SSL_ALLOW="${FTP_SSL_ALLOW:-$FTP_SSL_FORCE}"
FTP_SSL_VERIFY="${FTP_SSL_VERIFY:-1}"

[ -n "$FTP_REMOTE_ROOT" ] || fail "defina FTP_REMOTE_ROOT no .env ou use --remote-root"

if [[ "$FTP_IGNORE_FILE" != /* ]]; then
  FTP_IGNORE_FILE="$ROOT_DIR/$FTP_IGNORE_FILE"
fi

[ -f "$FTP_IGNORE_FILE" ] || fail "arquivo de exclusoes nao encontrado: $FTP_IGNORE_FILE"

if [ -n "$DELETE_MODE" ]; then
  FTP_DELETE="$DELETE_MODE"
else
  FTP_DELETE="${FTP_DELETE:-0}"
fi

SSL_ALLOW_BOOL="$(to_lftp_bool "$FTP_SSL_ALLOW")"
SSL_FORCE_BOOL="$(to_lftp_bool "$FTP_SSL_FORCE")"
SSL_VERIFY_BOOL="$(to_lftp_bool "$FTP_SSL_VERIFY")"

if [ "$SSL_ALLOW_BOOL" = "false" ]; then
  warn "FTP_SSL_ALLOW=0 (upload sem TLS). Recomendado usar TLS quando disponivel."
fi
if [ "$SSL_VERIFY_BOOL" = "false" ]; then
  warn "FTP_SSL_VERIFY=0 (sem validacao de certificado). Use apenas quando necessario."
fi

declare -a MIRROR_ARGS
MIRROR_ARGS=(mirror -R --parallel="$FTP_PARALLEL")

if [ "$VERBOSE" = true ]; then
  MIRROR_ARGS+=(--verbose=2)
else
  MIRROR_ARGS+=(--verbose=1)
fi

if is_truthy "$FTP_DELETE"; then
  MIRROR_ARGS+=(--delete)
fi

if [ "$DRY_RUN" = true ]; then
  MIRROR_ARGS+=(--dry-run --just-print)
fi

while IFS= read -r line || [ -n "$line" ]; do
  line="$(trim "$line")"

  if [ -z "$line" ] || [[ "$line" == \#* ]]; then
    continue
  fi

  if [[ "$line" == !* ]]; then
    warn "padrao negado em $FTP_IGNORE_FILE nao suportado pelo script: $line"
    continue
  fi

  line="${line#/}"
  MIRROR_ARGS+=(--exclude-glob "$line")
done < "$FTP_IGNORE_FILE"

MIRROR_ARGS+=("$ROOT_DIR" "$FTP_REMOTE_ROOT")
MIRROR_COMMAND="$(build_quoted_command "${MIRROR_ARGS[@]}")"

HOST_ESCAPED="$(escape_single_quotes "$FTP_HOST")"
USER_ESCAPED="$(escape_single_quotes "$FTP_USER")"
PASS_ESCAPED="$(escape_single_quotes "$FTP_PASS")"

log "origem local: $ROOT_DIR"
log "destino remoto: $FTP_REMOTE_ROOT"
log "arquivo de exclusoes: $FTP_IGNORE_FILE"
log "delete remoto: $(is_truthy "$FTP_DELETE" && echo "habilitado" || echo "desabilitado")"
log "modo dry-run: $( [ "$DRY_RUN" = true ] && echo "sim" || echo "nao" )"

lftp <<EOF
set cmd:fail-exit true
set cmd:verbose false
set net:max-retries 2
set net:timeout 30
set net:reconnect-interval-base 5
set ftp:ssl-allow $SSL_ALLOW_BOOL
set ftp:ssl-force $SSL_FORCE_BOOL
set ssl:verify-certificate $SSL_VERIFY_BOOL
open -p $FTP_PORT '$HOST_ESCAPED'
user '$USER_ESCAPED' '$PASS_ESCAPED'
$MIRROR_COMMAND
bye
EOF

log "upload FTP concluido"
