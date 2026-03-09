# Estudo Funcional e Plano de Implantacao Integrada

**Tema:** Gestao unificada de pessoas com duplo controle de reembolso no MTE  
**Data:** 2026-03-06  
**Escopo deste documento:** detalhar o desenho funcional, tecnico e de implantacao para operar simultaneamente:

- fluxo A: **recebimento de pessoa no MTE** (MTE paga/reembolsa orgao de origem);
- fluxo B: **cessao de pessoa do MTE para orgao/empresa destino** (MTE recebe reembolso do destino).

---

## 1) Objetivo executivo

Implantar no sistema um modelo de operacao com **cadastro unico de pessoas**, sem duplicidade de CPF, permitindo abrir e acompanhar movimentos de entrada e saida com:

1. triagem ate desligamento/encerramento do caso;
2. controle financeiro completo para pagar e para receber;
3. projecoes e orcamentos segregados por natureza financeira;
4. dashboards dedicados para cada controle e visao executiva consolidada;
5. reaproveitamento maximo das estruturas ja existentes (`people`, `organs`, `mte_destinations`, `assignments`, `invoices`, `payments`, `budget_*`, `reports`).

---

## 2) Principios de arquitetura e negocio

1. **Pessoa unica:** `people` continua sendo o cadastro mestre, com unicidade por CPF.
2. **Movimento separado da pessoa:** cada caso operacional/financeiro e um movimento (entrada ou saida), sem criar nova pessoa.
3. **Fonte unica de orgaos/empresas:** reutilizar `organs` para origem e destino, incluindo empresas publicas e estatais via `organ_type`.
4. **Fonte unica de lotacoes MTE:** reutilizar `mte_destinations` tanto para lotacao de destino (entrada) quanto para lotacao de origem (saida).
5. **Segregacao contabil obrigatoria:** receita de reembolso nunca abate despesa de reembolso.
6. **Compatibilidade incremental:** evoluir sem quebra dos modulos atuais, com migracao progressiva e feature flags.
7. **Rastreabilidade total:** auditoria, timeline, documentos, checklist e trilha financeira para ambos os fluxos.

---

## 3) Diagnostico do estado atual (base reaproveitavel)

### 3.1 Capacidades ja existentes que serao reutilizadas

| Bloco | Situacao atual | Reuso no plano |
|---|---|---|
| Cadastro de pessoas (`people`) | CPF unico, orgao, modalidade, lotacao MTE textual, fluxo | Mantem cadastro unico; adiciona contexto de direcao por movimento |
| Cadastro de orgaos (`organs`) | CRUD completo, classificacao institucional, importacao CSV | Reuso integral para orgao de origem e destino |
| Cadastro de lotacoes (`mte_destinations`) | CRUD completo | Reuso integral para lotacao origem/destino dentro do MTE |
| Pipeline (`assignments`, `assignment_flows`) | Fluxo BPMN configuravel por status/transicao | Reuso integral com novos fluxos de saida MTE |
| Checklist por tipo de caso | Ja distingue `cessao`, `cft`, `requisicao` | Expandir templates para saida MTE com itens financeiros e de retorno |
| Timeline e dossie | Eventos, anexos, retificacao, versao documental | Reuso integral para ambos os fluxos |
| Financeiro (reembolso, boletos, pagamentos) | Controle de titulos, rateio, baixa, comprovantes, lotes | Evoluir para suportar naturezas `pagar` e `receber` |
| Orcamento/Projecoes | Ciclo anual, simulacoes, parametros por orgao | Segregar ciclos por natureza financeira |
| Relatorios e dashboard | Painel operacional + financeiro + exportacoes | Criar paineis dedicados para recebimentos e visao dual |

### 3.2 Lacunas para atender o novo escopo

1. Diferenciacao explicita de direcao do movimento no ato de abertura do caso.
2. Registro da lotacao de origem MTE para casos de saida do MTE.
3. Registro da lotacao de destino MTE para casos de entrada no MTE.
4. Operacao financeira de recebiveis do MTE (faturar/cobrar/receber/conciliar).
5. Orcamentos separados por natureza (despesa x receita), sem compensacao automatica.
6. Dashboards completos e segregados para os dois controles.

---

## 4) Modelo funcional alvo

## 4.1 Conceitos de dominio

