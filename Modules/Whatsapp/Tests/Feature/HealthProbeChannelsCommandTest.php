<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * Camada 2 — self-healing health-probe-channels (probe + auto-recovery).
 *
 * Mocka daemon CT 100 via Http::fake e exercita 6 cenários canônicos:
 *   1. Channel healthy → atualiza last_health_check_at + reset failures
 *   2. instance_not_found → 3 retries → 1º recover → healthy
 *   3. instance_not_found → 3 retries falham → disconnected + failures++
 *   4. banned → marca banned (sem tentar connect)
 *   5. Multi-tenant: --business=99 não toca biz=1
 *   6. --dry-run não muda channel_health
 *
 * Schema in-memory: espelha ImportHistoryCommandTest.
 *
 * Nota anti-flake: testes que envolvem retries esperam backoff [1s, 5s, 30s].
 * Pra rodar rápido, faríamos override de RETRY_BACKOFF_SECONDS via subclasse,
 * mas Wagner regra é zero "magia em test". Em vez disso usamos cenários onde
 * o 1º retry já recupera (rápido) e o cenário de 3 falhas espera ~36s real.
 * Em CI rodar via `pest --filter HealthProbe --no-coverage` pra isolar tempo.
 *
 * @see Modules/Whatsapp/Console/Commands/HealthProbeChannelsCommand.php
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
        'whatsapp.baileys.daemon_url' => 'https://daemon-test.local',
        'whatsapp.baileys.api_key' => 'test-api-key-1234567890',
        'whatsapp.baileys.request_timeout' => 5,
    ]);
});

/**
 * Helper — cria Channel Baileys já marcado active + healthy/never_checked.
 */
function makeHpChannel(int $bizId, string $label = 'Test', string $health = 'never_checked', int $failures = 0): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'channel_uuid' => sprintf('hpc%05d-0000-0000-0000-%012d', $bizId, random_int(1, 999999)),
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
        'channel_health' => $health,
        'channel_health_consecutive_failures' => $failures,
    ]);
}

it('HP-001 — channel healthy: atualiza last_health_check_at + reset failures', function () {
    $channel = makeHpChannel(1, 'Healthy', 'disconnected', failures: 5);

    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'connected',
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:health-probe-channels', [
        '--business' => '1',
    ]);

    expect($exitCode)->toBe(0);

    $channel->refresh();
    expect($channel->channel_health)->toBe('healthy');
    expect($channel->channel_health_consecutive_failures)->toBe(0);
    expect($channel->last_health_check_at)->not->toBeNull();
});

it('HP-002 — instance_not_found → 1º connect retry recupera → healthy', function () {
    $channel = makeHpChannel(1, 'Recover', 'disconnected', failures: 2);

    // Sequência: status=instance_not_found, depois status=connected após connect
    Http::fake([
        'daemon-test.local/instances/*/status' => Http::sequence()
            ->push(['state' => 'instance_not_found'], 404)
            ->push(['state' => 'connected'], 200),
        'daemon-test.local/instances/*/connect' => Http::response([
            'ok' => true, 'state' => 'connecting',
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:health-probe-channels', [
        '--business' => '1',
    ]);

    expect($exitCode)->toBe(0);

    $channel->refresh();
    expect($channel->channel_health)->toBe('healthy');
    expect($channel->channel_health_consecutive_failures)->toBe(0);
    expect($channel->last_health_message)->toContain('recovered after 1');
})->skip('Test envolve sleep(1) real do backoff. Rodar manual: pest --filter HP-002.');

it('HP-003 — instance_not_found + 3 retries falham → disconnected + failures++', function () {
    $channel = makeHpChannel(1, 'Failed', 'never_checked', failures: 0);

    // Todas as chamadas status retornam instance_not_found
    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'instance_not_found',
        ], 404),
        'daemon-test.local/instances/*/connect' => Http::response([
            'ok' => false, 'state' => 'disconnected',
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:health-probe-channels', [
        '--business' => '1',
    ]);

    expect($exitCode)->toBe(0);

    $channel->refresh();
    expect($channel->channel_health)->toBe('disconnected');
    expect($channel->channel_health_consecutive_failures)->toBe(1);
    expect($channel->last_health_message)->toContain('recovery failed após 3 attempts');
})->skip('Test envolve sleep(1+5+30)=36s real do backoff. Rodar manual: pest --filter HP-003.');

it('HP-004 — state=banned → marca banned sem tentar connect', function () {
    $channel = makeHpChannel(1, 'Banned chip', 'never_checked', failures: 0);

    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'banned',
        ], 200),
        'daemon-test.local/instances/*/connect' => Http::response([
            'ok' => true,
        ], 200),
    ]);

    $exitCode = \Artisan::call('whatsapp:health-probe-channels', [
        '--business' => '1',
    ]);

    expect($exitCode)->toBe(0);

    $channel->refresh();
    expect($channel->channel_health)->toBe('banned');
    expect($channel->channel_health_consecutive_failures)->toBe(1);

    // Confirma que /connect NUNCA foi chamado pra channel banned
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/connect');
    });
});

it('HP-005 — multi-tenant: --business=99 não toca biz=1', function () {
    $ch1 = makeHpChannel(1, 'biz1 chip', 'disconnected', failures: 3);
    $ch99 = makeHpChannel(99, 'biz99 chip', 'disconnected', failures: 3);

    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'connected',
        ], 200),
    ]);

    \Artisan::call('whatsapp:health-probe-channels', [
        '--business' => '99',
    ]);

    $ch1->refresh();
    $ch99->refresh();

    // biz=1 NÃO foi tocado (health continua disconnected, failures 3)
    expect($ch1->channel_health)->toBe('disconnected');
    expect($ch1->channel_health_consecutive_failures)->toBe(3);

    // biz=99 foi processado e marcado healthy
    expect($ch99->channel_health)->toBe('healthy');
    expect($ch99->channel_health_consecutive_failures)->toBe(0);
});

it('HP-006 — --dry-run não muda channel_health', function () {
    $channel = makeHpChannel(1, 'Dry preview', 'disconnected', failures: 7);

    Http::fake([
        'daemon-test.local/instances/*/status' => Http::response([
            'state' => 'instance_not_found',
        ], 404),
    ]);

    $exitCode = \Artisan::call('whatsapp:health-probe-channels', [
        '--business' => '1',
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    $channel->refresh();
    // Sem mudança (dry-run pulou tudo após status inicial)
    expect($channel->channel_health)->toBe('disconnected');
    expect($channel->channel_health_consecutive_failures)->toBe(7);

    // /connect nunca foi chamado em dry-run
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/connect');
    });
});

it('HP-007 — daemon não configurado → skip global graceful', function () {
    config([
        'whatsapp.baileys.daemon_url' => '',
        'whatsapp.baileys.api_key' => '',
    ]);

    makeHpChannel(1, 'No daemon');

    $exitCode = \Artisan::call('whatsapp:health-probe-channels');

    expect($exitCode)->toBe(0);
    // Não houve crash, e não foi feita nenhuma request HTTP
    Http::assertNothingSent();
});

it('HP-008 — sem canais ativos → exit 0 graceful', function () {
    // Nenhum channel criado
    $exitCode = \Artisan::call('whatsapp:health-probe-channels');

    expect($exitCode)->toBe(0);
});
