<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexingForColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('product_department_id');
            $table->index('user_id');
            $table->index('ticket_ref');
            $table->index('status');
            $table->index('priority');
            $table->index('is_public');
            $table->index('last_updated_by');
            $table->index('updated_at');
        });

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->index('ticket_id');
            $table->index('user_id');
        });

        Schema::table('ticket_support_agents', function (Blueprint $table) {
            $table->index('ticket_id');
            $table->index('user_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
            $table->index('user_id');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('user_id');
        });

        Schema::table('documentations', function (Blueprint $table) {
            $table->index('title');
            $table->index('status');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('canned_responses', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->index('role');
            $table->index('product_id');
            $table->index('start_datetime');
            $table->index('end_datetime');
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
