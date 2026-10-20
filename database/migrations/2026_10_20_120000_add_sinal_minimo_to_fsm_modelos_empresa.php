<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-FLX-28 · US-PCP-025 — "Produção só libera com o sinal pago", por empresa.
 *
 * Percentual mínimo do total da venda que precisa estar pago para a OS por item sair de
 * Aguardando liberação. Nulo = regra desligada (o Liberar segue como antes). De 0 a 100.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fsm_modelos_empresa') && ! Schema::hasColumn('fsm_modelos_empresa', 'sinal_minimo_pct')) {
            Schema::table('fsm_modelos_empresa', function (Blueprint $t) {
                $t->decimal('sinal_minimo_pct', 5, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fsm_modelos_empresa', 'sinal_minimo_pct')) {
            Schema::table('fsm_modelos_empresa', function (Blueprint $t) {
                $t->dropColumn('sinal_minimo_pct');
            });
        }
    }
};
