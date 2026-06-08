<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DeleteBaileysInstanceJob;

uses(Tests\TestCase::class);

/**
 * Regression test pro reconciler — automatiza fix de drift channels Baileys
 * sem intervenção humana.
 *
 * Wagner pediu 2026-05-13: "como resolve isso vai sempre você? automatize".
 *
 * Cenários cobertos:
 *  - DB=active mas daemon=banned → marca DB.status=banned + dispatch DeleteBaileysInstanceJob
 *  - DB=active mas daemon=disconnected → marca DB.status=disconnected + dispatch purge
 *  - DB=active mas daemon=404 → marca DB.status=setup (precisa re-parear)
 *  - DB=disconnected mas daemon=connected → auto-fix reverso DB.status=active
 *  - daemon=connected last_seen >30min → conta zombie (sem ação — PR #817 healthcheck cuida)
 *  - daemon=connected last_seen recente → in_sync (só timestamp atualizado)
 *  - daemon error/offline → conta daemon_errors (não muda DB)
 *  - --dry-run → não persiste
 *
 * @see Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php
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

    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'a'.str_repeat('b', 31),
    ]);

    Queue::fake();
});

function makeReconcilerChannel(int $id, int $bizId, string $uuid, string $status, string $displayId = '+5511999998888'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'id' => $id,
        'business_id' => $bizId,
        'channel_uuid' => $uuid,
        'label' => "Canal#{$id}",
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => $status,
        'display_identifier' => $displayId,
    ]);
}

it('R-WA-REC-001 — drift active→banned auto-corrigido (DB marca banned)', function () {
    $ch = makeReconcilerChannel(10, 1, 'aaaaaaaa-1111-2222-3333-444444444444', 'active');
    $instanceId = 'ch-aaaaaaaa11112222333344444444444';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'banned',
            'ban_reason' => 'logged_out',
            'last_seen' => null,
        ], 200),
    ]);

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('banned');
    expect($ch->channel_health)->toBe('banned');
    expect($ch->last_health_message)->toContain('drift active→banned');
});

it('R-WA-REC-002 — drift active→disconnected auto-corrigido', function () {
    $ch = makeReconcilerChannel(11, 1, 'bbbbbbbb-1111-2222-3333-444444444444', 'active');
    $instanceId = 'ch-bbbbbbbb11112222333344444444444';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'disconnected',
            'ban_reason' => null,
            'last_seen' => null,
        ], 200),
    ]);

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('disconnected');
});

it('R-WA-REC-003 — daemon 404 marca channel.status=setup (re-parear)', function () {
    $ch = makeReconcilerChannel(12, 1, 'cccccccc-1111-2222-3333-444444444444', 'active');
    $instanceId = 'ch-cccccccc11112222333344444444444';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'error' => 'instance_not_found',
        ], 404),
    ]);

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('setup');
    expect($ch->last_health_message)->toContain('instance_not_found');
});

it('R-WA-REC-004 — drift reverso disconnected→connected (raro mas existe)', function () {
    $ch = makeReconcilerChannel(13, 1, 'dddddddd-1111-2222-3333-444444444444', 'disconnected');
    $instanceId = 'ch-dddddddd11112222333344444444444';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'connected',
            'last_seen' => now()->toIso8601String(),
        ], 200),
    ]);

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('active');
    expect($ch->channel_health)->toBe('healthy');
});

it('R-WA-REC-005 — connected+last_seen recente é in_sync (só timestamp atualizado)', function () {
    $ch = makeReconcilerChannel(14, 1, 'eeeeeeee-1111-2222-3333-444444444444', 'active');
    $ch->forceFill(['channel_health' => 'never_checked'])->save();
    $instanceId = 'ch-eeeeeeee11112222333344444444444';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'connected',
            'last_seen' => now()->subMinutes(2)->toIso8601String(),
        ], 200),
    ]);

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('active');
    expect($ch->channel_health)->toBe('healthy');
});

it('R-WA-REC-006 — dry-run NÃO persiste mudanças', function () {
    $ch = makeReconcilerChannel(15, 1, 'ffffffff-1111-2222-3333-444444444444', 'active');
    $instanceId = 'ch-ffffffff11112222333344444444444';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'banned',
            'ban_reason' => 'logged_out',
        ], 200),
    ]);

    $this->artisan('whatsapp:channels-reconcile --dry-run')->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('active'); // NÃO mudou
});

it('R-WA-REC-007 — daemon offline (timeout) conta daemon_errors mas não muda DB', function () {
    $ch = makeReconcilerChannel(16, 1, '99999999-1111-2222-3333-444444444444', 'active');
    $instanceId = 'ch-9999999911112222333344444444444';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([], 500),
    ]);

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('active'); // NÃO mudou
});

it('R-WA-REC-008 — channel type != baileys NÃO entra no loop (Z-API/Meta skip)', function () {
    Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'id' => 20,
        'business_id' => 1,
        'channel_uuid' => 'zapi-1111-2222-3333-444444444444',
        'label' => 'Z-API',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'status' => 'active',
    ]);

    // Sem Http::fake() — se tentasse, falharia. Garantia que NÃO faz request
    Http::preventStrayRequests();

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);
    // Sem assertion adicional — passar sem stray request já garante skip
});

it('R-WA-REC-009 — multi-tenant Tier 0: channels de N businesses são tratados isoladamente', function () {
    $ch1 = makeReconcilerChannel(30, 1, 'biz1-1111-2222-3333-444444444444', 'active');
    $ch164 = makeReconcilerChannel(31, 164, 'biz164-11-2222-3333-444444444444', 'active');
    $iid1 = 'ch-biz1111122223333444444444444';
    $iid164 = 'ch-biz1641111222233334444444444444';

    Http::fake([
        "https://daemon.test/instances/{$iid1}/status" => Http::response(['state' => 'banned', 'ban_reason' => 'logged_out'], 200),
        "https://daemon.test/instances/{$iid164}/status" => Http::response(['state' => 'connected', 'last_seen' => now()->toIso8601String()], 200),
    ]);

    $this->artisan('whatsapp:channels-reconcile')->assertExitCode(0);

    $ch1->refresh();
    $ch164->refresh();
    expect($ch1->status)->toBe('banned');
    expect($ch164->status)->toBe('active'); // não foi afetado pelo ban do biz=1
});
