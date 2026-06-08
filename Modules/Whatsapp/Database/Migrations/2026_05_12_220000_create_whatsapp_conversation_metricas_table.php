<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-021/041 — Tabela `whatsapp_conversation_metricas`.
 *
 * Snapshot diário agregado de métricas de atendimento omnichannel
 * (CYCLE-07 PR-3). Cada row = uma (business, dia, canal). Canal `null`
 * = agregado do business inteiro.
 *
 * Por que P0 — Constituição §4 "loop fechado por métrica". Sem dashboard
 * vivo, US-WA-049 (A/B testing) e pricing recurring ficam bloqueados.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — `business_id` indexado +
 * global scope no Model. Sem FK pra `business` (legacy UltimatePOS é
 * `unsignedInteger`, ver reference_ultimatepos_integracao).
 *
 * FK CASCADE em `channel_id` → se canal for deletado, métricas dele
 * caem junto. Conversa/messages permanecem pelo schema append-only.
 *
 * Migration idempotente (`Schema::hasTable` guard).
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap P0 #4
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-021/041
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_conversation_metricas')) {
            return;
        }

        Schema::create('whatsapp_conversation_metricas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->date('metric_date');
            $table->unsignedBigInteger('channel_id')->nullable()
                ->comment('null = agregado do business inteiro');
            $table->unsignedInteger('conversations_opened')->default(0);
            $table->unsignedInteger('conversations_resolved')->default(0);
            $table->unsignedInteger('messages_inbound')->default(0);
            $table->unsignedInteger('messages_outbound')->default(0);
            $table->unsignedInteger('avg_first_response_seconds')->nullable()
                ->comment('tempo médio até 1ª resposta humana outbound');
            $table->unsignedInteger('avg_resolution_seconds')->nullable()
                ->comment('tempo médio até conversation.status=resolved');
            $table->unsignedBigInteger('total_cost_centavos')->default(0)
                ->comment('soma messages.cost_centavos do dia');
            $table->timestamps();

            $table->unique(
                ['business_id', 'metric_date', 'channel_id'],
                'wa_metrics_uniq',
            );
            $table->index(['business_id', 'metric_date'], 'wa_metrics_biz_date_idx');

            // FK CASCADE — canal deletado → métricas correspondentes caem
            // junto. Métricas agregadas (channel_id=null) preservadas.
            $table->foreign('channel_id')
                ->references('id')->on('channels')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_metricas');
    }
};
