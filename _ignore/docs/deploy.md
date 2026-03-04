# Guia de Deploy Inicial do Sistema Reembolso

Este documento descreve o passo-a-passo para realizar o primeiro deploy do sistema Reembolso em um servidor de produção: criação do banco, execução das migrações, seed do usuário administrador e primeiro acesso.

## Visão rápida (resumo)
- Verificar requisitos: PHP 8.0+, extensão PDO/MySQL, MySQL/MariaDB.
- Fazer upload do projeto ao servidor e apontar o DocumentRoot para `public/`.
- Criar arquivo `.env` com configurações de BD e app.
- Executar `php db/migrate.php` e `php db/seed.php`.
- Acessar a aplicação e alterar a senha do admin.

## 1. Requisitos
- PHP 8.0 ou superior (o código usa recursos de PHP 8+).
- Extensões PHP: pdo, pdo_mysql, mbstring, openssl, json.
- MySQL ou MariaDB (recomenda-se MySQL 5.7+ / MariaDB equivalente).
- Acesso SSH ao servidor e permissão para criar banco de dados e usuários.

## 2. Estrutura importante do projeto
- O ponto de entrada web é `public/index.php` — configure o DocumentRoot para esta pasta.
- Scripts de migração e seed ficam em `db/migrate.php`, `db/seed.php` e `db/migrations/*.sql`.
- Variáveis de ambiente são carregadas de `/.env` (ver `bootstrap.php` e `app/Core/Env.php`).

## 3. Preparar o servidor e enviar arquivos
1. Faça upload do projeto para uma pasta fora do DocumentRoot quando possível e aponte o servidor para `.../reembolso/public`.
2. Proteja pastas não públicas (`app/`, `db/`, `storage/`) garantindo que não sejam servidas diretamente.

## 4. Criar o arquivo `.env`
Coloque `.env` na raiz do projeto (mesmo nível de `bootstrap.php`). Exemplo mínimo:

```
APP_ENV=production
APP_DEBUG=0
BASE_URL=https://seu-dominio.com
NAME=Reembolso

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=reembolso
DB_USER=reembolso_user
DB_PASS=senha_segura
DB_CHARSET=utf8mb4

SEED_ADMIN_EMAIL=admin@reembolso.local
SEED_ADMIN_NAME="Administrador Sistema"
SEED_ADMIN_PASSWORD=ChangeMe123!
```

Observações:
- `app/Core/Config.php` mostra todas as chaves suportadas pelo sistema. Ajuste `SEED_*` se quiser criar o admin com valores personalizados.

## 5. Permissões
Defina permissões de escrita para o PHP nas pastas de storage/ e uploads:

```bash
cd /caminho/para/reembolso
chmod -R 775 storage/
chmod -R 775 storage/logs/
chmod -R 775 storage/uploads/
chown -R www-data:www-data storage/   # ajuste usuário/grupo conforme seu sistema
```

## 6. Criar banco de dados e usuário (exemplo MySQL)
No servidor MySQL, execute:

```sql
CREATE DATABASE reembolso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'reembolso_user'@'localhost' IDENTIFIED BY 'senha_segura';
GRANT ALL PRIVILEGES ON reembolso.* TO 'reembolso_user'@'localhost';
FLUSH PRIVILEGES;
```

Substitua `reembolso_user` e `senha_segura` conforme sua política.

## 7. Executar migrações
No shell, estando na raiz do projeto, execute:

```bash
php db/migrate.php
```

O que o script faz (detalhes úteis):
- Cria a tabela `migrations` (caso não exista).
- Percorre `db/migrations/*.sql` em ordem e aplica cada arquivo que ainda não foi executado.
- Saída de exemplo:

```
[ok]   001_phase0_foundation.sql
[ok]   002_phase1_organs.sql
Migrations concluídas.
```

Se algum arquivo já foi aplicado, ele será marcado como [skip]. Em caso de erro, o script interrompe e imprime a mensagem.

## 8. Executar seed (criar dados iniciais e usuário admin)
Após as migrations, rode:

```bash
php db/seed.php
```

O `seed.php` realiza:
- Criação/atualização de papéis (roles), permissões e catálogos (document types, event types, modalities).
- Criação do usuário administrador se não existir, tomando os valores de `SEED_ADMIN_*` do `.env` (ou os padrões configurados em `Config::load`).
- Vincula o usuário ao papel `sist_admin`.

Saída de exemplo:

```
Seed concluído com sucesso.
Admin inicial: admin@reembolso.local
Senha inicial (altere após o primeiro login): ChangeMe123!
```

