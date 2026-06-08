<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration cv_instalacoes_catalogo — catálogo de tipos de instalação.
 *
 * Schema SPEC §12.1:
 * - preco_base + preco_m2 + preco_km cobrem composição de cálculo
 *   (fachada simples R$ 80 base, +R$ 5/m², +R$ 1,20/km deslocamento)
 * - exige_nr35 flag pra checklist trabalho em altura ≥2m (US-COMVIS-007)
 * - ferramentas_necessarias_json snapshot da lista (escada, andaime, parafusadeira, EPI)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1 US-COMVIS-007
 * @see memory/requisitos/ComunicacaoVisual/ROADMAP.md Fase 1 §1.4
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_instalacoes_catalogo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');

            $table->string('nome', 150);
            $table->decimal('preco_base', 10, 2)->default(0);
            $table->decimal('preco_m2', 8, 2)->default(0);
            $table->decimal('preco_km', 8, 2)->default(0);
            $table->boolean('exige_nr35')->default(false);
            $table->json('ferramentas_necessarias_json')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id', 'idx_cv_inst_cat_business');
            $table->index(['business_id', 'ativo'], 'idx_cv_inst_cat_business_ativo');

            $table->foreign('business_id', 'fk_cv_inst_cat_business')
                  ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_instalacoes_catalogo');
    }
};
