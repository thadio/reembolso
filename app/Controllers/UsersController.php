<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\SecuritySettingsRepository;
use App\Repositories\UserAdminRepository;
use App\Services\SecuritySettingsService;
use App\Services\UserAdminService;

final class UsersController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $status = (string) $request->input('status', 'all');
        $roleId = max(0, (int) $request->input('role_id', '0'));
        $sort = (string) $request->input('sort', 'created_at');
        $dir = (string) $request->input('dir', 'desc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($query, $status, $roleId, $sort, $dir, $page, $perPage);

        $this->view('users/index', [
            'title' => 'Usuarios e Acessos',
            'users' => $result['items'],
            'roles' => $this->service()->roles(),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => [
                'q' => $query,
                'status' => $status,
                'role_id' => $roleId,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPage,
            ],
            'canManage' => $this->app->auth()->hasPermission('users.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('users/create', [
            'title' => 'Novo Usuario',
            'user' => $this->emptyUser(),
            'roles' => $this->service()->roles(),
            'passwordRulesSummary' => $this->service()->passwordRulesSummary(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($this->sanitizeFlashInput($input));

        $result = $this->service()->create(
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/users/create');
        }

        flash('success', 'Usuario cadastrado com sucesso.');
        $this->redirect('/users/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Usuario invalido.');
            $this->redirect('/users');
        }

        $user = $this->service()->find($id);
        if ($user === null) {
            flash('error', 'Usuario nao encontrado.');
            $this->redirect('/users');
        }

        $this->view('users/show', [
            'title' => 'Detalhe do Usuario',
            'user' => $user,
            'canManage' => $this->app->auth()->hasPermission('users.manage'),
            'isSelf' => (int) ($this->app->auth()->id() ?? 0) === $id,
            'passwordRulesSummary' => $this->service()->passwordRulesSummary(),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Usuario invalido.');
            $this->redirect('/users');
        }

        $user = $this->service()->find($id);
        if ($user === null) {
            flash('error', 'Usuario nao encontrado.');
            $this->redirect('/users');
        }

        $this->view('users/edit', [
            'title' => 'Editar Usuario',
            'user' => $user,
            'roles' => $this->service()->roles(),
            'isSelf' => (int) ($this->app->auth()->id() ?? 0) === $id,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Usuario invalido.');
            $this->redirect('/users');
        }

        $input = $request->all();
        Session::flashInput($this->sanitizeFlashInput($input));

        $result = $this->service()->update(
            $id,
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/users/edit?id=' . $id);
        }

        flash('success', 'Usuario atualizado com sucesso.');
        $this->redirect('/users/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');

        $result = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', $result['message']);
            $this->redirect('/users');
        }

        flash('success', $result['message']);
        $this->redirect('/users');
    }

    public function toggleActive(Request $request): void
    {
        $id = (int) $request->input('id', '0');

        $result = $this->service()->toggleActive(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', $result['message']);
        } else {
            flash('success', $result['message']);
        }

        $redirect = (string) $request->input('redirect', '/users');
        if ($redirect === '') {
            $redirect = '/users';
        }

        $this->redirect($redirect);
    }

    public function roles(Request $request): void
    {
        $matrix = $this->service()->rolePermissionMatrix();

        $this->view('users/roles', [
            'title' => 'Papeis e Permissoes',
            'roles' => $matrix['roles'],
            'permissions' => $matrix['permissions'],
            'rolePermissionMap' => $matrix['role_permission_map'],
        ]);
    }

    public function updateRolePermissions(Request $request): void
    {
        $result = $this->service()->updateRolePermissions(
            $request->all(),
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/users/roles');
        }

        flash('success', 'Permissoes do papel atualizadas com sucesso.');
        $this->redirect('/users/roles');
    }

    public function passwordForm(Request $request): void
    {
        $this->view('users/password', [
            'title' => 'Trocar Senha',
            'passwordRulesSummary' => $this->service()->passwordRulesSummary(),
        ]);
    }

    public function updateOwnPassword(Request $request): void
    {
        $authId = (int) ($this->app->auth()->id() ?? 0);
        if ($authId <= 0) {
            flash('error', 'Sessao invalida. Faca login novamente.');
            $this->redirect('/login');
        }

        $result = $this->service()->changeOwnPassword(
            $authId,
            $request->all(),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/users/password');
        }

        $this->app->auth()->refresh();
        flash('success', 'Senha alterada com sucesso.');
        $this->redirect('/dashboard');
    }

    public function resetPassword(Request $request): void
    {
        $targetUserId = (int) $request->input('id', '0');

        $result = $this->service()->adminResetPassword(
            $targetUserId,
            $request->all(),
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/users/show?id=' . $targetUserId);
        }

        flash('success', 'Senha redefinida com sucesso. Informe a nova senha ao usuario.');
        $this->redirect('/users/show?id=' . $targetUserId);
    }

    /** @return array<string, mixed> */
    private function emptyUser(): array
    {
        return [
            'name' => '',
            'email' => '',
            'cpf' => '',
            'is_active' => 1,
            'role_ids' => [],
        ];
    }

    private function service(): UserAdminService
    {
        return new UserAdminService(
            new UserAdminRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            $this->securityService()
        );
    }

    private function securityService(): SecuritySettingsService
    {
        return new SecuritySettingsService(
            new SecuritySettingsRepository($this->app->db()),
            $this->app->config(),
            $this->app->audit(),
            $this->app->events()
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitizeFlashInput(array $input): array
    {
        unset(
            $input['password'],
            $input['password_confirmation'],
            $input['current_password'],
            $input['new_password'],
            $input['new_password_confirmation'],
            $input['reset_password'],
            $input['reset_password_confirmation']
        );

        return $input;
    }
}
