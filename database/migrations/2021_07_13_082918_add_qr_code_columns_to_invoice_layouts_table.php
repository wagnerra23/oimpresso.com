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
            if (!Schema::hasColumn('invoice_layouts', 'show_qr_code')) {
                $table->boolean('show_qr_code')->default(0)->after('business_id');
            }
            if (!Schema::hasColumn('invoice_layouts', 'qr_code_fields')) {
                $table->text('qr_code_fields')->nullable()->after('show_qr_code');
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
            if (Schema::hasColumn('invoice_layouts', 'show_qr_code')) {
                $table->dropColumn('show_qr_code');
            }
            if (Schema::hasColumn('invoice_layouts', 'qr_code_fields')) {
                $table->dropColumn('qr_code_fields');
            }
        });
    }
};
