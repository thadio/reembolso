# Changelog

## 2026-03-05 — Ciclo 9.19 concluido (Evolucao assistida por IA no dossie documental)
- Entregue conferencia assistida por IA no Perfil 360 com execucao manual protegida:
  - nova rota `POST /people/documents/intelligence/run` (permissao `people.manage` + CSRF)
  - acao e painel de resultados incorporados em `app/Views/people/show.php`
- Nova base de dados para inteligencia documental:
  - migration `040_phase9_document_intelligence.sql`
  - tabelas `document_ai_reviews`, `document_ai_extractions` e `document_ai_findings`
- Novo modulo de dominio:
  - `DocumentIntelligenceRepository` (persistencia e consultas de extracoes/findings/historico)
  - `DocumentIntelligenceService` com:
    - extracao automatica de campos (SEI, CPF, competencia e valor) a partir do dossie
    - deteccao de inconsistencias por regras (SEI divergente/ausente, competencia ausente, duplicidade)
    - deteccao de anomalias de valor por referencia estatistica (z-score por tipo documental)
    - sugestao automatica de justificativas para divergencias recorrentes (`cost_mirror_divergences`) usando historico + fallback por template
- `PeopleController` ampliado para:
  - expor `documentIntelligence` no payload do Perfil 360
  - executar `runDocumentIntelligence` com trilha de auditoria/eventos
- Checklist da etapa adicionado em `tests/checklist-etapa-9.19-ia-assistida.md`.

## 2026-03-05 — Ciclo 9.18 concluido (RF-45 + RNF-07: painel financeiro completo e observabilidade estruturada)
- Fechamento do `RF-45` no modulo de relatorios premium (`GET /reports`):
  - consolidado financeiro por status `abertos`, `vencidos`, `pagos` e `conciliados`
  - visao mensal por status (quantidade e valor) no painel web
  - dataset financeiro ampliado em `ReportRepository::financialStatusDataset`
  - exportacoes CSV/PDF atualizadas para incluir secoes de status financeiro
- Fechamento do `RNF-07` com painel estruturado na aplicacao:
  - nova rota protegida `GET /ops/health-panel` (permissao `security.view`)
  - novos componentes `OpsHealthPanelController` e `OpsHealthPanelService`
  - view `app/Views/ops/health_panel.php` com:
    - status geral e totais de checks
    - detalhamento de checks tecnicos (health/log severity/error review/kpi freshness)
    - historico recente de snapshots
    - severidade de logs com top mensagens
    - fontes operacionais (snapshot files e app.log)
  - menu principal atualizado com atalho "Observabilidade"
- Configuracao operacional ampliada:
  - novas chaves `OPS_HEALTH_PANEL_SNAPSHOT_DIR` e `OPS_LOG_SEVERITY_SNAPSHOT_DIR` em `Config`
- Checklist da etapa adicionado em `tests/checklist-etapa-9.18-rf45-rnf07.md`.

## 2026-03-05 — Ciclo 9.17 concluido (Governanca de cadastro: importacao CSV de orgaos)
- Entregue importacao CSV em massa para orgaos com rota protegida:
  - `POST /organs/import-csv`
- Fluxo implementado no modulo de orgaos:
  - upload de CSV com validacao de extensao/MIME/tamanho (ate 5MB)
  - deteccao automatica de delimitador (`,`/`;`/TAB) e suporte a aliases de cabecalho
  - modo de simulacao (`validate_only=1`) sem persistencia
  - importacao transacional com rollback completo em caso de erro
- `OrganService` ampliado com:
  - `importCsv` (validacao de cabecalho/linhas, deduplicacao por CNPJ e persistencia em lote)
  - parser CSV interno com normalizacao de campos e mensagens por linha
- `OrganRepository` ampliado com suporte transacional (`beginTransaction`, `commit`, `rollBack`) para importacao em lote segura.
- `OrgansController` ampliado:
  - novo endpoint `importCsv` com tratamento de resumo de erros para feedback operacional
- UI:
  - `app/Views/organs/index.php` recebeu formulario de importacao CSV com opcao "Apenas validar (sem gravar)"
- Checklist da etapa adicionado em `tests/checklist-etapa-9.17-importacao-csv-orgaos.md`.

## 2026-03-05 — Ciclo 9.16 concluido (Gestao executiva: simulacao previa da aprovacao final)
- Entregue simulacao previa para aprovacao final de lotes de pagamento com rota protegida:
  - `POST /invoices/payment-batches/final-approval/simulate`
- Fluxo do detalhe do lote atualizado com duas etapas:
  - simulacao de risco para status final alvo (`pago` ou `cancelado`)
  - atualizacao de status com validacao de token de simulacao (expiracao e integridade do lote)
- `InvoiceService` ampliado com:
  - `simulatePaymentBatchFinalApproval` (analise de risco, qualidade dos itens e janela de validade)
  - bloqueio de transicao final sem simulacao valida em `updatePaymentBatchStatus`
  - nova governanca por auditoria/evento:
    - auditoria `payment_batch:final_approval.simulate`
    - evento `payment_batch.final_approval_simulated`
- `InvoicesController` ampliado:
  - novo endpoint `simulatePaymentBatchFinalApproval`
  - gestao de sessao para token de simulacao previa por lote
- UI do detalhe do lote (`app/Views/invoices/payment_batches/show.php`) atualizada com:
  - card de simulacao previa (status alvo, risco, comprovantes/processos faltantes e validade)
  - envio do token de simulacao no formulario de mudanca de status
- Checklist da etapa adicionado em `tests/checklist-etapa-9.16-simulacao-previa-aprovacao-final.md`.

## 2026-03-05 — Ciclo 9.15 concluido (Gestao executiva: busca global unificada)
- Entregue modulo de busca global com rota protegida:
  - `GET /global-search`
- Novo eixo de consulta unificada por termo (`q`) e escopo (`scope`):
  - pessoas (`CPF`, `SEI`, nome, tags e orgao)
  - orgaos (nome, sigla, CNPJ e localidade)
  - processo formal/DOU (oficio, protocolo, `dou_edition`, link DOU, SEI, pessoa e orgao)
  - documentos do dossie (titulo, tipo, referencia SEI, tags, arquivo, pessoa e orgao)
- Novos componentes:
  - `GlobalSearchRepository` com consultas SQL por secao
  - `GlobalSearchService` com normalizacao de filtros, controle de escopo e trilha de auditoria/evento de busca
  - `GlobalSearchController` para orquestracao HTTP/UI
  - view `app/Views/global_search/index.php` com KPIs por secao, tabelas de resultado e links de aprofundamento
- Seguranca aplicada:
  - endpoint sob `people.view`
  - resultados de documentos respeitam sensibilidade (`restricted/sensitive` so aparecem com `people.documents.sensitive`)
  - CPF mascarado quando o usuario nao possui `people.cpf.full`
- Navegacao:
  - item de menu principal `Busca global` adicionado no layout para perfis com acesso aos modulos consultaveis
- Governanca/trilha:
  - auditoria `entity=global_search` com `action=search`
  - evento `global_search.executed`
- Checklist da etapa adicionado em `tests/checklist-etapa-9.15-busca-global.md`.

## 2026-03-05 — Ciclo 9.14 concluido (Gestao executiva: lotes de pagamento)
- Criada migration `039_phase9_payment_batches.sql` com as tabelas:
  - `payment_batches` (cabecalho do lote: codigo, status, referencia, previsto, totais e trilha de fechamento)
  - `payment_batch_items` (itens por pagamento com vinculo para `payments` e `invoices`)
- `InvoiceRepository` ampliado com operacoes de lotes:
  - listagem paginada de lotes com filtros (`paginatePaymentBatches`)
  - detalhe e itens do lote (`findPaymentBatchById`, `paymentBatchItems`)
  - consulta de pagamentos elegiveis (`paymentBatchCandidates`, `findEligiblePaymentsForBatchByIds`)
  - persistencia de lote/itens e atualizacao de status (`createPaymentBatch`, `addPaymentToBatch`, `updatePaymentBatchStatus`)
- `InvoiceService` ampliado com regras de negocio de lotes:
  - criacao transacional de lote com validacao de elegibilidade, totalizacao, auditoria (`payment_batch:create`) e evento (`payment_batch.created`)
  - atualizacao de status com controle de transicao, fechamento (`closed_by`, `closed_at`), auditoria (`payment_batch:status.update`) e evento (`payment_batch.status_updated`)
  - normalizacao de filtros e status labels para UI
