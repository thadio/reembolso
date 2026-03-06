<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
$db = $app->db();
$config = $app->config();

$roles = [
    ['name' => 'sist_admin', 'description' => 'Administrador sistêmico'],
    ['name' => 'admin', 'description' => 'Administrador funcional'],
    ['name' => 'user', 'description' => 'Usuário padrão'],
];

$permissions = [
    ['name' => 'dashboard.view', 'description' => 'Visualizar dashboard'],
    ['name' => 'users.view', 'description' => 'Visualizar modulo administrativo de usuarios e acessos'],
    ['name' => 'users.manage', 'description' => 'Gerenciar usuarios, papeis/permissoes e reset de senha'],
    ['name' => 'security.view', 'description' => 'Visualizar politicas de seguranca e lockout'],
    ['name' => 'security.manage', 'description' => 'Gerenciar politicas de senha, lockout e hardening de upload'],
    ['name' => 'lgpd.view', 'description' => 'Visualizar acessos sensiveis e politicas LGPD'],
    ['name' => 'lgpd.manage', 'description' => 'Gerenciar politicas LGPD e executar retencao/anonimizacao'],
    ['name' => 'budget.view', 'description' => 'Visualizar dashboard orcamentario e capacidade'],
    ['name' => 'budget.manage', 'description' => 'Gerenciar parametros orcamentarios e ciclo'],
    ['name' => 'budget.simulate', 'description' => 'Executar simulacoes de contratacao'],
    ['name' => 'budget.approve', 'description' => 'Aprovar reservas e cenarios orcamentarios'],
    ['name' => 'cdo.view', 'description' => 'Visualizar CDO'],
    ['name' => 'cdo.manage', 'description' => 'Gerenciar CDO'],
    ['name' => 'invoice.view', 'description' => 'Visualizar boletos estruturados'],
    ['name' => 'invoice.manage', 'description' => 'Gerenciar boletos estruturados'],
    ['name' => 'cost_mirror.view', 'description' => 'Visualizar espelhos de custo'],
    ['name' => 'cost_mirror.manage', 'description' => 'Gerenciar espelhos de custo'],
    ['name' => 'office_template.view', 'description' => 'Visualizar templates e oficios gerados'],
    ['name' => 'office_template.manage', 'description' => 'Gerenciar templates e gerar oficios'],
    ['name' => 'process_meta.view', 'description' => 'Visualizar metadados formais de processo'],
    ['name' => 'process_meta.manage', 'description' => 'Gerenciar metadados formais de processo'],
    ['name' => 'sla.view', 'description' => 'Visualizar painel de SLA e pendencias'],
    ['name' => 'sla.manage', 'description' => 'Gerenciar regras de SLA e notificacoes'],
    ['name' => 'report.view', 'description' => 'Visualizar relatorios premium com exportacao CSV/PDF'],
    ['name' => 'people.view', 'description' => 'Visualizar pessoas'],
    ['name' => 'people.manage', 'description' => 'Gerenciar cadastro de pessoas'],
    ['name' => 'people.cpf.full', 'description' => 'Visualizar CPF completo'],
    ['name' => 'people.documents.sensitive', 'description' => 'Visualizar, baixar e classificar documentos restritos/sensiveis'],
    ['name' => 'mte_destinations.view', 'description' => 'Visualizar lotações MTE'],
    ['name' => 'mte_destinations.manage', 'description' => 'Gerenciar cadastro de lotações MTE'],
    ['name' => 'cost_item.view', 'description' => 'Visualizar catalogo de itens de custo'],
    ['name' => 'cost_item.manage', 'description' => 'Gerenciar catalogo de itens de custo'],
    ['name' => 'organs.view', 'description' => 'Visualizar órgãos'],
    ['name' => 'organs.manage', 'description' => 'Gerenciar cadastro de órgãos'],
    ['name' => 'audit.view', 'description' => 'Visualizar trilha de auditoria'],
    ['name' => 'admin.manage', 'description' => 'Gerenciar usuários e parâmetros'],
];

