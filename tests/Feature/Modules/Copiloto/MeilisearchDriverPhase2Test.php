<?php

use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Modules\Copiloto\Entities\CopilotoMemoriaFato;
use Modules\Copiloto\Services\Memoria\HydeQueryExpander;
use Modules\Copiloto\Services\Memoria\LlmReranker;
use Modules\Copiloto\Services\Memoria\MeilisearchDriver;
use Modules\Copiloto\Services\Memoria\NegativeCacheService;

/**
 * MEM-MEM-WIRE Phase 2 — testes do pipeline HyDE + Reranker + NegativeCache
 * wireado no MeilisearchDriver::buscar() (ADR 0054).
 *
 * Engine fake reutilizada dos testes Phase 1. Mocks Mockery pra HyDE/Reranker
 * quando precisamos verificar chamadas sem side-effects de LLM.
 */

// Helper: cria engine fake que retorna zero resultados (pra testes de pipeline)
function fakeEngineVazia(): Engine
{
    return new class extends Engine {
        public array $lastParams = [];
        public string $lastQuery  = '';

        public function update($models): void {}
        public function delete($models): void {}

        public function search(Builder $builder)
        {
            if ($builder->callback) {
                $idx = new class ($this) {
                    public function __construct(private readonly mixed $outer) {}
                    public function search(string $q, array $p): object
                    {
                        $this->outer->lastQuery  = $q;
                        $this->outer->lastParams = $p;
                        return (object) ['hits' => [], 'estimatedTotalHits' => 0];
                    }
                };
                call_user_func($builder->callback, $idx, $builder->query, []);
            }
            return (object) ['hits' => [], 'estimatedTotalHits' => 0];
        }

        public function paginate(Builder $builder, $perPage, $page) { return $this->search($builder); }
        public function mapIds($results) { return collect(); }
        public function map(Builder $builder, $results, $model) { return collect(); }
        public function lazyMap(Builder $builder, $results, $model) { return \Illuminate\Support\LazyCollection::empty(); }
        public function getTotalCount($results) { return 0; }
        public function flush($model): void {}
        public function createIndex($name, array $options = []) {}
        public function deleteIndex($name) {}
    };
}

beforeEach(function () {
    config([
        'scout.driver' => 'fake-phase2',
        'copiloto.memoria.meilisearch.embedder'       => 'openai',
        'copiloto.memoria.meilisearch.semantic_ratio' => 0.7,
        'copiloto.hyde.enabled'           => false,
        'copiloto.reranker.enabled'       => false,
        'copiloto.negative_cache.enabled' => false,
    ]);

    $engine = fakeEngineVazia();
    app(EngineManager::class)->extend('fake-phase2', fn () => $engine);
});

// ── Negative Cache ──────────────────────────────────────────────────────────

it('NegativeCacheService: desabilitado nunca bloqueia', function () {
    config(['copiloto.negative_cache.enabled' => false]);
    Cache::shouldReceive('has')->never();

    $svc = new NegativeCacheService();
    expect($svc->ehNegativo(4, 12, 'qualquer query'))->toBeFalse();
});

it('NegativeCacheService: marcarNegativo + ehNegativo com cache real (array driver)', function () {
    config([
        'cache.default'                     => 'array',
        'copiloto.negative_cache.enabled'   => true,
        'copiloto.negative_cache.ttl_segundos' => 300,
    ]);

    $svc = new NegativeCacheService();

    expect($svc->ehNegativo(4, 12, 'quanto vendi?'))->toBeFalse();

    $svc->marcarNegativo(4, 12, 'quanto vendi?');

    expect($svc->ehNegativo(4, 12, 'quanto vendi?'))->toBeTrue();
});

it('NegativeCacheService: normaliza query antes de cache (pontuação, case)', function () {
    config([
        'cache.default'                     => 'array',
        'copiloto.negative_cache.enabled'   => true,
        'copiloto.negative_cache.ttl_segundos' => 300,
    ]);

    $svc = new NegativeCacheService();
    $svc->marcarNegativo(4, 12, 'Quanto vendi?!');

    // Versão sem pontuação/case diferente deve bater na mesma chave
    expect($svc->ehNegativo(4, 12, 'quanto vendi'))->toBeTrue();
});

