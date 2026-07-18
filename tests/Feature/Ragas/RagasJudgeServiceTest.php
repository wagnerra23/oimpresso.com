<?php

declare(strict_types=1);

// @covers-us US-COPI-137

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

use Illuminate\Support\Facades\Http;
use Modules\Jana\Services\Ragas\RagasJudgeService;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

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

// ── Backend Ollama local (US-COPI-137 rota B, zero-egress pra terceiro) ──────────

it('backend ollama: POST /api/chat, parseia message.content, NÃO toca OpenAI', function () {
    config([
        'ragas.judge_backend' => 'ollama',
        'ragas.ollama_url'    => 'http://ollama.test:11434',
        'ragas.ollama_model'  => 'qwen2.5:3b',
        'langfuse.enabled'    => false, // recordScore vira no-op no teste
    ]);

    Http::fake([
        'ollama.test:11434/api/chat' => Http::response([
            'message'           => ['content' => '{"total_claims": 4, "supported": 3, "score": 0.75}'],
            'prompt_eval_count' => 120,
            'eval_count'        => 40,
        ]),
        // Guarda dura: se tocar OpenAI, o teste explode (prova de zero-egress pra terceiro).
        'api.openai.com/*' => Http::response(['erro' => 'NUNCA'], 500),
    ]);

    $score = (new RagasJudgeService())->scoreFaithfulness('pergunta', 'resposta real', 'contexto real');

    expect($score)->toBe(0.75);
    Http::assertSent(fn ($req) => str_contains($req->url(), 'ollama.test:11434/api/chat'));
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'api.openai.com'));
});

it('backend ollama sem url configurada: retorna 0.0 e NÃO faz HTTP (pré-req infra ausente)', function () {
    config([
        'ragas.judge_backend' => 'ollama',
        'ragas.ollama_url'    => '',
        'langfuse.enabled'    => false,
    ]);

    Http::fake(); // qualquer HTTP registraria

    $score = (new RagasJudgeService())->scoreFaithfulness('q', 'a', 'ctx');

    expect($score)->toBe(0.0);
    Http::assertNothingSent();
});

it('backend ollama HTTP 500: fail-open (0.0), não explode', function () {
    config([
        'ragas.judge_backend' => 'ollama',
        'ragas.ollama_url'    => 'http://ollama.test:11434',
        'langfuse.enabled'    => false,
    ]);

    Http::fake(['ollama.test:11434/api/chat' => Http::response('down', 500)]);

    $score = (new RagasJudgeService())->scoreFaithfulness('q', 'a', 'ctx');

    expect($score)->toBe(0.0);
});

// Regressão do refactor: o caminho openai (batch RAGAS histórico) segue intacto.
it('backend openai (default): POST api.openai.com, parseia choices.0.message.content', function () {
    config([
        'ragas.judge_backend' => 'openai',
        'openai.api_key'      => 'sk-test',
        'langfuse.enabled'    => false,
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"score": 0.91}']]],
            'usage'   => ['prompt_tokens' => 200, 'completion_tokens' => 60],
        ]),
    ]);

    $score = (new RagasJudgeService())->scoreFaithfulness('q', 'a', 'ctx');

    expect($score)->toBe(0.91);
    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.openai.com'));
});
