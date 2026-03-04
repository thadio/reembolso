# REEMBOLSO - Especificacao Funcional e Plano de Desenvolvimento (v2.1)

**Data de atualizacao:** 2026-03-04  
**Aplicacao:** Reembolso (Web App em PHP)  
**Ambiente alvo:** HostGator Shared Hosting (cPanel, PHP 8.1+, MySQL/Percona 5.7+)  
**Orgao atendido:** MTE - Ministerio do Trabalho e Emprego

---

## 0) Objetivo da v2.1

Esta versao reorganiza a v2.0 com foco em:

- consolidar status real do repositorio em formato executivo;
- remover blocos de rascunho e duplicacoes;
- integrar ao roadmap o modulo **Orcamento e Capacidade de Contratacao**;
- integrar backlog operacional de alto impacto para analistas, gestao e auditoria;
- manter o proximo ciclo pratico de execucao no app atual.

### 0.1 Convencao de status

- `[x]` Concluido (implementado no codigo atual)
- `[~]` Parcial (base implementada, mas incompleta)
- `[ ]` Pendente (nao implementado)

### 0.2 Evidencias tecnicas consideradas

- Rotas e controle de acesso: `routes/web.php`
- Migrations: `db/migrations/001` a `012`
- Servicos e repositorios: `app/Services`, `app/Repositories`
- Changelog tecnico: `CHANGELOG.md`
- Estado documentado: `README.md`, `docs/02-architecture.md`

---

## 1) Escopo funcional consolidado

O sistema cobre o ciclo de movimentacao de pessoas para o MTE e o ciclo financeiro de reembolso:

- cadastro e gestao de pessoas e orgaos de origem;
- pipeline operacional por etapas de movimentacao;
- timeline por pessoa com eventos e anexos;
- dossie documental com upload/download seguro;
- custos previstos por pessoa com versionamento;
- reembolsos reais por pessoa (lancamento e baixa);
- conciliacao previsto x real por competencia;
- CDO com vinculo 1..N de pessoas e controle de saldo;
- dashboard operacional e trilha de auditoria.

---

## 2) Snapshot de atendimento (estado real)

### 2.1 Macrofases

| Macrofase | Status | Observacao objetiva |
|---|---|---|
| Fase 0 - Fundacao e seguranca base | `[x]` | MVC leve, auth, RBAC, CSRF, auditoria, health |
| Fase 1 - Pipeline operacional | `[x]` | Orgaos, pessoas, pipeline, timeline, dossie |
| Fase 2 - Financeiro base | `[x]` | custos, auditoria por pessoa, dashboard, reembolso, conciliacao |
| Fase 3 - Nucleo financeiro estruturado | `[~]` | 3.1, 3.2 e 3.3 concluidas; 3.4 e 3.5 pendentes |
| Fase 4 - Automacao administrativa | `[~]` | 4.1 parcial concluida (catalogo/versionamento/merge/HTML); 4.2 e 4.3 pendentes |
| Fase 5 - Inteligencia orcamentaria e relatorios | `[ ]` | projecoes, cenarios, relatorios premium, modulo orcamentario |
| Fase 6 - Compliance e seguranca avancada | `[ ]` | admin de usuarios via UI, LGPD avancado, hardening |
| Fase 7 - Operacao, performance e qualidade | `[~]` | base parcial (health/log), sem fechamento operacional completo |

### 2.2 Modulos concluidos

- `[x]` Fundacao tecnica (bootstrap, router, session, CSRF, logger)
- `[x]` Autenticacao e RBAC base
- `[x]` Auditoria e eventos de sistema
- `[x]` CRUD de Orgaos
- `[x]` CRUD de Pessoas com Perfil 360
- `[x]` Pipeline de movimentacao
- `[x]` Timeline completa com anexos e retificacao
- `[x]` Dossie documental seguro
- `[x]` Custos previstos com versionamento
- `[x]` Auditoria filtravel por pessoa com CSV
- `[x]` Dashboard operacional com KPIs reais
- `[x]` Reembolsos reais por pessoa
- `[x]` Conciliacao previsto x real por competencia
- `[x]` CDO com vinculo de pessoas e controle de saldo
- `[x]` Boletos estruturados por orgao/competencia com PDF e rateio por pessoa
- `[x]` Espelho de custo detalhado por pessoa/competencia com cadastro manual e importacao CSV

### 2.3 Itens parciais relevantes

