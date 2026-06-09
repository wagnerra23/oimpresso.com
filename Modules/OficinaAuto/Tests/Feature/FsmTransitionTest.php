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

defined('BIZ_WAGNER_FSM') || define('BIZ_WAGNER_FSM', 1);
defined('BIZ_FICTICIO_FSM') || define('BIZ_FICTICIO_FSM', 99);

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
        'order_type'  => 'manutencao', // locação erradicada (ADR 0265); cenário testa fluxo de status, não o tipo
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

// Cenários 3 e 4 (is_overdue / valor_receber de locação ativa) REMOVIDOS — locação
// erradicada (ADR 0265). Os accessors `is_overdue`/`valor_receber` agora são sempre
// false/0.0 (cobertura do estado pós-erradicação fica em CacambaSchemaTest, casos
// order_type=manutencao). Atraso de reparo vive no ServiceOrderController via
// expected_completion. Repontuar o kanban de Caçambas é dívida F3 charter v4.

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
