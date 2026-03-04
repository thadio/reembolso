# REEMBOLSO — Plano de Desenvolvimento por Fases (v1.1)

**Base:** Especificação Funcional e Técnica do Reembolso (v1). fileciteturn0file0  
**Stack alvo:** PHP  + MySQL/MariaDB em HostGator Shared Hosting (cPanel). fileciteturn0file0  
**Objetivo do plano:** orientar um desenvolvimento **rápido, limpo, bem documentado, seguro, auditável e com excelente usabilidade**, explorando um **agente de IA ultra inteligente** como motor de execução, **sem sacrificar qualidade** e respeitando limitações de ambiente compartilhado.

> **Premissas (importantes para “faseamento” com IA):**  
> - O agente é extremamente produtivo, mas **a carga por etapa deve ser limitada** para evitar regressões e manter rastreabilidade.  
> - Cada etapa entrega **um “vertical slice”** (do banco à UI) e fecha com **DoD (Definition of Done)**: testes, documentação e auditoria.  
> - Em servidor compartilhado: priorizar **simplicidade**, **baixo consumo de memória**, **dependências mínimas** e **processos idempotentes** (migrations, importações).

---

## 0) Estratégia de entrega

### 0.1 Filosofia de entrega (para IA)
- **Incremental e verificável:** pequenas fatias, sempre “rodando”.
- **Tolerância zero a “código sem contrato”:** toda feature vem com:
  - migração,
  - validações,
  - logs/auditoria,
  - telas com UX consistente,
  - documentação,
  - testes mínimos automatizados (e/ou checklist de testes manuais reproduzíveis).
- **Arquitetura estável desde o início:** MVC leve + camadas (Controllers/Services/Repositories) e padrões de segurança (CSRF, sessão, uploads, RBAC). fileciteturn0file0

### 0.2 Tamanho de carga por etapa (heurística prática)
Para um agente de IA “ultra avançado”, o ideal é que cada etapa contenha, no máximo:
- **1–2 novos domínios** (ex.: Pessoas + Órgãos), ou  
- **1 fluxo completo** com 3–5 telas, ou  
- **1 módulo financeiro** com regras (ex.: Boletos & Espelhos) + relatórios do módulo.

> Se uma etapa começar a envolver “Pessoas + Custos + Boletos + Pagamentos + Dashboard”, ela fica grande demais: quebrar.

### 0.3 Definition of Done (DoD) — obrigatório por etapa
Uma etapa só é concluída quando:
1. **Código** mergeado (branch “release/etapa-X”), build ok.
2. **Migrations** versionadas e reexecutáveis.
3. **RBAC** aplicado em todas as rotas/telas.
4. **Auditoria** registrando ações críticas (create/update/delete, anexos, mudanças de status). fileciteturn0file0
5. **Segurança mínima**: CSRF, validação de inputs, prepared statements.
6. **Documentação** atualizada (README + doc da etapa).
7. **Checklist de testes** (manual + automatizado quando aplicável).
8. **Deploy** reproduzível no HostGator (passo-a-passo).

---

## 1) Organização do trabalho (como “operar” o agente)

### 1.1 Estrutura de issues (padrão)
Cada issue deve conter:
- **Objetivo** (1 parágrafo)
- **Escopo** (o que entra / não entra)
- **Critérios de aceite**
- **Telas impactadas**
- **Tabelas impactadas (migrations)**
- **Regras de negócio**
- **Riscos**
- **Plano de teste**
- **Artefatos gerados** (prints, docs, scripts)

### 1.2 Controle de qualidade com IA
- O agente implementa, mas também:
  - gera **relatórios de testes** (HTML ou Markdown),
  - cria **scripts de carga** (seed) para QA,
  - produz **changelog** da etapa.
- Sempre que possível, manter **“Golden dataset”** pequeno para regressão (ex.: 10 pessoas, 3 órgãos, 2 boletos, 2 competências).

### 1.3 Convenções obrigatórias (para rastreabilidade)
- IDs: usar chaves numéricas autoincrement (MySQL).  
- Timestamps: `created_at`, `updated_at`, `deleted_at` (soft delete onde fizer sentido).  
- Auditoria: tabela `audit_log` com:
  - entidade, entity_id, ação, antes/depois (JSON), user_id, ip, user_agent, timestamp.  
- Documentos: armazenar em `storage/` fora de `public/`. fileciteturn0file0

---

## 2) Fases e etapas

> **Visão macro (fases):**
- **Fase 0 — Fundações**: infraestrutura, segurança, arquitetura, padrões.
- **Fase 1 — Pipeline Operacional**: Pessoas, Órgãos, Movimentação, Timeline, Dossiê.
- **Fase 2 — Orçamento & CDO**: custos previstos, CDO, projeções base.
- **Fase 3 — Reembolsos completos**: boletos, espelhos, pagamentos, conciliação.
- **Fase 4 — Inteligência e Governança**: dashboards, alertas, relatórios premium, performance.
- **Fase 5 — Hardening**: auditoria avançada, LGPD, backups, DR, qualidade final.