- `[~]` RF-12 templates de oficio (catalogo/versionamento/merge/HTML print)
- `[~]` RF-13 metadados completos de oficio/SEI
- `[~]` RF-14 metadados formais de DOU/entrada oficial
- `[~]` RF-45 painel financeiro global (abertos/vencidos/pagos/conciliados)
- `[~]` RF-52 exportacoes executivas completas (CSV/PDF)
- `[~]` RNF-05 LGPD avancado (trilhas de visualizacao e retencao)
- `[~]` RNF-07 observabilidade operacional estruturada

### 2.4 Lacunas criticas pendentes

- `[x]` Espelho de custo item a item por competencia
- `[ ]` Conciliacao item a item com justificativa e aprovacao
- `[ ]` Pagamentos completos com comprovante e conciliacao por titulo
- `[~]` Geracao de oficios por templates versionados
- `[ ]` Importacao CSV em massa (pessoas e orgaos)
- `[ ]` Gestao administrativa de usuarios/papeis via UI
- `[ ]` Relatorios executivos e financeiros completos

---

## 3) Matriz de requisitos (v2.1)

### 3.1 Requisitos funcionais (RF)

| ID | Requisito | Status | Observacao objetiva |
|---|---|---|---|
| RF-01 | CRUD de Orgaos | `[x]` | Implementado com listagem, detalhe, edicao e exclusao logica |
| RF-02 | CRUD de Pessoas | `[x]` | Implementado com filtros, busca, Perfil 360 e soft delete |
| RF-03 | Vinculo obrigatorio pessoa-orgao | `[x]` | `people.organ_id` obrigatorio com FK |
| RF-04 | Importacao em massa de pessoas (CSV) | `[ ]` | Ainda nao existe rota/servico de importacao |
| RF-10 | Pipeline de status configuravel | `[x]` | `assignment_statuses` + acao de avance |
| RF-11 | Timeline automatica/manual | `[x]` | Eventos automaticos/manuais com anexos e retificacao |
| RF-12 | Geracao de oficios por template | `[~]` | Catalogo/versionamento/merge/HTML print implementados; PDF nativo pendente |
| RF-13 | Metadados de oficio/SEI/anexos | `[~]` | Base existe; sem modulo formal de oficio |
| RF-14 | Registro DOU + entrada oficial no MTE | `[~]` | Fluxo por status existe; faltam metadados formais |
| RF-20 | Dossie documental por pessoa/processo | `[x]` | Upload/download seguro e metadados |
| RF-21 | Classificacao por tipo/tags + busca | `[~]` | Tipo/tags existem; busca dedicada limitada |
| RF-22 | Controle de acesso a docs sensiveis | `[~]` | Controle geral existe; falta granularidade por sensibilidade |
| RF-30 | Cadastro de CDO | `[x]` | Implementado com `cdos`, CRUD e trilha auditavel |
| RF-31 | Vinculo CDO x pessoas | `[x]` | Implementado com `cdo_people` e bloqueio por saldo |
| RF-32 | Custo previsto por pessoa | `[x]` | Implementado com versionamento e itens |
| RF-33 | Projecoes mensais/anuais/cenarios | `[~]` | Base parcial no dashboard, sem modulo dedicado |
| RF-40 | Cadastro de boleto (dominio proprio) | `[x]` | Implementado com `invoices` e metadados de boleto/PDF |
| RF-41 | Boleto agrupando multiplas pessoas | `[x]` | Implementado com `invoice_people` e vinculo 1..N |
| RF-42 | Espelho por competencia e pessoa | `[x]` | Implementado com `cost_mirrors` + `cost_mirror_items`, cadastro manual e importacao CSV |
| RF-43 | Conciliacao automatica item e total | `[~]` | Total por competencia existe; item a item nao |
| RF-44 | Registro de pagamento com comprovante/processo | `[~]` | Baixa existe; comprovante/vinculo formal nao |
| RF-45 | Painel financeiro completo de status | `[~]` | KPI parcial; falta modulo financeiro consolidado |
| RF-50 | Dashboard executivo (KPIs + operacional) | `[x]` | Implementado |
| RF-51 | Relatorios filtraveis por multiplos eixos | `[ ]` | Nao implementado como modulo dedicado |
| RF-52 | Exportacao CSV/PDF | `[~]` | CSV de auditoria + timeline print; pacote completo pendente |
| RF-60 | Auditoria de acoes criticas | `[x]` | Presente nos modulos principais |
| RF-61 | Controle de acesso por perfil (RBAC) | `[x]` | Permissoes por rota/acao implementadas |
| RF-62 | Mascaramento de CPF em listagens | `[x]` | Implementado com permissao de visualizacao completa |

