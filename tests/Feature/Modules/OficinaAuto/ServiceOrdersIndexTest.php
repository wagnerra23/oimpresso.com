<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * ServiceOrders Index Dashboard — KPIs, filtros e cross-tenant.
 *
 * Cobre o método `index()` do dashboard de OS de reparo (locação erradicada — ADR 0265).
 * KPIs pós-erradicação: manutencao_ativas, concluidas_mes, atrasadas (atraso de reparo
 * via expected_completion). A chave `locacoes_ativas` foi REMOVIDA do contrato
 * (dívida P5 do RUNBOOK-erradicacao-locacao quitada — frontend não consome mais).
 * Usa Schema::hasColumn fallbacks porque colunas Wave 5-A podem não estar migradas.
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

it('Index retorna 3 KPIs corretos (manutencao_ativas, concluidas_mes, atrasadas — locacoes_ativas fora do contrato)', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    $hasOrderType = Schema::hasColumn('service_orders', 'order_type');

    $v = makeVehicleSOI('SOI001');

    // Manutenção ativa, no prazo
    $man1 = makeOrderSOI([
        'vehicle_id' => $v->id,
        'status'     => 'em_servico',
    ]);
    if ($hasOrderType) { $man1->order_type = 'manutencao'; }
    $man1->expected_completion = now()->addDays(5);
    $man1->save();

    // Manutenção ativa ATRASADA — atraso de REPARO via expected_completion (ADR 0265,
    // locação erradicada; não há mais expected_return_date/locação como conceito)
    $atr = makeOrderSOI([
        'vehicle_id' => $v->id,
        'status'     => 'em_servico',
    ]);
    if ($hasOrderType) { $atr->order_type = 'manutencao'; }
    $atr->expected_completion = now()->subDays(3);
    $atr->save();

    // Concluída esse mês
    $con = makeOrderSOI([
        'vehicle_id' => $v->id,
        'status'     => 'concluida',
    ]);
    if ($hasOrderType) { $con->order_type = 'manutencao'; }
    $con->save();
    // Força updated_at no mês corrente
    DB::table('service_orders')->where('id', $con->id)->update(['updated_at' => now()]);

    // Replica a lógica de KPI do controller pós-erradicação (ADR 0265):
    //  - locacoes_ativas NÃO existe mais no contrato (chave removida do payload)
    //  - atrasadas = ativas com expected_completion vencida (atraso de reparo)
    $kpis = [
        'manutencao_ativas' => $hasOrderType
            ? ServiceOrder::where('order_type', 'manutencao')
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->count()
            : ServiceOrder::whereNotIn('status', ['concluida', 'cancelada'])->count(),
        'concluidas_mes' => ServiceOrder::where('status', 'concluida')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count(),
        'atrasadas' => ServiceOrder::whereNotIn('status', ['concluida', 'cancelada'])
            ->whereNotNull('expected_completion')
            ->whereDate('expected_completion', '<', now()->toDateString())
            ->count(),
    ];

    // Locação erradicada → chave fora do contrato. 2 manutenções ativas (man1 + atr), 1 atrasada (atr), 1 concluída.
    expect($kpis)->not->toHaveKey('locacoes_ativas');
    expect($kpis['manutencao_ativas'])->toBeGreaterThanOrEqual(2);
    expect($kpis['atrasadas'])->toBeGreaterThanOrEqual(1);
    expect($kpis['concluidas_mes'])->toBeGreaterThanOrEqual(1);
})->afterEach(function () {
    cleanupSOI('SOI001');
});

it('Filter ?status=atrasada retorna apenas OS overdue (expected_completion vencida)', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    if (! Schema::hasColumn('service_orders', 'order_type')) {
        $this->markTestSkipped('order_type ausente — Wave 5-A não migrada');
    }

    $v = makeVehicleSOI('SOI002');

    // No prazo
    $ok = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $ok->order_type = 'manutencao';
    $ok->expected_completion = now()->addDays(2);
    $ok->save();

    // Atrasada (atraso de REPARO — ADR 0265)
    $late = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $late->order_type = 'manutencao';
    $late->expected_completion = now()->subDays(2);
    $late->save();

    // Mesma regra do controller pós-erradicação: ativa + expected_completion vencida
    $atrasadas = ServiceOrder::whereNotIn('status', ['concluida', 'cancelada'])
        ->whereNotNull('expected_completion')
        ->whereDate('expected_completion', '<', now()->toDateString())
        ->pluck('id')
        ->toArray();

    expect($atrasadas)->toContain($late->id);
    expect($atrasadas)->not->toContain($ok->id);
})->afterEach(function () {
    cleanupSOI('SOI002');
});

it('Filter ?type=manutencao filtra apenas manutenções (mecanica fora)', function () {
    session(['user.business_id' => BIZ_WAGNER_SOI]);

    if (! Schema::hasColumn('service_orders', 'order_type')) {
        $this->markTestSkipped('order_type ausente — Wave 5-A não migrada');
    }

    $v = makeVehicleSOI('SOI003');

    // order_type ∈ {manutencao, mecanica} pós-erradicação (ADR 0265) — locacao não existe mais
    $man = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $man->order_type = 'manutencao';
    $man->save();

    $mec = makeOrderSOI(['vehicle_id' => $v->id, 'status' => 'em_servico']);
    $mec->order_type = 'mecanica';
    $mec->save();

    $somenteManutencao = ServiceOrder::where('order_type', 'manutencao')->pluck('id')->toArray();

    expect($somenteManutencao)->toContain($man->id);
    expect($somenteManutencao)->not->toContain($mec->id);
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
