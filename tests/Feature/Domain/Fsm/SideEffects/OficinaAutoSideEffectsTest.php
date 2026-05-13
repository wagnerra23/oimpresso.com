<?php

declare(strict_types=1);

use App\Domain\Fsm\SideEffects\CancelarServicoCacamba;
use App\Domain\Fsm\SideEffects\ConcluirServicoCacamba;
use App\Domain\Fsm\SideEffects\EnviarCacambaManutencao;
use App\Domain\Fsm\SideEffects\IniciarLocacaoCacamba;
use App\Domain\Fsm\SideEffects\IniciarServicoCacamba;
use App\Domain\Fsm\SideEffects\RecolherCacamba;
use App\Domain\Fsm\SideEffects\VoltarCacambaDisponivel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * Wave 5-A — 7 SideEffects FSM Modules/OficinaAuto (process cacamba_locacao + cacamba_manutencao).
 *
 * Specs:
 *   1. IniciarLocacaoCacamba — vehicle vira 'locada' + current_rental_id = SO.id
 *   2. RecolherCacamba — vehicle vira 'disponivel' + current_rental_id null
 *   3. EnviarCacambaManutencao (subject=Vehicle) — current_status='manutencao', rental_id null
 *   4. VoltarCacambaDisponivel (subject=Vehicle) — vira 'disponivel' + rental_id null
 *   5. IniciarServicoCacamba — garante vehicle.current_status='manutencao'
 *   6. ConcluirServicoCacamba — vehicle volta 'disponivel' se nenhuma OS manutenção ativa restante
 *   7. CancelarServicoCacamba — idem 6 (mesmo recálculo)
 *   8. Cross-tenant biz=99 — SideEffect biz=99 NÃO afeta vehicles biz=1
 *   9. Idempotência — chamar 2× resulta no mesmo state final
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 default + biz=99 cross-tenant (ADR 0101).
 *
 * Refs:
 *   - app/Domain/Fsm/SideEffects/{IniciarLocacaoCacamba,RecolherCacamba,...}.php
 *   - SPEC.md US-OFICINA-003
 *   - Wave 5-A PR #723
 */

beforeEach(function () {
    // ── Schema mínimo SQLite in-memory (já com colunas Wave 5-A pra testar contrato) ──

    Schema::create('vehicles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('contact_id')->nullable();
        $t->string('plate', 10);
        $t->string('vehicle_type', 30)->default('automovel');
        // ── Wave 5-A fields (testando contrato esperado pelos SideEffects) ──
        $t->string('current_status', 30)->default('disponivel');
        $t->unsignedBigInteger('current_rental_id')->nullable();
        // ────────────────────────────────────────────────────────────────────
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('service_orders', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('vehicle_id')->index();
        $t->unsignedBigInteger('transaction_id')->nullable();
        $t->string('status', 30)->default('aberta');
        $t->string('order_type', 30)->default('manutencao'); // Wave 5-A
        $t->text('notes')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
});

afterEach(function () {
    Schema::dropIfExists('service_orders');
    Schema::dropIfExists('vehicles');
});

// ── Helpers ────────────────────────────────────────────────────────────────

function oaSideHelperVehicle(int $bizId, string $plate = 'AAA1234', string $status = 'disponivel'): Vehicle
{
    $v = new Vehicle();
    $v->business_id = $bizId;
    $v->plate = $plate;
    $v->vehicle_type = 'cacamba_estacionaria';
    $v->save();

    // Override default 'disponivel' se preciso
    DB::table('vehicles')->where('id', $v->id)->update(['current_status' => $status]);
    $v->refresh();

    return $v;
}

function oaSideHelperServiceOrder(int $bizId, int $vehicleId, string $status = 'aberta', string $orderType = 'manutencao'): ServiceOrder
{
    $so = new ServiceOrder();
    $so->business_id = $bizId;
    $so->vehicle_id = $vehicleId;
    $so->status = $status;
    $so->save();

    DB::table('service_orders')->where('id', $so->id)->update(['order_type' => $orderType]);
    $so->refresh();

    return $so;
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. IniciarLocacaoCacamba — vehicle vira locada + current_rental_id = SO.id', function () {
    $vehicle = oaSideHelperVehicle(1, 'LOC1001');
    $so = oaSideHelperServiceOrder(1, $vehicle->id, 'aberta', 'locacao');

    Log::spy();

    (new IniciarLocacaoCacamba())->execute($so);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('locada')
        ->and((int) $row->current_rental_id)->toBe($so->id);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'IniciarLocacaoCacamba')
            && $ctx['business_id'] === 1
            && $ctx['vehicle_id'] === $vehicle->id);
});

it('2. RecolherCacamba — vehicle vira disponivel + current_rental_id null', function () {
    $vehicle = oaSideHelperVehicle(1, 'REC1001', 'locada');
    $so = oaSideHelperServiceOrder(1, $vehicle->id, 'em_servico', 'locacao');
    DB::table('vehicles')->where('id', $vehicle->id)->update(['current_rental_id' => $so->id]);

    Log::spy();

    (new RecolherCacamba())->execute($so);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('disponivel')
        ->and($row->current_rental_id)->toBeNull();

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'RecolherCacamba'));
});

it('3. EnviarCacambaManutencao (subject=Vehicle) — vira manutencao + rental_id null', function () {
    $vehicle = oaSideHelperVehicle(1, 'MAN1001', 'locada');
    DB::table('vehicles')->where('id', $vehicle->id)->update(['current_rental_id' => 999]);

    Log::spy();

    (new EnviarCacambaManutencao())->execute($vehicle, ['motivo' => 'pneu furado']);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('manutencao')
        ->and($row->current_rental_id)->toBeNull();

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'EnviarCacambaManutencao')
            && ($ctx['motivo'] ?? null) === 'pneu furado');
});