### 3.2 Requisitos nao funcionais (RNF)

| ID | Requisito | Status | Observacao objetiva |
|---|---|---|---|
| RNF-01 | Compatibilidade PHP 8.x + MySQL | `[x]` | Stack compativel com ambiente alvo |
| RNF-02 | Interface responsiva | `[x]` | Presente nas views principais |
| RNF-03 | Performance com paginacao e indices | `[x]` | Paginacao e indices nas tabelas centrais |
| RNF-04 | Seguranca base (CSRF/upload/SQLi/hash) | `[x]` | Implementado |
| RNF-05 | LGPD avancado | `[~]` | Base implementada; faltam trilhas e retencao |
| RNF-06 | Backups e restore operacional | `[x]` | Scripts, checklist e runbook operacional implementados |
| RNF-07 | Observabilidade operacional | `[~]` | Health/logs existem; sem painel estruturado |

---

## 4) Roadmap reorganizado por fases

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

## 4.2 Fases em evolucao (prioridade alta)

### Fase 3 - Nucleo financeiro estruturado (CDO + titulos + espelhos)

**Objetivo:** sair do modelo simplificado e fechar rastreabilidade financeira ponta a ponta.

#### Etapa 3.1 - CDO completo

- `[x]` Tabelas `cdos` e `cdo_people`
- `[x]` CRUD de CDO
- `[x]` Vinculo CDO x pessoas com totalizador/saldo
- `[x]` Eventos e auditoria de alteracoes de valor/status
- `[x]` Cards de CDO no dashboard

#### Etapa 3.2 - Boletos estruturados

- `[x]` Criar tabelas `invoices` e `invoice_people`
- `[x]` Cadastro de boleto por orgao/competencia/vencimento
- `[x]` Upload de PDF e metadados (linha digitavel, referencia)
- `[x]` Vincular pessoas ao boleto (rateio opcional)
- `[x]` Status operacional (aberto, vencido, pago parcial, pago)

**Criterios de aceite 3.2:**
- um boleto pode conter multiplas pessoas;
- saldo do boleto e saldo por pessoa permanecem consistentes apos baixa.

#### Etapa 3.3 - Espelho de custo detalhado

- `[x]` Criar tabelas `cost_mirrors` e `cost_mirror_items`
- `[x]` Cadastro manual por item e importacao CSV
- `[x]` Validacao de competencia e duplicidade por pessoa
- `[x]` Vinculo opcional espelho x boleto

#### Etapa 3.4 - Conciliacao avancada e workflow

- `[ ]` Conciliacao item a item (previsto x espelho)
- `[ ]` Tabela de divergencias com severidade
- `[ ]` Justificativa obrigatoria para divergencia acima de limiar
- `[ ]` Aprovacao de conferencia com bloqueio de edicao

#### Etapa 3.5 - Pagamentos completos

- `[ ]` Criar tabela `payments` e vinculo com boletos
- `[ ]` Baixa total/parcial por boleto
- `[ ]` Upload de comprovante de pagamento
- `[ ]` Integracao com status financeiro por pessoa

### Fase 4 - Automacao de processo administrativo (SEI/oficios/SLA)

**Objetivo:** reduzir trabalho manual e acelerar andamento com rastreabilidade.

#### Etapa 4.1 - Templates de oficio

- `[x]` Catalogo de templates (orgao, MGI, cobranca, resposta)
- `[x]` Merge de variaveis (pessoa, orgao, processo, custo, CDO)
- `[x]` Versionamento e historico de template
- `[~]` Geracao em HTML print e PDF (HTML print implementado; PDF nativo pendente)

#### Etapa 4.2 - Metadados formais de processo

- `[ ]` Numero de oficio, data de envio, canal e protocolo
- `[ ]` Publicacao DOU (edicao, data, link, anexo)
- `[ ]` Data oficial de entrada no MTE

#### Etapa 4.3 - SLA e alertas de pendencia

- `[ ]` Regras de alerta configuraveis por etapa
- `[ ]` Painel de pendencias (vencido, em risco, no prazo)
- `[ ]` Notificacao opcional por email (SMTP cPanel)

