<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('business_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('business_locations', 'sale_invoice_scheme_id')) {
                $table->integer('sale_invoice_scheme_id')->after('invoice_scheme_id')->nullable();
            }
        });

        // Only run the update if the column was newly added or if you want to ensure it's populated
        // Consider if this update should also be conditional or if it's safe to run multiple times.
        // If 'invoice_scheme_id' can change and you want 'sale_invoice_scheme_id' to reflect the latest,
        // then running it always is fine. If it's a one-time population, it might be better
        // to place it inside the conditional block if the column was just created.
        // For simplicity and idempotency, it's often fine to run updates like this,
        // but be mindful of performance on very large tables if run unnecessarily.
        DB::statement('UPDATE business_locations SET sale_invoice_scheme_id = invoice_scheme_id WHERE sale_invoice_scheme_id IS NULL AND invoice_scheme_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('business_locations', function (Blueprint $table) {
            //
        });
    }
};
