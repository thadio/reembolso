-- Fase 13 - Central de importacoes em lote com RBAC especifico

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('bulk_import.view', 'Acessar central de importacoes em lote', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('people.import_bulk', 'Executar importacao em lote de pessoas via CSV', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('organs.import_bulk', 'Executar importacao em lote de orgaos via CSV', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('cost_mirror.import_bulk', 'Executar importacao em lote de itens de espelho via CSV', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name IN (
  'bulk_import.view',
  'people.import_bulk',
  'organs.import_bulk',
  'cost_mirror.import_bulk'
)
WHERE r.name = 'sist_admin'
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

DELETE rp
FROM role_permissions rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE p.name IN (
  'bulk_import.view',
  'people.import_bulk',
  'organs.import_bulk',
  'cost_mirror.import_bulk'
)
  AND r.name <> 'sist_admin';