### Fase 5 - Inteligencia orcamentaria e relatorios executivos

**Objetivo:** adicionar camada decisoria para planejamento e governanca.

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

#### Etapa 5.4 - Modulo Orcamento e Capacidade de Contratacao (novo na v2.1)

- `[ ]` Dashboard orcamentario (total, executado, comprometido, disponivel, projecao)
- `[ ]` Simulador de contratacao (ano corrente e ano seguinte)
- `[ ]` Motor de capacidade maxima por saldo e data de entrada
- `[ ]` Parametrizacao de custo medio por orgao/cargo/setor
- `[ ]` Planejamento por cenarios salvos (conservador/base/expansao)
- `[ ]` Alertas de risco orcamentario e deficit projetado

### Fase 6 - Administracao, compliance e seguranca avancada

**Objetivo:** elevar governanca de acesso e adequacao LGPD.

#### Etapa 6.1 - Admin de usuarios e acessos

- `[ ]` CRUD de usuarios
- `[ ]` Vinculo de papeis/permissoes via UI
- `[ ]` Ativacao/desativacao de conta
- `[ ]` Fluxo de troca e reset de senha

#### Etapa 6.2 - LGPD avancado

- `[ ]` Registro de visualizacao de dados sensiveis
- `[ ]` Relatorio de acesso a CPF/documentos sensiveis
- `[ ]` Politica de retencao e anonimizacao parametrizavel

#### Etapa 6.3 - Seguranca reforcada

- `[ ]` Politica de senha e expiracao configuravel
- `[ ]` Bloqueio por tentativas excessivas com janela configuravel
- `[ ]` Hardening de upload e validacoes adicionais

### Fase 7 - Operacao, performance e qualidade

**Objetivo:** garantir estabilidade de producao e evolucao segura.

#### Etapa 7.1 - Backup e restore

- `[x]` Scripts de backup DB e storage
- `[x]` Script e checklist de restore
- `[x]` Runbook de contingencia

#### Etapa 7.2 - Performance e processamento

- `[ ]` Indices adicionais para filtros de alto volume
- `[ ]` Snapshots de KPIs/projecoes via cron
- `[ ]` Otimizacao de consultas pesadas do dashboard

#### Etapa 7.3 - Qualidade e testes

- `[ ]` Suite de testes unitarios para regras financeiras
- `[ ]` Testes de integracao para fluxos criticos
- `[ ]` Checklists manuais versionados por etapa
- `[ ]` Regressao minima por dataset fixo de QA

#### Etapa 7.4 - Observabilidade operacional

- `[ ]` Painel de saude com indicadores tecnicos
- `[ ]` Logs estruturados por severidade
- `[ ]` Rotina de revisao de erros recorrentes

---

## 5) Especificacao integrada - Modulo Orcamento e Capacidade de Contratacao

### 5.1 Objetivo funcional

Permitir decisao rapida sobre:

- quanto ja foi gasto;
- quanto ainda pode ser gasto;
- se e possivel abrir novas contratacoes;
- quantas pessoas podem ser contratadas;
- impacto financeiro no ano corrente e no ano subsequente.

### 5.2 Modelo financeiro base

Parametro central (configuravel):

```
ANNUAL_FACTOR = 13.3
```

Composicao default:

- 12 meses de salario
- 1 mes de 13o
- 0.3 mes de adicional de ferias

### 5.3 Formulas de referencia

Custo no ano corrente (pro rata por data de entrada):

```
custo_ano_corrente = custo_mensal * meses_restantes_no_ano
```

Custo no ano seguinte:

```
custo_ano_seguinte = custo_mensal * ANNUAL_FACTOR
```

Capacidade maxima de contratacao (simplificada):

```
capacidade = saldo_disponivel / custo_ano_corrente_por_pessoa
```

### 5.4 Dashboard orcamentario (MVP)

Indicadores minimos:

- Orcamento total
- Executado
- Comprometido
- Disponivel
- Projecao ano seguinte
- Deficit/superavit projetado

### 5.5 Simulador de contratacao (MVP)

Entradas:

- orgao
- setor/departamento
- data de entrada
- quantidade de pessoas
- custo medio

Saidas:

- impacto no ano corrente
- impacto no ano seguinte
- capacidade maxima remanescente
- risco de estouro por centro de custo

### 5.6 Parametrizacao por orgao/setor

