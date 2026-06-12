<?php

declare(strict_types=1);

/**
 * JanaRecallEvalCommandTest — frente KL-C2 (plano SDD 2026-06-12).
 *
 * Modo mock = 100% determinístico, zero LLM, zero Meilisearch, zero DB write.
 * Valida o golden set REAL commitado (tests/eval/recall-golden.yaml) + fixtures
 * boa/ruim (filosofia gate-selftest GT-G6: provar que o gate MORDE).
 *
 * @group recall-eval
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function recallEvalReport(array $params = []): array
{
    Artisan::call('jana:recall-eval', $params + ['--json' => true]);

    return json_decode(trim(Artisan::output()), true);
}

it('mock mode passa contra o golden set canon commitado', function () {
    $exit = Artisan::call('jana:recall-eval', ['--json' => true, '--mode' => 'mock']);
    $report = json_decode(trim(Artisan::output()), true);

    expect($exit)->toBe(0)
        ->and($report['gate_status'])->toBe('pass')
        ->and($report['mode'])->toBe('mock')
        ->and($report['errors'])->toBe([])
        ->and($report['n_queries'])->toBeGreaterThanOrEqual(25)
        ->and($report['n_queries'])->toBeLessThanOrEqual(30)
        ->and($report['n_collision_queries'])->toBeGreaterThanOrEqual(2)
        ->and($report['n_queries_with_violations'])->toBeGreaterThanOrEqual(1)
        ->and($report['checks']['yaml_parse'])->toBeTrue()
        ->and($report['checks']['estrutura_ok'])->toBeTrue();
})->group('recall-eval');

it('JSON output tem o schema canon consumível por workflow', function () {
    $report = recallEvalReport(['--mode' => 'mock']);

    expect($report)->toHaveKeys([
        'command', 'mode', 'golden_path', 'ran_at', 'n_queries',
        'n_collision_queries', 'n_queries_with_violations', 'checks', 'errors', 'gate_status',
    ])->and($report['command'])->toBe('jana:recall-eval');
})->group('recall-eval');

it('gate MORDE: slug fantasma no disco gera fail com erro nomeado', function () {
    $bad = <<<'YAML'
    meta: { version: "1", top_k: 5, violation_window: 3 }
    queries:
      - id: fantasma
        query: "query com slug que não existe no disco"
        expected: ["9999-slug-fantasma-que-nao-existe"]
        violations: []
    YAML;
    $path = tempnam(sys_get_temp_dir(), 'recall') . '.yaml';
    file_put_contents($path, $bad);

    $exit = Artisan::call('jana:recall-eval', ['--json' => true, '--mode' => 'mock', '--golden' => $path]);
    $report = json_decode(trim(Artisan::output()), true);
    unlink($path);

    expect($exit)->toBe(1)
        ->and($report['gate_status'])->toBe('fail')
        ->and(implode(' | ', $report['errors']))
        ->toContain('9999-slug-fantasma-que-nao-existe')
        ->toContain('25-30')          // contagem fora da faixa
        ->toContain('pares colididos'); // <2 queries de colisão
})->group('recall-eval');

it('gate MORDE: violation que NÃO é superseded é rejeitada', function () {
    $golden = ['meta' => ['version' => '1', 'top_k' => 5, 'violation_window' => 3], 'queries' => []];
    for ($i = 1; $i <= 25; $i++) {
        $golden['queries'][] = [
            'id' => "q{$i}",
            'query' => "pergunta {$i}",
            'collision_number' => '0178',
            'must_resolve_slug' => '0178-restauracao-campos-fiscais-br-canon',
            'expected' => ['0178-restauracao-campos-fiscais-br-canon'],
            // ADR 0093 é ATIVA — usar como violation deve falhar (violation = só superseded/historical)
            'violations' => $i === 1 ? ['0093-multi-tenant-isolation-tier-0'] : [],
        ];
    }
    $path = tempnam(sys_get_temp_dir(), 'recall') . '.yaml';
    file_put_contents($path, Symfony\Component\Yaml\Yaml::dump($golden, 4));

    $exit = Artisan::call('jana:recall-eval', ['--json' => true, '--mode' => 'mock', '--golden' => $path]);
    $report = json_decode(trim(Artisan::output()), true);
    unlink($path);

    expect($exit)->toBe(1)
        ->and(implode(' | ', $report['errors']))->toContain('não tem status/lifecycle superseded');
})->group('recall-eval');

it('modo real sem Meilisearch falha estruturado apontando CT 100 (fase 2)', function () {
    Http::fake(fn () => Http::response(['message' => 'index not found'], 404));

    $exit = Artisan::call('jana:recall-eval', ['--json' => true, '--mode' => 'real']);
    $report = json_decode(trim(Artisan::output()), true);

    expect($exit)->toBe(1)
        ->and($report['gate_status'])->toBe('fail')
        ->and(implode(' | ', $report['errors']))->toContain('CT 100');
})->group('recall-eval');
