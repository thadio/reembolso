# REEMBOLSO — Especificação Funcional e Técnica (v1)

**Aplicação:** Reembolso (Web App em PHP)  
**Ambiente alvo:** HostGator Shared Hosting (cPanel, PHP, MySQL/MariaDB)  --> ver serverconfig.md
**Órgão:** MTE — Ministério do Trabalho e Emprego  
**Objetivo macro:** controlar o fluxo completo de **movimentação de força de trabalho** (Cessão / Composição de Força de Trabalho), a **reserva orçamentária (CDO)** e a **gestão de reembolsos** (boletos + espelhos de custo), com **linha do tempo por pessoa**, **dossiê documental**, **projeções vs efetivos** e **relatórios executivos**.

---

## 1) Visão geral do problema e da solução

O MTE recebe trabalhadores oriundos de outros órgãos/entidades públicas (órgãos de origem), por modalidades como **Cessão** e **Composição de Força de Trabalho**. O fluxo envolve:

1. Recepção de interessados/candidatos e currículos  
2. Triagem e seleção  
3. Ofício ao órgão de origem solicitando:
   - custos detalhados do servidor/empregado (remuneração + auxílios)  
   - confirmação de liberação para início no MTE  
4. Resposta do órgão de origem com custos e liberação  
5. Emissão/registro do **CDO** (Certificação de Disponibilidade Orçamentária) e reserva  
6. Ofício ao **MGI** para anuência e publicação no **DOU**  (DIário Oficial da União)
7. Efetivação da movimentação e data oficial de entrada no MTE  
8. Gestão recorrente de:
   - recebimento de **boletos** e **espelhos de custo** (por pessoa ou lote/grupo)
   - reembolsos pagos
   - conciliação de divergências entre **custo previsto** vs **custo efetivo** (espelho)

A solução “Reembolso” será um sistema web para **orquestrar, registrar, auditar e analisar** esse processo, com **UX voltada para operação diária** e **gestão executiva** (dashboards e projeções).

---

## 2) Princípios de produto (o que “ultra completo” significa aqui)

- **Rastreabilidade total:** cada pessoa tem uma “história” completa (linha do tempo + documentos + custos).  
- **Auditoria de ponta a ponta:** tudo que muda fica registrado (quem, quando, o quê, por quê).  
- **Gestão financeira de verdade:** projeções, cenários, séries históricas, comparação previsto x efetivo, alertas e suplementação.  
- **Operação simples e segura:** telas rápidas, filtros robustos, upload de documentos fluido, poucos cliques.  
- **Relatórios prontos para governança:** PDF, Excel/CSV, gráficos, e indicadores para reuniões.  
- **Preparado para evolução:** estrutura modular, dados normalizados, suporte a novos fluxos/documentos.

---

## 3) Perfis de usuário e permissões (RBAC)

### 3.1 Perfis principais
1. **Administrador do Sistema**
   - gerencia usuários, perfis, parâmetros globais, backups e integrações
2. **Operador DGP/SGP (Triagem e Movimentação)**
   - cadastra pessoas, controla etapas, documentos, ofícios, datas, status
3. **Gestor Orçamentário/Financeiro**
   - projeta custos, controla CDO, gerencia boletos/espelhos, pagamentos, conciliações
4. **Gestor/Coordenação**
   - consulta painéis, indicadores, relatórios, aprova marcos (opcional)
5. **Leitura (Auditoria/Controle)**
   - acesso apenas leitura a registros, documentos e relatórios

### 3.2 Matriz de permissões (exemplo)
- **Pessoas (CRUD):** admin, operador  
- **Órgãos de origem (CRUD):** admin, operador  
- **Movimentações/Etapas (CRUD):** operador  
- **Custos previstos (CRUD):** financeiro  
- **Boletos/espelhos (CRUD):** financeiro  
- **Pagamentos/reembolsos (CRUD):** financeiro + admin  
- **Relatórios/PDF/export:** todos exceto restrições (dados sensíveis)  
- **Logs/auditoria:** leitura (auditoria), admin

> **Nota LGPD:** CPF e dados sensíveis devem ter acesso restrito, mascaramento em listagens e logs sem exposição de valor bruto quando possível.

---

## 4) Jornadas de usuário (UX) — do início ao fim

### Jornada A — “Cadastro e triagem de interessados”
1. **Tela: Caixa de Entrada de Interessados**
   - botão “Novo interessado”
   - importação em massa (CSV) opcional
2. **Cadastro rápido (wizard em 3 passos)**
   - Identificação: Nome, CPF, nascimento, contatos
   - Origem: órgão/entidade, cargo, vínculo, modalidade pretendida (cessão/composição)
   - número processo SEI
   - lotação de destino dentro do MTE
   - Anexos: currículo + docs iniciais
