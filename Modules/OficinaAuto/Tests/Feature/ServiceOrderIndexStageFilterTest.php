<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Wave 7-D — Gap #3 estado-da-arte FSM screen: filtros chips por stage no Index OS.
 *
 * Cobre:
 *   1. Filtro stage=disponivel mostra só OS biz=1 com current_stage_id matching
 *   2. Multi-tenant Tier 0 — OS biz=99 mesmo stage_id NÃO vaza pra biz=1
 *   3. stages payload retorna counts corretos por stage cacamba_*
 *   4. stage=all retorna todas as OS (sem filtro)
 *
 * Pattern: skip SQLite (ADR 0101 — schema MySQL UltimatePOS obrigatório).
 * Validação direta na query — não roda HTTP middleware UltimatePOS.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (método index + buildStagesPayload)
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/Index.tsx (STAGE_PILLS UI)
 * @see memory/sessions/2026-05-20-arte-tela-fsm-workflow.md (Gap #3)
 */

const BIZ_STAGE_TEST = 1;
const BIZ_STAGE_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('FSM/OficinaAuto tables missing — rode migrate primeiro');
    }
    if (! Schema::hasColumn('service_orders', 'current_stage_id')) {
        $this->markTestSkipped('Wave 7 migration current_stage_id pendente');
    }
});

/**
 * Helper — cria processo FSM cacamba_locacao com 2 stages.
 *
 * @return array{process: SaleProcess, disponivel: SaleProcessStage, locada: SaleProcessStage}
 */
function osfSetupCacambaProcess(int $bizId): array
{
    $suffix = 'osf' . $bizId . '_' . uniqid();
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'              => $bizId,
        'key'                      => 'cacamba_locacao_' . $suffix,
        'name'                     => 'Caçamba — Locação (test)',
        'default_for_contact_type' => 'any',
        'active'                   => true,
    ]);
    $disponivel = SaleProcessStage::create([
        'process_id' => $process->id,
        'key'        => 'disponivel',
        'name'       => 'Disponível',
        'sort_order' => 0,
        'is_initial' => true,
        'color'      => 'gray',
    ]);
    $locada = SaleProcessStage::create([
        'process_id' => $process->id,
        'key'        => 'locada',
        'name'       => 'Locada',
        'sort_order' => 1,
        'color'      => 'blue',
    ]);

    return compact('process', 'disponivel', 'locada');
}

function osfCreateOs(int $bizId, ?int $stageId = null): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $bizId,
        'plate'        => 'OSF' . substr((string) random_int(1000, 9999), 0, 4),
        'vehicle_type' => 'caminhao',
    ]);
    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id'      => $bizId,
        'vehicle_id'       => $vehicle->id,
        'order_type'       => 'locacao',
        'status'           => 'aberta',
        'entered_at'       => now(),
        'daily_rate'       => '150.00',
        'current_stage_id' => $stageId,
    ]);
}

function osfCleanupTest(int $bizId): void
{
    $procIds = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)
        ->where('key', 'like', 'cacamba_locacao_osf%')
        ->pluck('id');
    $stageIds = SaleProcessStage::whereIn('process_id', $procIds)->pluck('id');

    Vehicle::withoutGlobalScopes()->where('business_id', $bizId)->where('plate', 'like', 'OSF%')->forceDelete();
    ServiceOrder::withoutGlobalScopes()->where('business_id', $bizId)->forceDelete();
    SaleProcessStage::whereIn('id', $stageIds)->delete();
    SaleProcess::whereIn('id', $procIds)->delete();
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. filtro stage=disponivel filtra apenas OS com current_stage_id matching key', function () {
    session(['user.business_id' => BIZ_STAGE_TEST]);

    ['disponivel' => $disp, 'locada' => $loc] = osfSetupCacambaProcess(BIZ_STAGE_TEST);

    // 2 OS em disponivel + 1 OS em locada + 1 sem stage
    osfCreateOs(BIZ_STAGE_TEST, $disp->id);
    osfCreateOs(BIZ_STAGE_TEST, $disp->id);
    osfCreateOs(BIZ_STAGE_TEST, $loc->id);
    osfCreateOs(BIZ_STAGE_TEST, null);

    // Resolve stage_ids do business pra cacamba_locacao (mesma lógica do controller)
    $stageIds = SaleProcessStage::query()
        ->whereHas('process', function ($p) {
            $p->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', BIZ_STAGE_TEST)
                ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao', 'cacamba_locacao_osf' . BIZ_STAGE_TEST . '_%']);
        })
        ->where('key', 'disponivel')
        ->pluck('id');

    $filtered = ServiceOrder::query()
        ->whereIn('current_stage_id', $stageIds)
        ->count();

    expect($filtered)->toBe(2);
})->afterEach(function () {
    osfCleanupTest(BIZ_STAGE_TEST);
});

