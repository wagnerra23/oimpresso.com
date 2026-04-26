<?php

declare(strict_types=1);

use App\Models\Evolution\MemoryChunk;
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
        'evolution.memory_path' => base_path('tests/fixtures/memory-fake'),
    ]);
    \DB::purge('sqlite');
    InitVizraSchema::run();
});

afterEach(function () {
    InitVizraSchema::tearDown();
});

/*
|--------------------------------------------------------------------------
| Fase 1b — index command + ingest pipeline
|--------------------------------------------------------------------------
| Ver: memory/requisitos/EvolutionAgent/TESTS.md (T-006..T-008)
*/

it('T-006 · evolution:index registrado no artisan', function () {
    $this->artisan('list')
        ->expectsOutputToContain('evolution:index')
        ->assertExitCode(0);
});

it('T-007 · MemoryIngest cria chunks com hash + scope', function () {
    $service = new MemoryIngest(
        memoryPath: base_path('tests/fixtures/memory-fake'),
        driver: new HashEmbeddingDriver(dimensions: 64),
    );

    $stats = $service->run();

    expect($stats['indexed'])->toBeGreaterThan(0)
        ->and(MemoryChunk::count())->toBeGreaterThan(0);

    $financeiro = MemoryChunk::query()
        ->where('source_path', 'like', '%Financeiro%')
        ->first();

    expect($financeiro)->not->toBeNull()
        ->and($financeiro->scope_module)->toBe('Financeiro')
        ->and($financeiro->content_hash)->toHaveLength(64);
});

it('T-008 · MemoryIngest é idempotente em re-run', function () {
    $service = new MemoryIngest(
        memoryPath: base_path('tests/fixtures/memory-fake'),
        driver: new HashEmbeddingDriver(dimensions: 64),
    );

    $service->run();
    $count1 = MemoryChunk::count();

    $service->run();
    $count2 = MemoryChunk::count();

    expect($count2)->toBe($count1);
});

it('T-009 · scope_module extraído de path requisitos/<X>/', function () {
    $service = new MemoryIngest(
        memoryPath: base_path('tests/fixtures/memory-fake'),
        driver: new HashEmbeddingDriver(dimensions: 64),
    );

    $service->run();

    $scopes = MemoryChunk::query()
        ->whereNotNull('scope_module')
        ->pluck('scope_module')
        ->unique()
        ->values();

    expect($scopes->all())->toContain('Financeiro');
});
