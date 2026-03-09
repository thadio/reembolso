-- Fase 13.1 - Tipologia universal de custos de pessoal no Brasil

ALTER TABLE cost_item_catalog
  ADD COLUMN cost_code SMALLINT UNSIGNED NULL AFTER id,
  ADD COLUMN macro_category VARCHAR(80) NOT NULL DEFAULT 'beneficios_provisoes_indiretos' AFTER cost_code,
  ADD COLUMN subcategory VARCHAR(80) NOT NULL DEFAULT 'Beneficios' AFTER macro_category,
  ADD COLUMN expense_nature VARCHAR(40) NOT NULL DEFAULT 'indenizatoria' AFTER subcategory,
  ADD COLUMN calculation_base VARCHAR(80) NOT NULL DEFAULT 'total' AFTER expense_nature,
  ADD COLUMN charge_incidence TINYINT(1) NOT NULL DEFAULT 0 AFTER calculation_base,
  ADD COLUMN reimbursability VARCHAR(40) NOT NULL DEFAULT 'reembolsavel' AFTER charge_incidence,
  ADD COLUMN predictability VARCHAR(20) NOT NULL DEFAULT 'fixa' AFTER reimbursability,
  ADD COLUMN type_description VARCHAR(255) NULL AFTER predictability;

ALTER TABLE cost_item_catalog
  ADD UNIQUE KEY uq_cost_item_catalog_cost_code (cost_code),
  ADD KEY idx_cost_item_catalog_macro_category (macro_category),
  ADD KEY idx_cost_item_catalog_subcategory (subcategory),
  ADD KEY idx_cost_item_catalog_expense_nature (expense_nature),
  ADD KEY idx_cost_item_catalog_reimbursability (reimbursability),
  ADD KEY idx_cost_item_catalog_predictability (predictability);

UPDATE cost_item_catalog
SET
  macro_category = CASE
    WHEN linkage_code = 309 THEN 'remuneracao_direta'
    ELSE 'beneficios_provisoes_indiretos'
  END,
  subcategory = CASE
    WHEN linkage_code = 309 THEN 'Remuneracao Base'
    ELSE 'Beneficios'
  END,
  expense_nature = CASE
    WHEN linkage_code = 309 THEN 'remuneratoria'
    ELSE 'indenizatoria'
  END,
  calculation_base = CASE
    WHEN linkage_code = 309 THEN 'salario_base'
    ELSE 'total'
  END,
  charge_incidence = CASE
    WHEN linkage_code = 309 THEN 1
    ELSE 0
  END,
  reimbursability = CASE
    WHEN is_reimbursable = 1 THEN 'reembolsavel'
    ELSE 'nao_reembolsavel'
  END,
  predictability = CASE
    WHEN payment_periodicity = 'mensal' THEN 'fixa'
    WHEN payment_periodicity = 'anual' THEN 'variavel'
    ELSE 'eventual'
  END,
  type_description = COALESCE(type_description, CONCAT('Item legado: ', name))
WHERE cost_code IS NULL;

