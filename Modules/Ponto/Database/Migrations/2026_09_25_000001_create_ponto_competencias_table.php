<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Competências fechadas — APPEND-ONLY (ADR 0413 W1).
 *
 * Uma linha = uma competência (mês) fechada por um business. Ela é GRAVADA UMA VEZ:
 *  - "Reabrir" não existe na v1 (ADR 0413 D1) → sem UPDATE;
 *  - correção pós-fechamento é por anulação com trilha em ponto_marcacoes
 *    (Marcacao::anular(), Portaria MTP 671/2021) → sem DELETE.
 * O registro guarda QUEM consolidou, QUANDO e QUAIS bloqueios aceitou (ADR 0413 D2 + W3);
 * não há assinatura digital (D2). Os bloqueios aceitos NÃO travam o AFD (W3).
 *
 * Fechar NÃO altera marcação, apuração nem banco de horas — só registra o ato.
 */
class CreatePontoCompetenciasTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ponto_competencias')) {
            return;
        }

        Schema::create('ponto_competencias', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->date('competencia')->comment('Mês de referência — sempre o dia 1 do mês');
            $table->integer('fechada_por')->unsigned()->comment('users.id de quem consolidou (ADR 0413 D2)');
            $table->dateTime('fechada_em')->comment('Momento do fechamento — imutável');
            $table->json('bloqueios_aceitos')->nullable()->comment('Bloqueios que o fechador aceitou (ADR 0413 W3) — não travam o AFD');
            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at — append-only

            $table->unique(['business_id', 'competencia'], 'ponto_competencias_biz_comp_uq');
            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('fechada_por')->references('id')->on('users');
        });

        // Triggers só em MySQL (mesmo padrão de ponto_marcacoes). O model também bloqueia
        // update/delete — defesa dupla, como no ledger de banco de horas.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_ponto_competencias_no_update
            BEFORE UPDATE ON ponto_competencias
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ponto_competencias é append-only (ADR 0413): competência fechada não reabre.';
            END;
        SQL);

        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_ponto_competencias_no_delete
            BEFORE DELETE ON ponto_competencias
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ponto_competencias é append-only (ADR 0413): competência fechada não reabre.';
            END;
        SQL);
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_competencias_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_competencias_no_delete');
        Schema::dropIfExists('ponto_competencias');
    }
}
