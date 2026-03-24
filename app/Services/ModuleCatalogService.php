<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Env;

final class ModuleCatalogService
{
    public const MODULE_REEMBOLSO = 'reembolso';
    public const MODULE_BPMN = 'bpmn';
    public const MODULE_USUARIOS = 'usuarios';
    public const MODULE_AUXILIO_TRANSPORTE = 'auxilio-transporte';
    public const MODULE_INDENIZACAO_FRONTEIRICO = 'indenizacao-fronteirico';
    public const MODULE_RECOMPOSICAO_ERARIO = 'recomposicao-erario';

    public function __construct(private ?Config $config = null)
    {
    }

    /**
     * @param array<string, mixed>|null $authUser
     * @return array<int, array<string, mixed>>
     */
    public function availableForUser(?array $authUser): array
    {
        $permissions = $this->extractPermissions($authUser);
        if ($permissions === []) {
            return [];
        }

        $modules = [];
        foreach ($this->catalog() as $module) {
            $resolved = $this->resolveAccessibleModule($permissions, $module);
            if ($resolved === null) {
                continue;
            }

            $slug = (string) ($module['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $module['entry_path'] = '/modules/' . $slug;
            $module['resolved_entry_path'] = (string) ($resolved['destination'] ?? '/home');
            $module['entry_type'] = (string) ($resolved['entry_type'] ?? 'internal_route');
            $module['access_mode'] = (string) ($resolved['access_mode'] ?? 'module_gate');
            unset(
                $module['module_permission'],
                $module['entry_candidates'],
                $module['route_prefixes'],
                $module['external_url'],
                $module['external_url_config_key']
            );

            $modules[] = $module;
        }

        usort($modules, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['sort_order'] ?? 0);
            $rightOrder = (int) ($right['sort_order'] ?? 0);

            if ($leftOrder === $rightOrder) {
                return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            }

            return $leftOrder <=> $rightOrder;
        });

        return $modules;
    }

