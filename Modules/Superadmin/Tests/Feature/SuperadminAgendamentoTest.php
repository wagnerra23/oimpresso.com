<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Superadmin\Providers\SuperadminServiceProvider;

uses(Tests\TestCase::class);

/**
 * Trava o agendamento do Superadmin (lote L4, 2026-11-16).
 *
 * Origem: `superadmin:health` estava registrado desde a Wave 23 e nada o agendava, embora o
 * docblock dissesse "roda 06:20". Medido no `schedule:list` de produção (HEAD fd6cbaa2c4):
 * os outros crons do Superadmin apareciam, ele não.
 *
 * O teste chama `SuperadminServiceProvider::agendar()` — o MESMO método que o provider chama
 * em `live` — contra um Schedule novo. O gate de ambiente (`app.env === 'live'`) não entra
 * aqui: em `testing` o bloco nem existe, e a prova dele é o `schedule:list` de produção.
 */
function eventosDoSuperadmin(): array
{
    $schedule = new Schedule();
    (new SuperadminServiceProvider(app()))->agendar($schedule);

    return $schedule->events();
}

function eventoQueRoda(array $eventos, string $comando): ?Event
{
    foreach ($eventos as $evento) {
        if (str_contains((string) $evento->command, $comando)) {
            return $evento;
        }
    }

    return null;
}

test('superadmin:health é agendado às 06:20 BRT, com --notify', function () {
    $evento = eventoQueRoda(eventosDoSuperadmin(), 'superadmin:health');

    expect($evento)->not->toBeNull();
    expect($evento->expression)->toBe('20 6 * * *');
    expect($evento->timezone)->toBe('America/Sao_Paulo');
    expect((string) $evento->command)->toContain('--notify');
});

test('os outros crons do Superadmin continuam agendados', function (string $comando) {
    expect(eventoQueRoda(eventosDoSuperadmin(), $comando))->not->toBeNull();
})->with([
    'pos:sendSubscriptionExpiryAlert',
    'paymentgateway:emit-trial-expired',
    'queue:work database --queue=superadmin-mail',
]);

/**
 * CONTROLE NEGATIVO — prova que a busca sabe não achar: um Schedule vazio não tem o
 * health-check, e um comando que não existe não aparece no agendamento real.
 */
test('CONTROLE NEGATIVO — a busca não acha o que não foi agendado', function () {
    expect(eventoQueRoda((new Schedule())->events(), 'superadmin:health'))->toBeNull();
    expect(eventoQueRoda(eventosDoSuperadmin(), 'superadmin:comandoQueNaoExiste'))->toBeNull();
});
