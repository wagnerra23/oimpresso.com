<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-para lembrado: código do item no fornecedor → variação do produto (ADR
 * `proposals/2026-10-06-entrada-xml-compra.md` D3 · US-COM-003/US-COM-012).
 *
 * O nome da técnica é "cadastro produto × fornecedor" (Bling: fornecedor vinculado ao produto;
 * Odoo: Vendor Product Code). Preenchido na primeira vez que o item aparece numa nota; a próxima
 * nota do mesmo fornecedor já vem vinculada. Um vínculo por (empresa, CNPJ, código).
 *
 * Tier 0 (ADR 0093): `business_id` indexado + FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras_produto_fornecedor')) {
            return;
        }

        Schema::create('compras_produto_fornecedor', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('cnpj_fornecedor', 14);
            $t->string('codigo_fornecedor', 60);
            $t->unsignedInteger('variation_id');
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'cnpj_fornecedor', 'codigo_fornecedor'], 'compras_prod_forn_biz_cnpj_cod_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_produto_fornecedor');
    }
};
