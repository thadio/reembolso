# Checklist de Testes - Ciclo 9.2 (RF-04: importacao CSV de pessoas)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `people.manage`
- [ ] Arquivo CSV de teste com cabecalho valido

## Endpoint e permissao
- [ ] `POST /people/import-csv` com usuario `people.manage` executa importacao/simulacao
- [ ] Usuario sem `people.manage` recebe `403` no endpoint
- [ ] Requisicao sem CSRF valida e bloqueada pelo middleware

## Cabecalho e parsing
- [ ] Cabecalho minimo `name,cpf,organ` e aceito
- [ ] Cabecalhos alias (`nome`, `orgao`, `modalidade`) sao mapeados corretamente
- [ ] CSV com delimitador `;` e aceito
- [ ] CSV com delimitador `,` e aceito
- [ ] CSV com cabecalho faltante (ex.: sem `cpf`) e rejeitado

## Validacao por linha
- [ ] Linha com CPF invalido e rejeitada com numero da linha no erro
- [ ] Linha com orgao inexistente e rejeitada com numero da linha no erro
- [ ] Linha com modalidade invalida e rejeitada
- [ ] CPF duplicado no mesmo arquivo e rejeitado
- [ ] CPF ja existente no banco e rejeitado

## Simulacao (sem persistencia)
- [ ] `validate_only=1` retorna sucesso quando arquivo esta valido
- [ ] `validate_only=1` nao cria registros em `people`

## Persistencia com rollback
- [ ] Importacao valida cria todas as pessoas previstas
- [ ] Cada pessoa importada recebe assignment inicial no pipeline (`pipeline.started`)
- [ ] Erro durante importacao cancela lote inteiro (sem persistencia parcial)

## Auditoria e eventos
- [ ] Cada pessoa importada registra `person:create` em `audit_log`
- [ ] Cada pessoa importada registra `person.created` em `system_events`
- [ ] Criacao de assignment inicial registra trilha de pipeline conforme fluxo padrao
