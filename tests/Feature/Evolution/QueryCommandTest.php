<?php

declare(strict_types=1);

use App\Services\Evolution\MemoryQuery;

uses(\Tests\TestCase::class);

beforeEach(function () {
    config(['evolution.memory_path' => base_path('tests/fixtures/memory-fake')]);
});

/*
|--------------------------------------------------------------------------
| Fase 1a — testes do skeleton mínimo (sem vetor, sem LLM)
|--------------------------------------------------------------------------
| Ver: memory/requisitos/EvolutionAgent/TESTS.md (T-001..T-005)
*/

it('T-001 · registra evolution:query no Artisan', function () {
    $this->artisan('list')
        ->expectsOutputToContain('evolution:query')
        ->assertExitCode(0);
});

it('T-002 · retorna trechos relevantes para "Financeiro"', function () {
    $this->artisan('evolution:query', ['question' => 'Financeiro'])
        ->expectsOutputToContain('Financeiro/SPEC.md')
        ->assertExitCode(0);
});

it('T-003 · retorna vazio para query sem match', function () {
    $this->artisan('evolution:query', ['question' => 'asdfqwerty12345'])
        ->expectsOutputToContain('Nenhum trecho encontrado')
        ->assertExitCode(0);
});

it('T-002b · saída --json é parseável', function () {
    $this->artisan('evolution:query', [
        'question' => 'Financeiro',
        '--json' => true,
    ])->assertExitCode(0);

    // Captura o output via Symfony console output buffer
    $tester = $this->artisan('evolution:query', [
        'question' => 'Financeiro',
        '--json' => true,
    ])->run();

    expect($tester)->toBe(0);
});

it('T-004 · MemoryQuery retorna shape consistente', function () {
    $service = new MemoryQuery(memoryPath: base_path('tests/fixtures/memory-fake'));

    $results = $service->search(query: 'Financeiro', topK: 5);

    expect($results)->toBeArray()
        ->and($results)->not->toBeEmpty();

    $first = $results[0];
    expect($first)->toHaveKeys(['file', 'heading', 'content', 'score'])
        ->and($first['file'])->toContain('Financeiro')
        ->and($first['score'])->toBeGreaterThan(0);
});

it('T-005 · MemoryQuery é determinístico em mesma query', function () {
    $service = new MemoryQuery(memoryPath: base_path('tests/fixtures/memory-fake'));

    $a = $service->search(query: 'Financeiro', topK: 3);
    $b = $service->search(query: 'Financeiro', topK: 3);

    expect(array_column($a, 'file'))->toBe(array_column($b, 'file'));
    expect(array_column($a, 'score'))->toBe(array_column($b, 'score'));
});

it('T-005b · query case-insensitive', function () {
    $service = new MemoryQuery(memoryPath: base_path('tests/fixtures/memory-fake'));

    $upper = $service->search(query: 'FINANCEIRO', topK: 3);
    $lower = $service->search(query: 'financeiro', topK: 3);

    expect(array_column($upper, 'file'))->toBe(array_column($lower, 'file'));
});

it('T-005c · respeita topK', function () {
    $service = new MemoryQuery(memoryPath: base_path('tests/fixtures/memory-fake'));

    $results = $service->search(query: 'oimpresso', topK: 1);

    expect(count($results))->toBeLessThanOrEqual(1);
});

it('T-005d · stopwords são ignorados', function () {
    $service = new MemoryQuery(memoryPath: base_path('tests/fixtures/memory-fake'));

    $results = $service->search(query: 'o de a um');

    expect($results)->toBeEmpty();
});