1. **Pessoa:** entidade mestre unica (`people`).
2. **Movimento funcional:** caso operacional com inicio, meio e fim (eixo de pipeline e documentos).
3. **Direcao do movimento:**
- `entrada_mte`: pessoa vem de fora para o MTE.
- `saida_mte`: pessoa do MTE vai para orgao/empresa destino.
4. **Contraparte externa:** sempre cadastrada em `organs` (orgao ou empresa).
5. **Lotacao MTE de referencia:**
- entrada: lotacao destino no MTE;
- saida: lotacao origem no MTE.
6. **Natureza financeira:**
- `despesa_reembolso` (MTE paga);
- `receita_reembolso` (MTE recebe).

## 4.2 Regra de ouro do cadastro unico

No cadastro, o sistema deve:

1. validar CPF;
2. se CPF nao existir: criar pessoa e abrir primeiro movimento;
3. se CPF existir: nao duplicar pessoa, apenas abrir novo movimento;
4. manter historico de todos os movimentos da pessoa com trilha completa.

## 4.3 Matriz obrigatoria no ato de abertura do movimento

| Campo | Entrada no MTE | Saida do MTE |
|---|---|---|
| Pessoa (CPF) | Obrigatorio | Obrigatorio |
| Tipo de movimento | `entrada_mte` | `saida_mte` |
| Orgao contraparte | Orgao de origem | Orgao/empresa de destino |
| Lotacao MTE | Lotacao destino | Lotacao origem |
| Modalidade | Cessao/CFT/Requisicao | Cessao/CFT/Requisicao |
| Fluxo BPMN | Fluxo de entrada | Fluxo de saida |
| Natureza financeira | `despesa_reembolso` | `receita_reembolso` |
| Data prevista inicio | Obrigatorio | Obrigatorio |

**Atendimento explicito dos requisitos funcionais do negocio:**

1. quando a pessoa vem para o MTE: obrigatorios `orgao_origem` + `lotacao_destino_mte`;
2. quando a pessoa sai do MTE: obrigatorios `orgao_destino` + `lotacao_origem_mte`.

---

## 5) Evolucao de dados e modelagem tecnica

## 5.1 Estrategia de modelagem

Evoluir `assignments` para representar o movimento funcional (1 pessoa para N movimentos ao longo do tempo), preservando cadastro unico em `people`.

## 5.2 Mudancas propostas em `assignments`

1. remover a restricao `UNIQUE (person_id)` para permitir historico de movimentos.
2. adicionar colunas:
- `movement_direction` (`entrada_mte`, `saida_mte`);
- `financial_nature` (`despesa_reembolso`, `receita_reembolso`);
- `counterparty_organ_id` (FK `organs.id`);
- `origin_mte_destination_id` (FK `mte_destinations.id`);
- `destination_mte_destination_id` (FK `mte_destinations.id`);
- `requested_end_date`;
- `effective_end_date`;
- `termination_reason`;
- `movement_code` (identificador funcional unico para auditoria).
3. adicionar indices:
- `(person_id, movement_direction, deleted_at, created_at)`;
- `(counterparty_organ_id, deleted_at, created_at)`;
- `(financial_nature, current_status_id, deleted_at)`;
- `(origin_mte_destination_id, destination_mte_destination_id, deleted_at)`.

## 5.3 Regras de consistencia por direcao

1. `entrada_mte`:
- `counterparty_organ_id` obrigatorio;
- `destination_mte_destination_id` obrigatorio;
- `origin_mte_destination_id` nulo.
2. `saida_mte`:
- `counterparty_organ_id` obrigatorio;
- `origin_mte_destination_id` obrigatorio;
- `destination_mte_destination_id` nulo.

## 5.4 Evolucao financeira (sem quebrar estrutura existente)

Adicionar coluna de natureza financeira nas tabelas atuais:

1. `reimbursement_entries.financial_nature` (`despesa_reembolso`, `receita_reembolso`);
2. `invoices.financial_nature` (`despesa_reembolso`, `receita_reembolso`);
3. `payments.financial_nature` (`despesa_reembolso`, `receita_reembolso`);
4. `payment_batches.financial_nature` (`despesa_reembolso`, `receita_reembolso`).

**Defaults legados:** registros atuais recebem `despesa_reembolso`.

## 5.5 Evolucao orcamentaria segregada

Adicionar `financial_nature` nos blocos de orcamento:

1. `budget_cycles` com unicidade `(cycle_year, financial_nature)`;
2. `budget_scenario_parameters` por ciclo segregado;
3. `hiring_scenarios` e itens segregados por natureza.

