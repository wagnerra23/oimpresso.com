<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * ProducaoOficinaController@index — Kanban estado caçambas (demo Martinho 13/maio).
 *
 * Cobre payload Inertia da tela /oficina-auto/producao-oficina:
 *  - 5 grupos kanban (disponivel/locada/aguardando/manutencao/pronta)
 *  - Caçamba locada overdue cai em 'aguardando' (não 'locada')
 *  - Cross-tenant biz=99 vê 0 caçambas biz=1 (ADR 0093 + ADR 0101)
 *  - KPIs derivados consistem com counts dos grupos
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — ADR 0101.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see prototipo-ui/prototipos/producao-oficina/F1.html (canon visual)
 */

const BIZ_WAGNER_PROD = 1;
const BIZ_FICTICIO_PROD = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('vehicles table missing — rode OficinaAuto migrate primeiro');
    }
});

afterEach(function () {
    Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', 'PROD-%')
        ->forceDelete();
});

it('Controller@index retorna props kanban (5 grupos) + kpis + filters', function () {
    if (! Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('current_status column missing — Wave 5 schema pending');
    }

    session(['user.business_id' => BIZ_WAGNER_PROD]);
    $this->actingAs(stubUserSuperadminProd());

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(\Inertia\Response::class);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'] ?? null;
    expect($payload)->not->toBeNull();
    expect($payload)->toHaveKeys(['kanban', 'kpis', 'filters']);

    // 5 grupos canônicos (paridade F1.html)
    expect($payload['kanban'])->toHaveKeys([
        'disponivel',
        'locada',
        'aguardando',
        'manutencao',
        'pronta',
    ]);

    // KPIs canônicos
    expect($payload['kpis'])->toHaveKeys(['total', 'atrasadas', 'aguardando_recolhimento']);

    // Filters defaults
    expect($payload['filters'])->toHaveKeys(['capacidade', 'q']);
    expect($payload['filters']['capacidade'])->toBe('all');
});

it('caçamba locada overdue cai em "aguardando" (não "locada")', function () {
    if (! Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('current_status column missing — Wave 5 schema pending');
    }
    if (! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('service_orders table missing — rode OficinaAuto migrate primeiro');
    }

    session(['user.business_id' => BIZ_WAGNER_PROD]);
    $this->actingAs(stubUserSuperadminProd());

    // 1) Cria caçamba locada SEM overdue
    $vehNoOverdue = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_PROD,
        'plate'          => 'PROD-OK1',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'locada',
    ]);
    $rentalOk = \Modules\OficinaAuto\Entities\ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'          => BIZ_WAGNER_PROD,
        'vehicle_id'           => $vehNoOverdue->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(3),
        'expected_return_date' => now()->addDays(2)->toDateString(), // futuro = não overdue
        'daily_rate'           => 100,
    ]);
    $vehNoOverdue->update(['current_rental_id' => $rentalOk->id]);

    // 2) Cria caçamba locada COM overdue
    $vehOverdue = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_PROD,
        'plate'          => 'PROD-OVD',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'locada',
    ]);
    $rentalOverdue = \Modules\OficinaAuto\Entities\ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'          => BIZ_WAGNER_PROD,
        'vehicle_id'           => $vehOverdue->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(10),
        'expected_return_date' => now()->subDays(2)->toDateString(), // passado = overdue
        'daily_rate'           => 100,
    ]);
    $vehOverdue->update(['current_rental_id' => $rentalOverdue->id]);

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    $platesLocada = collect($payload['kanban']['locada'] ?? [])->pluck('plate')->all();
    $platesAguardando = collect($payload['kanban']['aguardando'] ?? [])->pluck('plate')->all();

    // PROD-OK1 (no prazo) → locada
    expect($platesLocada)->toContain('PROD-OK1');
    expect($platesAguardando)->not->toContain('PROD-OK1');

    // PROD-OVD (overdue) → aguardando (NÃO locada)
    expect($platesAguardando)->toContain('PROD-OVD');
    expect($platesLocada)->not->toContain('PROD-OVD');

    // KPIs refletem
    expect($payload['kpis']['atrasadas'])->toBeGreaterThanOrEqual(1);
    expect($payload['kpis']['aguardando_recolhimento'])->toBeGreaterThanOrEqual(1);
});

it('cross-tenant biz=99 vê 0 caçambas biz=1 (Tier 0 ADR 0093)', function () {
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_PROD,
        'plate'          => 'PROD-XT1',
        'vehicle_type'   => 'cacamba_estacionaria',
        'current_status' => Schema::hasColumn('vehicles', 'current_status') ? 'disponivel' : null,
    ]);

    // Sessão biz=99 (fictício)
    session(['user.business_id' => BIZ_FICTICIO_PROD]);
    $this->actingAs(stubUserSuperadminProd());

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    // Nenhum grupo deve conter PROD-XT1 (vazado de biz=1)
    $allPlates = collect($payload['kanban'])
        ->flatMap(fn ($col) => collect($col)->pluck('plate'))
        ->all();

    expect($allPlates)->not->toContain('PROD-XT1');
    expect($payload['kpis']['total'])->toBe(0);
});

it('filter ?capacidade=5 reduz cards', function () {
    if (! Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('current_status column missing — Wave 5 schema pending');
    }

    session(['user.business_id' => BIZ_WAGNER_PROD]);
    $this->actingAs(stubUserSuperadminProd());

    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_PROD,
        'plate'          => 'PROD-C5A',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'disponivel',
    ]);
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_PROD,
        'plate'          => 'PROD-C7A',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 7,
        'current_status' => 'disponivel',
    ]);

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET', ['capacidade' => '5']);
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    $platesAll = collect($payload['kanban'])
        ->flatMap(fn ($col) => collect($col)->pluck('plate'))
        ->all();

    expect($platesAll)->toContain('PROD-C5A');
    expect($platesAll)->not->toContain('PROD-C7A');
    expect($payload['filters']['capacidade'])->toBe('5');
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function stubUserSuperadminProd(): \App\User
{
    $user = \App\User::query()
        ->where('business_id', BIZ_WAGNER_PROD)
        ->first();

    if (! $user) {
        test()->markTestSkipped('User biz=1 ausente — rode UltimatePOS seeders primeiro');
    }

    return $user;
}