it('NegativeCacheService: escoped por business_id (não vaza entre tenants)', function () {
    config([
        'cache.default'                     => 'array',
        'copiloto.negative_cache.enabled'   => true,
        'copiloto.negative_cache.ttl_segundos' => 300,
    ]);

    $svc = new NegativeCacheService();
    $svc->marcarNegativo(4, 12, 'meta do mês');

    // biz=1 NÃO deve ser bloqueado
    expect($svc->ehNegativo(1, 12, 'meta do mês'))->toBeFalse();
    expect($svc->ehNegativo(4, 99, 'meta do mês'))->toBeFalse();
});

// ── buscar() com negative cache ativo ───────────────────────────────────────

it('buscar retorna [] imediato se negative cache hit (sem tocar Scout)', function () {
    config([
        'cache.default'                     => 'array',
        'copiloto.negative_cache.enabled'   => true,
        'copiloto.negative_cache.ttl_segundos' => 300,
    ]);

    // Pré-marca negativo
    (new NegativeCacheService())->marcarNegativo(4, 12, 'query sem resultado');

    // Substitui engine por spy que falha se chamada
    $spyEngine = new class extends Engine {
        public function update($models): void {}
        public function delete($models): void {}
        public function search(Builder $builder) { throw new \RuntimeException('Scout NÃO deveria ser chamado'); }
        public function paginate(Builder $builder, $perPage, $page) { return $this->search($builder); }
        public function mapIds($results) { return collect(); }
        public function map(Builder $builder, $results, $model) { return collect(); }
        public function lazyMap(Builder $builder, $results, $model) { return \Illuminate\Support\LazyCollection::empty(); }
        public function getTotalCount($results) { return 0; }
        public function flush($model): void {}
        public function createIndex($name, array $options = []) {}
        public function deleteIndex($name) {}
    };
    app(EngineManager::class)->extend('fake-phase2', fn () => $spyEngine);

    $result = (new MeilisearchDriver())->buscar(4, 12, 'query sem resultado', 5);
    expect($result)->toBeEmpty();
});

it('buscar com zero resultados marca negative cache pra próxima vez', function () {
    config([
        'cache.default'                     => 'array',
        'copiloto.negative_cache.enabled'   => true,
        'copiloto.negative_cache.ttl_segundos' => 300,
    ]);

    // Engine já retorna vazia (configurado no beforeEach)
    (new MeilisearchDriver())->buscar(4, 12, 'fato inexistente', 5);

    // Agora deve estar marcado
    expect((new NegativeCacheService())->ehNegativo(4, 12, 'fato inexistente'))->toBeTrue();
});

// ── HyDE ────────────────────────────────────────────────────────────────────

it('buscar com hyde desabilitado usa só query original (sem chamada HyDE)', function () {
    config(['copiloto.hyde.enabled' => false]);

    $hyde = Mockery::mock(HydeQueryExpander::class);
    // expandir DEVE ser chamado (delegamos pra ele decidir), mas ele retorna só [query]
    $hyde->shouldReceive('expandir')
         ->once()
         ->with('meta de faturamento')
         ->andReturn(['meta de faturamento']);
    app()->instance(HydeQueryExpander::class, $hyde);

    (new MeilisearchDriver())->buscar(4, 12, 'meta de faturamento', 5);
});

it('buscar com hyde habilitado chama expandir e aceita doc hipotetico', function () {
    config(['copiloto.hyde.enabled' => true]);

    $hyde = Mockery::mock(HydeQueryExpander::class);
    $hyde->shouldReceive('expandir')
         ->once()
         ->andReturn(['meta de faturamento', 'Faturamento mensal meta R$ X, vendas acumuladas']);
    app()->instance(HydeQueryExpander::class, $hyde);

    // Nenhuma exceção = 2 iterações de search completaram
    $result = (new MeilisearchDriver())->buscar(4, 12, 'meta de faturamento', 5);
    expect($result)->toBeArray();
});

// ── Reranker ─────────────────────────────────────────────────────────────────