INSERT INTO cost_item_catalog (
  cost_code,
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
  (1, 'Salario Base / Vencimento', 'Remuneracao fixa do cargo', 'remuneracao_direta', 'Remuneracao Base', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (2, 'Subsidio', 'Remuneracao unica de cargos politicos ou magistratura', 'remuneracao_direta', 'Remuneracao Base', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (3, 'Soldos', 'Remuneracao de militares', 'remuneracao_direta', 'Remuneracao Base', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (4, 'Honorarios', 'Pagamentos por funcao ou cargo', 'remuneracao_direta', 'Remuneracao Base', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (5, 'Pro Labore', 'Remuneracao de socios', 'remuneracao_direta', 'Remuneracao Base', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),

  (10, 'Adicional de Tempo de Servico', 'Anuenio, trienio, quinquenio', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (11, 'Adicional de Produtividade', 'Metas ou desempenho', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (12, 'Adicional de Funcao', 'Cargo de chefia', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (13, 'Adicional de Periculosidade', 'Trabalho perigoso', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (14, 'Adicional de Insalubridade', 'Trabalho insalubre', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (15, 'Adicional Noturno', 'Trabalho noturno', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (16, 'Adicional de Transferencia', 'Mudanca de localidade', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (17, 'Adicional de Titulacao', 'Pos-graduacao ou especializacao', 'remuneracao_direta', 'Adicionais', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),

  (20, 'Gratificacao de Funcao', 'Funcao de confianca', 'remuneracao_direta', 'Gratificacoes', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (21, 'Gratificacao de Desempenho', 'Avaliacao institucional', 'remuneracao_direta', 'Gratificacoes', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (22, 'Gratificacao de Qualificacao', 'Formacao adicional', 'remuneracao_direta', 'Gratificacoes', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (23, 'Gratificacao Temporaria', 'Situacao transitoria', 'remuneracao_direta', 'Gratificacoes', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (24, 'Gratificacao de Representacao', 'Funcao institucional', 'remuneracao_direta', 'Gratificacoes', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (25, 'Gratificacao por Projeto', 'Projetos estrategicos', 'remuneracao_direta', 'Gratificacoes', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),

  (30, 'Complemento de Remuneracao', 'Ajuste de salario', 'remuneracao_direta', 'Complementos', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (31, 'Diferenca Individual', 'Ajuste historico', 'remuneracao_direta', 'Complementos', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (32, 'Complemento de Cessao', 'Pagamento por cessao', 'remuneracao_direta', 'Complementos', 'remuneratoria', 'valor_fixo', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (33, 'Complemento Salarial', 'Ajuste de equiparacao', 'remuneracao_direta', 'Complementos', 'remuneratoria', 'salario_base', 1, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),

  (40, 'Auxilio Alimentacao', 'Vale refeicao ou alimentacao', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (41, 'Auxilio Transporte', 'Vale transporte', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (42, 'Auxilio Moradia', 'Ajuda de custo habitacional', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (43, 'Auxilio Creche', 'Apoio a dependentes', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (44, 'Assistencia Medica', 'Plano de saude', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (45, 'Assistencia Odontologica', 'Plano odontologico', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (46, 'Seguro de Vida', 'Beneficio securitario', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (47, 'Previdencia Complementar', 'Fundos de pensao', 'beneficios_provisoes_indiretos', 'Beneficios', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 510, 1, 'mensal', NULL, NOW(), NOW(), NULL),

  (50, 'INSS Patronal', 'Contribuicao previdenciaria', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (51, 'FGTS', 'Fundo de garantia', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (52, 'RAT / SAT', 'Riscos ambientais', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (53, 'Salario Educacao', 'Contribuicao social', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (54, 'Sistema S', 'SENAI, SESC e correlatos', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (55, 'PIS sobre folha', 'Contribuicao social', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (56, 'Contribuicoes para Fundos', 'PREVI, FUNCEF e correlatos', 'encargos_obrigacoes_legais', 'Encargos Sociais e Trabalhistas', 'encargos', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),

  (60, 'Ferias', 'Provisao anual', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'anual', NULL, NOW(), NOW(), NULL),
  (61, '1/3 de Ferias', 'Adicional constitucional', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'anual', NULL, NOW(), NOW(), NULL),
  (62, '13o Salario', 'Gratificacao anual', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'anual', NULL, NOW(), NOW(), NULL),
  (63, 'Encargos sobre Ferias', 'INSS e FGTS sobre ferias', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'anual', NULL, NOW(), NOW(), NULL),
  (64, 'Encargos sobre 13o', 'INSS e FGTS sobre 13o', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'fixa', 309, 1, 'anual', NULL, NOW(), NOW(), NULL),
  (65, 'Rescisoes', 'Demissoes futuras', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'eventual', 309, 1, 'eventual', NULL, NOW(), NOW(), NULL),
  (66, 'Contingencias Trabalhistas', 'Processos judiciais', 'beneficios_provisoes_indiretos', 'Provisoes Trabalhistas', 'provisoes', 'total_folha', 0, 'parcialmente_reembolsavel', 'eventual', 309, 1, 'eventual', NULL, NOW(), NOW(), NULL),

  (70, 'PLR / PPR', 'Participacao nos lucros', 'remuneracao_direta', 'Remuneracoes Variaveis', 'remuneratoria', 'valor_fixo', 1, 'nao_reembolsavel', 'variavel', 309, 0, 'anual', NULL, NOW(), NOW(), NULL),
  (71, 'Bonus Anual', 'Remuneracao variavel anual', 'remuneracao_direta', 'Remuneracoes Variaveis', 'remuneratoria', 'valor_fixo', 1, 'nao_reembolsavel', 'variavel', 309, 0, 'anual', NULL, NOW(), NOW(), NULL),
  (72, 'Comissao', 'Percentual sobre vendas', 'remuneracao_direta', 'Remuneracoes Variaveis', 'remuneratoria', 'total', 1, 'parcialmente_reembolsavel', 'variavel', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (73, 'Stock Options', 'Opcoes de acoes', 'remuneracao_direta', 'Remuneracoes Variaveis', 'remuneratoria', 'valor_fixo', 0, 'nao_reembolsavel', 'eventual', 309, 0, 'eventual', NULL, NOW(), NOW(), NULL),
  (74, 'Gratificacao Eventual', 'Pagamento extraordinario', 'remuneracao_direta', 'Remuneracoes Variaveis', 'remuneratoria', 'valor_fixo', 0, 'parcialmente_reembolsavel', 'eventual', 309, 1, 'eventual', NULL, NOW(), NOW(), NULL),

  (80, 'Treinamento', 'Capacitacao', 'beneficios_provisoes_indiretos', 'Custos de Pessoal Indiretos', 'indenizatoria', 'valor_fixo', 0, 'nao_reembolsavel', 'eventual', 510, 0, 'eventual', NULL, NOW(), NOW(), NULL),
  (81, 'Equipamentos', 'Notebook, celular e afins', 'beneficios_provisoes_indiretos', 'Custos de Pessoal Indiretos', 'indenizatoria', 'valor_fixo', 0, 'nao_reembolsavel', 'eventual', 510, 0, 'eventual', NULL, NOW(), NOW(), NULL),
  (82, 'Uniformes', 'Vestimenta', 'beneficios_provisoes_indiretos', 'Custos de Pessoal Indiretos', 'indenizatoria', 'valor_fixo', 0, 'nao_reembolsavel', 'eventual', 510, 0, 'eventual', NULL, NOW(), NOW(), NULL),
  (83, 'Espaco de Trabalho', 'Infraestrutura', 'beneficios_provisoes_indiretos', 'Custos de Pessoal Indiretos', 'indenizatoria', 'total', 0, 'nao_reembolsavel', 'fixa', 510, 0, 'mensal', NULL, NOW(), NOW(), NULL),
  (84, 'Sistemas', 'Softwares corporativos', 'beneficios_provisoes_indiretos', 'Custos de Pessoal Indiretos', 'indenizatoria', 'total', 0, 'nao_reembolsavel', 'fixa', 510, 0, 'mensal', NULL, NOW(), NOW(), NULL),
  (85, 'Reembolso de Despesas', 'Viagens, combustivel e correlatos', 'beneficios_provisoes_indiretos', 'Custos de Pessoal Indiretos', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'eventual', 510, 1, 'eventual', NULL, NOW(), NOW(), NULL),

  (90, 'Reembolso de Servidor Cedido', 'Reembolso entre orgaos', 'beneficios_provisoes_indiretos', 'Cessao ou Cooperacao', 'indenizatoria', 'total', 0, 'reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (91, 'Complemento de Remuneracao Cessionario', 'Complemento de remuneracao pelo orgao cessionario', 'beneficios_provisoes_indiretos', 'Cessao ou Cooperacao', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (92, 'Encargos Reembolsaveis', 'INSS, FGTS e correlatos reembolsaveis', 'beneficios_provisoes_indiretos', 'Cessao ou Cooperacao', 'encargos', 'total_folha', 0, 'reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL),
  (93, 'Beneficios Reembolsaveis', 'Plano de saude e correlatos reembolsaveis', 'beneficios_provisoes_indiretos', 'Cessao ou Cooperacao', 'indenizatoria', 'valor_fixo', 0, 'reembolsavel', 'fixa', 309, 1, 'mensal', NULL, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
  cost_code = VALUES(cost_code),
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
