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
            if (!Schema::hasColumn('transactions', 'additional_expense_key_1')) {
                $table->string('additional_expense_key_1')->nullable()->after('round_off_amount');
            }
            if (!Schema::hasColumn('transactions', 'additional_expense_value_1')) {
                $table->decimal('additional_expense_value_1', 22, 4)->default(0)->after('additional_expense_key_1');
            }

            if (!Schema::hasColumn('transactions', 'additional_expense_key_2')) {
                $table->string('additional_expense_key_2')->nullable()->after('additional_expense_value_1');
            }
            if (!Schema::hasColumn('transactions', 'additional_expense_value_2')) {
                $table->decimal('additional_expense_value_2', 22, 4)->default(0)->after('additional_expense_key_2');
            }

            if (!Schema::hasColumn('transactions', 'additional_expense_key_3')) {
                $table->string('additional_expense_key_3')->nullable()->after('additional_expense_value_2');
            }
            if (!Schema::hasColumn('transactions', 'additional_expense_value_3')) {
                $table->decimal('additional_expense_value_3', 22, 4)->default(0)->after('additional_expense_key_3');
            }

            if (!Schema::hasColumn('transactions', 'additional_expense_key_4')) {
                $table->string('additional_expense_key_4')->nullable()->after('additional_expense_value_3');
            }
            if (!Schema::hasColumn('transactions', 'additional_expense_value_4')) {
                $table->decimal('additional_expense_value_4', 22, 4)->default(0)->after('additional_expense_key_4');
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
            if (Schema::hasColumn('transactions', 'additional_expense_key_1')) {
                $table->dropColumn('additional_expense_key_1');
            }
            if (Schema::hasColumn('transactions', 'additional_expense_value_1')) {
                $table->dropColumn('additional_expense_value_1');
            }

            if (Schema::hasColumn('transactions', 'additional_expense_key_2')) {
                $table->dropColumn('additional_expense_key_2');
            }
            if (Schema::hasColumn('transactions', 'additional_expense_value_2')) {
                $table->dropColumn('additional_expense_value_2');
            }

            if (Schema::hasColumn('transactions', 'additional_expense_key_3')) {
                $table->dropColumn('additional_expense_key_3');
            }
            if (Schema::hasColumn('transactions', 'additional_expense_value_3')) {
                $table->dropColumn('additional_expense_value_3');
            }

            if (Schema::hasColumn('transactions', 'additional_expense_key_4')) {
                $table->dropColumn('additional_expense_key_4');
            }
            if (Schema::hasColumn('transactions', 'additional_expense_value_4')) {
                $table->dropColumn('additional_expense_value_4');
            }
        });
    }
};