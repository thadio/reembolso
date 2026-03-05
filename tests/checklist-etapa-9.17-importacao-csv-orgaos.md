# Checklist de Testes - Ciclo 9.17 (Importacao CSV de orgaos)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `organs.manage`
- [ ] Base com ao menos 1 orgao existente para teste de duplicidade de CNPJ

## Upload e validacao de arquivo
- [ ] `POST /organs/import-csv` rejeita requisicao sem arquivo
- [ ] Importacao rejeita extensao invalida (nao `.csv`/`.txt`)
- [ ] Importacao rejeita arquivo acima de 5MB
- [ ] CSV com cabecalho minimo (`name`) e aceito em validacao

## Regras de negocio
- [ ] `validate_only=1` executa simulacao sem gravar registros
- [ ] Importacao real grava orgaos validos e retorna contagem de criados
- [ ] CNPJ duplicado no CSV e bloqueia lote com mensagem de linha
- [ ] CNPJ ja existente na base e bloqueia lote com mensagem de linha
- [ ] Erro em qualquer linha invalida lote completo (rollback transacional)

## Variantes de cabecalho e delimitador
- [ ] Cabecalhos aliases funcionam (`nome`, `sigla`, `uf`, `cep`, `observacoes`)
- [ ] CSV separado por `;` funciona
- [ ] CSV separado por `,` funciona

## Governanca e regressao
- [ ] `audit_log` registra `organ:create` para cada orgao importado em execucao real
- [ ] `system_events` registra `organ.created` para cada orgao importado em execucao real
- [ ] CRUD manual de orgaos continua funcionando (create/edit/delete)
