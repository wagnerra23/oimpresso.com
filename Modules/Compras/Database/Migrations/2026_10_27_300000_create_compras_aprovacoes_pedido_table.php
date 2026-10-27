<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aprovação do pedido de compra por alçada de valor (US-COM-032 · decisão [W] 2026-10-26: no pedido
 * de compra, limites de valor configuráveis por empresa — comprador, gerente, diretoria).
 *
 * Uma linha por pedido que passou do limite de quem o fez. Tabela própria em vez de status novo em
 * `transactions`: o status do pedido é do núcleo (`ordered`/`partial`/`completed`) e outras telas o
 * leem. Enquanto `pendente` ou `recusado`, o pedido não vira compra recebida.
 *
 * Tier 0 (ADR 0093): `business_id` indexado + FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras_aprovacoes_pedido')) {
            return;
        }

        Schema::create('compras_aprovacoes_pedido', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('transaction_id');
            $t->decimal('valor', 22, 4);
            $t->enum('nivel', ['gerente', 'diretoria']);
            $t->enum('status', ['pendente', 'aprovado', 'recusado'])->default('pendente');
            $t->unsignedInteger('pedido_por');
            $t->unsignedInteger('decidido_por')->nullable();
            $t->timestamp('decidido_em')->nullable();
            $t->string('motivo', 500)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'transaction_id'], 'compras_aprov_ped_biz_tx_uq');
            $t->index(['business_id', 'status'], 'compras_aprov_ped_biz_status_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_aprovacoes_pedido');
    }
};
