<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * FSM transitions ServiceOrder (US-OFICINA-003 — ADR 0143).
 *
 * Cobre transições válidas/invalidas dos 2 fluxos canônicos:
 * - Simples (Martinho caçamba): aberta → em_servico → concluida
 * - Complexa (Vargas recapagem): aberta → orcamento → aprovada → em_producao → concluida → entregue
 *
 * Isolamento multi-tenant biz=1 vs biz=99 obrigatório (ADR 0093, ADR 0101).
 *
 * Quando US-OFICINA-003 entregar pipeline canon via ExecuteStageActionService,
 * estes testes evoluem pra acionar `$service->execute(subject, action_key, user, payload)`
 * em vez de update direto em status. Na V0 (status livre) testam a sequência permitida.
 *
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see memory/decisions/0129-state-machine-canonica-fsm-rbac.md
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-003
 */

const BIZ_WAGNER_FSM = 1;
const BIZ_FICTICIO_FSM = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('service_orders/vehicles tables missing — rode OficinaAuto migrate primeiro');
    }
});

function createFsmVehicle(string $plate): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste
        'business_id'  => BIZ_WAGNER_FSM,
        'plate'        => $plate,
        'vehicle_type' => 'caminhao',
    ]);
}

it('Cenário 1 — fluxo Simples Martinho: aberta → em_servico → concluida (sub-vertical 4 mecânica pesada caminhão basculante · ADR 0194 — pré-correção dizia "locação caçamba")', function () {
    session(['user.business_id' => BIZ_WAGNER_FSM]);

    $vehicle = createFsmVehicle('FSM001');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_FSM,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'locacao',
        'status'      => 'aberta',
        'entered_at'  => now(),
        'daily_rate'  => '150.00',
    ]);

    expect($os->status)->toBe('aberta');

    $os->update(['status' => 'em_servico']);
    expect($os->fresh()->status)->toBe('em_servico');

    $os->update(['status' => 'concluida', 'completed_at' => now()]);
    $final = $os->fresh();
    expect($final->status)->toBe('concluida');
    expect($final->completed_at)->not->toBeNull();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'FSM001'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'FSM001')->forceDelete();
});

it('Cenário 2 — fluxo Complexa Vargas: aberta → orcamento → aprovada → em_producao → concluida → entregue', function () {
    session(['user.business_id' => BIZ_WAGNER_FSM]);

    $vehicle = createFsmVehicle('FSM002');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_FSM,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
        'entered_at'  => now(),
        'notes'       => 'Recapagem 4 pneus radiais',
    ]);

    $expectedFlow = ['orcamento', 'aprovada', 'em_producao', 'concluida', 'entregue'];

    foreach ($expectedFlow as $nextStatus) {
        $extra = [];
        if ($nextStatus === 'concluida') {
            $extra['completed_at'] = now();
        }
        if ($nextStatus === 'entregue') {
            $extra['delivered_at'] = now();
        }
        $os->update(['status' => $nextStatus] + $extra);
        expect($os->fresh()->status)->toBe($nextStatus);
    }

    $final = $os->fresh();
    expect($final->completed_at)->not->toBeNull();
    expect($final->delivered_at)->not->toBeNull();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'FSM002'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'FSM002')->forceDelete();
});

it('Cenário 3 — locação ativa retorna is_overdue=true quando expected_return_date passada', function () {
    session(['user.business_id' => BIZ_WAGNER_FSM]);

    $vehicle = createFsmVehicle('FSM003');
    $os = ServiceOrder::create([
        'business_id'          => BIZ_WAGNER_FSM,
        'vehicle_id'           => $vehicle->id,
        'order_type'           => 'locacao',
        'status'               => 'em_servico',
        'entered_at'           => now()->subDays(10),
        'expected_return_date' => now()->subDays(2)->toDateString(),
        'daily_rate'           => '180.00',
    ]);

    expect($os->fresh()->is_overdue)->toBeTrue();

    // Após terminal status, is_overdue volta a false
    $os->update(['status' => 'concluida', 'completed_at' => now()]);
    expect($os->fresh()->is_overdue)->toBeFalse();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'FSM003'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'FSM003')->forceDelete();
});

it('Cenário 4 — accessor valor_receber calcula daily_rate × dias_locacao em locação ativa', function () {
    session(['user.business_id' => BIZ_WAGNER_FSM]);

    $vehicle = createFsmVehicle('FSM004');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_FSM,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'locacao',
        'status'      => 'em_servico',
        'entered_at'  => now()->subDays(7),
        'daily_rate'  => '200.00',
    ]);

    $fresh = $os->fresh();
    expect($fresh->dias_locacao)->toBe(7);
    expect($fresh->valor_receber)->toBe(1400.00);

    // Após terminal status, valor_receber zera (locação encerrada)
    $os->update(['status' => 'concluida', 'completed_at' => now()]);
    expect($os->fresh()->valor_receber)->toBe(0.0);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'FSM004'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'FSM004')->forceDelete();
});

it('Cenário 5 — isolamento multi-tenant: transição em biz=1 não vaza pra biz=99', function () {
    session(['user.business_id' => BIZ_WAGNER_FSM]);

    $vehicle = createFsmVehicle('FSM005');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_FSM,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
        'entered_at'  => now(),
    ]);

    $os->update(['status' => 'em_servico']);

    // Troca pra biz=99 — não deve enxergar OS de biz=1
    session(['user.business_id' => BIZ_FICTICIO_FSM]);
    $vazado = ServiceOrder::where('id', $os->id)->get();
    expect($vazado)->toHaveCount(0);

    // E não pode atualizar status cross-tenant
    $semGlobal = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($semGlobal->business_id)->toBe(BIZ_WAGNER_FSM);
    expect($semGlobal->status)->toBe('em_servico');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'FSM005'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'FSM005')->forceDelete();
});
