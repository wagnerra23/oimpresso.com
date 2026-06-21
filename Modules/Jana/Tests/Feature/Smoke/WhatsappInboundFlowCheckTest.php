<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(Tests\TestCase::class);

/**
 * Sentinela de fluxo de inbound WhatsApp (incidente 2026-06-16 #2726).
 *
 * A lógica vive no método estático evaluateInboundFlow (mesmo padrão de
 * parseLessonLedger): testável sem DB nem relógio real. O último teste prova que
 * o check está registrado no jana:health-check e é HARD (derruba o exit code).
 *
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php::checkWhatsappInboundFlow
 */
$comercial = fn (): Carbon => Carbon::parse('2026-06-16 10:00:00', 'America/Sao_Paulo'); // terça 10h BRT

test('acende quando canal ativo com histórico fica mudo além do threshold em horário comercial', function () use ($comercial) {
    $r = HealthCheckCommand::evaluateInboundFlow([
        ['label' => 'Suporte', 'business_id' => 1, 'last_inbound' => '2026-06-16 09:30:00'], // 30min — fresco
        ['label' => 'Jana', 'business_id' => 1, 'last_inbound' => '2026-06-15 20:00:00'],    // ~14h — mudo
    ], $comercial(), 6);

    expect($r['ok'])->toBeFalse();
    expect($r['mudos'])->toHaveCount(1);
    expect($r['mudos'][0])->toContain('Jana');
    expect($r['vigiados'])->toBe(2);
});

test('ignora canal sem histórico de inbound (sem baseline pra vigiar)', function () use ($comercial) {
    $r = HealthCheckCommand::evaluateInboundFlow([
        ['label' => 'NovoCanal', 'business_id' => 1, 'last_inbound' => null],
    ], $comercial(), 6);

    expect($r['ok'])->toBeTrue();
    expect($r['vigiados'])->toBe(0);
});

test('não alarma fora do horário comercial BRT (canal quieto à noite é normal)', function () {
    $noite = Carbon::parse('2026-06-16 23:00:00', 'America/Sao_Paulo');
    $r = HealthCheckCommand::evaluateInboundFlow([
        ['label' => 'Suporte', 'business_id' => 1, 'last_inbound' => '2026-06-14 10:00:00'], // 2+ dias mudo
    ], $noite, 6);

    expect($r['ok'])->toBeTrue();
    expect($r['fora_horario'])->toBeTrue();
});

test('ok quando todos os canais ativos receberam dentro do threshold', function () use ($comercial) {
    $r = HealthCheckCommand::evaluateInboundFlow([
        ['label' => 'Suporte', 'business_id' => 1, 'last_inbound' => '2026-06-16 08:00:00'], // 2h
    ], $comercial(), 6);

    expect($r['ok'])->toBeTrue();
    expect($r['mudos'])->toBe([]);
});

test('whatsapp_inbound_flow está registrado no jana:health-check e é hard (não-advisory)', function () {
    Artisan::call('jana:health-check', ['--json' => true]);
    $out = Artisan::output();
    $start = strpos($out, '{');
    $json = $start === false ? [] : json_decode(substr($out, (int) $start), true);

    $check = collect($json['checks'] ?? [])->firstWhere('name', 'whatsapp_inbound_flow');
    expect($check)->not->toBeNull('check whatsapp_inbound_flow ausente do jana:health-check');
    expect($check['advisory'] ?? false)->toBeFalse(); // hard check — derruba exit + ALERT
    expect($check)->toHaveKeys(['name', 'ok', 'value', 'threshold', 'message']);
});
