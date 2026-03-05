# Checklist de Testes - Fase 5.3 (Relatorios premium)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `023_phase5_premium_reports.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `report.view`

## Acesso e navegacao
- [ ] Menu lateral exibe item `Relatorios` para perfil com permissao
- [ ] `GET /reports` carrega sem erro (auth + permission)
- [ ] Usuario sem `report.view` recebe `403` em `/reports` e exports

## Filtros
- [ ] Filtro por ano + faixa de meses altera recorte operacional e financeiro
- [ ] Filtro por orgao altera tabelas e KPIs
- [ ] Filtro por etapa (`status_code`) limita os casos operacionais
- [ ] Filtro por severidade SLA (`no_prazo`, `em_risco`, `vencido`) funciona
- [ ] Busca textual (`q`) filtra por pessoa/orgao/SEI/CPF
- [ ] Ordenacao e direcao aplicam no detalhe operacional

## Relatorio operacional
- [ ] KPIs exibem total, no prazo, em risco, vencido e tempo medio
- [ ] Tabela de gargalos lista etapa, casos e tempos medio/maximo
- [ ] Tabela detalhada lista pessoa, orgao, etapa, dias e nivel SLA
- [ ] Paginacao do detalhe operacional funciona

## Relatorio financeiro
- [ ] KPIs exibem previsto, efetivo, pago, a pagar, desvio e aderencia
- [ ] Tabela mensal exibe previsto/efetivo/pago/a pagar por mes
- [ ] Recorte por orgao impacta os totais financeiros

## Exportacoes
- [ ] `GET /reports/export/csv` baixa arquivo CSV com filtros aplicados
- [ ] CSV inclui resumo operacional, gargalos, detalhe e secao financeira
- [ ] `GET /reports/export/pdf` baixa arquivo PDF valido
- [ ] PDF inclui cabecalho com filtros + resumo operacional/financeiro
- [ ] `GET /reports/export/zip` baixa pacote ZIP de prestacao de contas
- [ ] ZIP inclui `relatorio/relatorio-premium.csv` e `relatorio/relatorio-premium.pdf`
- [ ] ZIP inclui `prestacao/boletos.csv`, `prestacao/pagamentos.csv` e `manifesto.txt`
- [ ] ZIP inclui anexos de boletos/comprovantes quando arquivos existirem no storage
- [ ] Exportacoes registram auditoria (`report:export_csv` / `report:export_pdf`)
- [ ] Exportacao ZIP registra auditoria (`report:export_zip`)
- [ ] Eventos `report.export_csv`, `report.export_pdf` e `report.export_zip` sao gravados em `system_events`
