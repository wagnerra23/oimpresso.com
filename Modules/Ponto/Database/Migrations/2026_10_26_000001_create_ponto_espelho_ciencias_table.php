<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ciência do colaborador no espelho do mês — APPEND-ONLY (US-PONTO-020).
 *
 * Uma linha = o colaborador declarou ciência do espelho de UMA competência já fechada.
 * Grava o SHA-256 do espelho como estava naquele momento: se uma anulação posterior mudar o
 * espelho, o hash deixa de conferir e isso fica visível, sem apagar a ciência.
 *
 * Não grava IP (US-PONTO-001: o módulo não guarda IP) nem dado biométrico (ADR 0383).
 */
class CreatePontoEspelhoCienciasTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ponto_espelho_ciencias')) {
            return;
        }

        Schema::create('ponto_espelho_ciencias', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('colaborador_config_id')->unsigned();
            $table->date('competencia')->comment('Mês de referência — sempre o dia 1 do mês');
            $table->char('hash_espelho', 64)->comment('SHA-256 do espelho no momento da ciência');
            $table->dateTime('ciente_em');
            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at — append-only

            $table->unique(['colaborador_config_id', 'competencia'], 'ponto_espelho_ciencias_colab_comp_uq');
            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('colaborador_config_id')->references('id')->on('ponto_colaborador_config');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_ponto_espelho_ciencias_no_update
            BEFORE UPDATE ON ponto_espelho_ciencias
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ponto_espelho_ciencias é append-only: a ciência dada não muda.';
            END;
        SQL);

        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_ponto_espelho_ciencias_no_delete
            BEFORE DELETE ON ponto_espelho_ciencias
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ponto_espelho_ciencias é append-only: a ciência dada não é apagada.';
            END;
        SQL);
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_espelho_ciencias_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_espelho_ciencias_no_delete');
        Schema::dropIfExists('ponto_espelho_ciencias');
    }
}
