<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration cv_ordens_producao — Ordem de Produção CV (canônica FSM ADR 0143).
 *
 * Schema SPEC §12.1 (tabela transacional principal):
 * - 1 OS = 1 venda (transaction_id FK transactions UltimatePOS, nullable durante draft)
 * - current_stage_id FK sale_process_stages (FSM Pipeline canônico — ADR 0143)
 * - area_m2 GENERATED ALWAYS AS (largura_m * altura_m * qtd) — calc server-side
 * - acabamento_json snapshot catalog (cv_acabamentos refs + qtd)
 * - instalacao_tipo ENUM (cliente_busca pula stage aguardando_instalacao)
 * - endereco_instalacao_json snapshot momento agendamento
 * - equipamentos_necessarios_json snapshot ferramentas (NR-35)
 * - commission_distribution_json multi-papel (vendedor/designer/instalador — §14)
 * - prazo_prometido (mapeia PROJETO_DT_FIM Delphi — _LICOES-CRITICAS §3)
 * - arte_url + arte_aprovada_em (workflow US-COMVIS-NEW-004)
 *
 * IMPORTANTE: `current_stage_id` é gateway obrigatório via ExecuteStageActionService
 * (FSM canon ADR 0143). Trait `GuardsFsmTransitions` bloqueia UPDATE direto.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see app/Domain/Fsm/Concerns/GuardsFsmTransitions.php
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_ordens_producao', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');

            $table->string('codigo', 30);                                // OP-CV-YYYY-NNNNN
            $table->unsignedBigInteger('orcamento_id')->nullable();      // FK cv_orcamentos OR legacy comvis_orcamentos
            $table->unsignedBigInteger('contato_id')->nullable();        // FK contacts (cliente final)
            $table->unsignedBigInteger('transaction_id')->nullable();    // FK transactions UPos (1 OS = 1 venda)

            // FSM canon ADR 0143
            $table->unsignedBigInteger('current_stage_id')->nullable();  // FK sale_process_stages

            // Substrato + dimensões
            $table->unsignedBigInteger('substrato_id')->nullable();      // FK cv_substratos
            $table->decimal('largura_m', 8, 3)->nullable();
            $table->decimal('altura_m', 8, 3)->nullable();
            $table->integer('qtd')->unsigned()->default(1);
            $table->decimal('area_m2', 10, 3)->nullable();               // calc server-side

            // Acabamento + instalação (JSON snapshots — promover quando >100 OS/m, SPEC §12.2)
            $table->json('acabamento_json')->nullable();
            $table->enum('instalacao_tipo', [
                'cliente_busca',
                'fachada_simples',
                'fachada_andaime',
                'fachada_nr35',
                'entrega_apenas',
            ])->default('cliente_busca');
            $table->json('endereco_instalacao_json')->nullable();
            $table->json('equipamentos_necessarios_json')->nullable();

            // Workflow arte (US-COMVIS-NEW-004)
            $table->string('arte_url', 500)->nullable();
            $table->timestamp('arte_aprovada_em')->nullable();

            // Prazos
            $table->date('prazo_prometido')->nullable();                 // _LICOES-CRITICAS §3 (PROJETO_DT_FIM Delphi)
            $table->timestamp('estimated_completion')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Comissão multi-papel (SPEC §14)
            $table->json('commission_distribution_json')->nullable();

            // Totais
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('extras', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'codigo'], 'uq_cv_op_business_codigo');
            $table->index('business_id', 'idx_cv_op_business');
            $table->index(['business_id', 'current_stage_id'], 'idx_cv_op_business_stage');
            $table->index(['business_id', 'substrato_id'], 'idx_cv_op_business_substrato');
            $table->index(['business_id', 'contato_id'], 'idx_cv_op_business_contato');
            $table->index('prazo_prometido', 'idx_cv_op_prazo_prometido');

            $table->foreign('business_id', 'fk_cv_op_business')
                  ->references('id')->on('business')->cascadeOnDelete();

            // FK current_stage_id é nullable + ON DELETE SET NULL pra permitir
            // remoção controlada de stages (rollback FSM seeder)
            $table->foreign('current_stage_id', 'fk_cv_op_stage')
                  ->references('id')->on('sale_process_stages')->nullOnDelete();

            $table->foreign('substrato_id', 'fk_cv_op_substrato')
                  ->references('id')->on('cv_substratos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cv_ordens_producao', function (Blueprint $table) {
            $table->dropForeign('fk_cv_op_business');
            $table->dropForeign('fk_cv_op_stage');
            $table->dropForeign('fk_cv_op_substrato');
        });
        Schema::dropIfExists('cv_ordens_producao');
    }
};
