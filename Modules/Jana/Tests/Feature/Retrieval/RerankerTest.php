<?php

declare(strict_types=1);

use Modules\Jana\Services\Retrieval\LlmRerankerAdapter;
use Modules\Jana\Services\Retrieval\NullReranker;
use Modules\Jana\Services\Retrieval\Reranker;
use Modules\Jana\Services\Retrieval\RrfReranker;

uses(Tests\TestCase::class);

/**
 * GAP-A — AUDITORIA 2026-05-13 §5 G3.
 *
 * Cobertura:
 *   - Rank changes (RRF reordena por multi-source rank fusion)
 *   - Idempotência (chamar 2× retorna mesma ordem)
 *   - Top-K respeitado (input 10, topK=3 → 3 items)
 *   - Edge cases (empty input, topK<=0, missing id, single-source fallback)
 *   - NullReranker (passthrough quando flag desabilitada)
 *   - Container resolve (binding via JanaServiceProvider)
 */

// ── helpers ──────────────────────────────────────────────────────────────

function makeCandidato(int $id, string $snippet = 'doc', float $score = 1.0, ?array $rankSources = null): array
{
    $c = ['id' => $id, 'snippet' => $snippet, 'score' => $score];
    if ($rankSources !== null) {
        $c['rank_sources'] = $rankSources;
    }
    return $c;
}

// ─── RrfReranker ─────────────────────────────────────────────────────────

it('RrfReranker reordena candidatos com rank_sources multi-source', function () {
    $reranker = new RrfReranker();

    // RRF math (k=60):
    //   doc1: 1/(60+0+1) = 0.01639                            (single hit, top rank)
    //   doc2: 2/(60+0+1) = 0.03279                            (2 sources, top rank → top-1)
    //   doc3: 1/(60+5+1) + 1/(60+8+1) = 0.01515 + 0.01449 = 0.02964   (2 sources, mid rank)
    // Ordem esperada: 2 > 3 > 1 (qty de sources beats rank único bom)
    $candidatos = [
        makeCandidato(1, 'doc1', rankSources: [0]),
        makeCandidato(2, 'doc2', rankSources: [0, 0]),
        makeCandidato(3, 'doc3', rankSources: [5, 8]),
    ];

    $out = $reranker->reranquear('query qualquer', $candidatos, 3);

    expect($out)->toHaveCount(3);
    expect($out[0]['id'])->toBe(2);
    expect($out[1]['id'])->toBe(3);
    expect($out[2]['id'])->toBe(1);
});

it('RrfReranker é idempotente: chamadas repetidas mesmo input retornam mesma ordem', function () {
    $reranker = new RrfReranker();

    $candidatos = [
        makeCandidato(1, 'a', rankSources: [0]),
        makeCandidato(2, 'b', rankSources: [0, 0]),
        makeCandidato(3, 'c', rankSources: [2]),
    ];

    $run1 = $reranker->reranquear('q', $candidatos, 3);
    $run2 = $reranker->reranquear('q', $candidatos, 3);
    $run3 = $reranker->reranquear('q', $candidatos, 3);

    expect(array_column($run1, 'id'))->toBe(array_column($run2, 'id'));
    expect(array_column($run2, 'id'))->toBe(array_column($run3, 'id'));
});

it('RrfReranker respeita topK menor que count(candidatos)', function () {
    $reranker = new RrfReranker();
    $candidatos = [];
    for ($i = 1; $i <= 10; $i++) {
        $candidatos[] = makeCandidato($i, "doc{$i}");
    }

    $out = $reranker->reranquear('q', $candidatos, 3);
    expect($out)->toHaveCount(3);

    $out5 = $reranker->reranquear('q', $candidatos, 5);
    expect($out5)->toHaveCount(5);

    // topK > count → retorna todos
    $outAll = $reranker->reranquear('q', $candidatos, 20);
    expect($outAll)->toHaveCount(10);
});

it('RrfReranker adiciona score_rerank normalizado 0..1 em todos os results', function () {
    $reranker = new RrfReranker();

    $candidatos = [
        makeCandidato(1, 'a', rankSources: [0]),
        makeCandidato(2, 'b', rankSources: [0, 0]),
        makeCandidato(3, 'c', rankSources: [2]),
    ];

    $out = $reranker->reranquear('q', $candidatos, 3);

    foreach ($out as $c) {
        expect($c)->toHaveKey('score_rerank');
        expect($c['score_rerank'])->toBeFloat();
        expect($c['score_rerank'])->toBeGreaterThanOrEqual(0.0);
        expect($c['score_rerank'])->toBeLessThanOrEqual(1.0);
    }

    // Top-1 sempre tem score_rerank=1.0 (normalização)
    expect($out[0]['score_rerank'])->toBe(1.0);
});

