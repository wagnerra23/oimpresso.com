<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra qual business_location (CNPJ fiscal) o Delphi estava usando
 * em cada chamada. O HD continua autorizado por business_id (operacao);
 * business_location_id e pura informacao de contexto pra audit.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('licenca_log', function (Blueprint $table) {
            $table->unsignedInteger('business_location_id')->nullable()->after('business_id');
            $table->index('business_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('licenca_log', function (Blueprint $table) {
            $table->dropIndex(['business_location_id']);
            $table->dropColumn('business_location_id');
        });
    }
};
