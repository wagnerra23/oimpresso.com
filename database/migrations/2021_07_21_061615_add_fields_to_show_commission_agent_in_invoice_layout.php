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
        Schema::table('invoice_layouts', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_layouts', 'commission_agent_label')) {
                $table->string('commission_agent_label')->nullable()->after('customer_label');
            }
            if (!Schema::hasColumn('invoice_layouts', 'show_commission_agent')) {
                $table->boolean('show_commission_agent')->default(0)->after('commission_agent_label');
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
        Schema::table('invoice_layouts', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_layouts', 'commission_agent_label')) {
                $table->dropColumn('commission_agent_label');
            }
            if (Schema::hasColumn('invoice_layouts', 'show_commission_agent')) {
                $table->dropColumn('show_commission_agent');
            }
        });
    }
};
