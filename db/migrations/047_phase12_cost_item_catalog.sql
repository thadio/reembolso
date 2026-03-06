-- Fase 12.2 - Catalogo de itens de custo e correlacao com custos previstos

CREATE TABLE IF NOT EXISTS cost_item_catalog (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  linkage_code SMALLINT UNSIGNED NOT NULL,
  is_reimbursable TINYINT(1) NOT NULL DEFAULT 1,
  payment_periodicity VARCHAR(30) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_cost_item_catalog_unique (name, linkage_code, is_reimbursable, payment_periodicity),
  KEY idx_cost_item_catalog_name (name),
  KEY idx_cost_item_catalog_linkage (linkage_code),
  KEY idx_cost_item_catalog_reimbursable (is_reimbursable),
  KEY idx_cost_item_catalog_periodicity (payment_periodicity),
  KEY idx_cost_item_catalog_deleted_at (deleted_at),
  KEY idx_cost_item_catalog_created_by (created_by),
  CONSTRAINT fk_cost_item_catalog_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cost_plan_items
  ADD COLUMN cost_item_catalog_id BIGINT UNSIGNED NULL AFTER person_id,
  ADD KEY idx_cost_items_catalog (cost_item_catalog_id),
  ADD CONSTRAINT fk_cost_items_catalog FOREIGN KEY (cost_item_catalog_id) REFERENCES cost_item_catalog(id);

INSERT INTO cost_item_catalog (
  name,
  linkage_code,
  is_reimbursable,
  payment_periodicity,
  created_by,
  created_at,
  updated_at,
  deleted_at
)
SELECT DISTINCT
  TRIM(i.item_name) AS name,
  CASE
    WHEN LOWER(i.item_name) LIKE '%auxilio%' OR LOWER(i.item_name) LIKE '%beneficio%' THEN 510
    ELSE 309
  END AS linkage_code,
  CASE
    WHEN LOWER(i.item_name) LIKE '%auxilio%' OR LOWER(i.item_name) LIKE '%beneficio%' THEN 1
    ELSE 0
  END AS is_reimbursable,
  CASE
    WHEN i.cost_type IN ('mensal', 'anual', 'unico') THEN i.cost_type
    ELSE 'mensal'
  END AS payment_periodicity,
  NULL,
  NOW(),
  NOW(),
  NULL
FROM cost_plan_items i
WHERE i.deleted_at IS NULL
  AND TRIM(i.item_name) <> ''
ON DUPLICATE KEY UPDATE
  updated_at = NOW();

UPDATE cost_plan_items i
INNER JOIN cost_item_catalog c
  ON c.name = TRIM(i.item_name)
 AND c.linkage_code = CASE
    WHEN LOWER(i.item_name) LIKE '%auxilio%' OR LOWER(i.item_name) LIKE '%beneficio%' THEN 510
    ELSE 309
  END
 AND c.is_reimbursable = CASE
    WHEN LOWER(i.item_name) LIKE '%auxilio%' OR LOWER(i.item_name) LIKE '%beneficio%' THEN 1
    ELSE 0
  END
 AND c.payment_periodicity = CASE
    WHEN i.cost_type IN ('mensal', 'anual', 'unico') THEN i.cost_type
    ELSE 'mensal'
  END
 AND c.deleted_at IS NULL
SET i.cost_item_catalog_id = c.id
WHERE i.cost_item_catalog_id IS NULL
  AND i.deleted_at IS NULL;

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('cost_item.view', 'Visualizar catalogo de itens de custo', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO permissions (name, description, created_at, updated_at)
VALUES ('cost_item.manage', 'Gerenciar catalogo de itens de custo', NOW(), NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW();

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'cost_item.view'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.name = 'cost_item.manage'
WHERE r.name IN ('sist_admin', 'admin')
ON DUPLICATE KEY UPDATE created_at = role_permissions.created_at;
