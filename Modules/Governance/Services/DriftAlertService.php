<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

/**
 * DriftAlertService — detecta drift entre SCOPE.md declarado e filesystem real
 * de cada `Modules/<X>/Http/Controllers/` (Constituição Art. 7 Module Charter).
 *
 * Mesma lógica do `bin/check-scope.php` mas em PHP runtime — pra UI Governance.
 *
 * Drift detection cron (Enforcement #5) ainda não persiste em `mcp_alertas`
 * categoria 'module_drift' — `getActiveDrifts` faz scan runtime do filesystem.
 *
 * @see Modules\Governance\Http\Controllers\DriftAlertsController
 */
class DriftAlertService
{
    /**
     * Controllers boilerplate que NÃO precisam aparecer no `contains[]` do SCOPE.
     */
    private const BOILERPLATE = [
        'Http/Controllers/DataController.php',
        'Http/Controllers/InstallController.php',
        'Http/Controllers/SuperadminController.php',
        'Http/Controllers/Controller.php',
    ];

    /**
     * Retorna drifts ativos detectados em runtime (controllers fora do contains[] do SCOPE).
     *
     * Estrutura retorno:
     *   [
     *     'report' => array<int, array{module, undeclared, undeclared_count, total_actual}>,
     *     'modules_without_scope' => array<int, string>,
     *     'modules_total' => int,
     *     'total_drift' => int,
     *   ]
     *
     * @param  int  $limit  Máximo de módulos com drift retornados (default 20)
     * @return Collection<string, mixed>
     */
    public function getActiveDrifts(int $limit = 20): Collection
    {
        $modulesPath = base_path('Modules');
        $modules = is_dir($modulesPath)
            ? array_filter(scandir($modulesPath), function ($d) use ($modulesPath) {
                return ! in_array($d, ['.', '..'], true) && is_dir($modulesPath . '/' . $d);
            })
            : [];

        $report = [];
        $totalDrift = 0;
        $modulesWithoutScope = [];

        foreach ($modules as $module) {
            $modulePath = $modulesPath . '/' . $module;
            $scopePath = $modulePath . '/SCOPE.md';

            if (! is_file($scopePath)) {
                $modulesWithoutScope[] = $module;
                continue;
            }

            $declared = $this->declaredControllers($scopePath);
            $actual = $this->actualControllers($modulePath);
            $undeclared = [];

            foreach ($actual as $ctrl) {
                $shortName = preg_replace('#^Http/Controllers/#', '', $ctrl);
                $shortName = preg_replace('#Controller\.php$#', 'Controller', $shortName);

                $matches = false;
                foreach ($declared as $d) {
                    if ($d === $shortName || str_ends_with($shortName, '/' . $d)) {
                        $matches = true;
                        break;
                    }
                }

                if (! $matches) {
                    $undeclared[] = $shortName;
                }
            }

            if (! empty($undeclared)) {
                $report[] = [
                    'module'           => $module,
                    'undeclared'       => $undeclared,
                    'undeclared_count' => count($undeclared),
                    'total_actual'     => count($actual),
                ];
                $totalDrift += count($undeclared);
            }
        }

        // Ordena drift por undeclared_count desc e trunca pelo limit.
        usort($report, fn ($a, $b) => $b['undeclared_count'] <=> $a['undeclared_count']);
        $report = array_slice($report, 0, $limit);

        return collect([
            'report'                => $report,
            'modules_without_scope' => array_values($modulesWithoutScope),
            'modules_total'         => count($modules),
            'total_drift'           => $totalDrift,
        ]);
    }

    /**
     * Alertas persistidos em `mcp_alertas` categoria 'module_drift'.
     *
     * Drift detection cron (Enforcement #5) + migration enum 'module_drift' ainda
     * pendentes (Fase 5+1) — retorna array vazio enquanto isso.
     *
     * @return array<int, object>
     */
    public function persistedAlerts(): array
    {
        // Hook pra cron job futuro — quando enum 'module_drift' for adicionado em
        // mcp_alertas, retornar DB::table('mcp_alertas')->where('category', 'module_drift')->get();
        return [];
    }

    /**
     * Lê controllers declarados no frontmatter YAML `contains[]` do SCOPE.md.
     *
     * @return array<int, string>
     */
    private function declaredControllers(string $scopePath): array
    {
        $content = @file_get_contents($scopePath);
        if (! $content) {
            return [];
        }
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) {
            return [];
        }

        try {
            $fm = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            report($e);
            \Log::error('DriftAlertService: YAML parse falhou em SCOPE.md', [
                'scope_path' => $scopePath,
                'exception'  => $e,
            ]);
            return [];
        }

        $declared = [];
        $contains = $fm['contains'] ?? [];
        if (! is_array($contains)) {
            return [];
        }

        foreach ($contains as $item) {
            if (! is_string($item)) {
                continue;
            }
            if (preg_match('#((?:[A-Z][a-zA-Z0-9]*/)*[A-Z][a-zA-Z0-9]*Controller)#', $item, $cm)) {
                $declared[] = $cm[1];
            }
        }

        // Aceita também controllers em drift_alerts (transitório).
        if (preg_match('/^drift_alerts:(.+?)(?=^[a-z_]+:|\z)/sm', $content, $dm)) {
            if (preg_match_all('/controller:\s*"([^"]+Controller)"/i', $dm[1], $dms)) {
                foreach ($dms[1] as $driftCtrl) {
                    $declared[] = $driftCtrl;
                }
            }
        }

        return $declared;
    }

    /**
     * Varre filesystem `Modules/<X>/Http/Controllers/` recursivo, ignorando boilerplate.
     *
     * @return array<int, string>
     */
    private function actualControllers(string $modulePath): array
    {
        $ctrlDir = $modulePath . '/Http/Controllers';
        if (! is_dir($ctrlDir)) {
            return [];
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ctrlDir, \FilesystemIterator::SKIP_DOTS)
        );
        $controllers = [];
        foreach ($iter as $file) {
            if ($file->getExtension() === 'php' && str_ends_with($file->getFilename(), 'Controller.php')) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($modulePath) + 1));
                if (! in_array($rel, self::BOILERPLATE, true)) {
                    $controllers[] = $rel;
                }
            }
        }
        sort($controllers);
        return $controllers;
    }
}
