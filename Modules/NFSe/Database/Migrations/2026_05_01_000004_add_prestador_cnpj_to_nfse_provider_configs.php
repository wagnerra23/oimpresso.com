<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfse_provider_configs', function (Blueprint $table) {
            $table->string('prestador_cnpj', 18)->nullable()->after('provider');
            $table->string('prestador_im', 20)->nullable()->after('prestador_cnpj'); // Inscrição Municipal
        });
    }

    public function down(): void
    {
        Schema::table('nfse_provider_configs', function (Blueprint $table) {
            $table->dropColumn(['prestador_cnpj', 'prestador_im']);
        });
    }
};