    /**
     * @param array<string, mixed>|null $authUser
     * @return array{slug: string, name: string, entry_type: string, destination: string, access_mode: string}|null
     */
    public function moduleDestinationForUser(?array $authUser, string $moduleSlug): ?array
    {
        $permissions = $this->extractPermissions($authUser);
        if ($permissions === []) {
            return null;
        }

        foreach ($this->catalog() as $module) {
            if ((string) ($module['slug'] ?? '') !== $moduleSlug) {
                continue;
            }

            $resolved = $this->resolveAccessibleModule($permissions, $module);
            if ($resolved === null) {
                return null;
            }

            return [
                'slug' => (string) ($module['slug'] ?? ''),
                'name' => (string) ($module['name'] ?? 'Modulo'),
                'entry_type' => (string) ($resolved['entry_type'] ?? 'internal_route'),
                'destination' => (string) ($resolved['destination'] ?? '/home'),
                'access_mode' => (string) ($resolved['access_mode'] ?? 'module_gate'),
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $authUser
     */
    public function activeModuleSlugForPath(?array $authUser, string $path): ?string
    {
        $normalizedPath = $this->normalizePath($path);
        if ($normalizedPath === '/home') {
            return null;
        }

        $permissions = $this->extractPermissions($authUser);
        if ($permissions === []) {
            return null;
        }

        $accessibleModules = [];
        foreach ($this->catalog() as $module) {
            $resolved = $this->resolveAccessibleModule($permissions, $module);
            if ($resolved === null) {
                continue;
            }

            $slug = (string) ($module['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $accessibleModules[$slug] = $module;
        }

        if ($accessibleModules === []) {
            return null;
        }

        if (preg_match('#^/modules/([a-z0-9-]+)(?:/.*)?$#', $normalizedPath, $matches) === 1) {
            $slug = (string) ($matches[1] ?? '');
            if ($slug !== '' && array_key_exists($slug, $accessibleModules)) {
                return $slug;
            }
        }

        foreach ($accessibleModules as $slug => $module) {
            $routePrefixes = is_array($module['route_prefixes'] ?? null)
                ? $module['route_prefixes']
                : [];

            foreach ($routePrefixes as $prefix) {
                if (!is_string($prefix) || trim($prefix) === '') {
                    continue;
                }

                if ($this->pathMatchesPrefix($normalizedPath, $prefix)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $permissions
     * @param array<string, mixed> $module
     * @return array{entry_type: string, destination: string, access_mode: string}|null
     */
    private function resolveAccessibleModule(array $permissions, array $module): ?array
    {
        $entryType = trim((string) ($module['entry_type'] ?? 'internal_route'));
        if ($entryType === '') {
            $entryType = 'internal_route';
        }

        $modulePermission = trim((string) ($module['module_permission'] ?? ''));
        $hasModuleGate = $modulePermission !== '' && in_array($modulePermission, $permissions, true);

        if ($entryType === 'external_url') {
            if (!$hasModuleGate) {
                return null;
            }

            $externalUrl = $this->resolveExternalUrl($module);
            if ($externalUrl === null) {
                return null;
            }

            return [
                'entry_type' => 'external_url',
                'destination' => $externalUrl,
                'access_mode' => 'module_gate',
            ];
        }

        $internalPath = $this->resolveInternalEntryPath($permissions, $module);
        $hasLegacyAccess = $internalPath !== null;

        if (!$hasModuleGate && !$hasLegacyAccess) {
            return null;
        }

        if ($internalPath === null) {
            return null;
        }

        return [
            'entry_type' => 'internal_route',
            'destination' => $internalPath,
            'access_mode' => $hasModuleGate ? 'module_gate' : 'legacy_fallback',
        ];
    }

    /**
     * @param array<int, string> $permissions
     * @param array<string, mixed> $module
     */
    private function resolveInternalEntryPath(array $permissions, array $module): ?string
    {
        $entryCandidates = is_array($module['entry_candidates'] ?? null)
            ? $module['entry_candidates']
            : [];

        foreach ($entryCandidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $permission = trim((string) ($candidate['permission'] ?? ''));
            $path = trim((string) ($candidate['path'] ?? ''));
            if ($permission === '' || $path === '') {
                continue;
            }

            if (in_array($permission, $permissions, true)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function resolveExternalUrl(array $module): ?string
    {
        $externalUrl = trim((string) ($module['external_url'] ?? ''));
        if ($externalUrl === '') {
            $configKey = trim((string) ($module['external_url_config_key'] ?? ''));
            if ($configKey !== '') {
                $externalUrl = trim($this->readString($configKey, ''));
            }
        }

        if ($externalUrl === '') {
            return null;
        }

        $parts = parse_url($externalUrl);
        if ($parts === false) {
            return null;
        }

        $scheme = mb_strtolower(trim((string) ($parts['scheme'] ?? '')));
        $host = trim((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
            return null;
        }

        return $externalUrl;
    }

    /**
     * @param array<string, mixed>|null $authUser
     * @return array<int, string>
     */
    private function extractPermissions(?array $authUser): array
    {
        if ($authUser === null) {
            return [];
        }

        $permissions = [];
        $rawPermissions = $authUser['permissions'] ?? [];
        if (!is_array($rawPermissions)) {
            return [];
        }

        foreach ($rawPermissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }

            $normalized = trim($permission);
            if ($normalized === '') {
                continue;
            }

            $permissions[] = $normalized;
        }

        return array_values(array_unique($permissions));
    }

    private function readString(string $key, string $default = ''): string
    {
        if ($this->config !== null) {
            return trim((string) $this->config->get($key, $default));
        }

        $envKey = mb_strtoupper(str_replace('.', '_', $key));
        $value = Env::get($envKey);
        if ($value !== null && trim($value) !== '') {
            return trim($value);
        }

        if (str_starts_with($key, 'modules.external.')) {
            $suffix = mb_strtoupper(substr($key, strlen('modules.external.')));
            $value = Env::get('MODULE_' . $suffix);
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return $default;
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '//' ? '/' : $normalized;
    }

    private function pathMatchesPrefix(string $path, string $prefix): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedPrefix = $this->normalizePath($prefix);

        if ($normalizedPrefix === '/') {
            return $normalizedPath === '/';
        }

        if ($normalizedPath === $normalizedPrefix) {
            return true;
        }

        return str_starts_with($normalizedPath, $normalizedPrefix . '/');
    }

    /** @return array<int, array<string, mixed>> */
    private function catalog(): array
    {
        return [
            [
                'slug' => self::MODULE_REEMBOLSO,
                'name' => 'Reembolso',
                'description' => 'Operacao de reembolso com painel executivo, pessoas, financeiro e relatorios.',
                'module_permission' => 'module.reembolso.access',
                'entry_type' => 'internal_route',
                'cta_label' => 'Abrir modulo',
                'sort_order' => 10,
                'route_prefixes' => [
                    '/',
                    '/dashboard',
                    '/dashboard2',
                    '/global-search',
                    '/budget',
                    '/reports',
                    '/people',
                    '/organs',
                    '/mte-destinations',
                    '/document-types',
                    '/cost-items',
                    '/cdos',
                    '/invoices',
                    '/cost-mirrors',
                    '/office-templates',
                    '/office-documents',
                    '/process-meta',
                    '/bulk-imports',
                ],
                'entry_candidates' => [
                    ['permission' => 'dashboard.view', 'path' => '/dashboard'],
                    ['permission' => 'people.view', 'path' => '/people'],
                    ['permission' => 'invoice.view', 'path' => '/invoices'],
                    ['permission' => 'budget.view', 'path' => '/budget'],
                    ['permission' => 'report.view', 'path' => '/reports'],
                ],
            ],
            [
                'slug' => self::MODULE_BPMN,
                'name' => 'Desenho de processos (BPMN)',
                'description' => 'Definicao e manutencao dos fluxos BPMN do pipeline de pessoas.',
                'module_permission' => 'module.bpmn.access',
                'entry_type' => 'internal_route',
                'cta_label' => 'Abrir modulo',
                'sort_order' => 20,
                'route_prefixes' => [
                    '/pipeline-flows',
                    '/sla-alerts',
                ],
                'entry_candidates' => [
                    ['permission' => 'people.manage', 'path' => '/pipeline-flows'],
                ],
            ],
            [
                'slug' => self::MODULE_USUARIOS,
                'name' => 'Gestao de usuarios',
                'description' => 'Administracao de contas, papeis, permissoes e politicas de acesso.',
                'module_permission' => 'module.users.access',
                'entry_type' => 'internal_route',
                'cta_label' => 'Abrir modulo',
                'sort_order' => 30,
                'route_prefixes' => [
                    '/users',
                    '/security',
                    '/integrity',
                    '/ops/health-panel',
                    '/lgpd',
                ],
                'entry_candidates' => [
                    ['permission' => 'users.view', 'path' => '/users'],
                    ['permission' => 'users.manage', 'path' => '/users/roles'],
                ],
            ],
            [
                'slug' => self::MODULE_AUXILIO_TRANSPORTE,
                'name' => 'Gestao de auxilio transporte',
                'description' => 'Modulo externo para operacao de auxilio transporte.',
                'module_permission' => 'module.auxilio_transporte.access',
                'entry_type' => 'external_url',
                'cta_label' => 'Abrir modulo externo',
                'sort_order' => 40,
                'route_prefixes' => [],
                'external_url_config_key' => 'modules.external.auxilio_transporte_url',
                'entry_candidates' => [],
            ],
            [
                'slug' => self::MODULE_INDENIZACAO_FRONTEIRICO,
                'name' => 'Gestao de indenizacao fronteirica',
                'description' => 'Modulo externo para operacao de indenizacao por trabalho fronteirico.',
                'module_permission' => 'module.indenizacao_fronteirico.access',
                'entry_type' => 'external_url',
                'cta_label' => 'Abrir modulo externo',
                'sort_order' => 50,
                'route_prefixes' => [],
                'external_url_config_key' => 'modules.external.indenizacao_fronteirico_url',
                'entry_candidates' => [],
            ],
            [
                'slug' => self::MODULE_RECOMPOSICAO_ERARIO,
                'name' => 'Gestao de recomposicao ao erario',
                'description' => 'Modulo externo para recomposicao de valores ao erario.',
                'module_permission' => 'module.recomposicao_erario.access',
                'entry_type' => 'external_url',
                'cta_label' => 'Abrir modulo externo',
                'sort_order' => 60,
                'route_prefixes' => [],
                'external_url_config_key' => 'modules.external.recomposicao_erario_url',
                'entry_candidates' => [],
            ],
        ];
    }
}
