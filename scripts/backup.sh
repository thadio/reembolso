#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
ENV_FILE="$ROOT_DIR/.env"

OUTPUT_DIR=""
RETENTION_DAYS=""
WITH_ENV=false
SKIP_DB=false
SKIP_STORAGE=false
DRY_RUN=false
LABEL=""

log() {
  printf '[backup] %s\n' "$*"
}

warn() {
  printf '[backup][warn] %s\n' "$*" >&2
}

fail() {
  printf '[backup][error] %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage: ./scripts/backup.sh [options]

Options:
  --output-dir <path>      Diretorio de destino dos backups
  --retention-days <n>     Remove backups com mais de n dias (default: BACKUP_RETENTION_DAYS ou 14)
  --with-env               Inclui snapshot do .env no backup
  --skip-db                Nao gera dump do banco
  --skip-storage           Nao gera arquivo de storage/uploads
  --label <texto>          Sufixo para identificar o backup
  --dry-run                Mostra comandos sem executar
  --help                   Mostra esta ajuda

Examples:
  ./scripts/backup.sh
  ./scripts/backup.sh --with-env --label pre_release
  ./scripts/backup.sh --output-dir /var/backups/reembolso --retention-days 30
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

sanitize_label() {
  local raw="$1"
  raw="${raw// /_}"
  raw="$(printf '%s' "$raw" | tr -cd 'A-Za-z0-9_-')"

  [ -n "$raw" ] || fail "label invalido; use apenas letras, numeros, _ ou -"
  printf '%s' "$raw"
}

hash_file() {
  local file="$1"

  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$file" | awk '{print $1}'
    return
  fi

  if command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$file" | awk '{print $1}'
    return
  fi

  fail "nenhum comando de hash disponivel (sha256sum ou shasum)"
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --output-dir)
      OUTPUT_DIR="$2"
      shift 2
      ;;
    --retention-days)
      RETENTION_DAYS="$2"
      shift 2
      ;;
    --with-env)
      WITH_ENV=true
      shift
      ;;
    --skip-db)
      SKIP_DB=true
      shift
      ;;
    --skip-storage)
      SKIP_STORAGE=true
      shift
      ;;
    --label)
      LABEL="$2"
      shift 2
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

if [ -z "$OUTPUT_DIR" ]; then
  OUTPUT_DIR="${BACKUP_ROOT:-$ROOT_DIR/storage/backups}"
fi

if [ -z "$RETENTION_DAYS" ]; then
  RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
fi

if [ "${BACKUP_INCLUDE_ENV:-0}" = "1" ]; then
  WITH_ENV=true
fi

[[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] || fail "--retention-days deve ser inteiro >= 0"

if [ "$SKIP_STORAGE" = false ] && [ ! -d "$ROOT_DIR/storage/uploads" ]; then
  warn "diretorio storage/uploads nao encontrado; backup de storage sera ignorado"
  SKIP_STORAGE=true
fi

if [ "$SKIP_DB" = true ] && [ "$SKIP_STORAGE" = true ] && [ "$WITH_ENV" = false ]; then
  fail "nenhum artefato selecionado; remova um --skip-* ou use --with-env"
fi

require_command date
require_command gzip
require_command tar
require_command mkdir
require_command find
require_command wc
require_command awk

if ! command -v sha256sum >/dev/null 2>&1 && ! command -v shasum >/dev/null 2>&1; then
  fail "comando de hash nao encontrado (sha256sum ou shasum)"
fi

if [ "$SKIP_DB" = false ]; then
  require_command mysqldump
  require_env DB_HOST
  require_env DB_PORT
  require_env DB_NAME
  require_env DB_USER
  require_env DB_CHARSET
fi

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
RUN_ID="backup_${TIMESTAMP}"

if [ -n "$LABEL" ]; then
  RUN_ID="${RUN_ID}_$(sanitize_label "$LABEL")"
fi

RUN_DIR="$OUTPUT_DIR/$RUN_ID"
DB_FILE="$RUN_DIR/db.sql.gz"
STORAGE_FILE="$RUN_DIR/uploads.tar.gz"
ENV_SNAPSHOT="$RUN_DIR/.env.snapshot"
MANIFEST_FILE="$RUN_DIR/manifest.txt"

ARTIFACTS=()

log "iniciando backup $RUN_ID"
run_cmd mkdir -p "$RUN_DIR"

if [ "$SKIP_DB" = false ]; then
  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] mysqldump -> $DB_FILE"
  else
    log "gerando dump do banco"
    if [ -n "${DB_PASS:-}" ]; then
      MYSQL_PWD="$DB_PASS" mysqldump \
        --default-character-set="$DB_CHARSET" \
        --single-transaction \
        --quick \
        --skip-lock-tables \
        -h "$DB_HOST" \
        -P "$DB_PORT" \
        -u "$DB_USER" \
        "$DB_NAME" | gzip -c > "$DB_FILE"
    else
      mysqldump \
        --default-character-set="$DB_CHARSET" \
        --single-transaction \
        --quick \
        --skip-lock-tables \
        -h "$DB_HOST" \
        -P "$DB_PORT" \
        -u "$DB_USER" \
        "$DB_NAME" | gzip -c > "$DB_FILE"
    fi
  fi
  ARTIFACTS+=("$DB_FILE")