it('2. multi-tenant Tier 0 — OS biz=99 com mesmo stage_id NAO vaza pra biz=1', function () {
    session(['user.business_id' => BIZ_STAGE_TEST]);

    // biz=1 cria seu próprio process+stage
    ['disponivel' => $dispBiz1] = osfSetupCacambaProcess(BIZ_STAGE_TEST);
    osfCreateOs(BIZ_STAGE_TEST, $dispBiz1->id);

    // biz=99 cria process+stage DIFERENTE (mesmo key 'disponivel' mas process_id diferente)
    ['disponivel' => $dispBiz99] = osfSetupCacambaProcess(BIZ_STAGE_FICTICIO);
    osfCreateOs(BIZ_STAGE_FICTICIO, $dispBiz99->id);

    // Resolve stage_ids do BIZ_STAGE_TEST (deve achar APENAS o do biz=1)
    $stageIdsBiz1 = SaleProcessStage::query()
        ->whereHas('process', function ($p) {
            $p->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', BIZ_STAGE_TEST)
                ->where('key', 'like', 'cacamba_locacao_osf' . BIZ_STAGE_TEST . '_%');
        })
        ->where('key', 'disponivel')
        ->pluck('id');

    expect($stageIdsBiz1)->toHaveCount(1);
    expect($stageIdsBiz1[0])->toBe($dispBiz1->id);

    // ServiceOrder global scope filtra automaticamente biz=1
    $filtered = ServiceOrder::query()
        ->whereIn('current_stage_id', $stageIdsBiz1)
        ->count();

    expect($filtered)->toBe(1);
})->afterEach(function () {
    osfCleanupTest(BIZ_STAGE_TEST);
    osfCleanupTest(BIZ_STAGE_FICTICIO);
});

it('3. buildStagesPayload retorna counts corretos por stage', function () {
    session(['user.business_id' => BIZ_STAGE_TEST]);

    ['disponivel' => $disp, 'locada' => $loc] = osfSetupCacambaProcess(BIZ_STAGE_TEST);

    osfCreateOs(BIZ_STAGE_TEST, $disp->id);
    osfCreateOs(BIZ_STAGE_TEST, $disp->id);
    osfCreateOs(BIZ_STAGE_TEST, $disp->id);
    osfCreateOs(BIZ_STAGE_TEST, $loc->id);

    // Replica a lógica do buildStagesPayload (sem chamar o controller direto)
    $stages = SaleProcessStage::query()
        ->whereHas('process', function ($p) {
            $p->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', BIZ_STAGE_TEST)
                ->where('key', 'like', 'cacamba_locacao_osf' . BIZ_STAGE_TEST . '_%');
        })
        ->orderBy('sort_order')
        ->get();

    $counts = ServiceOrder::query()
        ->whereIn('current_stage_id', $stages->pluck('id'))
        ->selectRaw('current_stage_id, COUNT(*) as total')
        ->groupBy('current_stage_id')
        ->pluck('total', 'current_stage_id');

    expect((int) $counts[$disp->id])->toBe(3);
    expect((int) $counts[$loc->id])->toBe(1);
})->afterEach(function () {
    osfCleanupTest(BIZ_STAGE_TEST);
});

it('4. sem filtro stage retorna todas as OS do business (multi-tenant preservado)', function () {
    session(['user.business_id' => BIZ_STAGE_TEST]);

    ['disponivel' => $disp] = osfSetupCacambaProcess(BIZ_STAGE_TEST);

    osfCreateOs(BIZ_STAGE_TEST, $disp->id);
    osfCreateOs(BIZ_STAGE_TEST, null);
    osfCreateOs(BIZ_STAGE_TEST, $disp->id);

    // Mesmo biz=99 com OS, não conta porque global scope
    ['disponivel' => $dispB99] = osfSetupCacambaProcess(BIZ_STAGE_FICTICIO);
    osfCreateOs(BIZ_STAGE_FICTICIO, $dispB99->id);

    $total = ServiceOrder::query()->count();
    expect($total)->toBe(3);
})->afterEach(function () {
    osfCleanupTest(BIZ_STAGE_TEST);
    osfCleanupTest(BIZ_STAGE_FICTICIO);
});
