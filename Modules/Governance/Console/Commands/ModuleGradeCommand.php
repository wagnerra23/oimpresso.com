<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Governance\Services\ModuleGradeService;

/**
 * Avalia maturidade de Modules/<X>/ via rubrica oficial module-grade-v1 (ADR 0153).
 *
 * Uso:
 *   php artisan module:grade Crm                # Avalia 1 módulo + breakdown
 *   php artisan module:grade --all              # Avalia todos + tabela ranqueada
 *   php artisan module:grade --all --json       # Output JSON pra dashboard/CI
 *   php artisan module:grade Crm --evolve       # Mostra batch de tasks-create sugeridas
 *
 * Pesos v3 (9 dims): D1 MT 25 / D2 Pest 17 / D3 Doc 12 / D4 Arq 17 / D5 Cli 12 / D6 Perf 10 / D7 LGPD 10 / D8 Sec 8 / D9 Obs 7.
 * Buckets: Excelente 80+ / Bom 60-79 / Médio 40-59 / Crítico 20-39 / Embrião <20.
 *
 * NOTA: NÃO usa `--verbose` (reservado Symfony Console — colide). Usa `--detail`.
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */
class ModuleGradeCommand extends Command
{
    protected $signature = 'module:grade
                            {name? : Nome do módulo (ex: Crm). Vazio + --all avalia todos}
                            {--all : Avalia todos os módulos detectados em Modules/}
                            {--json : Output JSON (machine-readable, sem cores)}
                            {--detail : Mostra breakdown completo das 9 dimensões v3}
                            {--evolve : Mostra batch de tasks-create sugeridas pra fechar gaps top 5}';

    protected $description = 'Avalia maturidade de Modules/<X>/ via rubrica oficial module-grade-v1 (ADR 0153)';

