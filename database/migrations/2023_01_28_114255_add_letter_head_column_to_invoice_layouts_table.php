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
            if (!Schema::hasColumn('invoice_layouts', 'show_letter_head')) {
                $table->boolean('show_letter_head')->default(0)->after('business_id');
            }
            if (!Schema::hasColumn('invoice_layouts', 'letter_head')) {
                $table->string('letter_head')->nullable()->after('show_letter_head');
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
