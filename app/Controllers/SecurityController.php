<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\SecuritySettingsRepository;
use App\Services\SecuritySettingsService;

final class SecurityController extends Controller
{
    public function index(Request $request): void
    {
        $settings = $this->service()->current();

        $this->view('security/index', [
            'title' => 'Seguranca',
            'settings' => $settings,
            'passwordRulesSummary' => $this->service()->passwordRulesSummary(),
            'canManage' => $this->app->auth()->hasPermission('security.manage'),
        ]);
    }

    public function update(Request $request): void
    {
        $result = $this->service()->update(
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/security');
        }

        flash('success', 'Configuracoes de seguranca atualizadas com sucesso.');
        $this->redirect('/security');
    }

    private function service(): SecuritySettingsService
    {
        return new SecuritySettingsService(
            new SecuritySettingsRepository($this->app->db()),
            $this->app->config(),
            $this->app->audit(),
            $this->app->events()
        );
    }
}
