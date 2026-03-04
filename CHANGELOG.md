# Changelog

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
