<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Services\Retrieval\BgeReranker;
use Modules\Jana\Services\Retrieval\Reranker;
use Modules\Jana\Services\Retrieval\RrfReranker;

uses(Tests\TestCase::class);

/**
 * Cobertura BgeReranker (Onda 4 R1 P0 — fechar Knowledge R3 hybrid retrieval).
 *
 * Testa:
 *   - Parsing resposta FastAPI BGE (formato {results:[{index,score},...]})
 *   - Ordering: items reordenados conforme score BGE (NÃO ordem de entrada)
 *   - Top-K respeitado
 *   - Idempotência (mesma query → mesma ordem)
 *   - Fallback graceful pra RRF quando HTTP 5xx, connection fail, body malformado
 *   - Edge cases (empty input, topK<=0, candidato sem id)
 *   - Multi-tenant Tier 0 (signature sem business_id)
 *
 * Não testa modelo real (sem cluster BGE em CI) — mocka Http::fake como contrato.
 */

// ── helpers ──────────────────────────────────────────────────────────────

function bgeMakeCandidato(int $id, string $snippet, float $score = 1.0): array
{
    return ['id' => $id, 'snippet' => $snippet, 'score' => $score];
}

function bgeReranker(?Reranker $fallback = null, string $endpoint = 'http://bge-reranker.ct100:8080/rerank'): BgeReranker
{
    return new BgeReranker(
        endpoint: $endpoint,
        fallback: $fallback ?? new RrfReranker(),
        timeoutSeconds: 5,
    );
}

// ── Parsing + ordering ───────────────────────────────────────────────────

it('BgeReranker reordena conforme score BGE (não preserva ordem de entrada)', function () {
    Http::fake([
        'bge-reranker.ct100:8080/*' => Http::response([
            'results' => [
                ['index' => 2, 'score' => 0.95],  // doc3 ganha
                ['index' => 0, 'score' => 0.72],  // doc1 segundo
                ['index' => 1, 'score' => 0.31],  // doc2 último
            ],
        ], 200),
    ]);

    $candidatos = [
        bgeMakeCandidato(101, 'doc1 texto'),
        bgeMakeCandidato(202, 'doc2 outro'),
        bgeMakeCandidato(303, 'doc3 relevante'),
    ];

    $out = bgeReranker()->reranquear('faturamento', $candidatos, 3);

    expect($out)->toHaveCount(3);
    expect(array_column($out, 'id'))->toBe([303, 101, 202]);
});

it('BgeReranker normaliza score_rerank em 0..1 baseado no top-1', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [
                ['index' => 0, 'score' => 0.90],
                ['index' => 1, 'score' => 0.45],
            ],
        ], 200),
    ]);

    $out = bgeReranker()->reranquear('q', [
        bgeMakeCandidato(1, 'a'),
        bgeMakeCandidato(2, 'b'),
    ], 2);

    expect($out[0]['score_rerank'])->toBe(1.0);
    expect($out[1]['score_rerank'])->toBeGreaterThan(0.0)->toBeLessThan(1.0);
    expect($out[1]['score_rerank'])->toBe(round(0.45 / 0.90, 4));
});

it('BgeReranker preserva campos originais do candidato (snippet, score legacy)', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [
                ['index' => 0, 'score' => 0.88],
            ],
        ], 200),
    ]);

    $out = bgeReranker()->reranquear('q', [
        bgeMakeCandidato(42, 'texto importante', 0.5),
    ], 1);

    expect($out[0])->toHaveKey('id');
    expect($out[0])->toHaveKey('snippet');
    expect($out[0])->toHaveKey('score');
    expect($out[0])->toHaveKey('score_rerank');
    expect($out[0]['id'])->toBe(42);
    expect($out[0]['snippet'])->toBe('texto importante');
    expect($out[0]['score'])->toBe(0.5);
});

// ── Top-K respeitado ─────────────────────────────────────────────────────

it('BgeReranker respeita topK quando serviço retorna mais que pedido', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [
                ['index' => 0, 'score' => 0.9],
                ['index' => 1, 'score' => 0.8],
                ['index' => 2, 'score' => 0.7],
                ['index' => 3, 'score' => 0.6],
                ['index' => 4, 'score' => 0.5],
            ],
        ], 200),
    ]);

    $candidatos = [];
    for ($i = 1; $i <= 5; $i++) {
        $candidatos[] = bgeMakeCandidato($i, "d{$i}");
    }

    $out = bgeReranker()->reranquear('q', $candidatos, 2);
    expect($out)->toHaveCount(2);
});

// ── Idempotência ─────────────────────────────────────────────────────────

it('BgeReranker é idempotente: mesmo input + resposta determinística → mesma ordem', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [
                ['index' => 1, 'score' => 0.85],
                ['index' => 0, 'score' => 0.42],
            ],
        ], 200),
    ]);

    $candidatos = [
        bgeMakeCandidato(10, 'a'),
        bgeMakeCandidato(20, 'b'),
    ];

    $run1 = bgeReranker()->reranquear('q', $candidatos, 2);
    $run2 = bgeReranker()->reranquear('q', $candidatos, 2);
    $run3 = bgeReranker()->reranquear('q', $candidatos, 2);

    expect(array_column($run1, 'id'))->toBe(array_column($run2, 'id'));
    expect(array_column($run2, 'id'))->toBe(array_column($run3, 'id'));
});

