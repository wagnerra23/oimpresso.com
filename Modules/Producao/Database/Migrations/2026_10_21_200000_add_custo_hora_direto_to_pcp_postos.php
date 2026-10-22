<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custo-hora direto do posto, separado da parcela fixa do custeio RKW (US-FIN-070 · decisão [W] 2026-10-21).
 *
 * `custo_hora` segue sendo o custo que o apontamento copia. Quando o custeio RKW aplica a parcela fixa,
 * o direto (o que foi digitado) fica aqui, e o sugerido passa a ser sempre direto + parcela do mês, sem
 * somar a parcela de novo a cada aplicação. Null = nada aplicado: o `custo_hora` é o próprio direto.
 * Sem backfill: em 2026-10-21 a produção tinha 0 postos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_postos') && ! Schema::hasColumn('pcp_postos', 'custo_hora_direto')) {
            Schema::table('pcp_postos', function (Blueprint $t) {
                $t->decimal('custo_hora_direto', 12, 2)->nullable()->after('custo_hora');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pcp_postos') && Schema::hasColumn('pcp_postos', 'custo_hora_direto')) {
            Schema::table('pcp_postos', fn (Blueprint $t) => $t->dropColumn('custo_hora_direto'));
        }
    }
};