3. **Triagem**
   - tags (“prioritário”, “TI”, “analítica”, “jurídico”)
   - status: *Interessado → Em triagem → Aprovado para seleção → Reprovado/Arquivado*
4. **Fila de Pendências**
   - “Faltam documentos”
   - “Aguardando entrevista”
   - “Aguardando validação de dados”

**UX essencial:** listagem com busca instantânea (nome/CPF/órgão), filtros por status/tags, e painéis laterais com detalhes sem sair da lista.

---

### Jornada B — “Seleção → ofício ao órgão de origem”
1. **Aprovar seleção**
   - define modalidade (Cessão/Composição), unidade do MTE, data alvo de início
2. **Gerar ofício ao órgão de origem**
   - modelo parametrizável (templates)
   - preenchimento automático (dados da pessoa + órgão)
   - campos editáveis + versionamento do documento
3. **Registrar expedição**
   - nº SEI, nº ofício, data de envio, canal (SEI/e-mail/outro)
   - anexar PDF assinado, comprovantes

**UX essencial:** botão “Gerar ofício” com preview e “Salvar como rascunho”.

---

### Jornada C — “Resposta do órgão: custos + liberação”
1. **Registrar resposta**
   - data de recebimento, referência SEI/documento
   - custo mensal previsto detalhado (remuneração base, gratificações, auxílios, encargos, etc.)
   - anexar espelho/cálculo do órgão (quando já vier nessa fase)
2. **Validações automáticas**
   - custo total mensal calculado
   - consistência: itens obrigatórios, periodicidade, vigência
3. **Status**
   - *Aguardando custos → Custos recebidos → Aguardando CDO*

**UX essencial:** formulário de custos em “grade” com soma automática e histórico de versões.

---

### Jornada D — “CDO: reserva orçamentária”
1. **Criar registro de CDO**
   - nº CDO, data, valor reservado, período (início/fim), UG/ação (se aplicável)
2. **Amarrar CDO às pessoas**
   - CDO pode cobrir uma pessoa ou um conjunto
3. **Status**
   - *CDO em elaboração → CDO emitido → Reserva confirmada*

**UX essencial:** visão “CDO” como objeto financeiro, com pessoas vinculadas e totais.

---

### Jornada E — “Ofício ao MGI → publicação no DOU → entrada efetiva”
1. **Gerar ofício ao MGI**
   - template + auto preenchimento
2. **Registrar publicação no DOU**
   - data de publicação, nº/edição, link (se houver), anexo PDF
3. **Registrar data oficial de entrada no MTE**
   - esta data ativa a contagem de custos no MTE
4. **Status**
   - *Aguardando MGI → Publicado no DOU → Ativo no MTE*

**UX essencial:** “Linha do tempo” com marcos, e o sistema destacando o “marco atual”.

---

### Jornada F — “Reembolsos: boletos + espelhos + pagamento + conciliação”
1. **Receber boleto(s)**
   - por pessoa ou lote
   - cadastrar boleto: órgão emissor, competência (mês/ano), vencimento, valor, linha digitável, anexo PDF
2. **Receber espelho(s) de custo**
   - anexar espelho e/ou digitar itens (ou importar planilha)
   - associar espelho a boleto (1 boleto → n pessoas; ou 1 pessoa → n boletos)
3. **Conferência**
   - sistema compara: *previsto da liberação* vs *espelho da competência*  
   - marca divergências por item e por total (% e R$)
4. **Pagamento**
   - registrar data de pagamento, n° processo, banco, comprovante
5. **Status do reembolso**
   - *Recebido → Em conferência → Aprovado → Pago → Conciliado*
6. **Tratamento de divergências**
   - justificativa, ajuste de projeções futuras, abertura de pendência, ofício de questionamento (opcional)

**UX essencial:** tela “Conferência” em modo “diferenças” (highlight) e botão “Gerar relatório de divergências”.

---

## 5) Objetos de negócio (domínios) e principais conceitos

- **Pessoa (candidato/servidor/empregado)**: indivíduo que poderá ser movimentado ao MTE  
- **Órgão de Origem**: entidade que cede/compõe e que emite cobrança (boleto)  
- **Movimentação**: “processo” da pessoa no fluxo (com modalidade, datas e marcos)  
- **Marco/Evento**: evento datado da linha do tempo (ex.: ofício enviado, CDO emitido, DOU publicado)  
- **Dossiê**: conjunto de documentos por pessoa e por processo (ofícios, currículo, publicações)  
- **Custo Previsto**: custos fornecidos pelo órgão de origem na fase de liberação/planejamento  
- **Competência**: referência mensal/periodicidade de cobrança (ex.: 2026-03)  
- **Espelho de Custo**: detalhamento do custo efetivo por competência  
- **Boleto**: cobrança recebida (pode agrupar várias pessoas)  
- **Reembolso/Pagamento**: registro do pagamento efetuado pelo MTE  
- **Conciliação**: resultado da comparação previsto x efetivo + justificativas/ajustes

