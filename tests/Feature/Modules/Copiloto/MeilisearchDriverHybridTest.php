<?php

use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Modules\Copiloto\Entities\CopilotoMemoriaFato;
use Modules\Copiloto\Services\Memoria\MeilisearchDriver;

/**
 * Regressão MEM-HOT-1 (ADR 0047) — MeilisearchDriver::buscar passa
 * 'hybrid:{embedder, semanticRatio}' + 'filter' string ao engine.
 *
 * O bug original: `Model::search($q)->where()->get()` usava Scout default
 * = full-text only (sem embedder), recall=0 mesmo com fato indexado.
 *
 * Estratégia do teste: bind um engine fake e capturar o callback do Builder
 * pra inspecionar os params que ele monta antes de chamar $index->search().
 */

beforeEach(function () {
    config([
        'scout.driver' => 'fake-meili',
        'copiloto.memoria.meilisearch.embedder'       => 'openai',
        'copiloto.memoria.meilisearch.semantic_ratio' => 0.7,
    ]);
});

it('buscar passa hybrid:{embedder,semanticRatio} + filter business_id/user_id ao engine Meilisearch', function () {
    $capturedParams = null;
    $capturedQuery  = null;

    // Engine fake que captura os params via callback Scout
    $fakeEngine = new class($capturedParams, $capturedQuery) extends Engine {
        public function __construct(public &$params, public &$query) {}

        public function update($models): void {}

        public function delete($models): void {}

        public function search(Builder $builder)
        {
            // Simula MeilisearchEngine::performSearch invocando o callback
            // com (Indexes $index, string $query, array $options).
            $fakeIndex = new class {
                public array $lastParams = [];

                public function search(string $q, array $params)
                {
                    $this->lastParams = $params;

                    // Retorno mínimo compatível com o que MeilisearchEngine
                    // espera (mas Scout->get() vai chamar mapIds/map também).
                    return (object) ['hits' => [], 'estimatedTotalHits' => 0];
                }
            };

            if ($builder->callback) {
                $result = call_user_func($builder->callback, $fakeIndex, $builder->query, []);
                $this->params = $fakeIndex->lastParams;
                $this->query  = $builder->query;
                return $result;
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

    app(EngineManager::class)->extend('fake-meili', fn () => $fakeEngine);

    $driver = new MeilisearchDriver();
    $driver->buscar(businessId: 4, userId: 12, query: 'meta de faturamento', topK: 7);

    expect($fakeEngine->query)->toBe('meta de faturamento');
    expect($fakeEngine->params)->toHaveKey('hybrid');
    expect($fakeEngine->params['hybrid'])->toBe([
        'embedder'      => 'openai',
        'semanticRatio' => 0.7,
    ]);
    expect($fakeEngine->params)->toHaveKey('filter');
    expect($fakeEngine->params['filter'])->toBe('business_id = 4 AND user_id = 12');
    expect($fakeEngine->params['limit'])->toBe(7);
});

it('buscar respeita override de embedder/ratio via config', function () {
    config([
        'copiloto.memoria.meilisearch.embedder'       => 'meu-embedder-custom',
        'copiloto.memoria.meilisearch.semantic_ratio' => 0.3,
    ]);

    $captured = null;
    $fakeEngine = new class($captured) extends Engine {
        public function __construct(public &$params) {}
        public function update($models): void {}
        public function delete($models): void {}
        public function search(Builder $builder)
        {
            $fakeIndex = new class { public array $p = []; public function search(string $q, array $p) { $this->p = $p; return (object) ['hits' => []]; } };
            if ($builder->callback) {
                call_user_func($builder->callback, $fakeIndex, $builder->query, []);
                $this->params = $fakeIndex->p;
            }
            return (object) ['hits' => []];
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

    app(EngineManager::class)->extend('fake-meili', fn () => $fakeEngine);

    (new MeilisearchDriver())->buscar(4, 12, 'qualquer', 5);

    expect($fakeEngine->params['hybrid']['embedder'])->toBe('meu-embedder-custom');
    expect($fakeEngine->params['hybrid']['semanticRatio'])->toBe(0.3);
});
