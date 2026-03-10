-- Fase 14.1 - Hierarquia de custos com ate 10 categorias agregadoras e suporte no efetivo

SET @has_parent_cost_item_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_item_catalog'
    AND COLUMN_NAME = 'parent_cost_item_id'
);

SET @sql_add_parent_cost_item_id := IF(
  @has_parent_cost_item_id = 0,
  'ALTER TABLE cost_item_catalog ADD COLUMN parent_cost_item_id BIGINT UNSIGNED NULL AFTER id',
  'SELECT 1'
);

PREPARE stmt_add_parent_cost_item_id FROM @sql_add_parent_cost_item_id;
EXECUTE stmt_add_parent_cost_item_id;
DEALLOCATE PREPARE stmt_add_parent_cost_item_id;

SET @has_is_aggregator := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_item_catalog'
    AND COLUMN_NAME = 'is_aggregator'
);

SET @sql_add_is_aggregator := IF(
  @has_is_aggregator = 0,
  'ALTER TABLE cost_item_catalog ADD COLUMN is_aggregator TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_cost_item_id',
  'SELECT 1'
);

PREPARE stmt_add_is_aggregator FROM @sql_add_is_aggregator;
EXECUTE stmt_add_is_aggregator;
DEALLOCATE PREPARE stmt_add_is_aggregator;

SET @has_hierarchy_sort := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_item_catalog'
    AND COLUMN_NAME = 'hierarchy_sort'
);

SET @sql_add_hierarchy_sort := IF(
  @has_hierarchy_sort = 0,
  'ALTER TABLE cost_item_catalog ADD COLUMN hierarchy_sort SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_aggregator',
  'SELECT 1'
);

PREPARE stmt_add_hierarchy_sort FROM @sql_add_hierarchy_sort;
EXECUTE stmt_add_hierarchy_sort;
DEALLOCATE PREPARE stmt_add_hierarchy_sort;

SET @has_idx_parent := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_item_catalog'
    AND INDEX_NAME = 'idx_cost_item_catalog_parent'
);

SET @sql_add_idx_parent := IF(
  @has_idx_parent = 0,
  'ALTER TABLE cost_item_catalog ADD KEY idx_cost_item_catalog_parent (parent_cost_item_id)',
  'SELECT 1'
);

PREPARE stmt_add_idx_parent FROM @sql_add_idx_parent;
EXECUTE stmt_add_idx_parent;
DEALLOCATE PREPARE stmt_add_idx_parent;

SET @has_idx_aggregator := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_item_catalog'
    AND INDEX_NAME = 'idx_cost_item_catalog_is_aggregator'
);

SET @sql_add_idx_aggregator := IF(
  @has_idx_aggregator = 0,
  'ALTER TABLE cost_item_catalog ADD KEY idx_cost_item_catalog_is_aggregator (is_aggregator)',
  'SELECT 1'
);

PREPARE stmt_add_idx_aggregator FROM @sql_add_idx_aggregator;
EXECUTE stmt_add_idx_aggregator;
DEALLOCATE PREPARE stmt_add_idx_aggregator;

SET @has_idx_hierarchy_sort := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_item_catalog'
    AND INDEX_NAME = 'idx_cost_item_catalog_hierarchy_sort'
);

SET @sql_add_idx_hierarchy_sort := IF(
  @has_idx_hierarchy_sort = 0,
  'ALTER TABLE cost_item_catalog ADD KEY idx_cost_item_catalog_hierarchy_sort (hierarchy_sort)',
  'SELECT 1'
);

PREPARE stmt_add_idx_hierarchy_sort FROM @sql_add_idx_hierarchy_sort;
EXECUTE stmt_add_idx_hierarchy_sort;
DEALLOCATE PREPARE stmt_add_idx_hierarchy_sort;

SET @has_fk_parent := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_cost_item_catalog_parent'
);

