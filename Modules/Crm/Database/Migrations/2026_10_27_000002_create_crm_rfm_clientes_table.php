<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segmentação RFM (US-CRM-098): o último cálculo por cliente. Recalculado por empresa pelo
 * comando `crm:rfm-calcular` (substitui as linhas da empresa a cada rodada).
 *
 * Tier 0 (ADR 0093): `business_id` NOT NULL, indexado, FK para `business`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_rfm_clientes')) {
            return;
        }
        Schema::create('crm_rfm_clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('recencia_dias');
            $table->unsignedInteger('frequencia');
            $table->decimal('valor', 22, 4);
            $table->unsignedTinyInteger('nota_r');
            $table->unsignedTinyInteger('nota_f');
            $table->unsignedTinyInteger('nota_m');
            $table->string('segmento', 20);
            $table->dateTime('calculado_em');
            $table->unique(['business_id', 'contact_id'], 'crm_rfm_clientes_contato_unique');
            $table->index(['business_id', 'segmento'], 'crm_rfm_clientes_segmento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_rfm_clientes');
    }
};
