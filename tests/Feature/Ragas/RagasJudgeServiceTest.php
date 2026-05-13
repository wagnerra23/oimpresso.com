<?php

declare(strict_types=1);

/**
 * RagasJudgeServiceTest — testa o judge service em modo mock (sem OPENAI_API_KEY).
 *
 * Cobre:
 *  - Mock mode retorna scores controlados (sanidade)
 *  - scoreAll() retorna as 4 métricas
 *  - context_recall = 0 quando groundTruth vazio
 *  - Sanitização do range 0..1 funciona
 *
 * Este teste RODA LOCAL em CI default — não precisa OPENAI_API_KEY.
 * Os outros RAGAS tests (BriefDiarioFaithfulnessTest, KbAnswerRelevancyTest)
 * são marcados @group ragas e só rodam sob workflow_dispatch + chave OpenAI.
 *
 * @see config/ragas.php
 * @see Modules/Jana/Services/Ragas/RagasJudgeService.php
 */

use Modules\Jana\Services\Ragas\RagasJudgeService;
use Tests\TestCase;

uses(TestCase::class);

it('retorna mock scores quando enableMock() é chamado', function () {
    $judge = new RagasJudgeService();
    $judge->enableMock([
        'faithfulness'      => 0.92,
        'answer_relevancy'  => 0.88,
        'context_precision' => 0.81,
        'context_recall'    => 0.74,
    ]);

    expect($judge->scoreFaithfulness('q', 'a', 'ctx'))->toBe(0.92);
    expect($judge->scoreAnswerRelevancy('q', 'a'))->toBe(0.88);
    expect($judge->scoreContextPrecision('q', 'ctx'))->toBe(0.81);
    expect($judge->scoreContextRecall('q', 'ctx', 'gt'))->toBe(0.74);
});

it('scoreAll retorna as 4 metricas RAGAS canonicas', function () {
    $judge = new RagasJudgeService();
    $judge->enableMock();

    $result = $judge->scoreAll(
        'Qual ADR fala de multi-tenant Tier 0?',
        'A ADR 0093 trata isolamento multi-tenant.',
        'ADR 0093: multi-tenant Tier 0 obrigatório (business_id global scope).',
        'ADR 0093 é a canonica.'
    );

    expect($result)->toHaveKeys([
        'faithfulness',
        'answer_relevancy',
        'context_precision',
        'context_recall',
    ]);

    foreach ($result as $metric => $score) {
        expect($score)->toBeFloat();
        expect($score)->toBeGreaterThanOrEqual(0.0);
        expect($score)->toBeLessThanOrEqual(1.0);
    }
});

it('context_recall retorna 0 quando ground_truth vazio', function () {
    $judge = new RagasJudgeService();
    $judge->enableMock();

    $result = $judge->scoreAll('q', 'a', 'ctx', '');

    expect($result['context_recall'])->toBe(0.0);
    expect($result['faithfulness'])->toBeGreaterThan(0.0);
});

it('config ragas.php carrega defaults sensatos', function () {
    expect(config('ragas.judge_model'))->toBe('gpt-4o-mini');
    expect(config('ragas.sample_size'))->toBeGreaterThanOrEqual(5);
    expect(config('ragas.thresholds.faithfulness'))->toBeGreaterThanOrEqual(0.5);
    expect(config('ragas.thresholds.answer_relevancy'))->toBeGreaterThanOrEqual(0.5);
    expect(config('ragas.thresholds.context_precision'))->toBeGreaterThanOrEqual(0.5);
    expect(config('ragas.thresholds.context_recall'))->toBeGreaterThanOrEqual(0.5);
});

it('thresholds canonicos batem com targets Langfuse 2026 (MVP relaxado)', function () {
    // MVP 2026-05-13 — alvo Q3/2026 = produção Langfuse standards.
    // Hoje: realistic-strict (não quebra IA-pair em runs reais).
    $t = config('ragas.thresholds');

    expect($t['faithfulness'])->toBeLessThanOrEqual(0.9, 'MVP < 0.9, produção alvo 0.9');
    expect($t['answer_relevancy'])->toBeLessThanOrEqual(0.85, 'MVP < 0.85, produção alvo 0.85');
    expect($t['context_precision'])->toBeLessThanOrEqual(0.8, 'MVP < 0.8, produção alvo 0.8');
});
