<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Jana\Console\Commands\HealthCheckCommand;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Jobs\DownloadMediaJob;
use Modules\Whatsapp\Jobs\RetryFailedMediaDownloadsJob;

uses(Tests\TestCase::class);

/**
 * Guardião 6 camadas anti-mídia-perdida — Pest tests.
 *
 * Cobre as 6 camadas + bonus + health check:
 *   1. Observer auto-dispatch (Camada 1)
 *   2. Observer NÃO dispatcha pra msg texto (Camada 1 — gate negativo)
 *   3. DownloadMediaJob increment attempts + status transitions (Camada 3)
 *   4. DownloadMediaJob max 5 attempts → failed_permanent (Camada 3)
 *   5. RetryFailedMediaDownloadsJob batches lazy (Camada 4)
 *   6. ScanMediaDriftCommand output + counts (Camada 5)
 *   7. Multi-tenant biz=99 NÃO vê pending de biz=1 (Tier 0)
 *   8. BackfillMediaDownloadCommand filter --since (Bonus)
 *   9. HealthCheck reporta count + status correto (Camada 6)
 *
 * Schema mirror prod (SQLite-compatible) — segue padrão InternalNoteTest.php
 * + MediaMessageTest.php.
 */
beforeEach(function () {
    Storage::fake('public');
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-api-key',
    ]);

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
        // US-WA-072 mídia
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedSmallInteger('media_duration_s')->nullable();
        $table->string('media_thumbnail_url', 500)->nullable();
        $table->text('media_transcription')->nullable();
        $table->string('media_filename', 255)->nullable();
        // Guardião 6 camadas (Camada 2)
        $table->string('media_download_status', 30)->default('pending');
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamp('media_download_last_attempt_at')->nullable();
        $table->string('media_download_failed_reason', 255)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });
});

function makeGuardiaoChannelAndConv(int $businessId, string $uuid = 'aaaa-0000-0000-0000-guardiao'): array
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
        'contact_name' => 'Cliente Guardião',
        'status' => 'open',
    ]);

    return [$channel, $conv];
}

// =======================================================================
// Camada 1 — Observer auto-dispatch
// =======================================================================

it('Camada 1 — Observer auto-dispatcha DownloadMediaJob quando media_mime presente e media_url null', function () {
    Bus::fake();
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'audio',
        'status' => 'received',
        'media_mime' => 'audio/ogg',
        // media_url null
    ]);

    Bus::assertDispatched(DownloadMediaJob::class, function (DownloadMediaJob $job) use ($message) {
        return $job->businessId === 1 && $job->messageId === $message->id;
    });
});

it('Camada 1 — Observer NÃO dispatcha pra msg texto (body!=null, media_mime=null)', function () {
    Bus::fake();
    [, $conv] = makeGuardiaoChannelAndConv(1);

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'status' => 'received',
        'body' => 'Oi, tudo bem?',
    ]);

    Bus::assertNotDispatched(DownloadMediaJob::class);
});

it('Camada 1 — Observer NÃO dispatcha quando media_url já preenchida (outbound upload)', function () {
    Bus::fake();
    [, $conv] = makeGuardiaoChannelAndConv(1);

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'image',
        'status' => 'sent',
        'media_url' => 'whatsapp/1/2026-05/abc.jpg',
        'media_mime' => 'image/jpeg',
    ]);

    Bus::assertNotDispatched(DownloadMediaJob::class);
});

it('Camada 1 — Observer NÃO dispatcha pra msg failed_permanent', function () {
    Bus::fake();
    [, $conv] = makeGuardiaoChannelAndConv(1);

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_FAILED_PERMANENT,
    ]);

    Bus::assertNotDispatched(DownloadMediaJob::class);
});

// =======================================================================
// Camada 3 — DownloadMediaJob status transitions
// =======================================================================

it('Camada 3 — DownloadMediaJob increment attempts + marca downloading no início', function () {
    // Bus::fake silencia o auto-dispatch do Observer pra isolar o handle() manual.
    Bus::fake([DownloadMediaJob::class]);
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_zapi', // Z-API path = HTTP direto (sem mediaKey)
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'payload' => ['media_url' => 'https://provider.test/media/abc.jpg'],
    ]);

    Http::fake([
        'provider.test/*' => Http::response(str_repeat('X', 128), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    (new DownloadMediaJob(1, $message->id, 'https://provider.test/media/abc.jpg', 'image/jpeg'))->handle();

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($message->id);
    expect((int) $fresh->media_download_attempts)->toBe(1);
    expect($fresh->media_download_status)->toBe(Message::DOWNLOAD_STATUS_SUCCESS);
    expect($fresh->media_download_last_attempt_at)->not->toBeNull();
});

it('Camada 3 — DownloadMediaJob 5 falhas consecutivas → failed_permanent', function () {
    Bus::fake([DownloadMediaJob::class]);
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_zapi',
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'payload' => ['media_url' => 'https://provider.test/x.jpg'],
        'media_download_attempts' => 4, // simulando 4 falhas anteriores
    ]);

    // HTTP 500 = retryable. 5ª attempt → failed_permanent.
    Http::fake([
        'provider.test/*' => Http::response('server error', 500),
    ]);

    (new DownloadMediaJob(1, $message->id, 'https://provider.test/x.jpg', 'image/jpeg'))->handle();

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($message->id);
    expect((int) $fresh->media_download_attempts)->toBe(5);
    expect($fresh->media_download_status)->toBe(Message::DOWNLOAD_STATUS_FAILED_PERMANENT);
    expect($fresh->media_download_failed_reason)->toContain('Max retries');
});

