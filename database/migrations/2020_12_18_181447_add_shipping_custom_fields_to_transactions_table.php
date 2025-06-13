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
            if (!Schema::hasColumn('transactions', 'shipping_custom_field_1')) {
                $table->string('shipping_custom_field_1')->nullable()->after('shipping_charges');
            }
            if (!Schema::hasColumn('transactions', 'shipping_custom_field_2')) {
                $table->string('shipping_custom_field_2')->nullable()->after('shipping_custom_field_1');
            }
            if (!Schema::hasColumn('transactions', 'shipping_custom_field_3')) {
                $table->string('shipping_custom_field_3')->nullable()->after('shipping_custom_field_2');
            }
            if (!Schema::hasColumn('transactions', 'shipping_custom_field_4')) {
                $table->string('shipping_custom_field_4')->nullable()->after('shipping_custom_field_3');
            }
            if (!Schema::hasColumn('transactions', 'shipping_custom_field_5')) {
                $table->string('shipping_custom_field_5')->nullable()->after('shipping_custom_field_4');
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
    }
};
