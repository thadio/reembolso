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
