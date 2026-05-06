<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado da apuração diária consolidada por colaborador.
 * Recalculável via ApuracaoService::reapurar($dia, $colaborador).
 */
class CreatePontoApuracaoDiaTable extends Migration
{
    public function up()
    {
        Schema::create('ponto_apuracao_dia', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('colaborador_config_id')->unsigned()->index();
            $table->date('data');
            $table->integer('escala_id')->unsigned()->nullable();

            // Horários previstos
            $table->time('prevista_entrada')->nullable();
            $table->time('prevista_saida')->nullable();
            $table->smallInteger('prevista_carga_minutos')->unsigned()->default(0);

            // Horários realizados
            $table->time('realizada_entrada')->nullable();
            $table->time('realizada_saida')->nullable();
            $table->smallInteger('realizada_trabalhada_minutos')->unsigned()->default(0);
            $table->smallInteger('realizada_intrajornada_minutos')->unsigned()->default(0);

            // Cálculos
            $table->smallInteger('atraso_minutos')->default(0);
            $table->smallInteger('saida_antecipada_minutos')->default(0);
            $table->smallInteger('falta_minutos')->default(0);
            $table->smallInteger('he_diurna_minutos')->default(0);
            $table->smallInteger('he_noturna_minutos')->default(0);
            $table->smallInteger('adicional_noturno_minutos')->default(0);
            $table->smallInteger('dsr_repercussao_minutos')->default(0);
            $table->smallInteger('interjornada_violacao_minutos')->default(0);
            $table->smallInteger('intrajornada_violacao_minutos')->default(0);
            $table->smallInteger('banco_horas_credito_minutos')->default(0);
            $table->smallInteger('banco_horas_debito_minutos')->default(0);

            // Estado
            $table->enum('estado', [
                'PENDENTE',
                'CALCULADO',
                'DIVERGENCIA',
                'AJUSTADO',
                'CONSOLIDADO',
                'FECHADO',
            ])->default('PENDENTE');
            $table->smallInteger('qtd_intercorrencias')->unsigned()->default(0);
            $table->smallInteger('qtd_marcacoes')->unsigned()->default(0);
            $table->json('divergencias')->nullable();
            $table->timestamp('calculado_em')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('colaborador_config_id')->references('id')->on('ponto_colaborador_config');
            $table->foreign('escala_id')->references('id')->on('ponto_escalas');
            $table->unique(['colaborador_config_id', 'data']);
            $table->index(['business_id', 'data', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_apuracao_dia');
    }
}
