-- Fase 17.2 - Permissoes de gate por modulo (camada guardachuva)

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('module.reembolso.access', 'Acessar modulo de Reembolso no portal guardachuva', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('module.bpmn.access', 'Acessar modulo de Desenho de processos (BPMN) no portal guardachuva', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('module.users.access', 'Acessar modulo de Gestao de usuarios no portal guardachuva', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('module.auxilio_transporte.access', 'Acessar modulo de Gestao de auxilio transporte no portal guardachuva', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('module.indenizacao_fronteirico.access', 'Acessar modulo de Gestao de indenizacao por trabalho fronteirico no portal guardachuva', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('module.recomposicao_erario.access', 'Acessar modulo de Gestao de recomposicao de valores ao erario no portal guardachuva', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name IN (
  'module.reembolso.access',
  'module.bpmn.access',
  'module.users.access',
  'module.auxilio_transporte.access',
  'module.indenizacao_fronteirico.access',
  'module.recomposicao_erario.access'
)
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'module.reembolso.access'
WHERE r.name = 'user'
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
