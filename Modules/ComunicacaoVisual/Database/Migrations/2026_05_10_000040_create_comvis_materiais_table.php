<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration comvis_materiais — catálogo de materiais para comunicação visual.
 *
 * Armazena materiais como lona, vinil adesivo, ACM, MDF, plotter de vinil, etc.
 * Base para cálculo de m² em orçamentos (US-COMVIS-001).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE — cada business tem seu próprio catálogo.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('comvis_materiais', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema — FK constraint)
            $table->unsignedInteger('business_id');
            $table->string('nome', 150);
            $table->string('categoria', 50);  // lona, vinil_adesivo, acm, mdf, plotter_vinil, etc
            $table->enum('unidade', ['m2', 'unidade', 'metro_linear'])->default('m2');
            $table->integer('gramatura_g_m2')->nullable();       // só pra lona/vinil
            $table->decimal('preco_custo_m2', 10, 2)->default(0);
            $table->decimal('preco_venda_m2', 10, 2)->default(0);
            $table->decimal('estoque_minimo_m2', 10, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('business_id', 'idx_comvis_mat_business');
            $table->index(['business_id', 'ativo'], 'idx_comvis_mat_business_ativo');

            $table->foreign('business_id', 'fk_comvis_mat_business')
                  ->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comvis_materiais');
    }
};
