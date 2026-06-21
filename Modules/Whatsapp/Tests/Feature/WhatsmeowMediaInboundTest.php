<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

uses(Tests\TestCase::class);

/**
 * R-WA-MEDIA-INBOUND — anti-regressão M1 fix 2026-05-28 batch midia.
 *
 * Sintoma reportado Wagner: 45.819 mensagens com mídia em
 * `media_download_status='pending'` desde 2026-05-15 (cutover Baileys→whatsmeow).
 * ZERO mídias baixadas. extractFromWhatsmeow não capturava image/video/audio/
 * document → message.media_mime sempre NULL → Observer gate L87
 * `if media_mime === null return` → DownloadMediaJob nunca dispatchava.
 *
 * Fix M1: extractFromWhatsmeow detecta {image|video|audio|document|sticker}Message
 * no protobuf body e preenche media_mime/media_size_bytes/media_filename.
 * media_url fica NULL — download separado (próximo PR M2 endpoint WuzAPI).
 *
 * Tests cobrem: image, video, audio, document, sticker; e text não tem media.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'conversations', 'channels', 'activity_log'] as $t) {
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
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->string('customer_external_id', 150);
        $table->string('phone_e164', 30)->nullable();
        $table->string('contact_name', 120)->nullable();
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->string('last_message_preview', 200)->nullable();
        $table->string('last_message_direction', 20)->nullable();
        $table->timestamps();
        $table->unique(['business_id', 'channel_id', 'customer_external_id'], 'conv_biz_ch_ext_uniq');
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30);
        $table->string('provider_message_id', 128)->nullable();
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->timestamps();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });

    Schema::create('activity_log', function ($table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description')->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->string('subject_type')->nullable();
        $table->json('properties')->nullable();
        $table->string('event')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->timestamps();
    });
});

function createWhatsmeowChannelM1(string $uuid, string $instance): Channel
{
    \DB::table('channels')->insert([
        'business_id' => 1,
        'channel_uuid' => $uuid,
        'label' => 'Test',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'active',
        'config_json' => json_encode(['whatsmeow' => ['user_name' => $instance]]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return Channel::query()->withoutGlobalScope(ScopeByBusiness::class)->where('channel_uuid', $uuid)->firstOrFail();
}

function makeWhatsmeowMediaPayload(string $instance, string $msgKey, array $messageProto): array
{
    return [
        'instanceName' => $instance,
        'event' => [
            'Info' => [
                'Chat' => '554899872822@s.whatsapp.net',
                'Sender' => '554899872822@s.whatsapp.net',
                'SenderAlt' => '554899872822@s.whatsapp.net',
                'IsFromMe' => false,
                'ID' => $msgKey,
                'Type' => 'media',
                'PushName' => 'Cliente',
            ],
            'Message' => $messageProto,
        ],
    ];
}

it('R-WA-MEDIA-INBOUND-001 — imageMessage extrai mime/size/filename', function () {
    $ch = createWhatsmeowChannelM1('11111111-1111-1111-1111-000000000001', 'ch-img');
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', makeWhatsmeowMediaPayload('ch-img', 'WAMID.IMG.1', [
        'imageMessage' => ['mimetype' => 'image/jpeg', 'fileLength' => 102400, 'caption' => 'foto teste'],
    ])))->handle();
    $m = \DB::table('messages')->where('provider_message_id', 'WAMID.IMG.1')->first();
    expect($m)->not->toBeNull();
    expect($m->type)->toBe('image');
    expect($m->media_mime)->toBe('image/jpeg');
    expect((int) $m->media_size_bytes)->toBe(102400);
    expect($m->body)->toBe('foto teste');
});

it('R-WA-MEDIA-INBOUND-002 — videoMessage extrai mime + caption', function () {
    $ch = createWhatsmeowChannelM1('22222222-2222-2222-2222-000000000002', 'ch-vid');
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', makeWhatsmeowMediaPayload('ch-vid', 'WAMID.VID.1', [
        'videoMessage' => ['mimetype' => 'video/mp4', 'fileLength' => 5242880],
    ])))->handle();
    $m = \DB::table('messages')->where('provider_message_id', 'WAMID.VID.1')->first();
    expect($m->type)->toBe('video');
    expect($m->media_mime)->toBe('video/mp4');
    expect($m->body)->toBe('[vídeo]');
});

it('R-WA-MEDIA-INBOUND-003 — audioMessage opus ogg', function () {
    $ch = createWhatsmeowChannelM1('33333333-3333-3333-3333-000000000003', 'ch-aud');
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', makeWhatsmeowMediaPayload('ch-aud', 'WAMID.AUD.1', [
        'audioMessage' => ['mimetype' => 'audio/ogg; codecs=opus', 'fileLength' => 8192],
    ])))->handle();
    $m = \DB::table('messages')->where('provider_message_id', 'WAMID.AUD.1')->first();
    expect($m->type)->toBe('audio');
    expect($m->media_mime)->toContain('ogg');
    expect($m->body)->toBe('[áudio]');
});

it('R-WA-MEDIA-INBOUND-004 — documentMessage preserva fileName', function () {
    $ch = createWhatsmeowChannelM1('44444444-4444-4444-4444-000000000004', 'ch-doc');
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', makeWhatsmeowMediaPayload('ch-doc', 'WAMID.DOC.1', [
        'documentMessage' => ['mimetype' => 'application/pdf', 'fileLength' => 200000, 'fileName' => 'contrato.pdf'],
    ])))->handle();
    $m = \DB::table('messages')->where('provider_message_id', 'WAMID.DOC.1')->first();
    expect($m->type)->toBe('document');
    expect($m->media_mime)->toBe('application/pdf');
    expect($m->media_filename)->toBe('contrato.pdf');
});

it('R-WA-MEDIA-INBOUND-005 — text mantém media_mime NULL (anti-regressão M1)', function () {
    $ch = createWhatsmeowChannelM1('55555555-5555-5555-5555-000000000005', 'ch-txt');
    (new ProcessIncomingWebhookJob(1, 'whatsmeow', [
        'instanceName' => 'ch-txt',
        'event' => [
            'Info' => ['Chat' => '554899872822@s.whatsapp.net', 'Sender' => '554899872822@s.whatsapp.net', 'IsFromMe' => false, 'ID' => 'WAMID.TXT.1', 'Type' => 'text', 'PushName' => 'Cliente'],
            'Message' => ['conversation' => 'oi tudo bem'],
        ],
    ]))->handle();
    $m = \DB::table('messages')->where('provider_message_id', 'WAMID.TXT.1')->first();
    expect($m->type)->toBe('text');
    expect($m->media_mime)->toBeNull();
    expect($m->body)->toBe('oi tudo bem');
});
