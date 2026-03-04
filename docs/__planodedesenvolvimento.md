# REEMBOLSO - Especificacao Funcional e Plano de Desenvolvimento (v2.0)

**Data de atualizacao:** 2026-03-04  
**Aplicacao:** Reembolso (Web App em PHP)  
**Ambiente alvo:** HostGator Shared Hosting (cPanel, PHP 8.1+, MySQL/Percona 5.7+)  
**Orgao atendido:** MTE - Ministerio do Trabalho e Emprego

---

## 0) Objetivo desta revisao

Este documento foi revisado para:

- consolidar a visao funcional do sistema;
- **marcar o que ja esta concluido** no repositorio;
- identificar lacunas reais (parciais/pendentes);
- reorganizar as fases e tarefas com maior granularidade;
- enriquecer o roadmap com funcionalidades de alto valor operacional, financeiro e de governanca.

### 0.1 Criterio de status usado neste plano

- `[x]` Concluido (implementado no codigo atual)
- `[~]` Parcial (base implementada, mas ainda incompleto)
- `[ ]` Pendente (nao implementado)

### 0.2 Evidencias tecnicas consideradas

- Rotas e controle de acesso (`routes/web.php`)
- Migrations existentes (`db/migrations/001` a `008`)
- Servicos e repositorios de dominio (`app/Services`, `app/Repositories`)
- Changelog do projeto (`CHANGELOG.md`)
- Estado atual documentado (`README.md`, `docs/02-architecture.md`)

---

## 1) Escopo funcional consolidado

O sistema cobre o ciclo de movimentacao de pessoas para o MTE e o ciclo financeiro de reembolso, incluindo:

- cadastro e gestao de pessoas e orgaos de origem;
- pipeline operacional por etapas de movimentacao;
- timeline por pessoa com eventos e anexos;
- dossie documental por pessoa com upload/download seguro;
- custos previstos por pessoa com versionamento;
- lancamentos de reembolso real (boleto/pagamento/ajuste) por pessoa;
- conciliacao previsto x real por competencia;
- dashboard operacional e trilha de auditoria por pessoa.

---

## 2) Diagnostico de atendimento (status atual real)

## 2.1 Modulos ja concluidos

- `[x]` Fundacao tecnica (bootstrap, MVC leve, router, session, CSRF, logger, health check)
- `[x]` Autenticacao e RBAC base (login/logout, permissoes por rota, rate limit de login)
- `[x]` Auditoria e eventos de sistema
- `[x]` CRUD de Orgaos
- `[x]` CRUD de Pessoas com Perfil 360
- `[x]` Pipeline de movimentacao com avance de etapa
- `[x]` Timeline completa (evento automatico/manual, retificacao, anexos, impressao)
- `[x]` Dossie documental (upload multiplo, metadados, download protegido)
- `[x]` Custos previstos com versionamento e historico
- `[x]` Auditoria filtravel por pessoa com exportacao CSV
- `[x]` Dashboard operacional com metricas reais
- `[x]` Reembolsos reais por pessoa (registro e baixa)
- `[x]` Conciliacao previsto x real por pessoa e competencia

## 2.2 Itens parcialmente atendidos

- `[~]` RF-13 metadados completos de oficio/SEI: existe base de SEI em pessoa/documento, mas sem modulo dedicado de oficios e versionamento de template.
- `[~]` RF-14 publicacao DOU e entrada oficial: existe pipeline com status DOU/Ativo, mas sem tela especifica de metadados completos de DOU.
- `[~]` RF-45 painel financeiro completo (abertos/vencidos/pagos/conciliados): existe visao por pessoa e KPI no dashboard, sem modulo global financeiro dedicado.
- `[~]` RF-52 exportacoes CSV/PDF: existe export CSV de auditoria e impressao de timeline; faltam pacotes executivos/financeiros completos.
- `[~]` RNF-05 LGPD avancado: mascaramento e permissao de CPF implementados, mas faltam trilhas de visualizacao de dado sensivel e politicas de retencao/anonimizacao.
- `[~]` RNF-07 observabilidade: health check e logs existem, faltam paineis operacionais de erros/filas e monitoracao estruturada.

