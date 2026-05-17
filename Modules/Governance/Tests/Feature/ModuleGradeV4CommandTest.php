<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Governance\Console\Commands\ModuleGradeV4Command;

/**
 * Tests pra `php artisan module:grade-v4` (Wave 21 + polish Wave 27).
 *
 * Cobre flags expandidas Wave 27:
 *   - --all (todos módulos com scorecard)
 *   - --bucket=X (filtra por bucket)
 *   - --summary (agrega por bucket)
 *   - --meta-only (só abaixo da meta)
 *   - --json (machine-readable)
 *   - --export-baseline (salva JSON em memory/governance/baselines/)
 *
 * NOTA: --verbose é Symfony-reserved. Comando usa --detail. Ver
 * .claude/rules/commands.md §"--detail NUNCA --verbose" (lição PR #851).
 *
 * @see Modules/Governance/Console/Commands/ModuleGradeV4Command.php
 * @see Modules/Governance/Services/ScopedScorecardEvaluator.php
 * @see memory/governance/scorecards/*.yaml
 */
uses(Tests\TestCase::class);

beforeEach(function () {
    if (! class_exists('Modules\\Governance\\Services\\ScopedScorecardEvaluator')) {
        $this->markTestSkipped('ScopedScorecardEvaluator não disponível.');
    }
});

it('exige module OU --all OU --bucket', function () {
    $this->artisan('module:grade-v4')
        ->expectsOutputToContain('Forneça {module} OU --all OU --bucket=<nome>')
        ->assertExitCode(2);
});

it('NÃO usa flag --verbose (Symfony reserved — usa --detail)', function () {
    $command = app(ModuleGradeV4Command::class);
    $definition = $command->getDefinition();

    expect($definition->hasOption('detail'))->toBeTrue();
    expect($definition->hasOption('json'))->toBeTrue();
    expect($definition->hasOption('all'))->toBeTrue();
    expect($definition->hasOption('bucket'))->toBeTrue();
    expect($definition->hasOption('meta-only'))->toBeTrue();
    expect($definition->hasOption('summary'))->toBeTrue();
    expect($definition->hasOption('export-baseline'))->toBeTrue();

    // --verbose deve ser apenas o default Symfony, não custom
    $verboseOption = $definition->hasOption('verbose') ? $definition->getOption('verbose') : null;
    if ($verboseOption) {
        expect($verboseOption->getShortcut())->toBe('v');
    }
});

it('--all retorna sumário com média de módulos', function () {
    $this->artisan('module:grade-v4 --all')
        ->expectsOutputToContain('Média:')
        ->assertExitCode(0);
});

it('--all --json retorna JSON parseável como array', function () {
    $exit = \Artisan::call('module:grade-v4', ['--all' => true, '--json' => true]);
    expect($exit)->toBe(0);

    $output = \Artisan::output();
    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray()->not->toBeEmpty();

    // Cada item tem estrutura mínima esperada
    $first = $decoded[0];
    expect($first)->toHaveKeys(['module', 'bucket', 'score_total']);
});

it('--bucket=cross_cutting_infra filtra módulos do bucket (admin/auditoria/governance)', function () {
    $exit = \Artisan::call('module:grade-v4', [
        '--bucket' => 'cross_cutting_infra',
        '--json'   => true,
    ]);
    expect($exit)->toBe(0);

    $decoded = json_decode(\Artisan::output(), true);
    expect($decoded)->toBeArray()->not->toBeEmpty();

    // Todos os retornados são bucket cross_cutting_infra
    foreach ($decoded as $r) {
        expect($r['bucket'])->toBe('cross_cutting_infra');
    }
});

it('--bucket=inexistente_xyz avisa e retorna vazio', function () {
    $this->artisan('module:grade-v4 --bucket=inexistente_xyz')
        ->expectsOutputToContain('inválido')
        ->assertExitCode(0);
});

it('--all --summary agrega por bucket com contadores', function () {
    $this->artisan('module:grade-v4 --all --summary')
        ->expectsOutputToContain('Sumário agregado por bucket')
        ->expectsOutputToContain('Média projeto:')
        ->assertExitCode(0);
});

it('--all --meta-only não erra mesmo quando todos passam (ou só lista subset)', function () {
    // Comando deve sair zero independente do conteúdo. Se nada abaixo da meta,
    // imprime "Nenhum módulo abaixo da meta. ✓" OU tabela vazia.
    $exit = \Artisan::call('module:grade-v4', [
        '--all' => true,
        '--meta-only' => true,
    ]);
    expect($exit)->toBe(0);
});

it('--all --export-baseline salva JSON em memory/governance/baselines/', function () {
    $relPath = 'memory/governance/baselines/module-grade-v4-baseline.json';
    $absPath = base_path($relPath);

    // Cleanup se existir do test anterior
    if (File::exists($absPath)) {
        File::delete($absPath);
    }

    $exit = \Artisan::call('module:grade-v4', [
        '--all' => true,
        '--export-baseline' => true,
    ]);
    expect($exit)->toBe(0);

    expect(File::exists($absPath))->toBeTrue();
    $payload = json_decode(File::get($absPath), true);
    expect($payload)->toBeArray();
    expect($payload)->toHaveKeys(['generated_at', 'rubrica', 'wave', 'count', 'modules']);
    expect($payload['rubrica'])->toBe('module-grade-v4');
    expect($payload['wave'])->toBe(27);
    expect($payload['modules'])->toBeArray();

    // Cleanup
    File::delete($absPath);
});

it('módulo single Vestuario retorna sucesso quando scorecard existe', function () {
    $scorecardPath = base_path('memory/governance/scorecards/vestuario.yaml');
    if (! file_exists($scorecardPath)) {
        $this->markTestSkipped('Scorecard vestuario.yaml ausente neste worktree.');
    }
    $this->artisan('module:grade-v4 Vestuario')
        ->expectsOutputToContain('Modules/Vestuario')
        ->assertExitCode(0);
});

it('módulo single inexistente retorna FAILURE com mensagem', function () {
    $this->artisan('module:grade-v4 ModuloXyzNaoExisteW27')
        ->expectsOutputToContain('scorecard não encontrado')
        ->assertExitCode(1);
});

it('discoverModules + scorecards reais → resultado não-vazio em --all', function () {
    $exit = \Artisan::call('module:grade-v4', [
        '--all' => true,
        '--json' => true,
    ]);
    expect($exit)->toBe(0);

    $decoded = json_decode(\Artisan::output(), true);
    // Modules/ tem 34+ pastas; pelo menos os 4 com scorecard YAML
    // (vestuario, jana, governance, functional_horizontal) devem aparecer.
    expect(count($decoded))->toBeGreaterThanOrEqual(1);
});
