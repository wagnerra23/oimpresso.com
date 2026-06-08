<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W27 — Crm Deal Pipeline Kanban (Pipedrive/HubSpot-like).
 *
 * Cria abstração "Deal" pra representar oportunidade de venda em pipeline
 * com 6 stages PT-BR (lead → qualificacao → proposta → negociacao → ganho/perdido).
 *
 * Diferente de Proposal (documento gerado), Deal é a OPORTUNIDADE que pode
 * gerar 0..N proposals durante seu ciclo de vida.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - business_id NOT NULL + indexado + FK cascade
 *   - HasBusinessScope no Model (defesa-em-profundidade)
 *
 * Best practices 2026 (Pipedrive/HubSpot):
 *   - 5-7 stages enum (sweet spot — mais fica unreadable)
 *   - valor_estimado + data_fechamento_prevista pra forecast weighted
 *   - metadata JSON pra rotting/probability custom per-business (futuro)
 *   - softDeletes pra audit LGPD (não hard-delete histórico)
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Crm/Entities/Deal.php
 * @see Modules/Crm/Services/DealPipelineService.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_deals')) {
            return; // idempotente
        }

        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id')->index();
            $table->foreign('business_id')
                ->references('id')->on('business')
                ->onDelete('cascade');

            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('proposal_id')->nullable()->index();

            $table->string('titulo', 191);

            $table->enum('stage', [
                'lead',
                'qualificacao',
                'proposta',
                'negociacao',
                'ganho',
                'perdido',
            ])->default('lead')->index();

            $table->decimal('valor_estimado', 12, 2)->default(0);
            $table->date('data_fechamento_prevista')->nullable()->index();

            $table->unsignedBigInteger('owner_user_id')->index();

            // Metadata JSON pra extensão (rotting_days, probability custom, lost_reason, etc).
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índice composto pra Kanban query (business + stage).
            $table->index(['business_id', 'stage'], 'idx_crm_deals_biz_stage');
            // Índice pra forecast (business + data prevista + stage).
            $table->index(['business_id', 'data_fechamento_prevista', 'stage'], 'idx_crm_deals_forecast');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};
