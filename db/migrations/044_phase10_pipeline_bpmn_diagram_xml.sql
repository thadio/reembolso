-- Ciclo 10.2 - Persistencia do XML BPMN no cadastro de fluxos

SET @has_assignment_flows_bpmn_diagram_xml := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'assignment_flows'
    AND COLUMN_NAME = 'bpmn_diagram_xml'
);

SET @sql_assignment_flows_bpmn_diagram_xml := IF(
  @has_assignment_flows_bpmn_diagram_xml = 0,
  'ALTER TABLE assignment_flows
      ADD COLUMN bpmn_diagram_xml LONGTEXT NULL AFTER description',
  'SELECT 1'
);

PREPARE stmt_assignment_flows_bpmn_diagram_xml FROM @sql_assignment_flows_bpmn_diagram_xml;
EXECUTE stmt_assignment_flows_bpmn_diagram_xml;
DEALLOCATE PREPARE stmt_assignment_flows_bpmn_diagram_xml;