Com isso, o sistema passa a ter dois ciclos paralelos por ano:

1. ciclo de despesas de reembolso;
2. ciclo de receitas de reembolso.

Nao ha compensacao automatica entre os dois.

---

## 6) Desenho de fluxo operacional ponta a ponta

## 6.1 Fluxo A - Recebimento de pessoa no MTE (MTE paga)

### 6.1.1 Macroetapas

1. triagem e elegibilidade;
2. formalizacao com orgao de origem;
3. validacao de custos e instrumentos;
4. tramitacao institucional;
5. ativacao no MTE;
6. execucao financeira de pagamento;
7. acompanhamento recorrente;
8. desligamento e encerramento.

### 6.1.2 Proposta de status BPMN

| Ordem | Codigo | Objetivo |
|---|---|---|
| 1 | `entrada_triagem` | Conferir base documental e enquadramento |
| 2 | `entrada_selecionado` | Aprovar internamente abertura do processo |
| 3 | `entrada_oficio_origem` | Emitir oficio para orgao de origem |
| 4 | `entrada_resposta_origem` | Receber resposta e custos |
| 5 | `entrada_cdo` | Garantir cobertura orcamentaria de despesa |
| 6 | `entrada_mgi` | Tramitacao ministerial |
| 7 | `entrada_dou` | Publicacao formal |
| 8 | `entrada_ativo_mte` | Pessoa em exercicio no MTE |
| 9 | `entrada_financeiro_execucao` | Titulos e pagamentos recorrentes |
| 10 | `entrada_desligamento` | Encerrar cessao/requisicao |
| 11 | `entrada_encerrado` | Caso encerrado com auditoria final |

### 6.1.3 Artefatos obrigatorios

1. oficio ao orgao de origem;
2. resposta formal com custos;
3. CDO/lastro orcamentario;
4. publicacao e metadados oficiais;
5. boletos/titulos recebidos do orgao de origem;
6. comprovantes de pagamento;
7. termo de encerramento/desligamento.

## 6.2 Fluxo B - Cessao do MTE para orgao/empresa (MTE recebe)

### 6.2.1 Macroetapas

1. triagem de demanda de saida;
2. validacao de elegibilidade interna (lotacao de origem MTE);
3. formalizacao com orgao/empresa destino;
4. definicao de parametros de ressarcimento ao MTE;
5. ativacao da cessao no destino;
6. emissao de cobrancas e controle de recebimentos;
7. inadimplencia, cobranca e regularizacao;
8. retorno/desligamento e encerramento.

### 6.2.2 Proposta de status BPMN

| Ordem | Codigo | Objetivo |
|---|---|---|
| 1 | `saida_triagem` | Abrir caso e validar premissas da cessao |
| 2 | `saida_validacao_lotacao_mte` | Confirmar lotacao origem MTE e autorizacoes |
| 3 | `saida_oficio_destino` | Enviar oficio para orgao/empresa destino |
| 4 | `saida_anuencia_destino` | Receber aceite do destino com condicoes |
| 5 | `saida_instrumento_ressarcimento` | Assinar instrumento com regras de cobranca |
| 6 | `saida_publicacao_ativacao` | Registrar ato/publicacao e inicio no destino |
| 7 | `saida_ativo_destino` | Pessoa cedida e em acompanhamento |
| 8 | `saida_financeiro_faturamento` | Gerar titulos a receber e cobrar |
| 9 | `saida_financeiro_recebimento` | Registrar recebimentos e conciliacao |
| 10 | `saida_inadimplencia` | Tratar atrasos e cobranca administrativa |
| 11 | `saida_retorno_desligamento` | Encerrar cessao e retorno ao MTE |
| 12 | `saida_encerrado` | Caso encerrado com fechamento financeiro |

### 6.2.3 Artefatos obrigatorios

1. oficio de cessao para destino;
2. aceite formal do destino;
3. instrumento juridico de ressarcimento;
4. publicacao/ato de cessao;
5. memoria de calculo de valores a receber;
6. faturas/titulos emitidos pelo MTE;
7. comprovantes de recebimento (extrato/ordem bancaria);
8. notificacoes de cobranca e termo de quitacao.

## 6.3 SLA e pendencias para os dois fluxos

