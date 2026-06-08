<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-CSAT — tabela `whatsapp_csat_responses` (PR-6 CYCLE-07).
 *
 * Quando atendente marca conversa como `resolved`, `CsatDispatcher` envia
 * mensagem automática ("Como você avalia este atendimento? 1-5") e cria
 * 1 row aqui com `score=null` aguardando resposta inbound.
 *
 * `ChannelBaileysWebhookController::handleMessage` (após `firstOrCreate`
 * da Message inbound) checa se há row pending pra conversation e tenta
 * parsear via `CsatResponseParser::tryParse()`. Acertou → popula `score`,
 * `comment`, `response_message_id`, `responded_at`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — `business_id` indexado;
 * Model `CsatResponse` aplica global scope via `HasBusinessScope`.
 *
 * Concorrente referência: Chatwoot CSAT, Take Blip Pesquisa Pós-Atendimento,
 * Octadesk NPS. Mercado pad 1-5 estrelas (com cauda comentário opcional).
 *
 * Idempotente — `Schema::hasTable` guard.
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_csat_responses')) {
            return;
        }

        Schema::create('whatsapp_csat_responses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('resolved_message_id')
                ->comment('msg outbound que disparou a pergunta CSAT');
            $table->unsignedBigInteger('response_message_id')->nullable()
                ->comment('msg inbound onde cliente respondeu (nota)');
            $table->unsignedTinyInteger('score')->nullable()
                ->comment('1-5; null=pending resposta');
            $table->text('comment')->nullable()
                ->comment('cauda livre opcional ("5 obrigado")');
            $table->unsignedInteger('resolved_by_user_id')->nullable()
                ->comment('atendente que marcou conversa como resolved');
            $table->timestamp('asked_at')
                ->comment('quando o dispatch da msg CSAT ocorreu');
            $table->timestamp('responded_at')->nullable()
                ->comment('quando parser populou score');
            $table->timestamps();

            // Job query / dashboard usa (business_id, created_at).
            $table->index(['business_id', 'created_at'], 'csat_biz_created_idx');
            // Webhook parser query (conversation_id, score=null, asked_at desc).
            $table->index('conversation_id', 'csat_conv_idx');
            // Idempotência dispatchOnResolve (busca pending últimas 24h por conv).
            $table->index(['conversation_id', 'score', 'asked_at'], 'csat_conv_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_csat_responses');
    }
};
