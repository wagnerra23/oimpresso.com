<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotfix: action_id pode ser NULL em sale_stage_history.
 *
 * Caso de uso: SaleFsmActionController::startPipeline insere entry
 * "Pipeline iniciado" sem action (não veio de transição cadastrada,
 * só seta current_stage_id inicial mapeando status legacy).
 *
 * Bug confirmado em prod (PR #642):
 *   SQLSTATE[23000]: 1048 Column 'action_id' cannot be null
 *
 * Usa DB::statement (não Blueprint->change) pra evitar dependência
 * doctrine/dbal. Idempotente: checa COLUMN_TYPE antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_stage_history')) {
            return;
        }

        $col = DB::selectOne(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'sale_stage_history'
               AND COLUMN_NAME = 'action_id'"
        );

        if ($col === null || $col->IS_NULLABLE === 'YES') {
            return;
        }

        DB::statement('ALTER TABLE sale_stage_history MODIFY action_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_stage_history')) {
            return;
        }

        $orphans = DB::table('sale_stage_history')->whereNull('action_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "Cannot rollback: {$orphans} row(s) em sale_stage_history com action_id NULL. " .
                'Limpar antes (DELETE) ou backfillar.'
            );
        }

        DB::statement('ALTER TABLE sale_stage_history MODIFY action_id BIGINT UNSIGNED NOT NULL');
    }
};
