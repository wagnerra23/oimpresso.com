<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Indexes pra queries tipicas em licenca_computador.
 *
 * Performance observada em produção (2026-04-23): listagem lenta.
 * Causa provavel: tabela sem index alem do PK e FK business_id.
 * Queries comuns:
 *   - WHERE business_id = X (ja indexado pela FK)
 *   - WHERE hd = ? (lookup por hardware id, sem index)
 *   - ORDER BY dt_ultimo_acesso DESC (sem index → filesort)
 *   - WHERE bloqueado = 1 (cardinalidade alta, vale compor)
 */
class AddIndexesToLicencaComputador extends Migration
{
    public function up()
    {
        // Idempotente — verifica antes de criar
        $indexes = collect(DB::select("SHOW INDEX FROM licenca_computador"))->pluck('Key_name')->unique();

        Schema::table('licenca_computador', function (Blueprint $table) use ($indexes) {
            if (! $indexes->contains('licenca_computador_hd_index')) {
                $table->index('hd');
            }
            if (! $indexes->contains('licenca_computador_dt_ultimo_acesso_index')) {
                $table->index('dt_ultimo_acesso');
            }
            if (! $indexes->contains('lcomp_business_dt_acesso_idx')) {
                $table->index(['business_id', 'dt_ultimo_acesso'], 'lcomp_business_dt_acesso_idx');
            }
            if (! $indexes->contains('lcomp_business_bloqueado_idx')) {
                $table->index(['business_id', 'bloqueado'], 'lcomp_business_bloqueado_idx');
            }
        });
    }

    public function down()
    {
        Schema::table('licenca_computador', function (Blueprint $table) {
            $table->dropIndex('licenca_computador_hd_index');
            $table->dropIndex('licenca_computador_dt_ultimo_acesso_index');
            $table->dropIndex('lcomp_business_dt_acesso_idx');
            $table->dropIndex('lcomp_business_bloqueado_idx');
        });
    }
}
