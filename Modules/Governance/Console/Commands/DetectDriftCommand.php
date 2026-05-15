<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

/**
 * Drift detection cron — Mecanismo #5 ENFORCEMENT.md (Constituição Art. 7).
 *
 * Compara estado DECLARADO (Modules/<X>/SCOPE.md.contains[]) × estado OBSERVADO
 * (filesystem Modules/<X>/Http/Controllers/**) e persiste alertas idempotentes
 * em `mcp_alertas_eventos` (tipo=module_drift).
 *
 * Roda daily 06:15 BRT via app/Console/Kernel.php (após jana:health-check 06:00).
 *
 * Defesa em profundidade contra:
 *   - PRs antigos pré-SCOPE.md (legacy controllers nunca declarados)
 *   - Branches paralelas que mergearam contornando Mecanismo #3 (pre-commit hook)
 *   - Edits SSH direto em prod (violação Regra Primária "mexeu, registra")
 *   - Race conditions entre N agents Claude paralelos
 *
 * Mapeamento Gap-spec → schema canônico existente (mcp_alertas_eventos):
 *   category=module_drift     →  tipo='module_drift'
 *   severity=medium           →  severidade='medium'
 *   module=<X>                →  metadata->module=<X>
 *   detail=<descricao>        →  titulo + descricao
 *   metadata.*                →  metadata (json)
 *   status=open               →  status='aberto'
 *   idempotency               →  chave_idempotencia UNIQUE
 *
 * Multi-tenant Tier 0 (ADR 0093): drift de Module Charter é repo-wide (não
 * scopado por business_id) — `business_id` fica NULL nos eventos gerados.
 *
 * Refs:
 *   - memory/governance/ENFORCEMENT.md §2 #5
 *   - ADR 0079 Constituição 7 camadas
 *   - ADR 0086 Fase 5 MVP Governance
 *   - bin/check-scope.php (mesma lógica via shell, pra pre-commit hook)
 *   - Modules/Governance/Http/Controllers/DriftAlertsController.php (UI consume)
 *
 * Exit codes (compatíveis com cron alerting):
 *   0 = sem drift_added (clean)
 *   1 = pelo menos um drift_added detectado
 *
 * Uso:
 *   php artisan governance:detect-drift
 *   php artisan governance:detect-drift --json
 *   php artisan governance:detect-drift --module=Jana --dry-run
 */
class DetectDriftCommand extends Command
{
    protected $signature = 'governance:detect-drift
                            {--module= : Filtra por módulo PascalCase (ex: Jana)}
                            {--json : Output JSON em vez de tabela}
                            {--dry-run : Não persiste mcp_alertas_eventos, só reporta}';

    protected $description = 'Detecta drift Module Charter (SCOPE.md.contains[] × filesystem real)';

    /**
     * Boilerplate ignorado em scan (igual DriftAlertsController).
     */
    private const BOILERPLATE_CONTROLLERS = [
        'DataController',
        'InstallController',
        'SuperadminController',
        'Controller', // base class
    ];