---

# FASE 0 — Fundação Técnica, Segurança e Padrões (obrigatória)

## Etapa 0.1 — Bootstrap do projeto + arquitetura base
**Objetivo:** criar um esqueleto sólido, simples e compatível com HostGator.

**Entregas**
- Estrutura de pastas (MVC leve):
  - `public/`, `app/Controllers`, `app/Services`, `app/Repositories`, `app/Views`, `app/Core`, `storage/`, `db/migrations/`.
- `bootstrap.php` (PDO, session, config, helpers).
- Router simples (ou roteamento por entrypoints controlados).
- Página de status “/health” (verifica DB, permissões de storage).
- `serverconfig.md` (documento de deploy/ambiente compartilhado). fileciteturn0file0

**DoD extra**
- `.env` carregado com segurança; nunca commitar segredos. fileciteturn0file0
- Padrões de log (`storage/logs/app.log`).

---

## Etapa 0.2 — Autenticação, RBAC e segurança mínima
**Objetivo:** garantir acesso controlado desde o dia 1 (sist_admin, admin, user). fileciteturn0file0

**Entregas**
- Tabelas: `users`, `roles`, `permissions`, `role_permissions`, `user_roles`.
- Login/logout, reset de senha (mínimo).
- Middleware de autorização por rota/ação.
- CSRF em forms, proteção XSS (escaping), SQLi (PDO prepared).
- Rate limit básico para login (simples, por IP/sessão).

**DoD extra**
- Logs de login/logout no `audit_log`.
- Mascaramento de CPF em listagens (padrão do sistema). fileciteturn0file0

---

## Etapa 0.3 — Auditoria, padrões de eventos e templates
**Objetivo:** estabelecer rastreabilidade e “linguagem comum” de eventos/documentos.

**Entregas**
- `audit_log` completo (com before/after em JSON).
- Catálogos parametrizáveis:
  - tipos de documento,
  - tipos de evento da timeline,
  - modalidades (Cessão, Composição, etc.). fileciteturn0file0
- Biblioteca interna de “Eventos do Sistema”:
  - função padrão `record_event(entity, type, payload...)`.
- “UI kit” simples (componentes HTML/Tailwind opcional, ou CSS próprio):
  - badges, cards, tabela, paginação, toasts.

---

# FASE 1 — Pipeline Operacional (Pessoas → Movimentação → Timeline → Dossiê)

## Etapa 1.1 — Órgãos de origem (CRUD) + busca eficiente
**Entregas**
- Tabela `organs` (dados, contatos, CNPJ, endereço, observações). fileciteturn0file0
- Tela lista (busca, filtros, paginação, ordenação).
- Tela detalhe + editar.
- Importação CSV (opcional, se simples).

**Pontos de UX**
- Busca por: nome, sigla, CNPJ.
- Ação rápida: “Ver pessoas vinculadas”.

---

## Etapa 1.2 — Pessoas (CRUD) + vínculo obrigatório ao órgão
**Entregas**
- Tabela `people` com campos essenciais (nome, CPF, nascimento, contatos, status). fileciteturn0file0
- Campos adicionais mencionados:
  - nº processo SEI,
  - lotação destino MTE,
  - modalidade pretendida. fileciteturn0file0
- Lista com filtros por status, modalidade, órgão, tags.
- Tela “Perfil 360” (abas: Resumo, Timeline, Documentos, Custos, Auditoria — ainda vazias onde não implementado).

**Pontos de UX**
- Painel lateral com resumo na lista (sem sair da página).
- CPF mascarado nas listas; full CPF só em tela com permissão.

---

## Etapa 1.3 — Movimentação + pipeline de status + eventos automáticos
**Entregas**
- Tabela `assignments` (modalidade, unidade MTE, datas alvo/efetivas).
- Pipeline de status configurável (minimamente: Interessado → Triagem → Selecionado → Ofício órgão → Custos recebidos → CDO → MGI → DOU → Ativo). fileciteturn0file0
- Ao mudar status:
  - registrar `timeline_event`,
  - registrar `audit_log`.

**Pontos de UX**
- Botões “Próxima ação” guiados no Perfil 360:
  - ex.: “Gerar ofício ao órgão”, “Registrar resposta”, “Registrar DOU”.

---

## Etapa 1.4 — Timeline (linha do tempo) completa por pessoa
**Entregas**
- Tabela `timeline_events`.
- Timeline vertical no Perfil 360:
  - badges por tipo,
  - anexos por evento,
  - edição controlada (evitar apagar eventos; preferir “retificar”).
