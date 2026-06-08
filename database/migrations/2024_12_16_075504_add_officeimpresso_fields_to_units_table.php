<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOfficeimpressoFieldsToUnitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->smallInteger('exibir_comprimento')->nullable()->default(0);
            $table->smallInteger('exibir_largura')->nullable()->default(0);
            $table->smallInteger('exibir_espessura')->nullable()->default(0);
            $table->smallInteger('calc_comprimento')->nullable()->default(0);
            $table->smallInteger('calc_largura')->nullable()->default(0);
            $table->smallInteger('calc_espessura')->nullable()->default(0);
            $table->smallInteger('gera_lote')->nullable()->default(0);
            $table->smallInteger('exibir_qtdmetricaunitaria')->nullable()->default(0);
            $table->integer('officeimpresso_codigo')->nullable();
            $table->timestamp('officeimpresso_dt_alteracao')->nullable();
            $table->string('formula', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn([
                'exibir_comprimento',
                'exibir_largura',
                'exibir_espessura',
                'calc_comprimento',
                'calc_largura',
                'calc_espessura',
                'gera_lote',
                'exibir_qtdmetricaunitaria',
                'officeimpresso_codigo',
                'officeimpresso_dt_alteracao',
                'formula',
            ]);
        });
    }
}
