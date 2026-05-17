<?php

declare(strict_types=1);

/**
 * JanaRagasCiCommandTest — smoke W28-2 RAGAS CI gate BLOQUEANTE Jana.
 *
 * Pattern H4: mock mode default RagasJudgeService → zero LLM cost CI.
 * Cobre:
 *   - gate_status=pass quando scores ≥ thresholds
 *   - gate_status=fail quando scores < thresholds
 *   - JSON output estruturado consumível pelo workflow gh pr comment
 *   - exit code 0 (pass) vs 1 (fail)
 *   - sample-size respeitado
 *
 * @group ragas
 * @group ci
 */

use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Services\Ragas\RagasJudgeService;
use Tests\TestCase;

uses(TestCase::class);

it('gate_status=pass quando mock scores acima dos thresholds', function () {
    // Mock acima dos thresholds default (0.80/0.75)
    $judge = app(RagasJudgeService::class);
    $judge->enableMock([
        'faithfulness' => 0.90,
        'answer_relevancy' => 0.85,
    ]);
    $this->app->instance(RagasJudgeService::class, $judge);

    $exit = Artisan::call('jana:ragas-ci-eval', [
        '--json' => true,
        '--mode' => 'mock',
        '--sample-size' => 5,
    ]);

    expect($exit)->toBe(0);

    $output = Artisan::output();
    $report = json_decode(trim($output), true);

    expect($report)->toBeArray()
        ->and($report['gate_status'])->toBe('pass')
        ->and($report['faithfulness_avg'])->toBeGreaterThanOrEqual(0.80)
        ->and($report['relevancy_avg'])->toBeGreaterThanOrEqual(0.75)
        ->and($report['mode'])->toBe('mock')
        ->and((float) $report['cost_usd'])->toBe(0.0)
        ->and($report['n_questions'])->toBe(5)
        ->and($report['n_failed'])->toBe(0);
})->group('ragas', 'ci');

it('gate_status=fail quando mock scores abaixo dos thresholds', function () {
    // Mock ABAIXO dos thresholds default
    $judge = app(RagasJudgeService::class);
    $judge->enableMock([
        'faithfulness' => 0.50,    // < 0.80 threshold
        'answer_relevancy' => 0.40, // < 0.75 threshold
    ]);
    $this->app->instance(RagasJudgeService::class, $judge);

    $exit = Artisan::call('jana:ragas-ci-eval', [
        '--json' => true,
        '--mode' => 'mock',
        '--sample-size' => 3,
    ]);

    expect($exit)->toBe(1); // fail = exit 1 → workflow bloqueia merge

    $output = Artisan::output();
    $report = json_decode(trim($output), true);

    expect($report['gate_status'])->toBe('fail')
        ->and($report['faithfulness_avg'])->toBeLessThan(0.80)
        ->and($report['relevancy_avg'])->toBeLessThan(0.75)
        ->and($report['n_failed'])->toBe(3)
        ->and($report['failures'])->toHaveCount(3);
})->group('ragas', 'ci');

it('respeita threshold custom via option (fail mesmo com scores médios)', function () {
    $judge = app(RagasJudgeService::class);
    $judge->enableMock([
        'faithfulness' => 0.82,    // passa threshold default 0.80
        'answer_relevancy' => 0.76, // passa default 0.75
    ]);
    $this->app->instance(RagasJudgeService::class, $judge);

    // Threshold custom mais agressivo
    $exit = Artisan::call('jana:ragas-ci-eval', [
        '--json' => true,
        '--mode' => 'mock',
        '--sample-size' => 2,
        '--threshold-faithfulness' => 0.95,
        '--threshold-relevancy' => 0.90,
    ]);

    expect($exit)->toBe(1);
    $report = json_decode(trim(Artisan::output()), true);
    expect($report['gate_status'])->toBe('fail');
})->group('ragas', 'ci');

it('JSON output tem schema canon esperado pelo workflow (gh pr comment)', function () {
    $judge = app(RagasJudgeService::class);
    $judge->enableMock();
    $this->app->instance(RagasJudgeService::class, $judge);

    Artisan::call('jana:ragas-ci-eval', [
        '--json' => true,
        '--mode' => 'mock',
        '--sample-size' => 2,
    ]);

    $report = json_decode(trim(Artisan::output()), true);

    // Schema canon W28 — workflow .github/workflows/jana-ragas-gate.yml depende destas keys
    expect($report)->toHaveKeys([
        'gate_status',
        'score_avg',
        'faithfulness_avg',
        'relevancy_avg',
        'n_questions',
        'n_passed',
        'n_failed',
        'failures',
        'mode',
        'cost_usd',
        'ran_at',
        'thresholds',
    ]);
    expect($report['gate_status'])->toBeIn(['pass', 'fail']);
    expect($report['thresholds'])->toHaveKeys(['faithfulness', 'answer_relevancy']);
})->group('ragas', 'ci');

it('sample-size limita golden set', function () {
    $judge = app(RagasJudgeService::class);
    $judge->enableMock();
    $this->app->instance(RagasJudgeService::class, $judge);

    Artisan::call('jana:ragas-ci-eval', [
        '--json' => true,
        '--mode' => 'mock',
        '--sample-size' => 3,
    ]);

    $report = json_decode(trim(Artisan::output()), true);
    expect($report['n_questions'])->toBe(3);
})->group('ragas', 'ci');