---

## 6) Linha do tempo por pessoa (feature central)

### 6.1 Requisitos
- Exibir eventos em ordem cronológica com:
  - data/hora
  - tipo do evento (badge)
  - descrição curta + detalhes
  - responsável (usuário)
  - documentos vinculados
  - número do processo SEI (Sistema Eletrônico de Informações)
- Permitir:
  - adicionar evento manual (com anexos)
  - “pular para” documentos do evento
  - visualização em modo compacto (para listas)
- Eventos padrão do fluxo (mínimo):
  - cadastro do interessado
  - triagem concluída
  - seleção aprovada
  - ofício ao órgão enviado
  - resposta do órgão recebida
  - CDO emitido / reserva confirmada
  - ofício ao MGI enviado
  - DOU publicado
  - entrada no MTE
  - boletos recebidos (por competência)
  - pagamentos efetuados
  - conciliações e divergências

### 6.2 UX sugerida
- Timeline vertical no lado direito do “Perfil da Pessoa”
- Cards de evento com ícone + cor por categoria:
  - **Pessoal/triagem**, **Jurídico/administrativo**, **Orçamentário**, **Reembolso**
- “Próxima ação sugerida” no topo (ex.: “Aguardando resposta do órgão — 12 dias”).

---

## 7) Dossiê documental

### 7.1 Tipos de documentos
- Currículo
- Identificação (quando aplicável)
- Ofício ao órgão (rascunho e assinado)
- Resposta do órgão (custos + liberação)
- CDO (documentos e comprovações)
- Ofício ao MGI
- Publicação do DOU
- Boleto(s)
- Espelho(s) de custo
- Comprovante de pagamento
- Relatórios de divergência
- Outros (livre)

### 7.2 Requisitos
- Upload com drag-and-drop e múltiplos arquivos
- Metadados por documento:
  - tipo, data, referência (SEI), observação, tags
- Versionamento (opcional, mas recomendado para ofícios/modelos)
- Busca por nome, tags e tipo
- Controle de acesso (ex.: documentos com CPF)

---

## 8) Gestão de custos e projeções

### 8.1 Custo previsto (planejamento)
- Estrutura por itens:
  - remuneração base
  - gratificações
  - auxílios
  - encargos/outros
- Campos:
  - valor mensal
  - periodicidade (mensal / eventual)
  - vigência (início/fim)
  - fonte do dado (documento do órgão)
- Totalizadores:
  - custo mensal total
  - custo anual projetado (base em vigência)

### 8.2 Projeções
- Projeção mensal do MTE considerando:
  - pessoas ativas (pela data de entrada)
  - custos previstos vigentes por competência
- Projeção anual e do próximo ano
- Cenários (mínimo):
  - “Base” (previsto)
  - “Atualizado” (ajustado por efetivos recentes)
  - “Pior caso” (variação % configurável)

### 8.3 Efetivos (reembolsos)
- Importação/lançamento do espelho por competência
- Consolidação por:
  - pessoa
  - órgão de origem
  - modalidade
  - unidade do MTE
- Acompanhamento:
  - valor previsto x efetivo (R$ e %)
  - tendência (últimos 6/12 meses)
  - alertas de desvio (ex.: >5% ou >R$ X)

### 8.4 Suplementação orçamentária
- Relatório “Gap orçamentário”
  - dotação/reserva informada (parâmetros)
  - compromissos projetados
  - risco de insuficiência por mês
- Lista de pessoas/órgãos que mais impactam o gap

---

## 9) Relatórios, gráficos e exportações (ultra completo)

### 9.1 Relatórios operacionais
- Pessoas por status (pipeline)
- Pendências por etapa (SLA)
- Tempo médio por etapa (ex.: seleção → resposta do órgão)
- Linha do tempo exportável (por pessoa e por lote)

### 9.2 Relatórios financeiros
- Previsto x efetivo por competência (mês)
- Previsto x efetivo por órgão
- Previsto x efetivo por pessoa
- Pagos vs a pagar (boletos vencidos/abertos)
- Divergências detalhadas (por item de custo)
- Projeção do mês, do ano, do próximo ano

### 9.3 Gráficos sugeridos
- Série temporal: previsto x efetivo (linha)
- Pareto de custo por órgão (barra)
- Distribuição por modalidade (pizza/rosca — opcional)
- Heatmap de desvios por competência (opcional)

### 9.4 Exportações
- **CSV/XLSX** (preferencialmente CSV no HostGator, XLSX opcional)
- **PDF**:
  - relatório executivo (1–3 páginas)
  - relatório detalhado (com tabelas e anexos listados)
