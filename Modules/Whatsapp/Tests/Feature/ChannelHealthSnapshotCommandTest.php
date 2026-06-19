<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Console\Commands\ChannelHealthSnapshotCommand as Snap;
use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * ChannelHealthSnapshotCommandTest — observabilidade de canal (ADR 0288).
 *
 * Cobre a decisão PURA do alerta (catraca shouldAlert) + o snapshot append-only +
 * o alerta no cruzamento do limiar. Schema sintético espelha HealthProbeChannelsCommandTest.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('schema sintético manual incompatível com MySQL persistente.');
    }

    Schema::dropIfExists('channels');
    Schema::dropIfExists('channel_health_snapshots');

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
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('channel_health_snapshots', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->string('channel_health', 20);
        $table->timestamp('recorded_at')->nullable();
    });

    config(['whatsapp.whatsmeow.health_alert_after_minutes' => 10]);
});

function makeSnapChannel(int $bizId, string $health = 'healthy'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'channel_uuid' => sprintf('snap%04d-0000-0000-0000-%012d', $bizId, random_int(1, 999999)),
        'label' => "Canal {$bizId}",
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'active',
        'channel_health' => $health,
    ]);
}

// ── catraca PURA do alerta ──────────────────────────────────────────────
it('shouldAlert: não caído / dentro do limiar → false', function () {
    expect(Snap::shouldAlert(null, null, 10))->toBeFalse();      // não caído
    expect(Snap::shouldAlert(5.0, null, 10))->toBeFalse();       // 5 < 10
});

it('shouldAlert: cruzou o limiar agora → true (dispara 1×)', function () {
    expect(Snap::shouldAlert(12.0, null, 10))->toBeTrue();       // streak começou e já passou
    expect(Snap::shouldAlert(11.0, 8.0, 10))->toBeTrue();        // prev < limiar, now >= limiar = cruzamento
});

it('shouldAlert: já alertou nesta streak → false (sem spam)', function () {
    expect(Snap::shouldAlert(25.0, 15.0, 10))->toBeFalse();      // prev já >= limiar
});

// ── snapshot + alerta ───────────────────────────────────────────────────
it('grava um snapshot append-only por canal ativo', function () {
    makeSnapChannel(1, 'healthy');

    \Artisan::call('whatsapp:channel-health-snapshot');

    expect(DB::table('channel_health_snapshots')->count())->toBe(1);
    $row = DB::table('channel_health_snapshots')->first();
    expect($row->channel_health)->toBe('healthy');
});

it('alerta quando canal cruza o limiar de queda (down > N min)', function () {
    $ch = makeSnapChannel(1, 'disconnected');
    // snapshot anterior caído há 12 min → ao gravar o atual, streak = 12min (>= 10), prev=0 → cruzamento.
    DB::table('channel_health_snapshots')->insert([
        'business_id' => 1, 'channel_id' => $ch->id, 'channel_health' => 'disconnected',
        'recorded_at' => now()->subMinutes(12),
    ]);

    \Artisan::call('whatsapp:channel-health-snapshot');
    $out = \Artisan::output();

    expect($out)->toContain('alertas: 1');
});

it('--dry-run não grava nem alerta', function () {
    makeSnapChannel(1, 'disconnected');
    DB::table('channel_health_snapshots')->insert([
        'business_id' => 1, 'channel_id' => 1, 'channel_health' => 'disconnected',
        'recorded_at' => now()->subMinutes(30),
    ]);

    \Artisan::call('whatsapp:channel-health-snapshot', ['--dry-run' => true]);

    // só o snapshot seed (1), nenhum novo gravado
    expect(DB::table('channel_health_snapshots')->count())->toBe(1);
    expect(\Artisan::output())->toContain('(dry-run)');
});
