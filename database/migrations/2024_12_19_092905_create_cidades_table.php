<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCidadesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cidades', function (Blueprint $table) {
            $table->increments('id'); // ID primária auto-incrementada
            $table->string('descricao', 50); // Nome ou descrição da cidade
            $table->string('uf', 2); // Unidade Federativa (estado)
            $table->timestamps(); // Campos created_at e updated_at
            $table->softDeletes(); // Para habilitar exclusão lógica (deleted_at)
            $table->integer('officeimpresso_codigo')->nullable(); // Código opcional
            $table->timestamp('officeimpresso_dt_alteracao')->nullable(); // Data de alteração opcional
            $table->unsignedInteger('business_id'); // Business ID obrigatório
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade'); // Chave estrangeira
        });
    }
    

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cidades');
    }
}
