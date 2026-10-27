<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspeção de qualidade no recebimento da compra (US-COM-034 · lacuna P2 de Compras, 2026-10-27).
 *
 * Uma linha por linha de compra inspecionada: quanto foi aprovado e quanto foi reprovado. O
 * reprovado sai do estoque pela devolução de compra do núcleo (`transactions.type = purchase_return`,
 * `return_parent_id` = a compra), e `devolucao_id` aponta para ela. A avaliação do fornecedor
 * (US-COM-033) lê daqui a taxa de reprovação.
 *
 * Tier 0 (ADR 0093): `business_id` indexado, com FK para `business`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras_inspecoes')) {
            return;
        }
        Schema::create('compras_inspecoes', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('transaction_id')->comment('A compra recebida (transactions.type = purchase)');
            $t->unsignedInteger('purchase_line_id');
            $t->unsignedInteger('contact_id')->comment('Fornecedor da compra, para a avaliação');
            $t->decimal('quantidade_inspecionada', 22, 4);
            $t->decimal('quantidade_aprovada', 22, 4);
            $t->decimal('quantidade_reprovada', 22, 4);
            $t->enum('resultado', ['aprovado', 'reprovado', 'parcial']);
            $t->string('motivo', 255)->nullable();
            $t->unsignedInteger('devolucao_id')->nullable()->comment('transactions.id do purchase_return');
            $t->unsignedInteger('inspecionado_por');
            $t->timestamps();
            $t->unique('purchase_line_id', 'compras_insp_linha_uq');
            $t->index(['business_id', 'contact_id'], 'compras_insp_biz_forn_idx');
            $t->index(['business_id', 'transaction_id'], 'compras_insp_biz_compra_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_inspecoes');
    }
};
