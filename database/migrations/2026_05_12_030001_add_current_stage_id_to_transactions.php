<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wire-up UI FSM — adiciona current_stage_id em transactions.
 *
 * Coluna NULLABLE preserva vendas legadas (sem migrar todas pra processo
 * FSM agora). Backfill é manual/incremental via comando artisan futuro
 * OU via UI ("Iniciar pipeline FSM" botão em venda específica).
 *
 * Trait App\Domain\Fsm\Concerns\GuardsFsmTransitions é adicionado ao
 * Transaction model — UPDATE direto em current_stage_id sem passar pelo
 * ExecuteStageActionService lança UnauthorizedActionException.
 *
 * Refs: ADR 0129 §SideEffects, PR #617 (US-SELL-032 Observer)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }
        if (Schema::hasColumn('transactions', 'current_stage_id')) {
            return; // idempotente
        }

        Schema::table('transactions', function (Blueprint $t) {
            $t->unsignedBigInteger('current_stage_id')->nullable()->after('status');
            $t->index(['business_id', 'current_stage_id'], 'transactions_biz_stage_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }
        if (! Schema::hasColumn('transactions', 'current_stage_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $t) {
            $t->dropIndex('transactions_biz_stage_idx');
            $t->dropColumn('current_stage_id');
        });
    }
};
