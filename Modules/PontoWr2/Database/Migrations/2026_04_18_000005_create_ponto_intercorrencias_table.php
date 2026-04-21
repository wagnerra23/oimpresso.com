<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intercorrências de expediente — saídas/retornos justificados durante o turno.
 * Fluxo: RASCUNHO → PENDENTE → APROVADA | REJEITADA → APLICADA (na apuração).
 */
class CreatePontoIntercorrenciasTable extends Migration
{
    public function up()
    {
        Schema::create('ponto_intercorrencias', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->integer('business_id')->unsigned()->index();
            $table->integer('colaborador_config_id')->unsigned()->index();
            $table->string('codigo', 40)->unique()->comment('INC-YYYY-MMDD-NNN');
            $table->enum('tipo', [
                'CONSULTA_MEDICA',
                'ATESTADO_MEDICO',
                'REUNIAO_EXTERNA',
                'VISITA_CLIENTE',
                'HORA_EXTRA_AUTORIZADA',
                'ESQUECIMENTO_MARCACAO',
                'PROBLEMA_EQUIPAMENTO',
                'OUTRO',
            ]);
            $table->date('data');
            $table->time('intervalo_inicio')->nullable();
            $table->time('intervalo_fim')->nullable();
            $table->boolean('dia_todo')->default(false);
            $table->text('justificativa');
            $table->string('anexo_path', 255)->nullable();
            $table->enum('estado', [
                'RASCUNHO',
                'PENDENTE',
                'APROVADA',
                'REJEITADA',
                'APLICADA',
                'CANCELADA',
            ])->default('RASCUNHO');
            $table->enum('prioridade', ['NORMAL', 'URGENTE'])->default('NORMAL');
            $table->boolean('impacta_apuracao')->default(true);
            $table->boolean('descontar_banco_horas')->default(false);
            $table->integer('solicitante_id')->unsigned();
            $table->integer('aprovador_id')->unsigned()->nullable();
            $table->timestamp('aprovado_em')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('colaborador_config_id')->references('id')->on('ponto_colaborador_config');
            $table->foreign('solicitante_id')->references('id')->on('users');
            $table->foreign('aprovador_id')->references('id')->on('users');
            $table->index(['business_id', 'estado', 'data']);
            $table->index(['colaborador_config_id', 'data']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_intercorrencias');
    }
}
