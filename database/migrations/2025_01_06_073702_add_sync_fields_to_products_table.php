<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSyncFieldsToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('officeimpresso_codigo', 255)->nullable(); // Código para sincronismo
            $table->timestamp('officeimpresso_dt_alteracao')->nullable(); // Data de última alteração
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
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['officeimpresso_codigo', 'officeimpresso_dt_alteracao']);
            $table->dropSoftDeletes();
        });
    }
}
