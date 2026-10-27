<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prazo de entrega do fornecedor por produto (US-COM-030 · MRP, decisão coordenação 2026-10-26).
 *
 * Quantos dias o fornecedor leva para entregar aquele produto, por empresa. O MRP usa para dizer
 * até quando pedir: data em que a primeira OS precisa do insumo − este prazo. Uma linha por
 * (empresa, produto, fornecedor). `products` e `contacts` são core e não ganham coluna.
 *
 * Tier 0 (ADR 0093): `business_id` indexado + FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras_prazo_fornecedor')) {
            return;
        }

        Schema::create('compras_prazo_fornecedor', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('product_id');
            $t->unsignedInteger('contact_id');
            $t->unsignedSmallInteger('dias');
            $t->timestamps();
            $t->unique(['business_id', 'product_id', 'contact_id'], 'compras_prazo_forn_biz_prod_cont_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $t->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_prazo_fornecedor');
    }
};
