<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-COMVIS-004 · UC-FLX-09: o operador informa, ao concluir a etapa, o m² impresso e a tinta
 * gasta por canal (C/M/Y/K, em ml). Vão na linha `fim` do apontamento, gravadas no insert:
 * `pcp_apontamentos` é append-only (trigger), então nada aqui é atualizado depois.
 * A leitura automática do equipamento (UC-FLX-11) não usa estas colunas por enquanto (D-RIP).
 */
return new class extends Migration
{
    private const COLUNAS = ['m2_impresso', 'tinta_ml_c', 'tinta_ml_m', 'tinta_ml_y', 'tinta_ml_k'];

    public function up(): void
    {
        if (! Schema::hasTable('pcp_apontamentos') || Schema::hasColumn('pcp_apontamentos', 'm2_impresso')) {
            return;
        }
        Schema::table('pcp_apontamentos', function (Blueprint $t) {
            $t->decimal('m2_impresso', 12, 3)->nullable()->after('qtd_perdida')->comment('m² impresso na etapa (linha fim)');
            foreach (['c', 'm', 'y', 'k'] as $canal) {
                $t->decimal("tinta_ml_{$canal}", 10, 2)->nullable()->comment("tinta {$canal} em ml (linha fim)");
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pcp_apontamentos') && Schema::hasColumn('pcp_apontamentos', 'm2_impresso')) {
            Schema::table('pcp_apontamentos', fn (Blueprint $t) => $t->dropColumn(self::COLUNAS));
        }
    }
};
