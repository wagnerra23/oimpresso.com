<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

uses(Tests\TestCase::class);

/**
 * Reparse meta de mídia a partir do payload aninhado Baileys.
 *
 * Cobre 8 cenários:
 *  1. Payload audio → media_mime/size/duration populados, type corrigido
 *  2. Payload image com caption → media_mime + body=caption + type=image
 *  3. Payload text (sem media proto) → skip
 *  4. media_mime já preenchido → não tocado (query exclui)
 *  5. dry-run não persiste
 *  6. Multi-tenant cross-tenant biz=99 não tocado quando --business=1
 *  7. --since=YYYY-MM-DD filtra corretamente
 *  8. --business=N específico só processa esse business
 *
 * Schema mirror SQLite (mesmo pattern de MediaMessageTest).
 */
beforeEach(function () {
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
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

/**
 * Helper — cria channel + conv pro business.
 *
 * @return array{0: Channel, 1: Conversation}
 */
function makeReparseChannelAndConv(int $businessId, string $uuid): array
{
    $channel = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511999999999',
        'contact_name' => 'Cliente Reparse',
        'status' => 'open',
    ]);

    return [$channel, $conv];
}

/**
 * Helper — cria Message órfã (sem meta) com payload Baileys aninhado.
 */
function makeOrphanMessage(int $businessId, int $convId, array $payload, ?string $createdAt = null): Message
{
    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text', // default bug pré-PR #664 quando media não era detectada
        'body' => null,
        'status' => 'received',
        'payload' => $payload,
    ]);

    // created_at não está em $fillable + Eloquent default override em timestamps()
    // — forçamos via DB::table() pra preservar valor fornecido (testar --since).
    if ($createdAt) {
        \Illuminate\Support\Facades\DB::table('messages')
            ->where('id', $msg->id)
            ->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        $msg->refresh();
    }

    return $msg;
}

it('audio payload — popula media_mime/size/duration, corrige type=audio', function () {
    [, $conv] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-audio1');

    $payload = [
        'key' => ['remoteJid' => '5548999@s.whatsapp.net', 'id' => 'ABC1', 'fromMe' => false],
        'message' => [
            'audioMessage' => [
                'mimetype' => 'audio/ogg; codecs=opus',
                'fileLength' => 12345,
                'seconds' => 7,
            ],
        ],
    ];
    $msg = makeOrphanMessage(1, $conv->id, $payload);

    $this->artisan('whatsapp:reparse-media-from-payload', ['--business' => 1])
        ->assertExitCode(0);

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg->id);
    expect($fresh->media_mime)->toBe('audio/ogg'); // codec strip
    expect((int) $fresh->media_size_bytes)->toBe(12345);
    expect((int) $fresh->media_duration_s)->toBe(7);
    expect($fresh->type)->toBe('audio'); // type corrigido de 'text' pra 'audio'
});

it('image payload com caption — popula media_mime + body=caption + type=image', function () {
    [, $conv] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-img11');

    $payload = [
        'key' => ['remoteJid' => '5548999@s.whatsapp.net', 'id' => 'IMG1', 'fromMe' => false],
        'message' => [
            'imageMessage' => [
                'mimetype' => 'image/jpeg',
                'fileLength' => 99999,
                'caption' => 'Olha que produto bonito',
            ],
        ],
    ];
    $msg = makeOrphanMessage(1, $conv->id, $payload);

    $this->artisan('whatsapp:reparse-media-from-payload', ['--business' => 1])
        ->assertExitCode(0);

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg->id);
    expect($fresh->media_mime)->toBe('image/jpeg');
    expect((int) $fresh->media_size_bytes)->toBe(99999);
    expect($fresh->body)->toBe('Olha que produto bonito');
    expect($fresh->type)->toBe('image');
});

it('payload sem mediaProto (só conversation/text) — SKIP', function () {
    [, $conv] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-text11');

    $payload = [
        'key' => ['remoteJid' => '5548999@s.whatsapp.net', 'id' => 'TXT1', 'fromMe' => false],
        'message' => [
            'conversation' => 'só texto, sem mídia',
        ],
    ];
    $msg = makeOrphanMessage(1, $conv->id, $payload);

    $this->artisan('whatsapp:reparse-media-from-payload', ['--business' => 1])
        ->assertExitCode(0);

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg->id);
    expect($fresh->media_mime)->toBeNull();
    expect($fresh->body)->toBeNull();
    expect($fresh->type)->toBe('text');
});

