<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-AUTO-008 — catálogo de peças por veículo (decisão [W] 2026-10-26: marca e modelo em texto).
 *
 * - `vehicles.brand` / `vehicles.model`: marca e modelo do veículo, texto livre (a consulta de placa já
 *   devolvia os dois, mas não havia onde gravar — iam parar em `notes`).
 * - `oficina_pecas_compativeis`: "esta peça (product) serve em <marca> <modelo> de <ano> a <ano>".
 *   `model` nulo = serve em toda a marca; ano nulo = sem limite daquele lado.
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles') && ! Schema::hasColumn('vehicles', 'brand')) {
            Schema::table('vehicles', function (Blueprint $t) {
                $t->string('brand', 60)->nullable();
                $t->string('model', 80)->nullable();
            });
        }

        if (! Schema::hasTable('oficina_pecas_compativeis')) {
            Schema::create('oficina_pecas_compativeis', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index('oficina_pecas_compat_biz_idx');
                $t->unsignedInteger('product_id');
                $t->string('brand', 60);
                $t->string('model', 80)->nullable();
                $t->smallInteger('year_from')->nullable();
                $t->smallInteger('year_to')->nullable();
                $t->timestamps();

                $t->index(['business_id', 'brand'], 'oficina_pecas_compat_marca_idx');
                $t->unique(['business_id', 'product_id', 'brand', 'model'], 'oficina_pecas_compat_uq');
                $t->foreign('business_id', 'oficina_pecas_compat_biz_fk')->references('id')->on('business')->cascadeOnDelete();
                $t->foreign('product_id', 'oficina_pecas_compat_prod_fk')->references('id')->on('products')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oficina_pecas_compativeis');
        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'brand')) {
            Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn(['brand', 'model']));
        }
    }
};