- “Pacote de prestação de contas” (ZIP) com PDFs + CSVs (opcional)

---

## 10) Requisitos funcionais (RF) — checklist

### Pessoas e Órgãos
- RF-01: CRUD de Órgãos de origem (dados, contatos, CNPJ, endereço, observações)
- RF-02: CRUD de Pessoas (dados pessoais, vínculo, modalidade, status)
- RF-03: Vincular pessoa a órgão de origem (obrigatório)
- RF-04: Importação em massa de pessoas (CSV) com validação

### Fluxo / Movimentação
- RF-10: Pipeline de status configurável (com etapas padrão)
- RF-11: Registro de eventos da linha do tempo (automático e manual)
- RF-12: Geração de ofícios por template (órgão e MGI)
- RF-13: Registro de metadados SEI/ofício (número, datas, anexos)
- RF-14: Registro de publicação DOU e data oficial de entrada no MTE

### Dossiê
- RF-20: Upload e gestão de documentos por pessoa e por processo
- RF-21: Classificação por tipo + tags + busca
- RF-22: Controle de acesso a documentos sensíveis

### CDO e Projeções
- RF-30: Cadastro de CDO (número, valor, período, status)
- RF-31: Vincular CDO a pessoas e totalizar
- RF-32: Custo previsto por pessoa (itens + vigência)
- RF-33: Projeções mensais/anuais e cenários

### Boletos, Espelhos e Pagamentos
- RF-40: Cadastro de boleto (por órgão, competência, vencimento, valor, anexo)
- RF-41: Boleto pode agrupar múltiplas pessoas
- RF-42: Cadastro/importação de espelho por competência (por pessoa)
- RF-43: Conciliação automático: previsto x efetivo (por item e total)
- RF-44: Registro de pagamento (data, comprovante, referência processo)
- RF-45: Painel de “abertos / vencidos / pagos / conciliados”

### Relatórios e Exportações
- RF-50: Dashboard executivo (KPIs + gráficos)
- RF-51: Relatórios filtráveis por período, órgão, modalidade, status
- RF-52: Exportação CSV e PDF

### Auditoria e Segurança
- RF-60: Log de auditoria (CRUD + anexos + status)
- RF-61: Controle de acesso por perfil (RBAC)
- RF-62: Mascaramento de CPF em listagens (ex.: ***.***.***-**)

---

## 11) Requisitos não funcionais (RNF)

- RNF-01: Compatível com PHP 8.x e MySQL/MariaDB
- RNF-02: Interface responsiva (mobile-first)
- RNF-03: Performance: listagens com paginação e busca eficiente (índices)
- RNF-04: Segurança:
  - CSRF tokens
  - validação de upload (MIME, tamanho, extensão)
  - sanitização contra XSS/SQLi (PDO prepared statements)
  - senhas com bcrypt/Argon2
- RNF-05: LGPD:
  - minimização de dados
  - trilha de auditoria
  - permissões finas
  - possibilidade de relatório de acesso (quem viu/alterou)
- RNF-06: Backups:
  - rotina de backup (script + orientação)
- RNF-07: Observabilidade:
  - logs de erro e eventos críticos
  - tela “Saúde do sistema” (opcional)

---

## 12) Modelo de dados (alto nível)

### 12.1 Entidades principais
- `users` (usuários do sistema)
- `roles`, `role_permissions` (RBAC)
- `organs` (órgãos de origem)
- `people` (pessoas/candidatos)
- `assignments` (movimentação/vínculo com MTE: modalidade, unidade, datas)
- `timeline_events` (eventos)
- `documents` (dossiê)
- `cost_plans` (custo previsto por pessoa)
- `cost_plan_items` (itens do custo previsto)
- `cdos` (reservas CDO)
- `cdo_people` (vínculo CDO ↔ pessoas)
- `invoices` (boletos)
- `invoice_people` (vínculo boleto ↔ pessoas)
- `cost_mirrors` (espelhos por competência e pessoa)
- `cost_mirror_items` (itens do espelho)
- `payments` (pagamentos/ressarcimentos)
- `reconciliations` (resultado previsto x efetivo, divergências, justificativas)
- `audit_log` (auditoria)

---

## 13) Telas (mapa de navegação) e UI/UX

### 13.1 Menu principal
- **Dashboard**
- **Pessoas**
- **Órgãos**
- **Movimentações**
- **CDO**
- **Boletos & Espelhos**
- **Pagamentos**
- **Relatórios**
- **Admin** (apenas admin)

---

O sistema deve ter cadastro de usuários, autenticaçao, matriz de acessos com perfis mínimos de acessos sistem_admin, admin e user. 

todas as credenciais devem se manter seguras e dentro do .env.

**Fim do documento (v1).**
