<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * Regression test pro reset 1-comando.
 *
 * Wagner 2026-05-13: "como resolve isso vai sempre você?". Este comando
 * resolve sem precisar chamar Claude.
 *
 * @see Modules/Whatsapp/Console/Commands/ChannelResetCommand.php
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

    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'a'.str_repeat('b', 31),
    ]);
});

function makeResetChannel(int $id, string $uuid, string $status): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'id' => $id,
        'business_id' => 1,
        'channel_uuid' => $uuid,
        'label' => "Reset test #{$id}",
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => $status,
        'channel_health_consecutive_failures' => 5,
    ]);
}

it('R-WA-RST-001 — reset purge daemon + reset DB status=setup', function () {
    $ch = makeResetChannel(50, 'aaaaaaaa-1111-2222-3333-555555555555', 'banned');
    $iid = 'ch-aaaaaaaa11112222333355555555555';

    Http::fake([
        "https://daemon.test/instances/{$iid}" => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('whatsapp:channel-reset', ['channel_id' => 50])->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('setup');
    expect($ch->channel_health)->toBe('never_checked');
    expect($ch->channel_health_consecutive_failures)->toBe(0);

    Http::assertSent(fn ($req) => $req->method() === 'DELETE');
});

it('R-WA-RST-002 — daemon 404 (instância não existia) ainda completa reset DB', function () {
    $ch = makeResetChannel(51, 'bbbbbbbb-1111-2222-3333-555555555555', 'disconnected');
    $iid = 'ch-bbbbbbbb11112222333355555555555';

    Http::fake([
        "https://daemon.test/instances/{$iid}" => Http::response(['error' => 'instance_not_found'], 404),
    ]);

    $this->artisan('whatsapp:channel-reset', ['channel_id' => 51])->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('setup'); // resetou mesmo com 404
});

it('R-WA-RST-003 — --reconnect também dispara POST /connect', function () {
    $ch = makeResetChannel(52, 'cccccccc-1111-2222-3333-555555555555', 'banned');
    $iid = 'ch-cccccccc11112222333355555555555';

    Http::fake([
        "https://daemon.test/instances/{$iid}" => Http::response(['ok' => true], 200),
        "https://daemon.test/instances/{$iid}/connect" => Http::response([
            'state' => 'connecting',
        ], 202),
    ]);

    $this->artisan('whatsapp:channel-reset', ['channel_id' => 52, '--reconnect' => true])->assertExitCode(0);

    Http::assertSent(fn ($req) => $req->method() === 'DELETE');
    Http::assertSent(fn ($req) => $req->method() === 'POST' && str_contains($req->url(), '/connect'));
});

it('R-WA-RST-004 — --dry-run NÃO chama daemon nem persiste', function () {
    $ch = makeResetChannel(53, 'dddddddd-1111-2222-3333-555555555555', 'banned');

    Http::preventStrayRequests();

    $this->artisan('whatsapp:channel-reset', ['channel_id' => 53, '--dry-run' => true])->assertExitCode(0);

    $ch->refresh();
    expect($ch->status)->toBe('banned'); // NÃO mudou
});

it('R-WA-RST-005 — channel não-existente devolve FAILURE', function () {
    $this->artisan('whatsapp:channel-reset', ['channel_id' => 99999])->assertExitCode(1);
});

it('R-WA-RST-006 — channel type != baileys devolve FAILURE (Z-API/Meta sem daemon)', function () {
    Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'id' => 60,
        'business_id' => 1,
        'channel_uuid' => 'zapi-1111-2222-3333-666666666666',
        'label' => 'Z-API',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'status' => 'active',
    ]);

    $this->artisan('whatsapp:channel-reset', ['channel_id' => 60])->assertExitCode(1);
});
