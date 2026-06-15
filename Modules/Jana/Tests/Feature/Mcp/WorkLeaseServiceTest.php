<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\WorkLease\WorkLeaseService;

uses(Tests\TestCase::class);

/**
 * D1 (ADR 0278 / proposal #2766) — mcp_work_leases + WorkLeaseService.
 *
 * GUARD tests do invariante de coordenação anti-vazamento:
 *   1. Schema: UNIQUE(task_id, active_marker) enforça 1 lease ATIVO por task
 *   2. claim() adquire lease quando livre
 *   3. claim() por OUTRO principal numa task ativa → conflito 'held' (não estoura)
 *   4. claim() pelo MESMO principal → renova (heartbeat), não conflito
 *   5. release() libera → re-claim por outro funciona
 *   6. sweepExpired() devolve lease estourado ao pool (TTL é do Service, não do schema)
 *
 * Padrão era-sqlite (schema sintético inline + skip não-sqlite), igual a
 * ChannelUserAccessTest: a lane per-PR roda sqlite :memory:, e RefreshDatabase
 * (suite inteira) inclui migrations MySQL-only — então monta-se só a tabela do D1.
 *
 * @see Modules/Jana/Database/Migrations/2026_06_15_140000_create_mcp_work_leases_table.php
 * @see memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente (floor SDD).');
    }

    Schema::dropIfExists('mcp_work_leases');

    Schema::create('mcp_work_leases', function ($table) {
        $table->bigIncrements('id');
        $table->string('task_id', 40);
        $table->string('human_principal', 60);
        $table->string('agent_id', 80)->nullable();
        $table->string('claude_code_session', 64)->nullable();
        $table->timestamp('acquired_at')->useCurrent();
        $table->timestamp('heartbeat_at')->useCurrent();
        $table->timestamp('expires_at');
        $table->timestamp('released_at')->nullable();
        $table->timestamps();
        $table->index('task_id', 'mwl_task_idx');
        $table->index('expires_at', 'mwl_expires_idx');
    });
    Schema::table('mcp_work_leases', function ($table) {
        $table->integer('active_marker')
            ->virtualAs('case when released_at is null then 1 else null end')
            ->nullable();
    });
    Schema::table('mcp_work_leases', function ($table) {
        $table->unique(['task_id', 'active_marker'], 'mwl_active_lease_unq');
    });
});

afterEach(function () {
    Schema::dropIfExists('mcp_work_leases');
});

function insertActiveLease(string $taskId, string $principal): void
{
    DB::table('mcp_work_leases')->insert([
        'task_id' => $taskId,
        'human_principal' => $principal,
        'acquired_at' => now(),
        'heartbeat_at' => now(),
        'expires_at' => now()->addMinutes(30),
        'released_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('schema enforça no máx 1 lease ativo por task (UNIQUE active_marker)', function () {
    insertActiveLease('US-GOV-022', 'wagner');

    expect(fn () => insertActiveLease('US-GOV-022', 'eliana'))
        ->toThrow(QueryException::class);
})->group('work-lease', 'ci');

it('liberados coexistem e permitem re-claim (NULLs distintos no UNIQUE)', function () {
    insertActiveLease('US-GOV-022', 'wagner');
    DB::table('mcp_work_leases')->where('task_id', 'US-GOV-022')->update(['released_at' => now()]);

    // Segundo lease ativo após release NÃO colide (marker do liberado = NULL).
    insertActiveLease('US-GOV-022', 'eliana');

    expect(DB::table('mcp_work_leases')->where('task_id', 'US-GOV-022')->count())->toBe(2);
})->group('work-lease', 'ci');

it('claim adquire lease quando a task está livre', function () {
    $svc = app(WorkLeaseService::class);
    $res = $svc->claim('US-GOV-022', 'wagner', 'claude-code');

    expect($res['ok'])->toBeTrue()
        ->and($res['lease']->human_principal)->toBe('wagner')
        ->and($res['lease']->agent_id)->toBe('claude-code');
})->group('work-lease', 'ci');

it('claim com sessão setada grava claude_code_session (LEASE-HABITO — TasksClaimTool propaga)', function () {
    // dim 4 (prep): a TasksClaimTool agora lê X-Claude-Code-Session da HTTP Request
    // e propaga como 4º arg do claim(). Este guard prova o contrato do Service que a
    // tool usa: $session → coluna claude_code_session (gera o PRIMEIRO sinal real da
    // Raia C/reconciler). Não muda a chave de conflito (C1, signal-gated).
    $svc = app(WorkLeaseService::class);
    $res = $svc->claim('US-GOV-022', 'wagner', 'claude-code', 'sess-abc123');

    expect($res['ok'])->toBeTrue()
        ->and($res['lease']->claude_code_session)->toBe('sess-abc123')
        ->and(DB::table('mcp_work_leases')
            ->where('task_id', 'US-GOV-022')
            ->value('claude_code_session'))->toBe('sess-abc123');
})->group('work-lease', 'ci');

it('claim sem sessão deixa claude_code_session NULL (default, retrocompat)', function () {
    $svc = app(WorkLeaseService::class);
    $res = $svc->claim('US-GOV-022', 'wagner', 'claude-code');

    expect($res['ok'])->toBeTrue()
        ->and($res['lease']->claude_code_session)->toBeNull();
})->group('work-lease', 'ci');

it('claim por outro principal numa task ativa retorna conflito held (não estoura)', function () {
    $svc = app(WorkLeaseService::class);
    $svc->claim('US-GOV-022', 'wagner');

    $res = $svc->claim('US-GOV-022', 'eliana');

    expect($res['ok'])->toBeFalse()
        ->and($res['reason'])->toBe('held')
        ->and($res['holder']->human_principal)->toBe('wagner');
})->group('work-lease', 'ci');

it('claim pelo mesmo principal renova o lease (heartbeat, não conflito)', function () {
    $svc = app(WorkLeaseService::class);
    $svc->claim('US-GOV-022', 'wagner');

    $res = $svc->claim('US-GOV-022', 'wagner');

    expect($res['ok'])->toBeTrue()
        ->and($res['renewed'] ?? false)->toBeTrue()
        ->and(DB::table('mcp_work_leases')->where('task_id', 'US-GOV-022')->whereNull('released_at')->count())->toBe(1);
})->group('work-lease', 'ci');

it('release libera e permite re-claim por outro', function () {
    $svc = app(WorkLeaseService::class);
    $svc->claim('US-GOV-022', 'wagner');

    expect($svc->release('US-GOV-022', 'wagner'))->toBeTrue();

    $res = $svc->claim('US-GOV-022', 'eliana');
    expect($res['ok'])->toBeTrue()
        ->and($res['lease']->human_principal)->toBe('eliana');
})->group('work-lease', 'ci');

it('sweepExpired devolve lease estourado ao pool (TTL vive no Service)', function () {
    DB::table('mcp_work_leases')->insert([
        'task_id' => 'US-GOV-022',
        'human_principal' => 'wagner',
        'acquired_at' => now()->subHour(),
        'heartbeat_at' => now()->subHour(),
        'expires_at' => now()->subMinutes(5), // já estourou
        'released_at' => null,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $svc = app(WorkLeaseService::class);

    expect($svc->activeLeaseFor('US-GOV-022'))->toBeNull(); // fora do TTL = não-ativo

    $res = $svc->claim('US-GOV-022', 'eliana'); // sweep + re-claim
    expect($res['ok'])->toBeTrue()
        ->and($res['lease']->human_principal)->toBe('eliana');
})->group('work-lease', 'ci');

it('heartbeat de NÃO-dono é no-op (guard de posse anti-spoof)', function () {
    $svc = app(WorkLeaseService::class);
    $svc->claim('US-GOV-022', 'wagner');

    expect($svc->heartbeat('US-GOV-022', 'intruso'))->toBeFalse()
        ->and($svc->activeLeaseFor('US-GOV-022')->human_principal)->toBe('wagner');
})->group('work-lease', 'ci');

it('release de NÃO-dono é no-op (guard de posse anti-spoof)', function () {
    $svc = app(WorkLeaseService::class);
    $svc->claim('US-GOV-022', 'wagner');

    expect($svc->release('US-GOV-022', 'intruso'))->toBeFalse()
        ->and($svc->activeLeaseFor('US-GOV-022'))->not->toBeNull();
})->group('work-lease', 'ci');
