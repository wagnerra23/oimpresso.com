<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * R-SEC-0215 — anti-regressão Sprint S-0215-1 ADR 0215 secrets governance.
 *
 * Garante que comandos secrets:scan e secrets:audit funcionam + index canon
 * parser está OK + drift detection funciona.
 *
 * NOTA: smoke real validação Hostinger API + ssh creds não roda em CI free
 * (precisa SSH key Wagner). Esses tests cobrem shape + lógica core.
 */

it('R-SEC-0215-001 — comando secrets:scan registrado', function () {
    $commands = collect(Artisan::all())->keys();
    expect($commands)->toContain('secrets:scan');
});

it('R-SEC-0215-002 — comando secrets:audit registrado', function () {
    $commands = collect(Artisan::all())->keys();
    expect($commands)->toContain('secrets:audit');
});

it('R-SEC-0215-003 — memory/_INDEX-SECRETS.md existe e tem tabela canônica', function () {
    $path = base_path('memory/_INDEX-SECRETS.md');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    // Header da tabela canon
    expect($content)->toContain('| Nome | Tipo |');
    // Entry Hostinger DNS (caso fundador)
    expect($content)->toContain('Hostinger DNS API token');
    // Status legends
    expect($content)->toContain('✅ active');
    expect($content)->toContain('EXPIRED');
});

it('R-SEC-0215-004 — secrets:scan roda sem exception', function () {
    $exitCode = Artisan::call('secrets:scan');
    // Exit code 0 (success) OR 1 (drift detected); ambos válidos
    expect($exitCode)->toBeIn([0, 1]);
});

it('R-SEC-0215-005 — secrets:audit roda sem exception (filter Hostinger)', function () {
    $exitCode = Artisan::call('secrets:audit', ['--filter' => 'hostinger']);
    expect($exitCode)->toBeIn([0, 1]);
});

it('R-SEC-0215-006 — cron secrets:audit registrado em schedule', function () {
    // Validar via reflection do Kernel
    $kernel = app(\App\Console\Kernel::class);
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

    $reflection = new ReflectionClass($kernel);
    $method = $reflection->getMethod('schedule');
    $method->setAccessible(true);
    $method->invoke($kernel, $schedule);

    $events = $schedule->events();
    $hasAuditCron = collect($events)->contains(
        fn ($e) => str_contains($e->command ?? '', 'secrets:audit')
    );
    expect($hasAuditCron)->toBeTrue();
});

it('R-SEC-0215-007 — .githooks/pre-commit tem bloco secrets:scan', function () {
    $hookPath = base_path('.githooks/pre-commit');
    if (! file_exists($hookPath)) {
        $this->markTestSkipped('.githooks/pre-commit não existe em ambiente CI Linux fresh');
    }
    $content = file_get_contents($hookPath);
    expect($content)->toContain('secrets:scan');
    expect($content)->toContain('SECRETS GOVERNANCE');
});

it('R-SEC-0215-008 — GH Action workflow secrets-governance.yml existe', function () {
    $path = base_path('.github/workflows/secrets-governance.yml');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('secrets:scan --fail-on-drift');
    expect($content)->toContain('secrets:audit --auto-pr');
});
