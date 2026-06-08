<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration cv_substratos — catálogo de substratos pra comunicação visual.
 *
 * Schema definido em SPEC §12.1 (memory/requisitos/ComunicacaoVisual/SPEC.md):
 * - Categorias cobrem 8 tipos (lona/vinil/adesivo/acm/tela/mdf/neon/letra_caixa)
 * - gramatura_g_m2 nullable (relevante apenas em lona/vinil)
 * - tributação por substrato (ncm/cfop/csosn) pra wizard onboarding CNAE 1813
 *   (US-COMVIS-006 — sem precisar contador configurar 80 produtos)
 * - minimo_m2 evita venda 0,1m² sangrando margem (regra Calcgraf/Mubisys/Zênite)
 *
 * Convive com legacy `comvis_materiais` (não conflita) — `cv_*` é o prefixo
 * canon SPEC §12.1, `comvis_*` é prefixo legacy Sprint 1 anterior. Migration
 * factory US-COMVIS-NEW-014 (Sprint 2+) decide caminho de unificação.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE — cada gráfica tem seu catálogo de substratos.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1
 * @see memory/requisitos/ComunicacaoVisual/ROADMAP.md Fase 1 §1.4
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_substratos', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema — FK constraint)
            $table->unsignedInteger('business_id');

            $table->string('nome', 150);
            $table->enum('categoria', [
                'lona',
                'vinil',
                'adesivo',
                'acm',
                'tela',
                'mdf',
                'neon',
                'letra_caixa',
                'outro',
            ])->default('outro');
            $table->integer('gramatura_g_m2')->unsigned()->nullable();  // só pra lona/vinil
            $table->decimal('preco_custo_m2', 10, 2)->default(0);
            $table->decimal('preco_venda_m2', 10, 2)->default(0);
            $table->decimal('minimo_m2', 8, 3)->nullable();             // ex: 0,5m² mesmo se peça é menor

            // Tributação CNAE 1813 (US-COMVIS-006)
            $table->string('ncm', 10)->nullable();
            $table->string('cfop_padrao', 4)->nullable();
            $table->string('csosn_padrao', 3)->nullable();

            $table->unsignedBigInteger('fornecedor_id')->nullable();    // FK contacts (fornecedor padrão)
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id', 'idx_cv_subs_business');
            $table->index(['business_id', 'ativo'], 'idx_cv_subs_business_ativo');
            $table->index(['business_id', 'categoria'], 'idx_cv_subs_business_categoria');

            $table->foreign('business_id', 'fk_cv_subs_business')
                  ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_substratos');
    }
};
