<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Jobs\DownloadMediaJob;

uses(Tests\TestCase::class);

/**
 * Wave 3 Agent B — Cron horário `whatsapp:retry-recent-media-downloads`.
 *
 * Cenários cobertos:
 *  1. Happy path — mídia órfã (media_url=NULL, media_mime preenchido, <24h)
 *     → dispatcha DownloadMediaJob
 *  2. Cross-tenant — biz=1 órfã + biz=99 órfã: cron cross-business processa
 *     AMBAS (não usa biz=4 ROTA LIVRE — ADR 0101)
 *  3. Lookback — mídia > 24h NÃO é processada (escopo apenas recente)
 *  4. Skip failed_permanent — cap atingido não é re-dispatchado
 *  5. Skip success com URL — idempotente, não re-dispatcha o que já baixou
 *  6. dry-run não dispatcha jobs
 *  7. Status anômalo (NULL/success-sem-URL) — pega mesmo assim
 *     (diferença chave vs Camada 4 RetryFailedMediaDownloadsJob)
 *
 * Schema mirror SQLite (mesmo pattern do ReparseMediaFromPayloadCommandTest).
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
        $table->string('media_download_status', 30)->nullable();
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamp('media_download_last_attempt_at')->nullable();
        $table->string('media_download_failed_reason', 255)->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    Queue::fake();
});

/**
 * Helper — cria channel + conv pro business.
 *
 * @return array{0: Channel, 1: Conversation}
 */
function makeRetryChannelAndConv(int $businessId, string $uuid): array
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
        'contact_name' => 'Cliente Retry',
        'status' => 'open',
    ]);

    return [$channel, $conv];
}

/**
 * Helper — cria Message órfã (media_url=null, media_mime preenchido).
 * Permite override de created_at + media_download_status.
 */
function makeOrphanMediaMessage(
    int $businessId,
    int $convId,
    string $mime = 'image/jpeg',
    ?string $createdAt = null,
    ?string $downloadStatus = null,
    ?string $mediaUrl = null,
): Message {
    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'image',
        'body' => null,
        'status' => 'received',
        'payload' => ['message' => ['imageMessage' => ['mimetype' => $mime]]],
        'media_mime' => $mime,
        'media_url' => $mediaUrl,
        'media_download_status' => $downloadStatus,
    ]);

    if ($createdAt) {
        DB::table('messages')
            ->where('id', $msg->id)
            ->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        $msg->refresh();
    } else {
        // Default: criada agora (dentro da janela 24h)
        DB::table('messages')
            ->where('id', $msg->id)
            ->update(['created_at' => now(), 'updated_at' => now()]);
        $msg->refresh();
    }

    return $msg;
}

it('happy path — mídia órfã <24h é dispatchada', function () {
    [, $conv] = makeRetryChannelAndConv(1, 'aaaa-0000-0000-0000-happy');

    $msg = makeOrphanMediaMessage(
        businessId: 1,
        convId: $conv->id,
        mime: 'audio/ogg',
        downloadStatus: Message::DOWNLOAD_STATUS_PENDING,
    );

    $this->artisan('whatsapp:retry-recent-media-downloads')
        ->assertExitCode(0);

    Queue::assertPushed(DownloadMediaJob::class, function ($job) use ($msg) {
        return $job->messageId === $msg->id
            && $job->businessId === 1
            && $job->expectedMime === 'audio/ogg';
    });
});

it('multi-tenant — biz=1 + biz=99 ambas processadas (cross-business cron)', function () {
    [, $conv1] = makeRetryChannelAndConv(1, 'aaaa-0000-0000-0000-biz01');
    [, $conv99] = makeRetryChannelAndConv(99, 'aaaa-0000-0000-0000-biz99');

    $msg1 = makeOrphanMediaMessage(businessId: 1, convId: $conv1->id);
    $msg99 = makeOrphanMediaMessage(businessId: 99, convId: $conv99->id);

    $this->artisan('whatsapp:retry-recent-media-downloads')
        ->assertExitCode(0);

    // AMBAS dispatchadas — cron cross-business
    Queue::assertPushed(DownloadMediaJob::class, fn ($j) => $j->messageId === $msg1->id && $j->businessId === 1);
    Queue::assertPushed(DownloadMediaJob::class, fn ($j) => $j->messageId === $msg99->id && $j->businessId === 99);

    // Total: exatamente 2 jobs (não vazou cross-tenant)
    Queue::assertPushed(DownloadMediaJob::class, 2);
});

