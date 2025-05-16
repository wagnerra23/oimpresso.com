<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateLicencaComputadorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('licenca_computador', function (Blueprint $table) {
            $table->string('descricao')->nullable(); // Adiciona a coluna descricao
            $table->string('sistema')->nullable(); // Adiciona a coluna sistema
            $table->timestamp('dt_cadastro')->nullable(); // Adiciona a coluna dt_cadastro
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('licenca_computador', function (Blueprint $table) {
            $table->dropColumn(['descricao', 'sistema', 'dt_cadastro']);
        });
    }
}
