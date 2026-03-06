# Especificação Técnica Consolidada - melhorias1

## 1) Objetivo
Consolidar as melhorias solicitadas para BPMN, timeline, dossiê documental, cadastro de pessoas e dashboard executivo, com entregas incrementais e rastreáveis.

## 2) Escopo funcional (requisitos)

### RF-01 - Exigência de evidência para encerrar etapa BPMN
- No cadastro da etapa do fluxo BPMN, deve existir um parâmetro `exige_evidencia`.
- Quando ativo, a etapa só pode ser encerrada se houver pelo menos um item de evidência:
  - anexo de timeline, ou
  - link de evidência.
- A validação ocorre no backend no momento do avanço de etapa.
- Mensagem de erro deve informar claramente que a etapa exige anexo/link.

### RF-02 - Tags em etapa BPMN
- No cadastro da etapa BPMN, permitir tags livres (`step_tags`) para metadados operacionais.
- Tags serão armazenadas em formato padronizado (lista única, sem duplicidade).
- Exemplo de uso: `data_transferencia_efetiva`, `juridico`, `financeiro`.

### RF-03 - Inserção de anexos/links na timeline operacional
- Na aba `people/show?id=&tab=timeline`, deve ser possível registrar evidências (anexo/link) no contexto das etapas.
- Evidência pode ser registrada independentemente de a etapa exigir encerramento com evidência.
- Fluxo de retificação continua válido como trilha imutável (sem sobrescrever histórico).

### RF-04 - Confirmação "salvar e encerrar etapa"
- Ao salvar evidência em etapa em aberto, o sistema deve perguntar se o usuário deseja também encerrar/avançar etapa.
- Se usuário confirmar, executar: salvar evidência -> tentar avanço de etapa.
- Se não confirmar, salvar somente evidência.

### RF-05 - Novo dashboard inicial
- Dashboard atual deve ser preservado como `dashboard2`.
- Nova tela inicial deve conter:
  - orçamento anual vigente (reembolso),
  - gasto acumulado no ano,
  - saldo disponível,
  - gráfico mensal (real x planejado) com linha de limite orçamentário,
  - projeções empilhadas: pessoas ativas + pessoas em pipeline.

### RF-06 - Data prevista de início efetivo (entrada/saída)
- No cadastro de pessoas, incluir campo de data prevista para início efetivo (entrada e saída).
- Enquanto não houver transferência efetiva, projeções financeiras usam essa data prevista.
- Quando houver etapa com tag `data_transferencia_efetiva`, a data real substitui automaticamente a prevista nas projeções.

### RF-07 - CRUD de tipos de documento
- Criar CRUD para `document_types`.
- Pré-cadastrar os tipos atualmente existentes:
  - Currículo
  - Ofício ao órgão
  - Resposta do órgão
  - Boleto
  - CDO
  - Publicação DOU
  - Espelho de custo
  - Comprovante de pagamento
- Permitir gestão de ativo/inativo.

### RF-08 - Tipos de documento esperados por etapa BPMN
- Em cada etapa BPMN, permitir selecionar um ou mais tipos de documento esperados.
- Ao anexar documento no dossiê com contexto da etapa, sugerir/categorizar automaticamente o tipo.

### RF-09 - UX da aba de documentos
- Em `people/show?id=&tab=documents`, após upload de documento, permanecer na própria aba de documentos.
- A aba deve abrir com:
  - lista de documentos já existentes (ou estado vazio),
  - formulário `document-form` recolhido por padrão,
  - botão explícito para expandir e inserir documento.

## 3) Requisitos não funcionais
- Compatibilidade com arquitetura atual (MVC + repositórios + serviços).
- Auditoria e eventos preservados para ações críticas.
- Backward compatibility de dados existentes.
- Sem quebra de segurança de upload (extensão, MIME, assinatura, tamanho).

## 4) Modelo de dados proposto
- `assignment_flow_steps`
  - `requires_evidence_close` TINYINT(1) NOT NULL DEFAULT 0
  - `step_tags` VARCHAR(500) NULL
- `timeline_event_links`
  - `id`, `timeline_event_id`, `person_id`, `url`, `label`, `created_by`, `created_at`
- (Fase posterior) `assignment_flow_step_document_types`
  - `id`, `flow_step_id`, `document_type_id`, `is_required`

## 5) Plano de implementação por fases

### Fase 1 (início imediato)
- Base de evidência por etapa BPMN:
  - persistir `requires_evidence_close` e `step_tags` em etapas;
  - bloquear avanço sem evidência quando exigido.
- Evidência por link na timeline:
  - permitir registrar links junto ao evento/retificação;
  - exibir links na timeline.
- UX rápida em documentos:
  - manter usuário na aba `documents` após upload;
  - lista primeiro e formulário recolhido.

### Fase 2
- Ação por item da timeline para adicionar evidência contextual por etapa.
- Confirmação "salvar e encerrar etapa" no fluxo de evidência.
- Melhorias de mensagens de validação por etapa.

### Fase 3
- CRUD completo de tipos de documento (tela, rotas, permissões).
- Vinculação de tipos esperados por etapa BPMN.
- Categorização automática no upload conforme etapa.

### Fase 4
- Campo de data prevista de início efetivo (entrada/saída) + regras de substituição por data efetiva.
- Novo dashboard inicial completo e migração do atual para `dashboard2`.

## 6) Critérios de aceite da Fase 1
- Etapa BPMN configurada com `exige evidência` não avança sem anexo/link.
- Cadastro/edição de etapa permite marcar exigência e informar tags.
- Timeline exibe links registrados.
- Upload de documento retorna para `tab=documents`.
- Aba de documentos inicia com lista (ou vazio) e formulário recolhido.

## 7) Status
- Este documento já está refinado e priorizado.
- Fase 1 implementada no código (evidência obrigatória por etapa, links na timeline e UX da aba de documentos).
- Fase 2 implementada no código (ação contextual por item da timeline, fluxo "salvar e encerrar etapa" e mensagens de validação mais claras por etapa).
- Fase 3 implementada no código (CRUD de tipos de documento, vínculo de tipos esperados por etapa BPMN e categorização automática no upload por contexto de etapa).
- Fase 4 implementada no código (datas previstas/efetivas de movimentação com substituição automática por tag `data_transferencia_efetiva` nas projeções e novo dashboard inicial com preservação do dashboard anterior em `dashboard2`).
