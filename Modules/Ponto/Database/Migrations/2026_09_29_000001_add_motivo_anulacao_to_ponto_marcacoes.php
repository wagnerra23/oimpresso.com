<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo da anulação em TEXTO ([W] 2026-09-29, thread 06).
 *
 * Até aqui o Marcacao::anular() guardava só um md5 do motivo, dentro do `dispositivo_id` — o texto
 * não ficava em lugar nenhum. A coluna é preenchida SÓ na marcação de anulação (ORIGEM_ANULACAO),
 * no INSERT; as demais ficam nulas.
 *
 * `ponto_marcacoes` é append-only (Portaria MTP 671/2021): os triggers barram UPDATE/DELETE. Um
 * ADD COLUMN nulo não dispara trigger e não altera nenhuma linha existente — as anulações antigas
 * ficam com o motivo nulo (o texto delas nunca foi gravado e não há como recuperá-lo).
 */
class AddMotivoAnulacaoToPontoMarcacoes extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('ponto_marcacoes') || Schema::hasColumn('ponto_marcacoes', 'motivo_anulacao')) {
            return;
        }

        Schema::table('ponto_marcacoes', function (Blueprint $table) {
            $table->text('motivo_anulacao')->nullable()->after('marcacao_anulada_id')
                ->comment('Motivo em texto — só em origem=ANULACAO, gravado no INSERT');
        });
    }

    public function down()
    {
        if (Schema::hasTable('ponto_marcacoes') && Schema::hasColumn('ponto_marcacoes', 'motivo_anulacao')) {
            Schema::table('ponto_marcacoes', function (Blueprint $table) {
                $table->dropColumn('motivo_anulacao');
            });
        }
    }
}
