<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(Tests\TestCase::class);

/**
 * Bite-test do rebaixamento de `distiller_freshness` a ADVISORY ([ADR 0380],
 * errata da 0292/0291 D-D).
 *
 * Prova as DUAS pernas, porque só a primeira não distingue conserto de carimbo:
 *   (1) o check de produção emite `advisory => true` — exercitando o método REAL
 *       por reflection, nunca uma cópia da lógica (§5 proibicoes 2026-08-14);
 *   (2) CONTROLE NEGATIVO: o mesmo check SEM o campo derruba o gate. Sem isso o
 *       teste passaria mesmo que `allChecksOk()` ignorasse advisory por engano.
 *
 * Não toca DB nem LLM: `checkDistillerFreshness()` só lê `memory/requisitos/`.
 */
test('checkDistillerFreshness emite advisory em todos os caminhos de retorno', function () {
    $cmd = app(HealthCheckCommand::class);
    $m = new ReflectionMethod($cmd, 'checkDistillerFreshness');
    $m->setAccessible(true);
    $check = $m->invoke($cmd);

    expect($check)->toHaveKey('advisory')
        ->and($check['advisory'])->toBeTrue()
        ->and($check['name'])->toBe('distiller_freshness');
});

test('o gate NAO cai por distiller_freshness stale (e CAI sem o advisory)', function () {
    $stale = [
        'name' => 'distiller_freshness',
        'advisory' => true,
        'ok' => false,
        'value' => 13,
        'threshold' => 0,
        'message' => 'STALE',
    ];
    $duroOk = ['name' => 'multi_tenant_isolation', 'ok' => true, 'value' => 0, 'message' => 'ok'];

    // (1) advisory falho não derruba o gate
    expect(HealthCheckCommand::allChecksOk([$duroOk, $stale]))->toBeTrue();

    // (2) controle negativo: sem o campo, o MESMO check derruba — prova que a
    //     asserção acima mede o advisory, e não uma tolerância geral do gate.
    $semAdvisory = $stale;
    unset($semAdvisory['advisory']);
    expect(HealthCheckCommand::allChecksOk([$duroOk, $semAdvisory]))->toBeFalse();
});

test('advisory nao silencia: o numero segue visivel no retorno do check', function () {
    $cmd = app(HealthCheckCommand::class);
    $m = new ReflectionMethod($cmd, 'checkDistillerFreshness');
    $m->setAccessible(true);
    $check = $m->invoke($cmd);

    // advisory muda o ENFORCEMENT, não a medição: value/message continuam lá.
    expect($check)->toHaveKey('value')->toHaveKey('message');
    expect($check['message'])->not->toBe('');
});
