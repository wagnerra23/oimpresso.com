<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

uses(Tests\TestCase::class);

/**
 * US-WA-080 — ImportHistoryCommand test (Baileys 90d).
 *
 * Mocka daemon CT 100 via Http::fake e exercita:
 *   1. Batch normal: 50 msgs → persisted (cursor avança)
 *   2. Paginação: has_more=true → 2 chamadas até cutoff
 *   3. has_more=false → para conv
 *   4. Empty: daemon retorna 0 msgs → para conv
 *   5. Idempotência: re-rodar mesma window não duplica
 *   6. Multi-tenant: biz=99 não toca
 *   7. --max cap respeitado
 *   8. Mídia preserva meta no payload
 *   9. --dry-run não persiste
 *
 * Schema in-memory: espelha ChannelBaileysWebhookIdempotencyTest.
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
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });

    // Config Baileys daemon mock
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon-test.local',
        'whatsapp.baileys.api_key' => 'test-api-key-12345678901234567890',
        'whatsapp.baileys.request_timeout' => 5,
    ]);
});

/**
 * Helper: cria channel + conv + 1 msg "âncora" (provê cursor pra import).
 */
function makeBaileysChannelConvAndAnchor(int $bizId = 1, string $convExtId = '+554899872822'): array
{
    $channel = Channel::query()->create([
        'business_id' => $bizId,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-' . sprintf('%012d', $bizId),
        'label' => 'Test',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    $conv = Conversation::query()->create([
        'business_id' => $bizId,
        'channel_id' => $channel->id,
        'customer_external_id' => $convExtId,
        'status' => 'open',
    ]);

    // Anchor msg pra prover cursor inicial — provider_message_id único per-biz
    // pra evitar colisão com UNIQUE global no test multi-tenant.
    $anchorId = "ANCHOR_MSG_BIZ{$bizId}";
    $msg = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->create([
            'business_id' => $bizId,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'provider' => Channel::TYPE_WHATSAPP_BAILEYS,
            'provider_message_id' => $anchorId,
            'type' => 'text',
            'body' => 'âncora pre-existente',
            'status' => 'received',
            'payload' => [
                'key' => [
                    'remoteJid' => preg_replace('/\D/', '', $convExtId) . '@s.whatsapp.net',
                    'id' => $anchorId,
                    'fromMe' => false,
                ],
            ],
        ]);

    // Força created_at de 1h atrás pro cursor fazer sentido
    \DB::table('messages')->where('id', $msg->id)->update([
        'created_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'updated_at' => now()->subHour()->format('Y-m-d H:i:s'),
    ]);

    return [$channel, $conv, $msg];
}

/**
 * Helper: payload de 1 msg estilo daemon /history.
 */
function fakeHistoryMsg(string $msgId, string $jid, int $ts, string $body = 'Texto histórico', bool $fromMe = false): array
{
    return [
        'key' => [
            'remoteJid' => $jid,
            'id' => $msgId,
            'fromMe' => $fromMe,
        ],
        'message' => [
            'conversation' => $body,
        ],
        'push_name' => 'Cliente Test',
        'timestamp' => $ts,
    ];
}

it('US-WA-080-001 — batch normal de 3 msgs é persistido', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();
    $jid = '554899872822@s.whatsapp.net';

    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 3,
            'has_more' => false,
            'oldest_id' => 'HIST_OLD',
            'oldest_ts' => now()->subDays(5)->timestamp,
            'empty' => false,
            'messages' => [
                fakeHistoryMsg('HIST_NEW', $jid, now()->subDays(2)->timestamp, 'Msg recente'),
                fakeHistoryMsg('HIST_MID', $jid, now()->subDays(3)->timestamp, 'Msg meio'),
                fakeHistoryMsg('HIST_OLD', $jid, now()->subDays(5)->timestamp, 'Msg antiga'),
            ],
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--max' => 2000,
        '--sleep' => 0,
    ]);

    expect($exitCode)->toBe(0);

    // 3 msgs novas + 1 âncora = 4
    $total = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->count();
    expect($total)->toBe(4);

    // Conferir provider_message_ids esperados
    $providerIds = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->pluck('provider_message_id')
        ->sort()
        ->values()
        ->toArray();
    expect($providerIds)->toEqual(['ANCHOR_MSG_BIZ1', 'HIST_MID', 'HIST_NEW', 'HIST_OLD']);
});

