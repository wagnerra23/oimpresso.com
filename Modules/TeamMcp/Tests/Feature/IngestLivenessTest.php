<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;
use Modules\TeamMcp\Services\IngestLivenessService;

uses(Tests\TestCase::class);

/**
 * B-LIVE-CHECK (SDD · ADR 0278) — reader de liveness do ingest.
 *
 * Cobre IngestLivenessService: classificação fresh/stale/dead por idade de
 * last_ingest_at + graceful-degrade quando a tabela não existe. Complementa o
 * teste do WRITER (IngestHeartbeatTest, B-LIVE-HB).
 *
 * Estratégia era-sqlite sintética (espelha IngestHeartbeatTest::ensureHeartbeatTable):
 * monta só mcp_ingest_heartbeat sob demanda; sem RefreshDatabase/migrate:fresh.
 * Carbon::setTestNow crava os limiares (sem flake de relógio). 100% sqlite — fica na
 * lane rápida do ci.yml (NÃO jana-pest.yml: nenhuma feature MySQL-only).
 *
 * @see \Modules\TeamMcp\Services\IngestLivenessService
 */
function ensureLivenessTable(): void
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

function seedHeartbeat(string $host, ?Carbon $lastIngestAt): void
{
    McpIngestHeartbeat::query()->create([
        'host'           => $host,
        'last_ingest_at' => $lastIngestAt,
        'last_session_uuid' => 'uuid-'.$host,
        'msgs_acc'       => 1,
    ]);
}

beforeEach(function () {
    // era-sqlite: tabela sintética só roda no sqlite :memory:. No MySQL persistente
    // do nightly o Schema::drop corromperia o schema compartilhado (US-GOV-021).
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: tabela sintética mcp_ingest_heartbeat só roda no sqlite');
    }
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
    ensureLivenessTable();
});

afterEach(function () {
    Carbon::setTestNow();
    if (config('database.default') !== 'sqlite') {
        return; // não dropar tabela compartilhada no MySQL persistente (US-GOV-021)
    }
    if (Schema::hasTable('mcp_ingest_heartbeat')) {
        Schema::drop('mcp_ingest_heartbeat');
    }
});

// ─── classify() — limiares determinísticos (não toca DB) ────────────────────────

it('classify: ingest recente (5min) é fresh', function () {
    expect(app(IngestLivenessService::class)->classify(now()->subMinutes(5)))->toBe('fresh');
});

it('classify: borda 15min ainda é fresh, 16min vira stale', function () {
    $svc = app(IngestLivenessService::class);
    // BITE: trocar >= por > na borda FRESH viraria o caso de 15min pra stale.
    expect($svc->classify(now()->subMinutes(15)))->toBe('fresh');
    expect($svc->classify(now()->subMinutes(16)))->toBe('stale');
});

it('classify: além do STALE_MINUTES (61min) é dead', function () {
    expect(app(IngestLivenessService::class)->classify(now()->subMinutes(61)))->toBe('dead');
});

it('classify: nunca ingeriu (null) é dead — incerteza, não falso-OK', function () {
    // BITE: enviesar null pra 'fresh'/'stale' (blind=safe) quebra aqui.
    expect(app(IngestLivenessService::class)->classify(null))->toBe('dead');
});

// ─── all() / summary() — agregação + graceful-degrade ───────────────────────────

it('summary: conta fresh/stale/dead corretos num mix de hosts', function () {
    seedHeartbeat('host-fresh', now()->subMinutes(3));
    seedHeartbeat('host-stale', now()->subMinutes(40));
    seedHeartbeat('host-dead', now()->subMinutes(120));
    seedHeartbeat('host-never', null);

    expect(app(IngestLivenessService::class)->summary())
        ->toBe(['fresh' => 1, 'stale' => 1, 'dead' => 2]);
});

it('classifyHost: host conhecido fresco é fresh; desconhecido é dead', function () {
    seedHeartbeat('host-a', now()->subMinutes(2));
    $svc = app(IngestLivenessService::class);

    expect($svc->classifyHost('host-a'))->toBe('fresh');
    expect($svc->classifyHost('host-inexistente'))->toBe('dead');
});

it('graceful-degrade: tabela ausente não estoura — all() vazio, summary zerado', function () {
    Schema::dropIfExists('mcp_ingest_heartbeat');
    $svc = app(IngestLivenessService::class);

    // BITE: remover o guard Schema::hasTable faria isto lançar QueryException.
    expect($svc->all())->toHaveCount(0);
    expect($svc->summary())->toBe(['fresh' => 0, 'stale' => 0, 'dead' => 0]);
    expect($svc->classifyHost('qualquer'))->toBe('dead');
});
