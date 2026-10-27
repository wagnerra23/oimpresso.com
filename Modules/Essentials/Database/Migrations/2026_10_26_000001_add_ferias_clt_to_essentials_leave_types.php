<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca o tipo de licença que conta como FÉRIAS da CLT (US-PONTO-018).
 *
 * O HRM tem só "licença" genérica (`leave_type` texto livre). Para o Ponto saber quais licenças
 * aprovadas consomem o saldo de férias (CLT arts. 130, 134 e 137), o tipo precisa dizer isso por
 * dado, não pelo nome: adivinhar "Férias" no texto é o guard sintático que proibicoes §5 já matou.
 *
 * Aditiva e idempotente: default `false`, nenhum tipo existente muda de comportamento.
 */
class AddFeriasCltToEssentialsLeaveTypes extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('essentials_leave_types') || Schema::hasColumn('essentials_leave_types', 'ferias_clt')) {
            return;
        }

        Schema::table('essentials_leave_types', function (Blueprint $table) {
            $table->boolean('ferias_clt')->default(false)->after('leave_count_interval');
        });
    }

    public function down()
    {
        if (Schema::hasTable('essentials_leave_types') && Schema::hasColumn('essentials_leave_types', 'ferias_clt')) {
            Schema::table('essentials_leave_types', function (Blueprint $table) {
                $table->dropColumn('ferias_clt');
            });
        }
    }
}
