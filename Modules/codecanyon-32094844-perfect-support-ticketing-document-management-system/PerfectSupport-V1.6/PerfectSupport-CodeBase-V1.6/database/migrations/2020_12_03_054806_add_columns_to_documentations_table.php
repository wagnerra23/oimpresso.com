<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToDocumentationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('documentations', function (Blueprint $table) {
            $table->integer('yes')
                ->default(0)
                ->comment('feedback of a doc if it was helpful')
                ->after('created_by');

            $table->integer('no')
                ->default(0)
                ->comment('feedback of a doc if it was helpful')
                ->after('yes');
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
