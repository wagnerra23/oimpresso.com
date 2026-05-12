<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-INV-001 — Tabela product_bom (Bill of Materials) normalizada.
 *
 * Substitui o JSON `variations.combo_variations` legacy UPos como FONTE
 * CANÔNICA pra composição de kits/produtos compostos. O JSON legacy é
 * mantido (NÃO removido) como fallback de coexistência V1 (SPEC §4.1 + D1).
 *
 * Tabela permite multi-level (componente pode ser ele mesmo um kit),
 * componentes opcionais, substituições e ordenação visual.
 *
 * Multi-tenant Tier 0 (ADR 0093) — business_id obrigatório + global scope no Model.
 *
 * Idempotente (SPEC §9 + Schema::hasTable guard).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_bom')) {
            return;
        }

        Schema::create('product_bom', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id'); // Tier 0 — escopo multi-tenant
            $t->unsignedInteger('parent_product_id');      // FK products.id
            $t->unsignedInteger('parent_variation_id')->nullable(); // FK variations.id (kit per variação)
            $t->unsignedInteger('component_product_id');   // FK products.id
            $t->unsignedInteger('component_variation_id')->nullable(); // FK variations.id

            $t->decimal('qty_required', 22, 4)->default(1); // mesma precisão de variation_location_details.qty_available
            $t->boolean('is_optional')->default(false);
            $t->boolean('allow_substitution')->default(false);
            $t->text('notes')->nullable();
            $t->integer('sort_order')->default(0);

            $t->timestamps();

            // Index: lookup primário "componentes deste produto" (BomResolver::resolve())
            $t->index(['business_id', 'parent_product_id'], 'pbom_biz_parent_idx');

            // Index: lookup reverso "em quais kits este componente aparece" (US-INV-002 UI)
            $t->index(['business_id', 'component_product_id'], 'pbom_biz_component_idx');

            // Index: ordenação visual estável dentro de um kit
            $t->index(['parent_product_id', 'sort_order'], 'pbom_parent_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bom');
    }
};