Importante: altere a senha do admin imediatamente após o primeiro login.

## 9. Configuração do servidor web (exemplos)

Apache (VirtualHost mínimo):

```
<VirtualHost *:80>
    ServerName seu-dominio.com
    DocumentRoot /var/www/reembolso/public

    <Directory /var/www/reembolso/public>
        Require all granted
        AllowOverride All
        Options FollowSymLinks
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/reembolso_error.log
    CustomLog ${APACHE_LOG_DIR}/reembolso_access.log combined
</VirtualHost>
```

Habilite mod_rewrite (apenas Apache) e redirecione para HTTPS em produção.

Nginx (server block mínimo):

```
server {
    listen 80;
    server_name seu-dominio.com;
    root /var/www/reembolso/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock; # ajuste conforme sua instalação
    }
}
```

HostGator / cPanel: aponte o domínio para a pasta `public/` do projeto; verifique se é possível usar Composer/PHP 8 e configurar variáveis de ambiente (alguns hosts permitem definir variáveis no painel). Consulte `_ignore/docs/reembolso_ambiente_hostgator_compatibilidade_v1.md` para dicas específicas se necessário.

## 10. Primeiro acesso em produção
1. Acesse `https://seu-dominio.com/`.
2. Vá para `/login` e entre com o email e senha exibidos pelo `db/seed.php` (ou os valores que você definiu em `.env`).
3. Ao logar, altere a senha do administrador e verifique se consegue acessar as rotas protegidas (ex.: `/dashboard`, `/organs`).

## 11. Pós-deploy e recomendações de segurança
- Defina `APP_DEBUG=0` em `.env`.
- Remova arquivos sensíveis acessíveis via web.
- Configure backups automáticos do banco de dados.
- Configure logs rotacionados para `storage/logs/app.log`.
- Mude a senha do admin e crie usuários administrativos separados.

## 12. Troubleshooting rápido
- Erro de conexão com o banco: verifique `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` e se o servidor MySQL aceita conexões locais.
- Permissão de escrita: verifique `storage/` e `storage/logs/` e dono/grupo do processo PHP.
- Mensagens de erro: verifique `storage/logs/app.log` e o log do servidor web.

## 13. Próximos passos úteis
- Automatizar migrações/seed em CI/CD (ex.: job que roda `php db/migrate.php` em deploys controlados).
- Incluir monitoramento (healthchecks) para `GET /health`.

---

Se quiser, eu adapto este guia com comandos e exemplos específicos para seu provedor (HostGator, DigitalOcean, AWS) ou adiciono um trecho de `systemd`/cron para backups e tarefas agendadas.

## Adendo: Compatibilidade com HostGator / cPanel (ambiente compartilhado)

Notas extraídas de `_ignore/docs/reembolso_ambiente_hostgator_compatibilidade_v1.md` e aplicáveis aqui:

- Ambiente típico: Apache + PHP 8.1 (ex.: 8.1.34) em hosting compartilhado (cPanel).
- Banco: Percona/MySQL 5.7 (conexão por socket local). O servidor pode reportar `utf8` como charset — padronize o uso de `utf8mb4` nas migrations e no `CREATE DATABASE`.
- Evite dependências e jobs pesados; prefira rotinas idempotentes e rápidas. Use cron do cPanel para tarefas agendadas.

## Separação de etapas (recomendado)

A seguir estão as etapas segregadas para facilitar o processo e o apoio operacional.

### A. Primeiro deploy / configuração inicial do servidor

1) Preparação do ambiente (recomendações)

- Garanta PHP >= 8.0 (HostGator costuma oferecer 8.1). As extensões necessárias são: pdo, pdo_mysql, mbstring, json.
- Garanta que `public/` será o DocumentRoot do site.
- Planeje armazenar uploads em `storage/uploads/` (fora de `public/`).

2) Subir arquivos

- Faça upload do projeto para o servidor. Preferível: colocar o projeto fora da pasta pública e apontar o domínio/alias para `.../reembolso/public`.

3) Criar o banco (quando tiver acesso ao MySQL)

- Se você tem acesso ao terminal MySQL via SSH/mysql client, crie o DB com:

