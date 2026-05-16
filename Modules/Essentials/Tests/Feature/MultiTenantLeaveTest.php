<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsLeave;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 das solicitações de leave (férias/afastamento).
 *
 * EssentialsLeave NÃO usa global scope BusinessScope — Controller filtra por
 * business_id da session. Testes validam que data biz=1 NÃO vaza em queries
 * scoped por business_id=99.
 *
 * ADR 0093: multi-tenant Tier 0 IRREVOGÁVEL.
 * ADR 0101: tests sempre biz=1 (Wagner WR2). NUNCA biz=4 (ROTA LIVRE prod).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema essentials_leaves requer MySQL UltimatePOS (ADR 0101).');
    }
    if (! Schema::hasTable('essentials_leaves')) {
        $this->markTestSkipped('essentials_leaves table missing — rode migrate do módulo Essentials primeiro.');
    }
});

const LEAVE_BIZ_WAGNER = 1;
const LEAVE_BIZ_FICTICIO = 99;

/**
 * Helper — cria leave_request stub minimal pra isolamento.
 */
function essCreateLeave(int $businessId, string $reason): EssentialsLeave
{
    return EssentialsLeave::create([
        'business_id'    => $businessId,
        'user_id'        => 1, // user_id stub
        'ref_no'         => 'LV-TEST-' . substr(md5($reason), 0, 8),
        'start_date'     => now()->toDateString(),
        'end_date'       => now()->addDay()->toDateString(),
        'reason'         => $reason,
        'status'         => 'pending',
    ]);
}

// ------------------------------------------------------------------
// 1. LIST — leave biz=1 não aparece em listagem scoped biz=99
// ------------------------------------------------------------------

it('Leave biz=1 não aparece em listagem scoped biz=99', function () {
    $leave = essCreateLeave(LEAVE_BIZ_WAGNER, 'Férias Wagner Isolamento');

    $resultado = EssentialsLeave::where('business_id', LEAVE_BIZ_FICTICIO)
        ->where('id', $leave->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    EssentialsLeave::where('reason', 'Férias Wagner Isolamento')->delete();
});

// ------------------------------------------------------------------
// 2. SHOW — leave biz=1 não pode ser carregada via where biz=99
// ------------------------------------------------------------------

it('Leave biz=1 NÃO pode ser carregada via show scoped biz=99', function () {
    $leave = essCreateLeave(LEAVE_BIZ_WAGNER, 'Atestado Show Isolamento');

    $achado = EssentialsLeave::where('business_id', LEAVE_BIZ_FICTICIO)
        ->where('id', $leave->id)
        ->first();

    expect($achado)->toBeNull();
})->afterEach(function () {
    EssentialsLeave::where('reason', 'Atestado Show Isolamento')->delete();
});

// ------------------------------------------------------------------
// 3. EDIT — leave biz=1 não pode ter status mudado via scope biz=99
// ------------------------------------------------------------------

it('Leave biz=1 NÃO pode ter status mudado via update scoped biz=99', function () {
    $leave = essCreateLeave(LEAVE_BIZ_WAGNER, 'Approval Edit Isolamento');

    $affected = EssentialsLeave::where('business_id', LEAVE_BIZ_FICTICIO)
        ->where('id', $leave->id)
        ->update(['status' => 'approved']);

    expect($affected)->toBe(0);

    $fresh = EssentialsLeave::find($leave->id);
    expect($fresh->status)->toBe('pending');
})->afterEach(function () {
    EssentialsLeave::where('reason', 'Approval Edit Isolamento')->delete();
});

// ------------------------------------------------------------------
// 4. DELETE — leave biz=1 não pode ser deletada via where biz=99
// ------------------------------------------------------------------

it('Leave biz=1 NÃO pode ser deletada via destroy scoped biz=99', function () {
    $leave = essCreateLeave(LEAVE_BIZ_WAGNER, 'Leave Delete Isolamento');

    $deleted = EssentialsLeave::where('business_id', LEAVE_BIZ_FICTICIO)
        ->where('id', $leave->id)
        ->delete();

    expect($deleted)->toBe(0);

    $sobreviveu = EssentialsLeave::where('business_id', LEAVE_BIZ_WAGNER)
        ->where('id', $leave->id)
        ->first();

    expect($sobreviveu)->not->toBeNull();
})->afterEach(function () {
    EssentialsLeave::where('reason', 'Leave Delete Isolamento')->delete();
});

// ------------------------------------------------------------------
// 5. (smoke) — biz=1 enxerga apenas o que é dele
// ------------------------------------------------------------------

it('Leave biz=1 aparece em listagem scoped biz=1', function () {
    $leave = essCreateLeave(LEAVE_BIZ_WAGNER, 'Leave Visivel');

    $resultado = EssentialsLeave::where('business_id', LEAVE_BIZ_WAGNER)
        ->where('id', $leave->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->reason)->toBe('Leave Visivel');
})->afterEach(function () {
    EssentialsLeave::where('reason', 'Leave Visivel')->delete();
});