SET @sql_add_fk_parent := IF(
  @has_fk_parent = 0,
  'ALTER TABLE cost_item_catalog ADD CONSTRAINT fk_cost_item_catalog_parent FOREIGN KEY (parent_cost_item_id) REFERENCES cost_item_catalog(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_add_fk_parent FROM @sql_add_fk_parent;
EXECUTE stmt_add_fk_parent;
DEALLOCATE PREPARE stmt_add_fk_parent;

INSERT INTO cost_item_catalog (
  cost_code,
  parent_cost_item_id,
  is_aggregator,
  hierarchy_sort,
  name,
  type_description,
  macro_category,
  subcategory,
  expense_nature,
  calculation_base,
  charge_incidence,
  reimbursability,
  predictability,
  linkage_code,
  is_reimbursable,
  payment_periodicity,
  created_by,
  created_at,
  updated_at,
  deleted_at
)
VALUES
  (1001, NULL, 1, 10, 'Remuneracao Base', 'Categoria agregadora para remuneracao base', 'remuneracao_direta', 'Remuneracao Base', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (1002, NULL, 1, 20, 'Adicionais', 'Categoria agregadora para adicionais remuneratorios', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (1003, NULL, 1, 30, 'Gratificacoes', 'Categoria agregadora para gratificacoes', 'remuneracao_direta', 'Gratificacoes', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (1004, NULL, 1, 40, 'Complementos', 'Categoria agregadora para complementos de remuneracao', 'remuneracao_direta', 'Complementos', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (1005, NULL, 1, 50, 'Beneficios', 'Categoria agregadora para beneficios e auxilios', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (1006, NULL, 1, 60, 'Encargos Sociais e Trabalhistas', 'Categoria agregadora para encargos sociais e trabalhistas', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (1007, NULL, 1, 70, 'Provisoes Trabalhistas', 'Categoria agregadora para provisoes trabalhistas', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'anual', NULL, NOW(), NOW(), NULL),
  (1008, NULL, 1, 80, 'Remuneracoes Variaveis', 'Categoria agregadora para remuneracoes variaveis', 'remuneracao_direta', 'Remuneracoes Variaveis', 'remuneratoria', 'valor_fixo', 1, 'nao_reembolsavel', 'variavel', 309, 0, 'anual', NULL, NOW(), NOW(), NULL),
  (1009, NULL, 1, 90, 'Custos de Pessoal Indiretos', 'Categoria agregadora para custos de pessoal indiretos', 'beneficios_provisoes_indiretos', 'Custos de Pessoal Indiretos', 'indenizatoria', 'valor_fixo', 0, 'nao_reembolsavel', 'eventual', 510, 0, 'eventual', NULL, NOW(), NOW(), NULL),
  (1010, NULL, 1, 100, 'Cessao ou Cooperacao', 'Categoria agregadora para cessao ou cooperacao', 'beneficios_provisoes_indiretos', 'Cessao ou Cooperacao', 'indenizatoria', 'total', 0, 'reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
  parent_cost_item_id = VALUES(parent_cost_item_id),
  is_aggregator = VALUES(is_aggregator),
  hierarchy_sort = VALUES(hierarchy_sort),
  name = VALUES(name),
  type_description = VALUES(type_description),
  macro_category = VALUES(macro_category),
  subcategory = VALUES(subcategory),
  expense_nature = VALUES(expense_nature),
  calculation_base = VALUES(calculation_base),
  charge_incidence = VALUES(charge_incidence),
  reimbursability = VALUES(reimbursability),
  predictability = VALUES(predictability),
  linkage_code = VALUES(linkage_code),
  is_reimbursable = VALUES(is_reimbursable),
  payment_periodicity = VALUES(payment_periodicity),
  deleted_at = NULL,
  updated_at = NOW();

UPDATE cost_item_catalog
SET is_aggregator = 0,
    parent_cost_item_id = NULL,
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND is_aggregator = 1
  AND cost_code NOT IN (1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010);

UPDATE cost_item_catalog c
INNER JOIN cost_item_catalog p
  ON p.deleted_at IS NULL
 AND p.is_aggregator = 1
 AND LOWER(TRIM(p.name)) = LOWER(TRIM(c.subcategory))
SET c.parent_cost_item_id = p.id,
    c.is_aggregator = 0,
    c.hierarchy_sort = CASE
      WHEN IFNULL(c.hierarchy_sort, 0) > 0 THEN c.hierarchy_sort
      WHEN IFNULL(c.cost_code, 0) > 0 THEN c.cost_code
      ELSE LEAST(c.id, 65535)
    END,
    c.updated_at = NOW()
WHERE c.deleted_at IS NULL
  AND c.id <> p.id
  AND c.is_aggregator = 0;

UPDATE cost_item_catalog c
INNER JOIN cost_item_catalog p
  ON p.deleted_at IS NULL
 AND p.is_aggregator = 1
 AND p.name = 'Encargos Sociais e Trabalhistas'
SET c.parent_cost_item_id = p.id,
    c.is_aggregator = 0,
    c.hierarchy_sort = CASE WHEN IFNULL(c.hierarchy_sort, 0) > 0 THEN c.hierarchy_sort ELSE IFNULL(NULLIF(c.cost_code, 0), LEAST(c.id, 65535)) END,
    c.updated_at = NOW()
WHERE c.deleted_at IS NULL
  AND c.is_aggregator = 0
  AND c.parent_cost_item_id IS NULL
  AND c.macro_category = 'encargos_obrigacoes_legais';

UPDATE cost_item_catalog c
INNER JOIN cost_item_catalog p
  ON p.deleted_at IS NULL
 AND p.is_aggregator = 1
 AND p.name = 'Provisoes Trabalhistas'
SET c.parent_cost_item_id = p.id,
    c.is_aggregator = 0,
    c.hierarchy_sort = CASE WHEN IFNULL(c.hierarchy_sort, 0) > 0 THEN c.hierarchy_sort ELSE IFNULL(NULLIF(c.cost_code, 0), LEAST(c.id, 65535)) END,
    c.updated_at = NOW()
WHERE c.deleted_at IS NULL
  AND c.is_aggregator = 0
  AND c.parent_cost_item_id IS NULL
  AND c.expense_nature = 'provisoes';

UPDATE cost_item_catalog c
INNER JOIN cost_item_catalog p
  ON p.deleted_at IS NULL
 AND p.is_aggregator = 1
 AND p.name = 'Remuneracao Base'
SET c.parent_cost_item_id = p.id,
    c.is_aggregator = 0,
    c.hierarchy_sort = CASE WHEN IFNULL(c.hierarchy_sort, 0) > 0 THEN c.hierarchy_sort ELSE IFNULL(NULLIF(c.cost_code, 0), LEAST(c.id, 65535)) END,
    c.updated_at = NOW()
WHERE c.deleted_at IS NULL
  AND c.is_aggregator = 0
  AND c.parent_cost_item_id IS NULL
  AND c.macro_category = 'remuneracao_direta';

UPDATE cost_item_catalog c
INNER JOIN cost_item_catalog p
  ON p.deleted_at IS NULL
 AND p.is_aggregator = 1
 AND p.name = 'Beneficios'
SET c.parent_cost_item_id = p.id,
    c.is_aggregator = 0,
    c.hierarchy_sort = CASE WHEN IFNULL(c.hierarchy_sort, 0) > 0 THEN c.hierarchy_sort ELSE IFNULL(NULLIF(c.cost_code, 0), LEAST(c.id, 65535)) END,
    c.updated_at = NOW()
WHERE c.deleted_at IS NULL
  AND c.is_aggregator = 0
  AND c.parent_cost_item_id IS NULL;

UPDATE cost_item_catalog
SET parent_cost_item_id = NULL,
    is_aggregator = 1,
    hierarchy_sort = CASE cost_code
      WHEN 1001 THEN 10
      WHEN 1002 THEN 20
      WHEN 1003 THEN 30
      WHEN 1004 THEN 40
      WHEN 1005 THEN 50
      WHEN 1006 THEN 60
      WHEN 1007 THEN 70
      WHEN 1008 THEN 80
      WHEN 1009 THEN 90
      WHEN 1010 THEN 100
      ELSE hierarchy_sort
    END,
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND cost_code IN (1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010);

UPDATE cost_item_catalog
SET hierarchy_sort = IFNULL(NULLIF(cost_code, 0), LEAST(id, 65535)),
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND is_aggregator = 0
  AND IFNULL(hierarchy_sort, 0) <= 0;

SET @has_reimbursement_cost_item_catalog_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND COLUMN_NAME = 'cost_item_catalog_id'
);

SET @sql_add_reimbursement_cost_item_catalog_id := IF(
  @has_reimbursement_cost_item_catalog_id = 0,
  'ALTER TABLE reimbursement_entries ADD COLUMN cost_item_catalog_id BIGINT UNSIGNED NULL AFTER assignment_id',
  'SELECT 1'
);

PREPARE stmt_add_reimbursement_cost_item_catalog_id FROM @sql_add_reimbursement_cost_item_catalog_id;
EXECUTE stmt_add_reimbursement_cost_item_catalog_id;
DEALLOCATE PREPARE stmt_add_reimbursement_cost_item_catalog_id;

SET @has_idx_reimbursement_cost_item_catalog := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reimbursement_entries'
    AND INDEX_NAME = 'idx_reimbursement_cost_item_catalog'
);

SET @sql_add_idx_reimbursement_cost_item_catalog := IF(
  @has_idx_reimbursement_cost_item_catalog = 0,
  'ALTER TABLE reimbursement_entries ADD KEY idx_reimbursement_cost_item_catalog (cost_item_catalog_id)',
  'SELECT 1'
);

PREPARE stmt_add_idx_reimbursement_cost_item_catalog FROM @sql_add_idx_reimbursement_cost_item_catalog;
EXECUTE stmt_add_idx_reimbursement_cost_item_catalog;
DEALLOCATE PREPARE stmt_add_idx_reimbursement_cost_item_catalog;

SET @has_fk_reimbursement_cost_item_catalog := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_reimbursement_cost_item_catalog'
);

SET @sql_add_fk_reimbursement_cost_item_catalog := IF(
  @has_fk_reimbursement_cost_item_catalog = 0,
  'ALTER TABLE reimbursement_entries ADD CONSTRAINT fk_reimbursement_cost_item_catalog FOREIGN KEY (cost_item_catalog_id) REFERENCES cost_item_catalog(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_add_fk_reimbursement_cost_item_catalog FROM @sql_add_fk_reimbursement_cost_item_catalog;
EXECUTE stmt_add_fk_reimbursement_cost_item_catalog;
DEALLOCATE PREPARE stmt_add_fk_reimbursement_cost_item_catalog;

UPDATE reimbursement_entries r
INNER JOIN cost_item_catalog c
  ON c.deleted_at IS NULL
 AND LOWER(TRIM(c.name)) = LOWER(TRIM(r.title))
SET r.cost_item_catalog_id = c.id,
    r.updated_at = NOW()
WHERE r.deleted_at IS NULL
  AND r.cost_item_catalog_id IS NULL;
