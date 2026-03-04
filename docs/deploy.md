# Deploy

Guia operacional de deploy para o projeto Reembolso, alinhado com o ambiente alvo (HostGator/cPanel, Apache 2.4, PHP 8.1, Percona/MySQL 5.7) e com o `.env` como fonte de verdade.

## 1) Pre-requisitos
- Acesso SSH ao servidor de producao.
- PHP 8.1+ disponivel no servidor remoto.
- Banco MySQL/Percona criado e acessivel com as credenciais de `DB_*`.
- DocumentRoot apontando para `public/`.
- Permissao de escrita em `storage/logs` e `storage/uploads`.

## 2) Variaveis de ambiente
Arquivo de referencia: `.env.example`.

### Obrigatorias para runtime da aplicacao
- `NAME`
- `BASE_URL`
- `TIMEZONE`
- `APP_ENV`
- `APP_DEBUG`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `SEED_ADMIN_NAME`
- `SEED_ADMIN_EMAIL`
- `SEED_ADMIN_PASSWORD`

### Obrigatorias para deploy remoto via script
- `DEPLOY_SSH_HOST`
- `DEPLOY_SSH_PORT`
- `DEPLOY_SSH_USER`
- `DEPLOY_SSH_REMOTE_ROOT`

### Opcionais para deploy remoto
- `DEPLOY_SSH_PASS` (usado somente se `sshpass` estiver instalado)
- `DEPLOY_IGNORE_FILE` (default: `.ftpignore`)
- `DEPLOY_RSYNC_DELETE` (`0` ou `1`)

## 3) Script oficial
Arquivo: `scripts/deploy.sh`

### O que o script faz
- Executa preflight de dependencias e arquivos essenciais.
- Sincroniza codigo com `rsync` via SSH.
- Executa `db/migrate.php` no remoto (ou local, por compatibilidade).
- Executa `db/seed.php` opcionalmente.
- Valida `GET /health` no endpoint configurado em `BASE_URL`.

### Seguranca no script
- Nao imprime segredos do `.env`.
- Nao exibe credenciais de seed em logs.
- Usa `StrictHostKeyChecking=accept-new` para evitar prompt interativo no primeiro acesso.

## 4) Fluxos recomendados

### 4.1 Primeiro deploy em producao
```bash
./scripts/deploy.sh --preflight
./scripts/deploy.sh --dry-run --remote-apply --with-seed --remote-health
./scripts/deploy.sh --remote-apply --with-seed --remote-health
```

### 4.2 Deploy recorrente (sem seed)
```bash
./scripts/deploy.sh --preflight
./scripts/deploy.sh --remote-apply --remote-health
```

### 4.3 Deploy com limpeza remota (`--delete`)
Habilite com uma das opcoes:
- `DEPLOY_RSYNC_DELETE=1` no `.env`, ou
- flag `--force-delete` no comando.

Exemplo:
```bash
./scripts/deploy.sh --remote-apply --remote-health --force-delete
```

## 5) Pos-deploy (checklist)
1. `GET /health` retorna `200` e `status: ok`.
2. Login funcional em `/login`.
3. Listagens de Pessoas e Orgaos respondem sem erro.
4. Arquivo de log `storage/logs/app.log` recebe escrita.
5. Upload para `storage/uploads` funciona.

## 6) Rollback pragmatico
Nao existe rollback automatico no script atual.

Procedimento minimo:
1. Restaurar backup de arquivos da aplicacao.
2. Restaurar backup de banco de dados (quando necessario).
3. Revalidar `GET /health` e login.

## 7) Troubleshooting

### Erro de autenticacao SSH
- Confirme `DEPLOY_SSH_HOST`, `DEPLOY_SSH_PORT`, `DEPLOY_SSH_USER`.
- Se usar senha, confirme `DEPLOY_SSH_PASS` e instalacao de `sshpass`.
- Se usar chave, valide agente SSH e chave autorizada no host.

### Erro de conexao com banco apos deploy
- Revisar `DB_*` no `.env` remoto.
- Validar permissao de usuario no banco (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`).

### Health check degradado
- Verificar conectividade DB.
- Verificar escrita em `storage/logs` e `storage/uploads`.
- Verificar logs em `storage/logs/app.log`.
