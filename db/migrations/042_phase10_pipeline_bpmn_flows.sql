-- Ciclo 10.1 - Pipeline BPMN configuravel por fluxo com transicoes nao lineares

CREATE TABLE IF NOT EXISTS assignment_flows (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_assignment_flows_name (name),
  KEY idx_assignment_flows_active (is_active, deleted_at),
  KEY idx_assignment_flows_default (is_default, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_flow_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flow_id INT UNSIGNED NOT NULL,
  status_id INT UNSIGNED NOT NULL,
  node_kind VARCHAR(20) NOT NULL DEFAULT 'activity',
  sort_order INT UNSIGNED NOT NULL DEFAULT 10,
  is_initial TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment_flow_steps_flow_status (flow_id, status_id),
  KEY idx_assignment_flow_steps_flow_sort (flow_id, is_active, sort_order, id),
  KEY idx_assignment_flow_steps_flow_initial (flow_id, is_initial, is_active),
  CONSTRAINT fk_assignment_flow_steps_flow FOREIGN KEY (flow_id) REFERENCES assignment_flows(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_flow_steps_status FOREIGN KEY (status_id) REFERENCES assignment_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_flow_transitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flow_id INT UNSIGNED NOT NULL,
  from_status_id INT UNSIGNED NOT NULL,
  to_status_id INT UNSIGNED NOT NULL,
  transition_label VARCHAR(160) NULL,
  action_label VARCHAR(180) NULL,
  event_type VARCHAR(120) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 10,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_assignment_flow_transitions_from (flow_id, from_status_id, is_active, sort_order, id),
  KEY idx_assignment_flow_transitions_to (flow_id, to_status_id, is_active),
  CONSTRAINT fk_assignment_flow_transitions_flow FOREIGN KEY (flow_id) REFERENCES assignment_flows(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_flow_transitions_from_status FOREIGN KEY (from_status_id) REFERENCES assignment_statuses(id),
  CONSTRAINT fk_assignment_flow_transitions_to_status FOREIGN KEY (to_status_id) REFERENCES assignment_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_people_assignment_flow := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'assignment_flow_id'
);

SET @sql_people_assignment_flow := IF(
  @has_people_assignment_flow = 0,
  'ALTER TABLE people
      ADD COLUMN assignment_flow_id INT UNSIGNED NULL AFTER desired_modality_id',
  'SELECT 1'
);

PREPARE stmt_people_assignment_flow FROM @sql_people_assignment_flow;
EXECUTE stmt_people_assignment_flow;
DEALLOCATE PREPARE stmt_people_assignment_flow;

SET @has_assignments_flow := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'flow_id'
);

SET @sql_assignments_flow := IF(
  @has_assignments_flow = 0,
  'ALTER TABLE assignments
      ADD COLUMN flow_id INT UNSIGNED NULL AFTER person_id',
  'SELECT 1'
);

PREPARE stmt_assignments_flow FROM @sql_assignments_flow;
EXECUTE stmt_assignments_flow;
DEALLOCATE PREPARE stmt_assignments_flow;

SET @has_idx_people_assignment_flow := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND INDEX_NAME = 'idx_people_assignment_flow'
);

SET @sql_idx_people_assignment_flow := IF(
  @has_idx_people_assignment_flow = 0,
  'ALTER TABLE people
      ADD KEY idx_people_assignment_flow (assignment_flow_id, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_people_assignment_flow FROM @sql_idx_people_assignment_flow;
EXECUTE stmt_idx_people_assignment_flow;
DEALLOCATE PREPARE stmt_idx_people_assignment_flow;

SET @has_idx_assignments_flow_status := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND INDEX_NAME = 'idx_assignments_flow_status_deleted'
);

SET @sql_idx_assignments_flow_status := IF(
  @has_idx_assignments_flow_status = 0,
  'ALTER TABLE assignments
      ADD KEY idx_assignments_flow_status_deleted (flow_id, current_status_id, deleted_at)',
  'SELECT 1'
);

PREPARE stmt_idx_assignments_flow_status FROM @sql_idx_assignments_flow_status;
EXECUTE stmt_idx_assignments_flow_status;
DEALLOCATE PREPARE stmt_idx_assignments_flow_status;

SET @has_fk_people_assignment_flow := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'assignment_flow_id'
    AND REFERENCED_TABLE_NAME = 'assignment_flows'
);

SET @sql_fk_people_assignment_flow := IF(
  @has_fk_people_assignment_flow = 0,
  'ALTER TABLE people
      ADD CONSTRAINT fk_people_assignment_flow
      FOREIGN KEY (assignment_flow_id) REFERENCES assignment_flows(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_fk_people_assignment_flow FROM @sql_fk_people_assignment_flow;
EXECUTE stmt_fk_people_assignment_flow;
DEALLOCATE PREPARE stmt_fk_people_assignment_flow;

SET @has_fk_assignments_flow := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignments'
    AND COLUMN_NAME = 'flow_id'
    AND REFERENCED_TABLE_NAME = 'assignment_flows'
);

SET @sql_fk_assignments_flow := IF(
  @has_fk_assignments_flow = 0,
  'ALTER TABLE assignments
      ADD CONSTRAINT fk_assignments_flow
      FOREIGN KEY (flow_id) REFERENCES assignment_flows(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_fk_assignments_flow FROM @sql_fk_assignments_flow;
EXECUTE stmt_fk_assignments_flow;
DEALLOCATE PREPARE stmt_fk_assignments_flow;

INSERT INTO assignment_flows (name, description, is_active, is_default, created_at, updated_at)
SELECT
  'Fluxo padrao',
  'Fluxo padrao do processo, com pontos de decisao e transicoes configuraveis.',
  1,
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1
  FROM assignment_flows
  WHERE name = 'Fluxo padrao'
    AND deleted_at IS NULL
);

SET @default_flow_id := COALESCE(
  (
    SELECT id
    FROM assignment_flows
    WHERE is_default = 1
      AND deleted_at IS NULL
    ORDER BY id ASC
    LIMIT 1
  ),
  (
    SELECT id
    FROM assignment_flows
    WHERE deleted_at IS NULL
    ORDER BY id ASC
    LIMIT 1
  )
);

UPDATE assignment_flows
SET is_default = CASE WHEN id = @default_flow_id THEN 1 ELSE 0 END,
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND @default_flow_id IS NOT NULL;

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'interessado',
  'Interessado/Triagem',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Concluir triagem e selecionar',
  'pipeline.selecionado',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'interessado'
);

UPDATE assignment_statuses
SET label = 'Interessado/Triagem',
    next_action_label = 'Concluir triagem e selecionar',
    event_type = 'pipeline.selecionado',
    is_active = 1,
    updated_at = NOW()
WHERE code = 'interessado';

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'selecionado',
  'Selecionado',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Gerar oficio ao orgao',
  'pipeline.oficio_orgao',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'selecionado'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'oficio_orgao',
  'Oficio orgao',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Registrar resposta do orgao',
  'pipeline.custos_recebidos',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'oficio_orgao'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'custos_recebidos',
  'Custos recebidos',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Registrar CDO',
  'pipeline.cdo',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'custos_recebidos'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'cdo',
  'CDO',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Registrar envio ao MGI',
  'pipeline.mgi',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'cdo'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'mgi',
  'MGI',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Registrar publicacao no DOU',
  'pipeline.dou',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'mgi'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'dou',
  'DOU',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  'Ativar no MTE',
  'pipeline.ativo',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'dou'
);

INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
SELECT
  'ativo',
  'Ativo',
  COALESCE((SELECT MAX(s.sort_order) + 1 FROM assignment_statuses s), 1),
  NULL,
  'pipeline.ativo',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM assignment_statuses WHERE code = 'ativo'
);

