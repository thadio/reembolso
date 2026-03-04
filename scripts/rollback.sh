#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
REF=""
RUN_HEALTHCHECK=true
RUN_MIGRATE=false
DRY_RUN=false

log() {
  printf '[rollback] %s\n' "$*"
}

fail() {
  printf '[rollback][error] %s\n' "$*" >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage: ./scripts/rollback.sh --ref <git_ref> [options]

Options:
  --ref <git_ref>      Commit, tag ou branch alvo do rollback (obrigatorio)
  --with-migrate       Executa migration apos trocar versao
  --skip-healthcheck   Nao roda health-check ao final
  --dry-run            Apenas simula comandos
  --help               Mostra esta ajuda

Examples:
  ./scripts/rollback.sh --ref v1.4.2
  ./scripts/rollback.sh --ref 1a2b3c4d --with-migrate
USAGE
}

run_cmd() {
  if [ "$DRY_RUN" = true ]; then
    log "[dry-run] $*"
    return
  fi
  "$@"
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --ref)
      REF="$2"
      shift 2
      ;;
    --with-migrate)
      RUN_MIGRATE=true
      shift
      ;;
    --skip-healthcheck)
      RUN_HEALTHCHECK=false
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

[ -n "$REF" ] || fail "informe --ref <git_ref>"

git -C "$ROOT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "repositorio git invalido em $ROOT_DIR"
git -C "$ROOT_DIR" rev-parse "$REF^{commit}" >/dev/null 2>&1 || fail "git_ref invalido: $REF"

if [ "$DRY_RUN" = false ]; then
  if ! git -C "$ROOT_DIR" diff --quiet || ! git -C "$ROOT_DIR" diff --cached --quiet; then
    fail "working tree com alteracoes locais. Commit/stash antes do rollback."
  fi
fi

CURRENT_REF="$(git -C "$ROOT_DIR" rev-parse --short HEAD)"
log "versao atual: $CURRENT_REF"
log "rollback para: $REF"

run_cmd git -C "$ROOT_DIR" fetch --all --tags --prune
run_cmd git -C "$ROOT_DIR" checkout "$REF"

if [ "$RUN_MIGRATE" = true ]; then
  run_cmd "$ROOT_DIR/scripts/deploy.sh" --migrate --skip-pull --skip-composer
fi

if [ "$RUN_HEALTHCHECK" = true ]; then
  run_cmd "$ROOT_DIR/scripts/healthcheck.sh"
fi

log "rollback concluido"
