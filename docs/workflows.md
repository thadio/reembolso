# Workflows (Fases 0, 1.1, 1.2 e 1.3)

## Fluxo de acesso
1. Usuário acessa `/login`.
2. Informa credenciais.
3. Sistema valida CSRF, rate limit e credenciais.
4. Em sucesso: sessão iniciada e auditoria `login.success`.
5. Em logout: auditoria `logout`.

## Workflow de Órgãos (Etapa 1.1)
1. Operador/Admin acessa `Órgãos`.
2. Busca por nome, sigla ou CNPJ e aplica ordenação/paginação.
3. Cria novo órgão em `/organs/create`.
4. Visualiza detalhe em `/organs/show?id={id}`.
5. Atualiza cadastro em `/organs/edit?id={id}`.
6. Remove logicamente em `/organs/delete` (soft delete).
7. Sistema registra auditoria e evento para cada alteração.

## Workflow de Pessoas (Etapa 1.2)
1. Operador/Admin acessa `Pessoas`.
2. Filtra por status, modalidade, órgão e tags.
3. Cria nova pessoa em `/people/create` com vínculo obrigatório ao órgão.
4. Consulta resumo lateral sem sair da lista.
5. Abre `Perfil 360` em `/people/show?id={id}`.
6. Atualiza cadastro em `/people/edit?id={id}`.
7. Remove logicamente em `/people/delete`.
8. Sistema registra auditoria e evento para cada alteração.

## Workflow de Movimentação e Pipeline (Etapa 1.3)
1. Ao criar pessoa, sistema inicializa assignment com status `interessado`.
2. No Perfil 360, usuário com `people.manage` usa botão de próxima ação.
3. Sistema avança status conforme ordem configurável em `assignment_statuses`.
4. A cada avanço:
   - atualiza status em `assignments`
   - sincroniza status em `people`
   - grava `timeline_events`
   - grava `audit_log`
   - grava `system_events`
5. No status final (`ativo`), registra data efetiva de início.

## Health check
- `GET /health` verifica:
  - conectividade com banco
  - escrita em `storage/logs`
  - escrita em `storage/uploads`