UPDATE assignment_statuses
SET is_active = 0,
    updated_at = NOW()
WHERE code = 'triagem';

INSERT INTO assignment_flow_steps (flow_id, status_id, node_kind, sort_order, is_initial, is_active, created_at, updated_at)
SELECT
  @default_flow_id,
  s.id,
  CASE WHEN s.code = 'ativo' THEN 'final' ELSE 'activity' END,
  CASE s.code
    WHEN 'interessado' THEN 10
    WHEN 'selecionado' THEN 20
    WHEN 'oficio_orgao' THEN 30
    WHEN 'custos_recebidos' THEN 40
    WHEN 'cdo' THEN 50
    WHEN 'mgi' THEN 60
    WHEN 'dou' THEN 70
    WHEN 'ativo' THEN 80
    ELSE 900
  END,
  CASE WHEN s.code = 'interessado' THEN 1 ELSE 0 END,
  1,
  NOW(),
  NOW()
FROM assignment_statuses s
WHERE s.code IN ('interessado', 'selecionado', 'oficio_orgao', 'custos_recebidos', 'cdo', 'mgi', 'dou', 'ativo')
  AND s.is_active = 1
  AND @default_flow_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM assignment_flow_steps fs
    WHERE fs.flow_id = @default_flow_id
      AND fs.status_id = s.id
  );

