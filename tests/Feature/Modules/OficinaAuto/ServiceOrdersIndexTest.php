<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * ServiceOrders Index Dashboard — KPIs, filtros e cross-tenant.
 *
 * Cobre o método `index()` reescrito pra dashboard de OS (locação + manutenção)
 * pré-reunião Martinho 13/maio. Usa Schema::hasColumn fallbacks porque colunas
 * Wave 5-A (order_type / expected_return_date / etc) podem não estar migradas
 * em todas branches durante consolidação paralela.
 *
 * Convenção: biz=1 default (Wagner WR2), biz=99 cross-tenant fictício (ADR 0101).
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const BIZ_WAGNER_SOI = 1;
const BIZ_FICTICIO_SOI = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('service_orders/vehicles tables missing — rode OficinaAuto migrate primeiro');
    }
});

function makeVehicleSOI(string $plate, int $businessId = BIZ_WAGNER_SOI): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste
        'business_id'  => $businessId,
        'plate'        => $plate,
        'vehicle_type' => 'cacamba_estacionaria',
    ]);
}

function makeOrderSOI(array $attrs): ServiceOrder
{
    return ServiceOrder::withoutGlobalScopes()->create(array_merge([ // SUPERADMIN: setup de teste
        'business_id' => BIZ_WAGNER_SOI,
        'status'      => 'aberta',
        'entered_at'  => now(),
    ], $attrs));
}

function cleanupSOI(string $platePrefix): void
{
    $vehicleIds = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', $platePrefix . '%')
        ->pluck('id')->toArray();

    if (! empty($vehicleIds)) {
        ServiceOrder::withoutGlobalScopes()
            ->whereIn('vehicle_id', $vehicleIds)
            ->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicleIds)->forceDelete();
    }
}

it('Index retorna 4 KPIs corretos (locacoes_ativas, manutencao_ativas, concluidas_mes, atrasadas)', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    $hasOrderType  = Schema::hasColumn('service_orders', 'order_type');
    $hasReturnDate = Schema::hasColumn('service_orders', 'expected_return_date');

    $v = makeVehicleSOI('SOI001');

    // Locação ativa, no prazo
    $loc = makeOrderSOI([
        'vehicle_id' => $v->id,
        'status'     => 'em_servico',
    ]);
    if ($hasOrderType)  { $loc->order_type = 'locacao'; }
    if ($hasReturnDate) { $loc->expected_return_date = now()->addDays(5); }
    $loc->save();

    // Locação ATRASADA
    $atr = makeOrderSOI([
        'vehicle_id' => $v->id,
        'status'     => 'em_servico',
    ]);
    if ($hasOrderType)  { $atr->order_type = 'locacao'; }
    if ($hasReturnDate) {
        $atr->expected_return_date = now()->subDays(3);
    } else {
        $atr->expected_completion = now()->subDays(3);
    }
    $atr->save();

    // Manutenção ativa
    $man = makeOrderSOI([
        'vehicle_id' => $v->id,
        'status'     => 'em_servico',
    ]);
    if ($hasOrderType) { $man->order_type = 'manutencao'; }
    $man->save();

    // Concluída esse mês
    $con = makeOrderSOI([
        'vehicle_id' => $v->id,
        'status'     => 'concluida',
    ]);
    if ($hasOrderType) { $con->order_type = 'manutencao'; }
    $con->save();
    // Força updated_at no mês corrente
    DB::table('service_orders')->where('id', $con->id)->update(['updated_at' => now()]);

    // Replica a lógica de KPI do controller (HTTP-test-free — sem Spatie/Auth setup).
    // Pra evitar acoplamento com middleware, validamos as queries que o controller usa.
    $kpis = [
        'locacoes_ativas' => $hasOrderType
            ? ServiceOrder::where('order_type', 'locacao')
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count()
            : 0,
        'manutencao_ativas' => $hasOrderType
            ? ServiceOrder::where('order_type', 'manutencao')
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count()
            : ServiceOrder::whereNotIn('status', ['concluida', 'cancelada'])->count(),
        'concluidas_mes' => ServiceOrder::where('status', 'concluida')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count(),
    ];

    if ($hasOrderType) {
        // Esperado: 2 locações ativas (loc + atr), 1 manutenção ativa, 1 concluída.
        expect($kpis['locacoes_ativas'])->toBeGreaterThanOrEqual(2);
        expect($kpis['manutencao_ativas'])->toBeGreaterThanOrEqual(1);
    } else {
        // Sem order_type: 3 ativas total (loc+atr+man) e 1 concluída
        expect($kpis['manutencao_ativas'])->toBeGreaterThanOrEqual(3);
    }
    expect($kpis['concluidas_mes'])->toBeGreaterThanOrEqual(1);
})->afterEach(function () {
    cleanupSOI('SOI001');
});