## 2.3 Lacunas criticas ainda pendentes

- `[ ]` Modulo de CDO real (`cdos`, `cdo_people`, CRUD, vinculos, totalizadores)
- `[ ]` Modulo de boletos estruturado por orgao e por lote (atualmente ha lancamento por pessoa)
- `[ ]` Modulo de espelho de custo item-a-item por competencia
- `[ ]` Conciliacao por item com justificativa formal e workflow de aprovacao
- `[ ]` Pagamentos com comprovante e conciliacao automatica por titulo
- `[ ]` Geracao de oficios (orgao/MGI) via templates versionados
- `[ ]` Importacao em massa (CSV) de pessoas e orgaos
- `[ ]` Gestao administrativa de usuarios/papeis/permissoes via UI
- `[ ]` Relatorios executivos e financeiros completos com filtros e exportacoes padronizadas
- `[ ]` Rotina de backup/restore documentada e automatizada

---

## 3) Matriz de requisitos atualizada

## 3.1 Requisitos funcionais (RF)

| ID | Requisito | Status | Observacao objetiva |
|---|---|---|---|
| RF-01 | CRUD de Orgaos | `[x]` | Implementado com listagem, detalhe, edicao e exclusao logica |
| RF-02 | CRUD de Pessoas | `[x]` | Implementado com filtros, busca, Perfil 360 e soft delete |
| RF-03 | Vinculo obrigatorio pessoa-orgao | `[x]` | `people.organ_id` obrigatorio e com FK |
| RF-04 | Importacao em massa de pessoas (CSV) | `[ ]` | Ainda nao existe rota/servico de importacao |
| RF-10 | Pipeline de status configuravel | `[x]` | Implementado com `assignment_statuses` e avance por acao |
| RF-11 | Timeline automatica/manual | `[x]` | Eventos automaticos e manuais com anexos e retificacao |
| RF-12 | Geracao de oficios por template | `[ ]` | Ainda nao implementado |
| RF-13 | Metadados de oficio/SEI/anexos | `[~]` | Base de SEI existe em pessoa/documento, sem modulo completo de oficio |
| RF-14 | Registro DOU + entrada oficial no MTE | `[~]` | Fluxo por status existe; faltam metadados formais e validacoes dedicadas |
| RF-20 | Dossie documental por pessoa/processo | `[x]` | Upload/download seguro e metadados por documento |
| RF-21 | Classificacao por tipo/tags + busca | `[~]` | Tipo/tags existem; busca dedicada no dossie ainda limitada |
| RF-22 | Controle de acesso a docs sensiveis | `[~]` | Controle por permissao geral existe; falta granularidade por sensibilidade |
| RF-30 | Cadastro de CDO | `[ ]` | Nao implementado |
| RF-31 | Vinculo CDO x pessoas | `[ ]` | Nao implementado |
| RF-32 | Custo previsto por pessoa | `[x]` | Implementado com versionamento e itens |
| RF-33 | Projecoes mensais/anuais/cenarios | `[~]` | Ha metricas de previsao no dashboard; sem modulo de cenarios completo |
| RF-40 | Cadastro de boleto (dominio proprio) | `[~]` | Existe `reimbursement_entries` por pessoa; falta dominio formal de boleto |
| RF-41 | Boleto agrupando multiplas pessoas | `[ ]` | Nao implementado |
| RF-42 | Espelho por competencia e pessoa | `[ ]` | Nao implementado com estrutura dedicada |
| RF-43 | Conciliacao automatica item e total | `[~]` | Conciliacao por total/competencia existe; item-a-item ainda nao |
| RF-44 | Registro de pagamento com comprovante/processo | `[~]` | Registro de baixa existe; comprovante e vinculo formal a titulos nao |
| RF-45 | Painel financeiro completo de status | `[~]` | Existe visao por pessoa e KPI global; falta modulo financeiro consolidado |
| RF-50 | Dashboard executivo (KPIs + visao operacional) | `[x]` | Implementado com KPIs e distribuicao de pipeline |
| RF-51 | Relatorios filtraveis por multiplos eixos | `[ ]` | Ainda nao implementado como modulo dedicado |
| RF-52 | Exportacao CSV e PDF | `[~]` | CSV de auditoria + timeline print; falta pacote completo |
| RF-60 | Auditoria de acoes criticas | `[x]` | Presente em modulos principais |
| RF-61 | Controle de acesso por perfil (RBAC) | `[x]` | Permissoes por rota/acao implementadas |
| RF-62 | Mascaramento de CPF em listagens | `[x]` | Implementado com permissao de visualizacao completa |

