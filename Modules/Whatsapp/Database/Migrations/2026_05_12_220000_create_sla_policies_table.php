<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CYCLE-07 PR-2 — tabela `sla_policies`.
 *
 * Policies de SLA por business — quando uma conversa Omnichannel passa do
 * `threshold_minutes` configurado sem resposta, o `SlaScanCommand` (hourly
 * via `everyFiveMinutes()` schedule) dispara `action_kind` (alerta
 * Centrifugo / reassign / set_status).
 *
 * Hoje o filtro `inbound_aging` (InboxController) só mostra conversas
 * atrasadas — manager NÃO é avisado quando passa. PR-2 fecha esse gap P0 #2
 * do COMPARATIVO-MERCADO-2026-05-12.md (bloqueia escalar >5 atendentes).
 *
 * **Triggers (`triggers_on`):**
 *   - `first_inbound_no_reply` — primeira msg cliente sem outbound atendente
 *   - `open_aging` — conversa `status=open` há muito tempo desde último inbound
 *   - `awaiting_human_aging` — bot escalou pra fila humana e ninguém pegou
 *
 * **Actions (`action_kind`):**
 *   - `centrifugo_notify` — publish no canal `omnichannel:business:{id}:sla_alerts`
 *   - `reassign` — atribui pra outro user (action_params.to_user_id)
 *   - `set_status` — muda Conversation.status (action_params.status)
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):**
 *   - `business_id` NOT NULL + FK ON DELETE CASCADE
 *   - Index composto (business_id, active) pra varredura rápida do scan job
 *   - Model `SlaPolicy` aplica global scope via `HasBusinessScope`
 *   - `channel_id` / `tag_id` nullable — null = aplica a TODOS canais/tags do business
 *
 * Idempotente — `Schema::hasTable` guard para rodar duas vezes sem erro.
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md (Gap P0 #2)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sla_policies')) {
            return;
        }

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('label', 80)
                ->comment('legível humano — ex "First response 1h"');
            $table->unsignedInteger('threshold_minutes')
                ->comment('tempo (min) sem resposta até disparar action');
            $table->enum('triggers_on', [
                'first_inbound_no_reply',
                'open_aging',
                'awaiting_human_aging',
            ])->comment('condição de disparo — ver SlaEnforcer');
            $table->unsignedBigInteger('channel_id')->nullable()
                ->comment('null = aplica a TODOS canais do business');
            $table->unsignedBigInteger('tag_id')->nullable()
                ->comment('null = aplica a TODAS tags do business');
            $table->enum('action_kind', [
                'centrifugo_notify',
                'reassign',
                'set_status',
            ])->comment('o que faz quando dispara');
            $table->json('action_params')->nullable()
                ->comment('ex {to_user_id:5} pra reassign | {status:"awaiting_human"} pra set_status');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('business_id')
                ->references('id')->on('business')
                ->onDelete('cascade');

            // Scan job pega rows ativas por business — index composto cobre
            // 99% das queries (everyFiveMinutes via WHERE business_id=? AND active=true).
            $table->index(['business_id', 'active'], 'sla_policies_biz_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
