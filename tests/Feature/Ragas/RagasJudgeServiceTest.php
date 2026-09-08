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

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

// --- Caminho de ERRO do judge (2026-09-02) -------------------------------
// O canary somou 20x "Judge HTTP 429" num dia sem nenhum campo capaz de dizer
// QUAL 429 era — e os dois tipos tem acoes OPOSTAS: `insufficient_quota` e
// billing (fora do repo) e `rate_limit_exceeded` e falta de backoff (aqui
// dentro). Estes testes prendem a DISTINCAO: se alguem voltar a logar so o
// status, os dois casos viram a mesma string e ambos quebram.

it('no erro HTTP loga o codigo insufficient_quota (billing — fora do repo)', function () {
    config(['openai.api_key' => 'sk-fake-para-teste']);

    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'You exceeded your current quota.',
            'type'    => 'insufficient_quota',
            'code'    => 'insufficient_quota',
        ],
    ], 429)]);

    Log::spy();

    expect((new RagasJudgeService())->scoreFaithfulness('q', 'a', 'ctx'))->toBe(0.0);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, '429')
        && str_contains($m, 'insufficient_quota')
        && str_contains($m, 'metric=faithfulness'));
});

it('no erro HTTP loga o codigo rate_limit_exceeded (backoff — dentro do repo)', function () {
    config(['openai.api_key' => 'sk-fake-para-teste']);

    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'Rate limit reached for gpt-4o-mini.',
            'type'    => 'requests',
            'code'    => 'rate_limit_exceeded',
        ],
    ], 429)]);

    Log::spy();

    expect((new RagasJudgeService())->scoreAnswerRelevancy('q', 'a'))->toBe(0.0);

    // CONTROLE NEGATIVO: o mesmo status 429, codigo DIFERENTE. Se o log voltasse a
    // carregar so o status, este assert e o de cima passariam a ser indistinguiveis.
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, 'rate_limit_exceeded')
        && ! str_contains($m, 'insufficient_quota'));
});

it('erro sem corpo JSON nao quebra o log (fallback declarado)', function () {
    config(['openai.api_key' => 'sk-fake-para-teste']);

    Http::fake(['api.openai.com/*' => Http::response('', 503)]);

    Log::spy();

    expect((new RagasJudgeService())->scoreFaithfulness('q', 'a', 'ctx'))->toBe(0.0);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, '503')
        && str_contains($m, '(sem corpo)'));
});


// --- Curto-circuito SEM-CREDITO (2026-09-03) -------------------------------------
//
// Medido no canary run 33744191426: 51 perguntas x 2 metricas = 102 chamadas, TODAS
// com `429 erro=credit_balance_exhausted`, e 102 linhas identicas de WARNING. A 1a ja
// respondia; as 101 seguintes gastaram runner e enterraram a causa.
//
// Os dois testes abaixo sao um PAR: o 1o prova que a familia sem-credito para o run,
// o 2o prova que `rate_limit_exceeded` NAO para. Sem o 2o, um breaker que abortasse
// em QUALQUER 429 passaria no 1o e derrubaria 50 perguntas boas por uma rajada de 1s.

it('sem-credito abre o circuito: a 2a metrica nao faz HTTP nenhum', function () {
    config(['openai.api_key' => 'sk-fake-para-teste']);

    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'Your credit balance is too low.',
            'type'    => 'invalid_request_error',
            'code'    => 'credit_balance_exhausted',
        ],
    ], 429)]);

    $judge = new RagasJudgeService();

    // MESMA instancia, como no `JanaRagasCiCommand` (o `app(RagasJudgeService::class)`
    // e resolvido UMA vez, antes do loop das perguntas). Se fosse uma instancia por
    // pergunta, o breaker nao seguraria nada — e este teste seria teatro.
    expect($judge->scoreFaithfulness('q', 'a', 'ctx'))->toBe(0.0);
    expect($judge->scoreAnswerRelevancy('q', 'a'))->toBe(0.0);
    expect($judge->scoreContextPrecision('q', 'ctx'))->toBe(0.0);

    // O assert que MORDE: 3 metricas pedidas, UMA chamada HTTP. Contar requests e o
    // unico jeito de provar que as outras 2 nao sairam — o score 0.0 seria identico
    // com ou sem breaker, entao assertar so o score nao provaria nada.
    Http::assertSentCount(1);
});

it('CONTROLE NEGATIVO: rate_limit_exceeded NAO abre o circuito', function () {
    config(['openai.api_key' => 'sk-fake-para-teste']);

    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'Rate limit reached for gpt-4o-mini.',
            'type'    => 'requests',
            'code'    => 'rate_limit_exceeded',
        ],
    ], 429)]);

    $judge = new RagasJudgeService();

    expect($judge->scoreFaithfulness('q', 'a', 'ctx'))->toBe(0.0);
    expect($judge->scoreAnswerRelevancy('q', 'a'))->toBe(0.0);
    expect($judge->scoreContextPrecision('q', 'ctx'))->toBe(0.0);

    // 3 pedidas, 3 enviadas: rajada e TRANSITORIA. Abortar o run por causa dela
    // descartaria perguntas que teriam medido bem 1 segundo depois.
    Http::assertSentCount(3);
});

it('sem-credito loga UMA linha que nomeia a causa e onde ela se resolve', function () {
    config(['openai.api_key' => 'sk-fake-para-teste']);

    Http::fake(['api.openai.com/*' => Http::response([
        'error' => ['code' => 'insufficient_quota'],
    ], 429)]);

    Log::spy();

    $judge = new RagasJudgeService();
    $judge->scoreFaithfulness('q', 'a', 'ctx');
    $judge->scoreAnswerRelevancy('q', 'a');

    // A linha existe e diz billing/conta — quem abre a issue do canary le a causa
    // sem precisar cavar 102 warnings identicos.
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, 'SEM CREDITO')
        && str_contains($m, 'insufficient_quota')
        && str_contains($m, 'billing'));

    // E ela sai UMA vez, nao a cada chamada pulada — senao o breaker trocaria 102
    // linhas de um tipo por 102 de outro.
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, 'SEM CREDITO'))->once();
});