## 3.2 Requisitos nao funcionais (RNF)

| ID | Requisito | Status | Observacao objetiva |
|---|---|---|---|
| RNF-01 | Compatibilidade PHP 8.x + MySQL | `[x]` | Stack e estrutura compatveis com ambiente alvo |
| RNF-02 | Interface responsiva | `[x]` | Layout responsivo implementado nas views principais |
| RNF-03 | Performance com paginacao e indices | `[x]` | Paginacao presente e indices nas tabelas centrais |
| RNF-04 | Seguranca base (CSRF, upload, SQLi, hash de senha) | `[x]` | Implementado |
| RNF-05 | LGPD (controle e rastreabilidade avancada) | `[~]` | Base implementada; faltam trilhas de visualizacao e politicas de retencao |
| RNF-06 | Backups e restore operacional | `[ ]` | Nao existe rotina formal de backup/restore no codigo atual |
| RNF-07 | Observabilidade | `[~]` | Health check/logs existem; sem painel de monitoracao operacional |

---

## 4) Novo roadmap por fases (reorganizado e detalhado)

## 4.1 Fases concluidas

### Fase 0 - Fundacao, seguranca e padroes

- `[x]` Arquitetura MVC leve e bootstrap
- `[x]` Autenticacao, RBAC e rate limit de login
- `[x]` CSRF, sessoes seguras, auditoria e eventos
- `[x]` Health check e base de operacao/deploy

### Fase 1 - Pipeline operacional

- `[x]` 1.1 Orgaos (CRUD)
- `[x]` 1.2 Pessoas (CRUD + Perfil 360)
- `[x]` 1.3 Pipeline e movimentacao
- `[x]` 1.4 Timeline completa + anexos
- `[x]` 1.5 Dossie documental seguro

### Fase 2 - Financeiro base e governanca operacional

- `[x]` 2.1 Custos previstos com versionamento
- `[x]` 2.2 Auditoria filtravel por pessoa + exportacao CSV
- `[x]` 2.3 Dashboard operacional com recomendacao
- `[x]` 2.4 Reembolsos reais (lancamento e baixa)
- `[x]` 2.5 Conciliacao previsto x real por competencia

## 4.2 Fases de evolucao (prioridade alta)

### Fase 3 - Nucleo financeiro estruturado (CDO + titulos + espelhos)

**Objetivo:** sair do modelo financeiro simplificado e entregar controle formal de CDO, boleto, espelho e pagamento rastreavel.

#### Etapa 3.1 - CDO completo

- `[ ]` Criar tabelas `cdos` e `cdo_people`
- `[ ]` CRUD de CDO (numero, valor, periodo, status, UG/acao)
- `[ ]` Vinculo CDO x pessoas com totalizador e saldo
- `[ ]` Eventos e auditoria de alteracoes de valor/status
- `[ ]` Cards de CDO no dashboard

**Criterios de aceite:**
- CDO pode cobrir 1..N pessoas;
- bloqueio de vinculo quando exceder saldo;
- trilha completa de alteracoes no `audit_log`.

#### Etapa 3.2 - Boletos estruturados

