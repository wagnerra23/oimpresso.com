<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ação do motor FSM que só executa com motivo ("Marcar perdido": Enviado → Perdido, "Motivo
 * obrigatório" no processo Orçamento e venda do protótipo Fluxos e regras — ProcessosDoPrototipo).
 *
 * Default false: as ações que já existem seguem aceitando motivo opcional.
 * ExecuteStageActionService recusa a execução sem `payload.motivo` quando a coluna é true.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_stage_actions') || Schema::hasColumn('sale_stage_actions', 'exige_motivo')) {
            return;
        }

        Schema::table('sale_stage_actions', function (Blueprint $t) {
            $t->boolean('exige_motivo')->default(false)->after('is_critical');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_stage_actions') || ! Schema::hasColumn('sale_stage_actions', 'exige_motivo')) {
            return;
        }

        Schema::table('sale_stage_actions', function (Blueprint $t) {
            $t->dropColumn('exige_motivo');
        });
    }
};
