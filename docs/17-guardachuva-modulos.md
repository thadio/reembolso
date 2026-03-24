# 17 - Estudo de Camada Guardachuva para Modulos

## Objetivo
Definir uma arquitetura evolutiva para transformar o sistema atual em uma plataforma guardachuva, onde o usuario autenticado entra primeiro em uma pagina inicial de modulos e depois acessa apenas os modulos permitidos.

Contexto de negocio informado:
- Reembolso atual passa a ser um modulo.
- Desenho de processos (BPMN) passa a ser um modulo.
- Gestao de usuarios passa a ser um modulo.
- Novos modulos previstos: auxilio transporte, indenizacao por trabalho fronteirico, recomposicao de valores ao erario.

## Diagnostico do estado atual (base de codigo)

### Pontos fortes para reaproveitamento imediato
- Autenticacao centralizada ja existe e esta madura (`Auth`, lockout, expiracao de senha, auditoria).
- RBAC ja esta implementado com `roles`, `permissions`, `role_permissions`, `user_roles`.
- O menu ja e orientado por permissao no layout.
- Ha separacao MVC clara (`Controllers`, `Services`, `Repositories`) e padrao de rotas consistente.

### Pontos de acoplamento atual
- Login redireciona sempre para `/dashboard` (sem etapa de selecao de modulo).
- A rota raiz `/` e o dashboard atual, nao uma home de modulos.
- Permissoes foram criadas por funcionalidade, mas nao existe ainda um conceito explicito de "modulo" no dominio.
- O modulo BPMN hoje esta acoplado ao contexto de pessoas/pipeline (`/pipeline-flows`), sem camada de portal.

## Principios da solucao guardachuva
- Reaproveitar 100% do que ja existe no modulo Reembolso, sem reescrita.
- Introduzir camada superior minima: portal de entrada + catalogo de modulos.
- Evoluir sem big bang, com compatibilidade de rotas antigas.
- Tratar modulo como entidade de governanca (nome, slug, rota, permissao de acesso, status).
- Preparar suporte para modulos internos (mesmo codigo-base) e externos (outra aplicacao com SSO).

## Arquitetura alvo recomendada (faseada)

### Decisao principal
Adotar **Monolito Modular com Portal Guardachuva** no curto prazo.

Motivo:
- Menor risco e menor custo para chegar rapido ao resultado.
- Preserva o que esta pronto.
- Permite adicionar novos modulos por cadastro/configuracao.
- Mantem opcao de evoluir para modulos externos no futuro, sem quebrar o portal.

### Camadas
1. **Camada Portal (Guardachuva)**
- Home apos login com cards de modulos permitidos.
- Seletor de modulo como ponto de navegacao primario.

2. **Camada de Catalogo de Modulos**
- Define metadados do modulo (slug, nome, rota de entrada, icone, tipo interno/externo, permissao gate).

3. **Camada de Controle de Acesso**
- Reaproveita RBAC existente.
- Introduz permissao de acesso por modulo (`module.<slug>.access`) para governanca simples.
- Mantem permissoes finas atuais para operacoes internas de cada modulo.

4. **Camada de Modulos de Negocio**
- Reembolso (existente).
- BPMN (existente via pipeline flows).
- Usuarios (existente).
- Modulos futuros (novos contextos de negocio).

## Modelo funcional proposto

### Fluxo de autenticacao e entrada
1. Usuario faz login.
2. Sistema autentica como hoje.
3. Redireciona para `/home` (portal guardachuva).
4. Portal lista apenas os modulos que o usuario pode acessar.
5. Usuario escolhe modulo e entra na rota de entrada definida para ele.

### Catalogo de modulos (proposta de dados)
Tabela sugerida: `system_modules`
- `id`
- `slug` (unico)
- `name`
- `description`
- `icon`
- `entry_type` (`internal_route` | `external_url`)
- `entry_path` (ex.: `/dashboard`, `/pipeline-flows`, `/users`)
- `external_url` (quando `entry_type = external_url`)
- `required_permission` (ex.: `module.reembolso.access`)
- `sort_order`
- `is_active`
- `created_at`, `updated_at`, `deleted_at`

Observacao:
- No inicio, se quiser reduzir mudanca de banco, o catalogo pode nascer em arquivo de configuracao.
- Em seguida, migrar para tabela para ganhar governanca por UI.

### Estrategia de permissoes
Novo nivel de permissao recomendado:
- `module.reembolso.access`
- `module.bpmn.access`
- `module.users.access`
- `module.auxilio_transporte.access`
- `module.indenizacao_fronteirico.access`
- `module.recomposicao_erario.access`

Convivencia com o legado:
- Continuar usando permissoes atuais de operacao (`people.manage`, `invoice.manage`, `users.manage`, etc.).
- A permissao de modulo funciona como "porta de entrada".

