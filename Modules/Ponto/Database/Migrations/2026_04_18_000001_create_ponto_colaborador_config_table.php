<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge: vincula users/essentials_users do UltimatePOS ao domínio de Ponto.
 * Não altera tabelas do core.
 */
class CreatePontoColaboradorConfigTable extends Migration
{
    public function up()
    {
        Schema::create('ponto_colaborador_config', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('user_id')->unsigned()->unique();
            $table->string('matricula', 30)->nullable()->index();
            $table->string('pis', 14)->nullable()->index();
            $table->string('cpf', 14)->nullable()->index();
            $table->integer('escala_atual_id')->unsigned()->nullable();
            $table->boolean('controla_ponto')->default(true);
            $table->boolean('usa_banco_horas')->default(false);
            $table->date('admissao');
            $table->date('desligamento')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            // FK para escala_atual_id adicionada em migration posterior (depende de ponto_escalas)
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_colaborador_config');
    }
}
