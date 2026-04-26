<?php

declare(strict_types=1);

namespace Tests\Feature\Evolution;

use Illuminate\Support\Facades\Schema;

/**
 * Helper para criar APENAS as tabelas vizra_* em sqlite :memory:.
 *
 * Por que: as migrations legacy do UltimatePOS usam ALTER TABLE ... MODIFY COLUMN
 * (sintaxe MySQL-only), incompatível com sqlite. Rodar todas via RefreshDatabase
 * quebra antes de chegar nas nossas tabelas.
 *
 * Solução: criar só o que precisamos pros testes Evolution.
 */
class InitVizraSchema
{
    public static function run(): void
    {
        if (! Schema::hasTable('vizra_agents')) {
            require_once base_path('database/migrations/2026_04_26_200001_create_vizra_agents_table.php');
        }

        $migrations = [
            'database/migrations/2026_04_26_200001_create_vizra_agents_table.php' => 'vizra_agents',
            'database/migrations/2026_04_26_200002_create_vizra_messages_table.php' => 'vizra_messages',
            'database/migrations/2026_04_26_200003_create_vizra_traces_table.php' => 'vizra_traces',
            'database/migrations/2026_04_26_200004_create_vizra_memory_chunks_table.php' => 'vizra_memory_chunks',
            'database/migrations/2026_04_26_200005_create_vizra_evaluations_table.php' => 'vizra_evaluations',
            'database/migrations/2026_04_26_200006_create_vizra_eval_runs_table.php' => 'vizra_eval_runs',
        ];

        foreach ($migrations as $path => $table) {
            if (Schema::hasTable($table)) {
                continue;
            }

            $migration = require base_path($path);
            $migration->up();
        }
    }

    public static function tearDown(): void
    {
        $tables = [
            'vizra_eval_runs',
            'vizra_evaluations',
            'vizra_traces',
            'vizra_messages',
            'vizra_memory_chunks',
            'vizra_agents',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
}
