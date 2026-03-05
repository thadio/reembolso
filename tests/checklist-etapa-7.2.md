# Checklist de Testes - Etapa 7.2 (Snapshots de KPI via cron)

## Pre-condicoes
- [ ] Aplicacao com banco acessivel e dashboard funcional
- [ ] Permissao de escrita em `storage/ops` e `storage/logs`
- [ ] Script executavel: `chmod +x scripts/kpi-snapshot.php` (se necessario)

## Geracao basica
- [ ] `./scripts/kpi-snapshot.php` retorna JSON com `status: ok`
- [ ] Arquivo `kpi_snapshot_*.json` e criado em `storage/ops/kpi_snapshots`
- [ ] JSON gerado contem `summary`, `status_distribution`, `recommendation` e `projections`

## Dry-run
- [ ] `./scripts/kpi-snapshot.php --dry-run` retorna JSON com `status: dry-run`
- [ ] Dry-run nao cria novo arquivo de snapshot

## Retencao
- [ ] `--retention-days 0` nao remove snapshots existentes
- [ ] `--retention-days 1` remove snapshots com mais de 1 dia
- [ ] Lista `removed_files` no retorno reflete arquivos removidos (ou candidatos no dry-run)

## Parametrizacao
- [ ] `OPS_KPI_SNAPSHOT_DIR` altera diretorio padrao de destino
- [ ] `OPS_KPI_SNAPSHOT_RETENTION_DAYS` altera retencao padrao
- [ ] `--output-dir` e `--retention-days` sobrescrevem os valores de ambiente

## Observabilidade
- [ ] Geracao real registra linha `kpi.snapshot.generated` em `storage/logs/app.log`

## Dashboard otimizado por snapshot
- [ ] Com snapshot recente existente, `GET /dashboard` indica fonte `snapshot KPI`
- [ ] Sem snapshot recente (ou snapshot expirado), `GET /dashboard` indica fonte `calculo ao vivo`
