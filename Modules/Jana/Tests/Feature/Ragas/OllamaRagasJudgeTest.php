<?php

declare(strict_types=1);

// @covers-us US-COPI-137

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Services\Ragas\JudgeUnavailableException;
use Modules\Jana\Services\Ragas\OllamaRagasJudge;
use Tests\TestCase;

uses(TestCase::class);

/**
 * OllamaRagasJudgeTest — US-COPI-137 — juiz RAGAS local (Ollama CT 100).
 *
 * Prova o que importa no transporte local (zero egress):
 *   1. pontua faithfulness via /api/chat lendo message.content (0..1), no url+model configurados.
 *   2. sanitiza score fora de 0..1.
 *   3. HONESTIDADE: em falha (HTTP != 2xx / JSON sem score / transporte) LANÇA
 *      JudgeUnavailableException — NUNCA devolve 0.0 fabricado (falso alarme de "infiel").
 */
beforeEach(function () {
    config([
        'copiloto.online_eval.local.url' => 'http://ollama.test',
        'copiloto.online_eval.local.model' => 'qwen2.5:3b',
        'copiloto.online_eval.local.timeout' => 5,
    ]);
});

function fakeOllamaChat(array $content): void
{
    Http::fake([
        'http://ollama.test/api/chat' => Http::response([
            'message' => ['content' => json_encode($content)],
        ], 200),
    ]);
}

it('pontua faithfulness via Ollama /api/chat — parse do score 0..1, no url+model configurados', function () {
    fakeOllamaChat(['total_claims' => 3, 'supported' => 2, 'score' => 0.67, 'reasoning' => 'ok']);

    $score = app(OllamaRagasJudge::class)->scoreFaithfulness('pergunta', 'resposta', 'contexto');

    expect($score)->toBe(0.67);

    Http::assertSent(function ($req) {
        return $req->url() === 'http://ollama.test/api/chat'
            && $req['model'] === 'qwen2.5:3b'
            && $req['format'] === 'json'
            && $req['stream'] === false;
    });
});

it('as 4 métricas herdadas passam pelo transporte Ollama (não OpenAI)', function () {
    fakeOllamaChat(['score' => 0.5]);

    $judge = app(OllamaRagasJudge::class);
    expect($judge->scoreAnswerRelevancy('q', 'a'))->toBe(0.5);
    expect($judge->scoreContextRecall('q', 'ctx', 'gt'))->toBe(0.5);

    // Nada saiu pro OpenAI (zero egress) — só o Ollama local recebeu.
    Http::assertNotSent(fn ($req) => str_contains($req->url(), 'openai.com'));
});

// 2 testes separados de propósito: Http::fake() APPENDA stubs (o 1º match ganha),
// então re-fakear a mesma URL no mesmo teste não troca a resposta. O estado do fake
// reseta entre testes (app recriada no setUp) — cada caso ganha um fake limpo.
it('sanitiza score > 1 → 1.0', function () {
    fakeOllamaChat(['score' => 1.7]);
    expect(app(OllamaRagasJudge::class)->scoreFaithfulness('q', 'a', 'c'))->toBe(1.0);
});

it('sanitiza score < 0 → 0.0', function () {
    fakeOllamaChat(['score' => -0.5]);
    expect(app(OllamaRagasJudge::class)->scoreFaithfulness('q', 'a', 'c'))->toBe(0.0);
});

it('HTTP 500 → LANÇA JudgeUnavailableException (não fabrica 0.0)', function () {
    Http::fake(['http://ollama.test/api/chat' => Http::response('boom', 500)]);

    expect(fn () => app(OllamaRagasJudge::class)->scoreFaithfulness('q', 'a', 'c'))
        ->toThrow(JudgeUnavailableException::class);
});

it('JSON sem score numérico → LANÇA JudgeUnavailableException', function () {
    fakeOllamaChat(['reasoning' => 'esqueci o score']);

    expect(fn () => app(OllamaRagasJudge::class)->scoreFaithfulness('q', 'a', 'c'))
        ->toThrow(JudgeUnavailableException::class);
});

it('erro de transporte (Ollama down / sem modelo) → LANÇA JudgeUnavailableException', function () {
    Http::fake(function () {
        throw new ConnectionException('connection refused');
    });

    expect(fn () => app(OllamaRagasJudge::class)->scoreFaithfulness('q', 'a', 'c'))
        ->toThrow(JudgeUnavailableException::class);
});

it('mock herdado curto-circuita ANTES do transporte (não bate no Ollama)', function () {
    Http::fake(); // qualquer chamada falharia a asserção abaixo

    $judge = app(OllamaRagasJudge::class);
    $judge->enableMock(['faithfulness' => 0.42]);

    expect($judge->scoreFaithfulness('q', 'a', 'c'))->toBe(0.42);
    Http::assertNothingSent();
});