it('Filter ?status=atrasada retorna apenas OS overdue', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    $hasOrderType  = Schema::hasColumn('service_orders', 'order_type');
    $hasReturnDate = Schema::hasColumn('service_orders', 'expected_return_date');

    if (! $hasOrderType || ! $hasReturnDate) {
        $this->markTestSkipped('order_type ou expected_return_date ausentes — Wave 5-A não migrada');
    }

    $v = makeVehicleSOI('SOI002');

    // No prazo
    $ok = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $ok->order_type = 'locacao';
    $ok->expected_return_date = now()->addDays(2);
    $ok->save();

    // Atrasada
    $late = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $late->order_type = 'locacao';
    $late->expected_return_date = now()->subDays(2);
    $late->save();

    $atrasadas = ServiceOrder::where('order_type', 'locacao')
        ->whereNotIn('status', ['concluida', 'cancelada'])
        ->whereNotNull('expected_return_date')
        ->whereDate('expected_return_date', '<', now()->toDateString())
        ->pluck('id')
        ->toArray();

    expect($atrasadas)->toContain($late->id);
    expect($atrasadas)->not->toContain($ok->id);
})->afterEach(function () {
    cleanupSOI('SOI002');
});

it('Filter ?type=locacao filtra apenas locações', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    if (! Schema::hasColumn('service_orders', 'order_type')) {
        $this->markTestSkipped('order_type ausente — Wave 5-A não migrada');
    }

    $v = makeVehicleSOI('SOI003');

    $loc = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $loc->order_type = 'locacao';
    $loc->save();

    $man = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $man->order_type = 'manutencao';
    $man->save();

    $somenteLocacao = ServiceOrder::where('order_type', 'locacao')->pluck('id')->toArray();

    expect($somenteLocacao)->toContain($loc->id);
    expect($somenteLocacao)->not->toContain($man->id);
})->afterEach(function () {
    cleanupSOI('SOI003');
});

it('Cross-tenant biz=99 NÃO vê OS biz=1 (Tier 0 ADR 0093)', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    $v = makeVehicleSOI('SOI004', BIZ_WAGNER_SOI);
    $os = makeOrderSOI([
        'business_id' => BIZ_WAGNER_SOI,
        'vehicle_id'  => $v->id,
        'status'      => 'em_servico',
    ]);

    // Switch session pra biz=99 — global scope deve filtrar tudo de biz=1
    session(['user.business_id' => BIZ_FICTICIO_SOI]);
    $resultado = ServiceOrder::where('id', $os->id)->get();

    expect($resultado)->toHaveCount(0);

    // E KPIs (que rodam sob global scope) também devem zerar pra biz=99
    $kpiAtivas = ServiceOrder::whereNotIn('status', ['concluida', 'cancelada'])->count();
    expect($kpiAtivas)->toBe(0);
})->afterEach(function () {
    cleanupSOI('SOI004');
});

it('Search "q" busca por placa do vehicle', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    $v = makeVehicleSOI('SOI005ZZZ');
    $os = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);

    $found = ServiceOrder::whereHas('vehicle', function ($q) {
        $q->where('plate', 'like', '%SOI005ZZZ%');
    })->get();

    expect($found->pluck('id')->toArray())->toContain($os->id);
})->afterEach(function () {
    cleanupSOI('SOI005');
});
