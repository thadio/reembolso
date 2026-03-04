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
    ['name' => 'cdo.view', 'description' => 'Visualizar CDO'],
    ['name' => 'cdo.manage', 'description' => 'Gerenciar CDO'],
    ['name' => 'people.view', 'description' => 'Visualizar pessoas'],
    ['name' => 'people.manage', 'description' => 'Gerenciar cadastro de pessoas'],
    ['name' => 'people.cpf.full', 'description' => 'Visualizar CPF completo'],
    ['name' => 'organs.view', 'description' => 'Visualizar órgãos'],
    ['name' => 'organs.manage', 'description' => 'Gerenciar cadastro de órgãos'],
    ['name' => 'audit.view', 'description' => 'Visualizar trilha de auditoria'],
    ['name' => 'admin.manage', 'description' => 'Gerenciar usuários e parâmetros'],
];

$rolePermissions = [
    'sist_admin' => ['dashboard.view', 'cdo.view', 'cdo.manage', 'people.view', 'people.manage', 'people.cpf.full', 'organs.view', 'organs.manage', 'audit.view', 'admin.manage'],
    'admin' => ['dashboard.view', 'cdo.view', 'cdo.manage', 'people.view', 'people.manage', 'people.cpf.full', 'organs.view', 'organs.manage', 'audit.view'],
    'user' => ['dashboard.view', 'people.view', 'organs.view'],
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
    ['code' => 'interessado', 'label' => 'Interessado', 'sort_order' => 1, 'next_action_label' => 'Iniciar triagem', 'event_type' => 'pipeline.triagem'],
    ['code' => 'triagem', 'label' => 'Triagem', 'sort_order' => 2, 'next_action_label' => 'Registrar seleção', 'event_type' => 'pipeline.selecionado'],
    ['code' => 'selecionado', 'label' => 'Selecionado', 'sort_order' => 3, 'next_action_label' => 'Gerar ofício ao órgão', 'event_type' => 'pipeline.oficio_orgao'],
    ['code' => 'oficio_orgao', 'label' => 'Ofício órgão', 'sort_order' => 4, 'next_action_label' => 'Registrar resposta do órgão', 'event_type' => 'pipeline.custos_recebidos'],
    ['code' => 'custos_recebidos', 'label' => 'Custos recebidos', 'sort_order' => 5, 'next_action_label' => 'Registrar CDO', 'event_type' => 'pipeline.cdo'],
    ['code' => 'cdo', 'label' => 'CDO', 'sort_order' => 6, 'next_action_label' => 'Registrar envio ao MGI', 'event_type' => 'pipeline.mgi'],
    ['code' => 'mgi', 'label' => 'MGI', 'sort_order' => 7, 'next_action_label' => 'Registrar publicação no DOU', 'event_type' => 'pipeline.dou'],
    ['code' => 'dou', 'label' => 'DOU', 'sort_order' => 8, 'next_action_label' => 'Ativar no MTE', 'event_type' => 'pipeline.ativo'],
    ['code' => 'ativo', 'label' => 'Ativo', 'sort_order' => 9, 'next_action_label' => null, 'event_type' => 'pipeline.ativo'],
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
         VALUES (:code, :label, :sort_order, :next_action_label, :event_type, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            sort_order = VALUES(sort_order),
            next_action_label = VALUES(next_action_label),
            event_type = VALUES(event_type),
            is_active = 1,
            updated_at = NOW()'
    );

    foreach ($assignmentStatuses as $status) {
        $upsertAssignmentStatus->execute($status);
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