- Novas rotas protegidas no modulo de boletos:
  - `GET /invoices/payment-batches`
  - `GET /invoices/payment-batches/show`
  - `POST /invoices/payment-batches/store`
  - `POST /invoices/payment-batches/status/update`
- `InvoicesController` ampliado com endpoints de listagem/detalhe/criacao/atualizacao de status de lotes.
- UI:
  - nova tela `app/Views/invoices/payment_batches/index.php` (filtros, tabela de lotes e formulario de criacao por selecao de pagamentos elegiveis)
  - nova tela `app/Views/invoices/payment_batches/show.php` (detalhe, itens e atualizacao de status)
  - acesso rapido para lotes adicionado em `app/Views/invoices/index.php`
- Checklist da etapa adicionado em `tests/checklist-etapa-9.14-lotes-pagamento.md`.

## 2026-03-05 — Ciclo 9.12 concluido (Gestao executiva: painel de gargalos e ranking de orgaos)
- Dashboard executivo incorporado em `GET /dashboard` com novo bloco de governanca:
  - KPIs executivos (`orgaos monitorados`, `casos monitorados`, `em risco`, `vencidos`)
  - tabela de gargalos por etapa (impacto por orgao, media/maximo de dias e criticidade SLA)
  - ranking de orgaos por score de severidade (risco/vencido + tempo medio em etapa)
  - acao rapida para aprofundamento em `/reports`
- `DashboardRepository` ampliado com consultas executivas:
  - `executiveBottlenecks`
  - `executiveOrganRanking`
  - calculo unificado de `daysInStatus` e `slaLevel` no proprio repositorio
- `DashboardService` ampliado:
  - novo bloco `executive_panel` no payload de `overview`
  - normalizacao e consolidacao de resumo executivo (totais, criticidade e score)
  - compatibilidade com leitura de snapshot legado (fallback para calculo live do painel executivo quando ausente)
- Snapshot KPI atualizado:
  - `scripts/kpi-snapshot.php` passa a persistir `executive_panel` no JSON
- Checklist da etapa adicionado em `tests/checklist-etapa-9.12-painel-executivo.md`.

## 2026-03-05 — Ciclo 9.11 concluido (Governanca: controle de versao de documentos)
- Criada migration `037_phase9_document_version_control.sql` com tabela `document_versions` e backfill inicial (V1) para documentos legados.
- `DocumentRepository` ampliado com operacoes de versionamento:
  - criacao de versao (`createVersion`)
  - calculo de proxima versao (`nextVersionNumber`)
  - leitura de historico por documento (`versionsByDocumentIds`)
  - download de versao por pessoa/documento (`findVersionByIdForPerson`)
  - sincronizacao do arquivo corrente em `documents` (`updateDocumentCurrentFile`)
- `DocumentService` ampliado para:
  - registrar versao inicial (V1) durante upload padrao de documentos
  - criar nova versao por documento (`createDocumentVersion`) com validacoes de upload/hardening e trilha de auditoria/LGPD
  - baixar versoes historicas (`documentVersionForDownload`) com controle de acesso por sensibilidade
  - expor historico de versoes no `profileData` do Perfil 360
- `PeopleController` e rotas atualizados com:
  - `POST /people/documents/version/store`
  - `GET /people/documents/version/download`
- `app/Views/people/show.php` atualizado:
  - badge da versao atual por documento
  - formulario de envio de nova versao por card
  - historico de versoes com download individual
- `public/assets/css/app.css` ajustado para estilos de historico/versionamento, incluindo responsividade mobile.
- Checklist da etapa adicionado em `tests/checklist-etapa-9.11-versionamento-documentos.md`.

## 2026-03-05 — Ciclo 9.10 concluido (Governanca: relatorios CGU/TCU + dossie completo ZIP/PDF/trilha)
- Fechamento da pendencia de governanca 6.2 para relatorios prontos de auditoria:
  - migration `036_phase6_audit_reports_indexes.sql` aplicada com indices de apoio em `audit_log`, `sensitive_access_logs`, `analyst_pending_items` e `cost_mirror_divergences`
  - `ReportRepository` ampliado com datasets dedicados para auditoria (`auditCriticalRows`, `auditSensitiveAccessRows`, `auditOpenPendingRows`, `auditUnresolvedDivergenceRows`)
  - `ReportService::exportAuditZip` entregue com pacote:
    - `auditoria/trilha_critica_auditoria.csv`
    - `auditoria/acessos_sensiveis.csv`
    - `auditoria/pendencias_abertas.csv`
    - `auditoria/divergencias_sem_justificativa.csv`
    - `manifesto_auditoria.txt`
  - nova rota `GET /reports/export/audit-zip` e botao de exportacao no modulo `/reports`
- Fechamento da pendencia de exportacao completa de dossie (ZIP/PDF + trilha):
  - novo servico `PersonDossierExportService` para montagem do pacote por pessoa/processo
  - novo endpoint `GET /people/dossier/export?person_id={id}` em `PeopleController::exportDossier`
  - pacote do dossie com:
    - CSVs de pessoa, timeline operacional, documentos, comentarios internos, timeline administrativa e financeiro de reembolso
    - PDF de sintese (`dossie/resumo_dossie.pdf`)
    - trilha (`trilha/auditoria.csv` quando houver `audit.view`; fallback `trilha/auditoria.txt` sem permissao)
    - anexo de arquivos fisicos de documentos/timeline quando disponiveis
    - manifesto (`manifesto_dossie.txt`)
  - acao de exportacao adicionada no cabecalho do Perfil 360 (`people/show`)
- Governanca de trilha e evento:
  - auditoria `person:export_dossier_zip`
  - evento `person.dossier_exported`
  - auditoria/evento de `report.export_audit_zip` mantidos no modulo de relatorios
- Validacoes executadas:
  - `php db/migrate.php` (com aplicacao de `036_phase6_audit_reports_indexes.sql`)
  - smoke test automatizado dos pacotes ZIP de auditoria e dossie (estrutura de arquivos validada)
  - `php scripts/financial-unit-tests.php --output json` (`status=ok`)
  - `php scripts/ops-quality-gate.php --output json` (`status=ok`)
  - `php scripts/kpi-snapshot.php` seguido de `php scripts/ops-health-panel.php --skip-health --output json` (`status=warn`, sem falha de frescor de snapshot)

## 2026-03-05 — Ciclo 9.9 concluido (Governanca: historico consolidado de pessoa e orgao)
- Criada migration `035_phase6_consolidated_history_indexes.sql` com indices de apoio para leitura de historico consolidado:
  - `audit_log(entity, entity_id, created_at)`
  - `people(organ_id, deleted_at, id)`
- Novo modulo de auditoria consolidada por orgao:
  - `OrganAuditRepository` com escopo cruzado (orgao + pessoas vinculadas + entidades operacionais/financeiras relacionadas)
  - `OrganAuditService` com filtros (`entity`, `action`, `q`, periodo), paginacao e exportacao
- Modulo de orgaos ampliado:
  - `OrgansController::show` agora carrega historico consolidado (quando usuario possui `audit.view`)
  - nova exportacao CSV em `OrgansController::exportAudit`
  - nova rota `GET /organs/audit/export` (protegida por `audit.view`)
  - `app/Views/organs/show.php` atualizado com card "Historico consolidado de pessoa e orgao", filtros, lista paginada e exportacao
- Escopo de auditoria por pessoa ampliado:
  - `PersonAuditRepository` passa a incluir eventos da entidade `organ` (orgao atual da pessoa)
  - inclui tambem entidade `analyst_pending_item` no Perfil 360
- UI de pessoa ajustada:
  - `people/show` recebeu novos rotulos de entidade (`organ` e `analyst_pending_item`) na secao Auditoria.
- Checklist da etapa adicionado em `tests/checklist-etapa-9.9-historico-consolidado-pessoa-orgao.md`.

## 2026-03-05 — Ciclo 9.8 concluido (Governanca: timeline administrativa completa por processo)
- Criada migration `034_phase6_process_admin_timeline.sql` com tabela `process_admin_timeline_notes` para notas administrativas por pessoa/movimentacao (status, severidade, fixacao, trilha de criacao/atualizacao/exclusao).
- Novo modulo de timeline administrativa:
  - `ProcessAdminTimelineRepository` para consolidacao de fontes administrativas (notas manuais, comentarios internos, pendencias, financeiro de reembolso, metadados formais e timeline operacional)
  - `ProcessAdminTimelineService` com consolidacao, filtros (`q`, origem e grupo de status), ordenacao, paginacao e KPI de acompanhamento
  - CRUD de nota manual (criar/editar/status/excluir) com validacao de vinculo `assignment_id` por pessoa