- `[ ]` Criar tabelas `invoices` e `invoice_people`
- `[ ]` Cadastro de boleto por orgao/competencia/vencimento
- `[ ]` Upload de PDF do boleto e metadados (linha digitavel, referencia)
- `[ ]` Vincular pessoas ao boleto (rateio opcional)
- `[ ]` Status operacional de boleto (aberto, vencido, pago parcial, pago)

**Criterios de aceite:**
- um boleto pode conter multiplas pessoas;
- saldo do boleto e saldo por pessoa ficam consistentes apos baixa.

#### Etapa 3.3 - Espelho de custo detalhado

- `[ ]` Criar tabelas `cost_mirrors` e `cost_mirror_items`
- `[ ]` Cadastro manual por item e importacao CSV
- `[ ]` Validacao de competencia e duplicidade por pessoa
- `[ ]` Vinculo opcional espelho x boleto

**Criterios de aceite:**
- total do espelho bate com soma de itens;
- bloqueio para duplicidade por pessoa+competencia+origem.

#### Etapa 3.4 - Conciliacao avancada e workflow

- `[ ]` Conciliacao item-a-item (previsto x espelho)
- `[ ]` Tabela de divergencias com severidade
- `[ ]` Justificativa obrigatoria para divergencia acima de limiar
- `[ ]` Acao de aprovacao de conferencia com bloqueio de edicao

**Criterios de aceite:**
- relatorio de divergencias por competencia/orgao/pessoa;
- auditoria de aprovacao de conferencia.

#### Etapa 3.5 - Pagamentos completos

- `[ ]` Criar tabela `payments` e vinculo com boletos
- `[ ]` Baixa total/parcial por boleto
- `[ ]` Upload de comprovante de pagamento
- `[ ]` Integracao com status financeiro por pessoa

**Criterios de aceite:**
- ao registrar pagamento, status e saldo do boleto sao recalculados automaticamente.

### Fase 4 - Automacao de processo administrativo (SEI/oficios/SLA)

**Objetivo:** reduzir trabalho manual, padronizar comunicacao e acelerar andamento do pipeline.

#### Etapa 4.1 - Templates de oficio

- `[ ]` Catalogo de templates (orgao de origem, MGI, cobranca, resposta)
- `[ ]` Merge de variaveis (pessoa, orgao, processo, custo, CDO)
- `[ ]` Versionamento e historico de template
- `[ ]` Geracao de documento em HTML print e PDF

#### Etapa 4.2 - Metadados formais de processo

- `[ ]` Registro de numero de oficio, data envio, canal, protocolo
- `[ ]` Registro de publicacao DOU (edicao, data, link, anexo)
- `[ ]` Registro de data oficial de entrada no MTE

#### Etapa 4.3 - SLA e alertas de pendencia

- `[ ]` Regras de alerta configuraveis por etapa
- `[ ]` Painel de pendencias (vencido, em risco, no prazo)
- `[ ]` Notificacao por email opcional (SMTP cPanel)

### Fase 5 - Inteligencia orcamentaria e relatorios executivos

**Objetivo:** entregar camada decisoria para gestao financeira e governanca.

#### Etapa 5.1 - Projecoes e cenarios

- `[ ]` Projecao mensal/anual/proximo ano
- `[ ]` Cenarios Base, Atualizado e Pior Caso
- `[ ]` Parametros de variacao por orgao/modalidade

#### Etapa 5.2 - Gap orcamentario e suplementacao

- `[ ]` Tela de risco de insuficiencia por mes
- `[ ]` Simulacao de impacto por entrada/saida de pessoas
- `[ ]` Ranking de maiores ofensores de desvio

#### Etapa 5.3 - Relatorios premium

- `[ ]` Relatorios operacionais (SLA, gargalos, tempos medios)
- `[ ]` Relatorios financeiros (previsto x efetivo, pago x a pagar)
- `[ ]` Exportacao CSV/PDF por filtros
- `[ ]` Pacote ZIP de prestacao de contas

### Fase 6 - Administracao, compliance e seguranca avancada

**Objetivo:** elevar governanca de acesso e adequacao LGPD.

