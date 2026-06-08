<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * manufacturing:health — Health check Manufacturing (D9.c — Wave 17 saturação 97%).
 *
 * Sinais mínimos:
 *   1. recipes_table_present — `mfg_recipes` existe
 *   2. ingredients_table_present — `mfg_recipe_ingredients` existe
 *   3. production_24h — transactions type=production_purchase nas últimas 24h
 *   4. recipes_active — recipes com ingredientes (avalia integridade BOM)
 *
 * Multi-tenant Tier 0 (ADR 0093): leitura agregada cross-tenant pra dashboard
 * superadmin. NUNCA escreve. business_id é dimensão de agregação, não filtro.
 *
 * Exit code:
 *   - Sem --alert: sempre 0 (info-only)
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se OK
 *
 * NOTA Tier 0: NUNCA `--verbose` (colide Symfony — usar `--detail` se precisar).
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see Modules\Brief\Console\Commands\BriefHealthCommand (pattern referência)
 */
class ManufacturingHealthCommand extends Command
{
    protected $signature = 'manufacturing:health
        {--alert : Exit code 2 se FAIL, 1 se WARN (cron + monitoring)}
        {--json : Output JSON estruturado em vez de tabela}';

    protected $description = 'Health check Manufacturing — 4 sinais (ADR 0155 D9.c, Wave 17).';

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkRecipesTable(),
            $this->checkIngredientsTable(),
            $this->checkProduction24h(),
            $this->checkRecipesActive(),
        ];

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            $this->line(json_encode([
                'timestamp' => now()->toIso8601String(),
                'module'    => 'Manufacturing',
                'checks'    => $checks,
                'summary'   => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->line('');
        $this->info('Manufacturing Health Check — ' . now()->toDateTimeString());
        $this->newLine();

        $rows = collect($checks)->map(fn ($c) => [
            $c['name'],
            $c['status'],
            mb_strimwidth((string) $c['details'], 0, 80, '…'),
            mb_strimwidth((string) $c['recommendation'], 0, 80, '…'),
        ])->toArray();

        $this->table(['Check', 'Status', 'Details', 'Recommendation'], $rows);
        $this->newLine();
        $line = "{$summary['ok']} OK, {$summary['warn']} WARN, {$summary['fail']} FAIL de {$summary['total']} checks";
        $summary['fail'] > 0 ? $this->error("  Resumo: {$line}")
            : ($summary['warn'] > 0 ? $this->warn("  Resumo: {$line}") : $this->info("  Resumo: {$line}"));

        return $this->resolveExitCode($summary, $alert);
    }

    private function checkRecipesTable(): array
    {
        return Schema::hasTable('mfg_recipes')
            ? $this->mk('recipes_table_present', 'OK', 'Tabela mfg_recipes presente', 'Schema canônico aplicado.')
            : $this->mk('recipes_table_present', 'FAIL', 'Tabela mfg_recipes ausente', 'Rode `php artisan migrate` em Modules/Manufacturing.');
    }

    private function checkIngredientsTable(): array
    {
        return Schema::hasTable('mfg_recipe_ingredients')
            ? $this->mk('ingredients_table_present', 'OK', 'Tabela mfg_recipe_ingredients presente', 'Schema canônico aplicado.')
            : $this->mk('ingredients_table_present', 'FAIL', 'Tabela ausente', 'Rode migrate.');
    }

    private function checkProduction24h(): array
    {
        if (! Schema::hasTable('transactions')) {
            return $this->mk('production_24h', 'WARN', 'Tabela transactions ausente', 'Schema core não aplicado.');
        }
        $count = (int) DB::table('transactions')
            ->where('type', 'production_purchase')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($count === 0) {
            return $this->mk('production_24h', 'WARN', '0 produções em 24h cross-tenant', 'Esperado para módulo opt-in; verifique se algum business deveria estar usando.');
        }
        return $this->mk('production_24h', 'OK', "{$count} produções em 24h cross-tenant", 'Produção ativa.');
    }

    private function checkRecipesActive(): array
    {
        if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients')) {
            return $this->mk('recipes_active', 'WARN', 'Schema parcial', 'Rode migrate completo.');
        }
        $recipesTotal = (int) DB::table('mfg_recipes')->count();
        if ($recipesTotal === 0) {
            return $this->mk('recipes_active', 'WARN', 'Nenhuma recipe cadastrada', 'Esperado em ambientes novos.');
        }
        $recipesComIng = (int) DB::table('mfg_recipes')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('mfg_recipe_ingredients')
                ->whereColumn('mfg_recipe_ingredients.mfg_recipe_id', 'mfg_recipes.id'))
            ->count();
        $orfas = $recipesTotal - $recipesComIng;
        if ($orfas > 0 && ($orfas / max(1, $recipesTotal)) > 0.5) {
            return $this->mk('recipes_active', 'FAIL', "{$orfas}/{$recipesTotal} recipes sem ingredientes (>50%)", 'BOM corrompido; investigue mfg_recipe_ingredients FK.');
        }
        if ($orfas > 0) {
            return $this->mk('recipes_active', 'WARN', "{$orfas}/{$recipesTotal} recipes sem ingredientes", 'Investigue rascunhos abandonados.');
        }
        return $this->mk('recipes_active', 'OK', "{$recipesTotal} recipes ativas com BOM", 'BOM íntegro.');
    }

    private function mk(string $name, string $status, string $details, string $recommendation): array
    {
        return compact('name', 'status', 'details', 'recommendation');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) return 0;
        if ($summary['fail'] > 0) return 2;
        if ($summary['warn'] > 0) return 1;
        return 0;
    }
}
