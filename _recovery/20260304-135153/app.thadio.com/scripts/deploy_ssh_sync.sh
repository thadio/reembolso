#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

get_env_file_value() {
  local key="$1"
  local env_file="$ROOT/.env"
  local line=""
  local value=""

  [[ -f "$env_file" ]] || return 1
  line="$(grep -m1 -E "^${key}=" "$env_file" || true)"
  [[ -n "$line" ]] || return 1

  value="${line#*=}"
  value="${value%$'\r'}"

  if [[ "${#value}" -ge 2 ]]; then
    if [[ "${value:0:1}" == '"' && "${value: -1}" == '"' ]]; then
      value="${value:1:${#value}-2}"
    elif [[ "${value:0:1}" == "'" && "${value: -1}" == "'" ]]; then
      value="${value:1:${#value}-2}"
    fi
  fi

  printf '%s' "$value"
}

trim_spaces() {
  local s="$1"
  s="${s#"${s%%[![:space:]]*}"}"
  s="${s%"${s##*[![:space:]]}"}"
  printf '%s' "$s"
}

fail() {
  echo "ERRO: $*" >&2
  exit 1
}

usage() {
  cat <<'EOF'
Uso:
  scripts/deploy_ssh_sync.sh [opcoes]

Opcoes:
  --dry-run            Mostra o plano sem enviar arquivos.
  --delete             Remove no servidor arquivos ausentes localmente.
  --confirm            Obrigatorio junto com --delete.
  --host <host>        Sobrescreve host SSH.
  --port <porta>       Sobrescreve porta SSH.
  --user <usuario>     Sobrescreve usuario SSH.
  --remote-root <dir>  Sobrescreve pasta remota de deploy.
  --ignore-file <arq>  Sobrescreve arquivo de exclusao (padrao: .ftpignore).
  -h, --help           Exibe ajuda.

Variaveis .env/ambiente:
  DEPLOY_SSH_HOST
  DEPLOY_SSH_PORT
  DEPLOY_SSH_USER
  DEPLOY_SSH_PASS
  DEPLOY_SSH_REMOTE_ROOT
  DEPLOY_IGNORE_FILE
  DEPLOY_RSYNC_DELETE
EOF
}

require_cmd() {
  local cmd="$1"
  command -v "$cmd" >/dev/null 2>&1 || fail "$cmd nao encontrado no PATH."
}

cleanup() {
  rm -f "${TMP_EXCLUDE_FILE:-}"
}

DEPLOY_SSH_HOST="${DEPLOY_SSH_HOST:-$(get_env_file_value DEPLOY_SSH_HOST || printf '162.241.203.145')}"
DEPLOY_SSH_PORT="${DEPLOY_SSH_PORT:-$(get_env_file_value DEPLOY_SSH_PORT || printf '2222')}"
DEPLOY_SSH_USER="${DEPLOY_SSH_USER:-$(get_env_file_value DEPLOY_SSH_USER || printf 'thadio58')}"
DEPLOY_SSH_PASS="${DEPLOY_SSH_PASS:-$(get_env_file_value DEPLOY_SSH_PASS || true)}"
DEPLOY_SSH_REMOTE_ROOT="${DEPLOY_SSH_REMOTE_ROOT:-$(get_env_file_value DEPLOY_SSH_REMOTE_ROOT || printf '/home/thadio58/app.thadio.com')}"
DEPLOY_IGNORE_FILE="${DEPLOY_IGNORE_FILE:-$(get_env_file_value DEPLOY_IGNORE_FILE || printf '.ftpignore')}"
DELETE_REMOTE="${DEPLOY_RSYNC_DELETE:-$(get_env_file_value DEPLOY_RSYNC_DELETE || printf '0')}"
DRY_RUN=0
CONFIRM_DELETE=0

while (($#)); do
  case "$1" in
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    --delete)
      DELETE_REMOTE=1
      shift
      ;;
    --confirm)
      CONFIRM_DELETE=1
      shift
      ;;
    --host)
      [[ $# -ge 2 ]] || fail '--host exige valor.'
      DEPLOY_SSH_HOST="$2"
      shift 2
      ;;
    --port)
      [[ $# -ge 2 ]] || fail '--port exige valor.'
      DEPLOY_SSH_PORT="$2"
      shift 2
      ;;
    --user)
      [[ $# -ge 2 ]] || fail '--user exige valor.'
      DEPLOY_SSH_USER="$2"
      shift 2
      ;;
    --remote-root)
      [[ $# -ge 2 ]] || fail '--remote-root exige valor.'
      DEPLOY_SSH_REMOTE_ROOT="$2"
      shift 2
      ;;
    --ignore-file)
      [[ $# -ge 2 ]] || fail '--ignore-file exige valor.'
      DEPLOY_IGNORE_FILE="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      fail "opcao invalida: $1"
      ;;
  esac
done

if ! [[ "$DEPLOY_SSH_PORT" =~ ^[0-9]+$ ]] || [[ "$DEPLOY_SSH_PORT" -lt 1 ]]; then
  fail 'DEPLOY_SSH_PORT/--port deve ser inteiro >= 1.'
fi

if [[ "$DELETE_REMOTE" == "1" && "$CONFIRM_DELETE" != "1" ]]; then
  fail '--delete exige --confirm para evitar remocao acidental.'
fi

IGNORE_FILE_PATH="$DEPLOY_IGNORE_FILE"
if [[ "$IGNORE_FILE_PATH" != /* ]]; then
  IGNORE_FILE_PATH="$ROOT/$IGNORE_FILE_PATH"
fi

TMP_EXCLUDE_FILE="$(mktemp)"
trap cleanup EXIT

{
  printf '%s\n' \
    '.git/' \
    '.git/*' \
    '.env' \
    '.env.*' \
    '.ftpignore' \
    '.DS_Store' \
    '*/.DS_Store'

  if [[ -f "$IGNORE_FILE_PATH" ]]; then
    while IFS= read -r line || [[ -n "$line" ]]; do
      line="${line%$'\r'}"
      line="$(trim_spaces "$line")"
      [[ -z "$line" ]] && continue
      [[ "$line" == \#* ]] && continue
      printf '%s\n' "$line"
    done <"$IGNORE_FILE_PATH"
  else
    echo "Aviso: arquivo de ignore nao encontrado em $IGNORE_FILE_PATH; usando apenas exclusoes minimas."
  fi
} | awk 'NF' | sort -u >"$TMP_EXCLUDE_FILE"

require_cmd rsync

USE_SSHPASS=0
if [[ -n "$DEPLOY_SSH_PASS" ]]; then
  if command -v sshpass >/dev/null 2>&1; then
    USE_SSHPASS=1
  else
    echo "Aviso: sshpass nao encontrado; DEPLOY_SSH_PASS sera ignorado e o SSH pedira a senha."
  fi
fi

echo "Host SSH: ${DEPLOY_SSH_HOST}:${DEPLOY_SSH_PORT}"
echo "Usuario SSH: ${DEPLOY_SSH_USER}"
echo "Destino remoto: ${DEPLOY_SSH_REMOTE_ROOT}"
echo "Arquivo de exclusao: ${IGNORE_FILE_PATH}"
echo "Delete remoto: ${DELETE_REMOTE}"
echo "Dry-run: ${DRY_RUN}"

SSH_CMD="ssh -p ${DEPLOY_SSH_PORT} -o StrictHostKeyChecking=accept-new"

declare -a CMD=()
if [[ "$USE_SSHPASS" == "1" ]]; then
  export SSHPASS="$DEPLOY_SSH_PASS"
  CMD+=(sshpass -e)
else
  if [[ -z "$DEPLOY_SSH_PASS" ]]; then
    echo "Aviso: DEPLOY_SSH_PASS vazio. O SSH pode solicitar senha interativamente."
  else
    echo "Aviso: executando sem sshpass; o SSH pedira a senha interativamente."
  fi
fi

CMD+=(
  rsync
  -az
  --human-readable
  --itemize-changes
  --omit-dir-times
  --exclude-from="$TMP_EXCLUDE_FILE"
  -e "$SSH_CMD"
)

if [[ "$DELETE_REMOTE" == "1" ]]; then
  CMD+=(--delete --delete-delay)
fi

if [[ "$DRY_RUN" == "1" ]]; then
  CMD+=(--dry-run)
fi

CMD+=(
  "$ROOT/"
  "${DEPLOY_SSH_USER}@${DEPLOY_SSH_HOST}:${DEPLOY_SSH_REMOTE_ROOT%/}/"
)

"${CMD[@]}"

if [[ "$DRY_RUN" == "1" ]]; then
  echo "Preview concluido: nenhuma transferencia foi aplicada."
else
  echo "Deploy por SSH concluido."
fi