- Novas rotas:
  - `POST /people/process-admin-timeline/store`
  - `POST /people/process-admin-timeline/update`
  - `POST /people/process-admin-timeline/delete`
- Governanca e trilha:
  - auditoria em `process_admin_timeline_note:create`, `process_admin_timeline_note:update`, `process_admin_timeline_note:status.update` e `process_admin_timeline_note:delete`
  - eventos `process_admin_timeline.note_created`, `process_admin_timeline.note_updated`, `process_admin_timeline.note_status_updated` e `process_admin_timeline.note_deleted`
  - `PersonAuditRepository` ampliado para incluir `process_admin_timeline_note` no escopo de auditoria do Perfil 360
- UI:
  - Perfil 360 (`people/show`) ganhou card de timeline administrativa completa com:
    - KPIs (`Total`, `Abertos`, `Concluidos`, `Notas manuais`, `Entradas automaticas`)
    - filtros por busca/origem/status
    - lista consolidada paginada com badges de origem/status/severidade
    - formulario de nota manual e edicao/exclusao de notas manuais
- Ajustes de compatibilidade de esquema:
  - consultas de `process_metadata` e `timeline_events` ajustadas para colunas reais da base atual, evitando falhas de coluna inexistente durante consolidacao.
- Checklist da etapa adicionado em `tests/checklist-etapa-9.8-timeline-administrativa.md`.

## 2026-03-05 — Ciclo 9.7 concluido (Produtividade: comentarios internos por processo)
- Criada migration `033_phase9_process_internal_comments.sql` com tabela `process_comments` para registrar observacoes internas por pessoa/movimentacao.
- Novo modulo de comentarios internos:
  - `ProcessCommentRepository` e `ProcessCommentService` para CRUD de comentarios (criar, editar, arquivar/fixar e exclusao logica)
  - validacoes de texto, status e vinculo de movimentacao (`assignment_id`) com a pessoa
  - resumo por pessoa (total, abertos, arquivados e fixados)
- Novas rotas:
  - `POST /people/process-comments/store`
  - `POST /people/process-comments/update`
  - `POST /people/process-comments/delete`
- Governanca e trilha:
  - auditoria em `process_comment:create`, `process_comment:update`, `process_comment:status.update` e `process_comment:delete`
  - eventos `process_comment.created`, `process_comment.updated`, `process_comment.status_updated` e `process_comment.deleted`
  - `PersonAuditRepository` ampliado para incluir `process_comment` no escopo de auditoria do Perfil 360
- UI:
  - Perfil 360 (`people/show`) ganhou card de comentarios internos com formulario, lista e edicao/arquivamento/exclusao
  - novos KPIs de comentarios no perfil (total, abertos, arquivados e fixados)
- Checklist da etapa adicionado em `tests/checklist-etapa-9.7-comentarios-processo.md`.

## 2026-03-05 — Ciclo 9.6 concluido (Produtividade: calculadora automatica de reembolso)
- Criada migration `032_phase9_reimbursement_calculator.sql` para incluir `reimbursement_entries.calculation_memory`.
- Reembolso ampliado com calculadora automatica:
  - `ReimbursementService` passou a aceitar componentes de calculo (`base`, `transporte`, `hospedagem`, `alimentacao`, `outros`, `ajuste percentual` e `desconto`)
  - total e calculado automaticamente no backend pela formula:
    - `(Base + Transporte + Hospedagem + Alimentacao + Outros) + Ajuste - Desconto`
  - memoria detalhada de calculo e persistida em JSON por lancamento
  - validacoes de borda para componentes negativos, percentual fora da faixa e total <= 0
- Perfil 360 atualizado na secao de reembolsos:
  - formulario com bloco da calculadora e preview de total
  - detalhes da memoria de calculo em cada lancamento
  - tabela de memorias de calculo recentes por pessoa
- Governanca e trilha:
  - auditoria de `reimbursement_entry:create` agora inclui flag/formula de calculo quando aplicado
  - evento `reimbursement.entry_created`/`reimbursement.entry_paid_created` recebe indicador `calculated`
- Checklist da etapa adicionado em `tests/checklist-etapa-9.6-calculadora-reembolso.md`.

## 2026-03-05 — Ciclo 9.5 concluido (Produtividade: central de pendencias)
- Criada migration `031_phase9_pending_center.sql` com tabela `analyst_pending_items` para consolidacao de pendencias operacionais por pessoa/movimentacao.
- Novo modulo de central de pendencias:
  - `PendingCenterRepository` e `PendingCenterService` para sincronizacao automatica e consulta paginada
  - regras automaticas para gerar pendencias de:
    - documentos obrigatorios ausentes no dossie por etapa do pipeline
    - divergencias financeiras sem justificativa obrigatoria
    - retornos externos atrasados por permanencia em etapa de retorno
  - resolucao automatica de pendencias obsoletas quando causa deixa de existir
- Novas rotas:
  - `GET /people/pending` (painel consolidado)
  - `POST /people/pending/status` (resolver/reabrir pendencia)
- Governanca e trilha:
  - auditoria em `analyst_pending_item:create`, `analyst_pending_item:sync.update`, `analyst_pending_item:auto.resolve` e `analyst_pending_item:status.update`
  - eventos `pending.created`, `pending.auto_resolved` e `pending.status_updated`
- UI:
  - nova tela `app/Views/people/pending.php` com KPI, filtros por tipo/status/severidade/fila e acoes de resolucao
  - acesso rapido \"Central de pendencias\" adicionado na listagem de pessoas
- Checklist da etapa adicionado em `tests/checklist-etapa-9.5-central-pendencias.md`.

## 2026-03-05 — Ciclo 9.4 concluido (Produtividade: checklist automatico por tipo de caso)
- Criada migration `030_phase9_case_checklist_automation.sql` com:
  - novas tabelas `assignment_checklist_templates` e `assignment_checklist_items`
  - templates padrao por tipo de caso (`geral`, `cessao`, `cft`, `requisicao`)
  - chaves/indices para consulta por assignment e controle de conclusao
- Pipeline ampliado para checklist automatico:
  - `PipelineRepository` com leitura de templates, upsert de itens e update de status dos itens
  - `PipelineService` passou a gerar checklist automaticamente com base no tipo de caso (modalidade) e calcular progresso
  - nova acao `PipelineService::updateChecklistItem` com validacao, auditoria (`assignment_checklist_item:status.update`) e evento (`pipeline.checklist_item_updated`)
- Perfil 360 atualizado:
  - card de pipeline exibe checklist automatico, progresso e itens obrigatorios/opcionais
  - formulario por item para marcar concluido/pendente via `POST /people/pipeline/checklist/update`
- Checklist da etapa adicionado em `tests/checklist-etapa-9.4-checklist-automatico.md`.

## 2026-03-05 — Ciclo 9.3 concluido (Produtividade: painel "Minha fila")
- Criada migration `029_phase9_analyst_queue_productivity.sql` com:
  - novas colunas em `assignments`: `assigned_user_id` e `priority_level` (`low|normal|high|urgent`)
  - indices para consulta de fila por responsavel/prioridade
  - normalizacao de dados legados de prioridade e responsavel
- Pipeline ampliado para gestao de fila:
  - `PipelineRepository` passou a expor responsavel/prioridade na consulta de assignment
  - `PipelineService::updateQueue` com validacao, auditoria (`assignment:queue.update`) e evento (`pipeline.queue_updated`)
  - novos metodos de apoio para opcoes de prioridade e usuarios atribuiveis
- Pessoas (listagem e perfil):
  - filtro "Minha fila" por `queue_scope`, `responsible_id` e `priority` em `/people`
  - novas colunas de listagem: `Responsável` e `Prioridade`
  - card de pipeline no Perfil 360 com formulario `POST /people/pipeline/queue/update`
- Checklist da etapa adicionado em `tests/checklist-etapa-9.3-fila.md`.

## 2026-03-05 — Ciclo 9.2 concluido (RF-04: importacao CSV em massa de pessoas)
- Entregue fluxo de importacao no modulo de pessoas:
  - nova rota protegida `POST /people/import-csv` (`people.manage` + CSRF)
  - formulario de importacao na listagem de pessoas com opcao de simulacao (`validate_only=1`)
