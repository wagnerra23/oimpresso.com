<?php

declare(strict_types=1);

use App\Services\Evolution\SchemaGuard;
use Illuminate\Support\Facades\Schema;
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
});

it('reporta missing quando nenhuma tabela existe', function () {
    $check = SchemaGuard::check();

    expect($check['ready'])->toBeFalse()
        ->and($check['missing'])->toHaveCount(6)
        ->and($check['missing'])->toContain('vizra_agents', 'vizra_memory_chunks')
        ->and($check['hint'])->toContain('migrate');
});

it('reporta ready quando todas as 6 tabelas existem', function () {
    InitVizraSchema::run();

    $check = SchemaGuard::check();

    expect($check['ready'])->toBeTrue()
        ->and($check['missing'])->toBeEmpty()
        ->and($check['hint'])->toBe('');

    InitVizraSchema::tearDown();
});

it('detecta uma tabela faltando depois de drop', function () {
    InitVizraSchema::run();
    Schema::dropIfExists('vizra_traces');

    $check = SchemaGuard::check();

    expect($check['ready'])->toBeFalse()
        ->and($check['missing'])->toBe(['vizra_traces']);

    InitVizraSchema::tearDown();
});

it('evolution:index falha gracioso quando tabelas faltam', function () {
    $exit = $this->artisan('evolution:index')
        ->expectsOutputToContain('Schema vizra_* incompleto')
        ->run();

    expect($exit)->toBe(1);
});
