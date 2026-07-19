<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Services\Checkers\IngestLivenessChecker;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;

uses(Tests\TestCase::class);

/**
 * IngestLivenessChecker — alarme anti-SPOF do pipe MEM-CC-1 dentro do governance:audit.
 *
 * Reúsa IngestLivenessService (não duplica a régua fresh/stale/dead) e só transforma
 * o sinal em finding. era-sqlite sintético (espelha IngestLivenessTest): monta só
 * mcp_ingest_heartbeat; Carbon::setTestNow crava os limiares; skipa no MySQL
 * persistente do nightly (US-GOV-021). Nomes de helper únicos (sem colisão com
 * IngestLivenessTest::ensureLivenessTable/seedHeartbeat).
 *
 * @see Modules\Governance\Services\Checkers\IngestLivenessChecker
 */
function ensureHbTableChecker(): void
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

function seedHbChecker(string $host, ?Carbon $last): void
{
    McpIngestHeartbeat::query()->create([
        'host' => $host,
        'last_ingest_at' => $last,
        'last_session_uuid' => 'uuid-'.md5($host),
        'msgs_acc' => 1,
    ]);
}

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: tabela sintética mcp_ingest_heartbeat só roda no sqlite (US-GOV-021)');
    }
    Carbon::setTestNow(Carbon::parse('2026-07-18 12:00:00'));
    ensureHbTableChecker();
});

afterEach(function () {
    Carbon::setTestNow();
    if (config('database.default') !== 'sqlite') {
        return;
    }
    if (Schema::hasTable('mcp_ingest_heartbeat')) {
        Schema::drop('mcp_ingest_heartbeat');
    }
});

it('sem host conhecido → clean (blind ≠ dead, não cria lobo)', function () {
    $r = (new IngestLivenessChecker())->check();
    expect($r->ok)->toBeTrue();
    expect($r->drift_count)->toBe(0);
    expect($r->metadata['reason'] ?? null)->toBe('no_known_hosts');
});

it('hosts existem mas NENHUM fresco (fresh=0) → ALARMA (drifted high)', function () {
    seedHbChecker('D:\\oimpresso.com', now()->subMinutes(120)); // dead (>60min)
    seedHbChecker('/home/felipe/oimpresso', now()->subMinutes(40)); // stale (15..60min)

    $r = (new IngestLivenessChecker())->check();
    expect($r->ok)->toBeFalse();
    expect($r->drift_count)->toBe(1);
    $f = $r->findings[0];
    expect($f->severity)->toBe('high');
    expect($f->target)->toBe('cc_ingest_pipeline');
    expect($f->message)->toContain('fresh=0');
});

it('pelo menos 1 host fresco → clean (pipe vivo)', function () {
    seedHbChecker('D:\\oimpresso.com', now()->subMinutes(3));       // fresh (<15min)
    seedHbChecker('/home/felipe/oimpresso', now()->subMinutes(120)); // dead

    // BITE: se o checker disparasse com fresh>=1, isto viraria vermelho.
    $r = (new IngestLivenessChecker())->check();
    expect($r->ok)->toBeTrue();
    expect($r->drift_count)->toBe(0);
});

it('tabela ausente → clean (graceful-degrade, nunca lança)', function () {
    Schema::dropIfExists('mcp_ingest_heartbeat');
    $r = (new IngestLivenessChecker())->check();
    expect($r->ok)->toBeTrue();
});