it('4. VoltarCacambaDisponivel (subject=Vehicle) — vira disponivel + rental_id null', function () {
    $vehicle = oaSideHelperVehicle(1, 'VLT1001', 'manutencao');

    Log::spy();

    (new VoltarCacambaDisponivel())->execute($vehicle);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('disponivel')
        ->and($row->current_rental_id)->toBeNull();

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'VoltarCacambaDisponivel'));
});

it('5. IniciarServicoCacamba — garante vehicle.current_status=manutencao', function () {
    $vehicle = oaSideHelperVehicle(1, 'SRV1001', 'disponivel'); // ainda não enviado pra manutencao
    $so = oaSideHelperServiceOrder(1, $vehicle->id, 'aberta', 'manutencao');

    Log::spy();

    (new IniciarServicoCacamba())->execute($so);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('manutencao');

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'IniciarServicoCacamba'));
});

it('6. ConcluirServicoCacamba — vehicle volta disponivel se nenhuma OS manutencao ativa restante', function () {
    $vehicle = oaSideHelperVehicle(1, 'CCL1001', 'manutencao');
    $so = oaSideHelperServiceOrder(1, $vehicle->id, 'em_servico', 'manutencao');

    Log::spy();

    // Marca status como concluida ANTES de chamar SideEffect (FSM já transicionou no service)
    DB::table('service_orders')->where('id', $so->id)->update(['status' => 'concluida']);

    (new ConcluirServicoCacamba())->execute($so);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('disponivel');

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'ConcluirServicoCacamba'));
});

it('6b. ConcluirServicoCacamba — mantém manutencao se outras OS manutenção ainda ativas', function () {
    $vehicle = oaSideHelperVehicle(1, 'CCL2001', 'manutencao');
    $so1 = oaSideHelperServiceOrder(1, $vehicle->id, 'em_servico', 'manutencao');
    // Outra OS de manutenção ativa pra mesmo vehicle
    oaSideHelperServiceOrder(1, $vehicle->id, 'em_servico', 'manutencao');

    DB::table('service_orders')->where('id', $so1->id)->update(['status' => 'concluida']);

    (new ConcluirServicoCacamba())->execute($so1);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('manutencao');
});

it('7. CancelarServicoCacamba — vehicle volta disponivel se nenhuma OS manutencao ativa restante', function () {
    $vehicle = oaSideHelperVehicle(1, 'CNC1001', 'manutencao');
    $so = oaSideHelperServiceOrder(1, $vehicle->id, 'em_servico', 'manutencao');

    Log::spy();

    DB::table('service_orders')->where('id', $so->id)->update(['status' => 'cancelada']);

    (new CancelarServicoCacamba())->execute($so, ['motivo' => 'cliente desistiu']);

    $row = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($row->current_status)->toBe('disponivel');

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'CancelarServicoCacamba')
            && ($ctx['motivo'] ?? null) === 'cliente desistiu');
});

it('8. cross-tenant — SideEffect biz=99 NÃO afeta vehicles biz=1 (Tier 0 isolation)', function () {
    // Setup biz=1
    $vehicle1 = oaSideHelperVehicle(1, 'BIZ1001', 'disponivel');

    // Setup biz=99 + SO biz=99 referenciando vehicle biz=99
    $vehicle99 = oaSideHelperVehicle(99, 'BIZ9901', 'disponivel');
    $so99 = oaSideHelperServiceOrder(99, $vehicle99->id, 'aberta', 'locacao');

    (new IniciarLocacaoCacamba())->execute($so99);

    // biz=99 mudou
    $row99 = DB::table('vehicles')->where('id', $vehicle99->id)->first();
    expect($row99->current_status)->toBe('locada');

    // biz=1 INTOCADO
    $row1 = DB::table('vehicles')->where('id', $vehicle1->id)->first();
    expect($row1->current_status)->toBe('disponivel')
        ->and($row1->current_rental_id)->toBeNull();
});

it('8b. cross-tenant — SideEffect bloqueia se ServiceOrder.business_id != Vehicle.business_id', function () {
    // Vehicle no biz=1
    $vehicle1 = oaSideHelperVehicle(1, 'BIZ1002', 'disponivel');

    // ServiceOrder forjada apontando pra vehicle do biz=1 mas declarando-se biz=99
    $so = new ServiceOrder();
    $so->business_id = 99;
    $so->vehicle_id = $vehicle1->id; // ← cross-tenant violation
    $so->status = 'aberta';
    $so->save();
    DB::table('service_orders')->where('id', $so->id)->update(['order_type' => 'locacao']);
    $so->refresh();

    expect(fn () => (new IniciarLocacaoCacamba())->execute($so))
        ->toThrow(InvalidArgumentException::class, 'não encontrado no business 99');

    // Vehicle biz=1 NÃO foi tocado
    $row = DB::table('vehicles')->where('id', $vehicle1->id)->first();
    expect($row->current_status)->toBe('disponivel');
});

it('9. idempotência — chamar 2× IniciarLocacaoCacamba resulta no mesmo state', function () {
    $vehicle = oaSideHelperVehicle(1, 'IDP1001');
    $so = oaSideHelperServiceOrder(1, $vehicle->id, 'aberta', 'locacao');

    (new IniciarLocacaoCacamba())->execute($so);
    $first = DB::table('vehicles')->where('id', $vehicle->id)->first();

    (new IniciarLocacaoCacamba())->execute($so);
    $second = DB::table('vehicles')->where('id', $vehicle->id)->first();

    expect($second->current_status)->toBe($first->current_status)
        ->and((int) $second->current_rental_id)->toBe((int) $first->current_rental_id)
        ->and($second->current_status)->toBe('locada');
});
