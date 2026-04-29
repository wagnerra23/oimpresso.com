<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * MEM-MET-3 (ADRs 0050+0051) — Verifica que o scheduler tem o comando
 * `copiloto:metrics:apurar --business=all` registrado pra rodar diário 23:55
 * sem overlap, em ambientes local + live.
 *
 * Garantia: cron Hostinger registra 1 linha/dia/business sem intervenção.
 */

it('schedule registra copiloto:metrics:apurar diariamente às 23:55', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $eventos = collect($schedule->events());

    $metricasEvent = $eventos->first(function ($event) {
        return str_contains($event->command ?? '', 'copiloto:metrics:apurar');
    });

    expect($metricasEvent)->not->toBeNull('schedule deveria ter copiloto:metrics:apurar');
    expect($metricasEvent->expression)->toBe('55 23 * * *', 'deveria rodar 23:55 todo dia');
    expect($metricasEvent->command)->toContain('--business=all');
    expect($metricasEvent->withoutOverlapping)->toBeTrue();
});

it('schedule MEM-MET-3 só roda em local/live (não em testing)', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $eventos = collect($schedule->events());
    $metricasEvent = $eventos->first(fn ($e) => str_contains($e->command ?? '', 'copiloto:metrics:apurar'));

    expect($metricasEvent)->not->toBeNull();
    // O Schedule guarda os env filters numa property protected; checa via reflection
    $ref = new \ReflectionClass($metricasEvent);
    if ($ref->hasProperty('environments')) {
        $prop = $ref->getProperty('environments');
        $prop->setAccessible(true);
        $envs = $prop->getValue($metricasEvent);
        expect($envs)->toContain('local');
        expect($envs)->toContain('live');
        expect($envs)->not->toContain('testing');
    }
});
