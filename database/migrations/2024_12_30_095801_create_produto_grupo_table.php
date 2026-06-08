<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProdutoGrupoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('produto_grupo', function (Blueprint $table) {
            $table->increments('id'); 
            $table->string('descricao', 40)->nullable();
            $table->string('referencia', 15)->nullable();
            $table->string('codplanocontas', 15)->nullable();
            $table->unique('referencia'); // Índice único para REFERENCIA

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
        Schema::dropIfExists('produto_grupo');
    }
}
