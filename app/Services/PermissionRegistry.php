<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * PermissionRegistry — auto-discovery de permissions declaradas pelos módulos.
 *
 * Cada módulo que quiser aparecer na tela Usuário 360° declara suas permissions
 * em `Modules/<Nome>/Resources/permissions.php` no formato:
 *
 *     return [
 *         'group' => 'NFSe',
 *         'icon'  => 'file-invoice',
 *         'permissions' => [
 *             ['key' => 'nfse.view', 'label' => 'Ver notas', 'risk' => 'low'],
 *             ['key' => 'nfse.emit', 'label' => 'Emitir nota', 'risk' => 'high',
 *              'requires' => ['nfse.view']],
 *         ],
 *     ];
 *
 * Risk levels: low | medium | high | critical
 *
 * Módulos sem `permissions.php` declarado caem num bucket "Sem registry"
 * — aparecem só pelas permissions Spatie já atribuídas, sem metadados.
 *
 * Cache: filesystem driver, TTL 5min, key `permission_registry:discover`.
 *
 * @see Modules/NFSe/Resources/permissions.php
 * @see Modules/Copiloto/Resources/permissions.php
 */
class PermissionRegistry
{
    private const CACHE_KEY = 'permission_registry:discover';
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Auto-discovery — escaneia Modules/<Nome>/Resources/permissions.php.
     *
     * @return Collection<string, array{group:string, icon:string, module:string, permissions:array}>
     */
    public function discover(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $modulesPath = base_path('Modules');
            if (! is_dir($modulesPath)) {
                return collect();
            }

            $result = collect();

            foreach (File::directories($modulesPath) as $moduleDir) {
                $moduleName = basename($moduleDir);
                $permissionsFile = $moduleDir . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'permissions.php';

                if (! file_exists($permissionsFile)) {
                    continue;
                }

                $declared = require $permissionsFile;

                if (! is_array($declared) || ! isset($declared['permissions'])) {
                    continue;
                }

                $result->put($moduleName, [
                    'module'      => $moduleName,
                    'group'       => $declared['group']       ?? $moduleName,
                    'icon'        => $declared['icon']        ?? 'box',
                    'permissions' => $declared['permissions'] ?? [],
                ]);
            }

            return $result;
        });
    }

    /**
     * Limpa o cache. Útil em testes ou quando se altera permissions.php.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Retorna estado de permissions agrupado por módulo, com info do que
     * o user TEM (Spatie) + risk + resumo. Inclui módulos declarados mesmo
     * que o user não tenha nenhuma permission deles (mostra ❌).
     *
     * Também adiciona um bucket virtual 'Sem registry' com permissions Spatie
     * que o user tem mas que nenhum módulo declarou — drift de governança.
     *
     * @return array<int, array{
     *     module:string, group:string, icon:string,
     *     total:int, granted:int, has_critical:bool, has_high:bool,
     *     permissions: array<int, array{key:string, label:string, risk:string,
     *                                    requires:array, granted:bool}>
     * }>
     */
    public function forUser(int $userId): array
    {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $userPerms = $user->getAllPermissions()->pluck('name')->all();
        $userPermsSet = array_flip($userPerms);

        $registry = $this->discover();
        $declaredKeys = [];

        $modules = [];

        foreach ($registry as $moduleName => $moduleData) {
            $perms = [];
            $granted = 0;
            $hasCritical = false;
            $hasHigh = false;

            foreach ($moduleData['permissions'] as $p) {
                $key = $p['key'] ?? null;
                if (! $key) {
                    continue;
                }
                $declaredKeys[$key] = true;

                $isGranted = isset($userPermsSet[$key]);
                if ($isGranted) {
                    $granted++;
                }

                $risk = $p['risk'] ?? 'low';
                if ($isGranted && $risk === 'critical') {
                    $hasCritical = true;
                }
                if ($isGranted && $risk === 'high') {
                    $hasHigh = true;
                }

                $perms[] = [
                    'key'      => $key,
                    'label'    => $p['label']    ?? $key,
                    'risk'     => $risk,
                    'requires' => $p['requires'] ?? [],
                    'granted'  => $isGranted,
                ];
            }

            $modules[] = [
                'module'       => $moduleName,
                'group'        => $moduleData['group'],
                'icon'         => $moduleData['icon'],
                'total'        => count($perms),
                'granted'      => $granted,
                'has_critical' => $hasCritical,
                'has_high'     => $hasHigh,
                'permissions'  => $perms,
            ];
        }

        // Bucket "Sem registry" — permissions que o user tem mas nenhum módulo declarou
        $orphanPerms = [];
        foreach ($userPerms as $name) {
            if (! isset($declaredKeys[$name])) {
                $orphanPerms[] = [
                    'key'      => $name,
                    'label'    => $name,
                    'risk'     => 'low',
                    'requires' => [],
                    'granted'  => true,
                ];
            }
        }

        if (! empty($orphanPerms)) {
            $modules[] = [
                'module'       => '_orphan',
                'group'        => 'Sem registry (legado/core)',
                'icon'         => 'help-circle',
                'total'        => count($orphanPerms),
                'granted'      => count($orphanPerms),
                'has_critical' => false,
                'has_high'     => false,
                'permissions'  => $orphanPerms,
            ];
        }

        return $modules;
    }

    /**
     * Drill-down: mesma estrutura de forUser() mas só pra um módulo.
     *
     * @return array|null
     */
    public function forUserModule(int $userId, string $module): ?array
    {
        $all = $this->forUser($userId);
        foreach ($all as $entry) {
            if (strcasecmp($entry['module'], $module) === 0) {
                return $entry;
            }
        }

        return null;
    }
}