fi

if [ "$SKIP_STORAGE" = false ]; then
  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] tar -czf $STORAGE_FILE -C $ROOT_DIR/storage uploads"
  else
    log "compactando storage/uploads"
    tar -czf "$STORAGE_FILE" -C "$ROOT_DIR/storage" uploads
  fi
  ARTIFACTS+=("$STORAGE_FILE")
fi

if [ "$WITH_ENV" = true ]; then
  [ -f "$ENV_FILE" ] || fail "arquivo .env nao encontrado em $ENV_FILE"

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] cp $ENV_FILE $ENV_SNAPSHOT"
  else
    log "gerando snapshot de .env"
    cp "$ENV_FILE" "$ENV_SNAPSHOT"
    chmod 600 "$ENV_SNAPSHOT"
  fi
  ARTIFACTS+=("$ENV_SNAPSHOT")
fi

if [ "$DRY_RUN" = false ]; then
  {
    printf 'backup_id=%s\n' "$RUN_ID"
    printf 'created_at_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    printf 'root_dir=%s\n' "$ROOT_DIR"
    printf 'output_dir=%s\n' "$RUN_DIR"
    printf 'db_included=%s\n' "$([ "$SKIP_DB" = true ] && echo 0 || echo 1)"
    printf 'storage_included=%s\n' "$([ "$SKIP_STORAGE" = true ] && echo 0 || echo 1)"
    printf 'env_included=%s\n' "$([ "$WITH_ENV" = true ] && echo 1 || echo 0)"

    for artifact in "${ARTIFACTS[@]}"; do
      if [ -f "$artifact" ]; then
        size_bytes="$(wc -c < "$artifact" | tr -d ' ')"
        hash="$(hash_file "$artifact")"
        printf 'artifact=%s size_bytes=%s sha256=%s\n' "$(basename "$artifact")" "$size_bytes" "$hash"
      fi
    done
  } > "$MANIFEST_FILE"
fi

if [ "$RETENTION_DAYS" -gt 0 ]; then
  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] remover backups em $OUTPUT_DIR com mais de $RETENTION_DAYS dias"
  else
    while IFS= read -r old_backup; do
      [ -n "$old_backup" ] || continue
      if [ "$old_backup" = "$RUN_DIR" ]; then
        continue
      fi
      rm -rf "$old_backup"
      log "backup removido por retencao: $old_backup"
    done < <(find "$OUTPUT_DIR" -mindepth 1 -maxdepth 1 -type d -name 'backup_*' -mtime "+$RETENTION_DAYS" -print)
  fi
fi

log "backup concluido: $RUN_DIR"
if [ "$DRY_RUN" = false ]; then
  log "manifesto: $MANIFEST_FILE"
fi
