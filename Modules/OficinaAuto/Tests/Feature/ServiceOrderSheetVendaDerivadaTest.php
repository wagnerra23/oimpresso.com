<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Pest — ADR 0192 · Integração Vendas × Oficina A1 KB-9.75 (OficinaAuto · feat/shared-venda-derivada-card).
 *
 * Garante que o endpoint JSON do drawer ServiceOrderSheet retorna o shape
 * `venda_derivada` consumido pelo VendaDerivadaCard shared
 * (@/Components/shared/VendaDerivadaCard.tsx) — gêmeo da implementação Repair
 * (ProducaoOficinaController::buildVendaDerivadaPayload).
 *
 * V0 core (Onda 5 shape): id + invoice_no + final_total + transaction_date.
 * FASE B (items_list + items_summary + fiscal NF-e) é gap conhecido pra wave
 * futura — exige join sell_lines + NfeBrasil que ainda não está cabeado no
 * OficinaAuto/ServiceOrderController.
 *
 * Cenários:
 *   (1) OS sem transaction_id → JSON venda_derivada=null (degrade gracioso)
 *   (2) OS com transaction_id → JSON venda_derivada populado com 4 campos core
 *   (3) Multi-tenant Tier 0: biz=1 não vê transaction biz=2 (ADR 0093)
 *
 * Refs:
 *  - Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php::show (json branch)
 *  - Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php::shapeVendaDerivada
 *  - resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderSheet.tsx (consumidor)
 *  - resources/js/Components/shared/VendaDerivadaCard.tsx (componente shared)
 *  - memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 *  - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const BIZ_WAGNER_VD = 1;
const BIZ_FICTICIO_VD = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles') || ! Schema::hasTable('transactions')) {
        $this->markTestSkipped('Tables missing — rode migrate primeiro');
    }
});

function vendaDerivadaCreateVehicle(int $businessId, string $plate): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $businessId,
        'plate'        => $plate,
        'vehicle_type' => 'automovel',
    ]);
}

function vendaDerivadaCreateTransaction(int $businessId, string $osRef, float $total): Transaction
{
    // Cria Transaction shape mínimo pra teste — espelha o que ServiceOrderObserver
    // criaria via Sells\\TransactionUtil mas sem invocar Observer (evita side-effects
    // tipo NfeBrasil dispatch). Multi-tenant Tier 0 via business_id explícito.
    return Transaction::create([
        'business_id'    => $businessId,
        'location_id'    => 1,  // assume location id=1 existe pro business
        'type'           => 'sell',
        'status'         => 'final',
        'payment_status' => 'due',
        'source'         => 'oficina',
        'os_ref'         => $osRef,
        'invoice_no'     => 'VD-TEST-'.uniqid(),
        'final_total'    => $total,
        'transaction_date' => now(),
        'created_by'     => 1,
    ]);
}

it('OS sem transaction → JSON venda_derivada null (degrade gracioso)', function () {
    session(['user.business_id' => BIZ_WAGNER_VD]);

    $vehicle = vendaDerivadaCreateVehicle(BIZ_WAGNER_VD, 'VDTEST01');
    $os = ServiceOrder::create([
        'business_id'  => BIZ_WAGNER_VD,
        'vehicle_id'   => $vehicle->id,
        'status'       => 'aberta',
        'transaction_id' => null,
    ]);

    $response = $this->actingAs(\App\User::factory()->create(['business_id' => BIZ_WAGNER_VD]))
        ->getJson('/oficina-auto/ordens-servico/'.$os->id);

    $response->assertOk();
    expect($response->json('venda_derivada'))->toBeNull();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()
        ->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'VDTEST01'))
        ->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'VDTEST01')->forceDelete();
});

it('OS com transaction → JSON venda_derivada populado (4 campos core ADR 0192 V0)', function () {
    session(['user.business_id' => BIZ_WAGNER_VD]);

    $vehicle = vendaDerivadaCreateVehicle(BIZ_WAGNER_VD, 'VDTEST02');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_VD,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'concluida',
    ]);
    $tx = vendaDerivadaCreateTransaction(BIZ_WAGNER_VD, 'SO-'.$os->id, 1234.56);
    $os->update(['transaction_id' => $tx->id]);

    $response = $this->actingAs(\App\User::factory()->create(['business_id' => BIZ_WAGNER_VD]))
        ->getJson('/oficina-auto/ordens-servico/'.$os->id);

    $response->assertOk();
    $vd = $response->json('venda_derivada');
    expect($vd)->not->toBeNull();
    expect($vd)->toHaveKeys(['id', 'invoice_no', 'final_total', 'transaction_date']);
    expect($vd['id'])->toBe($tx->id);
    expect($vd['invoice_no'])->toBe($tx->invoice_no);
    expect((float) $vd['final_total'])->toBe(1234.56);
    expect($vd['transaction_date'])->toBe(now()->toDateString());
})->afterEach(function () {
    $vs = Vehicle::withoutGlobalScopes()->where('plate', 'VDTEST02')->get();
    foreach ($vs as $v) {
        ServiceOrder::withoutGlobalScopes()
            ->where('vehicle_id', $v->id)
            ->get()
            ->each(function (ServiceOrder $so) {
                if ($so->transaction_id) {
                    Transaction::where('id', $so->transaction_id)->delete();
                }
                $so->forceDelete();
            });
        $v->forceDelete();
    }
});

it('Multi-tenant Tier 0: OS biz=1 com transaction biz=2 retorna venda_derivada null (ADR 0093)', function () {
    // Cenário adversarial — alguém forçou FK cross-tenant em DB (race condition
    // ou bug histórico). O `load('transaction:...')` respeita global scope da
    // Transaction model, então biz=1 vendo OS com transaction_id apontando pra
    // biz=2 deve resolver Transaction como null (não vaza dado).
    session(['user.business_id' => BIZ_WAGNER_VD]);

    $vehicle = vendaDerivadaCreateVehicle(BIZ_WAGNER_VD, 'VDTEST03');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_VD,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'concluida',
    ]);
    // Cria Transaction num biz DIFERENTE (forçando o bug adversarial).
    $txOtherBiz = vendaDerivadaCreateTransaction(BIZ_FICTICIO_VD, 'SO-'.$os->id, 999.99);
    // FK explicit cross-tenant sem global scope (simula o pior caso DB-level).
    DB::table('service_orders')->where('id', $os->id)->update(['transaction_id' => $txOtherBiz->id]);

    $response = $this->actingAs(\App\User::factory()->create(['business_id' => BIZ_WAGNER_VD]))
        ->getJson('/oficina-auto/ordens-servico/'.$os->id);

    $response->assertOk();
    // GLOBAL SCOPE Transaction deve resolver Transaction belongsTo como null
    // porque o tenant ativo (biz=1) não enxerga Transaction biz=99.
    expect($response->json('venda_derivada'))->toBeNull();
})->afterEach(function () {
    $vs = Vehicle::withoutGlobalScopes()->where('plate', 'VDTEST03')->get();
    foreach ($vs as $v) {
        ServiceOrder::withoutGlobalScopes()
            ->where('vehicle_id', $v->id)
            ->get()
            ->each(function (ServiceOrder $so) {
                if ($so->transaction_id) {
                    Transaction::where('id', $so->transaction_id)->delete();
                }
                $so->forceDelete();
            });
        $v->forceDelete();
    }
});
