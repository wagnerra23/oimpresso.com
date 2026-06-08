<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

/**
 * Regression test pro drift sentinel — alerta quando daemon CT 100 fica
 * desatualizado vs main local.
 *
 * Wagner 2026-05-13: "deveria ter um teste desses se isso acontecer?".
 *
 * Cenários cobertos:
 *   1. Daemon retorna mesmo SHA do main local → in sync (exit 0)
 *   2. Daemon retorna SHA diferente → drift (exit 0 default, exit 1 com --fail-on-drift)
 *   3. Daemon não expõe daemon_source_sha (versão velha) → warning suave (exit 0)
 *   4. Daemon offline → FAILURE
 *   5. Config ausente → FAILURE
 *
 * @see Modules/Whatsapp/Console/Commands/DaemonSourceDriftCheckCommand.php
 */
beforeEach(function () {
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'a'.str_repeat('b', 31),
    ]);
});

it('R-WA-DRIFT-001 — daemon retorna mesmo SHA é in sync (exit 0)', function () {
    // Captura SHA local real do source
    $localSha = trim((string) shell_exec(
        'git -C ' . escapeshellarg(base_path()) . ' rev-parse HEAD:Modules/Whatsapp/daemon-node/src 2>/dev/null'
    ));

    if ($localSha === '' || strlen($localSha) < 40) {
        $this->markTestSkipped('git rev-parse não retornou SHA — pulando (CI shallow clone?).');
    }

    Http::fake([
        'https://daemon.test/health' => Http::response([
            'status' => 'ok',
            'daemon_source_sha' => $localSha,
        ], 200),
    ]);

    $this->artisan('whatsapp:daemon-source-drift-check')
        ->expectsOutputToContain('EM SYNC')
        ->assertExitCode(0);
});

it('R-WA-DRIFT-002 — SHA diferente é drift mas default exit 0 (não trava cron)', function () {
    Http::fake([
        'https://daemon.test/health' => Http::response([
            'status' => 'ok',
            'daemon_source_sha' => '0000000000000000000000000000000000000000', // SHA fake antigo
        ], 200),
    ]);

    $this->artisan('whatsapp:daemon-source-drift-check')
        ->expectsOutputToContain('DRIFT detectado')
        ->assertExitCode(0); // default — alerta mas não falha cron
});

it('R-WA-DRIFT-003 — SHA diferente + --fail-on-drift exit 1 (CI usage)', function () {
    Http::fake([
        'https://daemon.test/health' => Http::response([
            'status' => 'ok',
            'daemon_source_sha' => '1111111111111111111111111111111111111111',
        ], 200),
    ]);

    $this->artisan('whatsapp:daemon-source-drift-check --fail-on-drift')
        ->expectsOutputToContain('DRIFT detectado')
        ->assertExitCode(1);
});

it('R-WA-DRIFT-004 — daemon sem daemon_source_sha (versão velha) warning suave (exit 0)', function () {
    Http::fake([
        'https://daemon.test/health' => Http::response([
            'status' => 'ok',
            // SEM daemon_source_sha — daemon antigo pré-PR safeguards
        ], 200),
    ]);

    $this->artisan('whatsapp:daemon-source-drift-check')
        ->expectsOutputToContain('versão pré-PR safeguards')
        ->assertExitCode(0);
});

it('R-WA-DRIFT-005 — daemon HTTP 503 (degraded) é FAILURE', function () {
    Http::fake([
        'https://daemon.test/health' => Http::response([
            'status' => 'degraded',
        ], 503),
    ]);

    $this->artisan('whatsapp:daemon-source-drift-check')
        ->expectsOutputToContain('HTTP 503')
        ->assertExitCode(1);
});

it('R-WA-DRIFT-006 — config daemon_url ausente FAILURE imediato', function () {
    config(['whatsapp.baileys.daemon_url' => '']);

    $this->artisan('whatsapp:daemon-source-drift-check')
        ->expectsOutputToContain('ausente')
        ->assertExitCode(1);
});
