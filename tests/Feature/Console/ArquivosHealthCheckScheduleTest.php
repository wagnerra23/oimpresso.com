<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

/**
 * Sprint 2 ADR 0123 — Verifica que o scheduler tem o comando
 * `arquivos:health-check --alert` registrado pra rodar diário 06:30 BRT
 * sem overlap, somente em ambiente live.
 *
 * Defasagem 30min do jana:health-check (06:00) pra não disputar DB.
 * Garante compliance LGPD ativo sem intervenção manual.
 *
 * Nota: NÃO declarar `uses(Tests\TestCase::class)` aqui — `tests/Pest.php:5`
 * já registra TestCase pra toda pasta `tests/Feature/`. Redeclarar gera
 * "The folder already uses the test case [Tests\TestCase]" e bloqueia
 * `vendor/bin/pest --filter`. Catalogado em
 * memory/decisions/proposals/drafts/_AGENT_A_AUDIT_FINDINGS.md:210-229.
 */

it('agenda arquivos:health-check daily 06:30 BRT', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'arquivos:health-check'));

    expect($events)->not->toBeEmpty('schedule deveria ter arquivos:health-check registrado');
    expect($events->first()->expression)->toBe('30 6 * * *', 'deveria rodar às 06:30 todo dia (cron 30 6 * * *)');
    expect($events->first()->timezone)->toBe('America/Sao_Paulo', 'timezone deve ser BRT');
});

it('schedule tem withoutOverlapping ativado', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'arquivos:health-check'));

    expect($events)->not->toBeEmpty();
    expect($events->first()->withoutOverlapping)->toBeTrue('withoutOverlapping é obrigatório pra evitar concorrência em cron tardio');
});

it('schedule tem flag --alert no command', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'arquivos:health-check'));

    expect($events)->not->toBeEmpty();
    expect($events->first()->command)->toContain('--alert', '--alert é obrigatório para integração com monitoring (exit code 2=FAIL / 1=WARN)');
});

it('schedule arquivos:health-check só roda em ambiente live', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'arquivos:health-check'));

    expect($event)->not->toBeNull();

    // Checa environments via reflection (property protected no Event do Laravel)
    $ref = new \ReflectionClass($event);
    if ($ref->hasProperty('environments')) {
        $prop = $ref->getProperty('environments');
        $prop->setAccessible(true);
        $envs = $prop->getValue($event);

        expect($envs)->toContain('live', 'deve rodar em live');
        expect($envs)->not->toContain('testing', 'não deve rodar em testing (evita poluir CI)');
    }
});
