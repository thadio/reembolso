# REEMBOLSO — Ambiente de Hospedagem (HostGator Shared) e Requisitos de Compatibilidade (v1)

**Origem:** dados extraídos do seu ambiente real (cPanel/HostGator).  
**Objetivo:** documentar o ambiente alvo e definir **requisitos de compatibilidade** para que a aplicação **Reembolso** (PHP) rode com segurança e previsibilidade em servidor compartilhado.

---

## 1) Resumo executivo do ambiente

Este ambiente é um **servidor compartilhado (shared hosting) HostGator** com **cPanel**, executando **Apache + PHP 8.1** e banco **Percona Server (compatível com MySQL 5.7)** em **localhost via UNIX socket**.  
Por se tratar de ambiente compartilhado, há limitações típicas (CPU/memória/I/O, processos longos, restrições de serviços), então o sistema deve priorizar **simplicidade, baixo consumo de recursos, dependências mínimas, rotinas idempotentes (migrations/importações)** e **execução rápida de requests**.

---

## 2) Inventário detalhado do ambiente

### 2.1 Hospedagem / Servidor
| Item | Detalhe |
|---|---|
| Pacote de hospedagem | M_100 |
| Nome do servidor | br1014 |
| IP compartilhado | 162.241.203.145 |
| Arquitetura | x86_64 |
| Sistema operacional | linux |
| Versão do kernel | 4.19.286-203.ELK.el7.x86_64 |

### 2.2 Painel / Web Server
| Item | Detalhe |
|---|---|
| Versão do cPanel | 110.0 (build 89) |
| Servidor web | Apache 2.4.66 |
| cpsrvd | 11.110.0.89 |

### 2.3 PHP / Extensões
| Item | Detalhe |
|---|---|
| Versão do PHP | 8.1.34 |
| Driver/cliente MySQL no PHP | libmysql - mysqlnd 8.1.34 |
| Extensões PHP confirmadas | mysqli, curl, mbstring |

> Observação importante: “mysqlnd 8.1.34” é o **driver cliente do PHP**, não a versão do MySQL do servidor.

### 2.4 Banco de dados
| Item | Detalhe |
|---|---|
| phpMyAdmin | 5.2.1 |
| Tipo de servidor | Percona Server |
| “Versão do MySQL” reportada | 5.7.23-23 |
| Versão do servidor | 5.7.23-23 - Percona Server (GPL), Release 23, Revision 500fcf5 |
| Servidor | Localhost via UNIX socket |
| SSL na conexão | Não está sendo usado |
| Protocolo | 10 |
| Usuário (exemplo do phpMyAdmin) | cpses_th9m2znlko@localhost |
| Charset do servidor | UTF-8 Unicode (utf8) |

### 2.5 E-mail e ferramentas do sistema
| Item | Detalhe |
|---|---|
| Caminho para envio de e-mail | /usr/sbin/sendmail |
| Caminho para Perl | /usr/bin/perl |
| Versão do Perl | 5.16.3 |

---

## 3) Requisitos de compatibilidade da aplicação (obrigatórios)

### 3.1 Compatibilidade de PHP
**Obrigatório**
- Suportar **PHP 8.1.x** (testar em 8.1.34).
- Evitar dependências que exijam extensões não garantidas no shared hosting.
- Usar **PDO** (recomendado) ou `mysqli` (disponível).  
  - Preferência: **PDO** + prepared statements (segurança e portabilidade).

**Recomendado**
- Evitar jobs longos em request web. Se necessário, usar **cron do cPanel**.
- Evitar bibliotecas muito pesadas (PDFs muito complexos) por consumo de RAM.

### 3.2 Compatibilidade de banco (Percona/MySQL 5.7)
**Obrigatório**
- Suportar **MySQL 5.7** (Percona 5.7 compatível).
- Não depender de recursos exclusivos do MySQL 8:
  - certos defaults de collation/charset,
  - algumas funções e melhorias específicas,
  - DDL/CTE/JSON avançado além do suportado em 5.7.
- Queries e índices devem ser eficientes:
  - paginação obrigatória,
  - filtros indexados,
  - evitar full table scans.

**Charset/Collation (ponto crítico)**
- Apesar do servidor reportar `utf8`, **padronizar o sistema em `utf8mb4`** para evitar problemas com caracteres e garantir consistência.
  - Banco/tabelas: `utf8mb4`
  - Collation recomendada (MySQL 5.7): `utf8mb4_unicode_ci` (ou `utf8mb4_general_ci` se necessário por performance/compat).

### 3.3 Compatibilidade de e-mail
**Obrigatório**
- Envio por `sendmail` deve ser suportado.
- Não assumir acesso a serviços externos sem configuração (SMTP).
- Implementar logs de envio e fila simples (opcional).

**Recomendado**
- Ter opção de SMTP do cPanel (maior confiabilidade), mantendo `sendmail` como fallback.

### 3.4 Compatibilidade de arquivos e uploads
**Obrigatório**
- Armazenar uploads **fora de `public/`** (ex.: `storage/uploads/`).
- Bloquear execução de arquivos na pasta de uploads (via `.htaccess`).
- Validar:
  - MIME type,
  - extensão permitida,
  - tamanho máximo,
  - nomes internos com hash + metadados no banco.
- Evitar processamento pesado de PDF no servidor (preferir armazenar e gerar “HTML print-friendly” quando possível).

### 3.5 Compatibilidade operacional (shared hosting)
**Obrigatório**
- Aplicação deve funcionar sem:
  - queue server,
  - redis,
  - workers permanentes,
  - serviços com daemon.
- Processos críticos:
  - migrations devem ser idempotentes,
  - rotinas de importação devem permitir retomada (checkpoint / reprocessamento seguro).
- Logs:
  - registrar em arquivo (`storage/logs`) e opcionalmente no banco (audit_log).

**Recomendado**
- Agendar tarefas com **cron do cPanel** (ex.: snapshots de projeções e KPIs, limpeza de temporários).
- Implementar “Health Check” (DB + permissões de storage + configuração).

---

## 4) Implicações diretas no design do Reembolso

### 4.1 PDF e relatórios
- Para evitar consumo excessivo:
  - **preferir HTML “print-friendly”** com CSS e opção de imprimir/salvar PDF no navegador.
  - se for usar biblioteca de PDF: escolher uma opção leve e testar com cargas reais.

### 4.2 Performance (consultas e dashboards)
- Dashboards devem usar:
  - agregações por período,
  - índices adequados,
  - snapshots/cache simples em tabela, recalculado por cron.

### 4.3 Segurança mínima obrigatória
- CSRF em formulários.
- Prepared statements (PDO/mysqli).
- Sessões seguras.
- RBAC (perfis e permissões).
- Mascaramento de CPF em listagens, com permissão específica para ver CPF completo.

---

## 5) Checklist de compatibilidade (para o agente de IA validar)

1. Rodar em PHP **8.1.34** sem warnings fatais.
2. Conectar em Percona/MySQL **5.7.23-23** via socket/localhost.
3. Criar banco e tabelas em **utf8mb4** com collation compatível.
4. Subir e baixar anexos com segurança (fora de `public/` + `.htaccess`).
5. Gerar export CSV por streaming (sem estourar memória).
6. Renderizar relatórios em HTML print-friendly (PDF opcional).
7. Registrar auditoria em `audit_log` (mudanças e uploads).
8. Implementar paginação e índices para evitar lentidão.
9. Executar tarefas de manutenção por cron do cPanel (quando necessário).

---

**Fim do documento.**