it('buscar com reranker desabilitado não chama reranquear', function () {
    config(['copiloto.reranker.enabled' => false]);

    $reranker = Mockery::mock(LlmReranker::class);
    $reranker->shouldNotReceive('reranquear');
    app()->instance(LlmReranker::class, $reranker);

    (new MeilisearchDriver())->buscar(4, 12, 'qualquer', 5);
});

it('buscar com reranker ativo solicita fetchK=topK*2 ao Scout', function () {
    config(['copiloto.reranker.enabled' => true]);

    $captured = null;
    $engine   = new class ($captured) extends Engine {
        public function __construct(public &$lastLimit) {}
        public function update($models): void {}
        public function delete($models): void {}
        public function search(Builder $builder)
        {
            if ($builder->callback) {
                $idx = new class ($this) {
                    public function __construct(private readonly mixed $outer) {}
                    public function search(string $q, array $p): object
                    {
                        $this->outer->lastLimit = $p['limit'] ?? null;
                        return (object) ['hits' => [], 'estimatedTotalHits' => 0];
                    }
                };
                call_user_func($builder->callback, $idx, $builder->query, []);
            }
            return (object) ['hits' => [], 'estimatedTotalHits' => 0];
        }
        public function paginate(Builder $b, $pp, $p) { return $this->search($b); }
        public function mapIds($r) { return collect(); }
        public function map(Builder $b, $r, $m) { return collect(); }
        public function lazyMap(Builder $b, $r, $m) { return \Illuminate\Support\LazyCollection::empty(); }
        public function getTotalCount($r) { return 0; }
        public function flush($m): void {}
        public function createIndex($n, array $o = []) {}
        public function deleteIndex($n) {}
    };
    app(EngineManager::class)->extend('fake-phase2', fn () => $engine);

    (new MeilisearchDriver())->buscar(4, 12, 'qualquer', 5);

    // fetchK deve ser 5 * 2 = 10
    expect($engine->lastLimit)->toBe(10);
});

// ── Regressão Phase 1 ────────────────────────────────────────────────────────

it('regressão: todos os enhancers desabilitados mantém hybrid params idênticos ao Phase 1', function () {
    config([
        'copiloto.hyde.enabled'           => false,
        'copiloto.reranker.enabled'       => false,
        'copiloto.negative_cache.enabled' => false,
        'copiloto.memoria.meilisearch.embedder'       => 'openai',
        'copiloto.memoria.meilisearch.semantic_ratio' => 0.7,
    ]);

    $capturedParams = null;
    $engine = new class ($capturedParams) extends Engine {
        public function __construct(public &$params) {}
        public function update($models): void {}
        public function delete($models): void {}
        public function search(Builder $builder)
        {
            if ($builder->callback) {
                $idx = new class ($this) {
                    public function __construct(private readonly mixed $outer) {}
                    public function search(string $q, array $p): object
                    {
                        $this->outer->params = $p;
                        return (object) ['hits' => [], 'estimatedTotalHits' => 0];
                    }
                };
                call_user_func($builder->callback, $idx, $builder->query, []);
            }
            return (object) ['hits' => [], 'estimatedTotalHits' => 0];
        }
        public function paginate(Builder $b, $pp, $p) { return $this->search($b); }
        public function mapIds($r) { return collect(); }
        public function map(Builder $b, $r, $m) { return collect(); }
        public function lazyMap(Builder $b, $r, $m) { return \Illuminate\Support\LazyCollection::empty(); }
        public function getTotalCount($r) { return 0; }
        public function flush($m): void {}
        public function createIndex($n, array $o = []) {}
        public function deleteIndex($n) {}
    };
    app(EngineManager::class)->extend('fake-phase2', fn () => $engine);

    (new MeilisearchDriver())->buscar(4, 12, 'meta de faturamento', 7);

    expect($engine->params)->toHaveKey('hybrid');
    expect($engine->params['hybrid'])->toBe(['embedder' => 'openai', 'semanticRatio' => 0.7]);
    expect($engine->params['filter'])->toBe('business_id = 4 AND user_id = 12');
    expect($engine->params['limit'])->toBe(7);
});
