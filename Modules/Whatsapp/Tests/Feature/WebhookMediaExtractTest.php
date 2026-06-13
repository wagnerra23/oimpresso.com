<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;

uses(Tests\TestCase::class);

/**
 * Hotfix B1 (2026-05-12) — guard test pro extract de mídia aninhado
 * em payload Baileys raw quando daemon NÃO normaliza (`data.media_url` etc).
 *
 * Bug em prod biz=1 oimpresso.com:
 *   - 89 messages sem body
 *   - 0 com `media_url` preenchido
 *   - 0 com `media_mime` preenchido
 *   - áudios chegavam como type=audio sem nenhuma meta visível
 *
 * Webhook precisa ler `imageMessage`/`audioMessage`/`videoMessage`/
 * `documentMessage`/`stickerMessage` aninhado e extrair:
 *   - mimetype  → media_mime (sanitized — strip codec "audio/ogg; codecs=opus"
 *                 vira "audio/ogg")
 *   - fileLength → media_size_bytes
 *   - seconds   → media_duration_s (audio/video)
 *   - fileName  → media_filename (document)
 *
 * `media_url` SEMPRE null nesse path — URL Baileys é cripto `.enc` + mediaKey.
 * Daemon C futuro vai decrypt + popular. UI B2 trata esse estado de
 * "aguardando download".
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // DownloadMediaJob é dispatchado quando media_url vem flat — fake pra
    // não fazer cURL real (teste "precedence" tenta resolver hostname).
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
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });
});

function makeMediaWebhookChannel(string $uuid = 'bbbb-0000-0000-0000-webhook'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

function postMediaWebhook(string $uuid, array $data, string $event = 'message'): \Illuminate\Http\JsonResponse
{
    $request = Request::create("/api/atendimento/channels/baileys/{$uuid}", 'POST', [
        'event' => $event,
        'data' => $data,
    ]);
    $controller = new ChannelBaileysWebhookController();
    return $controller->handle($request, $uuid);
}

it('audio Baileys payload — extrai mimetype/fileLength/seconds aninhados', function () {
    $channel = makeMediaWebhookChannel('bbbb-0000-0000-0000-audio1');

    $response = postMediaWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_MSG_AUDIO_001',
        ],
        'message' => [
            'audioMessage' => [
                'url' => 'https://mmg.whatsapp.net/v/t62.7117-24/cripto.enc?ccb=11-4',
                'mimetype' => 'audio/ogg; codecs=opus',
                'fileLength' => '56477',
                'seconds' => 27,
                'ptt' => true,
                'mediaKey' => 'fake-key-base64',
            ],
        ],
        'push_name' => 'Rafael',
        'timestamp' => 1778597889,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_MSG_AUDIO_001')
        ->firstOrFail();

    expect($msg->type)->toBe('audio');
    // MIME sanitized (codec strip)
    expect($msg->media_mime)->toBe('audio/ogg');
    expect($msg->media_size_bytes)->toBe(56477);
    expect($msg->media_duration_s)->toBe(27);
    // URL continua null — daemon decrypt não implementado (B1 só extrai meta)
    expect($msg->media_url)->toBeNull();
});

it('image Baileys payload — caption vira body + mimetype extraído', function () {
    $channel = makeMediaWebhookChannel('bbbb-0000-0000-0000-image1');

    postMediaWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_MSG_IMG_001',
        ],
        'message' => [
            'imageMessage' => [
                'url' => 'https://mmg.whatsapp.net/v/cripto-img.enc',
                'mimetype' => 'image/jpeg',
                'fileLength' => '102400',
                'caption' => 'Foto do produto',
                'mediaKey' => 'fake-key',
            ],
        ],
        'push_name' => 'Cliente',
    ]);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_MSG_IMG_001')
        ->firstOrFail();

    expect($msg->type)->toBe('image');
    expect($msg->body)->toBe('Foto do produto');
    expect($msg->media_mime)->toBe('image/jpeg');
    expect($msg->media_size_bytes)->toBe(102400);
    expect($msg->media_url)->toBeNull();
});

it('document Baileys payload — fileName extraído pra media_filename', function () {
    $channel = makeMediaWebhookChannel('bbbb-0000-0000-0000-doc1');

    postMediaWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_MSG_DOC_001',
        ],
        'message' => [
            'documentMessage' => [
                'url' => 'https://mmg.whatsapp.net/v/cripto-doc.enc',
                'mimetype' => 'application/pdf',
                'fileLength' => '524288',
                'fileName' => 'contrato.pdf',
                'mediaKey' => 'fake-key',
            ],
        ],
    ]);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_MSG_DOC_001')
        ->firstOrFail();

    expect($msg->type)->toBe('document');
    expect($msg->media_mime)->toBe('application/pdf');
    expect($msg->media_filename)->toBe('contrato.pdf');
    expect($msg->media_size_bytes)->toBe(524288);
});

it('video Baileys payload — seconds + mimetype + caption extraídos', function () {
    $channel = makeMediaWebhookChannel('bbbb-0000-0000-0000-vid1');

    postMediaWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_MSG_VID_001',
        ],
        'message' => [
            'videoMessage' => [
                'url' => 'https://mmg.whatsapp.net/v/cripto-vid.enc',
                'mimetype' => 'video/mp4',
                'fileLength' => '2097152',
                'seconds' => 12,
                'caption' => 'Demo do produto',
                'mediaKey' => 'fake-key',
            ],
        ],
    ]);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_MSG_VID_001')
        ->firstOrFail();

    expect($msg->type)->toBe('video');
    expect($msg->body)->toBe('Demo do produto');
    expect($msg->media_mime)->toBe('video/mp4');
    expect($msg->media_duration_s)->toBe(12);
    expect($msg->media_size_bytes)->toBe(2097152);
});

it('payload texto puro — nenhum campo media_* populado', function () {
    $channel = makeMediaWebhookChannel('bbbb-0000-0000-0000-txt1');

    postMediaWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_MSG_TXT_001',
        ],
        'message' => [
            'conversation' => 'Oi, tudo bem?',
        ],
    ]);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_MSG_TXT_001')
        ->firstOrFail();

    expect($msg->type)->toBe('text');
    expect($msg->body)->toBe('Oi, tudo bem?');
    expect($msg->media_mime)->toBeNull();
    expect($msg->media_size_bytes)->toBeNull();
    expect($msg->media_duration_s)->toBeNull();
    expect($msg->media_filename)->toBeNull();
    expect($msg->media_url)->toBeNull();
});

it('daemon normalizado (data.media_url + data.mime) tem precedência sobre proto aninhado', function () {
    $channel = makeMediaWebhookChannel('bbbb-0000-0000-0000-precedence');

    postMediaWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_MSG_PREC_001',
        ],
        // Daemon C normalizou flat (ideal cenário futuro)
        'media_url' => 'whatsapp/1/2026-05/decrypted.ogg',
        'mime' => 'audio/ogg',
        'size_bytes' => 12345,
        'duration_s' => 9,
        'message' => [
            'audioMessage' => [
                'url' => 'https://mmg.whatsapp.net/v/cripto.enc',
                'mimetype' => 'audio/ogg; codecs=opus',
                'fileLength' => '99999', // valor diferente — confirma que flat tem precedência
                'seconds' => 99,
            ],
        ],
    ]);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_MSG_PREC_001')
        ->firstOrFail();

    expect($msg->media_url)->toBe('whatsapp/1/2026-05/decrypted.ogg');
    expect($msg->media_mime)->toBe('audio/ogg');
    expect($msg->media_size_bytes)->toBe(12345);
    expect($msg->media_duration_s)->toBe(9);
});

it('sticker Baileys payload — mimetype extraído (mapeia como image type)', function () {
    $channel = makeMediaWebhookChannel('bbbb-0000-0000-0000-stk1');

    postMediaWebhook($channel->channel_uuid, [
        'key' => [
            'remoteJid' => '5548999872822@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'WA_MSG_STK_001',
        ],
        'message' => [
            'stickerMessage' => [
                'url' => 'https://mmg.whatsapp.net/v/cripto-stk.enc',
                'mimetype' => 'image/webp',
                'fileLength' => '32768',
                'mediaKey' => 'fake-key',
            ],
        ],
    ]);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'WA_MSG_STK_001')
        ->firstOrFail();

    expect($msg->type)->toBe('image'); // sticker → image (mapping no controller)
    expect($msg->media_mime)->toBe('image/webp');
    expect($msg->media_size_bytes)->toBe(32768);
});
