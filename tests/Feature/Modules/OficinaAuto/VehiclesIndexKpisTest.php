<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * VehicleController@index — KPIs + filter + cross-tenant (US-OFICINA-001 evoluída).
 *
 * Cobre payload Inertia da tela de Caçambas (demo Martinho 13/maio 2026):
 * - 4 KPIs (disponivel, locada, manutencao, atrasada)
 * - Filter ?status=locada
 * - Cross-tenant biz=99 vê 0 vehicles biz=1 (ADR 0093 + ADR 0101)
 * - Empty state quando vehicles vazios
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — ADR 0101.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */

const BIZ_WAGNER_KPIS = 1;
const BIZ_FICTICIO_KPIS = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('vehicles table missing — rode OficinaAuto migrate primeiro');
    }
    // Wave 5 do Agent A entrega coluna current_status. Sem ela, o teste de KPI
    // valida apenas o caminho fail-soft (zeros + total).
});

afterEach(function () {
    Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', 'KPI%')
        ->forceDelete();
});

it('Controller@index retorna props vehicles + kpis + filters', function () {
    if (! Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('current_status column missing — Agent A migration pending');
    }
    session(['user.business_id' => BIZ_WAGNER_KPIS]);

    $controller = new \Modules\OficinaAuto\Http\Controllers\VehicleController();

    // Stub user com permission superadmin pra bypass do abort_unless
    $this->actingAs(stubUserWithSuperadmin());

    $request = \Illuminate\Http\Request::create('/oficina-auto/veiculos', 'GET');
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(\Inertia\Response::class);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'] ?? null;
    expect($payload)->not->toBeNull();
    expect($payload)->toHaveKeys(['vehicles', 'kpis', 'filters']);
    expect($payload['kpis'])->toHaveKeys(['disponivel', 'locada', 'manutencao', 'atrasada', 'total']);
    expect($payload['filters'])->toHaveKeys(['q', 'status']);
    expect($payload['filters']['status'])->toBe('all');
});

it('filter ?status=locada bate na query', function () {
    if (! Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('current_status column missing — Agent A migration pending');
    }
    session(['user.business_id' => BIZ_WAGNER_KPIS]);

    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_KPIS,
        'plate'          => 'KPI-LOC1',
        'vehicle_type'   => 'cacamba_estacionaria',
        'current_status' => 'locada',
    ]);
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_KPIS,
        'plate'          => 'KPI-DSP1',
        'vehicle_type'   => 'cacamba_estacionaria',
        'current_status' => 'disponivel',
    ]);

    $this->actingAs(stubUserWithSuperadmin());

    $request = \Illuminate\Http\Request::create('/oficina-auto/veiculos', 'GET', ['status' => 'locada']);
    $response = (new \Modules\OficinaAuto\Http\Controllers\VehicleController())->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];
    $plates = collect($payload['vehicles']['data'] ?? [])->pluck('plate')->all();

    expect($plates)->toContain('KPI-LOC1');
    expect($plates)->not->toContain('KPI-DSP1');
});

it('cross-tenant biz=99 vê 0 vehicles biz=1 (Tier 0 ADR 0093)', function () {
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_KPIS,
        'plate'          => 'KPI-XT-1',
        'vehicle_type'   => 'cacamba_estacionaria',
    ]);

    // Sessão biz=99 (fictício, sem nenhuma vehicle)
    session(['user.business_id' => BIZ_FICTICIO_KPIS]);
    $this->actingAs(stubUserWithSuperadmin());

    $request = \Illuminate\Http\Request::create('/oficina-auto/veiculos', 'GET');
    $response = (new \Modules\OficinaAuto\Http\Controllers\VehicleController())->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];
    $plates = collect($payload['vehicles']['data'] ?? [])->pluck('plate')->all();

    expect($plates)->not->toContain('KPI-XT-1');
});

it('empty state quando filter não retorna vehicles', function () {
    session(['user.business_id' => BIZ_FICTICIO_KPIS]);
    $this->actingAs(stubUserWithSuperadmin());

    $request = \Illuminate\Http\Request::create('/oficina-auto/veiculos', 'GET');
    $response = (new \Modules\OficinaAuto\Http\Controllers\VehicleController())->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];
    expect($payload['vehicles']['data'] ?? [])->toBeEmpty();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Cria um stub de User com permission superadmin pra bypass do abort_unless.
 * Usa o User real do biz=1 (Wagner) se existir; senão pula via skip.
 */
function stubUserWithSuperadmin(): \App\User
{
    $user = \App\User::query()
        ->where('business_id', BIZ_WAGNER_KPIS)
        ->first();

    if (! $user) {
        // CI sem seed UltimatePOS — pula teste
        test()->markTestSkipped('User biz=1 ausente — rode UltimatePOS seeders primeiro');
    }

    return $user;
}
