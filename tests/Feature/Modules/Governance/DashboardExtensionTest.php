<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-COPI-098 (extensão /governance Dashboard) — 3 fontes de saúde do ecossistema.
 *
 * SQLite in-memory replicando schemas mínimos. Cobre:
 *   - 3 KPIs novos (failed_jobs_24h, custo_ia_brl_24h, last_narrative)
 *   - Lista narratives top 5
 *   - Degradação graciosa quando tabelas faltam
 *   - Janela 24h (registros antigos não contam)
 */

beforeEach(function () {
    // era-sqlite: cria schema manual (sqlite-friendly). No MySQL persistente do nightly
    // isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é na lane
    // sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::dropIfExists('jana_health_narratives');
    Schema::dropIfExists('jana_mensagens');
    Schema::dropIfExists('failed_jobs');

    Schema::create('failed_jobs', function (Blueprint $t) {
        $t->id();
        $t->string('uuid')->unique();
        $t->text('connection');
        $t->text('queue');
        $t->longText('payload');
        $t->longText('exception');
        $t->timestamp('failed_at')->useCurrent();
    });

    Schema::create('jana_mensagens', function (Blueprint $t) {
        $t->id();
        $t->unsignedInteger('tokens_in')->default(0);
        $t->unsignedInteger('tokens_out')->default(0);
        $t->timestamps();
    });

    Schema::create('jana_health_narratives', function (Blueprint $t) {
        $t->id();
        $t->timestamp('generated_at')->index();
        $t->string('severity', 20)->default('info')->index();
        $t->text('narrative');
        $t->string('snapshot_hash', 64)->index();
        $t->string('model', 50)->default('gpt-4o-mini');
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->decimal('custo_brl', 10, 6)->nullable();
        $t->json('payload_summary')->nullable();
        $t->timestamp('created_at')->useCurrent();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('jana_health_narratives');
    Schema::dropIfExists('jana_mensagens');
    Schema::dropIfExists('failed_jobs');
});

/**
 * Reflete acesso aos métodos privados via reflexão. Pra teste isolado de
 * cada fonte sem precisar montar Inertia + auth + middleware da rota completa.
 */
function controllerInstance(): \Modules\Governance\Http\Controllers\DashboardController
{
    return new \Modules\Governance\Http\Controllers\DashboardController;
}

if (! function_exists('invokePrivate')) {
    function invokePrivate(object $obj, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($obj, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($obj, $args);
    }
}

test('failedJobs24h conta apenas registros recentes', function () {
    DB::table('failed_jobs')->insert([
        ['uuid' => 'a', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subHours(2)],
        ['uuid' => 'b', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subHours(20)],
        ['uuid' => 'c', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(5)],
    ]);

    $count = invokePrivate(controllerInstance(), 'failedJobs24h');

    expect($count)->toBe(2);
});

test('failedJobs24h retorna null quando tabela ausente', function () {
    Schema::dropIfExists('failed_jobs');

    expect(invokePrivate(controllerInstance(), 'failedJobs24h'))->toBeNull();
});

test('custoIa24h aplica pricing canônico gpt-4o-mini', function () {
    DB::table('jana_mensagens')->insert([
        ['tokens_in' => 1_000_000, 'tokens_out' => 500_000, 'created_at' => now()->subHours(1), 'updated_at' => now()],
        ['tokens_in' => 500_000, 'tokens_out' => 0, 'created_at' => now()->subDays(2), 'updated_at' => now()],
    ]);

    $custo = invokePrivate(controllerInstance(), 'custoIa24h');

    // 1M * 0.15/1M + 500k * 0.60/1M = 0.45 USD * 5 BRL = 2.25
    expect($custo)->toBe(2.25);
});

test('custoIa24h retorna null quando jana_mensagens ausente', function () {
    Schema::dropIfExists('jana_mensagens');

    expect(invokePrivate(controllerInstance(), 'custoIa24h'))->toBeNull();
});

test('ultimaNarrativa retorna shape com severity, message e generated_at', function () {
    DB::table('jana_health_narratives')->insert([
        ['severity' => 'warning', 'narrative' => 'Custo Brain B subiu 3x', 'snapshot_hash' => str_repeat('a', 64), 'generated_at' => now()->subMinutes(30)],
        ['severity' => 'info', 'narrative' => 'Tudo OK', 'snapshot_hash' => str_repeat('b', 64), 'generated_at' => now()->subHours(2)],
    ]);

    $row = invokePrivate(controllerInstance(), 'ultimaNarrativa');

    expect($row)->toBeArray()
        ->and($row['severity'])->toBe('warning')
        ->and($row['message'])->toBe('Custo Brain B subiu 3x');
});

test('ultimaNarrativa retorna null quando jana_health_narratives ausente', function () {
    Schema::dropIfExists('jana_health_narratives');

    expect(invokePrivate(controllerInstance(), 'ultimaNarrativa'))->toBeNull();
});

test('narrativasRecentes pega só últimas 5 das 24h', function () {
    foreach (range(1, 8) as $i) {
        DB::table('jana_health_narratives')->insert([
            'severity' => 'info',
            'narrative' => "Narrativa {$i}",
            'snapshot_hash' => str_repeat((string) $i, 64),
            'generated_at' => now()->subMinutes($i * 30),
        ]);
    }

    DB::table('jana_health_narratives')->insert([
        'severity' => 'info',
        'narrative' => 'Antiga',
        'snapshot_hash' => str_repeat('z', 64),
        'generated_at' => now()->subDays(2),
    ]);

    $recentes = invokePrivate(controllerInstance(), 'narrativasRecentes');

    expect($recentes)->toHaveCount(5)
        ->and($recentes[0]['narrative'])->toBe('Narrativa 1');
});

test('narrativasRecentes retorna array vazio quando jana_health_narratives ausente', function () {
    Schema::dropIfExists('jana_health_narratives');

    expect(invokePrivate(controllerInstance(), 'narrativasRecentes'))->toBe([]);
});
