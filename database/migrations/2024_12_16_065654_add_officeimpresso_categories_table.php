<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOfficeimpressoCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('officeimpresso_codigo')->nullable(); // Campo para código do OfficeImpresso
            $table->timestamp('officeimpresso_dt_alteracao')->nullable(); // Campo para data de alteração
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['officeimpresso_codigo', 'officeimpresso_dt_alteracao']);
        });
    }
}
