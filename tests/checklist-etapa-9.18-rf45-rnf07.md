# Checklist de Validacao — Etapa 9.18 (RF-45 + RNF-07)

## 1) Pre-condicoes
- [ ] Base com migrations aplicadas e dados de boletos/reembolsos/espelhos para o periodo.
- [ ] Usuario com permissao `report.view` para acessar `/reports`.
- [ ] Usuario com permissao `security.view` para acessar `/ops/health-panel`.
- [ ] Pelo menos um snapshot em `storage/ops/health-panel` e `storage/ops/log-severity`.

## 2) RF-45 — Painel financeiro completo de status
- [ ] Acessar `GET /reports` e confirmar card "Painel financeiro por status".
- [ ] Validar exibicao de `Abertos`, `Vencidos`, `Pagos` e `Conciliados` (qtd + valor).
- [ ] Validar KPI de "Cobertura de conciliacao".
- [ ] Validar tabela "Status financeiro mensal" com colunas de qtd/valor por status.
- [ ] Aplicar filtro por orgao e confirmar alteracao coerente dos valores.
- [ ] Aplicar recorte de meses/ano e confirmar consistencia por competencia.
- [ ] Validar exportacao CSV (`/reports/export/csv`) contendo:
  - [ ] secao "Resumo financeiro por status"
  - [ ] secao "Status financeiro mensal"
- [ ] Validar exportacao PDF (`/reports/export/pdf`) contendo:
  - [ ] bloco "[FINANCEIRO POR STATUS]"
  - [ ] bloco "[STATUS FINANCEIRO MENSAL]"

## 3) RNF-07 — Observabilidade operacional estruturada
- [ ] Acessar `GET /ops/health-panel` e confirmar carregamento sem erro.
- [ ] Confirmar exibicao de:
  - [ ] status geral
  - [ ] totais de checks (ok/warn/fail)
  - [ ] recorrencias de erro
  - [ ] idade/frescor do snapshot KPI
- [ ] Confirmar tabela "Checks tecnicos" com status, mensagem e metrica.
- [ ] Confirmar tabela "Historico recente do painel tecnico".
- [ ] Confirmar secao "Severidade de logs (ultimo snapshot)" com top mensagens.
- [ ] Confirmar exibicao de caminhos de fonte (snapshots e `app.log`).
- [ ] Validar controle de acesso: usuario sem `security.view` deve receber 403.

## 4) Regressao minima
- [ ] `GET /reports` continua exportando CSV/PDF/ZIP sem regressao.
- [ ] `GET /security` permanece funcional.
- [ ] Menu principal renderiza item "Observabilidade" somente para perfil autorizado.

## 5) Validacao tecnica sugerida (CLI)
- [ ] `php -l app/Repositories/ReportRepository.php`
- [ ] `php -l app/Services/ReportService.php`
- [ ] `php -l app/Controllers/OpsHealthPanelController.php`
- [ ] `php -l app/Services/OpsHealthPanelService.php`
- [ ] `php -l app/Views/reports/index.php`
- [ ] `php -l app/Views/ops/health_panel.php`
- [ ] `php -l routes/web.php`
- [ ] `php scripts/ops-health-panel.php --skip-health --output json`