$rolePermissions = [
    'sist_admin' => ['dashboard.view', 'users.view', 'users.manage', 'security.view', 'security.manage', 'lgpd.view', 'lgpd.manage', 'budget.view', 'budget.manage', 'budget.simulate', 'budget.approve', 'cdo.view', 'cdo.manage', 'invoice.view', 'invoice.manage', 'cost_mirror.view', 'cost_mirror.manage', 'office_template.view', 'office_template.manage', 'process_meta.view', 'process_meta.manage', 'sla.view', 'sla.manage', 'report.view', 'people.view', 'people.manage', 'people.cpf.full', 'people.documents.sensitive', 'mte_destinations.view', 'mte_destinations.manage', 'cost_item.view', 'cost_item.manage', 'organs.view', 'organs.manage', 'audit.view', 'admin.manage'],
    'admin' => ['dashboard.view', 'users.view', 'users.manage', 'security.view', 'security.manage', 'lgpd.view', 'lgpd.manage', 'budget.view', 'budget.manage', 'budget.simulate', 'budget.approve', 'cdo.view', 'cdo.manage', 'invoice.view', 'invoice.manage', 'cost_mirror.view', 'cost_mirror.manage', 'office_template.view', 'office_template.manage', 'process_meta.view', 'process_meta.manage', 'sla.view', 'sla.manage', 'report.view', 'people.view', 'people.manage', 'people.cpf.full', 'people.documents.sensitive', 'mte_destinations.view', 'mte_destinations.manage', 'cost_item.view', 'cost_item.manage', 'organs.view', 'organs.manage', 'audit.view'],
    'user' => ['dashboard.view', 'people.view', 'mte_destinations.view', 'organs.view'],
];

$documentTypes = [
    ['name' => 'Currículo', 'description' => 'Currículo do interessado'],
    ['name' => 'Ofício ao órgão', 'description' => 'Documento de solicitação ao órgão de origem'],
    ['name' => 'Resposta do órgão', 'description' => 'Custos e liberação recebidos'],
    ['name' => 'CDO', 'description' => 'Certificação de disponibilidade orçamentária'],
    ['name' => 'Publicação DOU', 'description' => 'Publicação oficial no Diário Oficial da União'],
    ['name' => 'Boleto', 'description' => 'Cobrança recebida do órgão de origem'],
    ['name' => 'Espelho de custo', 'description' => 'Detalhamento de custos por competência'],
    ['name' => 'Comprovante de pagamento', 'description' => 'Evidência de pagamento efetuado'],
];

$eventTypes = [
    ['name' => 'cadastro_interessado', 'description' => 'Cadastro inicial da pessoa'],
    ['name' => 'triagem_concluida', 'description' => 'Triagem concluída'],
    ['name' => 'oficio_orgao_enviado', 'description' => 'Ofício enviado ao órgão'],
    ['name' => 'custos_recebidos', 'description' => 'Custos recebidos do órgão'],
    ['name' => 'cdo_emitido', 'description' => 'CDO emitido'],
    ['name' => 'dou_publicado', 'description' => 'Publicação no DOU registrada'],
    ['name' => 'entrada_mte', 'description' => 'Entrada oficial no MTE'],
    ['name' => 'boleto_recebido', 'description' => 'Boleto recebido'],
    ['name' => 'pagamento_registrado', 'description' => 'Pagamento registrado'],
];

$modalities = [
    ['name' => 'Cessão', 'description' => 'Cessão de servidor/empregado'],
    ['name' => 'Composição de Força de Trabalho', 'description' => 'Composição de força de trabalho'],
    ['name' => 'Requisição', 'description' => 'Requisição administrativa'],
];

$assignmentStatuses = [
    ['code' => 'interessado', 'label' => 'Interessado/Triagem', 'sort_order' => 1, 'next_action_label' => 'Concluir triagem e selecionar', 'event_type' => 'pipeline.selecionado', 'is_active' => 1],
    ['code' => 'triagem', 'label' => 'Triagem (legado)', 'sort_order' => 2, 'next_action_label' => 'Concluir triagem e selecionar', 'event_type' => 'pipeline.selecionado', 'is_active' => 0],
    ['code' => 'selecionado', 'label' => 'Selecionado', 'sort_order' => 3, 'next_action_label' => 'Gerar ofício ao órgão', 'event_type' => 'pipeline.oficio_orgao', 'is_active' => 1],
    ['code' => 'oficio_orgao', 'label' => 'Ofício órgão', 'sort_order' => 4, 'next_action_label' => 'Registrar resposta do órgão', 'event_type' => 'pipeline.custos_recebidos', 'is_active' => 1],
    ['code' => 'custos_recebidos', 'label' => 'Custos recebidos', 'sort_order' => 5, 'next_action_label' => 'Registrar CDO', 'event_type' => 'pipeline.cdo', 'is_active' => 1],
    ['code' => 'cdo', 'label' => 'CDO', 'sort_order' => 6, 'next_action_label' => 'Registrar envio ao MGI', 'event_type' => 'pipeline.mgi', 'is_active' => 1],
    ['code' => 'mgi', 'label' => 'MGI', 'sort_order' => 7, 'next_action_label' => 'Registrar publicação no DOU', 'event_type' => 'pipeline.dou', 'is_active' => 1],
    ['code' => 'dou', 'label' => 'DOU', 'sort_order' => 8, 'next_action_label' => 'Ativar no MTE', 'event_type' => 'pipeline.ativo', 'is_active' => 1],
    ['code' => 'ativo', 'label' => 'Ativo', 'sort_order' => 9, 'next_action_label' => null, 'event_type' => 'pipeline.ativo', 'is_active' => 1],
];

