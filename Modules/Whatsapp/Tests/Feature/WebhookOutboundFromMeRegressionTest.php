<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;

uses(Tests\TestCase::class);

/**
 * P0 #4 — Anti-regressão pro fix Multi-Device unified inbox (PR #688).
 *
 * Contexto:
 *   - Pré-PR #688: daemon Baileys filtrava `key.fromMe=true` em
 *     `handleIncomingMessage` (Instance.ts:443) → mensagens enviadas pelo
 *     próprio WhatsApp Web/celular do operador NÃO chegavam no Inbox
 *     oimpresso. Cliente respondia via celular, atendente via oimpresso —
 *     unified inbox quebrava.
 *   - PR #688: removeu o filtro → daemon emite tudo via webhook → controller
 *     persiste com `direction='outbound'`, `status='sent'`, `sender_kind='human'`.
 *     Idempotência (US-WA-070 `firstOrCreate` em `business_id`+`provider_message_id`)
 *     garante que se o eco do mesmo provider_message_id chegar de volta via
 *     dedup → no-op gracioso.
 *
 * Bug que esta suite protege:
 *   Próximo refactor no controller pode re-introduzir filtro `if ($fromMe) skip`
 *   silenciosamente (ex: tentando reduzir ruído, ou copiando código legado).
 *   Esses asserts quebram imediatamente se isso voltar.
 *
 * Estilo (imita Hotfix B1 `WebhookMediaExtractTest`):
 *   - Schema::create direto pras 3 tabelas (channels/conversations/messages).
 *   - Bus::fake() + Http::fake() pra não disparar DownloadMediaJob/Centrifugo.
 *   - Instancia controller via new + Request::create — sem HTTP real.
 *
 * @see Modules/Whatsapp/daemon-node/src/baileys/Instance.ts (PR #688 fromMe filter removed)
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
beforeEach(function () {
    Bus::fake();
    Http::fake();

    foreach (['messages', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->string('display_identifier', 100)->nullable();
        $table->text('config_json')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_external_id', 150);
        $table->string('contact_name', 120)->nullable();
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->boolean('bot_handling')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->string('last_message_preview', 120)->nullable();
        $table->string('last_message_direction', 20)->nullable();
        $table->boolean('is_blocked')->default(false);
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30);
        $table->string('provider_message_id', 128)->nullable();
        $table->string('type', 20)->default('text');
        $table->string('template_name', 64)->nullable();
        $table->string('subject', 255)->nullable();
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->string('failed_reason', 255)->nullable();
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->string('sender_kind', 20)->nullable();
        $table->unsignedInteger('cost_centavos')->nullable();
        $table->boolean('is_internal_note')->default(false);
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedSmallInteger('media_duration_s')->nullable();
        $table->string('media_thumbnail_url', 500)->nullable();
        $table->text('media_transcription')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->string('media_download_status', 30)->default('pending');
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamp('media_download_last_attempt_at')->nullable();
        $table->string('media_download_failed_reason', 255)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });
});

function makeFromMeWebhookChannel(string $uuid): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

function postFromMeWebhook(string $uuid, array $data): \Illuminate\Http\JsonResponse
{
    $request = Request::create("/api/atendimento/channels/baileys/{$uuid}", 'POST', [
        'event' => 'message',
        'data' => $data,
    ]);
    $controller = new ChannelBaileysWebhookController();
    return $controller->handle($request, $uuid);
}

it('R-WA-688-001 — messages.upsert key.fromMe=true persiste como outbound/sent/human', function () {
    $channel = makeFromMeWebhookChannel('aaaa-fffe-0000-0000-000000000001');

    $response = postFromMeWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => true,
            'id' => 'WA_FROMME_001',
        ],
        'message' => [
            'conversation' => 'Olá, posso te ajudar?',
        ],
        'push_name' => 'Atendente Suporte',
    ]);

    expect($response->getStatusCode())->toBe(200);

    $messages = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_FROMME_001')
        ->get();

    expect($messages)->toHaveCount(1);

    $msg = $messages->first();
    expect($msg->business_id)->toBe(1);
    expect($msg->direction)->toBe('outbound');
    expect($msg->status)->toBe('sent');
    expect($msg->sender_kind)->toBe('human');
    expect($msg->provider_message_id)->toBe('WA_FROMME_001');
    expect($msg->body)->toBe('Olá, posso te ajudar?');
    expect($msg->conversation_id)->not->toBeNull();
    expect($msg->provider)->toBe(Channel::TYPE_WHATSAPP_BAILEYS);

    // Conversation criada coerente — last_outbound_at preenchido, unread NÃO incrementado
    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $msg->conversation_id)
        ->firstOrFail();
    expect($conv->business_id)->toBe(1);
    expect($conv->customer_external_id)->toBe('+5548999872822');
    expect($conv->unread_count)->toBe(0); // outbound não conta como unread
    expect($conv->last_outbound_at)->not->toBeNull();
    expect($conv->last_message_at)->not->toBeNull();
});

it('R-WA-688-002 — idempotência fromMe: webhook 2× mesmo provider_message_id = 1 row', function () {
    $channel = makeFromMeWebhookChannel('aaaa-fffe-0000-0000-000000000002');

    $payload = [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => true,
            'id' => 'WA_FROMME_DUP_002',
        ],
        'message' => [
            'conversation' => 'Mensagem outbound de teste',
        ],
        'push_name' => 'Atendente',
    ];

    // 1ª entrega
    $r1 = postFromMeWebhook($channel->channel_uuid, $payload);
    expect($r1->getStatusCode())->toBe(200);

    // 2ª entrega (daemon replay/reconnect)
    $r2 = postFromMeWebhook($channel->channel_uuid, $payload);
    expect($r2->getStatusCode())->toBe(200);

    // DB state: apenas 1 row (firstOrCreate idempotente)
    $count = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_FROMME_DUP_002')
        ->count();
    expect($count)->toBe(1);

    // A 2ª entrega NÃO incrementa last_outbound_at de novo nem unread.
    // Verifica via flag note do payload de resposta.
    $payload2 = $r2->getData(true);
    expect($payload2['note'] ?? null)->toBe('message_duplicate_ignored');
});

it('R-WA-688-003 — fromMe=false continua persistindo como inbound (não-regressão)', function () {
    $channel = makeFromMeWebhookChannel('aaaa-fffe-0000-0000-000000000003');

    postFromMeWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_INBOUND_003',
        ],
        'message' => [
            'conversation' => 'Oi, preciso de ajuda',
        ],
        'push_name' => 'Cliente Larissa',
    ]);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_INBOUND_003')
        ->firstOrFail();

    expect($msg->direction)->toBe('inbound');
    expect($msg->status)->toBe('received');
    expect($msg->sender_kind)->toBeNull();

    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $msg->conversation_id)
        ->firstOrFail();
    expect($conv->unread_count)->toBe(1); // inbound incrementa
    expect($conv->last_inbound_at)->not->toBeNull();
});
