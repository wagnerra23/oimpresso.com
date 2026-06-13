<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * Regression test pro helper `Channel::baileysInstanceId()` (hardening
 * pós-PR #815 — centraliza convenção `ch-{uuid sem hífens}` evitando drift
 * entre Observer + Job + runbook agent whatsapp-doctor).
 *
 * Garante:
 *   1. Helper retorna formato canônico esperado em produção
 *   2. Retorna null pra type != baileys (defensive — Z-API/Meta não têm daemon)
 *   3. Retorna null pra channel_uuid vazio (defensive)
 *   4. Observer continua usando o helper (drift detector)
 *
 * @see Modules/Whatsapp/Entities/Channel.php::baileysInstanceId()
 * @see Modules/Whatsapp/Observers/ChannelObserver.php::resolveInstanceId()
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
});

it('R-WA-INSTID-001 — Baileys channel devolve ch-{uuid sem hífens} (caso real prod 2026-05-13)', function () {
    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 164,
        'channel_uuid' => '88b13697-b89e-451c-b65b-e917533bab21',
        'label' => 'Jana MARTINHO CAÇAMBAS',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    expect($ch->baileysInstanceId())->toBe('ch-88b13697b89e451cb65be917533bab21');
});

it('R-WA-INSTID-002 — Z-API channel devolve null (defensive — sem daemon CT 100)', function () {
    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'label' => 'Z-API biz1',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'status' => 'active',
    ]);

    expect($ch->baileysInstanceId())->toBeNull();
});

it('R-WA-INSTID-003 — Meta Cloud channel devolve null', function () {
    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'metameta-1111-2222-3333-444444444444',
        'label' => 'Meta Cloud',
        'type' => Channel::TYPE_WHATSAPP_META,
        'status' => 'active',
    ]);

    expect($ch->baileysInstanceId())->toBeNull();
});

it('R-WA-INSTID-004 — channel_uuid vazio devolve null (defensive)', function () {
    $ch = new Channel();
    $ch->type = Channel::TYPE_WHATSAPP_BAILEYS;
    $ch->channel_uuid = null;

    expect($ch->baileysInstanceId())->toBeNull();
});

it('R-WA-INSTID-005 — convenção exata: 32 chars hex sem hífens precedidos de "ch-"', function () {
    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'da8c23c5-5a6c-4538-b82f-1a05c47ac5da',
        'label' => 'Teste convenção',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'banned',
    ]);

    $instanceId = $ch->baileysInstanceId();
    expect($instanceId)->toBe('ch-da8c23c55a6c4538b82f1a05c47ac5da');
    expect($instanceId)->toMatch('/^ch-[a-f0-9]{32}$/');
});
