<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'prefer_payment_method')) {
                $table->string('prefer_payment_method')
                    ->nullable()
                    ->after('created_by');
            }
            if (!Schema::hasColumn('transactions', 'prefer_payment_account')) {
                $table->integer('prefer_payment_account')
                    ->nullable()
                    ->after('prefer_payment_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'prefer_payment_method')) {
                $table->dropColumn('prefer_payment_method');
            }
            if (Schema::hasColumn('transactions', 'prefer_payment_account')) {
                $table->dropColumn('prefer_payment_account');
            }
        });
    }
};