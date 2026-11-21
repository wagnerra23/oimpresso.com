<?php

declare(strict_types=1);

/*
 * Worker da fila `rb_webhooks` na agenda (auditoria RecurringBilling 2026-11-19, achado a2).
 *
 * Os webhooks Asaas e Inter despacham para `rb_webhooks` (AsaasWebhookIdempotencyTest prova o
 * `assertPushedOn`). Até este lote nenhum queue:work do Kernel lia essa fila, e o pagamento
 * ficava parado na tabela `jobs`.
 *
 * O ambiente é conferido pelo `runsInEnvironment`, a função que o `schedule:run` chama
 * (proibicoes §5 2026-09-05: o `schedule:list` não responde essa pergunta).
 */

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

uses(Tests\TestCase::class);

function rbWorkerDaAgenda(): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $e) => str_contains((string) $e->command, 'queue:work')
            && str_contains((string) $e->command, '--queue=rb_webhooks'));
}

it('a agenda tem um queue:work que lê a fila rb_webhooks, com 3 tentativas', function () {
    $evento = rbWorkerDaAgenda();

    expect($evento)->not->toBeNull('nenhum queue:work lê rb_webhooks')
        ->and((string) $evento->command)->toContain('--tries=3')
        ->and((string) $evento->command)->toContain('database')
        ->and($evento->withoutOverlapping)->toBeTrue();
});

it('o worker de rb_webhooks só roda em produção', function () {
    $evento = rbWorkerDaAgenda();

    expect($evento)->not->toBeNull()
        ->and($evento->runsInEnvironment('live'))->toBeTrue()
        ->and($evento->runsInEnvironment('local'))->toBeFalse()
        ->and($evento->runsInEnvironment('testing'))->toBeFalse();
});

it('controle: o worker do whatsapp continua na agenda (a busca enxerga queue:work)', function () {
    $outro = collect(app(Schedule::class)->events())
        ->first(fn (Event $e) => str_contains((string) $e->command, '--queue=whatsapp '));

    expect($outro)->not->toBeNull();
});
