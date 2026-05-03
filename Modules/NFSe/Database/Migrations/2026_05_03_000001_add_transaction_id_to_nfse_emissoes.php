<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfse_emissoes', function (Blueprint $table) {
            $table->unsignedInteger('transaction_id')
                  ->nullable()
                  ->after('recurring_invoice_id')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('nfse_emissoes', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};
