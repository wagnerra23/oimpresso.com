<?php

declare(strict_types=1);

/**
 * KbBgeRerankerServiceTest — Wave 23 KB §G1.
 *
 * Cobre KbBgeRerankerService wrapper sobre BgeReranker Jana:
 *   - Disabled mode → devolve top-N por score legacy
 *   - Enabled mode + driver mock → reordena conforme score_rerank
 *   - Multi-tenant Tier 0 assert biz>0
 *   - Empty top-K → empty out
 *   - Driver exception → fallback graceful
 *
 * Mock-safe: usa fake Reranker driver — não chama HTTP CT 100.
 */

use Illuminate\Support\Collection;
use Modules\Jana\Services\Retrieval\Reranker;
use Modules\KB\Services\KbBgeRerankerService;

// TestCase é aplicado via tests/Pest.php uses(TestCase::class)->in(KbUnitDir).

/**
 * Fake driver — retorna ordem invertida (último primeiro) pra assertar reorder.
 */
function fakeBgeReranker(array $scoreOverride = []): Reranker
{
    return new class($scoreOverride) implements Reranker {
        public function __construct(private array $scoreOverride) {}

        public function reranquear(string $query, array $candidatos, int $topK = 5): array
        {
            // Inverte ordem + score_rerank decrescente (1.0, 0.5, 0.25, ...)
            $reversed = array_reverse($candidatos);
            $out = [];
            $score = 1.0;
            foreach (array_slice($reversed, 0, $topK) as $c) {
                $out[] = [
                    'id' => $c['id'],
                    'snippet' => $c['snippet'],
                    'score_rerank' => $this->scoreOverride[$c['id']] ?? $score,
                ];
                $score *= 0.5;
            }

            return $out;
        }
    };
}

beforeEach(function () {
    config(['kb.bge.enabled' => true]);
});

it('rerank exige business_id positivo (Tier 0 ADR 0093)', function () {
    $svc = new KbBgeRerankerService(fakeBgeReranker());
    $topK = collect([['kb_node_id' => 1, 'snippet' => 's', 'score' => 0.5]]);

    expect(fn () => $svc->rerank('q', $topK, 0))->toThrow(\InvalidArgumentException::class);
    expect(fn () => $svc->rerank('q', $topK, -1))->toThrow(\InvalidArgumentException::class);
});

it('rerank disabled mode → fallback score-only legacy', function () {
    config(['kb.bge.enabled' => false]);
    $svc = new KbBgeRerankerService(fakeBgeReranker());

    $topK = collect([
        ['kb_node_id' => 1, 'slug' => 'a', 'snippet' => 'aaa', 'score' => 0.3],
        ['kb_node_id' => 2, 'slug' => 'b', 'snippet' => 'bbb', 'score' => 0.9],
        ['kb_node_id' => 3, 'slug' => 'c', 'snippet' => 'ccc', 'score' => 0.5],
    ]);

    $out = $svc->rerank('query', $topK, businessId: 1, topN: 2);

    // Desabilitado → ordena por score DESC (legacy behavior)
    expect($out)->toHaveCount(2);
    expect($out[0]['kb_node_id'])->toBe(2); // score 0.9
    expect($out[1]['kb_node_id'])->toBe(3); // score 0.5
});

it('rerank enabled mode → driver reordena conforme score_rerank', function () {
    config(['kb.bge.enabled' => true]);
    $svc = new KbBgeRerankerService(fakeBgeReranker());

    // Original ordem: 1, 2, 3 (por score). Driver fake inverte → 3, 2, 1
    $topK = collect([
        ['kb_node_id' => 1, 'slug' => 'a', 'snippet' => 'aaa', 'score' => 0.9],
        ['kb_node_id' => 2, 'slug' => 'b', 'snippet' => 'bbb', 'score' => 0.5],
        ['kb_node_id' => 3, 'slug' => 'c', 'snippet' => 'ccc', 'score' => 0.3],
    ]);

    $out = $svc->rerank('query', $topK, businessId: 1, topN: 3);

    expect($out)->toHaveCount(3);
    expect($out[0]['kb_node_id'])->toBe(3); // invertido pelo fake
    expect($out[1]['kb_node_id'])->toBe(2);
    expect($out[2]['kb_node_id'])->toBe(1);

    // score_rerank adicionado
    expect($out[0]['score_rerank'])->toBeFloat();
});

it('rerank empty topK retorna collection vazia', function () {
    $svc = new KbBgeRerankerService(fakeBgeReranker());
    $out = $svc->rerank('q', collect(), businessId: 1, topN: 5);
    expect($out)->toBeInstanceOf(Collection::class);
    expect($out)->toBeEmpty();
});

it('rerank topN=0 retorna collection vazia', function () {
    $svc = new KbBgeRerankerService(fakeBgeReranker());
    $topK = collect([['kb_node_id' => 1, 'snippet' => 's', 'score' => 0.5]]);
    $out = $svc->rerank('q', $topK, businessId: 1, topN: 0);
    expect($out)->toBeEmpty();
});

it('rerank driver retorna vazio → fallback top-N por score', function () {
    $emptyDriver = new class implements Reranker {
        public function reranquear(string $query, array $candidatos, int $topK = 5): array
        {
            return []; // simula driver fail + fallback interno também vazio
        }
    };
    $svc = new KbBgeRerankerService($emptyDriver);

    $topK = collect([
        ['kb_node_id' => 1, 'slug' => 'a', 'snippet' => 's', 'score' => 0.3],
        ['kb_node_id' => 2, 'slug' => 'b', 'snippet' => 's', 'score' => 0.9],
    ]);

    $out = $svc->rerank('q', $topK, businessId: 1, topN: 1);

    expect($out)->toHaveCount(1);
    expect($out[0]['kb_node_id'])->toBe(2);
});

it('makeDefault factory monta service válido com config canon', function () {
    config(['kb.bge.endpoint' => 'http://bge-reranker.ct100:8080/rerank']);
    config(['kb.bge.timeout' => 5]);

    $svc = KbBgeRerankerService::makeDefault();
    expect($svc)->toBeInstanceOf(KbBgeRerankerService::class);
});