- `PeopleService` ampliado com motor de importacao CSV:
  - leitura robusta de CSV (`,`, `;` e tab), validacao de extensao/MIME/tamanho (ate 5MB) e limpeza de BOM
  - mapeamento de cabecalhos com aliases (`name|nome`, `organ|orgao`, etc.)
  - validacao por linha reutilizando regras de cadastro de pessoa
  - resolucao de referencias por ID/nome/sigla para orgao, ID/nome para modalidade e nome/codigo para lotacao MTE
  - deteccao de CPF duplicado no proprio arquivo e no banco
  - modo simulacao sem persistencia
  - importacao efetiva com transacao e rollback completo em caso de falha
- `PeopleRepository` recebeu suporte transacional (`beginTransaction`, `commit`, `rollBack`) para garantir atomicidade da importacao.
- `PeopleController` passou a iniciar automaticamente o pipeline (`ensureAssignment`) para cada pessoa criada via importacao.
- Checklist da etapa adicionado em `tests/checklist-etapa-9.2-rf04.md`.

## 2026-03-05 — Ciclo 9.1 concluido (RF-22: acesso granular a documentos sensiveis)
- Criada migration `028_phase6_document_sensitivity_access.sql` com:
  - coluna `documents.sensitivity_level` (`public`, `restricted`, `sensitive`) e indice dedicado
  - normalizacao de registros legados para `public`
  - permissao `people.documents.sensitive` vinculada a `sist_admin` e `admin`
- Dossie documental reforcado:
  - `DocumentRepository` passou a persistir/consultar `sensitivity_level`
  - `DocumentService` valida classificacao de sensibilidade no upload e exige permissao para classificar `restricted/sensitive`
  - listagem do Perfil 360 filtra documentos sensiveis para perfis sem permissao
  - download de documento sensivel sem permissao agora gera negacao auditavel (`download_denied`) com evento `document.download_denied`
  - trilha LGPD detalhada para download permitido/negado com sensibilidades `document_public`, `document_restricted` e `document_sensitive`
- `PeopleController` atualizado para repassar capacidade de acesso/classificacao sensivel ao `DocumentService`.
- `people/show` atualizado com seletor de sensibilidade e badges de classificacao por documento.
- `db/seed.php` atualizado para incluir `people.documents.sensitive` no bootstrap padrao.
- Checklist da entrega adicionado em `tests/checklist-etapa-9.1-rf22.md`.

## 2026-03-05 — Etapa 7.4 concluida (Observabilidade operacional)
- Fechamento da etapa 7.4 com os tres eixos previstos:
  - painel tecnico de saude em `scripts/ops-health-panel.php` (health endpoint, severidade de logs, recorrencia e frescor de KPI snapshot)
  - logs estruturados por severidade em `scripts/log-severity.php` (totais, serie temporal, top mensagens, snapshot e limiar de falha)
  - revisao de erros recorrentes em `scripts/error-review.php` (agrupamento por assinatura, recorrencia e relatorio markdown)
- Gate operacional consolidado em `scripts/ops-quality-gate.php` para execucao recorrente dos checks de QA/logs.
- Documentacao operacional atualizada com runbook de observabilidade e fase 7 marcada como concluida no plano.

## 2026-03-05 — Etapa 7.3 concluida (Qualidade e testes)
- Entregue suite automatizada da etapa 7.3 para regras financeiras:
  - `scripts/financial-unit-tests.php` com assertions unitarias de formulas/bordas em `DashboardService` e `BudgetService`
  - `scripts/financial-integration-tests.php` com fixture fixa (`tests/fixtures/qa_regression_dataset.sql`) para validar deltas de KPI e fluxo de simulacao orcamentaria
  - `scripts/phase7-3-tests.php` como runner consolidado (`unit + integration + qa-regression`)
- Ajuste de determinismo no QA:
  - `scripts/qa-regression.php` passou a forcar leitura live (`overview(..., false)`), evitando interferencia de snapshot KPI na validacao de delta
- Documentacao/checklist atualizados para fechar oficialmente a etapa 7.3.

## 2026-03-05 — Etapa 7.2 concluida (Performance e processamento)
- Consolidacao da etapa 7.2 com os tres eixos previstos:
  - indices adicionais para filtros de alto volume (migration `014_phase7_performance_indexes.sql`)
  - snapshots de KPI/projecoes via cron (`scripts/kpi-snapshot.php`)
  - otimizacao de consultas pesadas do dashboard por reaproveitamento de snapshot fresco
- Dashboard otimizado:
  - `DashboardService` passou a ler snapshot em `storage/ops/kpi_snapshots` quando dentro da janela de frescor
  - fallback automatico para calculo ao vivo quando snapshot estiver ausente/expirado
  - indicador de fonte de dados no dashboard (`snapshot KPI` ou `calculo ao vivo`)
- Configuracao operacional adicionada:
  - `OPS_KPI_SNAPSHOT_MAX_AGE_MINUTES` para controlar idade maxima aceita do snapshot no dashboard

## 2026-03-05 — Fase 6.3 concluida (Seguranca reforcada: politica de senha, lockout configuravel e hardening de upload)
- Criada migration `027_phase6_security_hardening.sql` com:
  - novas colunas em `users`: `password_changed_at` e `password_expires_at`
  - nova coluna em `login_attempts`: `lockout_until`
  - nova tabela `security_settings` para politica de senha, lockout de login e limite global de upload
  - permissoes `security.view` e `security.manage` para `sist_admin` e `admin`
- Implementado modulo de seguranca:
  - `SecuritySettingsRepository`, `SecuritySettingsService` e `SecurityController`
  - view `app/Views/security/index.php` com gerenciamento da politica de senha, bloqueio e upload
  - rotas protegidas:
    - `GET /security`
    - `POST /security/update`
- Hardening de autenticacao:
  - `RateLimiter` atualizado para lockout explicito por janela configuravel
  - `Auth` atualizado para consumir politica dinamica de lockout e carregar expiracao de senha
  - forca troca de senha expirada via middleware (`/users/password`)
- Hardening de upload aplicado:
  - validacao de nome seguro, upload nativo HTTP e assinatura binaria (PDF/PNG/JPEG)
  - remocao de fallback `rename` em uploads de documentos, timeline, boleto/comprovante e anexo DOU
  - integracao de limite global de upload (fase 6.3) com teto por modulo
- Fluxo de usuarios reforcado:
  - `UserAdminService` passou a validar senha via politica configuravel
  - criacao/reset/troca de senha agora atualizam expiracao dinamica
  - views de usuario exibem regras de senha e metadados de expiracao
- Navegacao e seed atualizados:
  - menu lateral com item `Seguranca` condicionado a `security.view`
  - `db/seed.php` atualizado com `security.view`/`security.manage`
- Checklist da etapa adicionado em `tests/checklist-etapa-6.3.md`

## 2026-03-05 — Fase 6.2 concluida (LGPD avancado: trilha sensivel + retencao/anonimizacao)
- Criada migration `026_phase6_lgpd_advanced.sql` com:
  - tabela `sensitive_access_logs` para registro de visualizacao/acesso de dados sensiveis
  - tabela `lgpd_retention_policies` para politicas parametrizaveis de retencao/anonimizacao
  - tabela `lgpd_retention_runs` para historico de execucoes (preview/apply)
  - permissoes `lgpd.view` e `lgpd.manage` com vinculo aos papeis `sist_admin` e `admin`
  - politicas padrao: `sensitive_access_logs`, `audit_log`, `people_soft_deleted`, `users_soft_deleted`
- Implementado modulo LGPD:
  - `LgpdRepository` e `LgpdService` com painel filtravel, exportacao CSV e motor de retencao/anonimizacao
  - `LgpdController` com rotas:
    - `GET /lgpd`
    - `GET /lgpd/export/access-csv`
    - `POST /lgpd/policies/upsert`
    - `POST /lgpd/retention/run`
  - view `app/Views/lgpd/index.php` com KPIs, trilha, politicas e historico de execucoes
- Integracoes de trilha sensivel aplicadas em fluxos existentes:
  - visualizacao de CPF completo em listagem/perfil/timeline print de pessoas
  - downloads de dossie (`document_download`) e anexo de timeline (`timeline_attachment_download`)
  - downloads de comprovante e PDF de boleto (`payment_proof_download`, `invoice_pdf_download`)
  - download de anexo DOU (`process_meta_attachment_download`)
  - visualizacao/print/PDF de oficio gerado (`office_document_*`)
