<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\ModuleCatalogService;
use App\Services\ModuleHandoffService;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $authUser = $this->app->auth()->user();
        $moduleCatalog = $this->moduleCatalog();
        $modules = $moduleCatalog->availableForUser($authUser);

        $sourceModuleSlug = $this->referrerModuleSlug($moduleCatalog, $authUser);
        if ($sourceModuleSlug !== null) {
            $this->auditModuleExit(
                request: $request,
                moduleSlug: $sourceModuleSlug,
                exitReason: 'portal_home',
                targetModuleSlug: null,
                targetPath: '/home'
            );
        }

        $this->auditPortalView($request, $modules);

        $this->view('home/index', [
            'title' => 'Inicio de modulos',
            'modules' => $modules,
        ]);
    }

    public function openReembolso(Request $request): void
    {
        $this->openModule($request, ModuleCatalogService::MODULE_REEMBOLSO);
    }

    public function openBpmn(Request $request): void
    {
        $this->openModule($request, ModuleCatalogService::MODULE_BPMN);
    }

    public function openUsuarios(Request $request): void
    {
        $this->openModule($request, ModuleCatalogService::MODULE_USUARIOS);
    }

    public function openAuxilioTransporte(Request $request): void
    {
        $this->openModule($request, ModuleCatalogService::MODULE_AUXILIO_TRANSPORTE);
    }

    public function openIndenizacaoFronteirico(Request $request): void
    {
        $this->openModule($request, ModuleCatalogService::MODULE_INDENIZACAO_FRONTEIRICO);
    }

    public function openRecomposicaoErario(Request $request): void
    {
        $this->openModule($request, ModuleCatalogService::MODULE_RECOMPOSICAO_ERARIO);
    }

    private function moduleCatalog(): ModuleCatalogService
    {
        return new ModuleCatalogService($this->app->config());
    }

    private function handoffService(): ModuleHandoffService
    {
        return new ModuleHandoffService($this->app->config());
    }

    private function openModule(Request $request, string $moduleSlug): void
    {
        $authUser = $this->app->auth()->user();
        $moduleCatalog = $this->moduleCatalog();
        $sourceModuleSlug = $this->referrerModuleSlug($moduleCatalog, $authUser);
        $destination = $moduleCatalog->moduleDestinationForUser($authUser, $moduleSlug);

        if ($destination === null) {
            $this->auditModuleOpenDenied($request, $moduleSlug);
            flash('error', 'Voce nao possui acesso ao modulo solicitado.');
            $this->redirect('/home');
        }

        if ($sourceModuleSlug !== null && $sourceModuleSlug !== $moduleSlug) {
            $this->auditModuleExit(
                request: $request,
                moduleSlug: $sourceModuleSlug,
                exitReason: 'module_switch',
                targetModuleSlug: $moduleSlug,
                targetPath: '/modules/' . $moduleSlug
            );
        }

        $entryType = (string) ($destination['entry_type'] ?? 'internal_route');
        $baseDestination = (string) ($destination['destination'] ?? '/home');
        $target = $baseDestination;
        $tokenIssued = false;
        $tokenExpiresAt = null;

        if ($entryType === 'external_url') {
            $handoff = $this->handoffService()->buildExternalRedirect($baseDestination, $moduleSlug, $authUser);
            $target = (string) ($handoff['url'] ?? $baseDestination);
            $tokenIssued = (bool) ($handoff['token_issued'] ?? false);
            $tokenExpiresAt = $handoff['token_expires_at'] ?? null;
            if (!is_string($tokenExpiresAt) || trim($tokenExpiresAt) === '') {
                $tokenExpiresAt = null;
            }
        }

        $this->auditModuleOpen(
            request: $request,
            moduleSlug: $moduleSlug,
            moduleName: (string) ($destination['name'] ?? 'Modulo'),
            entryType: $entryType,
            accessMode: (string) ($destination['access_mode'] ?? 'module_gate'),
            destinationBase: $baseDestination,
            destinationFinal: $target,
            handoffTokenIssued: $tokenIssued,
            handoffTokenExpiresAt: $tokenExpiresAt
        );

        if ($entryType === 'external_url') {
            $this->redirectAbsolute($target);
        }

        $this->redirect($target);
    }

    /**
     * @param array<string, mixed>|null $authUser
     */
    private function referrerModuleSlug(ModuleCatalogService $moduleCatalog, ?array $authUser): ?string
    {
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer === '') {
            return null;
        }

        $refererPath = parse_url($referer, PHP_URL_PATH);
        if (!is_string($refererPath) || trim($refererPath) === '') {
            return null;
        }

        $slug = $moduleCatalog->activeModuleSlugForPath($authUser, $refererPath);
        if (!is_string($slug) || trim($slug) === '') {
            return null;
        }

        return $slug;
    }

    private function redirectAbsolute(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     */
    private function auditPortalView(Request $request, array $modules): void
    {
        $userId = (int) ($this->app->auth()->id() ?? 0);

        $moduleSlugs = [];
        foreach ($modules as $module) {
            $slug = trim((string) ($module['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $moduleSlugs[] = $slug;
        }

        $metadata = [
            'modules_count' => count($moduleSlugs),
            'module_slugs' => $moduleSlugs,
        ];

        $this->app->audit()->log(
            entity: 'module_portal',
            entityId: null,
            action: 'portal.view',
            beforeData: null,
            afterData: null,
            metadata: $metadata,
            userId: $userId > 0 ? $userId : null,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->app->events()->recordEvent(
            entity: 'module_portal',
            type: 'module_portal.viewed',
            payload: $metadata,
            entityId: null,
            userId: $userId > 0 ? $userId : null
        );
    }

    private function auditModuleOpenDenied(Request $request, string $moduleSlug): void
    {
        $userId = (int) ($this->app->auth()->id() ?? 0);
        $payload = [
            'module_slug' => $moduleSlug,
            'result' => 'denied',
        ];

        $this->app->audit()->log(
            entity: 'module_portal',
            entityId: null,
            action: 'module.open.denied',
            beforeData: null,
            afterData: null,
            metadata: $payload,
            userId: $userId > 0 ? $userId : null,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->app->events()->recordEvent(
            entity: 'module_portal',
            type: 'module.open_denied',
            payload: $payload,
            entityId: null,
            userId: $userId > 0 ? $userId : null
        );
    }

    private function auditModuleOpen(
        Request $request,
        string $moduleSlug,
        string $moduleName,
        string $entryType,
        string $accessMode,
        string $destinationBase,
        string $destinationFinal,
        bool $handoffTokenIssued,
        ?string $handoffTokenExpiresAt
    ): void {
        $userId = (int) ($this->app->auth()->id() ?? 0);

        $targetHost = null;
        if ($entryType === 'external_url') {
            $parsedHost = parse_url($destinationFinal, PHP_URL_HOST);
            if (is_string($parsedHost) && trim($parsedHost) !== '') {
                $targetHost = $parsedHost;
            }
        }

        $payload = [
            'module_slug' => $moduleSlug,
            'module_name' => $moduleName,
            'entry_type' => $entryType,
            'access_mode' => $accessMode,
            'destination_base' => $destinationBase,
            'destination_final' => $entryType === 'external_url' ? null : $destinationFinal,
            'destination_host' => $targetHost,
            'handoff_token_issued' => $handoffTokenIssued ? 1 : 0,
            'handoff_token_expires_at' => $handoffTokenExpiresAt,
        ];

        $this->app->audit()->log(
            entity: 'module_portal',
            entityId: null,
            action: 'module.open',
            beforeData: null,
            afterData: null,
            metadata: $payload,
            userId: $userId > 0 ? $userId : null,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->app->events()->recordEvent(
            entity: 'module_portal',
            type: 'module.opened',
            payload: $payload,
            entityId: null,
            userId: $userId > 0 ? $userId : null
        );
    }

    private function auditModuleExit(
        Request $request,
        string $moduleSlug,
        string $exitReason,
        ?string $targetModuleSlug,
        ?string $targetPath
    ): void {
        $userId = (int) ($this->app->auth()->id() ?? 0);
        $payload = [
            'module_slug' => $moduleSlug,
            'reason' => $exitReason,
            'target_module_slug' => $targetModuleSlug,
            'target_path' => $targetPath,
        ];

        $this->app->audit()->log(
            entity: 'module_portal',
            entityId: null,
            action: 'module.exit',
            beforeData: null,
            afterData: null,
            metadata: $payload,
            userId: $userId > 0 ? $userId : null,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->app->events()->recordEvent(
            entity: 'module_portal',
            type: 'module.exited',
            payload: $payload,
            entityId: null,
            userId: $userId > 0 ? $userId : null
        );
    }
}
