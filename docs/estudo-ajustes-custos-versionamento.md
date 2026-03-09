# Estudo Técnico: Ajustes da Tela de Custos com Versionamento Automático

## Objetivo
Aplicar uma reformulação da aba de custos da pessoa para reduzir complexidade operacional e garantir rastreabilidade completa das revisões financeiras.

## Problema identificado
- Fluxo atual fragmentado em duas ações manuais: criar versão e adicionar item unitário.
- Cadastro de custos não orientado ao catálogo completo.
- Falta de edição em lote com projeções imediatas durante o preenchimento.
- Versionamento dependente de ação manual, gerando risco de perda de histórico entre ajustes.

## Requisitos implementados
- Geração automática de rótulo da versão no padrão `Vn - dd/mm/aaaa`.
- Criação automática da V1 no primeiro salvamento da tabela.
- Em qualquer novo salvamento, criação automática de nova versão (V2, V3, ...), com histórico preservado.
- Tela com tabela única contendo todos os itens ativos do catálogo.
- Edição em lote dos valores (com vigência e observações por item).
- Cálculos em tempo real:
  - total do período informado;
  - total anualizado;
  - valor projetado até o fim do ano corrente, considerando início de vigência.
- Manutenção do detalhamento das versões anteriores.

## Ajustes UX (2º passe)
- Campo `Fim da vigência` removido da tabela de edição.
- Campo `Início da vigência` pré-carrega por prioridade:
  1. `Início efetivo (real)`;
  2. `Início efetivo (previsto)` quando o real não existir.
- `Periodicidade` passou a ser editável por linha:
  - inicia com o valor padrão do item no catálogo;
  - permite override para `mensal`, `anual` ou `unico` antes de salvar nova versão.
- Aba de custos agora abre em modo de leitura (sem edição) com a versão ativa exibida.
- Edição ocorre somente por ação explícita em “Ajustar/alterar e gerar nova versão de custos”.
- Botão final simplificado para “Salvar nova versão”.
- Aba de reembolsos reais ajustada para priorizar visualização em tabela e abrir formulário apenas sob ação explícita.

## Estratégia de implementação

### Backend (domínio)
- `CostPlanService` recebeu o método `saveTable(...)`:
  - valida e normaliza os dados da tabela;
  - cria sempre uma nova versão;
  - grava os itens em lote;
  - registra auditoria por item e por versão;
  - registra evento de sistema da gravação em lote.
- `profileData(...)` passou a expor:
  - `suggested_version_label`;
  - `next_version_number`.
- Padronização do rótulo automático também para versões iniciais e criação de versão via serviço.

### Controller
- `PeopleController::storeCostItem(...)` passou a usar `CostPlanService::saveTable(...)`.
- Mantido o endpoint existente (`/people/costs/item/store`) para evitar quebra de rota.

### Frontend (view + estilos + script)
- Remoção do fluxo antigo (form separado de versão e form unitário de item).
- Inclusão da tabela em lote na aba de custos:
  - todos os itens do catálogo;
  - campo de valor por item;
  - início de vigência;
  - observações;
  - colunas calculadas (anualizado e até fim do ano).
- Pré-preenchimento com dados da versão ativa, quando existente.
- Script client-side para recálculo automático dos totais enquanto digita.
- Atualização de estilos em `public/assets/css/app.css` para o novo layout.

## Impacto funcional esperado
- Menos cliques e menor risco operacional no ajuste de custos.
- Histórico confiável por versão, sem depender de ação manual extra.
- Melhor previsibilidade orçamentária no próprio momento de edição.
- Tela mais objetiva para uso diário por analistas e gestão.

## Compatibilidade e riscos
- Não houve necessidade de migração de banco para este ajuste.
- Fluxo antigo de adição unitária deixa de ser o caminho principal na UI.
- A lógica de projeção até o fim do ano depende das datas previstas do processo quando disponíveis; sem essas datas, usa a referência do ano corrente.

## Próximos passos recomendados
- Validar o fluxo em homologação com cenários de:
  - pessoa sem versão (geração da V1);
  - pessoa com versão ativa e múltiplos ajustes sucessivos;
  - início de vigência antes/depois do mês atual;
  - itens `mensal`, `anual` e `unico`.
- Atualizar checklist funcional da etapa financeira para refletir o fluxo em lote/versionamento automático.