- Eventos padrão do fluxo (catálogo inicial). fileciteturn0file0

**Qualidade**
- Renderização performática (carregar paginado se necessário).
- Export “Timeline em PDF/HTML print” (opção simples inicialmente).

---

## Etapa 1.5 — Dossiê documental (uploads seguros)
**Entregas**
- Tabela `documents` (tipo, tags, ref SEI, observação, data). fileciteturn0file0
- Upload múltiplo (drag and drop).
- Armazenamento em `storage/uploads/{people_id}/...`.
- `.htaccess` bloqueando execução na pasta de upload.
- Download protegido por permissão.

**Qualidade**
- Validação MIME/tamanho/extensão.
- Logs de upload/download no `audit_log`.

---

# FASE 2 — Orçamento & CDO (custos previstos e projeções base)

## Etapa 2.1 — Custos previstos (planejamento) por pessoa
**Entregas**
- Tabelas: `cost_plans`, `cost_plan_items`.
- Tela de custos no Perfil 360:
  - grid de itens (valor mensal, periodicidade, vigência),
  - soma automática e totalizadores anualizados. fileciteturn0file0
- Versionamento lógico (nova versão de custo previsto, mantendo histórico).

**UX**
- “Adicionar item” rápido, com itens padrão sugeridos.
- “Comparar versões” (diferença entre versões).

---

## Etapa 2.2 — CDO (reserva) + vínculo com pessoas
**Entregas**
- Tabelas: `cdos`, `cdo_people`.
- CRUD de CDO:
  - número, data, valor, período, status. fileciteturn0file0
- Vincular CDO a pessoas e totalizar.
- Relatório: “CDO por pessoa / por órgão / por período”.

**Qualidade**
- Auditoria e trilha de alterações (valor/periodicidade/status).

---

## Etapa 2.3 — Projeções base (mês/ano/próximo ano)
**Entregas**
- Serviço de projeção:
  - considera pessoas ativas (data entrada) e custos previstos vigentes.
- Tela “Projeções” com:
  - projeção do mês,
  - acumulado do ano,
  - estimativa do próximo ano. fileciteturn0file0
- Export CSV das projeções.

**Observação HostGator**
- Calcular projeções de forma eficiente (SQL + caches simples em tabela de “snapshots” se necessário).

---

# FASE 3 — Reembolsos completos (boletos, espelhos, pagamentos, conciliação)

## Etapa 3.1 — Boletos (invoices) + vínculos com pessoas
**Entregas**
- Tabelas: `invoices`, `invoice_people`.
- CRUD de boletos:
  - órgão, competência (YYYY-MM), vencimento, valor, linha digitável, anexo PDF. fileciteturn0file0
- Um boleto pode agrupar várias pessoas. fileciteturn0file0
- Lista com status: aberto/vencido/pago.

**UX**
- Tela de boleto com sublista de pessoas e valores rateados (opcional) ou “valor por pessoa” (se informado).

---

## Etapa 3.2 — Espelhos de custo (por pessoa e competência)
**Entregas**
- Tabelas: `cost_mirrors`, `cost_mirror_items`.
- Tela de espelho:
  - por pessoa + competência,
  - itens detalhados (importação CSV opcional),
  - anexos (PDF do espelho). fileciteturn0file0

**Qualidade**
- Validações: competência, duplicidade, consistência de soma.

---

## Etapa 3.3 — Conciliação automático (previsto vs efetivo) + divergências
**Entregas**
- Tabela `reconciliations` (ou derivado com snapshot).
- Tela “Conferência”:
  - tabela item-a-item: previsto, efetivo, diferença, %. fileciteturn0file0
  - marca divergências (limiares configuráveis).
- Registro de justificativa por divergência.
- Relatório PDF/HTML print de divergências.

**UX**
- Modo “somente divergências”.
- Botão “Aprovar conferência” (gera evento e trava edição — ou controla por status).

---

## Etapa 3.4 — Pagamentos (reembolsos) + comprovação
**Entregas**
- Tabela `payments`.
- Registrar pagamento:
  - data, referência de processo, valor pago, comprovante, quais boletos quitou. fileciteturn0file0
- Status do boleto atualiza automaticamente.
- Dashboard simples “a pagar / vencidos / pagos”.

---

# FASE 4 — Inteligência, relatórios premium e governança

## Etapa 4.1 — Dashboard executivo (KPIs + gráficos)
**Entregas**
- KPIs:
  - pessoas ativas,
  - custo mensal projetado,
  - total pago no ano,
  - top órgãos por custo,
  - desvios previstos vs efetivos. fileciteturn0file0
- Gráficos sugeridos na especificação:
  - série temporal previsto x efetivo,
  - pareto por órgão. fileciteturn0file0
- Exportação de dados do dashboard (CSV).

