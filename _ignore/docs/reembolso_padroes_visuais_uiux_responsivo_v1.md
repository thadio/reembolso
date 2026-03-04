# REEMBOLSO — Padrões Visuais (UI) e Experiência (UX) para Desktop e Mobile (v1)

**Objetivo:** definir padrões visuais e de usabilidade para o sistema **Reembolso**, garantindo uma experiência **bonita, moderna, responsiva, consistente e eficiente** para operação diária e uso executivo (dashboards e relatórios), em **desktop e mobile**, com foco em performance e simplicidade (ambiente compartilhado).

---

## 1) Princípios de design (o “norte” do produto)

1. **Clareza antes de beleza**  
   O usuário precisa entender rapidamente: situação, próximo passo, pendências, custo e risco.

2. **Consistência absoluta**  
   Mesmos componentes com o mesmo comportamento em todas as telas (botões, tabelas, filtros, modais, toasts).

3. **Velocidade operacional**  
   Reduzir cliques, evitar recarregamentos desnecessários, priorizar ações rápidas e atalhos.

4. **Informação em camadas**  
   Mostrar o essencial primeiro; detalhes em expanders, abas, drawers e tooltips.

5. **Acessibilidade pragmática**  
   Contraste adequado, foco visível, navegação por teclado nas ações críticas e textos legíveis.

---

## 2) Layout e grid (responsividade)

### 2.1 Breakpoints recomendados
- **Mobile:** 360–480px
- **Tablet:** 768px
- **Desktop:** 1024px+
- **Wide:** 1280px+

### 2.2 Estrutura base (Shell)
**Desktop**
- Sidebar fixa (colapsável) + Topbar
- Área central com largura máxima (ex.: `max-width: 1280px`) para legibilidade
- Breadcrumb + título da página + ações primárias

**Mobile**
- Sidebar vira **menu hambúrguer**
- Topbar fixa com:
  - título curto
  - botão “+” (ação principal) quando aplicável
- Conteúdo em coluna única, cards, e tabelas adaptadas

### 2.3 Grid e espaçamentos
- Grid 12 colunas (desktop), 4 colunas (mobile)
- Espaçamento base: 8px (escala 8/16/24/32)
- Cards: padding 16–24px; em mobile, 12–16px

---

## 3) Tipografia (legibilidade e hierarquia)

### 3.1 Escala tipográfica sugerida
- **H1 (título página):** 20–24px (desktop), 18–20px (mobile)
- **H2 (seções):** 16–18px
- **Body (texto):** 14–16px
- **Caption (metadados):** 12–13px

### 3.2 Regras de leitura
- Limitar linhas a ~70–90 caracteres (desktop)
- Em mobile: evitar parágrafos longos, preferir bullets e linhas curtas
- Priorizar números e datas em fonte monoespaçada (opcional) para leitura rápida

---

## 4) Cores e estilo (tema “institucional moderno”)

### 4.1 Paleta (regras)
- **Neutros predominantes** (cinzas claros e fundos limpos)
- **1 cor primária** para ações (ex.: azul institucional)
- **Cores semânticas**:
  - Sucesso (verde)
  - Atenção (amarelo/âmbar)
  - Erro (vermelho)
  - Info (azul claro)

> Regra: não usar mais de 1 cor “forte” por tela, para evitar poluição visual.

### 4.2 Feedback visual
- Estados de componente:
  - default, hover, active, disabled, focus
- Ações destrutivas sempre em vermelho + confirmação
- Campos inválidos: borda/vermelho + mensagem curta (1 linha)

---

## 5) Componentes padrão (design system mínimo)

### 5.1 Botões
- **Primary:** ação principal (1 por tela, no máximo 2)
- **Secondary:** ações alternativas
- **Ghost/Link:** ações de baixo risco
- **Danger:** excluir/cancelar/estornar

Regras:
- Texto sempre verbo + objeto: “Salvar pessoa”, “Gerar ofício”, “Registrar pagamento”
- Ícones só quando agregam (ex.: +, download, printer)

### 5.2 Campos e formulários
- Label acima do campo (melhor no mobile)
- Placeholder nunca substitui label
- Campos com máscara (CPF, datas) com validação robusta
- Seções longas: dividir em **abas** ou **wizard** (3–5 passos)
- “Salvar rascunho” quando o formulário for longo (ex.: ofícios, custos)

### 5.3 Tabelas (ponto crítico do sistema)
- Tabela sempre com:
  - cabeçalho fixo (se possível)
  - paginação
  - ordenação
  - filtros
  - “colunas essenciais” (mobile)
- **Mobile:** tabelas viram “cards por linha”
  - ex.: Pessoa → card com Nome, Órgão, Status, Próxima ação

### 5.4 Badges / Status
Padrão de status (badge):
- Cinza: neutro/rascunho
- Azul: em andamento
- Verde: concluído
- Vermelho: bloqueado/erro
- Âmbar: pendência/atenção

Badge deve ser:
- curto (1–2 palavras)
- consistente em todo sistema

