# 15 - Security Hardening (Fase 6.3)

## Escopo
Entrega da fase 6.3 com:
- politica de senha e expiracao configuravel;
- bloqueio de login por tentativas excessivas com janela configuravel;
- hardening adicional de upload.

## Migration
Arquivo: `db/migrations/027_phase6_security_hardening.sql`

Principais mudancas:
- `users`: `password_changed_at`, `password_expires_at` e indice de expiracao.
- `login_attempts`: `lockout_until` e indice de lockout.
- `security_settings`: configuracoes default de senha/login/upload.
- permissoes: `security.view` e `security.manage` com vinculo a `sist_admin` e `admin`.

## Modulo de seguranca
Componentes:
- `SecuritySettingsRepository`
- `SecuritySettingsService`
- `SecurityController`
- `app/Views/security/index.php`

Rotas:
- `GET /security` (`security.view`)
- `POST /security/update` (`security.manage`)

## Politica de senha
Configuracoes disponiveis:
- tamanho minimo e maximo;
- obrigatoriedade de maiuscula/minuscula/numero/simbolo;
- expiracao em dias (`0` desativa expiracao).

Aplicacao:
- validacao em criacao de usuario;
- validacao em troca da propria senha;
- validacao em reset administrativo;
- forca de troca quando senha expirada (redirecionamento para `/users/password`).

## Lockout de login
Configuracoes disponiveis:
- tentativas maximas;
- janela de contagem (segundos);
- duracao do bloqueio (segundos).

Aplicacao:
- `Auth` consulta politica ativa;
- `RateLimiter` aplica lockout por `lockout_until`;
- mensagens de bloqueio exibem tempo restante quando aplicavel.

## Hardening de upload
Aplicado em:
- `DocumentService`
- `PipelineService`
- `InvoiceService` (PDF de boleto e comprovante)
- `ProcessMetadataService` (anexo DOU)

Validacoes aplicadas:
- nome de arquivo seguro;
- validacao nativa de upload HTTP (`is_uploaded_file`);
- validacao de extensao + MIME + assinatura binaria;
- limite global configuravel de upload (com teto por modulo);
- armazenamento via `move_uploaded_file` sem fallback `rename`.

## Checklist operacional
Checklist manual da etapa: `tests/checklist-etapa-6.3.md`.
