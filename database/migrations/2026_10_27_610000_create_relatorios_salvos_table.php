<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-REL-001 — relatório montável salvo pela empresa.
 * `definicao` guarda só chaves (fonte, campos, filtros, grupo, gráfico); nunca SQL.
 * Tier 0 (ADR 0093): `business_id` indexado + FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('relatorios_salvos')) {
            return;
        }
        Schema::create('relatorios_salvos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('criado_por');
            $table->string('nome', 120);
            $table->json('definicao');
            $table->timestamps();

            $table->index(['business_id', 'nome'], 'rel_salvos_biz_nome_idx');
            $table->foreign('business_id', 'rel_salvos_biz_fk')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorios_salvos');
    }
};