    public function handle(): int
    {
        $modulesPath = base_path('Modules');
        if (! is_dir($modulesPath)) {
            $this->error("Diretório Modules/ não existe em base_path");

            return self::FAILURE;
        }

        $filterModule = $this->option('module');
        $dryRun = (bool) $this->option('dry-run');

        $report = [];
        $totalDriftAdded = 0;
        $totalAlertasCriados = 0;

        $modules = $this->discoverModules($modulesPath, $filterModule);

        foreach ($modules as $module => $scopePath) {
            $declared = $this->declaredControllers($scopePath);
            $observed = $this->observedControllers($modulesPath . DIRECTORY_SEPARATOR . $module);

            // drift_added: filesystem tem, SCOPE.md não declara (caso grave)
            $driftAdded = array_values(array_diff($observed, $declared));
            // drift_removed: SCOPE.md declara, filesystem não tem (warning leve — pode ser rename pendente)
            $driftRemoved = array_values(array_diff($declared, $observed));

            $alertIds = [];
            if (! empty($driftAdded) && ! $dryRun) {
                foreach ($driftAdded as $controller) {
                    $alertId = $this->persistirAlerta(
                        module: $module,
                        controller: $controller,
                        declaredCount: count($declared),
                        observedCount: count($observed),
                        modulePath: $modulesPath . DIRECTORY_SEPARATOR . $module,
                    );
                    if ($alertId !== null) {
                        $alertIds[] = $alertId;
                        $totalAlertasCriados++;
                    }
                }
            }

            if (! empty($driftAdded) || ! empty($driftRemoved)) {
                $report[] = [
                    'module' => $module,
                    'declared_count' => count($declared),
                    'observed_count' => count($observed),
                    'drift_added' => $driftAdded,
                    'drift_removed' => $driftRemoved,
                    'alert_ids' => $alertIds,
                ];
            }

            $totalDriftAdded += count($driftAdded);
        }

        $output = [
            'scanned_at' => now()->toIso8601String(),
            'modules_scanned' => count($modules),
            'modules_with_drift' => count($report),
            'total_drift_added' => $totalDriftAdded,
            'total_alertas_criados' => $totalAlertasCriados,
            'dry_run' => $dryRun,
            'modules' => $report,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($output);
        }

        // Log structured pra cron alerting (Log::channel('single') é convenção do Kernel.php)
        if ($totalDriftAdded > 0) {
            Log::channel('single')->warning('governance:detect-drift — drift detectado', [
                'modules_with_drift' => count($report),
                'total_drift_added' => $totalDriftAdded,
                'alertas_criados' => $totalAlertasCriados,
                'dry_run' => $dryRun,
            ]);
        }

        return $totalDriftAdded > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Descobre todos Modules/<X>/SCOPE.md, opcionalmente filtrando por nome.
     *
     * @return array<string, string> map [module_name => scope_path]
     */
    private function discoverModules(string $modulesPath, ?string $filter): array
    {
        $out = [];
        $dirs = scandir($modulesPath) ?: [];
        foreach ($dirs as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $moduleDir = $modulesPath . DIRECTORY_SEPARATOR . $entry;
            if (! is_dir($moduleDir)) {
                continue;
            }
            if ($filter !== null && $entry !== $filter) {
                continue;
            }
            $scopePath = $moduleDir . DIRECTORY_SEPARATOR . 'SCOPE.md';
            if (is_file($scopePath)) {
                $out[$entry] = $scopePath;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Parsea SCOPE.md.contains[] + drift_alerts[] e extrai nomes Controller.
     *
     * Item típico contains[]:
     *   "ChatController — UI chat principal"
     *   "Admin/CustosController — dashboard custos LLM"
     *
     * Regex captura "AlgumaCoisaController" (com prefix opcional Subfolder/).
     *
     * @return list<string>
     */
    private function declaredControllers(string $scopePath): array
    {
        $raw = @file_get_contents($scopePath);
        if ($raw === false || $raw === '') {
            return [];
        }
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $raw, $m)) {
            return [];
        }

        try {
            $fm = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            Log::warning("governance:detect-drift — YAML parse falhou em {$scopePath}", [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        $declared = [];

        // contains[] — fonte primária
        foreach ((array) ($fm['contains'] ?? []) as $item) {
            if (! is_string($item)) {
                continue;
            }
            if (preg_match('#((?:[A-Z][a-zA-Z0-9]*/)*[A-Z][a-zA-Z0-9]*Controller)#', $item, $cm)) {
                // Pega só o basename (última parte após /) pra match com filesystem scan
                $parts = explode('/', $cm[1]);
                $declared[] = end($parts);
            }
        }

        // drift_alerts[] (transitório — controllers em migração tolerados temporariamente)
        if (preg_match('/^drift_alerts:(.+?)(?=^[a-z_]+:|\z)/sm', $raw, $dm)) {
            if (preg_match_all('/controller:\s*"([^"]+Controller)"/i', $dm[1], $dms)) {
                foreach ($dms[1] as $driftCtrl) {
                    $parts = explode('/', $driftCtrl);
                    $declared[] = end($parts);
                }
            }
        }

        return array_values(array_unique($declared));
    }

    /**
     * Escaneia filesystem Modules/<X>/Http/Controllers/**\/*Controller.php.
     * Retorna basename (sem .php, sem path) excluindo BOILERPLATE.
     *
     * @return list<string>
     */
    private function observedControllers(string $modulePath): array
    {
        $ctrlDir = $modulePath . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers';
        if (! is_dir($ctrlDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ctrlDir, \FilesystemIterator::SKIP_DOTS)
        );

        $out = [];
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (! str_ends_with($name, 'Controller.php')) {
                continue;
            }
            $basename = substr($name, 0, -4); // strip .php
            if (in_array($basename, self::BOILERPLATE_CONTROLLERS, true)) {
                continue;
            }
            $out[] = $basename;
        }
        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * Persiste alerta idempotente em mcp_alertas_eventos.
     *
     * Idempotência via chave_idempotencia UNIQUE: rodar 2× no mesmo dia com
     * mesmo drift NÃO duplica. firstOrCreate pattern é seguro porque cron
     * roda 1× via onOneServer + withoutOverlapping (sem race condition).
     *
     * Retorna id do alerta (criado ou pré-existente), ou null se INSERT falhou.
     */
    private function persistirAlerta(
        string $module,
        string $controller,
        int $declaredCount,
        int $observedCount,
        string $modulePath,
    ): ?int {
        // Chave idempotente: 1 alerta por (module, controller) por DIA.
        // Drift novo amanhã com mesmo controller = MESMO alerta (não spam).
        // Se Wagner ack/resolve manualmente, próximo run em data NOVA cria novo.
        $diaUtc = now()->format('Y-m-d');
        $chave = sprintf('module_drift:%s:%s:%s', $module, $controller, $diaUtc);

        try {
            $existing = DB::table('mcp_alertas_eventos')
                ->where('chave_idempotencia', $chave)
                ->value('id');
            if ($existing !== null) {
                return (int) $existing;
            }

            $relPath = "Modules/{$module}/Http/Controllers/{$controller}.php";

            $id = DB::table('mcp_alertas_eventos')->insertGetId([
                'user_id' => null, // alerta global plataforma
                'business_id' => null, // module charter é repo-wide (ADR 0093 §Exceção repo-wide)
                'tipo' => 'module_drift',
                'severidade' => 'medium',
                'titulo' => sprintf(
                    'Drift Module Charter — %s/%s não declarado em SCOPE.md',
                    $module,
                    $controller
                ),
                'descricao' => sprintf(
                    "Controller %s.php existe em filesystem mas não está em Modules/%s/SCOPE.md.contains[].\n\n" .
                    "Declarado: %d | Observado: %d.\n\n" .
                    "Ação: editar Modules/%s/SCOPE.md adicionando o controller em contains[] " .
                    "OU mover/remover o controller. Ref: memory/governance/ENFORCEMENT.md §2 #5.",
                    $controller,
                    $module,
                    $declaredCount,
                    $observedCount,
                    $module,
                ),
                'chave_idempotencia' => $chave,
                'metadata' => json_encode([
                    'module' => $module,
                    'controller' => $controller,
                    'declared_count' => $declaredCount,
                    'observed_count' => $observedCount,
                    'file_path' => $relPath,
                    'enforcement_mecanismo' => 5,
                    'detected_at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'status' => 'aberto',
                'criado_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $id;
        } catch (\Throwable $e) {
            Log::channel('single')->error('governance:detect-drift — falha ao persistir alerta', [
                'module' => $module,
                'controller' => $controller,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function renderTable(array $output): void
    {
        $this->info("Drift Detection — {$output['scanned_at']}");
        $this->line(sprintf(
            'Módulos scaneados: %d | Com drift: %d | Drift added: %d | Alertas criados: %d%s',
            $output['modules_scanned'],
            $output['modules_with_drift'],
            $output['total_drift_added'],
            $output['total_alertas_criados'],
            $output['dry_run'] ? ' (dry-run)' : ''
        ));

        if (count($output['modules']) === 0) {
            $this->line('  ✓ Nenhum drift detectado');

            return;
        }

        foreach ($output['modules'] as $row) {
            $this->newLine();
            $this->warn(sprintf(
                '%s — declared=%d observed=%d',
                $row['module'],
                $row['declared_count'],
                $row['observed_count']
            ));
            if (! empty($row['drift_added'])) {
                $this->line('  Drift added (filesystem novo, SCOPE.md ausente):');
                foreach ($row['drift_added'] as $ctrl) {
                    $this->line("    + {$ctrl}");
                }
            }
            if (! empty($row['drift_removed'])) {
                $this->line('  Drift removed (SCOPE.md declara, filesystem ausente):');
                foreach ($row['drift_removed'] as $ctrl) {
                    $this->line("    - {$ctrl}");
                }
            }
        }
    }
}
