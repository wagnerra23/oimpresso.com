<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * GUARD do invariante de imutabilidade append-only enforçado por trigger MySQL
 * (SIGNAL SQLSTATE '45000' em BEFORE UPDATE / BEFORE DELETE).
 *
 * Cobre DUAS tabelas de governança (sem business_id — repo-wide, espelham mcp_tasks):
 *   - mcp_task_events  → triggers criados pela migration D-EVT-TRIG (este PR)
 *   - mcp_audit_log    → triggers existem desde 2026_05_05_230001 mas NUNCA tiveram
 *                        teste — esta suíte fecha a dívida (Constituição Artigo 9).
 *
 * MySQL-ONLY: triggers SIGNAL não existem no SQLite :memory: do harness per-PR;
 * por isso markTestSkipped quando o driver não é mysql/mariadb (padrão da lane
 * financeiro-pest — ver Modules/Financeiro/Tests/Feature/BackfillExtratoOfxTest.php).
 *
 * Isolamento: DatabaseTransactions (rollback) + prefixo único por run.
 * Os triggers são criados pela migração (passo `migrate` da lane). NÃO se cria
 * DDL no beforeEach — CREATE TRIGGER auto-commita e quebraria o wrapping de
 * transação do teste. Se a migração ainda não rodou, a suíte dá skip-as-pass.
 *
 * @see Modules/Jana/Database/Migrations/2026_06_15_160000_add_immutability_triggers_to_mcp_task_events.php
 * @see Modules/Jana/Database/Migrations/2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php
 * @see memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md
 */
function triggerExists(string $name): bool
{
    return DB::table('information_schema.TRIGGERS')
        ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
        ->where('TRIGGER_NAME', $name)
        ->exists();
}

beforeEach(function () {
    $driver = DB::connection()->getDriverName();
    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
        test()->markTestSkipped('MySQL-only: triggers SIGNAL SQLSTATE 45000 não existem no SQLite (lane financeiro-pest).');
    }

    if (! Schema::hasTable('mcp_task_events') || ! Schema::hasTable('mcp_audit_log')) {
        test()->markTestSkipped('Schema baseline ausente (mcp_task_events / mcp_audit_log) — rode migrate.');
    }

    foreach ([
        'trg_mcp_task_events_no_update',
        'trg_mcp_task_events_no_delete',
        'trg_mcp_audit_log_no_update',
        'trg_mcp_audit_log_no_delete',
    ] as $trg) {
        if (! triggerExists($trg)) {
            test()->markTestSkipped("Trigger {$trg} ausente — rode migrate (lane financeiro-pest aplica).");
        }
    }
});

// ─── mcp_task_events ──────────────────────────────────────────────────────────

function insertTaskEvent(string $taskId): int
{
    return DB::table('mcp_task_events')->insertGetId([
        'task_id' => $taskId,
        'event_type' => 'created',
        'from_value' => null,
        'to_value' => 'todo',
        'author' => 'system',
        'note' => 'pest guard',
        'occurred_at' => now(),
        'created_at' => now(),
    ]);
}

it('mcp_task_events: INSERT é permitido (append)', function () {
    $taskId = 'PEST-EVT-' . Str::random(8);
    $id = insertTaskEvent($taskId);

    expect($id)->toBeGreaterThan(0)
        ->and(DB::table('mcp_task_events')->where('id', $id)->exists())->toBeTrue();
})->group('immutability', 'governance', 'ci');

it('mcp_task_events: UPDATE estoura QueryException 45000 (append-only)', function () {
    $taskId = 'PEST-EVT-' . Str::random(8);
    $id = insertTaskEvent($taskId);

    try {
        DB::table('mcp_task_events')->where('id', $id)->update(['to_value' => 'doing']);
        $this->fail('UPDATE deveria ter sido bloqueado pelo trigger trg_mcp_task_events_no_update.');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('45000')
            ->and($e->getMessage())->toContain('append-only');
    }

    // Row intacta: trigger BEFORE bloqueia antes de gravar.
    expect(DB::table('mcp_task_events')->where('id', $id)->value('to_value'))->toBe('todo');
})->group('immutability', 'governance', 'ci');

it('mcp_task_events: DELETE estoura QueryException 45000 (append-only)', function () {
    $taskId = 'PEST-EVT-' . Str::random(8);
    $id = insertTaskEvent($taskId);

    try {
        DB::table('mcp_task_events')->where('id', $id)->delete();
        $this->fail('DELETE deveria ter sido bloqueado pelo trigger trg_mcp_task_events_no_delete.');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('45000')
            ->and($e->getMessage())->toContain('append-only');
    }

    expect(DB::table('mcp_task_events')->where('id', $id)->exists())->toBeTrue();
})->group('immutability', 'governance', 'ci');

// ─── mcp_audit_log (dívida histórica — nunca teve teste) ──────────────────────

function insertImmutableAuditLog(): int
{
    // user_id é FK NOT NULL → usa o primeiro user semeado pela lane (biz=1).
    $userId = (int) DB::table('users')->min('id');
    if ($userId === 0) {
        test()->markTestSkipped('Nenhum user semeado — mcp_audit_log.user_id é FK NOT NULL.');
    }

    return DB::table('mcp_audit_log')->insertGetId([
        'request_id' => (string) Str::uuid(),
        'user_id' => $userId,
        'business_id' => null,
        'ts' => now(),
        'endpoint' => 'tools/call',
        'tool_or_resource' => 'pest-guard',
        'status' => 'ok',
        'created_at' => now(),
    ]);
}

it('mcp_audit_log: INSERT é permitido (append)', function () {
    $id = insertImmutableAuditLog();

    expect($id)->toBeGreaterThan(0)
        ->and(DB::table('mcp_audit_log')->where('id', $id)->exists())->toBeTrue();
})->group('immutability', 'governance', 'ci');

it('mcp_audit_log: UPDATE estoura QueryException 45000 (append-only)', function () {
    $id = insertImmutableAuditLog();

    try {
        DB::table('mcp_audit_log')->where('id', $id)->update(['status' => 'error']);
        $this->fail('UPDATE deveria ter sido bloqueado pelo trigger trg_mcp_audit_log_no_update.');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('45000')
            ->and($e->getMessage())->toContain('append-only');
    }

    expect(DB::table('mcp_audit_log')->where('id', $id)->value('status'))->toBe('ok');
})->group('immutability', 'governance', 'ci');

it('mcp_audit_log: DELETE estoura QueryException 45000 (append-only)', function () {
    $id = insertImmutableAuditLog();

    try {
        DB::table('mcp_audit_log')->where('id', $id)->delete();
        $this->fail('DELETE deveria ter sido bloqueado pelo trigger trg_mcp_audit_log_no_delete.');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('45000')
            ->and($e->getMessage())->toContain('append-only');
    }

    expect(DB::table('mcp_audit_log')->where('id', $id)->exists())->toBeTrue();
})->group('immutability', 'governance', 'ci');
