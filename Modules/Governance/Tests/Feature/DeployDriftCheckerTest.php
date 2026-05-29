<?php

declare(strict_types=1);

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\Checkers\DeployDriftChecker;
use Modules\Governance\Services\DriftFinding;

uses(Tests\TestCase::class);

/**
 * DeployDriftChecker (ADR 0216) — detecta código deployado != GitHub main.
 * Fecha o "1302-commits cego". Plugado no framework (não comando bespoke).
 * Métodos puros (analisar/mesmoSha/deployedSha) testáveis sem rede/git binary.
 */

it('implementa DriftChecker + registrado em governance.drift_checkers', function () {
    expect(new DeployDriftChecker())->toBeInstanceOf(DriftChecker::class)
        ->and((new DeployDriftChecker())->name())->toBe('deploy_drift')
        ->and((array) config('governance.drift_checkers'))->toContain(DeployDriftChecker::class);
});

it('analisar: deployado == main → sem finding', function () {
    expect((new DeployDriftChecker())->analisar('abc1234def', 'abc1234def'))->toBeEmpty();
});

it('analisar: deployado != main → finding high (deploy atrasado)', function () {
    $f = (new DeployDriftChecker())->analisar('aaaaaaa', 'bbbbbbb');
    expect($f)->toHaveCount(1)
        ->and($f[0])->toBeInstanceOf(DriftFinding::class)
        ->and($f[0]->severity)->toBe('high')
        ->and($f[0]->message)->toContain('deployado');
});

it('analisar: deployado null → finding low (não verificável)', function () {
    $f = (new DeployDriftChecker())->analisar(null, 'abc1234');
    expect($f)->toHaveCount(1)->and($f[0]->severity)->toBe('low');
});

it('analisar: main null → info (reverifica no próximo push)', function () {
    $f = (new DeployDriftChecker())->analisar('abc1234', null);
    expect($f)->toHaveCount(1)->and($f[0]->severity)->toBe('info');
});

it('mesmoSha: short vs full do mesmo commit → iguais', function () {
    expect((new DeployDriftChecker())->mesmoSha('744e864', '744e86439edcabc123'))->toBeTrue()
        ->and((new DeployDriftChecker())->mesmoSha('744e864', '999e864abc'))->toBeFalse();
});

it('deployedSha: lê .git/HEAD → ref → refs/heads/main (sem git binary)', function () {
    $base = sys_get_temp_dir().'/deploydrift_'.uniqid();
    mkdir($base.'/.git/refs/heads', 0777, true);
    file_put_contents($base.'/.git/HEAD', "ref: refs/heads/main\n");
    file_put_contents($base.'/.git/refs/heads/main', "744e86439edc0000000000000000000000000000\n");

    expect((new DeployDriftChecker())->deployedSha($base))->toBe('744e86439edc0000000000000000000000000000');

    // cleanup
    @unlink($base.'/.git/refs/heads/main');
    @unlink($base.'/.git/HEAD');
});

it('deployedSha: HEAD destacado (SHA direto) também resolve', function () {
    $base = sys_get_temp_dir().'/deploydrift2_'.uniqid();
    mkdir($base.'/.git', 0777, true);
    file_put_contents($base.'/.git/HEAD', "abc1234def5678\n");

    expect((new DeployDriftChecker())->deployedSha($base))->toBe('abc1234def5678');
    @unlink($base.'/.git/HEAD');
});
