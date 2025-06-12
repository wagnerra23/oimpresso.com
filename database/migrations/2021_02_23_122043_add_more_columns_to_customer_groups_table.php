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
        Schema::table('purchase_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_lines', 'purchase_order_line_id')) {
                $table->integer('purchase_order_line_id')->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('purchase_lines', 'po_quantity_purchased')) {
                $table->decimal('po_quantity_purchased', 22, 4)->default(0)->after('quantity_returned');
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
        Schema::table('purchase_lines', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_lines', 'purchase_order_line_id')) {
                $table->dropColumn('purchase_order_line_id');
            }
            if (Schema::hasColumn('purchase_lines', 'po_quantity_purchased')) {
                $table->dropColumn('po_quantity_purchased');
            }
        });
    }
};