## Mapeamento inicial de modulos (sobre o que ja existe)

### Modulo 1 - Reembolso
- Entrada: `/dashboard` (ou `/people`, conforme estrategia de UX).
- Permissao gate: `module.reembolso.access`.
- Permissoes internas existentes: `dashboard.view`, `people.*`, `invoice.*`, `report.view`, `budget.*`, etc.

### Modulo 2 - BPMN
- Entrada: `/pipeline-flows`.
- Permissao gate: `module.bpmn.access`.
- Permissoes internas existentes: `people.manage` (transicao gradual) + futuras permissoes especificas de BPMN.

### Modulo 3 - Gestao de Usuarios
- Entrada: `/users`.
- Permissao gate: `module.users.access`.
- Permissoes internas existentes: `users.view`, `users.manage`.

## Estrategia de UX/Navegacao
- Tela inicial apos login: cards de modulos com nome, descricao curta e acao "Entrar".
- Modulos sem acesso nao aparecem.
- Se usuario tem acesso a apenas 1 modulo, pode haver redirecionamento automatico opcional para esse modulo.
- Sidebar atual continua existindo dentro do modulo, evitando ruptura de uso para equipes atuais.
- Inserir atalho "Inicio de modulos" no topo/sidebar para voltar ao guardachuva.

## Roadmap recomendado (sem big bang)

### Fase 1 - Fundacao guardachuva (baixo risco)
- Criar rota e controller da home de modulos (`/home`).
- Mudar redirect de login para `/home`.
- Cadastrar modulos iniciais (Reembolso, BPMN, Usuarios).
- Exibir modulos por permissao.
- Manter rotas antigas funcionando.

### Fase 2 - RBAC de modulo
- Incluir permissoes `module.*.access`.
- Ajustar papeis padrao no seed/migration.
- Atualizar tela de papeis/permissoes para incluir os gates de modulo.
- Adicionar fallback temporario para nao bloquear ambiente antigo durante rollout.

### Fase 3 - Organizacao de navegacao
- Reorganizar menu para destacar modulo ativo.
- Criar "switcher de modulo".
- Padronizar pagina inicial de cada modulo.

### Fase 4 - Preparacao para modulos externos
- Suportar `entry_type = external_url`.
- Definir estrategia SSO (token assinado curto, OpenID Connect ou reverse proxy interno).
- Auditar eventos de entrada/saida por modulo.

## Impactos tecnicos esperados (arquivos principais)
- `app/Controllers/AuthController.php`: redirect pos-login.
- `routes/web.php`: nova rota de home guardachuva.
- Novo `app/Controllers/HomeController.php` (ou `PortalController.php`).
- Novo `app/Services/ModuleCatalogService.php`.
- Novo `app/Views/home/index.php`.
- `app/Views/layout.php`: link/acao para voltar ao portal e, opcionalmente, switcher.
- `db/migrations/*`: permissoes `module.*.access` e (opcional) tabela `system_modules`.
- `db/seed.php`: seed de modulos e atribuicao de permissoes iniciais por papel.

## Riscos e mitigacoes
- Risco: usuario perde acesso apos rollout de novas permissoes de modulo.
  Mitigacao: fallback temporario por permissao legada + script de homologacao de papeis.

- Risco: BPMN ficar semanticamente preso ao modulo Pessoas.
  Mitigacao: criar gates proprios de BPMN desde cedo e separar nomenclatura de permissao.

- Risco: crescimento de menu e confusao de navegacao.
  Mitigacao: separar navegacao em dois niveis (portal de modulos e menu interno por modulo).

- Risco: integracao futura com modulos externos exigir SSO mais robusto.
  Mitigacao: deixar `entry_type` preparado no catalogo e escolher protocolo antes da fase de externalizacao.

## Criterios de aceite da iniciativa
- Login leva ao portal de modulos.
- Usuario ve somente modulos permitidos.
- Reembolso, BPMN e Usuarios entram por seus respectivos cards sem quebrar fluxo atual.
- Rotas antigas continuam validas durante janela de transicao.
- Auditoria registra acesso ao portal e entrada em modulo.

## Estimativa macro
- Fase 1: 3 a 5 dias uteis.
- Fase 2: 2 a 4 dias uteis.
- Fase 3: 2 a 4 dias uteis.
- Fase 4: 3 a 7 dias uteis (depende de SSO e modulos externos).

Total inicial sem externalizacao: **7 a 13 dias uteis**.

## Recomendacao final
Implementar primeiro a camada guardachuva interna (portal + catalogo + gate de modulo) e manter o modulo de Reembolso como esta internamente. Esse caminho entrega valor rapido, reduz risco operacional e cria a base certa para conectar novos modulos de negocio nos proximos ciclos.
