# Checklist de Validacao — Etapa 9.19 (Evolucao assistida por IA)

## 1) Pre-condicoes
- [ ] Base com migrations aplicadas, incluindo `040_phase9_document_intelligence.sql`.
- [ ] Pessoa com documentos no Perfil 360 (`/people/show?id={id}`).
- [ ] Opcional: divergencias abertas em `cost_mirror_divergences` com `requires_justification=1` para validar sugestoes.
- [ ] Usuario com permissao `people.manage` para executar a conferencia assistida.

## 2) Conferencia assistida no dossie
- [ ] Acessar `GET /people/show?id={id}` e localizar o card "Conferencia assistida por IA" na secao Documentos.
- [ ] Acionar `Executar conferencia assistida` e confirmar mensagem de sucesso.
- [ ] Confirmar atualizacao dos KPIs do card:
  - [ ] docs analisados
  - [ ] extracoes
  - [ ] inconsistencias
  - [ ] sugestoes
  - [ ] alta severidade
- [ ] Validar detalhes de "Extracoes de campos": SEI, competencia, valor e confianca por documento.
- [ ] Validar detalhes de "Inconsistencias e sugestoes": tipo, severidade, descricao e confianca.

## 3) Regras e anomalias
- [ ] Documento com referencia SEI divergente da pessoa deve gerar finding de alta severidade.
- [ ] Documento sem referencia SEI deve gerar finding de ausencia de referencia.
- [ ] Documento sem competencia identificavel deve gerar finding de competencia ausente.
- [ ] Documento com referencia SEI repetida deve gerar finding de duplicidade.
- [ ] Quando houver base historica suficiente de valores, outlier estatistico deve gerar finding de anomalia de valor.

## 4) Sugestoes para divergencias recorrentes
- [ ] Havendo divergencias abertas com justificativas historicas similares, validar sugestao baseada em historico.
- [ ] Sem historico suficiente, validar sugestao fallback por template com valores previsto x espelho x diferenca.

## 5) Seguranca e permissao
- [ ] Usuario sem `people.manage` nao deve executar `POST /people/documents/intelligence/run`.
- [ ] Requisicao sem token CSRF deve ser rejeitada.

## 6) Regressao minima
- [ ] Upload/download de documentos continua funcional (`store`, `download`, `version/store`, `version/download`).
- [ ] Exportacao de dossie (`GET /people/dossier/export`) continua funcional.
- [ ] Conciliacao avancada (`/cost-mirrors/reconciliation/show`) continua funcional com workflow de justificativa/aprovacao.

## 7) Validacao tecnica sugerida (CLI)
- [ ] `php -l app/Repositories/DocumentIntelligenceRepository.php`
- [ ] `php -l app/Services/DocumentIntelligenceService.php`
- [ ] `php -l app/Controllers/PeopleController.php`
- [ ] `php -l app/Views/people/show.php`
- [ ] `php -l routes/web.php`
