-- Fase 9.4 - Checklist automatico por tipo de caso

CREATE TABLE IF NOT EXISTS assignment_checklist_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_type VARCHAR(40) NOT NULL,
  code VARCHAR(90) NOT NULL,
  label VARCHAR(170) NOT NULL,
  description VARCHAR(255) NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment_checklist_templates_case_code (case_type, code),
  KEY idx_assignment_checklist_templates_active (is_active, case_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_checklist_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NULL,
  case_type VARCHAR(40) NOT NULL,
  item_code VARCHAR(90) NOT NULL,
  item_label VARCHAR(170) NOT NULL,
  item_description VARCHAR(255) NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  done_at DATETIME NULL,
  done_by BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment_checklist_items_assignment_case_code (assignment_id, case_type, item_code),
  KEY idx_assignment_checklist_items_assignment_done (assignment_id, is_done),
  KEY idx_assignment_checklist_items_case_done (case_type, is_done),
  CONSTRAINT fk_assignment_checklist_items_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_checklist_items_template FOREIGN KEY (template_id) REFERENCES assignment_checklist_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_assignment_checklist_items_done_by FOREIGN KEY (done_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO assignment_checklist_templates (case_type, code, label, description, is_required, sort_order, is_active, created_at, updated_at)
VALUES
  ('geral', 'cadastro_validado', 'Cadastro validado', 'Confirmar dados cadastrais e contatos basicos.', 1, 10, 1, NOW(), NOW()),
  ('geral', 'sei_registrado', 'Processo SEI registrado', 'Garantir que o numero de processo SEI esteja preenchido e consistente.', 1, 20, 1, NOW(), NOW()),
  ('geral', 'timeline_inicial', 'Timeline inicial conferida', 'Verificar se os eventos iniciais do pipeline foram registrados.', 1, 30, 1, NOW(), NOW()),
  ('geral', 'dossie_inicial', 'Dossie inicial anexado', 'Confirmar que existe evidencia documental minima no dossie.', 1, 40, 1, NOW(), NOW()),

  ('cessao', 'oficio_orgao', 'Oficio ao orgao emitido', 'Anexar oficio de solicitacao ao orgao de origem.', 1, 110, 1, NOW(), NOW()),
  ('cessao', 'resposta_orgao', 'Resposta do orgao recebida', 'Registrar retorno formal do orgao com custos e condicoes.', 1, 120, 1, NOW(), NOW()),
  ('cessao', 'cdo_emitida', 'CDO emitida', 'Confirmar certificacao orcamentaria para viabilizar a cessao.', 1, 130, 1, NOW(), NOW()),

  ('cft', 'formalizacao_cft', 'Formalizacao da CFT', 'Validar documento de composicao de forca de trabalho.', 1, 210, 1, NOW(), NOW()),
  ('cft', 'custos_homologados', 'Custos homologados', 'Conferir planilha de custos e aprovacao da area responsavel.', 1, 220, 1, NOW(), NOW()),
  ('cft', 'autorizacao_mgi', 'Autorizacao MGI registrada', 'Registrar envio/retorno relacionado ao MGI quando aplicavel.', 0, 230, 1, NOW(), NOW()),

  ('requisicao', 'base_legal', 'Base legal confirmada', 'Conferir fundamento juridico para requisicao administrativa.', 1, 310, 1, NOW(), NOW()),
  ('requisicao', 'despacho_autoridade', 'Despacho da autoridade', 'Registrar despacho/autorizacao da autoridade competente.', 1, 320, 1, NOW(), NOW()),
  ('requisicao', 'publicacao_dou', 'Publicacao DOU registrada', 'Anexar evidencia de publicacao oficial quando exigida.', 0, 330, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  description = VALUES(description),
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = NOW();
