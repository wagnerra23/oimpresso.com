<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DeleteBaileysInstanceJob;
use Modules\Whatsapp\Observers\ChannelObserver;

uses(Tests\TestCase::class);

/**
 * Sync Laravel→daemon: ao desativar/deletar channel Baileys, purgar instance
 * no daemon CT 100 (fecha Gap A do post-mortem 2026-05-13).
 *
 * Cenário real do incident:
 *   - Channels id=2 (biz=1) e id=3 (biz=1) marcaram status=banned/disconnected
 *     no Laravel, mas suas instâncias `ch-da8c23...` e `ch-3bcafcfc...`
 *     continuaram ativas no daemon CT 100 por DIAS, gerando loop de
 *     reconnect com creds revogadas e acelerando ban Meta.
 *
 * @see Modules/Whatsapp/Observers/ChannelObserver.php
 * @see Modules/Whatsapp/Jobs/DeleteBaileysInstanceJob.php
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

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

    // ServiceProvider já registra o observer; reforçamos pra testes isolados
    Channel::observe(ChannelObserver::class);

    Queue::fake();
});

function makeActiveBaileysChannel(int $businessId, string $uuid): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte biz' . $businessId,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
        'display_identifier' => '554888782087',
    ]);
}

it('R-WA-CHAN-SYNC-001 — dispatch DeleteBaileysInstanceJob ao transitar active → banned', function () {
    $ch = makeActiveBaileysChannel(1, '88b13697-b89e-451c-b65b-e917533bab21');
    Queue::assertNothingPushed();

    $ch->status = 'banned';
    $ch->save();

    Queue::assertPushed(DeleteBaileysInstanceJob::class, function (DeleteBaileysInstanceJob $job) {
        return $job->instanceId === 'ch-88b13697b89e451cb65be917533bab21'
            && $job->businessId === 1
            && str_starts_with($job->reason, 'status_transition_active_to_banned');
    });
});

it('R-WA-CHAN-SYNC-002 — dispatch ao transitar active → disconnected', function () {
    $ch = makeActiveBaileysChannel(1, 'da8c23c5-5a6c-4538-b82f-1a05c47ac5da');

    $ch->status = 'disconnected';
    $ch->save();

    Queue::assertPushed(DeleteBaileysInstanceJob::class, function (DeleteBaileysInstanceJob $job) {
        return $job->instanceId === 'ch-da8c23c55a6c4538b82f1a05c47ac5da'
            && $job->reason === 'status_transition_active_to_disconnected';
    });
});

it('R-WA-CHAN-SYNC-003 — NÃO dispatch quando channel type ≠ baileys', function () {
    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'zapi-' . uniqid(),
        'label' => 'Z-API biz1',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'status' => 'active',
        'display_identifier' => 'someinstance',
    ]);

    $ch->status = 'banned';
    $ch->save();

    Queue::assertNothingPushed();
});

it('R-WA-CHAN-SYNC-004 — NÃO dispatch quando status não muda (save sem transição)', function () {
    $ch = makeActiveBaileysChannel(1, '99999999-9999-9999-9999-999999999999');

    $ch->label = 'Renomeado';
    $ch->save();

    Queue::assertNothingPushed();
});

it('R-WA-CHAN-SYNC-005 — NÃO dispatch quando transita ENTRE deactivation states (banned→disconnected)', function () {
    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'label' => 'Já banned',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'banned',
        'display_identifier' => '554888782087',
    ]);

    $ch->status = 'disconnected';
    $ch->save();

    Queue::assertNothingPushed();
});

it('R-WA-CHAN-SYNC-006 — dispatch ao deletar channel Baileys', function () {
    $ch = makeActiveBaileysChannel(99, '11111111-2222-3333-4444-555555555555');

    $ch->delete();

    Queue::assertPushed(DeleteBaileysInstanceJob::class, function (DeleteBaileysInstanceJob $job) {
        return $job->instanceId === 'ch-11111111222233334444555555555555'
            && $job->businessId === 99
            && $job->reason === 'channel_deleted';
    });
});

it('R-WA-CHAN-SYNC-007 — multi-tenant Tier 0: business_id propagado corretamente no job', function () {
    $chBiz1 = makeActiveBaileysChannel(1, 'biz1uuid-1111-1111-1111-111111111111');
    $chBiz164 = makeActiveBaileysChannel(164, 'biz164uu-1111-1111-1111-111111111111');

    $chBiz1->status = 'banned';
    $chBiz1->save();

    $chBiz164->status = 'banned';
    $chBiz164->save();

    // Cada dispatch carrega seu próprio business_id (sem session-leak entre tenants)
    Queue::assertPushed(DeleteBaileysInstanceJob::class, fn ($job) => $job->businessId === 1);
    Queue::assertPushed(DeleteBaileysInstanceJob::class, fn ($job) => $job->businessId === 164);
});

it('R-WA-CHAN-SYNC-008 — NÃO dispatch quando channel sem channel_uuid (defensivo)', function () {
    // Caso patológico: channel criado sem UUID (não deveria acontecer, mas
    // o booted() sempre gera um). Cobrimos pelo defensive guard no observer.
    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->newInstance([
        'business_id' => 1,
        'label' => 'Sem UUID',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ], exists: true);
    $ch->channel_uuid = null;
    $ch->id = 9999;
    $ch->status = 'banned';
    $ch->syncOriginal();
    $ch->status = 'banned'; // não dispara updating sem dirty mas vamos forçar via deleted()

    $observer = new ChannelObserver();
    $observer->deleted($ch);

    Queue::assertNothingPushed();
});
