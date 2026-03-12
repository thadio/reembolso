-- Fase 16.2 - Unificacao de Adicionais e Complementos no catalogo de itens de custo

SET @merged_subcategory := 'Adicionais e Complementos';
SET @merged_description := 'Categoria agregadora para adicionais e complementos de remuneracao';

SET @target_aggregator_id := (
  SELECT id
  FROM cost_item_catalog
  WHERE deleted_at IS NULL
    AND is_aggregator = 1
    AND cost_code = 1002
  ORDER BY id
  LIMIT 1
);

SET @source_aggregator_id := (
  SELECT id
  FROM cost_item_catalog
  WHERE deleted_at IS NULL
    AND is_aggregator = 1
    AND cost_code = 1004
  ORDER BY id
  LIMIT 1
);

SET @effective_target_aggregator_id := COALESCE(@target_aggregator_id, @source_aggregator_id);

UPDATE cost_item_catalog
SET name = @merged_subcategory,
    subcategory = @merged_subcategory,
    type_description = @merged_description,
    hierarchy_sort = CASE
      WHEN IFNULL(hierarchy_sort, 0) > 0 THEN hierarchy_sort
      ELSE 20
    END,
    updated_at = NOW(),
    deleted_at = NULL
WHERE id = @effective_target_aggregator_id;

UPDATE cost_item_catalog
SET subcategory = @merged_subcategory,
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND is_aggregator = 0
  AND subcategory IN ('Adicionais', 'Complementos', 'Adicionais e Complementos');

UPDATE cost_item_catalog
SET parent_cost_item_id = @effective_target_aggregator_id,
    subcategory = @merged_subcategory,
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND is_aggregator = 0
  AND @effective_target_aggregator_id IS NOT NULL
  AND (
    parent_cost_item_id = @source_aggregator_id
    OR parent_cost_item_id = @target_aggregator_id
    OR subcategory = @merged_subcategory
  );

UPDATE cost_item_catalog
SET deleted_at = COALESCE(deleted_at, NOW()),
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND is_aggregator = 1
  AND cost_code = 1004
  AND @source_aggregator_id IS NOT NULL
  AND @effective_target_aggregator_id IS NOT NULL
  AND id <> @effective_target_aggregator_id;

UPDATE cost_item_catalog
SET parent_cost_item_id = NULL,
    is_aggregator = 1,
    macro_category = 'remuneracao_direta',
    subcategory = @merged_subcategory,
    name = @merged_subcategory,
    type_description = @merged_description,
    linkage_code = 309,
    updated_at = NOW(),
    deleted_at = NULL
WHERE id = @effective_target_aggregator_id;