it('US-WA-080-002 — paginação: 2 batches has_more=true → has_more=false', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();
    $jid = '554899872822@s.whatsapp.net';

    // Sequência mockada — Http::fakeSequence pra 2 chamadas distintas.
    Http::fake([
        'daemon-test.local/instances/*/history' => Http::sequence()
            ->push([
                'count' => 2,
                'has_more' => true,
                'oldest_id' => 'BATCH1_OLD',
                'oldest_ts' => now()->subDays(10)->timestamp,
                'empty' => false,
                'messages' => [
                    fakeHistoryMsg('BATCH1_NEW', $jid, now()->subDays(8)->timestamp, 'Batch1 new'),
                    fakeHistoryMsg('BATCH1_OLD', $jid, now()->subDays(10)->timestamp, 'Batch1 old'),
                ],
            ], 200)
            ->push([
                'count' => 1,
                'has_more' => false,
                'oldest_id' => 'BATCH2_OLD',
                'oldest_ts' => now()->subDays(20)->timestamp,
                'empty' => false,
                'messages' => [
                    fakeHistoryMsg('BATCH2_OLD', $jid, now()->subDays(20)->timestamp, 'Batch2 only'),
                ],
            ], 200),
    ]);

    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--sleep' => 0,
    ]);

    $count = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->whereIn('provider_message_id', ['BATCH1_NEW', 'BATCH1_OLD', 'BATCH2_OLD'])
        ->count();
    expect($count)->toBe(3);
});

it('US-WA-080-003 — idempotência: re-rodar não duplica', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();
    $jid = '554899872822@s.whatsapp.net';

    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 1,
            'has_more' => false,
            'oldest_id' => 'DUP_MSG',
            'oldest_ts' => now()->subDays(2)->timestamp,
            'empty' => false,
            'messages' => [
                fakeHistoryMsg('DUP_MSG', $jid, now()->subDays(2)->timestamp, 'Mensagem que vai duplicar'),
            ],
        ], 200),
    ]);

    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--sleep' => 0,
    ]);
    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--sleep' => 0,
    ]);

    // Mesmo provider_message_id → 1 row só
    $count = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'DUP_MSG')
        ->count();
    expect($count)->toBe(1);
});

it('US-WA-080-004 — --max cap respeitado', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();
    $jid = '554899872822@s.whatsapp.net';

    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 5,
            'has_more' => true,
            'oldest_id' => 'CAP_OLD',
            'oldest_ts' => now()->subDays(5)->timestamp,
            'empty' => false,
            'messages' => [
                fakeHistoryMsg('CAP_M1', $jid, now()->subDays(1)->timestamp),
                fakeHistoryMsg('CAP_M2', $jid, now()->subDays(2)->timestamp),
                fakeHistoryMsg('CAP_M3', $jid, now()->subDays(3)->timestamp),
                fakeHistoryMsg('CAP_M4', $jid, now()->subDays(4)->timestamp),
                fakeHistoryMsg('CAP_M5', $jid, now()->subDays(5)->timestamp),
            ],
        ], 200),
    ]);

    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--max' => 3,
        '--sleep' => 0,
    ]);

    // Só 3 das CAP_* persistidas + 1 âncora
    $count = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->where('provider_message_id', 'like', 'CAP_%')
        ->count();
    expect($count)->toBe(3);
});

it('US-WA-080-005 — multi-tenant: biz=99 não toca biz=1', function () {
    [$channel99, $conv99] = makeBaileysChannelConvAndAnchor(99, '+554811112222');
    [$channel1, $conv1] = makeBaileysChannelConvAndAnchor(1, '+554899872822');

    $jid99 = '554811112222@s.whatsapp.net';

    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 1,
            'has_more' => false,
            'oldest_id' => 'BIZ99_MSG',
            'oldest_ts' => now()->subDay()->timestamp,
            'empty' => false,
            'messages' => [
                fakeHistoryMsg('BIZ99_MSG', $jid99, now()->subDay()->timestamp, 'Só pra biz 99'),
            ],
        ], 200),
    ]);

    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel99->id,
        '--since' => '90d',
        '--sleep' => 0,
    ]);

    // Msg persistida no biz=99
    $biz99Count = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 99)
        ->where('provider_message_id', 'BIZ99_MSG')
        ->count();
    expect($biz99Count)->toBe(1);

    // biz=1 não foi tocado — só tem a âncora
    $biz1Count = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->count();
    expect($biz1Count)->toBe(1); // só âncora

    // Msg biz=99 não vazou pra biz=1
    $cross = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('provider_message_id', 'BIZ99_MSG')
        ->exists();
    expect($cross)->toBeFalse();
});

