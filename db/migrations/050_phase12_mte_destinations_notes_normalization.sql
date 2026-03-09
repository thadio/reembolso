-- Fase 12.5 - Normalizacao de metadados UORG fora de observacoes

UPDATE mte_destinations
SET
  acronym = CASE
    WHEN (acronym IS NULL OR acronym = '')
      AND LOCATE('Sigla:', notes) > 0
    THEN UPPER(TRIM(SUBSTRING_INDEX(SUBSTRING(notes, LOCATE('Sigla:', notes) + CHAR_LENGTH('Sigla:')), '|', 1)))
    ELSE acronym
  END,
  upag_code = CASE
    WHEN (upag_code IS NULL OR upag_code = '')
      AND LOCATE('UPAG:', notes) > 0
    THEN UPPER(TRIM(SUBSTRING_INDEX(SUBSTRING(notes, LOCATE('UPAG:', notes) + CHAR_LENGTH('UPAG:')), '|', 1)))
    ELSE upag_code
  END,
  parent_uorg_code = CASE
    WHEN (parent_uorg_code IS NULL OR parent_uorg_code = '')
      AND LOCATE('UORG vinculacao:', notes) > 0
    THEN UPPER(TRIM(SUBSTRING_INDEX(SUBSTRING(notes, LOCATE('UORG vinculacao:', notes) + CHAR_LENGTH('UORG vinculacao:')), '|', 1)))
    WHEN (parent_uorg_code IS NULL OR parent_uorg_code = '')
      AND LOCATE('UORG vinculação:', notes) > 0
    THEN UPPER(TRIM(SUBSTRING_INDEX(SUBSTRING(notes, LOCATE('UORG vinculação:', notes) + CHAR_LENGTH('UORG vinculação:')), '|', 1)))
    ELSE parent_uorg_code
  END
WHERE deleted_at IS NULL
  AND notes IS NOT NULL
  AND TRIM(notes) <> ''
  AND (
    acronym IS NULL OR acronym = ''
    OR upag_code IS NULL OR upag_code = ''
    OR parent_uorg_code IS NULL OR parent_uorg_code = ''
  );

UPDATE mte_destinations
SET notes = NULL
WHERE deleted_at IS NULL
  AND notes IS NOT NULL
  AND TRIM(notes) LIKE 'Importacao UORG MTE%';
