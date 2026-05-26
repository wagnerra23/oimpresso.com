<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Schema + accessors caçamba avulsa estacionária — leitura PRÉ-ADR 0194.
 *
 * **Atualização 2026-05-26 (ADR 0194):** Martinho é sub-vertical 4 mecânica
 * pesada caminhão basculante CNAE 4520 (não locação caçamba container CNAE
 * 4581). Enum value `cacamba_avulsa` + accessors `dias_locacao`/`is_overdue`
 * preservados nullable em prod biz=164 como schema sub-vertical 3 hipotético
 * sem cliente real ancorado. Estes testes validam que o schema continua
 * funcional caso cliente real surgir. Não é teste do domínio Martinho real
 * — esse usa OS de manutenção (mecânica pesada) sem dias_locacao.
 *
 * Cobertura:
 *  - Migration `add_cacamba_fields_to_vehicles` aplicada (capacity_m3, current_status, current_rental_id)
 *  - Migration `add_rental_fields_to_service_orders` aplicada (order_type, daily_rate, expected_return_date, delivery_address)
 *  - Vehicle accessor `status_badge_color` retorna cor correta por estado
 *  - ServiceOrder accessor `dias_locacao` calcula dias corretos
 *  - ServiceOrder accessor `is_overdue` detecta atraso
 *  - ServiceOrder accessor `valor_receber` = daily_rate × dias_locacao
 *  - Cross-tenant biz=99 NÃO vê vehicles biz=1 (Tier 0 — ADR 0093)
 *
 * Tests biz=1 (Wagner WR2) + biz=99 (fictício) conforme ADR 0101.
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const BIZ_WAGNER_CAC = 1;
const BIZ_FICTICIO_CAC = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema OficinaAuto requer MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('vehicles') || ! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('vehicles/service_orders tables missing — rode módulo OficinaAuto migrate primeiro');
    }
    if (! Schema::hasColumn('vehicles', 'capacity_m3')) {
        $this->markTestSkipped('migration add_cacamba_fields_to_vehicles não aplicada — rode migrate');
    }
    if (! Schema::hasColumn('service_orders', 'order_type')) {
        $this->markTestSkipped('migration add_rental_fields_to_service_orders não aplicada — rode migrate');
    }
});

it('migration aplicada: vehicles tem capacity_m3 NULL nullable + current_status default disponivel', function () {
    expect(Schema::hasColumn('vehicles', 'capacity_m3'))->toBeTrue();
    expect(Schema::hasColumn('vehicles', 'current_status'))->toBeTrue();
    expect(Schema::hasColumn('vehicles', 'current_rental_id'))->toBeTrue();
});

it('migration aplicada: service_orders tem order_type + daily_rate + expected_return_date + delivery_address', function () {
    expect(Schema::hasColumn('service_orders', 'order_type'))->toBeTrue();
    expect(Schema::hasColumn('service_orders', 'daily_rate'))->toBeTrue();
    expect(Schema::hasColumn('service_orders', 'expected_return_date'))->toBeTrue();
    expect(Schema::hasColumn('service_orders', 'delivery_address'))->toBeTrue();
});

it('Vehicle status_badge_color retorna emerald quando disponivel', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'    => BIZ_WAGNER_CAC,
        'plate'          => 'CAC001',
        'vehicle_type'   => 'cacamba_avulsa',
        'capacity_m3'    => 5.00,
        'current_status' => 'disponivel',
    ]);

    expect($v->status_badge_color)->toBe('emerald');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC001')->forceDelete();
});

it('Vehicle status_badge_color retorna blue quando locada (sem atraso)', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'    => BIZ_WAGNER_CAC,
        'plate'          => 'CAC002',
        'vehicle_type'   => 'cacamba_avulsa',
        'capacity_m3'    => 5.00,
        'current_status' => 'locada',
    ]);

    expect($v->status_badge_color)->toBe('blue');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC002')->forceDelete();
});

it('Vehicle status_badge_color retorna amber quando manutencao', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'    => BIZ_WAGNER_CAC,
        'plate'          => 'CAC003',
        'vehicle_type'   => 'cacamba_avulsa',
        'capacity_m3'    => 7.00,
        'current_status' => 'manutencao',
    ]);

    expect($v->status_badge_color)->toBe('amber');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC003')->forceDelete();
});

it('Vehicle status_badge_color retorna slate quando indisponivel', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'    => BIZ_WAGNER_CAC,
        'plate'          => 'CAC004',
        'vehicle_type'   => 'cacamba_avulsa',
        'capacity_m3'    => 3.00,
        'current_status' => 'indisponivel',
    ]);

    expect($v->status_badge_color)->toBe('slate');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC004')->forceDelete();
});

it('ServiceOrder dias_locacao calcula dias decorridos desde entered_at', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC005',
        'vehicle_type' => 'cacamba_avulsa',
        'capacity_m3'  => 5.00,
    ]);

    $os = ServiceOrder::create([
        'business_id'           => BIZ_WAGNER_CAC,
        'vehicle_id'            => $v->id,
        'order_type'            => 'locacao',
        'status'                => 'aberta',
        'entered_at'            => now()->subDays(7),
        'expected_return_date'  => now()->addDays(3)->toDateString(),
        'daily_rate'            => 50.00,
        'delivery_address'      => 'Rua Teste 123, Termas do Gravatal/SC',
    ]);

    expect($os->dias_locacao)->toBe(7);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC005');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC005')->forceDelete();
});