```sql
CREATE DATABASE reembolso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

- Se não tiver SSH, use o phpMyAdmin do cPanel: crie o banco e importe os arquivos `db/migrations/*.sql` (veja opção 6 abaixo para import manual).

4) Configurar variáveis de ambiente (`.env` ou painel do HostGator)

- Crie um arquivo `.env` na raiz com os valores essenciais (exemplo abaixo). Em muitos shared hosts você também pode definir variáveis no painel; a aplicação lê `/.env` por padrão.

```
APP_ENV=production
APP_DEBUG=0
BASE_URL=https://seu-dominio.com
NAME=Reembolso

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=reembolso
DB_USER=seu_usuario_db
DB_PASS=sua_senha_db
DB_CHARSET=utf8mb4

SEED_ADMIN_EMAIL=admin@reembolso.local
SEED_ADMIN_NAME="Administrador Sistema"
SEED_ADMIN_PASSWORD=ChangeMe123!
```

5) Permissões e proteção de pastas

- Ajuste permissões para que o processo PHP consiga escrever em `storage/`:

```bash
chmod -R 775 storage/
chmod -R 775 storage/logs/
chmod -R 775 storage/uploads/
# se possível, ajuste dono/grupo para o usuário do PHP (ex: www-data ou nobody)
chown -R www-data:www-data storage/    # apenas se tiver permissão
```

- Crie (ou verifique) `.htaccess` na pasta `storage/uploads/` com conteúdo para negar execução e acessos diretos, por exemplo:

```
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule .* - [F]
</IfModule>

<FilesMatch "\.(php|php5|phtml)$">
    Require all denied
</FilesMatch>
```

6) Executar migrações

- Preferível: via SSH execute na raiz do projeto:

```bash
cd /caminho/para/reembolso
php db/migrate.php
```

- Alternativa (sem SSH): importe os SQLs manualmente via phpMyAdmin, na ordem numérica (`001_...`, `002_...`) ou compacte todos em um único arquivo e importe. Atenção: o script `db/migrate.php` registra migrações aplicadas na tabela `migrations`, então importar manualmente exige marcar essas entradas se quiser manter o mecanismo (pode não ser necessário em ambiente simples).

7) Executar seed (criar admin e dados iniciais)

- Via SSH (recomendado):

```bash
php db/seed.php
```

- Sem SSH: crie um job temporário no cron do cPanel que execute `php /home/usuario/public_html/reembolso/db/seed.php` uma vez, ou execute o script via web com atenção (não recomendado por exposição). Depois remova o job.

8) Testes e validação

- Acesse `https://seu-dominio.com/login` e use o e-mail/senha mostrados pelo `seed.php` (ou os definidos em `.env`).
- Verifique logs em `storage/logs/app.log` e erros no log do Apache (cPanel) se algo falhar.

9) Configurar backups e cron

- Configure no cPanel backups periódicos do banco e da pasta `storage/`.
- Adicione crons no cPanel para tarefas de manutenção (rotinas leves), por exemplo limpeza de arquivos temporários.

### B. Próximos deploys (rotina)

1) Pull / upload de novas versões

- Atualize o código (git pull, SFTP ou deploy via painel). Garanta que `public/` permanece DocumentRoot.

2) Rodar migrações incrementais

- Após atualizar o código, execute:

```bash
php db/migrate.php
```

- Em hosting compartilhado sem SSH: crie um job temporário no cron que execute o comando e remova depois.

3) Rodar seed apenas quando necessário

- `db/seed.php` é idempotente (atualiza/insere por ON DUPLICATE KEY / checks), então só rode quando precisar inserir dados default ou atualizar catálogos. Evite rodar em cada deploy sem necessidade.

4) Backups e verificação pós-deploy

- Faça um snapshot do banco antes de rodar migrações críticas.
- Verifique logs e endpoints principais (`/health`, `/login`, `/dashboard`).

5) Boas práticas para deploys em shared hosting

- Evite processos longos e memoria-intensivos. Teste localmente com PHP 8.1 e MySQL 5.7 para garantir compatibilidade.
- Mantenha `APP_DEBUG=0` em produção.
- Tenha um roteiro de rollback (restaurar backup do DB e reverter arquivos).

## Check-list rápido antes de “ir ao ar”

- [ ] `.env` preenchido e seguro
- [ ] `public/` definido como DocumentRoot
- [ ] `storage/` gravável pelo PHP
- [ ] Banco criado em `utf8mb4` e usuário com permissões
- [ ] Migrações aplicadas (`php db/migrate.php` ou import via phpMyAdmin)
- [ ] Seed executado e admin criado
- [ ] SSL configurado (Let's Encrypt via cPanel recomendado)

---

Se quiser, adapto o guia para gerar um pequeno `scripts/deploy.sh` que execute checagens locais, rode `php db/migrate.php` e `php db/seed.php` com validações; ou escrevo instruções passo-a-passo para HostGator/cPanel específicas do seu painel (onde você me diga se possui SSH). Estou pronto para aplicar a opção que preferir.
