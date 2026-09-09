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

/*
|--------------------------------------------------------------------------
| Canal já CAÍDO sai da vigilância (2026-09-08)
|--------------------------------------------------------------------------
| "Canal ativo está fora" tem dono: whatsapp:channel-health-snapshot (ADR 0288),
| que alerta 1×/streak e entrega por 3 sinks. Re-alarmar aqui não acrescentava
| informação e saturava o exit code do jana:health-check (medido em prod: 2 canais
| biz=1 caídos desde julho seguravam exit 1 em TODA execução em horário comercial,
| o que impedia o comando de sinalizar qualquer coisa nova).
|
| O teste do meio é o CONTROLE NEGATIVO: prova que a exclusão não engoliu a classe
| do #2726 — canal que se diz saudável e parou de receber SEGUE alarmando.
*/

test('canal já CAÍDO não é vigiado nem alarma — o alarme de queda é do ADR 0288', function () use ($comercial) {
    foreach (HealthCheckCommand::SAUDE_CANAL_CAIDA as $saudeCaida) {
        $r = HealthCheckCommand::evaluateInboundFlow([
            ['label' => 'Suporte', 'business_id' => 1, 'channel_health' => $saudeCaida,
                'last_inbound' => '2026-05-01 10:00:00'], // ~45 dias mudo
        ], $comercial(), 6);

        expect($r['ok'])->toBeTrue("health={$saudeCaida} deveria sair da vigilância");
        expect($r['mudos'])->toBe([]);
        expect($r['vigiados'])->toBe(0);
        expect($r['ja_caidos'])->toBe(1);
    }
});

test('CONTROLE NEGATIVO — canal SAUDÁVEL e mudo segue alarmando mesmo com um caído ao lado', function () use ($comercial) {
    $r = HealthCheckCommand::evaluateInboundFlow([
        ['label' => 'Caido', 'business_id' => 1, 'channel_health' => 'disconnected',
            'last_inbound' => '2026-05-01 10:00:00'],
        ['label' => 'Saudavel', 'business_id' => 1, 'channel_health' => 'healthy',
            'last_inbound' => '2026-06-15 20:00:00'], // ~14h mudo — classe do #2726
    ], $comercial(), 6);

    expect($r['ok'])->toBeFalse('a exclusão do caído não pode silenciar a classe #2726');
    expect($r['mudos'])->toHaveCount(1);
    expect($r['mudos'][0])->toContain('Saudavel');
    expect($r['vigiados'])->toBe(1);
    expect($r['ja_caidos'])->toBe(1);
});

test('channel_health ausente ou null → VIGIA (dado faltando nunca pode virar silêncio)', function () use ($comercial) {
    foreach ([['label' => 'SemChave', 'business_id' => 1, 'last_inbound' => '2026-06-15 20:00:00'],
        ['label' => 'ChaveNull', 'business_id' => 1, 'channel_health' => null, 'last_inbound' => '2026-06-15 20:00:00']] as $canal) {
        $r = HealthCheckCommand::evaluateInboundFlow([$canal], $comercial(), 6);

        expect($r['ok'])->toBeFalse("{$canal['label']}: ausência de saúde deve vigiar, não isentar");
        expect($r['vigiados'])->toBe(1);
        expect($r['ja_caidos'])->toBe(0);
    }
});

test('sem canal vigiável, o check NÃO afirma que o recebimento está bom', function () {
    Artisan::call('jana:health-check', ['--json' => true]);
    $out = Artisan::output();
    $start = strpos($out, '{');
    $json = $start === false ? [] : json_decode(substr($out, (int) $start), true);
    $check = collect($json['checks'] ?? [])->firstWhere('name', 'whatsapp_inbound_flow');

    expect($check)->not->toBeNull();
    // Quando nada é vigiado, a mensagem tem que dizer isso — nunca "todos receberam".
    if (($check['value'] ?? null) === '0 canais vigiáveis') {
        expect($check['message'])->toContain('não está afirmando');
        expect($check['message'])->not->toContain('Todos os');
    }
})->group('smoke');

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
