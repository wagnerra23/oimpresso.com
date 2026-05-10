<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration comvis_apontamentos — Spool Plotter / Apontamento de Produção.
 *
 * Registra início e fim de execução de itens de uma OS de comunicação visual.
 * Permite rastrear tempo de produção e m² produzido vs orçado (drift detection).
 *
 * Padrão append-only: sem SoftDeletes — cada linha é registro legal de produção.
 * Correções geram novo apontamento via ApontamentoTracker::corrigir().
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK — isolamento obrigatório.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-004
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('comvis_apontamentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema — FK constraint)
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('os_id');                    // FK comvis_os
            $table->unsignedBigInteger('orcamento_item_id')->nullable(); // FK comvis_orcamento_itens — null = OS sem item específico
            $table->unsignedInteger('operador_id');                 // FK users.id — quem apontou

            $table->string('maquina', 80)->nullable();              // ex: 'plotter-roland-1', 'corte-laser-2' — livre
            $table->timestamp('iniciado_em');                       // início da produção
            $table->timestamp('finalizado_em')->nullable();         // null = em andamento
            $table->unsignedInteger('duracao_segundos')->nullable(); // calculado server-side ao finalizar

            $table->decimal('m2_produzido', 10, 3)->nullable();    // operador informa ao finalizar
            $table->decimal('m2_orcado', 10, 3)->nullable();       // snapshot de orcamento_item.area_m2 ao iniciar
            $table->decimal('drift_percent', 6, 2)->nullable();    // ((m2_prod - m2_orc) / m2_orc) * 100, null se m2_orc=0

            $table->text('observacoes')->nullable();
            $table->timestamps();

            // Índices de consulta
            $table->index('business_id', 'idx_comvis_apt_business');
            $table->index(['business_id', 'os_id'], 'idx_comvis_apt_business_os');
            $table->index('operador_id', 'idx_comvis_apt_operador');
            $table->index('iniciado_em', 'idx_comvis_apt_iniciado_em');

            // FK: comvis_os ON DELETE CASCADE (OS deletada → apontamentos deletados)
            $table->foreign('os_id', 'fk_comvis_apt_os')
                  ->references('id')->on('comvis_os')->onDelete('cascade');

            // FK: comvis_orcamento_itens ON DELETE SET NULL (item deletado → apontamento mantido sem vínculo)
            $table->foreign('orcamento_item_id', 'fk_comvis_apt_orcamento_item')
                  ->references('id')->on('comvis_orcamento_itens')->onDelete('set null');

            // FK: business
            $table->foreign('business_id', 'fk_comvis_apt_business')
                  ->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('comvis_apontamentos', function (Blueprint $table) {
            // Drop FKs antes da tabela (evita erro de constraint)
            $table->dropForeign('fk_comvis_apt_os');
            $table->dropForeign('fk_comvis_apt_orcamento_item');
            $table->dropForeign('fk_comvis_apt_business');
        });

        Schema::dropIfExists('comvis_apontamentos');
    }
};
