<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proposta de preço de venda gerada pelo recebimento da compra (US-COM-026 · UC-FLX-17).
 *
 * Nasce quando a regra "Atualizar o custo dos insumos" da empresa está em "Propõe novo preço":
 * o recebimento grava o custo novo e deixa aqui o preço proposto, sem mudar o preço. Aprovar ou
 * recusar é follow-up (fila da Fabricação / Pendências).
 *
 * Tier 0 (ADR 0093): `business_id` indexado + FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras_propostas_preco')) {
            return;
        }

        Schema::create('compras_propostas_preco', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('variation_id');
            $t->unsignedInteger('transaction_id');
            $t->decimal('custo_antes', 22, 4);
            $t->decimal('custo_depois', 22, 4);
            $t->decimal('preco_antes', 22, 4);
            $t->decimal('preco_proposto', 22, 4);
            $t->enum('status', ['pendente', 'aprovada', 'recusada'])->default('pendente');
            $t->unsignedInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'status'], 'compras_propostas_preco_biz_status_idx');
            $t->index('variation_id', 'compras_propostas_preco_variation_idx');
            $t->foreign('business_id', 'compras_propostas_preco_biz_fk')
                ->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_propostas_preco');
    }
};