    public function handle(ModuleGradeService $service): int
    {
        $name = $this->argument('name');
        $all = (bool) $this->option('all');
        $json = (bool) $this->option('json');
        $detail = (bool) $this->option('detail');
        $evolve = (bool) $this->option('evolve');

        if (! $name && ! $all) {
            $this->error('Forneça {name} ou --all. Ex: `php artisan module:grade Crm` ou `php artisan module:grade --all`');
            return self::INVALID;
        }

        if ($all) {
            return $this->handleAll($service, $json);
        }

        try {
            $grade = $service->gradeModule($name);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($json) {
            $this->line(json_encode($grade, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->printModule($grade, $detail);

        if ($evolve) {
            $this->printEvolve($grade);
        }

        return self::SUCCESS;
    }

    private function handleAll(ModuleGradeService $service, bool $json): int
    {
        $grades = $service->gradeAllModules();

        if ($json) {
            $this->line($grades->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $rows = $grades->map(fn ($g) => [
            'Módulo'   => $g['module'],
            'Nota'     => $g['score'],
            'Bucket'   => $g['bucket'],
            'D1 MT'    => $this->dimCell($g, 'multi_tenant'),
            'D2 Pest'  => $this->dimCell($g, 'pest_coverage'),
            'D3 Doc'   => $this->dimCell($g, 'documentation'),
            'D4 Arq'   => $this->dimCell($g, 'architecture'),
            'D5 Cli'   => $this->dimCell($g, 'client_real'),
            'D6 Perf'  => $this->dimCell($g, 'performance'),
            'D7 LGPD'  => $this->dimCell($g, 'lgpd'),
            'D8 Sec'   => $this->dimCell($g, 'security'),
            'D9 Obs'   => $this->dimCell($g, 'observability'),
        ])->all();

        $this->table(
            ['Módulo', 'Nota', 'Bucket', 'D1 MT', 'D2 Pest', 'D3 Doc', 'D4 Arq', 'D5 Cli', 'D6 Perf', 'D7 LGPD', 'D8 Sec', 'D9 Obs'],
            $rows,
        );

        $avg = round($grades->avg('score'), 1);
        $count = $grades->count();
        $byBucket = $grades->groupBy('bucket')->map->count();

        $this->newLine();
        $this->info("Média projeto: {$avg} pts ({$count} módulos)");
        $this->line("Distribuição por bucket: " . $byBucket->map(fn ($v, $k) => "{$k}={$v}")->implode(' · '));

        return self::SUCCESS;
    }

    private function printModule(array $grade, bool $detail): void
    {
        $bucketColor = match ($grade['bucket']) {
            'Excelente' => 'green',
            'Bom'       => 'cyan',
            'Médio'     => 'yellow',
            'Crítico'   => 'red',
            'Embrião'   => 'red',
            default     => 'default',
        };

        $this->newLine();
        $this->line("<fg=white;options=bold>Modules/{$grade['module']}</>");
        $this->line("Nota: <fg={$bucketColor};options=bold>{$grade['score']}/100</> · Bucket: <fg={$bucketColor}>{$grade['bucket']}</>");
        $this->line("Avaliado em: {$grade['evaluated_at']}");
        $this->newLine();

        $rows = [];
        foreach ($grade['dimensions'] as $key => $dim) {
            $label = match ($key) {
                'multi_tenant'  => 'D1 Multi-tenant Tier 0',
                'pest_coverage' => 'D2 Pest cobertura',
                'documentation' => 'D3 Documentação canônica',
                'architecture'  => 'D4 Maturidade arquitetura',
                'client_real'   => 'D5 Cliente real',
                'performance'   => 'D6 Performance',
                'lgpd'          => 'D7 LGPD',
                'security'      => 'D8 Segurança',
                'observability' => 'D9 Observabilidade',
                default         => $key,
            };
            $rows[] = [$label, "{$dim['score']}/{$dim['max']}", "peso {$dim['weight']}"];
        }
        $this->table(['Dimensão', 'Score', 'Peso'], $rows);

        if ($detail) {
            $this->newLine();
            $this->line("<fg=white;options=bold>Breakdown completo:</>");
            foreach ($grade['dimensions'] as $key => $dim) {
                $this->line("<fg=cyan>  {$key}:</>");
                foreach ($dim['breakdown'] as $item) {
                    $color = $item['score'] === $item['max'] ? 'green' : ($item['score'] > 0 ? 'yellow' : 'red');
                    $this->line(sprintf(
                        "    <fg={$color}>%s</> %s — %s",
                        "[{$item['score']}/{$item['max']}]",
                        $item['key'] ?? '',
                        $item['evidence']
                    ));
                }
            }
        }

        if (! empty($grade['gaps'])) {
            $this->newLine();
            $this->line("<fg=white;options=bold>Top gaps (perda de pontos ordenada):</>");
            foreach (array_slice($grade['gaps'], 0, 5) as $g) {
                $this->line(sprintf(
                    "  <fg=red>-%d pts</> <fg=cyan>%s</> [%s] %s",
                    $g['lost'], $g['key'], $g['priority'], $g['desc']
                ));
            }
        }
    }

    /**
     * Formata célula score/max de uma dimensão.
     *
     * Backward-compat: se a dim não existir no output (Service v1 antigo
     * sem D6-D9 v3), retorna "—" em vez de quebrar.
     */
    private function dimCell(array $grade, string $key): string
    {
        $dim = $grade['dimensions'][$key] ?? null;
        if (! $dim || ! isset($dim['score'], $dim['max'])) {
            return '—';
        }
        return "{$dim['score']}/{$dim['max']}";
    }

    private function printEvolve(array $grade): void
    {
        $this->newLine();
        $this->line("<fg=white;options=bold>Batch de tasks-create sugeridas (botão Evoluir):</>");
        $this->newLine();
        foreach ($grade['evolve_tasks'] as $i => $task) {
            $n = $i + 1;
            $this->line("<fg=cyan>Task #{$n}</> [{$task['priority']}] · estimativa {$task['estimate']}");
            $this->line("  Título: {$task['title']}");
            $this->line("  Módulo: {$task['module']}");
            $this->line("  Gap ref: {$task['gap_ref']}");
            $this->line("  Racional: {$task['rationale']}");
            $this->newLine();
        }
        $this->line("<fg=yellow>Copiar como markdown e colar no Claude Code pra criar via `tasks-create` MCP.</>");
    }
}
