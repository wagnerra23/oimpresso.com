<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(Tests\TestCase::class);

/**
 * Auto-teste do alarme do canário do webhook (camada 5 · Fase 1 perda-zero,
 * incidente 2026-06-16 #2726).
 *
 * A lógica vive em evaluateCanaryFreshness (estática, sem cache nem relógio real
 * — mesmo padrão de evaluateInboundFlow). O último teste prova que o check está
 * registrado no jana:health-check e é HARD (derruba o exit code).
 *
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php::checkWhatsappInboundCanary
 * @see Modules/Whatsapp/Console/Commands/WebhookCanaryCommand.php
 */
$comercial = fn (): Carbon => Carbon::parse('2026-06-16 10:00:00', 'America/Sao_Paulo'); // terça 10h BRT
$tick = fn (Carbon $now, int $agoMin, bool $ok): array => [
    'ok' => $ok,
    'status' => $ok ? 200 : 401,
    'at' => $now->copy()->subMinutes($agoMin)->toIso8601String(),
];

test('não-configurado (dev/CI sem segredo) não acende', function () use ($comercial) {
    $r = HealthCheckCommand::evaluateCanaryFreshness(false, null, $comercial(), 15);
    expect($r['ok'])->toBeTrue();
    expect($r['state'])->toBe('nao-configurado');
});

test('tick fresco e verde = ok (caminho provado)', function () use ($comercial, $tick) {
    $r = HealthCheckCommand::evaluateCanaryFreshness(true, $tick($comercial(), 5, true), $comercial(), 15);
    expect($r['ok'])->toBeTrue();
    expect($r['state'])->toBe('fresco');
    expect($r['age_min'])->toBe(5);
});

test('tick fresco mas FALHOU = alerta (webhook quebrado agora)', function () use ($comercial, $tick) {
    $r = HealthCheckCommand::evaluateCanaryFreshness(true, $tick($comercial(), 3, false), $comercial(), 15);
    expect($r['ok'])->toBeFalse();
    expect($r['state'])->toBe('falha');
});

test('tick velho (stale) = alerta (cron do canário morreu — o monitor apodreceu)', function () use ($comercial, $tick) {
    $r = HealthCheckCommand::evaluateCanaryFreshness(true, $tick($comercial(), 40, true), $comercial(), 15);
    expect($r['ok'])->toBeFalse();
    expect($r['state'])->toBe('stale');
    expect($r['age_min'])->toBe(40);
});

test('cold-start (nunca reportou) = ok com grace pós-deploy', function () use ($comercial) {
    $r = HealthCheckCommand::evaluateCanaryFreshness(true, null, $comercial(), 15);
    expect($r['ok'])->toBeTrue();
    expect($r['state'])->toBe('cold');
});

test('fora do horário comercial BRT não acende (canário em pausa à noite)', function () use ($tick) {
    $noite = Carbon::parse('2026-06-16 23:00:00', 'America/Sao_Paulo');
    $r = HealthCheckCommand::evaluateCanaryFreshness(true, $tick($noite, 120, true), $noite, 15);
    expect($r['ok'])->toBeTrue();
    expect($r['state'])->toBe('fora-horario');
});

test('whatsapp_inbound_canary está registrado no jana:health-check e é hard (não-advisory)', function () {
    Cache::flush(); // garante cold-start determinístico (ok=true) — não falha o exit por este check
    Artisan::call('jana:health-check', ['--json' => true]);
    $out = Artisan::output();
    $start = strpos($out, '{');
    $json = $start === false ? [] : json_decode(substr($out, (int) $start), true);

    $check = collect($json['checks'] ?? [])->firstWhere('name', 'whatsapp_inbound_canary');
    expect($check)->not->toBeNull('check whatsapp_inbound_canary ausente do jana:health-check');
    expect($check['advisory'] ?? false)->toBeFalse(); // hard check — derruba exit + ALERT
    expect($check)->toHaveKeys(['name', 'ok', 'value', 'threshold', 'message']);
});