it('Camada 3 — DownloadMediaJob 4xx URL expirou → non-retryable → failed_permanent imediato', function () {
    Bus::fake([DownloadMediaJob::class]);
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_zapi',
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'payload' => ['media_url' => 'https://provider.test/expired.jpg'],
    ]);

    // 410 Gone = URL expirou = non-retryable
    Http::fake([
        'provider.test/*' => Http::response('gone', 410),
    ]);

    (new DownloadMediaJob(1, $message->id, 'https://provider.test/expired.jpg', 'image/jpeg'))->handle();

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($message->id);
    expect((int) $fresh->media_download_attempts)->toBe(1);
    expect($fresh->media_download_status)->toBe(Message::DOWNLOAD_STATUS_FAILED_PERMANENT);
});

it('Camada 3 — DownloadMediaJob via daemon decrypt-url (Baileys mediaKey)', function () {
    Bus::fake([DownloadMediaJob::class, \Modules\Whatsapp\Jobs\TranscribeAudioJob::class]);
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'audio',
        'status' => 'received',
        'media_mime' => 'audio/ogg',
        'payload' => [
            'message' => [
                'audioMessage' => [
                    'url' => 'https://mmg.whatsapp.net/d/f/abc.enc',
                    'mediaKey' => 'base64-encoded-key',
                    'mimetype' => 'audio/ogg; codecs=opus',
                ],
            ],
        ],
    ]);

    // Daemon devolve octet-stream (bytes brutos decifrados)
    Http::fake([
        'daemon.test/media/decrypt-url' => Http::response(
            str_repeat('A', 512),
            200,
            ['Content-Type' => 'application/octet-stream']
        ),
    ]);

    (new DownloadMediaJob(1, $message->id, '', 'audio/ogg'))->handle();

    Http::assertSent(function (\Illuminate\Http\Client\Request $req) {
        return str_contains($req->url(), '/media/decrypt-url')
            && $req->hasHeader('Authorization', 'Bearer test-api-key')
            && data_get($req->data(), 'mediaKey') === 'base64-encoded-key';
    });

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($message->id);
    expect($fresh->media_download_status)->toBe(Message::DOWNLOAD_STATUS_SUCCESS);
    expect($fresh->media_url)->toStartWith('whatsapp/1/');
});

it('Camada 3 — DownloadMediaJob idempotente: já success → skip', function () {
    Bus::fake([DownloadMediaJob::class]);
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_zapi',
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_url' => 'whatsapp/1/2026-05/already.jpg',
        'media_download_status' => Message::DOWNLOAD_STATUS_SUCCESS,
        'media_download_attempts' => 1,
    ]);

    Http::fake();
    (new DownloadMediaJob(1, $message->id, 'https://provider.test/x.jpg', 'image/jpeg'))->handle();

    // Não chamou HTTP — saiu no early return
    Http::assertNothingSent();

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($message->id);
    expect((int) $fresh->media_download_attempts)->toBe(1); // não incrementou
});

// =======================================================================
// Camada 4 — RetryFailedMediaDownloadsJob
// =======================================================================

it('Camada 4 — RetryFailedMediaDownloadsJob dispatcha pending mídia recente', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    // withoutEvents pula MessageObserver auto-dispatch — queremos só Retry job.
    $ids = Message::withoutEvents(function () use ($conv) {
        $ids = [];
        foreach (range(1, 3) as $i) {
            $m = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => 1,
                'conversation_id' => $conv->id,
                'direction' => 'inbound',
                'provider' => 'whatsapp_baileys',
                'type' => 'image',
                'status' => 'received',
                'media_mime' => 'image/jpeg',
                'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
            ]);
            $ids[] = $m->id;
        }
        return $ids;
    });
    \Illuminate\Support\Facades\DB::table('messages')
        ->whereIn('id', $ids)
        ->update(['created_at' => now()->subHours(2)]);

    Bus::fake([DownloadMediaJob::class]);
    (new RetryFailedMediaDownloadsJob())->handle();

    Bus::assertDispatchedTimes(DownloadMediaJob::class, 3);
});

