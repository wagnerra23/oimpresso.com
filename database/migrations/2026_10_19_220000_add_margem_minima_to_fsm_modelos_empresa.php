<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-FLX-35 · US-SELL-069 — margem mínima do orçamento, por empresa (aba Fluxos da empresa).
 *
 * Nula = regra desligada: nenhuma conversão é bloqueada. Em %, de 0 a 100.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fsm_modelos_empresa') && ! Schema::hasColumn('fsm_modelos_empresa', 'margem_minima_pct')) {
            Schema::table('fsm_modelos_empresa', function (Blueprint $t) {
                $t->decimal('margem_minima_pct', 5, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fsm_modelos_empresa', 'margem_minima_pct')) {
            Schema::table('fsm_modelos_empresa', function (Blueprint $t) {
                $t->dropColumn('margem_minima_pct');
            });
        }
    }
};
