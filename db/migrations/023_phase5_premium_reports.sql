-- Fase 5.3 - Relatorios premium (filtros e exportacoes)

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('report.view', 'Visualizar relatorios premium com exportacao CSV/PDF', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'report.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
