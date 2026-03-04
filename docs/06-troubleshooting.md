# 06 - Troubleshooting

## 1) `/health` retorna 503
Causas comuns:
- banco indisponivel
- sem permissao em `storage/logs`
- sem permissao em `storage/uploads`

Diagnostico:
```bash
./scripts/healthcheck.sh
ls -ld storage storage/logs storage/uploads
```

## 2) Erro de conexao com banco
Checklist:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- usuario com permissao no schema correto

## 3) Erro de permissao em storage
Correcao:
```bash
mkdir -p storage/logs storage/uploads
chmod 775 storage storage/logs storage/uploads
```

## 4) `git pull` falha no deploy
- Verifique alteracoes locais pendentes
- Verifique branch atual
- Verifique permissao de leitura no remoto Git

## 5) Deploy sobe, mas rota principal falha
- Confirmar DocumentRoot para `public/`
- Se DocumentRoot estiver na raiz do projeto, garantir `.htaccess` com roteamento para `public/`

## 6) Upload FTP falha no VS Code
Checklist:
- Validar `FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASS`
- Validar `FTP_REMOTE_ROOT` com caminho remoto correto
- Executar primeiro em modo simulacao:
```bash
./scripts/ftp-upload.sh --dry-run
```
- Se houver erro de certificado FTPS, ajustar:
  - `FTP_SSL_ALLOW`
  - `FTP_SSL_FORCE`
  - `FTP_SSL_VERIFY`
