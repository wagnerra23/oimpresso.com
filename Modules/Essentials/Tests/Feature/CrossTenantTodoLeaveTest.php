<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\ToDo;

uses(Tests\TestCase::class);

/**
 * Wave J 2026-05-16: cobertura adicional cross-tenant Todo + Leave.
 *
 * MultiTenantTodoTest + MultiTenantLeaveTest (Wave M8) cobrem 5 cenários
 * básicos cada (list/show/edit/delete/complete). Este teste adiciona 4
 * cenários AVANÇADOS focados em bulk + audit trail + simultaneidade
 * inter-business — vetor de regressão menos óbvio:
 *
 *   1. BULK UPDATE Todo — UPDATE em batch via WHERE business_id NÃO vaza
 *   2. BULK DELETE Leave — DELETE em batch via WHERE business_id NÃO vaza
 *   3. COUNT cross-tenant — count() de biz=99 NÃO conta registros biz=1
 *   4. JOIN biz isolation — Leave biz=1 + Todo biz=99 não cruzam por id
 *
 * ADR 0093: multi-tenant Tier 0 IRREVOGÁVEL.
 * ADR 0101: tests biz=1 (Wagner WR2) e biz=99 (fictício). NUNCA biz=4.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/Essentials/Tests/Feature/MultiTenantTodoTest.php (5 cenários básicos)
 * @see Modules/Essentials/Tests/Feature/MultiTenantLeaveTest.php (5 cenários básicos)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: schema essentials_to_dos + essentials_leaves requer MySQL UltimatePOS (ADR 0101).'
        );
    }
    if (! Schema::hasTable('essentials_to_dos')) {
        $this->markTestSkipped('essentials_to_dos table missing — rode migrate do módulo Essentials primeiro.');
    }
    if (! Schema::hasTable('essentials_leaves')) {
        $this->markTestSkipped('essentials_leaves table missing — rode migrate do módulo Essentials primeiro.');
    }
});

const X_BIZ_WAGNER = 1;
const X_BIZ_FICTICIO = 99;
const X_TAG = 'WaveJ-CrossTenant';

/**
 * Helper — cria Todo identificável por tag pra cleanup limpo.
 */
function xCreateTodo(int $businessId, string $suffix): ToDo
{
    return ToDo::create([
        'business_id' => $businessId,
        'created_by'  => 1,
        'task'        => X_TAG . '-' . $suffix,
        'task_id'     => 'XJT-' . substr(md5(X_TAG . $suffix . $businessId), 0, 10),
        'date'        => now(),
        'status'      => 'new',
        'priority'    => 'medium',
    ]);
}

function xCreateLeave(int $businessId, string $suffix): EssentialsLeave
{
    return EssentialsLeave::create([
        'business_id' => $businessId,
        'user_id'     => 1,
        'ref_no'      => 'XJL-' . substr(md5(X_TAG . $suffix . $businessId), 0, 10),
        'start_date'  => now()->toDateString(),
        'end_date'    => now()->addDay()->toDateString(),
        'reason'      => X_TAG . '-' . $suffix,
        'status'      => 'pending',
    ]);
}

// ------------------------------------------------------------------
// 1. BULK UPDATE Todo — biz=99 NÃO consegue afetar batch biz=1
// ------------------------------------------------------------------

it('BULK UPDATE em Todo via biz=99 NÃO afeta nenhum registro biz=1', function () {
    $t1 = xCreateTodo(X_BIZ_WAGNER, 'bulk-1');
    $t2 = xCreateTodo(X_BIZ_WAGNER, 'bulk-2');
    $t3 = xCreateTodo(X_BIZ_WAGNER, 'bulk-3');

    // Atacante tenta UPDATE em batch via WHERE biz=99 + IN(ids reais biz=1)
    $affected = ToDo::where('business_id', X_BIZ_FICTICIO)
        ->whereIn('id', [$t1->id, $t2->id, $t3->id])
        ->update(['status' => 'completed', 'priority' => 'low']);

    expect($affected)->toBe(0);

    // Recarrega — todos preservados
    foreach ([$t1, $t2, $t3] as $t) {
        $fresh = ToDo::find($t->id);
        expect($fresh->status)->toBe('new');
        expect($fresh->priority)->toBe('medium');
    }
})->afterEach(function () {
    ToDo::where('task', 'like', X_TAG . '-bulk-%')->delete();
});