$defaultFlowSteps = [
    ['status_code' => 'interessado', 'node_kind' => 'activity', 'sort_order' => 10, 'is_initial' => 1, 'is_active' => 1],
    ['status_code' => 'selecionado', 'node_kind' => 'activity', 'sort_order' => 20, 'is_initial' => 0, 'is_active' => 1],
    ['status_code' => 'oficio_orgao', 'node_kind' => 'activity', 'sort_order' => 30, 'is_initial' => 0, 'is_active' => 1],
    ['status_code' => 'custos_recebidos', 'node_kind' => 'activity', 'sort_order' => 40, 'is_initial' => 0, 'is_active' => 1],
    ['status_code' => 'cdo', 'node_kind' => 'activity', 'sort_order' => 50, 'is_initial' => 0, 'is_active' => 1],
    ['status_code' => 'mgi', 'node_kind' => 'activity', 'sort_order' => 60, 'is_initial' => 0, 'is_active' => 1],
    ['status_code' => 'dou', 'node_kind' => 'activity', 'sort_order' => 70, 'is_initial' => 0, 'is_active' => 1],
    ['status_code' => 'ativo', 'node_kind' => 'final', 'sort_order' => 80, 'is_initial' => 0, 'is_active' => 1],
];

$defaultFlowTransitions = [
    ['from_code' => 'interessado', 'to_code' => 'selecionado', 'transition_label' => 'Seleção aprovada', 'action_label' => 'Concluir triagem e selecionar', 'event_type' => 'pipeline.selecionado', 'sort_order' => 10, 'is_active' => 1],
    ['from_code' => 'selecionado', 'to_code' => 'oficio_orgao', 'transition_label' => 'Ofício enviado', 'action_label' => 'Gerar ofício ao órgão', 'event_type' => 'pipeline.oficio_orgao', 'sort_order' => 20, 'is_active' => 1],
    ['from_code' => 'oficio_orgao', 'to_code' => 'custos_recebidos', 'transition_label' => 'Resposta recebida', 'action_label' => 'Registrar resposta do órgão', 'event_type' => 'pipeline.custos_recebidos', 'sort_order' => 30, 'is_active' => 1],
    ['from_code' => 'custos_recebidos', 'to_code' => 'cdo', 'transition_label' => 'CDO emitida', 'action_label' => 'Registrar CDO', 'event_type' => 'pipeline.cdo', 'sort_order' => 40, 'is_active' => 1],
    ['from_code' => 'cdo', 'to_code' => 'mgi', 'transition_label' => 'Processo enviado ao MGI', 'action_label' => 'Registrar envio ao MGI', 'event_type' => 'pipeline.mgi', 'sort_order' => 50, 'is_active' => 1],
    ['from_code' => 'mgi', 'to_code' => 'dou', 'transition_label' => 'Publicação registrada', 'action_label' => 'Registrar publicação no DOU', 'event_type' => 'pipeline.dou', 'sort_order' => 60, 'is_active' => 1],
    ['from_code' => 'dou', 'to_code' => 'ativo', 'transition_label' => 'Entrada oficial no MTE', 'action_label' => 'Ativar no MTE', 'event_type' => 'pipeline.ativo', 'sort_order' => 70, 'is_active' => 1],
];

$db->beginTransaction();

