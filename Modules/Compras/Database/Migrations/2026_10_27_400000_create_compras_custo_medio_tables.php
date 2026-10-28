<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custo médio ponderado móvel por variação (US-PROD-024 fase 2).
 *
 * `compras_custo_medio` guarda o custo médio atual de cada variação da empresa; a linha muda a cada
 * compra recebida. `compras_custo_medio_movimentos` guarda cada recálculo (antes, entrada, depois),
 * para saber o custo médio de qualquer momento — é dele que a margem da venda lê.
 *
 * `variations` é core e não ganha coluna; `dpp_inc_tax` continua sendo escrito pelo core ou pela
 * regra de custo (US-COM-026), sem mudança.
 *
 * Tier 0 (ADR 0093): `business_id` indexado + FK nas duas tabelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compras_custo_medio')) {
            Schema::create('compras_custo_medio', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('variation_id');
                $t->decimal('custo_medio', 22, 4);
                $t->timestamps();
                $t->unique(['business_id', 'variation_id'], 'compras_custo_medio_biz_var_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
                $t->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('compras_custo_medio_movimentos')) {
            Schema::create('compras_custo_medio_movimentos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('variation_id');
                $t->unsignedInteger('transaction_id')->nullable();
                $t->string('origem', 12); // entrada | semente
                $t->decimal('qtd_antes', 22, 4);
                $t->decimal('custo_antes', 22, 4)->nullable();
                $t->decimal('qtd_entrada', 22, 4);
                $t->decimal('custo_entrada', 22, 4)->nullable();
                $t->decimal('custo_depois', 22, 4);
                $t->dateTime('registrado_em');
                $t->timestamps();
                $t->unique(['business_id', 'transaction_id', 'variation_id'], 'compras_cm_mov_biz_tx_var_uq');
                $t->index(['business_id', 'variation_id', 'registrado_em'], 'compras_cm_mov_biz_var_em_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
                $t->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_custo_medio_movimentos');
        Schema::dropIfExists('compras_custo_medio');
    }
};