it('lookback — mídia >24h NÃO é processada (fora da janela)', function () {
    [, $conv] = makeRetryChannelAndConv(1, 'aaaa-0000-0000-0000-old01');

    // Criada 48h atrás — fora da janela 24h default
    $oldMsg = makeOrphanMediaMessage(
        businessId: 1,
        convId: $conv->id,
        createdAt: now()->subHours(48)->toDateTimeString(),
    );

    // Criada agora — dentro da janela
    $newMsg = makeOrphanMediaMessage(businessId: 1, convId: $conv->id);

    $this->artisan('whatsapp:retry-recent-media-downloads')
        ->assertExitCode(0);

    Queue::assertPushed(DownloadMediaJob::class, fn ($j) => $j->messageId === $newMsg->id);
    Queue::assertNotPushed(DownloadMediaJob::class, fn ($j) => $j->messageId === $oldMsg->id);
});

it('skip failed_permanent — cap atingido não é re-dispatchado', function () {
    [, $conv] = makeRetryChannelAndConv(1, 'aaaa-0000-0000-0000-fperm');

    $cappedMsg = makeOrphanMediaMessage(
        businessId: 1,
        convId: $conv->id,
        downloadStatus: Message::DOWNLOAD_STATUS_FAILED_PERMANENT,
    );

    $pendingMsg = makeOrphanMediaMessage(
        businessId: 1,
        convId: $conv->id,
        downloadStatus: Message::DOWNLOAD_STATUS_PENDING,
    );

    $this->artisan('whatsapp:retry-recent-media-downloads')
        ->assertExitCode(0);

    // Pending dispatchado, failed_permanent skippado
    Queue::assertPushed(DownloadMediaJob::class, fn ($j) => $j->messageId === $pendingMsg->id);
    Queue::assertNotPushed(DownloadMediaJob::class, fn ($j) => $j->messageId === $cappedMsg->id);
});

it('skip mídia já baixada — media_url preenchido (idempotente via query)', function () {
    [, $conv] = makeRetryChannelAndConv(1, 'aaaa-0000-0000-0000-done0');

    $doneMsg = makeOrphanMediaMessage(
        businessId: 1,
        convId: $conv->id,
        downloadStatus: Message::DOWNLOAD_STATUS_SUCCESS,
        mediaUrl: 'whatsapp/1/2026-05/already-saved.jpg',
    );

    $this->artisan('whatsapp:retry-recent-media-downloads')
        ->assertExitCode(0);

    // media_url NOT NULL → query exclui → nada dispatchado
    Queue::assertNotPushed(DownloadMediaJob::class);
});

it('dry-run não dispatcha jobs', function () {
    [, $conv] = makeRetryChannelAndConv(1, 'aaaa-0000-0000-0000-dryr1');

    makeOrphanMediaMessage(businessId: 1, convId: $conv->id);
    makeOrphanMediaMessage(businessId: 1, convId: $conv->id);

    $this->artisan('whatsapp:retry-recent-media-downloads', ['--dry-run' => true])
        ->assertExitCode(0);

    Queue::assertNotPushed(DownloadMediaJob::class);
});

it('status anômalo NULL — pega mesmo assim (diferença vs Camada 4)', function () {
    [, $conv] = makeRetryChannelAndConv(1, 'aaaa-0000-0000-0000-nullstat');

    // Mídia com download_status NULL (cenário: PersistHistorySyncBatchJob
    // pulou MessageObserver durante re-pareamento). Camada 4 NÃO pegaria
    // (filtro `whereIn` exige pending|downloading). Este comando pega.
    $anomMsg = makeOrphanMediaMessage(
        businessId: 1,
        convId: $conv->id,
        downloadStatus: null,
    );

    $this->artisan('whatsapp:retry-recent-media-downloads')
        ->assertExitCode(0);

    Queue::assertPushed(DownloadMediaJob::class, fn ($j) => $j->messageId === $anomMsg->id);
});
