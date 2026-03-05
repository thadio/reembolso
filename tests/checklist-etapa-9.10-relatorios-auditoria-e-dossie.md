# Checklist de Testes - Ciclo 9.10 (Relatorios de auditoria + dossie completo)

## Pre-condicoes
- [ ] `php db/migrate.php` aplicado (incluindo `036_phase6_audit_reports_indexes.sql`)
- [ ] `php db/seed.php` aplicado
- [ ] Usuario com permissao `report.view`
- [ ] Usuario com permissao `people.view`
- [ ] Usuario com permissao `audit.view` para validar trilha completa no dossie

## Relatorios prontos para auditoria (CGU/TCU)
- [ ] Em `GET /reports`, botao `Pacote Auditoria CGU/TCU` aparece na barra de exportacao
- [ ] `GET /reports/export/audit-zip` gera ZIP valido
- [ ] ZIP contem arquivos:
  - [ ] `auditoria/trilha_critica_auditoria.csv`
  - [ ] `auditoria/acessos_sensiveis.csv`
  - [ ] `auditoria/pendencias_abertas.csv`
  - [ ] `auditoria/divergencias_sem_justificativa.csv`
  - [ ] `manifesto_auditoria.txt`
- [ ] Manifesto registra periodo, orgao, filtros e contagens

## Exportacao completa de dossie (ZIP/PDF + trilha)
- [ ] Em `GET /people/show?id={id}`, botao `Exportar dossie ZIP/PDF` aparece no cabecalho do Perfil 360
- [ ] `GET /people/dossier/export?person_id={id}` gera ZIP valido
- [ ] ZIP contem arquivos base do dossie:
  - [ ] `dossie/pessoa.csv`
  - [ ] `dossie/timeline_operacional.csv`
  - [ ] `dossie/documentos.csv`
  - [ ] `dossie/comentarios_internos.csv`
  - [ ] `dossie/timeline_administrativa.csv`
  - [ ] `dossie/financeiro_reembolso.csv`
  - [ ] `dossie/resumo_dossie.pdf`
  - [ ] `manifesto_dossie.txt`
- [ ] Para usuario com `audit.view`, ZIP inclui `trilha/auditoria.csv`
- [ ] Para usuario sem `audit.view`, ZIP inclui `trilha/auditoria.txt` (sem dados detalhados)
- [ ] Quando existirem anexos fisicos, ZIP inclui conteudo em `anexos/documentos/*` e `anexos/timeline/*`

## Seguranca e regressao
- [ ] Usuario sem `report.view` nao acessa `GET /reports/export/audit-zip` (403)
- [ ] Usuario sem `people.view` nao acessa `GET /people/dossier/export` (403)
- [ ] Exportacao de dossie respeita classificacao documental (somente inclui sensiveis para quem possui `people.documents.sensitive`)
- [ ] Exportacoes existentes (`/reports/export/csv`, `/reports/export/pdf`, `/reports/export/zip`, `/people/audit/export`) continuam funcionais