- Navegacao e seed atualizados:
  - menu lateral com item `LGPD` condicionado a `lgpd.view`
  - `db/seed.php` atualizado com permissoes LGPD e concessao para `sist_admin`/`admin`
- Checklist da etapa adicionado em `tests/checklist-etapa-6.2.md`

## 2026-03-04 — Fase 6.1 concluida (Admin de usuarios e acessos via UI)
- Criada migration `024_phase6_user_admin_access.sql` com:
  - permissoes `users.view` e `users.manage`
  - vinculo das permissoes aos papeis `sist_admin` e `admin`
- Implementado modulo administrativo de usuarios:
  - `UserAdminRepository`, `UserAdminService` e `UsersController`
  - rotas protegidas:
    - `GET /users`
    - `GET /users/create`
    - `POST /users/store`
    - `GET /users/show?id={id}`
    - `GET /users/edit?id={id}`
    - `POST /users/update`
    - `POST /users/delete`
    - `POST /users/toggle-active`
    - `GET /users/roles`
    - `POST /users/roles/update`
    - `POST /users/reset-password`
- Fluxo de senha entregue:
  - `GET /users/password`
  - `POST /users/password/update` (troca da propria senha com validacao da senha atual)
  - reset administrativo de senha na tela de detalhe do usuario
- Governanca de acesso entregue:
  - vinculo de papeis por usuario no formulario de create/edit
  - vinculo de permissoes por papel na tela `/users/roles`
- Navegacao atualizada:
  - item de menu `Usuarios` condicionado a `users.view`
  - acoes rapidas para `Novo usuario` e `Trocar senha`
- Seed atualizado com permissoes `users.view` e `users.manage` para papeis `sist_admin` e `admin`
- Checklist da etapa adicionado em `tests/checklist-etapa-6.1.md`

## 2026-03-04 — Fase 4.3 concluida (SLA por etapa + painel de pendencias)
- Consolidado modulo de SLA administrativo:
  - regras configuraveis por etapa em `sla_rules` (risco, vencido, ativo, notificacao e destinatarios)
  - painel de pendencias em `/sla-alerts` com KPIs e filtros por busca/status/severidade
  - cadastro de regras em `/sla-alerts/rules` e disparo opcional de email em `/sla-alerts/dispatch-email`
- Ajuste de regra de disparo:
  - severidade `all` passou a considerar apenas pendencias `em_risco` e `vencido` (exclui `no_prazo`)
- Trilha auditavel:
  - `audit_log`: `sla_rule:upsert` e `sla:dispatch_notifications`
  - `system_events`: `sla.rule_upserted` e `sla.notifications_dispatched`
  - log detalhado de envios em `sla_notification_logs`
- Checklist da etapa atualizado em `tests/checklist-etapa-4.3.md`

## 2026-03-04 — Fase 4.1 concluida (PDF nativo de oficio)
- Implementada geracao de PDF nativo para documentos de oficio:
  - novo `OfficeDocumentPdfBuilder` para transformar documento renderizado (HTML) em PDF textual
  - `OfficeTemplateService::documentPdf` com trilha de auditoria e evento de exportacao
  - nova rota `GET /office-documents/pdf?id={id}` em `OfficeTemplatesController`
- Fluxo de visualizacao atualizado:
  - botao de download PDF em `app/Views/office_templates/document_show.php`
  - acao `PDF` na tabela de oficios gerados em `app/Views/office_templates/show.php`
- Auditoria e eventos adicionados para exportacao:
  - `audit_log`: `office_document:export_pdf`
  - `system_events`: `office_document.pdf_exported`
- Checklist da etapa 4.1 atualizado para incluir validacao de PDF nativo em `tests/checklist-etapa-4.1.md`

## 2026-03-04 — Fase 4.2 concluida (metadados formais completos de processo)
- Criada migration `013_phase4_process_metadata.sql` com:
  - tabela `process_metadata` (oficio, DOU, entrada oficial MTE e metadados de anexo)
  - permissoes `process_meta.view` e `process_meta.manage` para perfis `sist_admin` e `admin`
- Implementado modulo de metadados formais:
  - `ProcessMetadataRepository` (CRUD, filtros/paginacao, busca e consultas de anexo)
  - `ProcessMetadataService` com validacoes de fase 4.2:
    - oficio: numero, data de envio, canal e protocolo
    - DOU: edicao, data, link e anexo
    - entrada oficial no MTE com regra de consistencia temporal
    - upload seguro de anexo DOU (`PDF/PNG/JPG`, limite 15MB, validacao de MIME)
  - `ProcessMetadataController` com rotas:
    - `GET /process-meta`
    - `GET /process-meta/create`
    - `POST /process-meta/store`
    - `GET /process-meta/show?id={id}`
    - `GET /process-meta/edit?id={id}`
    - `POST /process-meta/update`
    - `POST /process-meta/delete`
    - `GET /process-meta/dou-attachment?id={id}`
- Views do modulo adicionadas em `app/Views/process_metadata/*` (lista, create/edit e detalhe)
- Menu lateral atualizado com acesso por permissao ao modulo de metadados formais
- Checklist da etapa adicionado em `tests/checklist-etapa-4.2.md`

## 2026-03-04 — Fase 5.3 concluida (Relatorios premium com filtros + exportacao CSV/PDF/ZIP)
- Criada migration `023_phase5_premium_reports.sql` com:
  - permissao `report.view` para acesso ao modulo de relatorios
  - vinculo da permissao aos perfis `sist_admin` e `admin`
- Implementado modulo de relatorios premium:
  - `ReportRepository` com consolidacao operacional (SLA, gargalos, tempos) e financeira (previsto x efetivo, pago x a pagar)
  - `ReportService` com normalizacao de filtros por periodo/orgao/etapa/SLA e montagem de datasets de exportacao
  - `ReportPdfBuilder` para geracao de PDF nativo simples sem dependencia externa
  - `ReportsController` com rotas:
    - `GET /reports`
    - `GET /reports/export/csv`
    - `GET /reports/export/pdf`
    - `GET /reports/export/zip`
- Pacote ZIP de prestacao de contas implementado com:
  - inclusao de relatorio consolidado (`CSV` + `PDF`) no pacote
  - anexos de boletos (`invoices.pdf_storage_path`) e comprovantes (`payments.proof_storage_path`) conforme filtros
  - manifestacao de conteudo e contagem de anexos encontrados/ausentes (`manifesto.txt`)
  - auditoria `report:export_zip` e evento `report.export_zip`
- View `app/Views/reports/index.php` adicionada com:
  - filtros combinados (busca, orgao, etapa, SLA, ano e faixa mensal)
  - KPIs e tabelas operacionais (gargalos + detalhe paginado)
  - KPIs e tabela financeira mensal (previsto/efetivo/pago/a pagar)
  - botoes de exportacao CSV/PDF preservando os filtros aplicados
- Menu lateral atualizado com item `Relatorios` condicionado a `report.view`
- Seed atualizado para incluir a permissao `report.view`
- Checklist da etapa adicionado em `tests/checklist-etapa-5.3.md`

## 2026-03-04 — Fase 5.4 consolidada (parametrizacao por cargo/setor + alertas ativos)
- Criada migration `022_phase5_budget_param_scope_alerts.sql` com:
  - escopo `cargo` e `setor` em `org_cost_parameters`
  - escopo `cargo` e `setor` em `hiring_scenarios`
  - ajuste de chave unica de parametro para `(organ_id, cargo, setor)`
- `BudgetRepository` ampliado com:
  - fallback de parametro por escopo (`cargo+setor` -> `cargo` -> `setor` -> `geral`)
  - persistencia e consulta de escopo (`cargo`, `setor`) no historico de cenarios
- `BudgetService` ampliado com:
  - uso de parametro de custo medio por escopo no simulador
  - motor de alertas ativos para risco de saldo/deficit (5.4)
- view `app/Views/budget/index.php` atualizada com:
  - painel \"Alertas ativos (5.4)\"
  - formularios de parametro e simulador com campos `cargo` e `setor`
  - exibicao de escopo no resultado, ranking e historico de cenarios
- Checklist 5.4 atualizado em `tests/checklist-etapa-5.4-mvp.md`

## 2026-03-04 — Fase 5.2 iniciada (Gap orcamentario e suplementacao)
- Criada migration `021_phase5_budget_gap_supplementation.sql` com:
  - extensao de `hiring_scenarios` com `movement_type` (`entrada`/`saida`)
