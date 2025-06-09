<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixOfficeimpressoFieldsToCitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Adicionar a nova coluna officeimpresso_codigo
        Schema::table('cities', function (Blueprint $table) {
            $table->string('officeimpresso_codigo', 15)->nullable();
        });

        // Atualizar os valores de 'codigo' para 'officeimpresso_codigo'
        DB::statement('UPDATE cities SET officeimpresso_codigo = codigo');

        // Remover a coluna antiga 'codigo'
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Adicionar novamente a coluna 'codigo'
        Schema::table('cities', function (Blueprint $table) {
            $table->string('codigo', 15)->nullable();
        });

        // Reverter os valores de 'officeimpresso_codigo' para 'codigo'
        DB::statement('UPDATE cities SET codigo = officeimpresso_codigo');

        // Remover a coluna 'officeimpresso_codigo'
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('officeimpresso_codigo');
        });
    }
}
