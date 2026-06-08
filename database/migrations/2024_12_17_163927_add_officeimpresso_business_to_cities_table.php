<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOfficeimpressoBusinessToCitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cities', function (Blueprint $table) {
            // Adiciona a coluna business_id
            $table->unsignedInteger('business_id')->nullable();
            
            // Adiciona um índice na coluna business_id para melhor performance
            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cities', function (Blueprint $table) {
            // Remove a coluna business_id e o índice
            $table->dropIndex(['business_id']);
            $table->dropColumn('business_id');
        });
    }
}
