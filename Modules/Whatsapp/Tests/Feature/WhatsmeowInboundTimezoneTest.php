<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

uses(Tests\TestCase::class);

/**
 * US-WA-308 — timezone da mensagem inbound (fix do "+3h" reportado pela cliente).
 *
 * `ProcessIncomingWebhookJob` é `ShouldQueue` → roda no queue worker, que NÃO
 * passa pelo middleware HTTP `App\Http\Middleware\Timezone`. Logo `now()` cairia
 * no default `Europe/London` (UTC+0) e gravaria a msg +3h adiantada vs
 * `America/Sao_Paulo` (UTC−3) — "pulando de dia" perto da meia-noite.
 *
 * O fix usa a hora REAL do evento (`Info.Timestamp` do whatsmeow) convertida pro
 * fuso do business. Estes testes SIMULAM o worker em London (beforeEach) e provam
 * que a msg fica no horário/dia certos.
 *
 * Unit puro (reflection nos métodos privados) — sem DB.
 *
 * Ref: auditoria timezone `now()` em contextos não-HTTP (2026-05-31).
 */

beforeEach(function () {
    // Simula o queue worker: app no fuso DEFAULT (London), não no do business.
    config(['app.timezone' => 'Europe/London']);
    date_default_timezone_set('Europe/London');
});

afterEach(fn () => Carbon::setTestNow());

function waTzInvoke(string $method, array $args): mixed
{
    $job = new ProcessIncomingWebhookJob(4, 'whatsmeow', []);
    $m = new ReflectionMethod($job, $method);
    $m->setAccessible(true);

    return $m->invoke($job, ...$args);
}

it('resolveSentAt: RFC3339 das 22h em SP NÃO pula pro dia seguinte', function () {
    // 22h SP = 01h do dia 29 em London. O bug gravava 2026-05-29.
    $r = waTzInvoke('resolveSentAt', ['2026-05-28T22:00:00-03:00', 'America/Sao_Paulo']);

    expect($r->format('Y-m-d H:i'))->toBe('2026-05-28 22:00');
    expect($r->toDateString())->toBe('2026-05-28');
});

it('resolveSentAt: epoch (UTC) converte pro fuso do business', function () {
    $epoch = Carbon::parse('2026-05-28T22:00:00-03:00')->timestamp; // 2026-05-29 01:00 UTC

    $r = waTzInvoke('resolveSentAt', [$epoch, 'America/Sao_Paulo']);

    expect($r->format('Y-m-d H:i'))->toBe('2026-05-28 22:00');
});

it('resolveSentAt: business em outro fuso (Manaus, UTC−4) respeita o offset', function () {
    $r = waTzInvoke('resolveSentAt', ['2026-05-28T23:30:00-03:00', 'America/Manaus']);

    // 23:30 SP (−03) = 22:30 Manaus (−04)
    expect($r->format('Y-m-d H:i'))->toBe('2026-05-28 22:30');
});

it('resolveSentAt: sem timestamp cai no fallback now() (não regride)', function () {
    Carbon::setTestNow('2026-05-29 01:00:00');

    $r = waTzInvoke('resolveSentAt', [null, 'America/Sao_Paulo']);

    expect($r->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'));
});

it('extractFromWhatsmeow: expõe sent_at a partir de Info.Timestamp', function () {
    $payload = [
        'event' => [
            'Info' => [
                'Chat' => '5511999999999@s.whatsapp.net',
                'Sender' => '5511999999999@s.whatsapp.net',
                'IsFromMe' => false,
                'ID' => 'WAMID.TZ.001',
                'Type' => 'text',
                'PushName' => 'Cliente',
                'Timestamp' => '2026-05-28T22:00:00-03:00',
            ],
            'Message' => ['conversation' => 'oi'],
        ],
    ];

    $out = waTzInvoke('extractFromWhatsmeow', [$payload]);

    expect($out[0]['sent_at'])->toBe('2026-05-28T22:00:00-03:00');
});
