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

escape_squote() {
  printf "%s" "$1" | sed "s/'/'\\\\''/g"
}

FTP_HOST="${FTP_HOST:-$(get_env_file_value FTP_HOST || printf 'ftp.thadio.com')}"
FTP_PORT="${FTP_PORT:-$(get_env_file_value FTP_PORT || printf '21')}"
FTP_USER="${FTP_USER:-$(get_env_file_value FTP_USER || true)}"
FTP_PASS="${FTP_PASS:-$(get_env_file_value FTP_PASS || true)}"
FTP_REMOTE_ROOT="${FTP_REMOTE_ROOT:-$(get_env_file_value FTP_REMOTE_ROOT || printf 'app.thadio.com')}"
FTP_SSL_VERIFY="${FTP_SSL_VERIFY:-$(get_env_file_value FTP_SSL_VERIFY || printf 'no')}"
FTP_SSL_ALLOW="${FTP_SSL_ALLOW:-$(get_env_file_value FTP_SSL_ALLOW || printf 'yes')}"
FTP_IGNORE_FILE="${FTP_IGNORE_FILE:-$ROOT/.ftpignore}"
FTP_MODE="${FTP_MODE:-full}"
FTP_PARALLEL="${FTP_PARALLEL:-6}"
FTP_REMOTE_ROOT="/${FTP_REMOTE_ROOT#/}"

FROM_REF="${FTP_FROM_REF:-}"
DRY_RUN="${FTP_DRY_RUN:-0}"
DELETE_REMOVED="${FTP_DELETE_REMOVED:-0}"
CONFIRM_DELETE="${FTP_CONFIRM_DELETE:-0}"

declare -a IGNORE_PATTERNS=()

tmp_upload=""
tmp_delete=""
tmp_lftp=""
tmp_ignore=""

usage() {
  cat <<'EOF'
Uso:
  scripts/deploy_ftp_changed.sh [opcoes]

Opcoes:
  --mode <full|changed> Modo de deploy.
                        full (padrao): compara projeto local inteiro com o remoto (mirror -R).
                        changed: incremental por diff do git.
  --from-ref <git_ref> Base para diff no modo changed.
  --parallel <n>       Numero de transferencias paralelas no modo full (padrao: 6).
  --dry-run            Mostra o plano sem transferir/remover arquivos.
  --delete-removed     Remove no FTP arquivos/pastas ausentes localmente.
  --confirm            Confirma explicitamente operacoes destrutivas (ex.: --delete-removed).
  -h, --help           Exibe esta ajuda.

Variaveis de ambiente:
  FTP_HOST         (padrao: ftp.thadio.com)
  FTP_PORT         (padrao: 21)
  FTP_USER         (obrigatorio para deploy real)
  FTP_PASS         (obrigatorio para deploy real)
  FTP_REMOTE_ROOT  (padrao: app.thadio.com)
  FTP_SSL_VERIFY   (padrao: no)
  FTP_SSL_ALLOW    (padrao: yes)
  FTP_IGNORE_FILE  (padrao: .ftpignore)
  FTP_MODE         (padrao: full)
  FTP_PARALLEL     (padrao: 6)
  FTP_CONFIRM_DELETE (padrao: 0)

Obs:
  1) O modo full resolve o problema de "arquivos ficando para tras" por nao depender
     de unstaged/staged; ele compara arvore local completa com o servidor.
  2) O .ftpignore aceita comentarios (#) e linhas vazias.
EOF
}

cleanup() {
  rm -f "$tmp_upload" "$tmp_delete" "$tmp_lftp" "$tmp_ignore"
}

fail() {
  echo "ERRO: $*" >&2
  exit 1
}

require_lftp() {
  command -v lftp >/dev/null 2>&1 || fail "lftp nao encontrado no PATH."
}

require_ftp_credentials() {
  [[ -n "$FTP_USER" ]] || fail "defina FTP_USER no ambiente ou no .env."
  [[ -n "$FTP_PASS" ]] || fail "defina FTP_PASS no ambiente ou no .env."
}

