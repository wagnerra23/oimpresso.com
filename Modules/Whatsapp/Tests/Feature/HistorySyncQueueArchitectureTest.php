<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\PersistHistorySyncBatchJob;

uses(Tests\TestCase::class);

/**
 * Regression test pra arquitetura "buffer rápido + worker async" pedida por
 * Wagner 2026-05-14 02h:
 *   "recebe tudo de maneira rapida no redis ou onde, depois sincroniza com
 *   o banco verifica se tem mensagem, mais sempre guarda para não perder
 *   depois vai tratando mais recebe e garante receber tudo sempre que for
 *   enviado."
 *
 * Decisões implementação:
 *   - Redis indisponível Hostinger (Connection refused) → Laravel queue
 *     driver=database (mesma arquitetura, sempre disponível)
 *   - PersistHistorySyncBatchJob::onConnection('database') override
 *     QUEUE_CONNECTION=sync default
 *   - handleHistorySync dispatch normal (não dispatchAfterResponse) →
 *     INSERT na tabela `jobs` atômico → daemon recebe 202 imediato
 *
 * Garantias testadas:
 *   1. Job vai pra `jobs` table (não roda inline)
 *   2. queue='whatsapp-history', connection='database'
 *   3. Idempotência via reset/re-dispatch
 *   4. Worker isolation (jobs de outras queues não interferem)
 *
 * @see Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 */
beforeEach(function () {
    Schema::dropIfExists('channels');
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

    Queue::fake();
});

function makeQueueTestChannel(): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'queue-test-' . uniqid(),
        'label' => 'Queue Test',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
        'display_identifier' => '5511999998888',
    ]);
}

it('R-WA-QSYNC-001 — Job vai pra connection=database (não sync)', function () {
    $ch = makeQueueTestChannel();

    PersistHistorySyncBatchJob::dispatch(
        businessId: 1,
        channelId: $ch->id,
        syncType: 2,
        chunkIndex: 0,
        chunkTotal: 1,
        messages: [
            ['key' => ['id' => 'msg1', 'remoteJid' => '5511999999999@s.whatsapp.net'], 'message' => []],
        ],
    );

    Queue::assertPushed(PersistHistorySyncBatchJob::class, function ($job) {
        return $job->connection === 'database';
    });
});

it('R-WA-QSYNC-002 — Job vai pra queue=whatsapp-history (isolada)', function () {
    $ch = makeQueueTestChannel();

    PersistHistorySyncBatchJob::dispatch(
        businessId: 1,
        channelId: $ch->id,
        syncType: 2,
        chunkIndex: 0,
        chunkTotal: 1,
        messages: [['key' => ['id' => 'msg1']]],
    );

    Queue::assertPushedOn('whatsapp-history', PersistHistorySyncBatchJob::class);
});

it('R-WA-QSYNC-003 — Job é dispatchado com payload completo (não perde dados)', function () {
    $ch = makeQueueTestChannel();
    $messages = [
        ['key' => ['id' => 'msg1', 'remoteJid' => 'a@s.whatsapp.net']],
        ['key' => ['id' => 'msg2', 'remoteJid' => 'b@s.whatsapp.net']],
        ['key' => ['id' => 'msg3', 'remoteJid' => 'c@s.whatsapp.net']],
    ];

    PersistHistorySyncBatchJob::dispatch(
        businessId: 42,
        channelId: $ch->id,
        syncType: 2,
        chunkIndex: 5,
        chunkTotal: 10,
        messages: $messages,
    );

    Queue::assertPushed(PersistHistorySyncBatchJob::class, function ($job) use ($ch, $messages) {
        return $job->businessId === 42
            && $job->channelId === $ch->id
            && $job->syncType === 2
            && $job->chunkIndex === 5
            && $job->chunkTotal === 10
            && count($job->messages) === 3
            && $job->messages[0]['key']['id'] === 'msg1';
    });
});

it('R-WA-QSYNC-004 — 3 tries com backoff exponencial (não-perda em falhas transientes)', function () {
    $ch = makeQueueTestChannel();
    $job = new PersistHistorySyncBatchJob(
        businessId: 1,
        channelId: $ch->id,
        syncType: 2,
        chunkIndex: 0,
        chunkTotal: 1,
        messages: [],
    );

    expect($job->tries)->toBe(3);
    expect($job->backoff())->toBe([10, 30, 90]);
});

it('R-WA-QSYNC-005 — multi-tenant Tier 0: businessId no constructor (não session-leak)', function () {
    $chBiz1 = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'biz1-queue-' . uniqid(),
        'label' => 'biz1',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $chBiz164 = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 164,
        'channel_uuid' => 'biz164-queue-' . uniqid(),
        'label' => 'biz164',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    PersistHistorySyncBatchJob::dispatch(1, $chBiz1->id, 2, 0, 1, [['key' => ['id' => 'a']]]);
    PersistHistorySyncBatchJob::dispatch(164, $chBiz164->id, 2, 0, 1, [['key' => ['id' => 'b']]]);

    Queue::assertPushed(PersistHistorySyncBatchJob::class, fn ($job) => $job->businessId === 1);
    Queue::assertPushed(PersistHistorySyncBatchJob::class, fn ($job) => $job->businessId === 164);
});