Tabela de referencia sugerida:

- `org_cost_parameters` (`org_id`, `cargo`, `avg_monthly_cost`, `updated_at`)

Alocacao organizacional:

- setor
- departamento
- coordenacao
- percentual de alocacao (quando houver multipla atuacao)

### 5.7 Pipeline de contratacao para reserva orcamentaria

Estados sugeridos:

- Planejada
- Em analise
- Aprovada
- Contratada
- Cancelada

Regra chave:

- ao passar para `Aprovada`, reservar orcamento automaticamente.

### 5.8 Alertas automaticos

- orcamento perto do limite;
- projecao negativa para o proximo ano;
- orgao/setor acima da media ou do teto;
- contratacao bloqueada por falta de saldo.

### 5.9 RBAC sugerido para o modulo

- `budget.view`
- `budget.manage`
- `budget.simulate`
- `budget.approve`

### 5.10 Entidades sugeridas para implementacao

- `budget_cycles`
- `budget_allocations`
- `budget_movements`
- `org_cost_parameters`
- `person_cost_allocations`
- `hiring_pipeline`
- `hiring_scenarios`
- `hiring_scenario_items`

Integracao com entidades existentes:

- `people`, `cost_plans`, `reimbursement_entries`, `cdos`
- e, apos fase 3.2/3.5, `invoices` e `payments`

---

## 6) Backlog operacional de alto impacto (integrado)

### 6.1 Produtividade do analista

- `[ ]` Painel "Minha fila" por responsavel e prioridade
- `[ ]` Checklist automatico por tipo de caso
- `[ ]` Calculadora automatica de reembolso com memoria de calculo
- `[ ]` Central de pendencias (documentos, divergencias, retornos)
- `[ ]` Comentarios internos por processo

### 6.2 Governanca e auditoria

- `[ ]` Timeline administrativa completa por processo
- `[ ]` Historico consolidado de pessoa e orgao
- `[ ]` Relatorios prontos para auditoria (CGU/TCU)
- `[ ]` Exportacao completa de dossie (ZIP/PDF + trilha)
- `[ ]` Controle de versao de documentos
- `[ ]` Controle de acesso por sensibilidade documental

### 6.3 Gestao executiva

- `[ ]` Painel executivo com gargalos e ranking de orgaos
- `[ ]` Controle de SLA e casos em atraso
- `[ ]` Gestao de lotes de pagamento
- `[ ]` Busca global por CPF/SEI/DOU/orgao/documento
- `[ ]` Simulacao previa antes da aprovacao final

### 6.4 Evolucao assistida por IA (futuro)

- `[ ]` Extracao de dados de documentos para conferencia automatica
- `[ ]` Deteccao de inconsistencias por regras e anomalias
- `[ ]` Sugestao de justificativas para divergencias recorrentes

---

## 7) Dependencias entre fases (ordem recomendada)

1. Fechar Fase 3 (3.4 a 3.5) antes de avancar pesado em relatorios premium da Fase 5.
2. Fase 4 (oficios/SLA) pode iniciar em paralelo parcial com 3.2.
3. Etapa 5.4 (orcamento/capacidade) pode iniciar por MVP apos 3.3, usando dados reais de titulos e espelhos.
4. Fase 6 deve iniciar apos base de admin de usuarios.
5. Fase 7 deve ocorrer em paralelo continuo, com fechamento formal no ciclo final.

---

## 8) Definition of Done (DoD) por etapa

Cada etapa so pode ser marcada como concluida quando cumprir todos os itens:

- migration idempotente + rollback seguro;
- regras no Service + persistencia no Repository;
- rota protegida por permissao adequada;
- auditoria para create/update/delete/status;
- checklist manual reproduzivel e/ou teste automatizado;
- documentacao atualizada (`CHANGELOG.md`, docs tecnicos e checklist da etapa);
- validacao operacional local com dados de seed;
- para regras financeiras: testes com dataset fixo e validacao de formulas em borda de competencia.

---

## 9) Proximo ciclo recomendado (execucao imediata)

1. Entregar **Fase 3.4 (conciliacao item a item + workflow)**.
2. Fechar **Fase 3.5 (pagamentos completos)**.
3. Abrir **Fase 5.4 MVP (orcamento/capacidade)** com dashboard + simulador.
4. Avancar **Fase 4.2 (metadados formais de processo)** em paralelo controlado.

---

**Fim do documento (v2.1).**