it('US-WA-080-006 — mídia: meta extraída do payload aninhado', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();
    $jid = '554899872822@s.whatsapp.net';

    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 1,
            'has_more' => false,
            'oldest_id' => 'MEDIA_MSG',
            'oldest_ts' => now()->subDay()->timestamp,
            'empty' => false,
            'messages' => [
                [
                    'key' => [
                        'remoteJid' => $jid,
                        'id' => 'MEDIA_MSG',
                        'fromMe' => false,
                    ],
                    'message' => [
                        'audioMessage' => [
                            'mimetype' => 'audio/ogg; codecs=opus',
                            'seconds' => 7,
                            'fileLength' => 12345,
                        ],
                    ],
                    'push_name' => 'Cliente',
                    'timestamp' => now()->subDay()->timestamp,
                ],
            ],
        ], 200),
    ]);

    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--sleep' => 0,
    ]);

    $msg = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'MEDIA_MSG')
        ->first();

    expect($msg)->not->toBeNull();
    expect($msg->type)->toBe('audio');
    // Sanitization MIME (strip codec)
    expect($msg->media_mime)->toBe('audio/ogg');
    expect($msg->media_duration_s)->toBe(7);
    expect($msg->media_size_bytes)->toBe(12345);
});

it('US-WA-080-007 — dry-run não persiste nada', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();
    $jid = '554899872822@s.whatsapp.net';

    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 2,
            'has_more' => false,
            'oldest_id' => 'DRY_OLD',
            'oldest_ts' => now()->subDay()->timestamp,
            'empty' => false,
            'messages' => [
                fakeHistoryMsg('DRY_NEW', $jid, now()->subHours(1)->timestamp),
                fakeHistoryMsg('DRY_OLD', $jid, now()->subDay()->timestamp),
            ],
        ], 200),
    ]);

    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--dry-run' => true,
        '--sleep' => 0,
    ]);

    // Só a âncora deve permanecer
    $count = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->count();
    expect($count)->toBe(1);

    $dryExists = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->whereIn('provider_message_id', ['DRY_NEW', 'DRY_OLD'])
        ->exists();
    expect($dryExists)->toBeFalse();
});

it('US-WA-080-008 — empty response da daemon para conv', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();

    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 0,
            'has_more' => false,
            'oldest_id' => null,
            'oldest_ts' => null,
            'empty' => true,
            'messages' => [],
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--sleep' => 0,
    ]);

    expect($exitCode)->toBe(0);
    // Só a âncora
    $count = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->count();
    expect($count)->toBe(1);
});

it('US-WA-080-009 — cutoff --since para quando cursor passa do limite', function () {
    [$channel, $conv] = makeBaileysChannelConvAndAnchor();
    $jid = '554899872822@s.whatsapp.net';

    // 1ª chamada devolve msg com oldest_ts MUITO antigo (>90d)
    Http::fake([
        'daemon-test.local/instances/*/history' => Http::response([
            'count' => 1,
            'has_more' => true, // daemon diz que tem mais
            'oldest_id' => 'PRE_CUTOFF',
            'oldest_ts' => now()->subDays(120)->timestamp, // 120d > cutoff 90d
            'empty' => false,
            'messages' => [
                fakeHistoryMsg('PRE_CUTOFF', $jid, now()->subDays(120)->timestamp, 'Anteriôr ao cutoff'),
            ],
        ], 200),
    ]);

    \Artisan::call('whatsapp:import-history', [
        '--channel' => $channel->id,
        '--since' => '90d',
        '--sleep' => 0,
    ]);

    // Msg persistida (chegou ANTES de decidir parar)
    $exists = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'PRE_CUTOFF')
        ->exists();
    expect($exists)->toBeTrue();

    // Mas só 1 chamada feita ao daemon — não deve continuar paginando
    Http::assertSentCount(1);
});
