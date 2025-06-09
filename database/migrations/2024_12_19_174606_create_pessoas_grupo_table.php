<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePessoasGrupoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pessoas_grupo', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('descricao', 150)->nullable();
            $table->timestamp('dt_alteracao')->nullable();

            // Campos extras de sincronização
            $table->integer('officeimpresso_codigo')->nullable();
            $table->timestamp('officeimpresso_dt_alteracao')->nullable();

            $table->unsignedInteger('business_id')->nullable();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');

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
        Schema::dropIfExists('pessoas_grupo');
    }
}