try {
    $upsertRole = $db->prepare(
        'INSERT INTO roles (name, description, created_at, updated_at)
         VALUES (:name, :description, NOW(), NOW())
         ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW()'
    );

    foreach ($roles as $role) {
        $upsertRole->execute($role);
    }

    $upsertPermission = $db->prepare(
        'INSERT INTO permissions (name, description, created_at, updated_at)
         VALUES (:name, :description, NOW(), NOW())
         ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW()'
    );

    foreach ($permissions as $permission) {
        $upsertPermission->execute($permission);
    }

    $roleIdStmt = $db->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $permissionIdStmt = $db->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');
    $linkRolePermission = $db->prepare(
        'INSERT INTO role_permissions (role_id, permission_id, created_at)
         VALUES (:role_id, :permission_id, NOW())
         ON DUPLICATE KEY UPDATE created_at = created_at'
    );

    foreach ($rolePermissions as $roleName => $permissionNames) {
        $roleIdStmt->execute(['name' => $roleName]);
        $roleId = (int) ($roleIdStmt->fetch()['id'] ?? 0);
        if ($roleId <= 0) {
            continue;
        }

        foreach ($permissionNames as $permissionName) {
            $permissionIdStmt->execute(['name' => $permissionName]);
            $permissionId = (int) ($permissionIdStmt->fetch()['id'] ?? 0);
            if ($permissionId <= 0) {
                continue;
            }

            $linkRolePermission->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    $upsertCatalog = static function (string $table, array $rows) use ($db): void {
        $stmt = $db->prepare(
            "INSERT INTO {$table} (name, description, is_active, created_at, updated_at)
             VALUES (:name, :description, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = 1, updated_at = NOW()"
        );

        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    };

    $upsertCatalog('document_types', $documentTypes);
    $upsertCatalog('timeline_event_types', $eventTypes);
    $upsertCatalog('modalities', $modalities);

    $upsertAssignmentStatus = $db->prepare(
        'INSERT INTO assignment_statuses (code, label, sort_order, next_action_label, event_type, is_active, created_at, updated_at)
         VALUES (:code, :label, :sort_order, :next_action_label, :event_type, :is_active, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            sort_order = VALUES(sort_order),
            next_action_label = VALUES(next_action_label),
            event_type = VALUES(event_type),
            is_active = VALUES(is_active),
            updated_at = NOW()'
    );

    foreach ($assignmentStatuses as $status) {
        $upsertAssignmentStatus->execute($status);
    }

    $upsertFlow = $db->prepare(
        'INSERT INTO assignment_flows (name, description, is_active, is_default, created_at, updated_at)
         VALUES (:name, :description, :is_active, :is_default, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            is_active = VALUES(is_active),
            is_default = VALUES(is_default),
            updated_at = NOW()'
    );
    $upsertFlow->execute([
        'name' => 'Fluxo padrao',
        'description' => 'Fluxo padrao do processo, com pontos de decisao e transicoes configuraveis.',
        'is_active' => 1,
        'is_default' => 1,
    ]);

    $defaultFlowStmt = $db->prepare(
        'SELECT id
         FROM assignment_flows
         WHERE name = :name
           AND deleted_at IS NULL
         LIMIT 1'
    );
    $defaultFlowStmt->execute(['name' => 'Fluxo padrao']);
    $defaultFlowId = (int) ($defaultFlowStmt->fetch()['id'] ?? 0);

    if ($defaultFlowId > 0) {
        $db->prepare(
            'UPDATE assignment_flows
             SET is_default = CASE WHEN id = :id THEN 1 ELSE 0 END,
                 updated_at = NOW()
             WHERE deleted_at IS NULL'
        )->execute(['id' => $defaultFlowId]);

        $statusByCodeStmt = $db->prepare('SELECT id FROM assignment_statuses WHERE code = :code LIMIT 1');

        $upsertFlowStep = $db->prepare(
            'INSERT INTO assignment_flow_steps (flow_id, status_id, node_kind, sort_order, is_initial, is_active, created_at, updated_at)
             VALUES (:flow_id, :status_id, :node_kind, :sort_order, :is_initial, :is_active, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                node_kind = VALUES(node_kind),
                sort_order = VALUES(sort_order),
                is_initial = VALUES(is_initial),
                is_active = VALUES(is_active),
                updated_at = NOW()'
        );

        foreach ($defaultFlowSteps as $step) {
            $statusByCodeStmt->execute(['code' => $step['status_code']]);
            $statusId = (int) ($statusByCodeStmt->fetch()['id'] ?? 0);
            if ($statusId <= 0) {
                continue;
            }

            $upsertFlowStep->execute([
                'flow_id' => $defaultFlowId,
                'status_id' => $statusId,
                'node_kind' => $step['node_kind'],
                'sort_order' => $step['sort_order'],
                'is_initial' => $step['is_initial'],
                'is_active' => $step['is_active'],
            ]);
        }

        $deleteLegacyTriagemStep = $db->prepare(
            'DELETE fs
             FROM assignment_flow_steps fs
             INNER JOIN assignment_statuses s ON s.id = fs.status_id
             WHERE fs.flow_id = :flow_id
               AND s.code = :code'
        );
        $deleteLegacyTriagemStep->execute([
            'flow_id' => $defaultFlowId,
            'code' => 'triagem',
        ]);

        $findTransitionStmt = $db->prepare(
            'SELECT id
             FROM assignment_flow_transitions
             WHERE flow_id = :flow_id
               AND from_status_id = :from_status_id
               AND to_status_id = :to_status_id
             ORDER BY id ASC
             LIMIT 1'
        );
        $updateTransitionStmt = $db->prepare(
            'UPDATE assignment_flow_transitions
             SET transition_label = :transition_label,
                 action_label = :action_label,
                 event_type = :event_type,
                 sort_order = :sort_order,
                 is_active = :is_active,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $insertTransitionStmt = $db->prepare(
            'INSERT INTO assignment_flow_transitions (
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
             ) VALUES (
                :flow_id,
                :from_status_id,
                :to_status_id,
                :transition_label,
                :action_label,
                :event_type,
                :sort_order,
                :is_active,
                NOW(),
                NOW()
             )'
        );

        foreach ($defaultFlowTransitions as $transition) {
            $statusByCodeStmt->execute(['code' => $transition['from_code']]);
            $fromStatusId = (int) ($statusByCodeStmt->fetch()['id'] ?? 0);

            $statusByCodeStmt->execute(['code' => $transition['to_code']]);
            $toStatusId = (int) ($statusByCodeStmt->fetch()['id'] ?? 0);

            if ($fromStatusId <= 0 || $toStatusId <= 0) {
                continue;
            }

            $payload = [
                'flow_id' => $defaultFlowId,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatusId,
                'transition_label' => $transition['transition_label'],
                'action_label' => $transition['action_label'],
                'event_type' => $transition['event_type'],
                'sort_order' => $transition['sort_order'],
                'is_active' => $transition['is_active'],
            ];

            $findTransitionStmt->execute([
                'flow_id' => $payload['flow_id'],
                'from_status_id' => $payload['from_status_id'],
                'to_status_id' => $payload['to_status_id'],
            ]);
            $existingTransitionId = (int) ($findTransitionStmt->fetch()['id'] ?? 0);

            if ($existingTransitionId > 0) {
                $updateTransitionStmt->execute([
                    'id' => $existingTransitionId,
                    'transition_label' => $payload['transition_label'],
                    'action_label' => $payload['action_label'],
                    'event_type' => $payload['event_type'],
                    'sort_order' => $payload['sort_order'],
                    'is_active' => $payload['is_active'],
                ]);
            } else {
                $insertTransitionStmt->execute($payload);
            }
        }
    }

    $adminEmail = mb_strtolower((string) $config->get('seed.admin_email', 'admin@reembolso.local'));
    $adminName = (string) $config->get('seed.admin_name', 'Administrador Sistema');
    $adminPassword = (string) $config->get('seed.admin_password', 'ChangeMe123!');

    $selectAdmin = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $selectAdmin->execute(['email' => $adminEmail]);
    $admin = $selectAdmin->fetch();

    if ($admin === false) {
        $insertAdmin = $db->prepare(
            'INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at)
             VALUES (:name, :email, :password_hash, 1, NOW(), NOW())'
        );
        $insertAdmin->execute([
            'name' => $adminName,
            'email' => $adminEmail,
            'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
        ]);

        $adminId = (int) $db->lastInsertId();
    } else {
        $adminId = (int) $admin['id'];
    }

    $roleIdStmt->execute(['name' => 'sist_admin']);
    $sistAdminRoleId = (int) ($roleIdStmt->fetch()['id'] ?? 0);

    if ($adminId > 0 && $sistAdminRoleId > 0) {
        $linkUserRole = $db->prepare(
            'INSERT INTO user_roles (user_id, role_id, created_at)
             VALUES (:user_id, :role_id, NOW())
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );

        $linkUserRole->execute([
            'user_id' => $adminId,
            'role_id' => $sistAdminRoleId,
        ]);
    }

    $db->commit();
} catch (Throwable $throwable) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, 'Seed falhou: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

echo "Seed concluído com sucesso.\n";
echo "Admin inicial configurado via SEED_ADMIN_EMAIL (valor oculto).\n";
echo "Senha inicial configurada via SEED_ADMIN_PASSWORD (valor oculto). Altere após o primeiro login.\n";
