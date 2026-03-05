-- Compliance complementar (apos fase 7): granularidade de acesso documental sensivel (RF-22)

SET @has_documents_sensitivity := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'documents'
    AND COLUMN_NAME = 'sensitivity_level'
);

SET @sql_documents_sensitivity := IF(
  @has_documents_sensitivity = 0,
  'ALTER TABLE documents
      ADD COLUMN sensitivity_level VARCHAR(20) NOT NULL DEFAULT ''public'' AFTER notes,
      ADD KEY idx_documents_sensitivity_level (sensitivity_level)',
  'SELECT 1'
);

PREPARE stmt_documents_sensitivity FROM @sql_documents_sensitivity;
EXECUTE stmt_documents_sensitivity;
DEALLOCATE PREPARE stmt_documents_sensitivity;

UPDATE documents
SET sensitivity_level = 'public'
WHERE sensitivity_level IS NULL
   OR sensitivity_level = ''
   OR sensitivity_level NOT IN ('public', 'restricted', 'sensitive');

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('people.documents.sensitive', 'Visualizar, baixar e classificar documentos com sensibilidade restrita/sensivel', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'people.documents.sensitive'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
