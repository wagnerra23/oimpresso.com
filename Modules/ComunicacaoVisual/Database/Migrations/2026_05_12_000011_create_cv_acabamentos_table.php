<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration cv_acabamentos — catálogo de acabamentos (corte, ilhós, costura, etc).
 *
 * Schema SPEC §12.1: tipo ENUM define como o preço escala
 *   m_linear (corte vinco)
 *   unitario (ilhós, perfuração)
 *   m2 (laminação)
 *   fixo (taxa setup arte)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1
 * @see memory/requisitos/ComunicacaoVisual/ROADMAP.md Fase 1 §1.4
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_acabamentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');

            $table->string('nome', 150);
            $table->enum('tipo', [
                'm_linear',
                'unitario',
                'm2',
                'fixo',
            ])->default('unitario');
            $table->decimal('preco', 8, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id', 'idx_cv_acab_business');
            $table->index(['business_id', 'ativo'], 'idx_cv_acab_business_ativo');

            $table->foreign('business_id', 'fk_cv_acab_business')
                  ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_acabamentos');
    }
};
