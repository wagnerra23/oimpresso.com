<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_categories — 1ª camada da taxonomia operacional.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §5
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id obrigatório + FK CASCADE.
 *
 * Categorias default seedadas em KbCategoriesSeeder (7 categorias + governance).
 * `hue` é o tom OKLCH chroma 0-360 usado pelo design system Cowork V2.
 *
 * Cria PRIMEIRO (antes de kb_subcategories e kb_nodes) porque é referenciado
 * pelas duas via FK SET NULL.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_categories')) {
            return;
        }

        Schema::create('kb_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned — bate com business.id (UltimatePOS legacy schema, FK).
            $table->unsignedInteger('business_id');
            $table->string('slug', 60);
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('hue')->default(240)->comment('0-360 OKLCH chroma');
            $table->string('icon', 80)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'slug'], 'uq_kb_cat_business_slug');
            $table->index(['business_id', 'sort_order'], 'idx_kb_cat_business_sort');

            $table->foreign('business_id', 'fk_kb_cat_business')
                ->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_categories');
    }
};
