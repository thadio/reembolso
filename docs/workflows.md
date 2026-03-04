# Workflows (Fase 0)

## Fluxo de acesso
1. Usuário acessa `/login`.
2. Informa credenciais.
3. Sistema valida CSRF, rate limit e credenciais.
4. Em sucesso: sessão iniciada e auditoria `login.success`.
5. Em logout: auditoria `logout`.

## Navegação MVP
- Dashboard
- Pessoas (lista vazia)
- Órgãos (lista vazia)

## Health check
- `GET /health` verifica:
  - conectividade com banco
  - escrita em `storage/logs`
  - escrita em `storage/uploads`