build_ignore_file() {
  {
    # Protecoes minimas sempre ativas.
    printf '%s\n' \
      '.git/' \
      '.git/*' \
      '.env' \
      '.env.*' \
      '.DS_Store' \
      '*/.DS_Store'

    if [[ -f "$FTP_IGNORE_FILE" ]]; then
      while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"
        line="$(trim_spaces "$line")"
        [[ -z "$line" ]] && continue
        [[ "$line" == \#* ]] && continue
        printf '%s\n' "$line"
      done <"$FTP_IGNORE_FILE"
    fi
  } | awk 'NF' | sort -u >"$tmp_ignore"

  IGNORE_PATTERNS=()
  while IFS= read -r pattern || [[ -n "$pattern" ]]; do
    [[ -z "$pattern" ]] && continue
    IGNORE_PATTERNS+=("$pattern")
  done <"$tmp_ignore"
}

path_is_excluded() {
  local path="${1#./}"
  local pattern=""
  local dir_pattern=""

  path="${path#/}"

  for pattern in "${IGNORE_PATTERNS[@]-}"; do
    [[ -z "$pattern" ]] && continue
    pattern="${pattern#/}"

    if [[ "$pattern" == */ ]]; then
      dir_pattern="${pattern%/}"
      if [[ "$path" == "$dir_pattern" || "$path" == "$dir_pattern/"* || "$path" == */"$dir_pattern" || "$path" == */"$dir_pattern/"* ]]; then
        return 0
      fi
      continue
    fi

    if [[ "$path" == $pattern || "$(basename "$path")" == $pattern ]]; then
      return 0
    fi
  done

  return 1
}

run_changed_mode() {
  tmp_upload="$(mktemp)"
  tmp_delete="$(mktemp)"

  if [[ -n "$FROM_REF" ]]; then
    git diff --name-only --diff-filter=ACMRTUXB "$FROM_REF" >>"$tmp_upload"
    git diff --name-only --diff-filter=D "$FROM_REF" >>"$tmp_delete"
    git ls-files --others --exclude-standard >>"$tmp_upload"
  else
    git diff --name-only --diff-filter=ACMRTUXB >>"$tmp_upload"
    git diff --name-only --cached --diff-filter=ACMRTUXB >>"$tmp_upload"
    git ls-files --others --exclude-standard >>"$tmp_upload"
    git diff --name-only --diff-filter=D >>"$tmp_delete"
    git diff --name-only --cached --diff-filter=D >>"$tmp_delete"
  fi

  sort -u "$tmp_upload" -o "$tmp_upload"
  sort -u "$tmp_delete" -o "$tmp_delete"

  declare -a uploads=()
  declare -a deletes=()

  while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    [[ -d "$path" ]] && continue
    [[ -f "$path" ]] || continue
    path_is_excluded "$path" && continue
    uploads+=("$path")
  done <"$tmp_upload"

  if [[ "$DELETE_REMOVED" == "1" ]]; then
    while IFS= read -r path; do
      [[ -z "$path" ]] && continue
      path_is_excluded "$path" && continue
      deletes+=("$path")
    done <"$tmp_delete"
  fi

  if [[ "${#uploads[@]}" -eq 0 && "${#deletes[@]}" -eq 0 ]]; then
    echo "Nenhum arquivo alterado elegivel para deploy (modo changed)."
    return 0
  fi

  echo "Arquivos para upload (changed): ${#uploads[@]}"
  for path in "${uploads[@]-}"; do
    [[ -z "$path" ]] && continue
    printf '  + %s\n' "$path"
  done

  if [[ "${#deletes[@]}" -gt 0 ]]; then
    echo "Arquivos para remover no FTP (changed): ${#deletes[@]}"
    for path in "${deletes[@]-}"; do
      [[ -z "$path" ]] && continue
      printf '  - %s\n' "$path"
    done
  fi

  if [[ "$DRY_RUN" == "1" ]]; then
    echo "Dry-run ativo: nenhuma transferencia foi executada."
    return 0
  fi

  require_ftp_credentials

  {
    printf 'set cmd:fail-exit yes\n'
    printf 'set ssl:verify-certificate %s\n' "$FTP_SSL_VERIFY"
    printf 'set ftp:ssl-allow %s\n' "$FTP_SSL_ALLOW"
    echo 'set net:max-retries 2'
    echo 'set net:timeout 20'
    echo 'set net:reconnect-interval-base 5'

    for path in "${uploads[@]-}"; do
      [[ -z "$path" ]] && continue
      dir_part="$(dirname "$path")"
      if [[ "$dir_part" == "." ]]; then
        remote_dir="$FTP_REMOTE_ROOT"
      else
        remote_dir="$FTP_REMOTE_ROOT/$dir_part"
      fi

      escaped_remote_dir="$(escape_squote "$remote_dir")"
      escaped_local_path="$(escape_squote "$path")"
      echo "mkdir -pf '$escaped_remote_dir'"
      echo "put -O '$escaped_remote_dir' '$escaped_local_path'"
    done

    for path in "${deletes[@]-}"; do
      [[ -z "$path" ]] && continue
      remote_path="$FTP_REMOTE_ROOT/$path"
      escaped_remote_path="$(escape_squote "$remote_path")"
      echo "rm -f '$escaped_remote_path'"
    done

    echo 'bye'
  } >"$tmp_lftp"

  lftp -u "$FTP_USER,$FTP_PASS" -p "$FTP_PORT" "$FTP_HOST" -e "$(cat "$tmp_lftp")"
  echo "Deploy FTP incremental (changed) concluido."
}

run_full_mode() {
  require_lftp
  require_ftp_credentials

  echo "Modo full: sincronizando arvore local completa com o FTP remoto."
  echo "Parallel transfers: $FTP_PARALLEL"
  echo "Delete remoto: $DELETE_REMOVED"
  echo "Dry-run: $DRY_RUN"

  {
    printf 'set cmd:fail-exit yes\n'
    printf 'set ssl:verify-certificate %s\n' "$FTP_SSL_VERIFY"
    printf 'set ftp:ssl-allow %s\n' "$FTP_SSL_ALLOW"
    echo 'set ftp:list-options -a'
    echo 'set net:max-retries 2'
    echo 'set net:timeout 20'
    echo 'set net:reconnect-interval-base 5'
    echo 'set net:reconnect-interval-multiplier 1'
    echo 'set net:reconnect-interval-max 20'
    echo 'set xfer:clobber yes'
    printf "lcd '%s'\n" "$(escape_squote "$ROOT")"

    printf 'mirror -R --verbose=1 --parallel=%s --scan-all-first --overwrite --no-perms --no-umask --no-empty-dirs ' "$FTP_PARALLEL"

    if [[ "$DELETE_REMOVED" == "1" ]]; then
      printf -- '--delete --delete-first '
    fi

    if [[ -s "$tmp_ignore" ]]; then
      printf -- "--exclude-glob-from='%s' " "$(escape_squote "$tmp_ignore")"
    fi

    if [[ "$DRY_RUN" == "1" ]]; then
      printf -- '--dry-run '
    fi

    printf "'.' '%s'\n" "$(escape_squote "$FTP_REMOTE_ROOT")"
    echo 'bye'
  } >"$tmp_lftp"

  lftp -u "$FTP_USER,$FTP_PASS" -p "$FTP_PORT" "$FTP_HOST" -e "$(cat "$tmp_lftp")"

  if [[ "$DRY_RUN" == "1" ]]; then
    echo "Dry-run full concluido: nenhuma transferencia efetiva foi feita."
  else
    echo "Deploy FTP full concluido."
  fi
}

while (($#)); do
  case "$1" in
    --mode)
      [[ $# -ge 2 ]] || fail '--mode exige um valor.'
      FTP_MODE="$2"
      shift 2
      ;;
    --from-ref)
      [[ $# -ge 2 ]] || fail '--from-ref exige um valor.'
      FROM_REF="$2"
      shift 2
      ;;
    --parallel)
      [[ $# -ge 2 ]] || fail '--parallel exige um valor inteiro >= 1.'
      FTP_PARALLEL="$2"
      shift 2
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    --delete-removed)
      DELETE_REMOVED=1
      shift
      ;;
    --confirm)
      CONFIRM_DELETE=1
      shift
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

if ! [[ "$FTP_PARALLEL" =~ ^[0-9]+$ ]] || [[ "$FTP_PARALLEL" -lt 1 ]]; then
  fail '--parallel/FTP_PARALLEL deve ser inteiro >= 1.'
fi

if [[ "$FTP_MODE" != "full" && "$FTP_MODE" != "changed" ]]; then
  fail "--mode invalido: $FTP_MODE (use full ou changed)."
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  fail 'este script precisa ser executado dentro de um repositorio git.'
fi

if [[ -n "$FROM_REF" ]] && ! git rev-parse --verify "${FROM_REF}^{commit}" >/dev/null 2>&1; then
  fail "git ref invalida: $FROM_REF"
fi

if [[ "$FTP_MODE" == "full" && -n "$FROM_REF" ]]; then
  fail '--from-ref so pode ser usado com --mode changed.'
fi

if [[ "$DELETE_REMOVED" == "1" && "$CONFIRM_DELETE" != "1" ]]; then
  fail '--delete-removed exige --confirm para evitar remocao acidental no servidor.'
fi

tmp_lftp="$(mktemp)"
tmp_ignore="$(mktemp)"
trap cleanup EXIT

if [[ ! -f "$FTP_IGNORE_FILE" ]]; then
  echo "Aviso: $FTP_IGNORE_FILE nao encontrado; usando somente exclusoes minimas internas."
fi

build_ignore_file

echo "FTP host: $FTP_HOST:$FTP_PORT"
echo "FTP root remoto: $FTP_REMOTE_ROOT"
echo "FTP mode: $FTP_MODE"
echo "FTP ignore file: $FTP_IGNORE_FILE"

if [[ "$FTP_MODE" == "full" ]]; then
  run_full_mode
else
  run_changed_mode
fi
