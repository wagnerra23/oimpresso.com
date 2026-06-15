<?php

declare(strict_types=1);

use Modules\Jana\Services\Reconcile\Reconcilers\TasksReconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\WorkLease\WorkLeaseService;

uses(Tests\TestCase::class);

/**
 * TasksReconciler — cobre o NÚCLEO PURO `analisar()` com tasks + leases ativos
 * INJETADOS (sem DB, determinístico). Espelha o BLOCO A puro do IndexReconcilerTest.
 *
 * Mapa pra ITEM B-R0 (ADR 0237 detect-only + ADR 0278):
 *   - R-A doing-órfã          → 'doing' SEM lease ativo = 1 drift; com lease = 0.
 *   - R-B done-sem-acceptance  → 'done' com acceptance_ref NULL ou '' = drift cada;
 *                                com ref preenchido = 0.
 *   - R-E blocked_by-resolvido → blocked_by=[Y] com Y done/cancelled = drift;
 *                                Y ainda doing = 0.
 *   - todos os drifts são detect-only → healable=false (alerta humano, R10).
 *
 * Cada `it` MORDE: se o reconciler parar de detectar (ou marcar healable=true), o
 * teste falha (assert de presença/ausência + flag healable, não tautologia).
 */

/** Reconciler PURO: WorkLeaseService nunca é tocado por analisar() (sem DB). */
function novoTasksRec(): TasksReconciler
{
    return new TasksReconciler(new WorkLeaseService());
}

/**
 * @param array<int, ReconcileDrift> $drifts
 */
function tasksDriftPorTarget(array $drifts, string $prefixo): ?ReconcileDrift
{
    foreach ($drifts as $d) {
        if ($d->target === $prefixo || str_starts_with($d->target, $prefixo)) {
            return $d;
        }
    }

    return null;
}

// ─── R-A: doing-órfã ──────────────────────────────────────────────────────────

it('R-A: doing SEM lease ativo → 1 drift doing_orfa (detect-only)', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-1', 'status' => 'doing', 'acceptance_ref' => null, 'blocked_by' => []],
    ];
    $drifts = $rec->analisar($tasks, []); // NENHUM lease ativo

    $drift = tasksDriftPorTarget($drifts, 'tasks.doing_orfa:COPI-1');

    expect($drifts)->toHaveCount(1)
        ->and($drift)->not->toBeNull()
        ->and($drift->healable)->toBeFalse()   // detect-only → alerta humano
        ->and($drift->healed)->toBeFalse()
        ->and($drift->detail)->toContain('COPI-1');
});

it('R-A: doing COM lease ativo → 0 drift (coordenação ok)', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-1', 'status' => 'doing', 'acceptance_ref' => null, 'blocked_by' => []],
    ];
    $drifts = $rec->analisar($tasks, ['COPI-1']); // lease ativo cobre a task

    expect(tasksDriftPorTarget($drifts, 'tasks.doing_orfa'))->toBeNull();
});

// ─── R-B: done-sem-acceptance_ref ───────────────────────────────────────────────

it('R-B: done com acceptance_ref NULL → 1 drift done_sem_acceptance', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-2', 'status' => 'done', 'acceptance_ref' => null, 'blocked_by' => []],
    ];
    $drift = tasksDriftPorTarget($rec->analisar($tasks, []), 'tasks.done_sem_acceptance:COPI-2');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeFalse()
        ->and($drift->observed)->toContain('NULL');
});

it('R-B: done com acceptance_ref vazio ("") → 1 drift done_sem_acceptance', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-3', 'status' => 'done', 'acceptance_ref' => '', 'blocked_by' => []],
    ];
    $drift = tasksDriftPorTarget($rec->analisar($tasks, []), 'tasks.done_sem_acceptance:COPI-3');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeFalse()
        ->and($drift->observed)->toContain('vazio');
});

it('R-B: done com acceptance_ref preenchido → 0 drift', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-4', 'status' => 'done', 'acceptance_ref' => 'PR #2795', 'blocked_by' => []],
    ];
    $drifts = $rec->analisar($tasks, []);

    expect(tasksDriftPorTarget($drifts, 'tasks.done_sem_acceptance'))->toBeNull();
});

// ─── R-E: blocked_by-resolvido ──────────────────────────────────────────────────

it('R-E: blocked_by referencia task DONE → 1 drift blocked_by_resolvido', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-5', 'status' => 'todo', 'acceptance_ref' => null, 'blocked_by' => ['COPI-9']],
        ['task_id' => 'COPI-9', 'status' => 'done', 'acceptance_ref' => 'x', 'blocked_by' => []],
    ];
    $drift = tasksDriftPorTarget($rec->analisar($tasks, []), 'tasks.blocked_by_resolvido:COPI-5');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeFalse()
        ->and($drift->detail)->toContain('COPI-9');
});

it('R-E: blocked_by referencia task CANCELLED → 1 drift blocked_by_resolvido', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-6', 'status' => 'blocked', 'acceptance_ref' => null, 'blocked_by' => ['COPI-9']],
        ['task_id' => 'COPI-9', 'status' => 'cancelled', 'acceptance_ref' => null, 'blocked_by' => []],
    ];
    $drift = tasksDriftPorTarget($rec->analisar($tasks, []), 'tasks.blocked_by_resolvido:COPI-6');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeFalse();
});

it('R-E: blocked_by referencia task ainda DOING → 0 drift (bloqueio legítimo)', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-7', 'status' => 'blocked', 'acceptance_ref' => null, 'blocked_by' => ['COPI-9']],
        ['task_id' => 'COPI-9', 'status' => 'doing', 'acceptance_ref' => null, 'blocked_by' => []],
    ];
    $drifts = $rec->analisar($tasks, ['COPI-9']); // COPI-9 doing com lease → não dispara R-A nele tampouco

    expect(tasksDriftPorTarget($drifts, 'tasks.blocked_by_resolvido'))->toBeNull();
});

// ─── Sanidade + contrato ────────────────────────────────────────────────────────

it('analisar: backlog todo em sincronia → nenhum drift', function () {
    $rec = novoTasksRec();

    $tasks = [
        ['task_id' => 'COPI-1', 'status' => 'doing', 'acceptance_ref' => null, 'blocked_by' => []],
        ['task_id' => 'COPI-2', 'status' => 'done', 'acceptance_ref' => 'PR #1', 'blocked_by' => []],
        ['task_id' => 'COPI-3', 'status' => 'blocked', 'acceptance_ref' => null, 'blocked_by' => ['COPI-1']],
        ['task_id' => 'COPI-4', 'status' => 'todo', 'acceptance_ref' => null, 'blocked_by' => []],
    ];
    // COPI-1 doing tem lease; COPI-2 done tem ref; COPI-3 bloqueada por COPI-1 (doing, não fechada).
    $drifts = $rec->analisar($tasks, ['COPI-1']);

    expect($drifts)->toBe([]);
});

it('reconcile: name e tags canônicos', function () {
    $rec = novoTasksRec();

    expect($rec->name())->toBe('tasks')
        ->and($rec->tags())->toContain('tasks')
        ->and($rec->tags())->toContain('tier_0')
        ->and($rec->tags())->toContain('governance');
});
