<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration comvis_os — Ordens de Serviço de comunicação visual.
 *
 * Representa o fluxo produtivo pós-aprovação do orçamento:
 * arte → producao → finalizando → entrega → instalacao → concluida.
 *
 * Pode ser criada vinculada a um orçamento aprovado (orcamento_id) ou de forma avulsa.
 * Acompanha responsável de produção separado do vendedor.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK — isolamento obrigatório.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('comvis_os', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema — FK constraint)
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('orcamento_id')->nullable(); // FK comvis_orcamentos — null = OS avulsa
            $table->string('numero', 20);                           // OS-2026-00001
            $table->enum('status_etapa', [
                'arte', 'producao', 'finalizando', 'entrega', 'instalacao', 'concluida', 'cancelada',
            ])->default('arte');
            $table->date('data_inicio')->nullable();
            $table->date('data_prazo')->nullable();
            $table->date('data_conclusao')->nullable();
            $table->unsignedInteger('vendedor_id')->nullable();
            $table->unsignedInteger('responsavel_producao_id')->nullable();
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'numero'], 'uq_comvis_os_business_numero');
            $table->index('business_id', 'idx_comvis_os_business');
            $table->index(['business_id', 'status_etapa'], 'idx_comvis_os_business_etapa');
            $table->index('data_prazo', 'idx_comvis_os_data_prazo');

            $table->foreign('business_id', 'fk_comvis_os_business')
                  ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('orcamento_id', 'fk_comvis_os_orcamento')
                  ->references('id')->on('comvis_orcamentos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comvis_os');
    }
};
