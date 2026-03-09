-- Dataset fixo de QA para regressao minima (Fase 7.3)
-- IDs reservados na faixa 990xxx para evitar colisao com dados operacionais.

INSERT INTO organs (
  id, name, acronym, cnpj, contact_name, contact_email, created_at, updated_at, deleted_at
) VALUES (
  990001, 'Orgao QA Regressao', 'QARG', '99000100000199', 'Equipe QA', 'qa-regressao@example.com', NOW(), NOW(), NULL
);

INSERT INTO people (
  id, organ_id, desired_modality_id, name, cpf, birth_date, email, phone, status, sei_process_number, tags, notes, created_at, updated_at, deleted_at
) VALUES
(
  990101, 990001, NULL, 'Pessoa QA Ativa', '99010100011', '1990-01-01', 'qa.ativa@example.com', '61990000001', 'ativo', 'SEI-QA-990101', 'qa,regressao', 'Pessoa de validacao ativa', NOW(), NOW(), NULL
),
(
  990102, 990001, NULL, 'Pessoa QA Triagem', '99010200022', '1991-02-02', 'qa.triagem@example.com', '61990000002', 'triagem', 'SEI-QA-990102', 'qa,regressao', 'Pessoa de validacao em triagem', NOW(), NOW(), NULL
);

INSERT INTO cost_plans (
  id, person_id, version_number, label, is_active, created_by, created_at, updated_at, deleted_at
) VALUES
(
  990201, 990101, 1, 'Plano QA v1', 1, NULL, NOW(), NOW(), NULL
);

INSERT INTO cost_plan_items (
  id, cost_plan_id, person_id, item_name, cost_type, amount, start_date, end_date, notes, created_by, created_at, updated_at, deleted_at
) VALUES
(
  990301, 990201, 990101, 'Custo mensal QA', 'mensal', 100.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), NULL, 'Item mensal para regressao', NULL, NOW(), NOW(), NULL
),
(
  990302, 990201, 990101, 'Custo anual QA', 'anual', 1200.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), NULL, 'Item anual para regressao', NULL, NOW(), NOW(), NULL
),
(
  990303, 990201, 990101, 'Custo unico QA', 'unico', 250.00, CURDATE(), NULL, 'Item unico no mes corrente', NULL, NOW(), NOW(), NULL
);

INSERT INTO reimbursement_entries (
  id, person_id, assignment_id, entry_type, status, title, amount, reference_month, due_date, paid_at, notes, created_by, created_at, updated_at, deleted_at
) VALUES
(
  990401, 990101, NULL, 'boleto', 'pendente', 'Reembolso QA pendente', 300.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), DATE_ADD(CURDATE(), INTERVAL 10 DAY), NULL, 'Entrada pendente no mes corrente', NULL, NOW(), NOW(), NULL
),
(
  990402, 990101, NULL, 'boleto', 'pago', 'Reembolso QA pago', 200.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), DATE_ADD(CURDATE(), INTERVAL 5 DAY), NOW(), 'Entrada paga no mes corrente', NULL, NOW(), NOW(), NULL
),
(
  990403, 990102, NULL, 'boleto', 'cancelado', 'Reembolso QA cancelado', 999.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), DATE_ADD(CURDATE(), INTERVAL 2 DAY), NULL, 'Nao deve entrar nos totais de postado/pago', NULL, NOW(), NOW(), NULL
);

INSERT INTO cdos (
  id, number, ug_code, action_code, period_start, period_end, total_amount, status, notes, created_by, created_at, updated_at, deleted_at
) VALUES
(
  990501, 'QA-CDO-990501', 'UG-QA', 'AC-QA', DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), 1000.00, 'aberto', 'CDO de regressao', NULL, NOW(), NOW(), NULL
);

INSERT INTO cdo_people (
  id, cdo_id, person_id, allocated_amount, notes, created_by, created_at, updated_at, deleted_at
) VALUES
(
  990601, 990501, 990101, 250.00, 'Alocacao QA', NULL, NOW(), NOW(), NULL
);

INSERT INTO timeline_events (
  id, person_id, assignment_id, event_type, title, description, event_date, metadata, created_by, created_at
) VALUES
(
  990701, 990101, NULL, 'qa.regression', 'Evento QA', 'Evento de validacao da regressao minima', NOW(), NULL, NULL, NOW()
);