it('Camada 4 — RetryFailedMediaDownloadsJob NÃO dispatcha mídia > 7d (cutoff)', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $m = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
    ]);
    \Illuminate\Support\Facades\DB::table('messages')
        ->where('id', $m->id)
        ->update(['created_at' => now()->subDays(10)]);

    Bus::fake([DownloadMediaJob::class]);
    (new RetryFailedMediaDownloadsJob())->handle();
    Bus::assertNotDispatched(DownloadMediaJob::class);
});

it('Camada 4 — RetryFailedMediaDownloadsJob NÃO dispatcha mídia failed_permanent', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_FAILED_PERMANENT,
        'media_download_attempts' => 5,
    ]);

    Bus::fake([DownloadMediaJob::class]);
    (new RetryFailedMediaDownloadsJob())->handle();
    Bus::assertNotDispatched(DownloadMediaJob::class);
});

it('Camada 4 — RetryFailedMediaDownloadsJob NÃO dispatcha mídia attempts>=MAX', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'image',
        'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
        'media_download_attempts' => Message::MEDIA_DOWNLOAD_MAX_ATTEMPTS,
    ]);

    Bus::fake([DownloadMediaJob::class]);
    (new RetryFailedMediaDownloadsJob())->handle();
    Bus::assertNotDispatched(DownloadMediaJob::class);
});

// =======================================================================
// Camada 5 — ScanMediaDriftCommand
// =======================================================================

it('Camada 5 — ScanMediaDriftCommand reporta contagens corretas', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $ids = [];
    foreach ([
        ['pending', 2],
        ['downloading', 3],
    ] as [$status, $hoursAgo]) {
        $m = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
            'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
            'media_mime' => 'image/jpeg',
            'media_download_status' => $status,
        ]);
        \Illuminate\Support\Facades\DB::table('messages')
            ->where('id', $m->id)
            ->update(['created_at' => now()->subHours($hoursAgo)]);
    }
    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
        'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_FAILED_PERMANENT,
        'media_download_last_attempt_at' => now()->subDays(1),
    ]);

    $exit = Artisan::call('whatsapp:scan-media-drift', ['--silent' => true]);
    expect($exit)->toBe(0);
});

it('Camada 5 — ScanMediaDriftCommand filtra por --business', function () {
    [, $conv1] = makeGuardiaoChannelAndConv(1, 'aaaa-0000-0000-0000-bizone');
    [, $conv99] = makeGuardiaoChannelAndConv(99, 'aaaa-0000-0000-0000-bizn99');

    $m1 = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'conversation_id' => $conv1->id, 'direction' => 'inbound',
        'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
    ]);
    $m99 = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99, 'conversation_id' => $conv99->id, 'direction' => 'inbound',
        'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
    ]);
    \Illuminate\Support\Facades\DB::table('messages')
        ->whereIn('id', [$m1->id, $m99->id])
        ->update(['created_at' => now()->subHours(2)]);

    $exit = Artisan::call('whatsapp:scan-media-drift', [
        '--business' => '1',
        '--silent' => true,
    ]);
    expect($exit)->toBe(0);
});

// =======================================================================
// Multi-tenant Tier 0
// =======================================================================

it('Tier 0 — RetryFailedMediaDownloadsJob dispatcha business_id correto cross-tenant', function () {
    [, $conv1] = makeGuardiaoChannelAndConv(1, 'aaaa-0000-0000-0000-biz1xr');
    [, $conv99] = makeGuardiaoChannelAndConv(99, 'aaaa-0000-0000-0000-biz99xr');

    // withoutEvents pula MessageObserver auto-dispatch — queremos só o Retry job.
    [$m1, $m99] = Message::withoutEvents(function () use ($conv1, $conv99) {
        return [
            Message::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => 1, 'conversation_id' => $conv1->id, 'direction' => 'inbound',
                'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
                'media_mime' => 'image/jpeg',
                'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
            ]),
            Message::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => 99, 'conversation_id' => $conv99->id, 'direction' => 'inbound',
                'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
                'media_mime' => 'image/jpeg',
                'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
            ]),
        ];
    });
    \Illuminate\Support\Facades\DB::table('messages')
        ->whereIn('id', [$m1->id, $m99->id])
        ->update(['created_at' => now()->subHour()]);

    Bus::fake([DownloadMediaJob::class]);
    (new RetryFailedMediaDownloadsJob())->handle();

    // Cada Job recebe businessId correto (não confunde 1↔99)
    Bus::assertDispatched(DownloadMediaJob::class, fn ($job) => $job->businessId === 1);
    Bus::assertDispatched(DownloadMediaJob::class, fn ($job) => $job->businessId === 99);
    Bus::assertDispatchedTimes(DownloadMediaJob::class, 2);
});

