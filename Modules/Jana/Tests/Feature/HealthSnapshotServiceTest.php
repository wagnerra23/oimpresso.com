<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\HealthSnapshotService;

uses(Tests\TestCase::class);

/**
 * US-COPI-097 — HealthSnapshotService devolve shape estável + agrega 24h.
 *
 * SQLite in-memory replicando schemas mínimos das 3 tabelas DB-backed
 * (failed_jobs, mcp_audit_log, jana_mensagens). `jana:health-check` é
 * invocado de verdade — em test sem todas as tabelas que o comando inspeciona,
 * cada check retorna `ok=false` via try/catch interno; o service só checa
 * que devolve um array.
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::dropIfExists('failed_jobs');
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('jana_mensagens');

    Schema::create('failed_jobs', function (Blueprint $t) {
        $t->id();
        $t->string('uuid')->unique();
        $t->text('connection');
        $t->text('queue');
        $t->longText('payload');
        $t->longText('exception');
        $t->timestamp('failed_at')->useCurrent();
    });

    Schema::create('mcp_audit_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->uuid('request_id')->unique();
        $t->unsignedInteger('user_id');
        $t->timestamp('ts')->useCurrent();
        $t->string('endpoint', 30);
        $t->string('status', 20);
        $t->decimal('custo_brl', 10, 6)->nullable();
    });

    Schema::create('jana_mensagens', function (Blueprint $t) {
        $t->id();
        $t->unsignedInteger('tokens_in')->default(0);
        $t->unsignedInteger('tokens_out')->default(0);
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('jana_mensagens');
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('failed_jobs');
});

test('snapshot retorna 5 keys top-level estáveis', function () {
    $snap = (new HealthSnapshotService)->snapshot();

    expect($snap)->toHaveKeys(['generated_at', 'health', 'queues', 'mcp', 'brain_b']);
    expect($snap['generated_at'])->toBeString();
});

test('queueStats conta apenas failed_jobs nas últimas 24h', function () {
    DB::table('failed_jobs')->insert([
        ['uuid' => 'a', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subHours(2)],
        ['uuid' => 'b', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subHours(20)],
        ['uuid' => 'c', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(5)],
    ]);

    $snap = (new HealthSnapshotService)->snapshot();

    expect($snap['queues']['available'])->toBeTrue();
    expect($snap['queues']['failed_24h'])->toBe(2);
    expect($snap['queues']['failed_total'])->toBe(3);
});

test('mcpStats agrega requests, errors e custo nas últimas 24h', function () {
    DB::table('mcp_audit_log')->insert([
        ['request_id' => '11111111-1111-1111-1111-111111111111', 'user_id' => 1, 'ts' => now()->subHours(1), 'endpoint' => 'tools/call', 'status' => 'ok', 'custo_brl' => 0.10],
        ['request_id' => '22222222-2222-2222-2222-222222222222', 'user_id' => 1, 'ts' => now()->subHours(2), 'endpoint' => 'tools/call', 'status' => 'ok', 'custo_brl' => 0.20],
        ['request_id' => '33333333-3333-3333-3333-333333333333', 'user_id' => 1, 'ts' => now()->subHours(3), 'endpoint' => 'tools/call', 'status' => 'denied', 'custo_brl' => 0],
        ['request_id' => '44444444-4444-4444-4444-444444444444', 'user_id' => 1, 'ts' => now()->subDays(2), 'endpoint' => 'tools/call', 'status' => 'ok', 'custo_brl' => 1.0],
    ]);

    $snap = (new HealthSnapshotService)->snapshot();

    expect($snap['mcp']['available'])->toBeTrue();
    expect($snap['mcp']['requests_24h'])->toBe(3);
    expect($snap['mcp']['errors_24h'])->toBe(1);
    expect($snap['mcp']['taxa_erro'])->toBe(round(1 / 3, 4));
    expect($snap['mcp']['custo_brl_24h'])->toBe(0.3);
});

test('brainBStats calcula custo gpt-4o-mini com pricing canônico', function () {
    DB::table('jana_mensagens')->insert([
        ['tokens_in' => 1_000_000, 'tokens_out' => 500_000, 'created_at' => now()->subHours(1), 'updated_at' => now()],
        ['tokens_in' => 500_000, 'tokens_out' => 0, 'created_at' => now()->subDays(2), 'updated_at' => now()],
    ]);

    $snap = (new HealthSnapshotService)->snapshot();

    expect($snap['brain_b']['available'])->toBeTrue();
    expect($snap['brain_b']['tokens_in_24h'])->toBe(1_000_000);
    expect($snap['brain_b']['tokens_out_24h'])->toBe(500_000);
    // 1M * 0.15/1M + 500k * 0.60/1M = 0.15 + 0.30 = 0.45 USD * 5 BRL = 2.25
    expect($snap['brain_b']['custo_brl_24h'])->toBe(2.25);
});

test('snapshot degrada graciosamente quando tabelas faltam', function () {
    Schema::dropIfExists('failed_jobs');
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('jana_mensagens');

    $snap = (new HealthSnapshotService)->snapshot();

    expect($snap['queues'])->toBe(['available' => false]);
    expect($snap['mcp'])->toBe(['available' => false]);
    expect($snap['brain_b'])->toBe(['available' => false]);
});
