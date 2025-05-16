<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNfNaturezaOperacaoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nf_natureza_operacao', function (Blueprint $table) {
            $table->increments('id'); 
            $table->string('descricao', 200)->nullable();
            $table->string('tipo_nf', 10)->nullable();
            $table->integer('nfse_codigo')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('dt_alteracao')->nullable();
            $table->char('consumidor_final', 1)->nullable();
            $table->char('entrada_saida', 1)->nullable();
            $table->string('operacao', 50)->nullable();
            $table->char('tem_tributacao_padrao', 1)->nullable();

            // Campos adicionais
            $table->unsignedBigInteger('created_by')->nullable();
            $table->integer('officeimpresso_codigo')->nullable();
            $table->timestamp('officeimpresso_dt_alteracao')->nullable();
            $table->unsignedBigInteger('business_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nf_natureza_operacao');
    }
}
