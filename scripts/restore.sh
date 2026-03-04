#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
ENV_FILE="$ROOT_DIR/.env"

BACKUP_PATH=""
RESTORE_DB=true
RESTORE_STORAGE=true
RESTORE_ENV=false
DRY_RUN=false
ASSUME_YES=false

log() {
  printf '[restore] %s\n' "$*"
}

warn() {
  printf '[restore][warn] %s\n' "$*" >&2
}

fail() {
  printf '[restore][error] %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage: ./scripts/restore.sh --from <backup_dir> [options]

Options:
  --from <path>          Diretorio do backup (absoluto ou relativo a BACKUP_ROOT/storage/backups)
  --db-only              Restaura apenas banco
  --storage-only         Restaura apenas storage/uploads
  --with-env             Restaura .env a partir de .env.snapshot
  --skip-db              Nao restaura banco
  --skip-storage         Nao restaura storage/uploads
  --yes                  Confirma restauracao destrutiva
  --dry-run              Mostra comandos sem executar
  --help                 Mostra esta ajuda

Examples:
  ./scripts/restore.sh --from backup_20260304_101500 --yes
  ./scripts/restore.sh --from /var/backups/reembolso/backup_20260304_101500 --storage-only --yes
  ./scripts/restore.sh --from backup_20260304_101500 --with-env --yes
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

resolve_backup_path() {
  local provided="$1"

  if [ -d "$provided" ]; then
    printf '%s' "$provided"
    return
  fi

  local default_root="${BACKUP_ROOT:-$ROOT_DIR/storage/backups}"
  if [ -d "$default_root/$provided" ]; then
    printf '%s' "$default_root/$provided"
    return
  fi

  fail "diretorio de backup nao encontrado: $provided"
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --from)
      BACKUP_PATH="$2"
      shift 2
      ;;
    --db-only)
      RESTORE_DB=true
      RESTORE_STORAGE=false
      shift
      ;;
    --storage-only)
      RESTORE_DB=false
      RESTORE_STORAGE=true
      shift
      ;;
    --with-env)
      RESTORE_ENV=true
      shift
      ;;
    --skip-db)
      RESTORE_DB=false
      shift
      ;;
    --skip-storage)
      RESTORE_STORAGE=false
      shift
      ;;
    --yes)
      ASSUME_YES=true
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

[ -n "$BACKUP_PATH" ] || fail "informe --from <backup_dir>"

load_env
BACKUP_PATH="$(resolve_backup_path "$BACKUP_PATH")"

if [ "$RESTORE_DB" = false ] && [ "$RESTORE_STORAGE" = false ] && [ "$RESTORE_ENV" = false ]; then
  fail "nenhum artefato selecionado para restore"
fi

if [ "$DRY_RUN" = false ] && [ "$ASSUME_YES" = false ]; then
  fail "operacao destrutiva; confirme com --yes"
fi

DB_DUMP="$BACKUP_PATH/db.sql.gz"
STORAGE_ARCHIVE="$BACKUP_PATH/uploads.tar.gz"
ENV_SNAPSHOT="$BACKUP_PATH/.env.snapshot"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

require_command date

if [ "$RESTORE_DB" = true ]; then
  [ -f "$DB_DUMP" ] || fail "dump de banco nao encontrado: $DB_DUMP"
  require_command mysql
  require_command gunzip

  require_env DB_HOST
  require_env DB_PORT
  require_env DB_NAME
  require_env DB_USER
  require_env DB_CHARSET
fi

if [ "$RESTORE_STORAGE" = true ]; then
  [ -f "$STORAGE_ARCHIVE" ] || fail "arquivo de storage nao encontrado: $STORAGE_ARCHIVE"
  require_command tar
  require_command mktemp
  require_command mv
fi

if [ "$RESTORE_ENV" = true ]; then
  [ -f "$ENV_SNAPSHOT" ] || fail "snapshot de .env nao encontrado: $ENV_SNAPSHOT"
  require_command cp
fi

if [ "$RESTORE_DB" = true ]; then
  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] gunzip -c $DB_DUMP | mysql -h $DB_HOST -P $DB_PORT -u $DB_USER $DB_NAME"
  else
    log "restaurando banco de dados"
    if [ -n "${DB_PASS:-}" ]; then
      gunzip -c "$DB_DUMP" | MYSQL_PWD="$DB_PASS" mysql \
        --default-character-set="$DB_CHARSET" \
        -h "$DB_HOST" \
        -P "$DB_PORT" \
        -u "$DB_USER" \
        "$DB_NAME"
    else
      gunzip -c "$DB_DUMP" | mysql \
        --default-character-set="$DB_CHARSET" \
        -h "$DB_HOST" \
        -P "$DB_PORT" \
        -u "$DB_USER" \
        "$DB_NAME"
    fi
  fi
fi

if [ "$RESTORE_STORAGE" = true ]; then
  UPLOADS_DIR="$ROOT_DIR/storage/uploads"
  SNAPSHOT_DIR="$ROOT_DIR/storage/uploads.pre_restore_$TIMESTAMP"

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] extrair $STORAGE_ARCHIVE para storage/uploads"
  else
    log "restaurando storage/uploads"

    TMP_DIR="$(mktemp -d "$ROOT_DIR/storage/.restore_uploads.XXXXXX")"
    tar -xzf "$STORAGE_ARCHIVE" -C "$TMP_DIR"

    [ -d "$TMP_DIR/uploads" ] || fail "arquivo de storage invalido: pasta uploads nao encontrada"

    if [ -d "$UPLOADS_DIR" ]; then
      mv "$UPLOADS_DIR" "$SNAPSHOT_DIR"
      log "snapshot atual movido para: $SNAPSHOT_DIR"
    fi

    mv "$TMP_DIR/uploads" "$UPLOADS_DIR"
    rmdir "$TMP_DIR" >/dev/null 2>&1 || true
  fi
fi

if [ "$RESTORE_ENV" = true ]; then
  PREV_ENV="$ROOT_DIR/.env.pre_restore_$TIMESTAMP"

  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] restaurar .env a partir de $ENV_SNAPSHOT"
  else
    if [ -f "$ENV_FILE" ]; then
      cp "$ENV_FILE" "$PREV_ENV"
      chmod 600 "$PREV_ENV"
      log "snapshot atual de .env salvo em: $PREV_ENV"
    fi

    cp "$ENV_SNAPSHOT" "$ENV_FILE"
    chmod 600 "$ENV_FILE"
  fi
fi

if [ "$DRY_RUN" = false ]; then
  log "restore concluido a partir de: $BACKUP_PATH"
else
  log "simulacao concluida"
fi
