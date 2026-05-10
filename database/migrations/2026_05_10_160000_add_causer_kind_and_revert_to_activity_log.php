<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-AUDIT-005 (Sprint F2) — adiciona 5 colunas + 2 indices em activity_log
 * pra suportar:
 *   - Causer dual (user vs IA): causer_kind ENUM + agent_run_id
 *   - Revert metadata: reverted_at, reverted_by_user_id, revert_reason
 *
 * Migration ADITIVA (sem rename/drop existentes). Default causer_kind='user'
 * aplica em rows existentes — zero-downtime.
 *
 * Refs:
 *   - ADR 0127 §schema (Modules/Auditoria UI + undo)
 *   - ADR 0093 (multi-tenant Tier 0 — business_id ja indexado, mantido)
 *
 * Reversivel via down() — drop indexes + drop columns.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Causer dual: distingue acao humana de acao da Jana via tool MCP
            $table->enum('causer_kind', ['user', 'agent', 'system', 'api'])
                ->default('user')
                ->after('causer_type');

            // Quando causer_kind='agent', referencia o run da Jana que originou a acao
            $table->unsignedBigInteger('agent_run_id')
                ->nullable()
                ->after('causer_kind');

            // Revert metadata — preenchido quando linha original foi revertida
            $table->timestamp('reverted_at')->nullable()->after('properties');
            $table->unsignedBigInteger('reverted_by_user_id')
                ->nullable()
                ->after('reverted_at');
            $table->string('revert_reason', 500)
                ->nullable()
                ->after('reverted_by_user_id');

            // Indices compostos pra queries comuns na UI /auditoria
            // (filtro por business + causer_kind + ordenacao por data)
            $table->index(
                ['business_id', 'causer_kind', 'created_at'],
                'idx_business_kind_created'
            );

            // Filtro "ja revertida ou nao" por subject (drill-down de auditoria)
            $table->index(
                ['subject_type', 'subject_id', 'reverted_at'],
                'idx_subject_reverted'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('idx_business_kind_created');
            $table->dropIndex('idx_subject_reverted');

            $table->dropColumn([
                'causer_kind',
                'agent_run_id',
                'reverted_at',
                'reverted_by_user_id',
                'revert_reason',
            ]);
        });
    }
};
