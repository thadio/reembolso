-- Fase 13.2 - Backfill de codigos da tipologia universal quando houve colisao por chave legada

DROP TEMPORARY TABLE IF EXISTS tmp_cost_code_map;

CREATE TEMPORARY TABLE tmp_cost_code_map (
  cost_code SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  linkage_code SMALLINT UNSIGNED NOT NULL,
  payment_periodicity VARCHAR(30) NOT NULL,
  PRIMARY KEY (cost_code),
  KEY idx_tmp_cost_code_map_lookup (name, linkage_code, payment_periodicity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_cost_code_map (cost_code, name, linkage_code, payment_periodicity)
VALUES
  (1, 'Salario Base / Vencimento', 309, 'mensal'),
  (2, 'Subsidio', 309, 'mensal'),
  (3, 'Soldos', 309, 'mensal'),
  (4, 'Honorarios', 309, 'mensal'),
  (5, 'Pro Labore', 309, 'mensal'),
  (10, 'Adicional de Tempo de Servico', 309, 'mensal'),
  (11, 'Adicional de Produtividade', 309, 'mensal'),
  (12, 'Adicional de Funcao', 309, 'mensal'),
  (13, 'Adicional de Periculosidade', 309, 'mensal'),
  (14, 'Adicional de Insalubridade', 309, 'mensal'),
  (15, 'Adicional Noturno', 309, 'mensal'),
  (16, 'Adicional de Transferencia', 309, 'mensal'),
  (17, 'Adicional de Titulacao', 309, 'mensal'),
  (20, 'Gratificacao de Funcao', 309, 'mensal'),
  (21, 'Gratificacao de Desempenho', 309, 'mensal'),
  (22, 'Gratificacao de Qualificacao', 309, 'mensal'),
  (23, 'Gratificacao Temporaria', 309, 'mensal'),
  (24, 'Gratificacao de Representacao', 309, 'mensal'),
  (25, 'Gratificacao por Projeto', 309, 'mensal'),
  (30, 'Complemento de Remuneracao', 309, 'mensal'),
  (31, 'Diferenca Individual', 309, 'mensal'),
  (32, 'Complemento de Cessao', 309, 'mensal'),
  (33, 'Complemento Salarial', 309, 'mensal'),
  (40, 'Auxilio Alimentacao', 510, 'mensal'),
  (41, 'Auxilio Transporte', 510, 'mensal'),
  (42, 'Auxilio Moradia', 510, 'mensal'),
  (43, 'Auxilio Creche', 510, 'mensal'),
  (44, 'Assistencia Medica', 510, 'mensal'),
  (45, 'Assistencia Odontologica', 510, 'mensal'),
  (46, 'Seguro de Vida', 510, 'mensal'),
  (47, 'Previdencia Complementar', 510, 'mensal'),
  (50, 'INSS Patronal', 309, 'mensal'),
  (51, 'FGTS', 309, 'mensal'),
  (52, 'RAT / SAT', 309, 'mensal'),
  (53, 'Salario Educacao', 309, 'mensal'),
  (54, 'Sistema S', 309, 'mensal'),
  (55, 'PIS sobre folha', 309, 'mensal'),
  (56, 'Contribuicoes para Fundos', 309, 'mensal'),
  (60, 'Ferias', 309, 'anual'),
  (61, '1/3 de Ferias', 309, 'anual'),
  (62, '13o Salario', 309, 'anual'),
  (63, 'Encargos sobre Ferias', 309, 'anual'),
  (64, 'Encargos sobre 13o', 309, 'anual'),
  (65, 'Rescisoes', 309, 'eventual'),
  (66, 'Contingencias Trabalhistas', 309, 'eventual'),
  (70, 'PLR / PPR', 309, 'anual'),
  (71, 'Bonus Anual', 309, 'anual'),
  (72, 'Comissao', 309, 'mensal'),
  (73, 'Stock Options', 309, 'eventual'),
  (74, 'Gratificacao Eventual', 309, 'eventual'),
  (80, 'Treinamento', 510, 'eventual'),
  (81, 'Equipamentos', 510, 'eventual'),
  (82, 'Uniformes', 510, 'eventual'),
  (83, 'Espaco de Trabalho', 510, 'mensal'),
  (84, 'Sistemas', 510, 'mensal'),
  (85, 'Reembolso de Despesas', 510, 'eventual'),
  (90, 'Reembolso de Servidor Cedido', 309, 'mensal'),
  (91, 'Complemento de Remuneracao Cessionario', 309, 'mensal'),
  (92, 'Encargos Reembolsaveis', 309, 'mensal'),
  (93, 'Beneficios Reembolsaveis', 309, 'mensal');

UPDATE cost_item_catalog c
INNER JOIN tmp_cost_code_map m
  ON m.name = c.name
 AND m.linkage_code = c.linkage_code
 AND m.payment_periodicity = c.payment_periodicity
LEFT JOIN cost_item_catalog existing
  ON existing.cost_code = m.cost_code
 AND existing.id <> c.id
SET c.cost_code = m.cost_code,
    c.updated_at = NOW()
WHERE c.cost_code IS NULL
  AND c.deleted_at IS NULL
  AND existing.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_cost_code_map;