1. SLA por etapa configurado em `sla_rules`.
2. Painel unico de pendencias com filtro por direcao e natureza financeira.
3. Escalonamento automatico para casos:
- sem documento obrigatorio;
- com pendencia de aprovacao;
- com vencimento financeiro em risco;
- com atraso de recebimento acima de X dias (fluxo saida).

---

## 7) Plano funcional detalhado da segunda situacao (saida do MTE)

## 7.1 Epic S1 - Abertura e qualificacao do caso de saida

1. wizard de abertura com:
- CPF;
- direcao `saida_mte`;
- lotacao origem MTE obrigatoria;
- orgao/empresa destino obrigatorio;
- modalidade;
- fluxo inicial.
2. validacoes:
- sem lotacao origem MTE nao avanca;
- destino deve existir em `organs` (ou ser criado no ato);
- bloquear duplicidade de caso ativo para mesma pessoa e mesma direcao.
3. resultado:
- movimento criado;
- checklist inicial gerado;
- timeline de abertura registrada;
- atribuicao de analista e prioridade.

## 7.2 Epic S2 - Gestao documental e formalizacao com destino

1. templates especificos para saida:
- oficio de cessao;
- minuta de instrumento de ressarcimento;
- notificacao de cobranca;
- termo de quitacao/encerramento.
2. controle de versoes e assinatura:
- versao por documento;
- trilha de quem gerou, aprovou e enviou.
3. metadados formais:
- numero do processo;
- protocolo no destino;
- datas de envio/retorno.

## 7.3 Epic S3 - Motor de faturamento e recebimento (MTE recebe)

1. configuracao de periodicidade de cobranca por caso:
- mensal;
- trimestral;
- por evento.
2. geracao automatica de titulo a receber:
- valor base;
- encargos/atualizacoes;
- vencimento;
- contraparte de destino.
3. controle de recebimento:
- parcial e total;
- comprovante;
- saldo aberto por titulo;
- conciliacao por pessoa e por movimento.
4. inadimplencia:
- dias em atraso;
- nivel de cobranca;
- carta de cobranca automatizada;
- trilha de tentativas de contato.

## 7.4 Epic S4 - Encerramento, retorno e auditoria

1. registrar data efetiva de retorno/desligamento.
2. bloquear novas cobrancas apos encerramento.
3. gerar termo de encerramento financeiro:
- total faturado;
- total recebido;
- saldo final;
- justificativa de diferencas.
4. exportacao de dossie completo para CGU/TCU.

---

## 8) Financeiro completo com segregacao de natureza

## 8.1 Modelo operacional de titulos

1. **Despesa de reembolso (`despesa_reembolso`):**
- origem: orgao de origem cobrando MTE;
- acao do MTE: pagar;
- modulo principal: fluxo entrada.
2. **Receita de reembolso (`receita_reembolso`):**
- origem: cobranca do MTE para orgao/empresa destino;
- acao do MTE: receber;
- modulo principal: fluxo saida.

## 8.2 Regras contabil-financeiras obrigatorias

1. nao existe compensacao automatica entre natureza de receita e despesa;
2. cobertura de pagamento usa apenas `despesa_reembolso`;
3. adimplencia de recebimento usa apenas `receita_reembolso`;
4. relatorios mostram totais separados e, quando consolidado, sempre com duas colunas distintas.

## 8.3 Ajustes em funcionalidades existentes

1. `InvoiceService`:
- incluir filtro/operacao por natureza financeira;
- permitir emissao de titulo a receber pelo MTE (nao apenas boleto recebido).
2. `payments`:
- registrar tanto pagamento efetuado quanto recebimento identificado;
- manter semantica por `financial_nature`.
3. lotes:
- lote de pagamento (despesa);
- lote de baixa de recebimento (receita).
4. conciliacao:
- reconciliar previsto x real para os dois eixos sem cruzamento.

## 8.4 Projecoes financeiras

1. previsao de despesa mensal/anual por casos de entrada.
2. previsao de receita mensal/anual por casos de saida.
3. previsao de caixa segregada:
- curva de desembolso;
- curva de recebimento;
- aging de recebiveis.
4. indicadores:
- prazo medio de pagamento;
- prazo medio de recebimento;
- taxa de inadimplencia;
- cobertura de lastro por natureza.

---

## 9) Orcamento e planejamento segregados

## 9.1 Estrutura alvo

Para cada ano:

1. `budget_cycle` de despesa (`despesa_reembolso`);
2. `budget_cycle` de receita (`receita_reembolso`).