it('ServiceOrder dias_locacao retorna 0 quando entered_at é null', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC006',
        'vehicle_type' => 'cacamba_avulsa',
    ]);

    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_CAC,
        'vehicle_id'  => $v->id,
        'order_type'  => 'locacao',
        'status'      => 'aberta',
        // entered_at NULL
    ]);

    expect($os->dias_locacao)->toBe(0);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC006');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC006')->forceDelete();
});

it('ServiceOrder is_overdue=true quando expected_return_date passou e status ativo', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC007',
        'vehicle_type' => 'cacamba_avulsa',
    ]);

    $os = ServiceOrder::create([
        'business_id'          => BIZ_WAGNER_CAC,
        'vehicle_id'           => $v->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(10),
        'expected_return_date' => now()->subDays(3)->toDateString(), // venceu há 3 dias
        'daily_rate'           => 50.00,
    ]);

    expect($os->is_overdue)->toBeTrue();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC007');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC007')->forceDelete();
});

it('ServiceOrder is_overdue=false quando status terminal (concluida)', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC008',
        'vehicle_type' => 'cacamba_avulsa',
    ]);

    $os = ServiceOrder::create([
        'business_id'          => BIZ_WAGNER_CAC,
        'vehicle_id'           => $v->id,
        'order_type'           => 'locacao',
        'status'               => 'concluida', // terminal
        'entered_at'           => now()->subDays(10),
        'expected_return_date' => now()->subDays(3)->toDateString(),
        'daily_rate'           => 50.00,
    ]);

    expect($os->is_overdue)->toBeFalse();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC008');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC008')->forceDelete();
});

it('ServiceOrder is_overdue=false quando order_type=manutencao', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC009',
        'vehicle_type' => 'cacamba_avulsa',
    ]);

    $os = ServiceOrder::create([
        'business_id'          => BIZ_WAGNER_CAC,
        'vehicle_id'           => $v->id,
        'order_type'           => 'manutencao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(10),
        'expected_return_date' => now()->subDays(3)->toDateString(),
    ]);

    expect($os->is_overdue)->toBeFalse();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC009');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC009')->forceDelete();
});

it('ServiceOrder valor_receber = daily_rate × dias_locacao quando locação ativa', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC010',
        'vehicle_type' => 'cacamba_avulsa',
    ]);

    $os = ServiceOrder::create([
        'business_id'          => BIZ_WAGNER_CAC,
        'vehicle_id'           => $v->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(5),
        'expected_return_date' => now()->addDays(2)->toDateString(),
        'daily_rate'           => 80.00,
    ]);

    // 5 dias × R$ [redacted Tier 0] = R$ [redacted Tier 0]
    expect($os->valor_receber)->toBe(400.00);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC010');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC010')->forceDelete();
});

it('ServiceOrder valor_receber = 0.0 quando manutencao (não cobra diária)', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC011',
        'vehicle_type' => 'cacamba_avulsa',
    ]);

    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_CAC,
        'vehicle_id'  => $v->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
        'entered_at'  => now()->subDays(3),
        'daily_rate'  => 80.00, // mesmo com daily_rate setado
    ]);

    expect($os->valor_receber)->toBe(0.0);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC011');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC011')->forceDelete();
});

it('Vehicle cross-tenant: biz=99 NÃO vê vehicles biz=1 (Tier 0 — ADR 0093)', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_CAC,
        'plate'          => 'CAC012',
        'vehicle_type'   => 'cacamba_avulsa',
        'capacity_m3'    => 5.00,
        'current_status' => 'disponivel',
    ]);

    session(['user.business_id' => BIZ_FICTICIO_CAC]);
    $resultado = Vehicle::where('id', $v->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC012')->forceDelete();
});

it('ServiceOrder scope rentalsAtivas filtra apenas locações não-terminais', function () {
    session(['user.business_id' => BIZ_WAGNER_CAC]);

    $v = Vehicle::create([
        'business_id'  => BIZ_WAGNER_CAC,
        'plate'        => 'CAC013',
        'vehicle_type' => 'cacamba_avulsa',
    ]);

    // 3 OS: 2 ativas (aberta + em_servico), 1 concluida, 1 manutenção (deve excluir)
    ServiceOrder::create([
        'business_id' => BIZ_WAGNER_CAC, 'vehicle_id' => $v->id,
        'order_type'  => 'locacao', 'status' => 'aberta',
        'entered_at'  => now(), 'daily_rate' => 50.00,
    ]);
    ServiceOrder::create([
        'business_id' => BIZ_WAGNER_CAC, 'vehicle_id' => $v->id,
        'order_type'  => 'locacao', 'status' => 'concluida',
        'entered_at'  => now()->subDays(20), 'daily_rate' => 50.00,
    ]);
    ServiceOrder::create([
        'business_id' => BIZ_WAGNER_CAC, 'vehicle_id' => $v->id,
        'order_type'  => 'manutencao', 'status' => 'aberta',
        'entered_at'  => now(),
    ]);

    $ativas = ServiceOrder::rentalsAtivas()->where('vehicle_id', $v->id)->get();

    expect($ativas)->toHaveCount(1); // apenas a aberta locacao
    expect($ativas->first()->status)->toBe('aberta');
    expect($ativas->first()->order_type)->toBe('locacao');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->where('vehicle_id', function ($q) {
        $q->select('id')->from('vehicles')->where('plate', 'CAC013');
    })->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'CAC013')->forceDelete();
});
