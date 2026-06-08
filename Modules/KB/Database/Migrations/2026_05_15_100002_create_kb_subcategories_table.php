<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_subcategories — 2ª camada da taxonomia.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §5
 *
 * `auto_match` é uma regra JSON pra derivar subcategoria automaticamente
 * com base em campos do kb_node (ex: equip=Roland VS-540 → subcat plotter).
 * Mesma semântica do KB_SUBCATS Cowork.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_subcategories')) {
            return;
        }

        Schema::create('kb_subcategories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('category_id');
            $table->string('slug', 60);
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->json('auto_match')->nullable()
                ->comment('ex: {field: "equip", op: "=", value: "Roland VS-540"}');
            $table->timestamps();

            $table->unique(['business_id', 'category_id', 'slug'], 'uq_kb_sub_biz_cat_slug');
            $table->index(['business_id', 'category_id'], 'idx_kb_sub_biz_cat');

            $table->foreign('business_id', 'fk_kb_sub_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('category_id', 'fk_kb_sub_category')
                ->references('id')->on('kb_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_subcategories');
    }
};
