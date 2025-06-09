<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomFieldsToTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {

            $table->dateTime('closed_on')
                ->nullable()
                ->after('labels');
            
            $table->bigInteger('closed_by')
                ->nullable()
                ->after('closed_on');

            $table->text('custom_field_1')
                ->nullable()
                ->after('closed_by');
            
            $table->text('custom_field_2')
                ->nullable()
                ->after('custom_field_1');

            $table->text('custom_field_3')
                ->nullable()
                ->after('custom_field_2');
            
            $table->text('custom_field_4')
                ->nullable()
                ->after('custom_field_3');

            $table->text('custom_field_5')
                ->nullable()
                ->after('custom_field_4');
            
            $table->text('custom_field_6')
                ->nullable()
                ->after('custom_field_5');

            $table->text('custom_field_7')
                ->nullable()
                ->after('custom_field_6');
            
            $table->text('custom_field_8')
                ->nullable()
                ->after('custom_field_7');

            $table->text('custom_field_9')
                ->nullable()
                ->after('custom_field_8');
            
            $table->text('custom_field_10')
                ->nullable()
                ->after('custom_field_9');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
