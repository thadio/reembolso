-- Fase 12.6 - Backfill de codigos para lotacoes tecnicas QA

UPDATE mte_destinations
SET
  upag_code = CASE
    WHEN (upag_code IS NULL OR upag_code = '') THEN LEFT(code, 20)
    ELSE upag_code
  END,
  parent_uorg_code = CASE
    WHEN (parent_uorg_code IS NULL OR parent_uorg_code = '') THEN LEFT(code, 20)
    ELSE parent_uorg_code
  END
WHERE deleted_at IS NULL
  AND code IS NOT NULL
  AND code <> ''
  AND code NOT REGEXP '^[0-9]{14}$'
  AND (
    upag_code IS NULL OR upag_code = ''
    OR parent_uorg_code IS NULL OR parent_uorg_code = ''
  );