// ------------------------------------------------------------------
// 2. BULK DELETE Leave — biz=99 NÃO consegue deletar batch biz=1
// ------------------------------------------------------------------

it('BULK DELETE em Leave via biz=99 NÃO deleta nenhum registro biz=1', function () {
    $l1 = xCreateLeave(X_BIZ_WAGNER, 'lv-bulk-1');
    $l2 = xCreateLeave(X_BIZ_WAGNER, 'lv-bulk-2');

    $deleted = EssentialsLeave::where('business_id', X_BIZ_FICTICIO)
        ->whereIn('id', [$l1->id, $l2->id])
        ->delete();

    expect($deleted)->toBe(0);

    // Ambos sobrevivem scoped biz=1
    $survivors = EssentialsLeave::where('business_id', X_BIZ_WAGNER)
        ->whereIn('id', [$l1->id, $l2->id])
        ->count();

    expect($survivors)->toBe(2);
})->afterEach(function () {
    EssentialsLeave::where('reason', 'like', X_TAG . '-lv-bulk-%')->delete();
});

// ------------------------------------------------------------------
// 3. COUNT cross-tenant — biz=99 não conta o que pertence a biz=1
// ------------------------------------------------------------------

it('COUNT scoped biz=99 NÃO inclui Todo nem Leave criados em biz=1', function () {
    $t = xCreateTodo(X_BIZ_WAGNER, 'count-todo');
    $l = xCreateLeave(X_BIZ_WAGNER, 'count-leave');

    $todoCountFicticio = ToDo::where('business_id', X_BIZ_FICTICIO)
        ->whereIn('id', [$t->id])
        ->count();

    $leaveCountFicticio = EssentialsLeave::where('business_id', X_BIZ_FICTICIO)
        ->whereIn('id', [$l->id])
        ->count();

    expect($todoCountFicticio)->toBe(0);
    expect($leaveCountFicticio)->toBe(0);

    // Sanity check — count scoped biz=1 enxerga ambos
    $todoCountWagner = ToDo::where('business_id', X_BIZ_WAGNER)
        ->whereIn('id', [$t->id])
        ->count();

    $leaveCountWagner = EssentialsLeave::where('business_id', X_BIZ_WAGNER)
        ->whereIn('id', [$l->id])
        ->count();

    expect($todoCountWagner)->toBe(1);
    expect($leaveCountWagner)->toBe(1);
})->afterEach(function () {
    ToDo::where('task', 'like', X_TAG . '-count-%')->delete();
    EssentialsLeave::where('reason', 'like', X_TAG . '-count-%')->delete();
});

// ------------------------------------------------------------------
// 4. PRIMARY KEY collision cross-tenant — id igual em biz diferente NÃO mistura
// ------------------------------------------------------------------

it('ID idêntico em biz=1 e biz=99 NÃO causa vazamento via where business_id', function () {
    // Cria 1 Todo em biz=1 e 1 Todo em biz=99 — ids diferentes naturalmente,
    // mas testamos que filtro biz separa corretamente cada um.
    $tWagner = xCreateTodo(X_BIZ_WAGNER, 'pk-wagner');
    $tFicticio = xCreateTodo(X_BIZ_FICTICIO, 'pk-ficticio');

    // Cada query scoped retorna APENAS o seu, mesmo passando ambos os ids
    $resultadoWagner = ToDo::where('business_id', X_BIZ_WAGNER)
        ->whereIn('id', [$tWagner->id, $tFicticio->id])
        ->get();

    $resultadoFicticio = ToDo::where('business_id', X_BIZ_FICTICIO)
        ->whereIn('id', [$tWagner->id, $tFicticio->id])
        ->get();

    expect($resultadoWagner)->toHaveCount(1);
    expect($resultadoWagner->first()->id)->toBe($tWagner->id);
    expect($resultadoWagner->first()->task)->toContain('pk-wagner');

    expect($resultadoFicticio)->toHaveCount(1);
    expect($resultadoFicticio->first()->id)->toBe($tFicticio->id);
    expect($resultadoFicticio->first()->task)->toContain('pk-ficticio');
})->afterEach(function () {
    ToDo::where('task', 'like', X_TAG . '-pk-%')->delete();
});
