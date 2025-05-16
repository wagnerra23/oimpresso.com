<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCondicaopagtoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('condicaopagto', function (Blueprint $table) {
            $table->increments('id');
            $table->string('descricao', 30)->nullable();
            $table->char('tipo', 1)->nullable();
            $table->integer('parcelas')->nullable();
            $table->integer('intervalo')->nullable();
            $table->char('entrada', 1)->nullable();
            $table->double('desconto_acrescimo')->nullable();
            $table->string('tipopagto', 50)->nullable();
            $table->string('tipo_utilizacao', 15)->nullable();
            $table->double('perc_entrada')->nullable();
            $table->string('codplanocontas', 30)->nullable();
            $table->string('codplanocontas_pagto', 30)->nullable();
            $table->double('fator_comercial')->nullable();
            $table->char('ativo', 1)->nullable();
            $table->timestamp('dt_alteracao')->nullable();
            $table->char('intervalo_mensal', 1)->nullable()->comment('DOM_BOOLEAN');
            $table->char('is_cartao', 1)->nullable();
            $table->char('pode_substituir_desconto_venda', 1)->nullable();
            $table->unsignedInteger('business_id'); // Campo obrigatório (não aceita NULL)
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');     

            // Campos de sincronismo
            $table->string('officeimpresso_codigo', 15)->nullable();
            $table->timestamp('officeimpresso_dt_alteracao')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('condicaopagto');
    }
}
