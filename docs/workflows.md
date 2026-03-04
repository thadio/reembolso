# Workflows (Fases 0 e 1.1)

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

## Health check
- `GET /health` verifica:
  - conectividade com banco
  - escrita em `storage/logs`
  - escrita em `storage/uploads`
