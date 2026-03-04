# Checklist de Testes — Fase 1.1 (Órgãos)

## Pré-condições
- [ ] `php db/migrate.php` aplicado com `002_phase1_organs.sql`
- [ ] `php db/seed.php` executado (permite `organs.manage` para admin)

## Listagem
- [ ] Acessar `GET /organs` retorna 200 para usuário com `organs.view`
- [ ] Busca por nome/sigla/CNPJ funciona
- [ ] Ordenação por nome/sigla/CNPJ/cadastro funciona
- [ ] Paginação altera página e respeita `per_page`

## CRUD
- [ ] `GET /organs/create` abre formulário para usuário com `organs.manage`
- [ ] `POST /organs/store` cria órgão válido
- [ ] `GET /organs/show?id={id}` exibe detalhes
- [ ] `GET /organs/edit?id={id}` abre edição
- [ ] `POST /organs/update` atualiza dados
- [ ] `POST /organs/delete` aplica soft delete (`deleted_at` preenchido)

## Validações
- [ ] Nome obrigatório (mínimo 3 caracteres)
- [ ] E-mail inválido é rejeitado
- [ ] CNPJ duplicado é rejeitado
- [ ] UF com tamanho diferente de 2 é rejeitada

## Segurança e auditoria
- [ ] Rotas de escrita exigem CSRF
- [ ] Usuário sem `organs.manage` recebe 403 em criar/editar/excluir
- [ ] Ações create/update/delete geram registros em `audit_log`
- [ ] Eventos `organ.created`, `organ.updated`, `organ.deleted` geram `system_events`

## UX
- [ ] Ação rápida “Ver pessoas vinculadas” aponta para `/people?organ_id={id}`
- [ ] Layout de lista e formulário mantém legibilidade em mobile
