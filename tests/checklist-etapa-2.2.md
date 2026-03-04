# Checklist de Testes — Fase 2.2 (Auditoria no Perfil 360)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] `php db/seed.php` executado
- [ ] Existe pessoa cadastrada com trilha em `audit_log`

## Listagem no Perfil 360
- [ ] Secao de Auditoria exibe registros relacionados a pessoa
- [ ] Exibe entidade, acao, data/hora, usuario e IP
- [ ] Exibe detalhes de `before_data`, `after_data` e `metadata` quando disponiveis

## Filtros basicos
- [ ] Filtro por entidade (`audit_entity`) restringe resultados
- [ ] Filtro por acao (`audit_action`) restringe resultados
- [ ] Filtro de busca (`audit_q`) encontra por entidade/acao/usuario
- [ ] Filtros por periodo (`audit_from`, `audit_to`) restringem por `created_at`
- [ ] Acao "Limpar" remove filtros e retorna ao estado padrao

## Paginacao
- [ ] `audit_page` navega corretamente entre paginas
- [ ] Contadores de exibicao (inicio/fim/total) condizem com os dados
- [ ] Paginacao da auditoria preserva estado de timeline/documentos

## Exportacao CSV
- [ ] `GET /people/audit/export?person_id={id}` retorna arquivo CSV
- [ ] Exportacao respeita filtros `audit_*` informados
- [ ] CSV inclui cabecalho e colunas principais (`entidade`, `acao`, `usuario`, payloads)

## Seguranca
- [ ] Usuario sem permissao `audit.view` nao visualiza trilha de auditoria no Perfil 360
- [ ] Usuario sem permissao `audit.view` recebe 403 em `/people/audit/export`