### 5.5 Cards (Resumo e KPIs)
- Card KPI:
  - valor grande
  - label pequeno
  - variação (↑/↓) opcional
- Card “Próxima ação”:
  - texto objetivo
  - botão de ação

### 5.6 Modais, drawers e toasts
- **Modal:** confirmação e formulários pequenos
- **Drawer (lateral):** detalhes sem perder contexto (ex.: detalhe rápido da pessoa na lista)
- **Toast:** feedback rápido (“Salvo”, “Upload concluído”, “Pagamento registrado”)
- Evitar alert() do browser

---

## 6) Padrões de navegação e fluxo (UX)

### 6.1 Regra do “próximo passo”
Em telas de pessoa/fluxo, sempre mostrar:
- **Situação atual** (status)
- **Pendências** (com contagem)
- **Próxima ação recomendada** (com botão)

### 6.2 Busca e filtros (essencial)
- Busca global no topo (nome/CPF/órgão/nº SEI)
- Filtros persistentes (salvar preferências do usuário)
- Botão “Limpar filtros”
- Mostrar “chips” dos filtros aplicados

### 6.3 Estados da interface (empty, loading, error)
- **Empty state** com ação:
  - “Nenhuma pessoa cadastrada — Cadastrar pessoa”
- **Loading state** com skeleton (preferível) ou spinner discreto
- **Error state** com mensagem humana + ação (“Tentar novamente”)

### 6.4 Padrões de confirmação (segurança e auditabilidade)
- Ações críticas exigem:
  - confirmação (modal)
  - registro em auditoria
- Exemplo: “Excluir documento”, “Marcar como pago”, “Alterar data de entrada”

### 6.5 Acessos rápidos (produtividade)
- Atalhos na lista:
  - “Ver”, “Anexar”, “Adicionar evento”, “Gerar ofício”
- “Ações em lote” onde fizer sentido (ex.: exportar, mudar status, gerar relatórios)

---

## 7) Timeline e dossiê (componentes “assinatura” do sistema)

### 7.1 Timeline
- Layout vertical
- Eventos com:
  - ícone (categoria)
  - data clara
  - título curto
  - detalhes em expand
  - anexos vinculados
- Destaque do “evento atual” e “evento atrasado”

### 7.2 Dossiê (documentos)
- Lista com:
  - tipo, data, referência, tags
  - ação: baixar / visualizar / editar metadados
- Upload com drag-and-drop
- Pré-visualização quando possível (PDF em aba nova)

---

## 8) Dashboard (executivo e operacional)

### 8.1 Estrutura padrão
- Linha 1: KPIs principais
- Linha 2: gráficos (série temporal + pareto)
- Linha 3: alertas e pendências

### 8.2 Leitura rápida
- Sempre mostrar:
  - período selecionado
  - filtros ativos
  - fonte do dado (Previsto / Efetivo / Ambos)
- Evitar excesso de gráficos; preferir 2–3 bem escolhidos

---

## 9) Padrões de mobile (detalhamento)

### 9.1 Regras de ouro
- 1 coluna
- botões grandes (44px altura mínimo)
- evitar tabelas; usar cards
- inputs com teclado adequado (tel/email/number/date)
- ações primárias fixas no topo (ou “floating action button” quando aplicável)

### 9.2 Componentes mobile recomendados
- Drawer para filtros
- Tabs horizontais para perfil da pessoa (scroll)
- Ação primária sempre visível

---

## 10) Microcopy (textos de interface)

### 10.1 Tom e estilo
- Objetivo, profissional e sem burocratês desnecessário
- Mensagens curtas e acionáveis

### 10.2 Padrões de mensagem
- Sucesso: “Pagamento registrado.”
- Erro: “Não foi possível salvar. Verifique os campos destacados.”
- Confirmação: “Confirmar pagamento deste boleto?” + “Isso será registrado na auditoria.”

---

## 11) Performance de UI (essencial em shared hosting)

- Evitar telas com “tudo ao mesmo tempo”
- Carregar dados por demanda (tabs lazy-load)
- Paginação sempre
- Exportações (CSV/PDF) via endpoints dedicados e streaming quando possível

---

## 12) Checklist de consistência (para revisão final)

- [ ] 1 ação primária por tela (no máximo 2)
- [ ] Campos com label + validação + mensagens
- [ ] Tabelas paginadas e com filtros
- [ ] Estados: empty/loading/error implementados
- [ ] Timeline e dossiê consistentes
- [ ] Mobile sem tabelas “quebradas” (cards)
- [ ] Acessibilidade: foco, contraste, leitura
- [ ] Auditoria e confirmações em ações críticas

---

## 13) Sugestão prática de stack de UI (sem “peso”)

Para manter leve no HostGator:
- HTML + CSS (utility simples) + JS leve
- Alternativa: Tailwind (somente se build for viável) — caso contrário, CSS utilitário manual
- Chart.js para gráficos (leve e simples)

---

**Fim do documento.**