it('media_mime já preenchido — não tocado (query exclui)', function () {
    [, $conv] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-done1');

    $payload = [
        'message' => [
            'audioMessage' => [
                'mimetype' => 'audio/mpeg',
                'fileLength' => 555,
                'seconds' => 3,
            ],
        ],
    ];

    // Cria já com media_mime preenchido (mensagem nova pós-PR #664)
    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'audio',
        'body' => null,
        'status' => 'received',
        'payload' => $payload,
        'media_mime' => 'audio/ogg', // já preenchido — query exclui
        'media_size_bytes' => 999,
    ]);

    $this->artisan('whatsapp:reparse-media-from-payload', ['--business' => 1])
        ->assertExitCode(0);

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg->id);
    // Não tocado — mantém valores originais (size=999 ≠ payload.fileLength=555)
    expect($fresh->media_mime)->toBe('audio/ogg');
    expect((int) $fresh->media_size_bytes)->toBe(999);
});

it('dry-run NÃO persiste alterações', function () {
    [, $conv] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-dryrn');

    $payload = [
        'message' => [
            'audioMessage' => [
                'mimetype' => 'audio/ogg',
                'fileLength' => 100,
                'seconds' => 2,
            ],
        ],
    ];
    $msg = makeOrphanMessage(1, $conv->id, $payload);

    $this->artisan('whatsapp:reparse-media-from-payload', ['--business' => 1, '--dry-run' => true])
        ->assertExitCode(0);

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg->id);
    expect($fresh->media_mime)->toBeNull();
    expect($fresh->media_size_bytes)->toBeNull();
});

it('multi-tenant — --business=1 NÃO toca messages de biz=99 (cross-tenant guard)', function () {
    [, $conv1] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-biz1r');
    [, $conv99] = makeReparseChannelAndConv(99, 'aaaa-0000-0000-0000-biz99');

    $payload = [
        'message' => [
            'audioMessage' => ['mimetype' => 'audio/ogg', 'fileLength' => 1, 'seconds' => 1],
        ],
    ];
    $msg1 = makeOrphanMessage(1, $conv1->id, $payload);
    $msg99 = makeOrphanMessage(99, $conv99->id, $payload);

    $this->artisan('whatsapp:reparse-media-from-payload', ['--business' => 1])
        ->assertExitCode(0);

    $fresh1 = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg1->id);
    $fresh99 = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg99->id);

    expect($fresh1->media_mime)->toBe('audio/ogg'); // tocado
    expect($fresh99->media_mime)->toBeNull();        // NÃO tocado (biz isolado)
});

it('--since=YYYY-MM-DD — filtra messages anteriores ao cutoff', function () {
    [, $conv] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-since');

    $payload = [
        'message' => [
            'audioMessage' => ['mimetype' => 'audio/ogg', 'fileLength' => 1, 'seconds' => 1],
        ],
    ];

    $oldMsg = makeOrphanMessage(1, $conv->id, $payload, '2026-04-01 10:00:00');
    $newMsg = makeOrphanMessage(1, $conv->id, $payload, '2026-05-10 10:00:00');

    $this->artisan('whatsapp:reparse-media-from-payload', [
        '--business' => 1,
        '--since' => '2026-05-01',
    ])->assertExitCode(0);

    $freshOld = Message::withoutGlobalScope(ScopeByBusiness::class)->find($oldMsg->id);
    $freshNew = Message::withoutGlobalScope(ScopeByBusiness::class)->find($newMsg->id);

    expect($freshOld->media_mime)->toBeNull();        // antes do cutoff — não tocado
    expect($freshNew->media_mime)->toBe('audio/ogg'); // depois do cutoff — tocado
});

it('--business=all — processa biz=1 E biz=99 (cross-tenant superadmin)', function () {
    [, $conv1] = makeReparseChannelAndConv(1, 'aaaa-0000-0000-0000-all-1');
    [, $conv99] = makeReparseChannelAndConv(99, 'aaaa-0000-0000-0000-all99');

    $payload = [
        'message' => [
            'audioMessage' => ['mimetype' => 'audio/ogg', 'fileLength' => 1, 'seconds' => 1],
        ],
    ];
    $msg1 = makeOrphanMessage(1, $conv1->id, $payload);
    $msg99 = makeOrphanMessage(99, $conv99->id, $payload);

    $this->artisan('whatsapp:reparse-media-from-payload', ['--business' => 'all'])
        ->assertExitCode(0);

    $fresh1 = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg1->id);
    $fresh99 = Message::withoutGlobalScope(ScopeByBusiness::class)->find($msg99->id);

    expect($fresh1->media_mime)->toBe('audio/ogg');
    expect($fresh99->media_mime)->toBe('audio/ogg');
});
