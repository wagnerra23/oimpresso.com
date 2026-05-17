<?php

declare(strict_types=1);

/**
 * KbDriftDetectorScheduleTest — Wave 27 §G2 — valida schedule weekly Sun 03:00 BRT.
 *
 * Smoke test do registro `kb:drift-detector` em KBServiceProvider::registerSchedule.
 *
 * Cobre:
 *   - Schedule está registrado (não silenciosamente ausente)
 *   - Cron expression = "0 3 * * 0" (Sun 03:00, domingo=0)
 *   - Timezone America/Sao_Paulo
 *   - withoutOverlapping ativo (mutex 60min)
 *   - onOneServer ativo (race-condition guard multi-server)
 *   - Comando alvo = "kb:drift-detector --business-id=1"
 *
 * Tier 0 multi-tenant: schedule dispara biz=1 (superadmin) — per-business
 * scheduling fica pra Wave 28+ quando mcp_briefs.cycle estabilizar.
 *
 * @group ragas
 * @group governance
 * @see Modules/KB/Providers/KBServiceProvider.php :: registerSchedule
 * @see Wave 27 §G2 — schedule weekly drift detector
 */

use Illuminate\Console\Scheduling\Schedule;

// TestCase aplicado via tests/Pest.php uses(TestCase::class)->in(KbFeatureDir).

beforeEach(function () {
    $this->schedule = app(Schedule::class);
});

it('schedule registra kb:drift-detector weekly Sun 03:00 BRT', function () {
    $events = $this->schedule->events();

    $kbEvent = collect($events)->first(function ($event) {
        return str_contains((string) $event->command, 'kb:drift-detector')
            || str_contains((string) ($event->description ?? ''), 'kb:drift-detector');
    });

    expect($kbEvent)->not->toBeNull('kb:drift-detector schedule NÃO registrado em KBServiceProvider');

    // Cron weeklyOn(0, '03:00') = "0 3 * * 0" (minuto 0, hora 3, domingo)
    expect($kbEvent->expression)->toBe('0 3 * * 0');
})->group('ragas')->group('governance');

it('schedule kb:drift-detector usa timezone America/Sao_Paulo', function () {
    $events = $this->schedule->events();

    $kbEvent = collect($events)->first(fn ($e) => str_contains((string) $e->command, 'kb:drift-detector'));

    expect($kbEvent)->not->toBeNull();
    expect($kbEvent->timezone)->toBe('America/Sao_Paulo');
})->group('ragas')->group('governance');

it('schedule kb:drift-detector tem withoutOverlapping ativo (mutex)', function () {
    $events = $this->schedule->events();

    $kbEvent = collect($events)->first(fn ($e) => str_contains((string) $e->command, 'kb:drift-detector'));

    expect($kbEvent)->not->toBeNull();
    expect($kbEvent->withoutOverlapping)->toBeTrue('withoutOverlapping deve estar ativo pra prevenir runs concorrentes');
})->group('ragas')->group('governance');

it('schedule kb:drift-detector tem onOneServer ativo (multi-server guard)', function () {
    $events = $this->schedule->events();

    $kbEvent = collect($events)->first(fn ($e) => str_contains((string) $e->command, 'kb:drift-detector'));

    expect($kbEvent)->not->toBeNull();
    expect($kbEvent->onOneServer)->toBeTrue('onOneServer deve estar ativo pra evitar race CT 100 + Hostinger');
})->group('ragas')->group('governance');

it('schedule kb:drift-detector passa --business-id=1 (Tier 0 ADR 0093)', function () {
    $events = $this->schedule->events();

    $kbEvent = collect($events)->first(fn ($e) => str_contains((string) $e->command, 'kb:drift-detector'));

    expect($kbEvent)->not->toBeNull();
    expect((string) $kbEvent->command)->toContain('--business-id=1');
})->group('ragas')->group('governance');
