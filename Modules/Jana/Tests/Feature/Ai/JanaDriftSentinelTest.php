<?php

declare(strict_types=1);

/**
 * JanaDriftSentinelTest — Wave 23 — testes do command jana:drift-sentinel.
 *
 * Cobre:
 *  - Comando registrado e runnable em mock mode
 *  - Exit 0 quando drift dentro do threshold
 *  - Exit 1 quando drift acima do threshold
 *  - --update-baseline regrava fixture
 *  - --detail emite tabela
 *
 * Mock-safe: usa RagasJudgeService::enableMock() — não chama OpenAI.
 *
 * @see Modules/Jana/Console/Commands/JanaDriftSentinelCommand.php
 */

use Illuminate\Support\Facades\File;
use Modules\Jana\Services\Ragas\RagasJudgeService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Garante baseline fixture existe (commit inicial criou em 0.85 fixed).
    $baselinePath = base_path('Modules/Jana/Tests/Feature/Ai/fixtures/baseline-responses.json');
    if (! File::exists($baselinePath)) {
        $this->markTestSkipped("Baseline ausente em {$baselinePath} — rode jana:drift-sentinel --update-baseline --mock primeiro.");
    }
});

it('drift-sentinel command em mock mode com baseline 0.85 e current 0.85 → exit 0', function () {
    // Mock score = 0.85, baseline = 0.85 → delta 0 → sem drift
    $this->artisan('jana:drift-sentinel', ['--mock' => true])
        ->assertExitCode(0);
});

it('drift-sentinel detecta drift quando current diverge muito do baseline', function () {
    // Injeta judge customizado ANTES do command rodar.
    // Command detecta isMockMode() e NÃO sobrescreve (preserva 0.30 vs baseline 0.85 = delta 0.55).
    $judge = new RagasJudgeService();
    $judge->enableMock(['faithfulness' => 0.30]);
    $this->app->instance(RagasJudgeService::class, $judge);

    $this->artisan('jana:drift-sentinel', [
        '--mock' => true,
        '--max-drift' => 5, // 5% — qualquer drift estoura
        '--drift-threshold' => 0.25,
    ])->assertExitCode(1); // drift detectado em todas 50 perguntas
});

it('drift-sentinel --update-baseline regrava fixture e retorna exit 0', function () {
    $baselinePath = base_path('Modules/Jana/Tests/Feature/Ai/fixtures/baseline-responses.json');
    $originalContent = File::get($baselinePath);

    try {
        $this->artisan('jana:drift-sentinel', [
            '--update-baseline' => true,
            '--mock' => true,
        ])->assertExitCode(0);

        $newContent = File::get($baselinePath);
        $data = json_decode($newContent, true);

        expect($data)->toHaveKeys(['_meta', 'responses']);
        expect(count($data['responses']))->toBeGreaterThanOrEqual(50); // bate com gold-set
        expect($data['responses'][0])->toHaveKeys(['question_id', 'question', 'faithfulness']);
    } finally {
        // Restore original — não polui o commit.
        File::put($baselinePath, $originalContent);
    }
});

it('drift-sentinel --detail emite tabela por pergunta', function () {
    $this->artisan('jana:drift-sentinel', [
        '--mock' => true,
        '--detail' => true,
    ])
        ->expectsOutputToContain('Question')
        ->assertExitCode(0);
});

it('drift-sentinel falha graceful se gold-set ausente', function () {
    // Simula gold-set ausente renomeando temporariamente
    $goldPath = base_path('Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json');
    $tempPath = $goldPath . '.bak';

    File::move($goldPath, $tempPath);
    try {
        $this->artisan('jana:drift-sentinel', ['--mock' => true])
            ->assertExitCode(1);
    } finally {
        File::move($tempPath, $goldPath);
    }
});