#### Etapa 6.1 - Admin de usuarios e acessos

- `[ ]` CRUD de usuarios
- `[ ]` Vinculo de papeis e permissoes por UI
- `[ ]` Ativacao/desativacao de conta
- `[ ]` Fluxo de troca e reset de senha

#### Etapa 6.2 - LGPD avancado

- `[ ]` Registro de visualizacao de dados sensiveis
- `[ ]` Relatorio de acesso a CPF/documentos sensiveis
- `[ ]` Politica de retencao e anonimização parametrizavel

#### Etapa 6.3 - Seguranca reforcada

- `[ ]` Politica de senha e expiracao configuravel
- `[ ]` Bloqueio por tentativas excessivas com janela configuravel
- `[ ]` Hardening de upload e validacoes adicionais de arquivo

### Fase 7 - Operacao, performance e qualidade

**Objetivo:** garantir estabilidade de producao e evolucao segura.

#### Etapa 7.1 - Backup e restore

- `[ ]` Scripts de backup DB e storage
- `[ ]` Script e checklist de restore
- `[ ]` Runbook de contingencia

#### Etapa 7.2 - Performance e processamento

- `[ ]` Indices adicionais para filtros de alto volume
- `[ ]` Snapshots de KPIs e projecoes via cron
- `[ ]` Otimizacao de consultas pesadas do dashboard

#### Etapa 7.3 - Qualidade e testes

- `[ ]` Suite de testes unitarios para regras financeiras
- `[ ]` Testes de integracao para fluxos criticos
- `[ ]` Checklists manuais versionados por etapa
- `[ ]` Regressao minima por dataset fixo de QA

#### Etapa 7.4 - Observabilidade operacional

- `[ ]` Painel de saude com indicadores tecnicos
- `[ ]` Estruturacao de logs por severidade
- `[ ]` Rotina de revisao de erros recorrentes

---

## 5) Dependencias entre fases (ordem recomendada)

1. Fase 3 (nucleo financeiro estruturado) deve anteceder Fase 5 para evitar relatorios sobre modelo incompleto.
2. Fase 4 (oficios/SLA) pode rodar em paralelo parcial com Fase 3.1/3.2.
3. Fase 6 (compliance avancado) deve iniciar apos base de admin de usuarios.
4. Fase 7 (operacao/qualidade) deve ocorrer em paralelo continuo, com fechamento forte no final.

---

## 6) Definition of Done (DoD) por etapa

Cada etapa so pode ser marcada como concluida quando cumprir todos os itens:

- migration idempotente + rollback seguro da mudanca;
- regras de negocio no Service + persistencia no Repository;
- rota protegida por permissao adequada;
- auditoria para create/update/delete/status;
- cobertura de teste (automatizado ou checklist manual reproduzivel);
- documentacao atualizada (`CHANGELOG.md`, docs tecnicos e checklist da etapa);
- validacao operacional local com dados de seed.

---

## 7) Backlog complementar de alto valor (funcionalidades extras)

- `[ ]` Importador CSV inteligente com preview, validacao e relatorio de erros por linha.
- `[ ]` Assistente de conciliacao com sugestao automatica de causa provavel do desvio.
- `[ ]` Classificacao automatica de documentos por tipo usando regras de nome/metadados.
- `[ ]` Painel "Proxima Acao" por pessoa com prioridade e SLA.
- `[ ]` API interna para integracao com BI externo e portal institucional.
- `[ ]` Modo "Prestacao de Contas" com pacote fechado por periodo (CSV + PDF + evidencias).

---

## 8) Proximo ciclo recomendado (execucao imediata)

1. Iniciar **Fase 3.1 (CDO completo)**.
2. Em seguida, entregar **Fase 3.2 (boletos estruturados)**.
3. Depois, fechar **Fase 3.3 + 3.4 (espelho e conciliacao item-a-item)**.
4. So entao abrir **Fase 5 (projecoes e relatorios premium)** com base financeira robusta.

---

**Fim do documento (v2.0).**
