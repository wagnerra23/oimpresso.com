<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Omnichannel schema polimórfico — channels + conversations + messages.
 *
 * Substitui long-term `whatsapp_business_phones` / `whatsapp_conversations` /
 * `whatsapp_messages` (ADR 0135). Tabelas Whatsapp legacy ficam intocadas
 * neste PR — drivers/jobs/webhooks continuam funcionando. Refactor pra usar
 * Channel/Conversation/Message vai num PR B separado (US-WA-057+).
 *
 * Wagner 2026-05-11: zero backfill — dados Whatsapp atuais eram teste.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope nos Models.
 *
 * Credenciais per-tipo (meta_*, zapi_*, baileys_*, email_*, ml_*) vão no
 * `config_json` cifrado pelo Model (encrypted cast Laravel).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── channels ──────────────────────────────────────────────
        // 1 row por canal cadastrado no business. Tipos discriminam driver
        // e shape de config_json. Mesmo business pode ter N canais do mesmo
        // tipo (ex: 2 números Baileys, 1 Z-API, 1 Instagram).
        Schema::create('channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->uuid('channel_uuid')->unique()
                ->comment('usado em webhook URL e Centrifugo channel granular');
            $table->string('label', 80)
                ->comment('apelido livre: Comercial / Suporte / @lojaoficial / vendas@');

            // Tipo discrimina driver E shape de config_json.
            // Evolution PROIBIDO permanente (ADR 0094 Tier 0).
            // Twitter rejeitado (ADR 0135 não-objetivo).
            $table->enum('type', [
                'whatsapp_meta',
                'whatsapp_zapi',
                'whatsapp_baileys',
                'instagram',
                'messenger',
                'email_imap',
                'email_smtp',
                'mercadolivre',
            ]);

            $table->enum('status', ['active', 'inactive', 'setup', 'disconnected', 'banned'])
                ->default('setup');

            // Identidade pública após pareamento (E.164, email, fb_page_id, ml_seller_id)
            $table->string('display_identifier', 100)->nullable()
                ->comment('preenchido após primeiro check bem-sucedido');

            // Credenciais per-tipo encrypted no Model (cast `encrypted` Laravel)
            $table->text('config_json')->nullable()
                ->comment('encrypted JSON — shape depende de type (meta_phone_number_id, zapi_instance_id, baileys_phone_e164, email_host, ml_oauth_token, etc)');

            // Roteamento de eventos automáticos (ADR 0117 Q2 — cada canal decide)
            $table->boolean('handles_repair_status')->default(false);
            $table->boolean('handles_billing')->default(false);
            $table->boolean('handles_jana_bot')->default(true);
            $table->boolean('handles_outbound_default')->default(false);

            $table->boolean('bot_enabled')->default(false);

            // Templates per-channel (cross-driver) — NULL pra canais que não usam HSM (Insta/email/ML)
            $table->string('template_repair_ready_name', 64)->nullable();
            $table->string('template_repair_waiting_parts_name', 64)->nullable();
            $table->string('template_billing_due_name', 64)->nullable();
            $table->string('template_billing_paid_name', 64)->nullable();

            // Channel health (atualizado por job de health-check)
            $table->enum('channel_health', ['healthy', 'degraded', 'disconnected', 'banned', 'never_checked'])
                ->default('never_checked');
            $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('last_health_message')->nullable();

            // LGPD per-channel (drivers não-oficiais exigem aceite explícito)
            $table->timestamp('lgpd_acknowledged_at')->nullable();
            $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();

            $table->timestamps();

            // UNIQUE composto — anti-duplicate (mesmo display_identifier 1× por business+type)
            // display_identifier pode ser NULL durante setup — UNIQUE permite múltiplos NULLs em MySQL
            $table->unique(['business_id', 'type', 'display_identifier'], 'channels_biz_type_id_unq');
            $table->index('business_id', 'channels_biz_idx');
            $table->index(['type', 'channel_health'], 'channels_type_health_idx');
        });

        // ─── conversations ─────────────────────────────────────────
        // 1 conversa = tripla (business, channel, customer_external_id).
        // customer_external_id é polimórfico: E.164 phone, fb_user_id, email,
        // ml_buyer_id, etc. — string genérica indexada por (channel_id, ext_id).
        Schema::create('conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('channel_id');
            $table->unsignedInteger('contact_id')->nullable()
                ->comment('contacts.id se já cadastrado, NULL se provisional');

            $table->string('customer_external_id', 150)
                ->comment('E.164 phone | fb_user_id | email | ml_buyer_id — discriminado pelo channel.type');
            $table->string('contact_name', 120)->nullable()
                ->comment('cache — UI mostra sem N+1 query contacts');

            $table->enum('status', ['open', 'awaiting_human', 'resolved', 'archived'])
                ->default('open');
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->boolean('bot_handling')->default(false);

            $table->timestamp('last_inbound_at')->nullable()
                ->comment('última msg cliente — janela 24h Meta para WA');
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable()
                ->comment('max(in, out) — sort lista');

            $table->unsignedInteger('unread_count')->default(0);

            $table->timestamps();

            $table->unique(
                ['business_id', 'channel_id', 'customer_external_id'],
                'conv_biz_ch_ext_uniq'
            );
            $table->index(['business_id', 'last_message_at'], 'conv_biz_lastmsg_idx');
            $table->index(['business_id', 'status'], 'conv_biz_status_idx');
            $table->index(['channel_id', 'status'], 'conv_ch_status_idx');

            // FK soft (sem cascade — preserva mensagens se canal sumir, anti-perda)
            // CASCADE delete só via comando explícito (ADR governance future)
        });

        // ─── messages ──────────────────────────────────────────────
        // Append-only — 1 row por mensagem in/out. Updates só em status/
        // failed_reason (delivery flow).
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('conversation_id');

            $table->enum('direction', ['inbound', 'outbound']);

            // Provider = denormalizado do channel.type — facilita índice
            // e queries cross-canal sem JOIN.
            $table->string('provider', 30)
                ->comment('whatsapp_meta|whatsapp_zapi|whatsapp_baileys|instagram|messenger|email_imap|email_smtp|mercadolivre');
            $table->string('provider_message_id', 128)->nullable()
                ->comment('wamid.XYZ (Meta) | messageId (Z-API/Baileys) | ig_dm_id | Message-ID header (email) | ml_message_id');

            // Tipo cresce pra cobrir email subject + ML question
            $table->enum('type', [
                'text', 'template', 'image', 'document', 'audio',
                'video', 'interactive', 'location', 'contacts',
                'email', 'ml_question', 'ml_answer',
            ])->default('text');
            $table->string('template_name', 64)->nullable();
            $table->string('subject', 255)->nullable()
                ->comment('só email');
            $table->text('body')->nullable();
            $table->json('payload')->nullable()
                ->comment('raw provider payload — auditoria + reprocess');

            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed', 'received']);
            $table->string('failed_reason', 255)->nullable();

            $table->unsignedInteger('sender_user_id')->nullable()
                ->comment('só outbound humano');
            $table->enum('sender_kind', ['human', 'bot', 'system'])->nullable();

            $table->unsignedInteger('cost_centavos')->nullable()
                ->comment('custo provider quando aplicável (Meta janela 24h, ML messaging fee)');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
            $table->index(['business_id', 'conversation_id', 'created_at'], 'msgs_biz_conv_idx');
            $table->index(['business_id', 'status', 'created_at'], 'msgs_biz_status_idx');
            $table->index(['provider', 'created_at'], 'msgs_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('channels');
    }
};
