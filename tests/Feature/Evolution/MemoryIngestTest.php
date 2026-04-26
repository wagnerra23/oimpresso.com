<?php

declare(strict_types=1);

use App\Models\Evolution\MemoryChunk;
use App\Services\Evolution\Embeddings\CosineSimilarity;
use App\Services\Evolution\Embeddings\HashEmbeddingDriver;
use App\Services\Evolution\MemoryIngest;
use Tests\Feature\Evolution\InitVizraSchema;

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => false,
        ],
    ]);
    \DB::purge('sqlite');
    InitVizraSchema::run();
});

afterEach(function () {
    InitVizraSchema::tearDown();
});

it('CosineSimilarity vetores idênticos = 1.0', function () {
    $v = [0.1, 0.2, 0.3, 0.4];
    expect(CosineSimilarity::compute($v, $v))->toEqualWithDelta(1.0, 0.0001);
});

it('CosineSimilarity vetores ortogonais = 0.0', function () {
    expect(CosineSimilarity::compute([1, 0, 0], [0, 1, 0]))->toEqualWithDelta(0.0, 0.0001);
});

it('HashEmbeddingDriver é determinístico (mesmo input → mesmo vetor)', function () {
    $d = new HashEmbeddingDriver(dimensions: 32);

    [$a] = $d->embed(['Financeiro Onda 2 backfill']);
    [$b] = $d->embed(['Financeiro Onda 2 backfill']);

    expect($a)->toBe($b);
});

it('HashEmbeddingDriver vetores diferentes pra textos diferentes', function () {
    $d = new HashEmbeddingDriver(dimensions: 64);

    [$a, $b] = $d->embed(['Financeiro', 'PontoWr2']);

    expect($a)->not->toBe($b);
    expect(CosineSimilarity::compute($a, $b))->toBeLessThan(1.0);
});

it('MemoryIngest persiste embedding como binário e roundtrip via getEmbeddingVector', function () {
    $service = new MemoryIngest(
        memoryPath: base_path('tests/fixtures/memory-fake'),
        driver: new HashEmbeddingDriver(dimensions: 16),
    );

    $service->run();

    $chunk = MemoryChunk::query()->whereNotNull('embedding')->first();

    expect($chunk)->not->toBeNull();

    $vector = $chunk->getEmbeddingVector();
    expect($vector)->toBeArray()
        ->and(count($vector))->toBe(16);

    foreach ($vector as $v) {
        expect($v)->toBeFloat();
    }
});