DELETE fs
FROM assignment_flow_steps fs
INNER JOIN assignment_statuses s ON s.id = fs.status_id
WHERE fs.flow_id = @default_flow_id
  AND s.code = 'triagem';

SET @status_interessado_id := (
  SELECT id
  FROM assignment_statuses
  WHERE code = 'interessado'
  LIMIT 1
);

SET @status_triagem_id := (
  SELECT id
  FROM assignment_statuses
  WHERE code = 'triagem'
  LIMIT 1
);

UPDATE assignments
SET current_status_id = @status_interessado_id,
    updated_at = NOW()
WHERE current_status_id = @status_triagem_id
  AND @status_interessado_id IS NOT NULL
  AND @status_triagem_id IS NOT NULL;

UPDATE people
SET status = 'interessado',
    updated_at = NOW()
WHERE status = 'triagem';

INSERT INTO assignment_flow_transitions (
  flow_id,
  from_status_id,
  to_status_id,
  transition_label,
  action_label,
  event_type,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  @default_flow_id,
  sf.id,
  st.id,
  transition_data.transition_label,
  transition_data.action_label,
  transition_data.event_type,
  transition_data.sort_order,
  1,
  NOW(),
  NOW()
FROM (
  SELECT 'interessado' AS from_code, 'selecionado' AS to_code, 'Selecao aprovada' AS transition_label, 'Concluir triagem e selecionar' AS action_label, 'pipeline.selecionado' AS event_type, 10 AS sort_order
  UNION ALL SELECT 'selecionado', 'oficio_orgao', 'Oficio enviado', 'Gerar oficio ao orgao', 'pipeline.oficio_orgao', 20
  UNION ALL SELECT 'oficio_orgao', 'custos_recebidos', 'Resposta recebida', 'Registrar resposta do orgao', 'pipeline.custos_recebidos', 30
  UNION ALL SELECT 'custos_recebidos', 'cdo', 'CDO emitida', 'Registrar CDO', 'pipeline.cdo', 40
  UNION ALL SELECT 'cdo', 'mgi', 'Processo enviado ao MGI', 'Registrar envio ao MGI', 'pipeline.mgi', 50
  UNION ALL SELECT 'mgi', 'dou', 'Publicacao registrada', 'Registrar publicacao no DOU', 'pipeline.dou', 60
  UNION ALL SELECT 'dou', 'ativo', 'Entrada oficial no MTE', 'Ativar no MTE', 'pipeline.ativo', 70
) transition_data
INNER JOIN assignment_statuses sf ON sf.code = transition_data.from_code
INNER JOIN assignment_statuses st ON st.code = transition_data.to_code
WHERE @default_flow_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM assignment_flow_transitions ft
    WHERE ft.flow_id = @default_flow_id
      AND ft.from_status_id = sf.id
      AND ft.to_status_id = st.id
  );

UPDATE people
SET assignment_flow_id = @default_flow_id,
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND assignment_flow_id IS NULL
  AND @default_flow_id IS NOT NULL;

UPDATE assignments a
LEFT JOIN people p ON p.id = a.person_id
SET a.flow_id = COALESCE(p.assignment_flow_id, @default_flow_id),
    a.updated_at = NOW()
WHERE a.deleted_at IS NULL
  AND a.flow_id IS NULL
  AND @default_flow_id IS NOT NULL;
