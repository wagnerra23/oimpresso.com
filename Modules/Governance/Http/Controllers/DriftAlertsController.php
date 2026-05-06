<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * Drift alerts — Constituição Art. 7 (Module Charter).
 *
 * Lê SCOPE.md de cada módulo + filesystem real de Modules/<X>/Http/Controllers/
 * e detecta divergência (controllers fora do contains[] declarado).
 *
 * Mesma lógica do bin/check-scope.php mas em PHP runtime — pra UI.
 *
 * Drift detection cron (Enforcement #5) vai persistir em mcp_alertas;
 * esta tela lê tanto runtime scan quanto alertas históricos.
 */
class DriftAlertsController extends Controller
{
    private const BOILERPLATE = [
        'Http/Controllers/DataController.php',
        'Http/Controllers/InstallController.php',
        'Http/Controllers/SuperadminController.php',
        'Http/Controllers/Controller.php',
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $modulesPath = base_path('Modules');
        $modules = is_dir($modulesPath) ? array_filter(scandir($modulesPath), function ($d) use ($modulesPath) {
            return !in_array($d, ['.', '..']) && is_dir($modulesPath . '/' . $d);
        }) : [];

        $report = [];
        $totalDrift = 0;
        $modulesWithoutScope = [];

        foreach ($modules as $module) {
            $modulePath = $modulesPath . '/' . $module;
            $scopePath = $modulePath . '/SCOPE.md';

            if (!is_file($scopePath)) {
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

                if (!$matches) {
                    $undeclared[] = $shortName;
                }
            }

            if (!empty($undeclared)) {
                $report[] = [
                    'module'       => $module,
                    'undeclared'   => $undeclared,
                    'undeclared_count' => count($undeclared),
                    'total_actual' => count($actual),
                ];
                $totalDrift += count($undeclared);
            }
        }

        // Alertas persistidos — drift detection cron job (Enforcement #5) ainda não roda;
        // tabela mcp_alertas é pra outras categorias (cota_excedida, tool_destrutiva,
        // ip_suspeito, taxa_errors, cliente_externo). Adicionar 'module_drift' ao enum
        // exige migration + ADR — fica pra Fase 5+1.
        $persistedAlerts = [];

        return Inertia::render('governance/DriftAlerts', [
            'kpis' => [
                'total_drift'           => $totalDrift,
                'modules_with_drift'    => count($report),
                'modules_without_scope' => count($modulesWithoutScope),
                'modules_total'         => count($modules),
            ],
            'report'                => $report,
            'modules_without_scope' => array_values($modulesWithoutScope),
            'persisted_alerts'      => $persistedAlerts,
        ]);
    }

    private function declaredControllers(string $scopePath): array
    {
        $content = @file_get_contents($scopePath);
        if (!$content) return [];
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) return [];

        try {
            $fm = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            return [];
        }

        $declared = [];
        $contains = $fm['contains'] ?? [];
        if (!is_array($contains)) return [];

        foreach ($contains as $item) {
            if (!is_string($item)) continue;
            if (preg_match('#((?:[A-Z][a-zA-Z0-9]*/)*[A-Z][a-zA-Z0-9]*Controller)#', $item, $cm)) {
                $declared[] = $cm[1];
            }
        }

        // Aceita também controllers em drift_alerts (transitório)
        if (preg_match('/^drift_alerts:(.+?)(?=^[a-z_]+:|\z)/sm', $content, $dm)) {
            if (preg_match_all('/controller:\s*"([^"]+Controller)"/i', $dm[1], $dms)) {
                foreach ($dms[1] as $driftCtrl) {
                    $declared[] = $driftCtrl;
                }
            }
        }

        return $declared;
    }

    private function actualControllers(string $modulePath): array
    {
        $ctrlDir = $modulePath . '/Http/Controllers';
        if (!is_dir($ctrlDir)) return [];

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ctrlDir, \FilesystemIterator::SKIP_DOTS)
        );
        $controllers = [];
        foreach ($iter as $file) {
            if ($file->getExtension() === 'php' && str_ends_with($file->getFilename(), 'Controller.php')) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($modulePath) + 1));
                if (!in_array($rel, self::BOILERPLATE, true)) {
                    $controllers[] = $rel;
                }
            }
        }
        sort($controllers);
        return $controllers;
    }
}
