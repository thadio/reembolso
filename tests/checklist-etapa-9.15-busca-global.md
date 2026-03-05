# Checklist de Testes - Ciclo 9.15 (Busca global unificada)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.view`
- [ ] Base com dados em pessoas, orgaos, processo formal e documentos

## Acesso e navegacao
- [ ] Item de menu `Busca global` aparece para perfil com acesso
- [ ] `GET /global-search` abre tela sem erro
- [ ] Campo de busca exige minimo de 3 caracteres

## Consulta unificada
- [ ] Busca por CPF retorna pessoa correspondente
- [ ] Busca por numero SEI retorna pessoa e/ou processo formal relacionado
- [ ] Busca por dado de DOU (`dou_edition` ou link) retorna registro de processo formal
- [ ] Busca por nome de orgao retorna resultados em secao de orgaos e entidades relacionadas
- [ ] Busca por titulo/tipo/tag de documento retorna secao de documentos

## Escopo e resultados
- [ ] Filtro `scope=all` retorna secoes combinadas
- [ ] Filtro `scope=people` limita resultado a pessoas
- [ ] Filtro `scope=organs` limita resultado a orgaos
- [ ] Filtro `scope=process_meta` limita resultado a processo formal/DOU
- [ ] Filtro `scope=documents` limita resultado a documentos

## Seguranca e sensibilidade
- [ ] Usuario sem `people.cpf.full` visualiza CPF mascarado
- [ ] Usuario sem `people.documents.sensitive` nao visualiza documentos `restricted/sensitive` na busca
- [ ] Usuario com `people.documents.sensitive` visualiza documentos `restricted/sensitive`
- [ ] Endpoint permanece protegido por `people.view`

## Governanca e regressao
- [ ] `audit_log` registra busca com `entity=global_search` e `action=search`
- [ ] `system_events` registra `global_search.executed`
- [ ] Rotas existentes de pessoas/orgaos/processo/documentos continuam funcionais