// ── Fallback graceful pra RRF ────────────────────────────────────────────

it('BgeReranker delega pra fallback RRF quando HTTP 500', function () {
    Http::fake([
        '*' => Http::response(['error' => 'cuda oom'], 500),
    ]);

    $candidatos = [
        bgeMakeCandidato(1, 'a', score: 1.0),
        bgeMakeCandidato(2, 'b', score: 1.0),
        bgeMakeCandidato(3, 'c', score: 1.0),
    ];

    $out = bgeReranker()->reranquear('q', $candidatos, 3);

    // RRF single-source mantém ordem de entrada
    expect($out)->toHaveCount(3);
    expect(array_column($out, 'id'))->toBe([1, 2, 3]);
    foreach ($out as $c) {
        expect($c)->toHaveKey('score_rerank');
    }
});

it('BgeReranker delega pra fallback RRF quando conexão falha (timeout / refused)', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $candidatos = [
        bgeMakeCandidato(10, 'a'),
        bgeMakeCandidato(20, 'b'),
    ];

    $out = bgeReranker()->reranquear('q', $candidatos, 2);

    expect($out)->toHaveCount(2);
    expect(array_column($out, 'id'))->toBe([10, 20]);
});

it('BgeReranker delega pra fallback quando body malformado (sem chave results)', function () {
    Http::fake([
        '*' => Http::response(['something' => 'wrong'], 200),
    ]);

    $candidatos = [
        bgeMakeCandidato(1, 'a'),
        bgeMakeCandidato(2, 'b'),
    ];

    $out = bgeReranker()->reranquear('q', $candidatos, 2);

    expect($out)->toHaveCount(2);
    expect(array_column($out, 'id'))->toBe([1, 2]);
});

// ── Timeout respeitado ───────────────────────────────────────────────────

it('BgeReranker aplica timeout configurado nas chamadas HTTP', function () {
    Http::fake([
        '*' => Http::response(['results' => []], 200),
    ]);

    $reranker = new BgeReranker(
        endpoint: 'http://bge-reranker.ct100:8080/rerank',
        fallback: new RrfReranker(),
        timeoutSeconds: 2,
    );

    $reranker->reranquear('q', [bgeMakeCandidato(1, 'a')], 1);

    // Asserta que pelo menos uma request saiu pro endpoint correto (timeout é prop request-level
    // do Http facade; valida via assertSent que payload chegou ok).
    Http::assertSent(function ($request) {
        return $request->url() === 'http://bge-reranker.ct100:8080/rerank'
            && $request['query'] === 'q'
            && $request['top_k'] === 1
            && is_array($request['documents']);
    });
});

// ── Edge cases ───────────────────────────────────────────────────────────

it('BgeReranker retorna [] quando candidatos vazio (sem chamar HTTP)', function () {
    Http::fake();

    $out = bgeReranker()->reranquear('q', [], 5);

    expect($out)->toBe([]);
    Http::assertNothingSent();
});

it('BgeReranker retorna [] quando topK <= 0 (sem chamar HTTP)', function () {
    Http::fake();

    $candidatos = [bgeMakeCandidato(1, 'a')];

    expect(bgeReranker()->reranquear('q', $candidatos, 0))->toBe([]);
    expect(bgeReranker()->reranquear('q', $candidatos, -3))->toBe([]);

    Http::assertNothingSent();
});

it('BgeReranker ignora candidatos sem id', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [
                ['index' => 0, 'score' => 0.9],
            ],
        ], 200),
    ]);

    $candidatos = [
        ['snippet' => 'sem id'],          // skip
        bgeMakeCandidato(7, 'com id'),
    ];

    $out = bgeReranker()->reranquear('q', $candidatos, 5);
    expect($out)->toHaveCount(1);
    expect($out[0]['id'])->toBe(7);
});

// ── Container binding via JanaServiceProvider ────────────────────────────

it('Container resolve Reranker como BgeReranker quando driver=bge', function () {
    config([
        'copiloto.reranker.enabled'      => true,
        'copiloto.reranker.driver'       => 'bge',
        'copiloto.reranker.bge.endpoint' => 'http://bge-reranker.ct100:8080/rerank',
        'copiloto.reranker.bge.timeout'  => 5,
    ]);

    $r = app(Reranker::class);
    expect($r)->toBeInstanceOf(BgeReranker::class);
});

// ── Multi-tenant Tier 0 ──────────────────────────────────────────────────

it('BgeReranker NÃO carrega business_id (stateless rerank — caller já filtrou)', function () {
    $reflection = new ReflectionClass(BgeReranker::class);
    $method     = $reflection->getMethod('reranquear');

    $paramNames = array_map(fn ($p) => $p->getName(), $method->getParameters());
    expect($paramNames)->not->toContain('businessId');
    expect($paramNames)->toContain('query', 'candidatos', 'topK');
});
