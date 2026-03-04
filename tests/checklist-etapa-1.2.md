# Checklist de Testes — Fase 1.2 (Pessoas)

## Pré-condições
- [ ] `php db/migrate.php` aplicado com `003_phase1_people.sql`
- [ ] `php db/seed.php` executado (permissões `people.manage` e `people.cpf.full`)
- [ ] Existe ao menos 1 órgão ativo para vínculo da pessoa

## Listagem e filtros
- [ ] `GET /people` retorna 200 para usuário com `people.view`
- [ ] Busca por nome/CPF/órgão/SEI funciona
- [ ] Filtro por status funciona
- [ ] Filtro por modalidade funciona
- [ ] Filtro por órgão funciona
- [ ] Filtro por tag funciona
- [ ] Ordenação por nome/status/órgão funciona
- [ ] Paginação respeita `per_page`

## UX (lista)
- [ ] Painel lateral de resumo atualiza sem sair da lista (via botão `Resumo`)
- [ ] Ação `Perfil 360` abre detalhe da pessoa
- [ ] CPF aparece mascarado em listagem sem permissão sensível

## CRUD
- [ ] `GET /people/create` abre formulário para usuário com `people.manage`
- [ ] `POST /people/store` cria pessoa válida vinculada a órgão
- [ ] `GET /people/show?id={id}` exibe Perfil 360
- [ ] `GET /people/edit?id={id}` abre edição
- [ ] `POST /people/update` atualiza dados
- [ ] `POST /people/delete` aplica soft delete (`deleted_at` preenchido)

## Validações
- [ ] Órgão obrigatório
- [ ] Nome obrigatório (mínimo 3 caracteres)
- [ ] CPF obrigatório e válido (11 dígitos)
- [ ] CPF duplicado é rejeitado
- [ ] E-mail inválido é rejeitado
- [ ] Modalidade inexistente é rejeitada

## Segurança e auditoria
- [ ] Rotas de escrita exigem CSRF
- [ ] Usuário sem `people.manage` recebe 403 em criar/editar/excluir
- [ ] Usuário sem `people.cpf.full` não vê CPF completo na lista
- [ ] Ações create/update/delete geram `audit_log` para `entity=person`
- [ ] Eventos `person.created`, `person.updated`, `person.deleted` geram `system_events`

## Perfil 360
- [ ] Aba Resumo exibe dados principais
- [ ] Abas Timeline/Documentos/Custos/Auditoria aparecem como placeholders da fase seguinte
