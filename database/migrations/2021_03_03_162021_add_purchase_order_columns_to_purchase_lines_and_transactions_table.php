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
                $table->integer('purchase_order_line_id')->after('tax_id')->nullable();
            }
            if (!Schema::hasColumn('purchase_lines', 'po_quantity_purchased')) {
                $table->decimal('po_quantity_purchased', 22, 4)->after('quantity_returned')->default(0);
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'purchase_order_ids')) {
                $table->text('purchase_order_ids')->after('created_by')->nullable();
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

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'purchase_order_ids')) {
                $table->dropColumn('purchase_order_ids');
            }
        });
    }
};