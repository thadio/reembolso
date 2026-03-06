-- Fase 12.1 - CRUD de tipos de documento + tipos esperados por etapa BPMN

CREATE TABLE IF NOT EXISTS assignment_flow_step_document_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flow_step_id BIGINT UNSIGNED NOT NULL,
  document_type_id INT UNSIGNED NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment_flow_step_document_types_step_doc (flow_step_id, document_type_id),
  KEY idx_assignment_flow_step_document_types_step (flow_step_id),
  KEY idx_assignment_flow_step_document_types_document_type (document_type_id),
  KEY idx_assignment_flow_step_document_types_required (is_required),
  CONSTRAINT fk_assignment_flow_step_document_types_step
    FOREIGN KEY (flow_step_id) REFERENCES assignment_flow_steps(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_flow_step_document_types_document_type
    FOREIGN KEY (document_type_id) REFERENCES document_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_types (name, description, is_active, created_at, updated_at)
VALUES
  ('Curriculo', 'Curriculo profissional atualizado.', 1, NOW(), NOW()),
  ('Oficio ao orgao', 'Oficio enviado ao orgao de origem/destino.', 1, NOW(), NOW()),
  ('Resposta do orgao', 'Resposta formal do orgao ao oficio.', 1, NOW(), NOW()),
  ('Boleto', 'Titulo de cobranca para reembolso.', 1, NOW(), NOW()),
  ('CDO', 'Comprovacao de disponibilidade orcamentaria.', 1, NOW(), NOW()),
  ('Publicacao DOU', 'Publicacao oficial no Diario Oficial da Uniao.', 1, NOW(), NOW()),
  ('Espelho de custo', 'Espelho de custo detalhado por competencia.', 1, NOW(), NOW()),
  ('Comprovante de pagamento', 'Comprovante de baixa/pagamento efetivado.', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('document_type.view', 'Visualizar tipos de documento', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('document_type.manage', 'Gerenciar tipos de documento', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'document_type.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'document_type.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
