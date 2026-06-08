<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePontoEscalasTable extends Migration
{
    public function up()
    {
        Schema::create('ponto_escalas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->string('nome', 120);
            $table->string('codigo', 30)->nullable()->index();
            $table->enum('tipo', ['FIXA', 'FLEXIVEL', 'ESCALA_12X36', 'ESCALA_6X1', 'ESCALA_5X2']);
            $table->smallInteger('carga_diaria_minutos')->unsigned()->default(480); // 8h
            $table->smallInteger('carga_semanal_minutos')->unsigned()->default(2640); // 44h
            $table->boolean('permite_banco_horas')->default(false);
            $table->json('dias_semana')->nullable(); // ["seg","ter",...]
            $table->json('horarios_padrao')->nullable(); // [{entrada, almoco_i, almoco_f, saida}]
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });

        Schema::create('ponto_escala_turnos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('escala_id')->unsigned();
            $table->foreign('escala_id')->references('id')->on('ponto_escalas')->onDelete('cascade');
            $table->tinyInteger('dia_semana')->unsigned(); // 0..6
            $table->time('hora_entrada');
            $table->time('hora_almoco_inicio')->nullable();
            $table->time('hora_almoco_fim')->nullable();
            $table->time('hora_saida');
            $table->timestamps();
        });

        // Adiciona FK pendente em ponto_colaborador_config.escala_atual_id
        Schema::table('ponto_colaborador_config', function (Blueprint $table) {
            $table->foreign('escala_atual_id')->references('id')->on('ponto_escalas')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('ponto_colaborador_config', function (Blueprint $table) {
            $table->dropForeign(['escala_atual_id']);
        });
        Schema::dropIfExists('ponto_escala_turnos');
        Schema::dropIfExists('ponto_escalas');
    }
}
