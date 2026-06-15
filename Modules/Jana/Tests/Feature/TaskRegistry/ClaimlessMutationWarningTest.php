<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

/**
 * A5 (ADR 0278 Fase 2) — sinal SOFT de mutação CLAIM-LESS.
 *
 * Quando o principal do TOKEN (não o $author auto-declarado) muta uma task SEM
 * segurar o lease ativo dela, o TaskCrudService emite um McpTaskEvent de AVISO
 * (sem throw). Esse sinal acumula pro hard-gate A7 (signal-gated, ADR 0105) decidir
 * depois entre hard-block e auto-claim.
 *
 * Padrão era-sqlite (schema sintético inline + activitylog OFF), espelha
 * AcceptanceRefTest. Roda na lane sqlite per-PR.
 *
 * @see Modules/Jana/Services/TaskRegistry/TaskCrudService::applyLockedUpdate
 * @see Modules/Jana/Services/WorkLease/WorkLeaseService::activeLeaseFor
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético incompatível com MySQL persistente (floor SDD).');
    }

    config(['activitylog.enabled' => false]); // McpTask usa LogsActivity

    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_task_events');
    Schema::dropIfExists('mcp_work_leases');

    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('module', 60)->nullable();
        $t->string('title', 255)->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('owner', 60)->nullable();
        $t->string('priority', 8)->nullable();
        $t->json('blocked_by')->nullable();
        $t->string('acceptance_ref', 500)->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('completed_at')->nullable();
        $t->timestamps();
    });
    Schema::create('mcp_task_events', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('event_type', 40);
        $t->string('from_value', 255)->nullable();
        $t->string('to_value', 255)->nullable();
        $t->string('author', 60)->nullable();
        $t->text('note')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('updated_at')->nullable();
    });
    // Lease mínimo sqlite-friendly (sem a coluna gerada active_marker do MySQL —
    // activeLeaseFor só usa task_id + released_at + expires_at + human_principal).
    Schema::create('mcp_work_leases', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('human_principal', 100);
        $t->string('agent_id', 100)->nullable();
        $t->timestamp('expires_at')->nullable();
        $t->timestamp('released_at')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_task_events');
    Schema::dropIfExists('mcp_work_leases');
});

function seedClaimTask(string $id): void
{
    DB::table('mcp_tasks')->insert([
        'task_id' => $id,
        'module' => 'Governance',
        'title' => 'T',
        'status' => 'todo',
        'priority' => 'p2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function avisosClaimless(string $taskId): int
{
    return McpTaskEvent::where('task_id', $taskId)
        ->where('note', 'like', '%SEM lease ativo%')->count();
}

it('mutação SEM lease ativo do mutador emite AVISO claim-less', function () {
    seedClaimTask('US-CLM-001');

    // wagner muta sem ter feito claim (nenhum lease) → sinal.
    app(TaskCrudService::class)->update('US-CLM-001', ['priority' => 'p1'], 'wagner', 'wagner');

    // BITE: se a guarda A5 regredir (não emitir), vira 0 e falha.
    expect(avisosClaimless('US-CLM-001'))->toBe(1);
})->group('claim-less', 'ci');

it('mutação COM lease ativo do mutador NÃO emite aviso', function () {
    seedClaimTask('US-CLM-002');
    DB::table('mcp_work_leases')->insert([
        'task_id' => 'US-CLM-002',
        'human_principal' => 'wagner',
        'expires_at' => now()->addMinutes(30),
        'released_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(TaskCrudService::class)->update('US-CLM-002', ['priority' => 'p1'], 'wagner', 'wagner');

    // BITE: se A5 ignorar o lease do dono e avisar à toa, vira 1 e falha.
    expect(avisosClaimless('US-CLM-002'))->toBe(0);
})->group('claim-less', 'ci');

it('lease de OUTRO principal ainda conta como claim-less (avisa)', function () {
    seedClaimTask('US-CLM-004');
    DB::table('mcp_work_leases')->insert([
        'task_id' => 'US-CLM-004',
        'human_principal' => 'felipe', // lease é do felipe; quem muta é o wagner
        'expires_at' => now()->addMinutes(30),
        'released_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(TaskCrudService::class)->update('US-CLM-004', ['priority' => 'p1'], 'wagner', 'wagner');

    expect(avisosClaimless('US-CLM-004'))->toBe(1);
})->group('claim-less', 'ci');

it('caller de sistema (principal null) NÃO emite aviso claim-less', function () {
    seedClaimTask('US-CLM-003');

    // 3 args (sem principal) = caller de sistema/bulk → sem sinal.
    app(TaskCrudService::class)->update('US-CLM-003', ['priority' => 'p1'], 'system');

    expect(avisosClaimless('US-CLM-003'))->toBe(0);
})->group('claim-less', 'ci');