- `BudgetService` ampliado com:
  - matriz de risco mensal de insuficiencia (orcamento acumulado x projecao acumulada)
  - simulacao de impacto por `entrada` e `saida` no ciclo corrente e no proximo ano
  - consolidacao de ofensores por pior caso para apoio a decisao de suplementacao
- `BudgetRepository` ampliado com:
  - consulta `topDeviationOffenders` para ranking de maior deficit em pior caso
  - persistencia/leitura de `movement_type` no historico de cenarios
- view `app/Views/budget/index.php` atualizada com:
  - painel de risco de insuficiencia por mes
  - seletor de tipo de movimento no simulador
  - ranking de maiores ofensores por desvio (pior caso)
  - exibicao de tipo de movimento no resultado e no historico
- Checklist da etapa adicionado em `tests/checklist-etapa-5.2.md`

## 2026-03-04 — Fase 5.1 concluida (Projecoes e cenarios multiparametricos)
- Criada migration `019_phase5_budget_projections_scenarios.sql` com:
  - `budget_scenario_parameters` (variacoes por ciclo/orgao/modalidade para Base/Atualizado/Pior Caso)
  - extensao de `hiring_scenarios` com coluna `modality`
  - extensao de `hiring_scenario_items` com `scenario_code` e `variation_percent`
- `BudgetRepository` ampliado com:
  - leitura de modalidades ativas
  - persistencia e consulta de parametros de cenario por orgao/modalidade
  - serie de projecao mensal (`executado`, `comprometido`, `base projetada`)
  - persistencia de itens multiparametricos por simulacao
- `BudgetService` ampliado com:
  - projecao mensal/anual do ciclo corrente
  - envelopes do proximo ano para cenarios Base/Atualizado/Pior Caso
  - simulacao multiparametrica (Base/Atualizado/Pior Caso) por orgao/modalidade
  - fallback de variacoes por perfil padrao quando nao houver parametrizacao dedicada
  - auditoria `budget_scenario_parameter:create/update` e eventos `budget.scenario_parameter_upserted`
- `BudgetController` e rotas atualizados com:
  - `POST /budget/scenario-parameters/upsert`
- view `app/Views/budget/index.php` atualizada com:
  - painel de projecoes mensais e consolidado anual
  - formulario de variacao por orgao/modalidade
  - resultado de simulacao com matriz Base/Atualizado/Pior Caso
  - exibicao de modalidade no historico de cenarios
- Checklist da etapa adicionado em `tests/checklist-etapa-5.1.md`

## 2026-03-04 — Fase 5.4 (MVP) iniciada (Orcamento e capacidade de contratacao)
- Criada migration `018_phase5_budget_capacity_mvp.sql` com:
  - `budget_cycles` (orcamento anual, fator anual e status do ciclo)
  - `org_cost_parameters` (custo medio mensal parametrizado por orgao)
  - `hiring_scenarios` e `hiring_scenario_items` (historico de simulacoes)
  - permissoes `budget.view`, `budget.manage`, `budget.simulate` e `budget.approve`
- Implementado modulo orcamentario:
  - `BudgetRepository` (snapshot financeiro, parametros por orgao e persistencia de cenarios)
  - `BudgetService` (dashboard orcamentario, simulador de contratacao e calculo de capacidade)
  - `BudgetController` com rotas:
    - `GET /budget`
    - `POST /budget/simulate`
    - `POST /budget/parameters/upsert`
  - view `app/Views/budget/index.php` com:
    - KPIs de total/executado/comprometido/disponivel
    - projecao do ano seguinte e saldo projetado
    - formulario de parametrizacao de custo medio por orgao
    - simulador de contratacao e historico de cenarios
- Menu lateral atualizado com acesso a \"Orcamento\" por permissao `budget.view`
- Seed atualizado para incluir permissoes orcamentarias no papel `sist_admin` e `admin`
- Checklist da etapa adicionado em `tests/checklist-etapa-5.4-mvp.md`

## 2026-03-04 — Fase 3.5 concluida (Pagamentos completos por boleto)
- Criada migration `017_phase3_payments.sql` com:
  - `payments` (baixa financeira por boleto com data, valor e comprovante)
  - `payment_people` (alocacao automatica de baixa por pessoa vinculada ao boleto)
- `InvoiceRepository` ampliado com:
  - persistencia de pagamentos e alocacoes por pessoa
  - somatorio de pagamentos por boleto e atualizacao de `invoices.paid_amount`
  - consulta de comprovante para download protegido
- `InvoiceService` ampliado com:
  - `registerPayment` para baixa parcial/total com validacoes de saldo
  - upload seguro de comprovante (`PDF`, `PNG`, `JPG`, `JPEG`, limite 15MB)
  - integracao com status financeiro do boleto (`aberto`, `vencido`, `pago_parcial`, `pago`)
  - integracao por pessoa via atualizacao de `invoice_people.paid_amount`
  - bloqueio de remocao de vinculo quando ja houver valor pago para a pessoa
- `InvoicesController`, rotas e view de detalhe atualizados com:
  - `POST /invoices/payments/store`
  - `GET /invoices/payments/proof`
  - formulario de registro de pagamento e historico de baixas por boleto
- Checklist da etapa adicionado em `tests/checklist-etapa-3.5.md`

## 2026-03-04 — Fase 3.4 concluida (Conciliacao avancada de espelho)
- Criada migration `016_phase3_reconciliation_workflow.sql` com:
  - `cost_mirror_reconciliations` (cabecalho, status e bloqueio de edicao)
  - `cost_mirror_divergences` (divergencias item a item com severidade e justificativa)
- Implementados `CostMirrorReconciliationRepository`, `CostMirrorReconciliationService` e `CostMirrorReconciliationController` com:
  - conciliacao item a item previsto x espelho
  - workflow de justificativa obrigatoria acima de limiar e aprovacao
  - bloqueio de edicao no espelho apos aprovacao
  - auditoria e eventos de sistema para run/justificativa/aprovacao
- Rotas adicionadas em `/cost-mirrors/reconciliation/*`
- `CostMirrorService`, `CostMirrorsController` e view de detalhe ajustados para respeitar bloqueio por conciliacao aprovada
- Ajustado `CostMirrorsController` para consumir upload CSV no campo correto (`csv_file`)
- Checklist da etapa adicionado em `tests/checklist-etapa-3.4.md`

## 2026-03-04 — Fase 4.1 iniciada (Templates de oficio)
- Criada migration `012_phase4_office_templates.sql` com:
  - `office_templates` (catalogo por chave/tipo com status ativo)
  - `office_template_versions` (versionamento de assunto/corpo HTML e variaveis)
  - `office_documents` (documentos renderizados por template/versao/pessoa)
  - permissoes `office_template.view` e `office_template.manage` para `sist_admin` e `admin`
- Implementado modulo de templates:
  - `OfficeTemplateRepository`, `OfficeTemplateService` e `OfficeTemplatesController`
  - catalogo de templates por tipo (`orgao`, `mgi`, `cobranca`, `resposta`, `outro`)
  - historico de versoes e publicacao de nova versao ativa
  - merge de variaveis de pessoa/orgao/processo/custos/CDO para geracao de oficio
  - visualizacao de documento gerado e pagina print-friendly (`/office-documents/print`)
- Rotas adicionadas em `/office-templates/*` e `/office-documents/*`
- Menu lateral atualizado com acesso a "Oficios" por permissao
- Seed atualizado para incluir permissoes do modulo
- Checklist da etapa adicionado em `tests/checklist-etapa-4.1.md`

## 2026-03-04 — Fase 3.3 concluida (Espelho de custo detalhado)
- Criada migration `011_phase3_cost_mirrors.sql` com:
  - `cost_mirrors` (pessoa, orgao, competencia, vinculo opcional com boleto e total consolidado)
  - `cost_mirror_items` (itens detalhados com quantidade, valor unitario e valor total)
  - permissoes `cost_mirror.view` e `cost_mirror.manage` com concessao para perfis `sist_admin` e `admin`
- Implementado `CostMirrorRepository` com:
  - listagem paginada com filtros (orgao, pessoa, status, competencia e busca textual)
  - CRUD de espelho e itens com soft delete
  - consulta de pessoas/boletos ativos para vinculo
  - recalculo de total do espelho a cada inclusao/remocao de item
- Implementado `CostMirrorService` com:
  - validacao de competencia e bloqueio de duplicidade ativa por pessoa + competencia
  - validacao de compatibilidade espelho x boleto (orgao e competencia)
  - cadastro manual de item com calculo por valor total ou quantidade x unitario
  - importacao de CSV com validacao de cabecalho/linhas e persistencia transacional
  - trilha de auditoria e eventos para create/update/delete, item add/remove e import CSV