## 9.2 Regras do modulo de orcamento

1. simulacoes de contratacao e impacto de despesa permanecem no ciclo de despesa.
2. simulacoes de cessao para fora e potencial de receita ficam no ciclo de receita.
3. alertas de risco por ciclo:
- insuficiencia de dotacao (despesa);
- frustacao de arrecadacao/inadimplencia (receita).
4. nenhum calculo reduz automaticamente um ciclo pelo saldo do outro.

## 9.3 Parametrizacao por orgao e modalidade

Reutilizar tabela de parametros com chave composta por:

1. orgao;
2. modalidade;
3. natureza financeira;
4. cargo/setor (quando aplicavel).

---

## 10) Dashboards completos

## 10.1 Dashboard Operacional - Entrada no MTE

KPIs:

1. casos abertos por etapa;
2. tempo medio por etapa;
3. pendencias documentais;
4. pessoas ativas no MTE por lotacao destino;
5. despesas previstas x pagas no mes;
6. casos proximos de desligamento.

Visualizacoes:

1. funil do pipeline de entrada;
2. mapa de orgaos de origem;
3. ranking de gargalos por etapa;
4. painel de SLA vencido/em risco.

## 10.2 Dashboard Operacional - Saida do MTE

KPIs:

1. casos de saida por etapa;
2. pessoas cedidas ativas por lotacao origem MTE;
3. distribuicao por orgao/empresa destino;
4. titulos a receber emitidos no periodo;
5. valor vencido e nao recebido;
6. taxa de adimplencia por destino.

Visualizacoes:

1. funil do pipeline de saida;
2. carteira de recebiveis por faixa de atraso (aging);
3. ranking de devedores por valor e atraso;
4. alertas de inadimplencia critica.

## 10.3 Dashboard Financeiro Segregado

Bloco despesa (`despesa_reembolso`):

1. aberto;
2. vencido;
3. pago parcial;
4. pago total;
5. cobertura de pagamento.

Bloco receita (`receita_reembolso`):

1. faturado;
2. a receber no prazo;
3. vencido;
4. recebido parcial;
5. recebido total;
6. inadimplencia.

## 10.4 Dashboard Executivo Integrado (sem compensacao)

1. quadro de duas colunas: despesa x receita;
2. comparativo mensal de tendencia;
3. risco operacional por fluxo;
4. risco financeiro por natureza;
5. capacidade orcamentaria por ciclo;
6. recomendacoes automaticas (acoes prioritarias).

---

## 11) Integracao com modulos existentes (sem duplicacao)

## 11.1 Cadastro e referencias

1. `people`: cadastro unico.
2. `organs`: origem e destino externos (orgaos e empresas).
3. `mte_destinations`: origem/destino interno MTE.

## 11.2 Pipeline e produtividade

1. `assignment_flows`: criar fluxo de saida e manter fluxo de entrada.
2. `assignment_checklist_templates`: ampliar templates para saida.
3. `analyst_pending_items`: reutilizar pendencias para cobranca e inadimplencia.

## 11.3 Documentos e metadados

1. `documents` e `document_versions`: reutilizar para instrumentos de cessao.
2. `office_templates`: incluir novos tipos para cobranca e encerramento de saida.
3. `process_metadata`: reaproveitar para atos/publicacoes de saida.

## 11.4 Financeiro

1. `invoices`, `payments`, `payment_batches`: reutilizar com `financial_nature`.
2. `reimbursement_entries`: manter e ampliar para eventos de receita.
3. `reports`: incluir cortes por direcao e natureza.

## 11.5 Orcamento

1. `budget_cycles` e cenarios: duplicidade logica por natureza, nao por tabela separada.
2. `budget parameters`: mesmo cadastro base, com eixo adicional de natureza.

---

## 12) Permissoes e governanca de acesso

## 12.1 Novas permissoes propostas

1. `receivable.view` - visualizar carteira de recebimentos;
2. `receivable.manage` - gerar titulos/cobrancas e registrar recebimentos;
3. `receivable.batch.manage` - gerir lotes de recebimento;
4. `budget.revenue.view` - visualizar ciclo de receita;
5. `budget.revenue.manage` - gerir parametros e cenarios de receita.

## 12.2 Segregacao funcional recomendada

