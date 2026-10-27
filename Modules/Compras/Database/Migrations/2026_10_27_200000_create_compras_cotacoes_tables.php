<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotação de compra entre fornecedores (US-COM-031 · decisão coordenação 2026-10-26).
 *
 * Uma cotação pede preço e prazo de vários fornecedores para os mesmos itens; o comprador digita
 * as respostas, compara e fecha, e a vencedora vira pedido de compra pelo fluxo do núcleo. Os itens
 * podem vir de uma requisição de compra ou de outro módulo (a Oficina vai consumir esta mesma
 * cotação — combinado com a frente Verticais em 2026-10-26): `origem` + `origem_id` dizem de onde.
 *
 * Tier 0 (ADR 0093): as quatro tabelas têm `business_id` indexado; a cotação tem FK para `business`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compras_cotacoes')) {
            Schema::create('compras_cotacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedInteger('location_id');
                $t->string('ref_no', 40);
                $t->enum('status', ['aberta', 'fechada', 'cancelada'])->default('aberta');
                $t->string('origem', 40)->nullable()->comment('requisicao | oficina | ... (de onde vieram os itens)');
                $t->unsignedBigInteger('origem_id')->nullable();
                $t->date('responder_ate')->nullable();
                $t->text('observacao')->nullable();
                $t->unsignedInteger('created_by');
                $t->timestamp('fechada_em')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'status'], 'compras_cot_biz_status_idx');
                $t->unique(['business_id', 'ref_no'], 'compras_cot_biz_ref_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('compras_cotacao_itens')) {
            Schema::create('compras_cotacao_itens', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('cotacao_id')->constrained('compras_cotacoes')->cascadeOnDelete();
                $t->unsignedInteger('product_id');
                $t->unsignedInteger('variation_id');
                $t->decimal('quantidade', 22, 4);
                $t->unsignedInteger('vencedor_contact_id')->nullable()->comment('Fornecedor escolhido ao fechar');
                $t->timestamps();
                $t->foreign('variation_id')->references('id')->on('variations');
            });
        }

        if (! Schema::hasTable('compras_cotacao_fornecedores')) {
            Schema::create('compras_cotacao_fornecedores', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('cotacao_id')->constrained('compras_cotacoes')->cascadeOnDelete();
                $t->unsignedInteger('contact_id');
                $t->timestamps();
                $t->unique(['cotacao_id', 'contact_id'], 'compras_cot_forn_uq');
                $t->foreign('contact_id')->references('id')->on('contacts');
            });
        }

        if (! Schema::hasTable('compras_cotacao_respostas')) {
            Schema::create('compras_cotacao_respostas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('cotacao_id')->constrained('compras_cotacoes')->cascadeOnDelete();
                $t->foreignId('item_id')->constrained('compras_cotacao_itens')->cascadeOnDelete();
                $t->unsignedInteger('contact_id');
                $t->decimal('preco_unitario', 22, 4);
                $t->unsignedSmallInteger('prazo_dias')->nullable();
                $t->timestamps();
                $t->unique(['item_id', 'contact_id'], 'compras_cot_resp_item_forn_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_cotacao_respostas');
        Schema::dropIfExists('compras_cotacao_fornecedores');
        Schema::dropIfExists('compras_cotacao_itens');
        Schema::dropIfExists('compras_cotacoes');
    }
};
