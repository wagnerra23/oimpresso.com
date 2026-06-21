<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;
use Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController;

uses(Tests\TestCase::class);

/**
 * B-LIVE-HB (SDD · ADR 0278) — Heartbeat do ingest (núcleo do fim do SPOF).
 *
 * Testa o WRITER do heartbeat (CcIngestController::bumpHeartbeat), que vive em
 * Modules/TeamMcp (dono do ingest, NÃO Jana). O reader/liveness service é tarefa
 * SEPARADA e não é coberto aqui.
 *
 * Estratégia era-sqlite sintética: a tabela real `mcp_ingest_heartbeat` é criada
 * sob demanda (Blueprint compatível sqlite) e o método protegido `bumpHeartbeat`
 * é invocado via reflection — sem depender da stack HTTP/middleware MySQL do
 * UltimatePOS (mcp.auth + SetSessionData etc.), que exige schema completo.
 *
 * Contratos:
 *   1. 1 POST (1 bump) → heartbeat com last_ingest_at recente.
 *   2. 2 POSTs do MESMO host → 1 ÚNICA linha (upsert idempotente por host) +
 *      msgs_acc acumulado.
 *
 * Tier 0 ({@see ADR 0093}): tabela SEM business_id (cross-tenant, espelha
 * mcp_cc_sessions). last_ingest_at gravado via now() em runtime (NUNCA coluna
 * gerada).
 *
 * @see Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController::bumpHeartbeat
 * @see Modules\TeamMcp\Database\Migrations\2026_06_15_100000_create_mcp_ingest_heartbeat_table.php
 */

/**
 * Cria a tabela sintética (Blueprint sqlite-friendly — sem fullText/MySQL-only).
 * Espelha as colunas da migration canônica.
 */
function ensureHeartbeatTable(): void
{
    if (Schema::hasTable('mcp_ingest_heartbeat')) {
        Schema::drop('mcp_ingest_heartbeat');
    }

    Schema::create('mcp_ingest_heartbeat', function ($t) {
        $t->bigIncrements('id');
        $t->string('host', 500)->unique();
        $t->timestamp('last_ingest_at')->nullable();
        $t->string('last_session_uuid', 36)->nullable();
        $t->unsignedBigInteger('msgs_acc')->default(0);
        $t->timestamps();
    });
}

/**
 * Invoca o método protegido CcIngestController::bumpHeartbeat via reflection.
 */
function bumpHeartbeatViaController(string $host, string $sessionUuid, int $inserted): void
{
    $controller = app(CcIngestController::class);
    $ref = new ReflectionMethod($controller, 'bumpHeartbeat');
    $ref->setAccessible(true);
    $ref->invoke($controller, $host, $sessionUuid, $inserted);
}

beforeEach(function () {
    // era-sqlite: tabela sintética só roda no sqlite :memory:. No MySQL persistente
    // do nightly o Schema::drop corromperia o schema compartilhado (US-GOV-021).
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: tabela sintética mcp_ingest_heartbeat só roda no sqlite');
    }
    ensureHeartbeatTable();
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return; // não dropar tabela compartilhada no MySQL persistente (US-GOV-021)
    }
    if (Schema::hasTable('mcp_ingest_heartbeat')) {
        Schema::drop('mcp_ingest_heartbeat');
    }
});

it('1 POST → cria heartbeat com last_ingest_at recente', function () {
    bumpHeartbeatViaController('D:\\oimpresso.com', 'uuid-aaa-111', 5);

    $hb = McpIngestHeartbeat::where('host', 'D:\\oimpresso.com')->first();

    expect($hb)->not->toBeNull();
    expect($hb->last_session_uuid)->toBe('uuid-aaa-111');
    expect((int) $hb->msgs_acc)->toBe(5);
    // last_ingest_at recente (escrito via now() em runtime, não coluna gerada).
    expect($hb->last_ingest_at)->not->toBeNull();
    expect($hb->last_ingest_at->diffInSeconds(now()))->toBeLessThan(10);
});

it('2 POSTs do mesmo host → 1 ÚNICA linha (upsert idempotente) + msgs_acc acumulado', function () {
    bumpHeartbeatViaController('D:\\oimpresso.com', 'uuid-aaa-111', 5);
    bumpHeartbeatViaController('D:\\oimpresso.com', 'uuid-bbb-222', 3);

    // Upsert por host: continua 1 linha.
    expect(DB::table('mcp_ingest_heartbeat')->count())->toBe(1);

    $hb = McpIngestHeartbeat::where('host', 'D:\\oimpresso.com')->first();
    expect($hb)->not->toBeNull();
    // last_session_uuid reflete o ÚLTIMO POST.
    expect($hb->last_session_uuid)->toBe('uuid-bbb-222');
    // msgs_acc somou 5 + 3.
    expect((int) $hb->msgs_acc)->toBe(8);
});

it('hosts distintos → linhas distintas (cross-tenant, sem colisão)', function () {
    bumpHeartbeatViaController('D:\\oimpresso.com', 'uuid-a', 2);
    bumpHeartbeatViaController('/home/felipe/oimpresso', 'uuid-b', 4);

    expect(DB::table('mcp_ingest_heartbeat')->count())->toBe(2);
});

it('host vazio é ignorado (no-op seguro)', function () {
    bumpHeartbeatViaController('', 'uuid-empty', 9);

    expect(DB::table('mcp_ingest_heartbeat')->count())->toBe(0);
});
