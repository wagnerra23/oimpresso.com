<?php

declare(strict_types=1);

use Modules\KB\Entities\KbDecisionTreeStep;

/**
 * Unit specs do KbDecisionTreeStep.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §7
 *
 * Invariante por linha (KbDecisionTreeStepObserver enforces saving event):
 *   - exatamente UM de (yes_next_step_id, yes_fix) populado (nem ambos NULL, nem ambos preenchidos)
 *   - exatamente UM de (no_next_step_id, no_fix) populado
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

/** Helper local — cria tree e retorna id. */
function kbCreateTree(int $bizId = 1): int
{
    return \DB::table('kb_decision_trees')->insertGetId([
        'business_id' => $bizId,
        'slug'        => 'tree-'.uniqid(),
        'title'       => 'Tree test',
        'hue'         => 240,
        'status'      => 'published',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

it('rejects step with both yes_next and yes_fix NULL', function () {
    kbActAsUser(bizId: 1);
    $treeId = kbCreateTree(1);

    $step = new KbDecisionTreeStep();
    $step->business_id      = 1;
    $step->tree_id          = $treeId;
    $step->position         = 1;
    $step->question         = 'Está ligada?';
    $step->yes_next_step_id = null;
    $step->yes_fix          = null;     // INVALIDO: ambos NULL
    $step->no_next_step_id  = null;
    $step->no_fix           = 'Trocar fusível.';

    expect(fn () => $step->save())->toThrow(\Throwable::class);
});

it('rejects step with both yes_next AND yes_fix populated', function () {
    kbActAsUser(bizId: 1);
    $treeId = kbCreateTree(1);

    // Cria step alvo pro yes_next apontar (precisa de FK válido)
    $next = new KbDecisionTreeStep();
    $next->business_id = 1; $next->tree_id = $treeId; $next->position = 2;
    $next->question = 'Q2'; $next->yes_fix = 'a'; $next->no_fix = 'b';
    $next->save();

    $step = new KbDecisionTreeStep();
    $step->business_id      = 1;
    $step->tree_id          = $treeId;
    $step->position         = 1;
    $step->question         = 'Q1';
    $step->yes_next_step_id = $next->id;
    $step->yes_fix          = 'AMBOS PREENCHIDOS - INVALIDO';
    $step->no_fix           = 'ok';

    expect(fn () => $step->save())->toThrow(\Throwable::class);
});

it('rejects step with both no_next and no_fix NULL', function () {
    kbActAsUser(bizId: 1);
    $treeId = kbCreateTree(1);

    $step = new KbDecisionTreeStep();
    $step->business_id      = 1;
    $step->tree_id          = $treeId;
    $step->position         = 1;
    $step->question         = 'Q?';
    $step->yes_fix          = 'fix sim';
    $step->no_next_step_id  = null;
    $step->no_fix           = null;     // INVALIDO

    expect(fn () => $step->save())->toThrow(\Throwable::class);
});

it('accepts step with yes_fix only and no_fix only (terminal)', function () {
    kbActAsUser(bizId: 1);
    $treeId = kbCreateTree(1);

    $step = new KbDecisionTreeStep();
    $step->business_id = 1;
    $step->tree_id     = $treeId;
    $step->position    = 1;
    $step->question    = 'Está ligada?';
    $step->yes_fix     = 'Verificar tensão.';
    $step->no_fix      = 'Trocar fusível.';

    $step->save();

    expect($step->exists)->toBeTrue();
});

it('accepts step pointing to next steps (recursive)', function () {
    kbActAsUser(bizId: 1);
    $treeId = kbCreateTree(1);

    $q2 = new KbDecisionTreeStep();
    $q2->business_id = 1; $q2->tree_id = $treeId; $q2->position = 2;
    $q2->question = 'Q2'; $q2->yes_fix = 'a'; $q2->no_fix = 'b';
    $q2->save();

    $q3 = new KbDecisionTreeStep();
    $q3->business_id = 1; $q3->tree_id = $treeId; $q3->position = 3;
    $q3->question = 'Q3'; $q3->yes_fix = 'c'; $q3->no_fix = 'd';
    $q3->save();

    $q1 = new KbDecisionTreeStep();
    $q1->business_id      = 1;
    $q1->tree_id          = $treeId;
    $q1->position         = 1;
    $q1->question         = 'Q1';
    $q1->yes_next_step_id = $q2->id;  // só yes_next, sem yes_fix
    $q1->no_next_step_id  = $q3->id;  // só no_next, sem no_fix
    $q1->save();

    expect($q1->exists)->toBeTrue()
        ->and($q1->fresh()->yes_next_step_id)->toBe($q2->id);
});
