<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-REP-FSM-002 — Schema additivo `current_stage_id` em `repair_job_sheets`.
 *
 * Adiciona o ponteiro FSM canônico (ADR 0129) sem mexer em `status_id` legacy:
 * coexistência permite rollback per-business via flag `business.repair_fsm_enabled`
 * (a flag em si fica em US separada — esta migration só prepara o schema).
 *
 * Index composto `(business_id, current_stage_id)` pra queries do tipo
 * "OS no stage X de biz Y" (espelha `transactions_biz_stage_idx`).
 *
 * Idempotente — verifica existência antes de adicionar coluna/index.
 *
 * IMPORTANTE: a tabela real chama-se `repair_job_sheets` (prefix do módulo Repair),
 * apesar do model `JobSheet` viver em `Modules\Repair\Entities\`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_job_sheets')) {
            return; // ambiente Pest inline pode não ter a tabela
        }

        Schema::table('repair_job_sheets', function (Blueprint $t) {
            if (! Schema::hasColumn('repair_job_sheets', 'current_stage_id')) {
                $col = $t->unsignedBigInteger('current_stage_id')->nullable();
                // SQLite ignora ->after() silently; MySQL respeita.
                if (config('database.default') !== 'sqlite') {
                    $col->after('status_id');
                }
            }
        });

        // Index composto: criar fora do closure pra suportar SQLite + MySQL.
        $indexExists = false;
        try {
            $idx = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes('repair_job_sheets');
            $indexExists = isset($idx['job_sheets_biz_stage_idx']);
        } catch (\Throwable $e) {
            // Doctrine pode não estar disponível; silencia
        }

        if (! $indexExists) {
            try {
                Schema::table('repair_job_sheets', function (Blueprint $t) {
                    $t->index(['business_id', 'current_stage_id'], 'job_sheets_biz_stage_idx');
                });
            } catch (\Throwable $e) {
                // duplicata ou tabela inline simples — ignora
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_job_sheets')) {
            return;
        }

        Schema::table('repair_job_sheets', function (Blueprint $t) {
            try {
                $t->dropIndex('job_sheets_biz_stage_idx');
            } catch (\Throwable $e) {}

            if (Schema::hasColumn('repair_job_sheets', 'current_stage_id')) {
                $t->dropColumn('current_stage_id');
            }
        });
    }
};
