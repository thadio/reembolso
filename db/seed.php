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
    ['name' => 'people.view', 'description' => 'Visualizar pessoas'],
    ['name' => 'organs.view', 'description' => 'Visualizar órgãos'],
    ['name' => 'organs.manage', 'description' => 'Gerenciar cadastro de órgãos'],
    ['name' => 'audit.view', 'description' => 'Visualizar trilha de auditoria'],
    ['name' => 'admin.manage', 'description' => 'Gerenciar usuários e parâmetros'],
];

$rolePermissions = [
    'sist_admin' => ['dashboard.view', 'people.view', 'organs.view', 'organs.manage', 'audit.view', 'admin.manage'],
    'admin' => ['dashboard.view', 'people.view', 'organs.view', 'organs.manage', 'audit.view'],
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
echo "Admin inicial: {$adminEmail}\n";
echo "Senha inicial (altere após o primeiro login): {$config->get('seed.admin_password', 'ChangeMe123!')}\n";