1. equipe operacional entrada;
2. equipe operacional saida;
3. equipe financeira despesa;
4. equipe financeira receita;
5. perfil executivo (visualiza ambos, sem permissao de alteracao transacional).

---

## 13) Rotas e interfaces propostas

## 13.1 Novas rotas (resumo)

1. `GET /movements` (lista unificada com filtros por direcao/natureza/status);
2. `GET /movements/create` e `POST /movements/store` (wizard unico);
3. `GET /movements/show?id=...` (perfil 360 do movimento);
4. `POST /movements/pipeline/advance`;
5. `POST /movements/checklist/update`;
6. `GET /receivables` (carteira de recebiveis);
7. `POST /receivables/invoices/store`;
8. `POST /receivables/receipts/store`;
9. `GET /dashboards/entrada`;
10. `GET /dashboards/saida`;
11. `GET /dashboards/financeiro-segregado`;
12. `GET /budget?year=...&nature=despesa_reembolso|receita_reembolso`.

## 13.2 Ajustes de UX

1. troca de paradigma de "cadastro de pessoa" para "pessoa + abertura de movimento";
2. badge visual de direcao em toda tela (`Entrada MTE` ou `Saida MTE`);
3. cards financeiros em dois blocos fixos (pagar x receber);
4. filtros persistentes por direcao e natureza em dashboard, relatorios e busca global.

---

## 14) Plano de implantacao integrada (faseado)

## 14.1 Estrategia geral

Implantar em ondas curtas, com convivencia do modelo atual e do novo por feature flag ate estabilizacao.

## 14.2 Fase 0 - Preparacao (1 a 2 semanas)

Entregas:

1. desenho final de modelo de dados;
2. especificacao funcional validada com negocio;
3. feature flags:
- `dual_flow_enabled`;
- `receivable_finance_enabled`;
- `segregated_budget_enabled`.
4. plano de migracao de dados e rollback.

Criterios de aceite:

1. dicionario de dados aprovado;
2. roteiros de teste homologados;
3. plano de cutover assinado.

## 14.3 Fase 1 - Cadastro unico + movimento (2 a 3 semanas)

Entregas:

1. remocao de `UNIQUE(person_id)` em `assignments`;
2. novas colunas de direcao/origem/destino;
3. wizard de abertura de movimento;
4. regras de validacao da matriz obrigatoria;
5. migracao legada de registros atuais para `entrada_mte`.

Criterios de aceite:

1. nao existe duplicacao de pessoa para novos casos;
2. novos casos de entrada e saida podem ser abertos corretamente;
3. consultas antigas continuam funcionais para casos legados.

## 14.4 Fase 2 - Pipeline completo de saida MTE (2 a 3 semanas)

Entregas:

1. novo `assignment_flow` para `saida_mte`;
2. status, transicoes, SLA e checklist de saida;
3. templates documentais de saida;
4. timeline administrativa adaptada ao novo fluxo.

Criterios de aceite:

1. movimento de saida percorre triagem ate encerramento;
2. checklist e pendencias funcionam por etapa;
3. auditoria e eventos registram direcao e lote de origem/destino.

## 14.5 Fase 3 - Financeiro de recebimentos (3 a 4 semanas)

Entregas:

1. `financial_nature` em titulos/pagamentos/lotes;
2. emissao de titulos a receber pelo MTE;
3. baixa de recebimentos parciais e totais;
4. carteira de inadimplencia e cobranca;
5. conciliacao de receita por caso e por competencia.

Criterios de aceite:

1. registrar recebimentos sem impactar saldo de despesas;
2. relatorios financeiros separados por natureza;
3. aging de recebiveis com filtros por orgao destino.

## 14.6 Fase 4 - Orcamento segregado + dashboards (2 a 3 semanas)

Entregas:

1. `budget_cycles` com `financial_nature`;
2. projecoes separadas de despesa e receita;
3. dashboards operacionais entrada/saida;
4. dashboard financeiro segregado;
5. dashboard executivo dual.

Criterios de aceite:

1. receita nao abate despesa em nenhum indicador;
2. paineis exibem comparativo sem compensacao;
3. exportacoes CSV/PDF/ZIP respeitam filtros de natureza.

## 14.7 Fase 5 - Go-live controlado e hipercare (2 semanas + 30 dias)

Entregas:

1. treinamento por perfil (operacional, financeiro, executivo);
2. migracao final de dados e ligacao de feature flags;
3. monitoramento intensivo de erros, SLA e inconsistencias;
4. checklist de estabilizacao diaria na primeira semana.

