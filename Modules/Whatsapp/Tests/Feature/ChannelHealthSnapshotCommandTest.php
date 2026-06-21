<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Console\Commands\ChannelHealthSnapshotCommand as Snap;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

uses(Tests\TestCase::class);

/**
 * ChannelHealthSnapshotCommandTest — observabilidade de canal (ADR 0288).
 *
 * Cobre a decisão PURA do alerta (catraca shouldAlert) + o snapshot append-only +
 * o alerta no cruzamento do limiar. Schema sintético espelha HealthProbeChannelsCommandTest.
 *
 * FASE 2 (ADR 0288): além do Log, o alerta publica no Centrifugo (realtime) e grava
 * em `mcp_alertas_eventos` (a notificação que chega no humano) — coberto abaixo.
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

    // FASE 2 — store de eventos disparados (subset suficiente; schema canônico da
    // migration Jana 2026_04_29_600001). É o destino do alerta que chega no humano.
    Schema::dropIfExists('mcp_alertas_eventos');
    Schema::create('mcp_alertas_eventos', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('business_id')->nullable();
        $table->string('tipo', 50);
        $table->string('severidade', 20)->default('medium');
        $table->string('titulo', 200);
        $table->text('descricao')->nullable();
        $table->string('chave_idempotencia', 200)->unique();
        $table->json('metadata')->nullable();
        $table->enum('status', ['aberto', 'notificado', 'ack', 'arquivado'])->default('aberto');
        $table->timestamp('criado_em')->nullable();
        $table->timestamp('notificado_em')->nullable();
        $table->timestamp('ack_em')->nullable();
        $table->unsignedInteger('ack_by_user_id')->nullable();
        $table->timestamps();
    });

    // Channel usa Spatie LogsActivity → o 1º create insere em `activity_log`.
    // Schema sintético não migra essa tabela; cria-a guardada (idioma do projeto,
    // ver tests/Pest.php RecurringBilling).
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
    }

    config(['whatsapp.whatsmeow.health_alert_after_minutes' => 10]);
});

afterEach(function () {
    Carbon::setTestNow(); // garante que nenhum freeze de tempo vaze entre testes
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

// ── FASE 2: sinks Centrifugo + mcp_alertas_eventos ──────────────────────
it('FASE 2: publica o alerta no Centrifugo (canal do business + event channel_alert)', function () {
    $spy = Mockery::spy(CentrifugoPublisher::class);
    app()->instance(CentrifugoPublisher::class, $spy);

    $ch = makeSnapChannel(7, 'disconnected');
    DB::table('channel_health_snapshots')->insert([
        'business_id' => 7, 'channel_id' => $ch->id, 'channel_health' => 'disconnected',
        'recorded_at' => now()->subMinutes(12), // cruza o limiar (12 >= 10)
    ]);

    \Artisan::call('whatsapp:channel-health-snapshot');

    $spy->shouldHaveReceived('publish')
        ->withArgs(function (string $channel, array $data) use ($ch) {
            return $channel === "whatsapp:business:{$ch->business_id}"
                && ($data['event'] ?? null) === 'whatsmeow.channel_alert'
                && (int) ($data['channel_id'] ?? 0) === (int) $ch->id
                && ($data['channel_health'] ?? null) === 'disconnected'
                && (int) ($data['threshold_minutes'] ?? 0) === 10;
        })
        ->once();
});

it('FASE 2: grava o alerta em mcp_alertas_eventos e não duplica na mesma streak', function () {
    Carbon::setTestNow(now()); // congela: 2ª rodada cai na MESMA streak → mesma chave

    $ch = makeSnapChannel(3, 'disconnected');
    DB::table('channel_health_snapshots')->insert([
        'business_id' => 3, 'channel_id' => $ch->id, 'channel_health' => 'disconnected',
        'recorded_at' => now()->subMinutes(12),
    ]);

    \Artisan::call('whatsapp:channel-health-snapshot');

    $ev = DB::table('mcp_alertas_eventos')->where('tipo', 'whatsapp_channel_down')->first();
    expect($ev)->not->toBeNull();
    expect((int) $ev->business_id)->toBe(3);  // Tier 0: tenant real do canal
    expect($ev->severidade)->toBe('high');
    expect($ev->status)->toBe('aberto');

    // rodar de novo na mesma streak NÃO duplica (dedup primária = shouldAlert;
    // chave_idempotencia ancorada no down-since = rede de segurança).
    \Artisan::call('whatsapp:channel-health-snapshot');
    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'whatsapp_channel_down')->count())->toBe(1);
});
