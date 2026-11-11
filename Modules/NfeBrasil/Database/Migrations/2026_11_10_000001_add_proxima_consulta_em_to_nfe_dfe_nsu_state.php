<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lote 1.3 da auditoria do NfeBrasil (N4-05) · Distribuição DF-e.
 *
 * Depois de cStat 137 ("nenhum documento") ou de alcançar o maxNSU, a SEFAZ pede 1 h até a próxima
 * consulta; insistir antes devolve cStat 656 (consumo indevido) e bloqueia o CNPJ por 1 h.
 * `proxima_consulta_em` guarda esse prazo. Nulo = como hoje (só o intervalo de 5 min).
 *
 * Só acrescenta (ADR 0093 G8): coluna nula, o cursor NSU não é tocado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_dfe_nsu_state') && ! Schema::hasColumn('nfe_dfe_nsu_state', 'proxima_consulta_em')) {
            Schema::table('nfe_dfe_nsu_state', function (Blueprint $t) {
                $t->timestamp('proxima_consulta_em')->nullable()->after('ultimo_check_em')
                    ->comment('Antes disto não consultar a SEFAZ (1 h após cStat 137/656 — NT 2014.002)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nfe_dfe_nsu_state', 'proxima_consulta_em')) {
            Schema::table('nfe_dfe_nsu_state', function (Blueprint $t) {
                $t->dropColumn('proxima_consulta_em');
            });
        }
    }
};