**Observação HostGator**
- Gerar gráficos com biblioteca JS leve (Chart.js) e dados agregados do backend.

---

## Etapa 4.2 — Relatórios avançados + filtros robustos
**Entregas**
- Relatórios operacionais:
  - SLA por etapa,
  - tempo médio por etapa,
  - pendências e gargalos. fileciteturn0file0
- Relatórios financeiros:
  - previsto x efetivo por pessoa/órgão/competência,
  - pagos vs a pagar,
  - divergências detalhadas. fileciteturn0file0
- Export CSV + PDF (ou HTML print).
- “Relatório pack” (ZIP) opcional.

---

## Etapa 4.3 — Alertas e SLA (governança)
**Entregas**
- Regras de alerta configuráveis:
  - órgão sem resposta há X dias,
  - divergência > %,
  - boleto vencendo em Y dias,
  - risco de gap orçamentário. fileciteturn0file0
- Tela de “Alertas” + e-mail (opcional; se e-mail, usar SMTP do cPanel).

---

# FASE 5 — Hardening final (segurança, LGPD, performance e operação)

## Etapa 5.1 — LGPD e segurança reforçada
**Entregas**
- Revisão de exposição de CPF:
  - mascaramento em listas,
  - permissão específica para ver CPF completo. fileciteturn0file0
- Trilhas de acesso (quem visualizou dados sensíveis).
- Política de retenção (soft delete vs anonimização — definir abordagem).
- Revisão de uploads (antivírus não disponível normalmente; mitigar com whitelist e checks).

---

## Etapa 5.2 — Performance & escalabilidade (shared hosting)
**Entregas**
- Índices de banco para filtros principais.
- Paginação obrigatória em listas.
- Cache simples (tabela de snapshots para projeções e KPIs).
- Jobs “assíncronos” via cron do cPanel (ex.: recomputar snapshots nightly).

---

## Etapa 5.3 — Operação: backups, restore, DR e observabilidade
**Entregas**
- Scripts:
  - backup DB (mysqldump),
  - backup storage (uploads),
  - restore com checklist.
- Guia de deploy e rollback.
- Página admin: “Saúde do sistema” + logs.

---

## 3) Plano de documentação (entregável contínuo)

### 3.1 Documentos mínimos no repositório
- `README.md` (instalação local + deploy HostGator)
- `docs/architecture.md` (visão MVC, camadas, padrões)
- `docs/security.md` (CSRF, RBAC, uploads, logs)
- `docs/data-model.md` (ERD + regras)
- `docs/workflows.md` (Jornadas A–F)
- `docs/reports.md` (relatórios e filtros)
- `docs/runbooks.md` (backup/restore, incidentes)

### 3.2 Atualização obrigatória
Toda etapa atualiza pelo menos:
- `CHANGELOG.md`
- `docs/workflows.md` (se afetar fluxo)
- `docs/data-model.md` (se afetar schema)

---

## 4) Plano de testes (adequado a servidor limitado)

### 4.1 Estratégia recomendada
- **Testes unitários críticos** (regras de cálculo, conciliação, projeções).
- **Testes de integração leves** (CRUD + permissões).
- **Checklist manual reproduzível** (para UI e fluxo).

### 4.2 “Test Pack” por etapa
Cada etapa entrega:
- `tests/checklist-etapa-X.md`
- dataset de seed (SQL/CSV)
- evidências (prints ou HTML report)

---

## 5) Riscos e mitigação

- **Complexidade financeira (rateio, lotes, divergências):**
  - mitigar com etapas 3.1 → 3.4, sempre fechando conciliação antes de avançar.
- **Performance em HostGator:**
  - mitigar com índices, paginação, snapshots, evitar PDF pesado (usar HTML print-friendly quando necessário).
- **Segurança / LGPD:**
  - mitigar com RBAC, logs, mascaramento e trilhas de visualização.
- **“Escopo infinito” (ultra completo):**
  - mitigar com DoD e faseamento: só entra o que tem critério de aceite e teste.

---

## 6) Critérios de sucesso (KPIs do projeto)

- Operacional:
  - redução do tempo de acompanhamento de etapas,
  - visibilidade clara do “próximo passo” por pessoa.
- Financeiro:
  - projeções confiáveis,
  - divergências detectadas automaticamente,
  - relatórios gerados em minutos.
- Governança:
  - auditoria completa,
  - histórico documental rastreável,
  - segurança compatível com LGPD.

---

## 7) Próximo passo imediato (o que o agente deve fazer primeiro)

1. Implementar **Fase 0 (Etapas 0.1–0.3)**: base técnica + auth/RBAC + auditoria.
2. Entregar um **MVP navegável** com:
   - login,
   - menu,
   - “Pessoas (lista vazia)” e “Órgãos (lista vazia)”.
3. Só então começar a Fase 1.

---

**Fim do plano (v1.1).**
