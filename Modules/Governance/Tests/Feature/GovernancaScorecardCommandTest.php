<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\HealthCheckCommand;
use Tests\TestCase;

uses(TestCase::class);

/**
 * W28 — `governanca:scorecard` (camada 3) + generalização do parseLessonLedger.
 *
 * Cobre: parser rodando no header do ledger [CC] (## L-NN), cálculo de
 * graduation_ratio nos dois formatos, e o comando que escreve o JSON agregado.
 *
 * @see Modules/Governance/Console/Commands/GovernancaScorecardCommand.php
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php
 */

it('parseLessonLedger generaliza pro header do ledger [CC] (## L-NN)', function () {
    $content = implode("\n", [
        '# LIÇÕES [CC]',
        '## L-01 — graduada',
        '- **Graduação:** MEC · check:`foo` · status:done',
        '## L-02 — sem graduação (design comum)',
        'texto qualquer, sem linha de graduação',
        '## L-03 — pendente',
        '- **Graduação:** JULG · regra:`bar` · status:pendente',
    ]);

    $r = HealthCheckCommand::parseLessonLedger($content, '/^##\s+(L-\d+)\b/m');

    expect($r['total'])->toBe(3);
    expect($r['malformed'])->toContain('L-02');  // sem graduação → não-graduada
    expect($r['overdue'])->toContain('L-03');    // status:pendente
});

it('default do parseLessonLedger continua sendo o ledger de operação (### L-OP-NNN)', function () {
    $content = implode("\n", [
        '### L-OP-001 · x',
        '- **Graduação:** MEC · check:`abc` · status:done',
        '## L-99 — não deve ser pego pelo header default',
    ]);

    $r = HealthCheckCommand::parseLessonLedger($content); // sem 2º arg = backward-compat

    expect($r['total'])->toBe(1);
    expect($r['malformed'])->toBe([]);
    expect($r['overdue'])->toBe([]);
});

it('ledgerGraduationStats calcula graduadas/pendentes/ratio', function () {
    $path = tempnam(sys_get_temp_dir(), 'ledger-') . '.md';
    file_put_contents($path, implode("\n", [
        '## L-01 — a', '- **Graduação:** MEC · check:`x` · status:done',
        '## L-02 — b', '(sem graduação)',
        '## L-03 — c', '- **Graduação:** JULG · regra:`y` · status:done',
        '## L-04 — d', '- **Graduação:** MEC · check:`z` · status:pendente',
    ]));

    $s = HealthCheckCommand::ledgerGraduationStats($path, '/^##\s+(L-\d+)\b/m');

    expect($s)->not->toBeNull();
    expect($s['total'])->toBe(4);
    expect($s['graduadas'])->toBe(2);            // L-01 + L-03
    expect($s['pendentes'])->toBe(2);            // L-02 (sem grad) + L-04 (pendente)
    expect($s['graduation_ratio'])->toBe(0.5);

    unlink($path);
});

it('ledgerGraduationStats retorna null se o arquivo não existe', function () {
    expect(HealthCheckCommand::ledgerGraduationStats('/no/such/ledger.md', '/^##\s+(L-\d+)\b/m'))->toBeNull();
});

it('ledger vazio = ratio 1.0 (vacuosamente fechado)', function () {
    $path = tempnam(sys_get_temp_dir(), 'ledger-empty-') . '.md';
    file_put_contents($path, "# sem lições\n");
    $s = HealthCheckCommand::ledgerGraduationStats($path, '/^##\s+(L-\d+)\b/m');
    expect($s['total'])->toBe(0);
    expect($s['graduation_ratio'])->toBe(1.0);
    unlink($path);
});

it('governanca:scorecard --json escreve o JSON agregado', function () {
    $path = storage_path('reports/governanca-scorecard.json');
    if (file_exists($path)) {
        unlink($path);
    }

    $this->artisan('governanca:scorecard', ['--json' => true])->assertExitCode(0);

    expect(file_exists($path))->toBeTrue();

    $json = json_decode((string) file_get_contents($path), true);
    expect($json)->toBeArray();
    expect($json['meta']['generator'])->toBe('governanca:scorecard');
    expect($json['ledgers'])->toHaveKeys(['operacao', 'cc']);
    expect($json['mecanizado']['enforcement_score'])
        ->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
    expect($json['mecanizado']['health_checks_count'])->toBeGreaterThan(0);
    expect($json['condicao_9_7'])->toHaveKeys(['ambos_ledgers_100', 'pipe_unico_cc_jana', 'atingido']);
    // honestidade de escopo: eixos subjetivos marcados como estimativa
    expect($json['eixos_subjetivos'][0]['source'])->toBe('estimativa [CC]');
});

it('condicao_9_7.atingido só é true com ambos 100% E pipe único', function () {
    // sem --pipe-unico, atingido é necessariamente false (mesmo se ambos 100%)
    $this->artisan('governanca:scorecard', ['--json' => true])->assertExitCode(0);
    $json = json_decode((string) file_get_contents(storage_path('reports/governanca-scorecard.json')), true);
    if (! $json['condicao_9_7']['pipe_unico_cc_jana']) {
        expect($json['condicao_9_7']['atingido'])->toBeFalse();
    }
});
