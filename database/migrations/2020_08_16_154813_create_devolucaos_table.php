<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDevolucaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('devolucaos', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('contact_id')->unsigned();
            $table->foreign('contact_id')->references('id')->on('contacts');

            $table->integer('natureza_id')->unsigned();
            $table->foreign('natureza_id')->references('id')->on('natureza_operacaos');

            $table->integer('business_id')->unsigned();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');

            $table->decimal('valor_integral', 10,2);
            $table->decimal('valor_devolvido', 10,2);

            $table->string('motivo', 100);
            $table->string('observacao', 50);
            $table->integer('estado');
            $table->boolean('devolucao_parcial');

            $table->string('chave_nf_entrada',48);
            $table->integer('nNf');
            $table->decimal('vFrete', 10, 2);
            $table->decimal('vDesc', 10, 2);

            $table->string('chave_gerada', 44);
            $table->integer('numero_gerado');

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
        Schema::dropIfExists('devolucaos');
    }
}
