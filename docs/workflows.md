# Workflows (Fases 0, 1.1 e 1.2)

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

## Health check
- `GET /health` verifica:
  - conectividade com banco
  - escrita em `storage/logs`
  - escrita em `storage/uploads`
