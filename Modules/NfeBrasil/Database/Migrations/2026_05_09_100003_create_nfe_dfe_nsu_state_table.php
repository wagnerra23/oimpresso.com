<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-051 · Tabela `nfe_dfe_nsu_state` — cursor NSU SEFAZ por business.
 *
 * 1 row por business. NSU é cursor irreversível — perda = perde XMLs.
 * Backup obrigatório antes de qualquer migration que toca esta tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_dfe_nsu_state')) {
            return;
        }

        Schema::create('nfe_dfe_nsu_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->unique();
            $table->unsignedBigInteger('last_nsu')->default(0)
                ->comment('Cursor SEFAZ — IRREVERSÍVEL. Não decrementa.');
            $table->timestamp('ultimo_check_em')->nullable();
            $table->unsignedBigInteger('total_xmls_processados')->default(0);
            $table->unsignedSmallInteger('ultimo_lote_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_dfe_nsu_state');
    }
};
