<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material consumido na OS por item (UC-FLX-37 · US-PCP-021). Decisão [W] 2026-10-19: "a OS baixa do
 * estoque o material apontado em cada etapa, pelo custo do lote que saiu".
 *
 * Uma linha por apontamento de material. A baixa de estoque mora no core: cada OS tem UMA transação
 * `production_sell` (no local da venda que gerou a OS) e cada consumo é uma linha de venda dela,
 * amarrada ao lote em `transaction_sell_lines_purchase_lines` pelo `mapPurchaseSell`.
 * `custo` é o custo do lote que saiu, congelado aqui: compra posterior não reescreve.
 *
 * Idempotente pela `chave` (fila offline do celular), como o apontamento de horas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_consumos')) {
            return;
        }

        Schema::create('pcp_consumos', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('chave', 64);
            $t->foreignId('ordem_id')->constrained('pcp_ordens')->cascadeOnDelete();
            $t->foreignId('programacao_id')->constrained('pcp_programacoes');
            $t->unsignedInteger('product_id');
            $t->unsignedInteger('variation_id');
            $t->decimal('quantidade', 22, 4);
            // Parte que não saiu de lote nenhum (empresa que permite vender sem estoque): sem custo.
            $t->decimal('quantidade_sem_lote', 22, 4)->default(0);
            $t->decimal('custo', 22, 4);
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('transaction_sell_line_id');
            $t->unsignedInteger('user_id');
            $t->dateTime('momento');
            $t->timestamps();
            $t->unique(['business_id', 'chave'], 'pcp_consumos_biz_chave_uq');
            $t->index(['business_id', 'ordem_id'], 'pcp_consumos_biz_ordem_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcp_consumos');
    }
};
