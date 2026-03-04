# Checklist de Testes — Etapa 7.1 (Backup e restore)

## Pre-condicoes
- [ ] Ambiente com `.env` valido e acesso ao banco
- [ ] Ferramentas instaladas: `mysqldump`, `mysql`, `tar`, `gzip`
- [ ] Permissao de escrita no diretorio de backup (`BACKUP_ROOT` ou `storage/backups`)

## Backup
- [ ] `./scripts/backup.sh --dry-run --label teste` executa sem erro
- [ ] `./scripts/backup.sh --label teste` gera pasta `backup_<timestamp>_teste`
- [ ] Backup gera `db.sql.gz` quando DB nao esta em `--skip-db`
- [ ] Backup gera `uploads.tar.gz` quando storage nao esta em `--skip-storage`
- [ ] Backup com `--with-env` gera `.env.snapshot` com permissao restrita
- [ ] Backup gera `manifest.txt` com hash e tamanho dos artefatos

## Retencao
- [ ] `BACKUP_RETENTION_DAYS` remove backups antigos conforme configuracao
- [ ] Backup atual nao e removido pelo processo de retencao

## Restore
- [ ] `./scripts/restore.sh --from <backup_dir> --dry-run --yes` executa sem erro
- [ ] `./scripts/restore.sh --from <backup_dir> --storage-only --yes` restaura `storage/uploads`
- [ ] Restore de storage cria snapshot local `uploads.pre_restore_<timestamp>`
- [ ] `./scripts/restore.sh --from <backup_dir> --yes` restaura banco sem erro
- [ ] Restore sem `--yes` bloqueia execucao destrutiva

## Pos-restore
- [ ] `./scripts/healthcheck.sh` retorna status `ok`
- [ ] Arquivos esperados continuam acessiveis em `storage/uploads`
- [ ] Dados de validacao no banco estao consistentes com o backup restaurado
