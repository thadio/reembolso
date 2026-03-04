# Checklist de Testes — Fase 1.4 (Timeline completa)

## Pré-condições
- [ ] `php db/migrate.php` aplicado com `005_phase1_timeline_attachments.sql`
- [ ] `php db/seed.php` executado
- [ ] Existe pessoa com assignment inicial criado (Etapa 1.3)

## Evento manual + anexos
- [ ] `POST /people/timeline/store` cria evento manual válido
- [ ] Upload de anexo válido (PDF/JPG/PNG) é persistido em `timeline_event_attachments`
- [ ] Arquivo inválido (MIME/extensão/tamanho) gera aviso sem quebrar o cadastro do evento
- [ ] Perfil 360 exibe anexos no item da timeline

## Retificação (edição controlada)
- [ ] `POST /people/timeline/rectify` cria novo evento `retificacao`
- [ ] Evento original permanece preservado (não é excluído/alterado)
- [ ] Metadata da retificação referencia o evento original (`rectifies_event_id`)

## Download e impressão
- [ ] `GET /people/timeline/attachment` permite download quando `person_id` confere
- [ ] Download inválido (id inexistente ou pessoa divergente) retorna erro e redireciona
- [ ] `GET /people/timeline/print?id={personId}` renderiza timeline completa em layout print-friendly

## Paginação e performance básica
- [ ] Perfil 360 carrega timeline paginada
- [ ] Navegação `timeline_page` funciona (anterior/próxima)
- [ ] Ordenação permanece cronológica decrescente por `event_date` e `id`

## Auditoria e rastreabilidade
- [ ] Evento manual registra `audit_log` (`entity=timeline_event`, `action=create`)
- [ ] Retificação registra `audit_log` (`action=rectify`)
- [ ] `system_events` registra `timeline.manual_event` e `timeline.rectified`

## Segurança
- [ ] Usuário sem `people.manage` recebe 403 em `/people/timeline/store` e `/people/timeline/rectify`
- [ ] Endpoints POST exigem CSRF válido
- [ ] Upload não é executável (armazenado fora de `public/` e protegido por `.htaccess`)
