<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-031 — Adiciona is_critical em sale_stage_actions (fail-secure FSM).
 *
 * Default false preserva back-compat de actions existentes. Marcar como
 * true em seeds das actions de risco (cancelar_venda, voltar_para_orcamento,
 * iniciar_producao, etc) — ExecuteStageActionService passa a exigir role
 * mesmo quando sale_stage_action_roles está vazia (fail-secure).
 *
 * Refs: ADR 0129, CASOS-USO-PIPELINE-VENDAS.md §CU-02
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_stage_actions')) {
            return;
        }
        if (Schema::hasColumn('sale_stage_actions', 'is_critical')) {
            return; // idempotente
        }

        Schema::table('sale_stage_actions', function (Blueprint $t) {
            $t->boolean('is_critical')->default(false)->after('requires_confirmation');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_stage_actions')) {
            return;
        }
        if (! Schema::hasColumn('sale_stage_actions', 'is_critical')) {
            return;
        }

        Schema::table('sale_stage_actions', function (Blueprint $t) {
            $t->dropColumn('is_critical');
        });
    }
};