- Implementado modulo de espelhos:
  - `CostMirrorsController`
  - rotas protegidas (`/cost-mirrors`, CRUD, itens manuais, importacao CSV e remocao de item)
  - views de lista, criacao, edicao e detalhe
  - item de menu lateral visivel por permissao `cost_mirror.view`
- Seed atualizado com permissoes de espelho de custo
- Checklist da etapa adicionado em `tests/checklist-etapa-3.3.md`

## 2026-03-04 — Fase 3.2 concluida (Boletos estruturados)
- Criada migration `010_phase3_invoices.sql` com:
  - `invoices` (orgao, competencia, vencimento, valor, status e metadados de PDF)
  - `invoice_people` (vinculo boleto x pessoa com rateio opcional)
  - permissoes `invoice.view` e `invoice.manage` com concessao para perfis `sist_admin` e `admin`
- Implementado `InvoiceRepository` com:
  - listagem paginada por filtros (orgao, status, competencia e busca)
  - agregados de boleto (`total`, `rateado`, `saldo`, pessoas vinculadas)
  - vinculo/desvinculo de pessoas com escopo por orgao do boleto
  - consulta de PDF para download protegido
- Implementado `InvoiceService` com:
  - validacoes de cadastro/edicao (numero, orgao, competencia, vencimento, valor)
  - upload seguro de PDF (`application/pdf`, limite de 15MB)
  - status operacional (`aberto`, `vencido`, `pago_parcial`, `pago`, `cancelado`)
  - bloqueio de rateio acima do saldo e de vinculos em status final
  - auditoria e eventos para create/update/delete, vinculos e download de PDF
- Implementado modulo de boletos:
  - `InvoicesController`
  - rotas protegidas (`/invoices`, CRUD, vinculo de pessoas e download de PDF)
  - views de lista, criacao, edicao e detalhe com formulario de rateio
  - item de menu lateral visivel por permissao `invoice.view`
- Seed atualizado com permissoes de boletos estruturados
- Checklist da etapa adicionado em `tests/checklist-etapa-3.2.md`

## 2026-03-04 — Etapa 7.1 iniciada (Backup/restore operacional)
- Adicionados scripts:
  - `scripts/backup.sh` para backup de banco (`mysqldump`) e `storage/uploads`
  - `scripts/restore.sh` para restore controlado de banco e uploads
- Recursos implementados nos scripts:
  - suporte a `--dry-run`
  - retencao automatica de backups via `BACKUP_RETENTION_DAYS`
  - diretorio de backup configuravel via `BACKUP_ROOT`
  - snapshot opcional de `.env` (`--with-env` / `BACKUP_INCLUDE_ENV=1`)
  - manifesto por backup com hash SHA-256 e tamanho dos artefatos
- Documentacao operacional atualizada com runbooks de backup, restore e contingencia:
  - `docs/03-environment.md`
  - `docs/04-deploy.md`
  - `docs/05-operations.md`
  - `README.md`
- Checklist reproduzivel adicionado em `tests/checklist-etapa-7.1.md`

## 2026-03-04 — Fase 3.1 concluída (CDO completo)
- Criada migration `009_phase3_cdos.sql` com:
  - `cdos` (numero, UG, acao, periodo, valor total, status e soft delete)
  - `cdo_people` (vinculo CDO x pessoa com valor alocado)
  - permissões `cdo.view` e `cdo.manage` com concessao para perfis `sist_admin` e `admin`
- Implementado `CdoRepository` com:
  - listagem paginada de CDOs com totalizador (`total`, `alocado`, `saldo`)
  - consulta de detalhe com agregados
  - vinculo/desvinculo de pessoa ao CDO
  - consulta de pessoas disponiveis para vinculo
- Implementado `CdoService` com:
  - validacoes de cadastro/edicao (numero, periodo, valor, status)
  - bloqueio de vinculo quando valor excede saldo disponivel
  - status automatico por alocacao (`aberto`, `parcial`, `alocado`)
  - auditoria e eventos para create/update/delete, alteracao de valor/status e vinculos
- Implementado modulo CDO completo:
  - `CdosController`
  - rotas protegidas (`/cdos`, CRUD e vinculo de pessoas)
  - views de lista, criacao, edicao e detalhe com formulario de vinculo
- Dashboard atualizado com KPI de cobertura CDO:
  - total de CDOs
  - CDOs em aberto
  - valor total, valor alocado e saldo disponivel
- Menu lateral atualizado para exibir acesso a CDO conforme permissao `cdo.view`
- Checklist da etapa adicionado em `tests/checklist-etapa-3.1.md`

## 2026-03-04 — Fase 2.5 concluída (Conciliação previsto x real)
- Implementado `ReconciliationRepository` para consulta de:
  - versão ativa de custos por pessoa
  - itens previstos da versão ativa
  - lançamentos reais (`reimbursement_entries`) elegíveis para conciliação
- Implementado `ReconciliationService` com:
  - consolidação por competência (previsto, real lançado, real pago)
  - cálculo de desvio por competência e acumulado
  - resumo por pessoa para o mês atual e janela analisada
- `PeopleController` atualizado para carregar dados de conciliação no Perfil 360
- Perfil 360 atualizado com seção **Conciliação previsto x real**:
  - KPIs do mês atual (previsto, real lançado, real pago e desvio)
  - tabela por competência com desvios lançado/pago
- Dashboard atualizado com KPI de conciliação do mês:
  - previsto global vs real lançado/pago
  - desvio global exibido no painel principal
- `DashboardService` atualizado para:
  - normalizar métricas de conciliação
  - priorizar recomendação operacional quando há desvio financeiro no mês
- Checklist da etapa adicionado em `tests/checklist-etapa-2.5.md`

## 2026-03-04 — Fase 2.4 concluída (Financeiro real de reembolsos)
- Criada migration `008_phase2_reimbursement_entries.sql` com tabela `reimbursement_entries` para:
  - lançamentos financeiros reais por pessoa (`boleto`, `pagamento`, `ajuste`)
  - status de execução (`pendente`, `pago`, `cancelado`)
  - competência, vencimento e data de pagamento
- Implementado `ReimbursementRepository` com:
  - resumo financeiro por pessoa (pendente/pago/vencido)
  - listagem ordenada de lançamentos
  - criação de lançamento e baixa de pagamento
- Implementado `ReimbursementService` com:
  - validações de tipo/status/valor/datas
  - regras de baixa automática para status pago
  - auditoria (`entity=reimbursement_entry`) e eventos de sistema
- `PeopleController` e rotas atualizados com:
  - `POST /people/reimbursements/store`
  - `POST /people/reimbursements/mark-paid`
- Perfil 360 atualizado com seção **Reembolsos reais**:
  - KPIs de pendente/pago/vencido e total de lançamentos
  - formulário de registro de lançamento
  - tabela de execução financeira com ação “Marcar como pago”
- Escopo de auditoria por pessoa ampliado para incluir `reimbursement_entry`
- Checklist da etapa adicionado em `tests/checklist-etapa-2.4.md`

## 2026-03-04 — Fase 2.3 concluída (Dashboard operacional)
- Implementado `DashboardRepository` com consultas de agregação para:
  - totais de pessoas/órgãos ativos
  - cobertura documental por pessoa
  - cobertura de custos por pessoa (versão ativa)
  - volume de timeline e auditoria dos últimos 30 dias
  - distribuição do pipeline por etapa
  - últimas movimentações da timeline
- Implementado `DashboardService` para:
  - normalização de métricas
  - cálculo de percentuais de cobertura
  - geração de recomendação operacional baseada nos gaps da base
- `DashboardController` atualizado para consumir o serviço e expor dados reais para a view
- Dashboard atualizado com:
  - KPIs reais
  - distribuição visual do pipeline por etapa
  - bloco de recomendação de próxima ação
  - lista de movimentações recentes com atalho para Perfil 360
- CSS atualizado com estilos específicos do novo painel operacional
- Checklist da etapa adicionado em `tests/checklist-etapa-2.3.md`

## 2026-03-04 — Fase 2.2 concluída (Auditoria no Perfil 360 + exportação CSV)
- Implementado `PersonAuditRepository` com:
  - escopo de auditoria por pessoa considerando entidades relacionadas (`person`, `assignment`, `timeline_event`, `document`, `cost_plan`, `cost_plan_item`)
  - paginação com filtros básicos (`entidade`, `ação`, `busca`, período)
  - consulta para exportação de trilha filtrada