Criterios de aceite:

1. operacao real de pelo menos 1 ciclo de faturamento e 1 ciclo de recebimento;
2. ausencia de divergencia contabil critica;
3. aprovacao formal da area usuaria e financeira.

---

## 15) Plano de migracao de dados

## 15.1 Mapeamento inicial de legado

1. movimentos atuais mapeados para `entrada_mte`;
2. `people.organ_id` legado vira `counterparty_organ_id` dos movimentos de entrada;
3. lotacao destino dos movimentos de entrada e migrada para `destination_mte_destination_id` (por lookup);
4. `invoices/payments/reimbursement_entries` legados recebem `despesa_reembolso`.

## 15.2 Validacoes de migracao

1. pessoas sem duplicidade de CPF;
2. todo movimento com contraparte valida;
3. todo movimento de entrada com lotacao destino valida;
4. nenhum titulo legado sem natureza financeira definida.

## 15.3 Rollback operacional

1. backup completo antes de cada fase com migracao estrutural;
2. scripts de rollback por migracao;
3. janelas de deploy com ponto de restauracao validado.

---

## 16) Plano de testes e qualidade

## 16.1 Testes funcionais

1. abertura de movimento entrada;
2. abertura de movimento saida;
3. fluxo completo entrada ate encerramento;
4. fluxo completo saida ate encerramento;
5. cobranca e recebimento parcial/total;
6. inadimplencia e notificacao.

## 16.2 Testes financeiros

1. titulo de despesa nao aparece em carteira de receita;
2. titulo de receita nao entra em cobertura de pagamento;
3. relatorios segregados por natureza;
4. conciliacao por competencia sem cruzamento indevido.

## 16.3 Testes de regressao

1. CRUD de pessoas e orgaos;
2. dashboard atual de entrada;
3. exportacoes auditoria e dossie;
4. permissao e controle de acesso;
5. scripts de deploy, backup e healthcheck.

## 16.4 Testes de performance

1. listas de movimentos com filtros de direcao/natureza;
2. dashboard financeiro segregado;
3. relatorios premium com periodo anual;
4. carteira de inadimplencia com alto volume.

---

## 17) KPI de sucesso da implantacao

1. `% de movimentos com campos obrigatorios completos` >= 99%;
2. `% de pessoas duplicadas por CPF` = 0%;
3. `tempo medio de triagem` reduzido em >= 20%;
4. `tempo medio para emissao de cobranca em saida` <= 2 dias uteis;
5. `taxa de adimplencia dos recebiveis` com meta definida por gestao;
6. `incidentes criticos financeiros por mes` = 0;
7. `acuracia de relatorio segregado (receita x despesa)` = 100%.

---

## 18) Riscos e mitigacoes

| Risco | Impacto | Mitigacao |
|---|---|---|
| Quebra de consultas por assumir 1 assignment por pessoa | Alto | adaptar repositorios para movimento ativo + historico, com testes de regressao |
| Mistura de receita e despesa em relatorios | Alto | `financial_nature` obrigatoria e filtros padrao por natureza |
| Dados legados sem lotacao MTE valida | Medio | rotina de saneamento e tabela de equivalencia antes da migracao final |
| Inadimplencia sem processo de cobranca padrao | Medio | workflow de cobranca por faixa de atraso e alertas SLA |
| Resistencia a mudanca operacional | Medio | treinamento, piloto controlado e hipercare com playbook |

---

## 19) Resultado esperado apos implantacao

1. MTE operando os dois fluxos no mesmo sistema, com cadastro unico de pessoas.
2. Distincao clara no momento da abertura entre:
- pessoa chegando ao MTE (orgao origem + lotacao destino MTE);
- pessoa saindo do MTE (lotacao origem MTE + orgao/empresa destino).
3. Controle financeiro completo de pagar e de receber com trilha auditavel.
4. Orcamento e projecoes segregados, sem abatimento cruzado.
5. Dashboards e relatorios executivos completos para as duas frentes.

---

## 20) Checklist de decisao para inicio imediato

1. aprovar modelo de dados proposto para movimentos N por pessoa;
2. aprovar catalogo de status do fluxo de saida MTE;
3. aprovar regras financeiras segregadas por natureza;
4. definir metas de adimplencia e SLA de cobranca;
5. autorizar inicio da Fase 0 com feature flags.
