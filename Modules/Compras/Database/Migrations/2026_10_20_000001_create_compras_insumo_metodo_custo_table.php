<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Método de custo próprio do insumo (US-COM-024 · UC-FLX-38).
 *
 * Quando a regra "Atualizar o custo dos insumos" da empresa é `por_insumo`, cada insumo
 * diz o seu método aqui. Tabela-ponte: `variations` é core UltimatePOS e não ganha coluna
 * (proibicoes.md §Código). Uma linha por (empresa, variação).
 *
 * Tier 0 (ADR 0093): `business_id` indexado + FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras_insumo_metodo_custo')) {
            return;
        }

        Schema::create('compras_insumo_metodo_custo', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('variation_id');
            $t->enum('metodo', ['medio', 'ultimo']);
            $t->timestamps();
            $t->unique(['business_id', 'variation_id'], 'compras_ins_metodo_biz_var_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_insumo_metodo_custo');
    }
};