it('RrfReranker degenera pra single-source quando rank_sources ausente (ordem entrada = rank)', function () {
    $reranker = new RrfReranker();

    // Sem rank_sources → ordem de entrada = rank → 1, 2, 3
    $candidatos = [
        makeCandidato(10, 'a'),
        makeCandidato(20, 'b'),
        makeCandidato(30, 'c'),
    ];

    $out = $reranker->reranquear('q', $candidatos, 3);
    expect(array_column($out, 'id'))->toBe([10, 20, 30]);
});

// ─── Edge cases ──────────────────────────────────────────────────────────

it('RrfReranker retorna [] quando candidatos vazio', function () {
    $reranker = new RrfReranker();
    expect($reranker->reranquear('q', [], 5))->toBe([]);
});

it('RrfReranker retorna [] quando topK <= 0', function () {
    $reranker = new RrfReranker();
    $candidatos = [makeCandidato(1)];
    expect($reranker->reranquear('q', $candidatos, 0))->toBe([]);
    expect($reranker->reranquear('q', $candidatos, -3))->toBe([]);
});

it('RrfReranker ignora candidatos sem id', function () {
    $reranker = new RrfReranker();
    $candidatos = [
        ['snippet' => 'sem id'],            // skip
        makeCandidato(1, 'com id'),
    ];

    $out = $reranker->reranquear('q', $candidatos, 5);
    expect($out)->toHaveCount(1);
    expect($out[0]['id'])->toBe(1);
});

// ─── NullReranker ────────────────────────────────────────────────────────

it('NullReranker faz passthrough preservando ordem e respeitando topK', function () {
    $reranker = new NullReranker();
    $candidatos = [
        makeCandidato(10, 'a'),
        makeCandidato(20, 'b'),
        makeCandidato(30, 'c'),
        makeCandidato(40, 'd'),
    ];

    $out = $reranker->reranquear('q', $candidatos, 2);
    expect($out)->toHaveCount(2);
    expect(array_column($out, 'id'))->toBe([10, 20]);

    foreach ($out as $c) {
        expect($c)->toHaveKey('score_rerank');
    }
});

it('NullReranker retorna [] em edge cases', function () {
    $reranker = new NullReranker();
    expect($reranker->reranquear('q', [], 5))->toBe([]);
    expect($reranker->reranquear('q', [makeCandidato(1)], 0))->toBe([]);
});

// ─── Container binding via JanaServiceProvider ───────────────────────────

it('Container resolve Reranker como RrfReranker quando driver=rrf', function () {
    config(['copiloto.reranker.enabled' => true, 'copiloto.reranker.driver' => 'rrf']);

    $r = app(Reranker::class);
    expect($r)->toBeInstanceOf(RrfReranker::class);
});

it('Container resolve Reranker como NullReranker quando flag desabilitada', function () {
    config(['copiloto.reranker.enabled' => false, 'copiloto.reranker.driver' => 'rrf']);

    $r = app(Reranker::class);
    expect($r)->toBeInstanceOf(NullReranker::class);
});

it('Container resolve Reranker como NullReranker quando driver=null', function () {
    config(['copiloto.reranker.enabled' => true, 'copiloto.reranker.driver' => 'null']);

    $r = app(Reranker::class);
    expect($r)->toBeInstanceOf(NullReranker::class);
});

it('Container resolve Reranker como LlmRerankerAdapter quando driver=llm', function () {
    config(['copiloto.reranker.enabled' => true, 'copiloto.reranker.driver' => 'llm']);

    $r = app(Reranker::class);
    expect($r)->toBeInstanceOf(LlmRerankerAdapter::class);
});

// ─── Multi-tenant Tier 0 defensiva ───────────────────────────────────────

it('Reranker NÃO carrega business_id — material assumido já scoped pelo caller', function () {
    // Defensiva ADR 0093: contrato Reranker recebe só material já filtered.
    // Reflection: nenhum método do contrato tem $businessId no signature.
    $reflection = new ReflectionClass(Reranker::class);
    $method     = $reflection->getMethod('reranquear');

    $paramNames = array_map(fn ($p) => $p->getName(), $method->getParameters());
    expect($paramNames)->not->toContain('businessId');
    expect($paramNames)->toContain('query', 'candidatos', 'topK');
});