- Implementado `PersonAuditService` para:
  - normalização de filtros de auditoria
  - montagem de dados paginados para o Perfil 360
  - preparação de dados para exportação CSV
- `PeopleController` atualizado com:
  - carregamento da seção de auditoria no `show`
  - controle de visibilidade por permissão `audit.view`
  - endpoint de exportação `GET /people/audit/export`
- Perfil 360 atualizado na seção de auditoria com:
  - formulário de filtros
  - listagem paginada de registros
  - exibição de `before_data`, `after_data` e `metadata`
  - botão de exportação CSV respeitando filtros aplicados
- CSS atualizado para o novo bloco visual de auditoria
- Checklist da etapa adicionado em `tests/checklist-etapa-2.2.md`

## 2026-03-04 — Fase 2.1 concluída (Custos previstos + versionamento)
- Criada migration `007_phase2_cost_plans.sql` com:
  - `cost_plans` (versionamento por pessoa, indicador de versão ativa)
  - `cost_plan_items` (itens de custo por versão)
- Implementado `CostPlanRepository` para:
  - consulta da versão ativa e da última versão
  - histórico de versões com totais agregados (mensal e anualizado)
  - criação de versão, desativação da versão ativa e clonagem de itens
  - inclusão de itens por versão
- Implementado `CostPlanService` com:
  - `profileData` para alimentar o Perfil 360
  - criação de nova versão com clonagem opcional
  - inclusão de item com criação automática de versão inicial quando necessário
  - validações de tipo/valor/vigência
  - execução transacional para manter consistência entre plano, itens, auditoria e eventos
- `PeopleController` e rotas atualizados com:
  - `POST /people/costs/version/create`
  - `POST /people/costs/item/store`
- Perfil 360 atualizado na seção de custos com:
  - KPIs de total mensal equivalente e anualizado
  - comparação entre versão ativa e anterior
  - formulário para criar nova versão
  - formulário para adicionar item
  - tabela de itens da versão ativa
  - histórico de versões
- Checklist da etapa adicionado em `tests/checklist-etapa-2.1.md`

## 2026-03-04 — Fase 1.5 concluída (Dossiê documental)
- Criada migration `006_phase1_documents_dossier.sql` com tabela `documents`
- Implementado `DocumentRepository` e `DocumentService` para:
  - upload múltiplo com validação de extensão/MIME/tamanho
  - armazenamento seguro em `storage/uploads/{person_id}/documents/{Y}/{m}`
  - listagem paginada de documentos no Perfil 360
  - download protegido por pessoa/permissão
- `PeopleController` e rotas atualizados com:
  - `POST /people/documents/store`
  - `GET /people/documents/download`
- Perfil 360 atualizado na seção de documentos com:
  - formulário de upload múltiplo
  - drag-and-drop
  - metadados (tipo, SEI, data, tags, observações)
  - paginação e ação de download por item
- Auditoria implementada para:
  - upload (`entity=document`, `action=upload`)
  - download (`entity=document`, `action=download`)
- Checklist da etapa adicionado em `tests/checklist-etapa-1.5.md`

## 2026-03-04 — Reorganizacao DevOps/Docs
- Documentacao centralizada em `/docs` com estrutura numerada (`01` a `07`)
- `README.md` transformado em portal da documentacao
- `docs/03-environment.md` definido como fonte canonica de ambiente
- `docs/04-deploy.md` reescrito para deploy via bash no servidor
- `scripts/deploy.sh` reescrito para fluxo idempotente no servidor atual
- Adicionados `scripts/healthcheck.sh` e `scripts/rollback.sh`
- Adicionados `scripts/ftp-upload.sh` e `.vscode/tasks.json` para upload FTP via Visual Studio Code
- `serverconfig.md` da raiz convertido para arquivo deprecado com ponteiro
- Conteudos legados em `_ignore/docs` removidos para evitar duplicidade e riscos
- `.env.example` simplificado e padronizado com placeholders seguros
- `.gitignore` reforcado para segredos/chaves/backups/dumps

## 2026-03-04 — Fase 1.4 concluída (Timeline completa)
- Criada migration `005_phase1_timeline_attachments.sql` com tabela `timeline_event_attachments`
- `PipelineRepository` expandido com:
  - paginação de timeline com anexos
  - consulta completa para exportação/impressão
  - persistência e consulta de anexos por evento
- `PipelineService` expandido com:
  - criação de evento manual (`addManualEvent`)
  - retificação não destrutiva (`rectifyEvent`)
  - upload seguro de anexos (PDF/JPG/PNG até 10MB)
  - download protegido de anexo por pessoa
- `PeopleController` e rotas atualizados com:
  - `POST /people/timeline/store`
  - `POST /people/timeline/rectify`
  - `GET /people/timeline/attachment`
  - `GET /people/timeline/print`
- Perfil 360 atualizado com:
  - formulário de evento manual
  - anexos por evento com download
  - retificação por evento (sem apagar histórico)
  - paginação da timeline
  - botão de impressão/exportação HTML print-friendly
- Novos templates:
  - `app/Views/print_layout.php`
  - `app/Views/people/timeline_print.php`
- Checklist de testes adicionado em `tests/checklist-etapa-1.4.md`

## 2026-03-04 — Fase 1.3 concluída (Movimentação + Pipeline)
- Criada migration `004_phase1_pipeline_assignments.sql` com:
  - `assignment_statuses`
  - `assignments`
  - `timeline_events`
- Implementado `PipelineRepository` e `PipelineService`
- Pipeline de status configurável com sequência padrão:
  - Interessado → Triagem → Selecionado → Ofício órgão → Custos recebidos → CDO → MGI → DOU → Ativo
- Ao criar pessoa, assignment inicial é criado automaticamente
- Endpoint de avanço de pipeline implementado em `POST /people/pipeline/advance`
- Ao avançar status, sistema registra automaticamente:
  - `audit_log`
  - `system_events`
  - `timeline_events`
- Perfil 360 atualizado com:
  - trilha visual do pipeline
  - botão de próxima ação guiada
  - timeline cronológica
- Checklist de testes adicionado em `tests/checklist-etapa-1.3.md`

## 2026-03-04 — Fase 1.2 concluída (Pessoas)
- Criada migration `003_phase1_people.sql` com tabela `people` e vínculo obrigatório a `organs`
- Implementado `PeopleRepository` com filtros (status/modalidade/órgão/tags), busca, ordenação e paginação
- Implementado `PeopleService` com validações, regras de CPF e auditoria/eventos
- Implementado CRUD completo de Pessoas:
  - lista filtrável com painel lateral de resumo
  - criação
  - edição
  - exclusão lógica
  - Perfil 360 com abas base (Resumo, Timeline, Documentos, Custos, Auditoria)
- RBAC atualizado com permissões:
  - `people.manage`
  - `people.cpf.full`
- CPF mascarado em listagens para perfis sem permissão de visualização completa
- Checklist de testes adicionado em `tests/checklist-etapa-1.2.md`

## 2026-03-04 — Fase 1.1 concluída (Órgãos)
- Criada migration `002_phase1_organs.sql` com tabela `organs` (soft delete e índices)
- Implementado `OrganRepository` com busca, ordenação e paginação
- Implementado `OrganService` com validação, auditoria e eventos
- Implementado CRUD completo de Órgãos:
  - lista com filtros/paginação
  - criação
  - detalhe
  - edição
  - exclusão lógica
- RBAC atualizado com permissão `organs.manage`
- UI responsiva ampliada (tabela, formulários, paginação e ações)
- Ação rápida no detalhe: “Ver pessoas vinculadas”
- Checklist de testes adicionado em `tests/checklist-etapa-1.1.md`

## 2026-03-04 — Fase 0 concluída
- Estrutura base MVC criada (`app`, `public`, `storage`, `db`, `docs`, `tests`)
- Bootstrap, router, sessão segura, CSRF e logger implementados
- Autenticação (login/logout), rate limit e RBAC por permissão
- Auditoria (`audit_log`) e biblioteca de eventos (`record_event`) implementadas
- Health check (`/health`) com validação de banco e storage
- UI base responsiva com menu, dashboard e listas vazias de Pessoas/Órgãos
- Migrations idempotentes e seed inicial de roles/permissões/catálogos/admin
- Documentação inicial e checklist de testes da etapa
