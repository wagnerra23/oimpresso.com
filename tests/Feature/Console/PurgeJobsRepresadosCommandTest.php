<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * jobs:purge-represados — purge de backlog de filas sem worker (audit 2026-07-02).
 *
 * Contrato (REGRA MESTRE memory/proibicoes.md — cálculo/estoque não aplica,
 * mas DELETE em prod aplica o espírito): dry-run é DEFAULT, DELETE exige
 * --execute explícito, filas com worker vivo são recusadas.
 */

beforeEach(function () {
    if (! Schema::hasTable('jobs')) {
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
    }

    DB::table('jobs')->where('queue', 'like', 'test-purge-%')->delete();
});

afterEach(function () {
    DB::table('jobs')->where('queue', 'like', 'test-purge-%')->delete();
});

function insereJobTeste(string $queue, int $createdAt, string $classe = 'App\\Jobs\\FakeJob'): void
{
    DB::table('jobs')->insert([
        'queue' => $queue,
        'payload' => json_encode(['uuid' => uniqid(), 'displayName' => $classe]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => $createdAt,
        'created_at' => $createdAt,
    ]);
}

it('recusa purgar filas protegidas com worker ativo', function () {
    $this->artisan('jobs:purge-represados', ['--queue' => ['whatsapp']])
        ->expectsOutputToContain('Filas protegidas')
        ->assertExitCode(1);

    $this->artisan('jobs:purge-represados', ['--queue' => ['whatsapp-history', 'test-purge-a']])
        ->assertExitCode(1);
});

it('dry-run (default) conta mas NÃO deleta', function () {
    insereJobTeste('test-purge-a', now()->subDays(10)->timestamp);
    insereJobTeste('test-purge-a', now()->subDays(5)->timestamp);

    $this->artisan('jobs:purge-represados', ['--queue' => ['test-purge-a']])
        ->expectsOutputToContain('DRY-RUN')
        ->assertExitCode(0);

    expect(DB::table('jobs')->where('queue', 'test-purge-a')->count())->toBe(2);
});

it('--execute deleta só a fila alvo, respeitando o cutoff --before', function () {
    insereJobTeste('test-purge-a', now()->subDays(10)->timestamp); // antes do cutoff → deleta
    insereJobTeste('test-purge-a', now()->subDay()->timestamp);    // depois do cutoff → fica
    insereJobTeste('test-purge-b', now()->subDays(10)->timestamp); // outra fila → fica

    $this->artisan('jobs:purge-represados', [
        '--queue' => ['test-purge-a'],
        '--before' => now()->subDays(2)->format('Y-m-d'),
        '--execute' => true,
    ])->assertExitCode(0);

    expect(DB::table('jobs')->where('queue', 'test-purge-a')->count())->toBe(1)
        ->and(DB::table('jobs')->where('queue', 'test-purge-b')->count())->toBe(1);
});
