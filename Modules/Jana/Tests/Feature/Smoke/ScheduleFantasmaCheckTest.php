<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(Tests\TestCase::class);

/**
 * Sentinela de comando agendado FANTASMA (agendado e inexistente) e DUPLICADO.
 *
 * A lógica vive no estático puro `evaluateSchedule` — mesmo pattern do
 * `evaluateInboundFlow`: testável sem app, sem Schedule e sem DB.
 *
 * Origem (2026-09-09): `pos:sendSubscriptionExpiryAlert` estava agendado e não existia
 * — 410 CommandNotFoundException no log de prod e o alerta de expiração de assinatura
 * nunca enviado — e 8 pares (comando+args, cron) estavam duplicados. Nada viu.
 *
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php::checkScheduleFantasma
 */
$agendar = fn (string $cmd, string $cron = '0 0 * * *'): array => [
    'command' => "'/usr/bin/php' 'artisan' {$cmd}",
    'expression' => $cron,
];

test('acusa comando agendado que não existe no Artisan', function () use ($agendar) {
    $r = HealthCheckCommand::evaluateSchedule(
        [$agendar('pos:comandoFantasma'), $agendar('jana:health-check', '0 6 * * *')],
        ['jana:health-check']
    );

    expect($r['fantasmas'])->toBe(['pos:comandoFantasma']);
    expect($r['duplicados'])->toBe([]);
    expect($r['medidos'])->toBe(2);
});

test('acusa o MESMO comando+args agendado duas vezes no mesmo cron', function () use ($agendar) {
    $r = HealthCheckCommand::evaluateSchedule(
        [$agendar('pos:sync 41'), $agendar('pos:sync 41')],
        ['pos:sync']
    );

    expect($r['duplicados'])->toHaveCount(1);
    expect($r['duplicados'][0])->toContain('pos:sync 41');
    expect($r['fantasmas'])->toBe([]);
});

/*
| CONTROLE NEGATIVO — este é o falso-positivo que a medição em prod pegou ANTES de
| armar o check. Com o nome do comando como chave, as 5 filas de `queue:work` colidiam
| e viravam "5x duplicado". A chave tem que incluir os argumentos.
*/
test('CONTROLE NEGATIVO — mesmo comando com ARGS diferentes não é duplicado', function () use ($agendar) {
    $r = HealthCheckCommand::evaluateSchedule(
        [
            $agendar('queue:work database --queue=whatsapp', '* * * * *'),
            $agendar('queue:work database --queue=backups', '* * * * *'),
            $agendar('queue:work database --queue=nfe', '* * * * *'),
        ],
        ['queue:work']
    );

    expect($r['duplicados'])->toBe([]);
    expect($r['medidos'])->toBe(3);
});

test('CONTROLE NEGATIVO — mesmo comando em CRONS diferentes não é duplicado', function () use ($agendar) {
    $r = HealthCheckCommand::evaluateSchedule(
        [$agendar('jana:health-check', '0 6 * * *'), $agendar('jana:health-check', '7 * * * *')],
        ['jana:health-check']
    );

    expect($r['duplicados'])->toBe([]);
});

test('Job/closure agendado conta como NÃO MEDIDO, nunca como fantasma', function () {
    $r = HealthCheckCommand::evaluateSchedule(
        [
            ['command' => '', 'expression' => '0 * * * *'],                       // closure
            ['command' => 'Modules\\Whatsapp\\Jobs\\RetryJob', 'expression' => '0 * * * *'], // job
        ],
        []
    );

    expect($r['nao_parseados'])->toBe(2);
    expect($r['fantasmas'])->toBe([]);
    expect($r['medidos'])->toBe(0);
});

test('schedule são não acusa nada', function () use ($agendar) {
    $r = HealthCheckCommand::evaluateSchedule(
        [$agendar('jana:health-check', '0 6 * * *'), $agendar('whatsmeow:health-probe', '*/3 * * * *')],
        ['jana:health-check', 'whatsmeow:health-probe']
    );

    expect($r['fantasmas'])->toBe([]);
    expect($r['duplicados'])->toBe([]);
    expect($r['nao_parseados'])->toBe(0);
    expect($r['medidos'])->toBe(2);
});
