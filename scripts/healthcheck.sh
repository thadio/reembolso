#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
ENV_FILE="$ROOT_DIR/.env"

URL=""
TIMEOUT=20

log() {
  printf '[healthcheck] %s\n' "$*"
}

fail() {
  printf '[healthcheck][error] %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage: ./scripts/healthcheck.sh [--url <url>] [--timeout <seconds>]

Options:
  --url      URL completa de health-check (default: BASE_URL + DEPLOY_HEALTH_PATH)
  --timeout  Timeout em segundos (default: 20)
  --help     Mostra esta ajuda
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

while [ "$#" -gt 0 ]; do
  case "$1" in
    --url)
      URL="$2"
      shift 2
      ;;
    --timeout)
      TIMEOUT="$2"
      shift 2
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

if [ -z "$URL" ]; then
  [ -n "${BASE_URL:-}" ] || fail "BASE_URL nao definido e --url nao informado"
  URL="${BASE_URL%/}${DEPLOY_HEALTH_PATH:-/health}"
fi

log "verificando $URL"
BODY="$(curl -fsS --max-time "$TIMEOUT" "$URL")" || fail "falha na requisicao HTTP"

if ! printf '%s' "$BODY" | grep -q '"status":"ok"'; then
  fail "health-check retornou estado diferente de ok"
fi

log "status ok"