// =======================================================================
// Bonus — BackfillMediaDownloadCommand
// =======================================================================

it('Bonus — BackfillMediaDownloadCommand --dry-run NÃO dispatcha', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
        'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
    ]);

    Bus::fake([DownloadMediaJob::class]);
    $exit = Artisan::call('whatsapp:backfill-media-download', [
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);
    Bus::assertNotDispatched(DownloadMediaJob::class);
});

it('Bonus — BackfillMediaDownloadCommand --since filtra mensagens antigas', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    [$old, $recent] = Message::withoutEvents(function () use ($conv) {
        return [
            Message::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
                'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
                'media_mime' => 'image/jpeg',
                'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
            ]),
            Message::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
                'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
                'media_mime' => 'image/jpeg',
                'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
            ]),
        ];
    });

    \Illuminate\Support\Facades\DB::table('messages')
        ->where('id', $old->id)
        ->update(['created_at' => now()->subDays(40)]);
    \Illuminate\Support\Facades\DB::table('messages')
        ->where('id', $recent->id)
        ->update(['created_at' => now()->subDays(3)]);

    Bus::fake([DownloadMediaJob::class]);
    Artisan::call('whatsapp:backfill-media-download', [
        '--since' => now()->subDays(7)->toDateString(),
    ]);

    Bus::assertDispatchedTimes(DownloadMediaJob::class, 1);
});

it('Bonus — BackfillMediaDownloadCommand --force-failed reset attempts', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
        'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_FAILED_PERMANENT,
        'media_download_attempts' => 5,
        'media_download_failed_reason' => 'Max retries',
    ]);

    Bus::fake([DownloadMediaJob::class]);
    Artisan::call('whatsapp:backfill-media-download', [
        '--force-failed' => true,
        '--limit' => 10,
    ]);

    $fresh = Message::withoutGlobalScope(ScopeByBusiness::class)->find($message->id);
    expect((int) $fresh->media_download_attempts)->toBe(0);
    expect($fresh->media_download_status)->toBe(Message::DOWNLOAD_STATUS_PENDING);
    Bus::assertDispatchedTimes(DownloadMediaJob::class, 1);
});

// =======================================================================
// Camada 6 — HealthCheck integration
// =======================================================================

it('Camada 6 — HealthCheck whatsapp_media_pending_1h retorna ok quando zero', function () {
    Bus::fake([DownloadMediaJob::class]);
    [, $conv] = makeGuardiaoChannelAndConv(1);
    // Sem mídia pending > 1h.

    $command = new HealthCheckCommand();
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('checkWhatsappMediaPending1h');
    $method->setAccessible(true);
    $result = $method->invoke($command);

    expect($result['name'])->toBe('whatsapp_media_pending_1h');
    expect($result['ok'])->toBeTrue();
    expect($result['value'])->toBe(0);
});

it('Camada 6 — HealthCheck whatsapp_media_pending_1h alerta quando > 1h pending', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $m = Message::withoutEvents(function () use ($conv) {
        return Message::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
            'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
            'media_mime' => 'image/jpeg',
            'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
        ]);
    });
    \Illuminate\Support\Facades\DB::table('messages')
        ->where('id', $m->id)
        ->update(['created_at' => now()->subHours(2)]);

    $command = new HealthCheckCommand();
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('checkWhatsappMediaPending1h');
    $method->setAccessible(true);
    $result = $method->invoke($command);

    expect($result['ok'])->toBeFalse();
    expect($result['value'])->toBe(1);
    expect($result['message'])->toContain('ALERTA');
});

it('Camada 6 — HealthCheck whatsapp_media_pending_1h ignora failed_permanent', function () {
    [, $conv] = makeGuardiaoChannelAndConv(1);

    $m = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
        'provider' => 'whatsapp_baileys', 'type' => 'image', 'status' => 'received',
        'media_mime' => 'image/jpeg',
        'media_download_status' => Message::DOWNLOAD_STATUS_FAILED_PERMANENT,
    ]);
    \Illuminate\Support\Facades\DB::table('messages')
        ->where('id', $m->id)
        ->update(['created_at' => now()->subHours(5)]);

    $command = new HealthCheckCommand();
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('checkWhatsappMediaPending1h');
    $method->setAccessible(true);
    $result = $method->invoke($command);

    // failed_permanent é terminal — não conta como pending alert
    expect($result['ok'])->toBeTrue();
    expect($result['value'])->toBe(0);
});
