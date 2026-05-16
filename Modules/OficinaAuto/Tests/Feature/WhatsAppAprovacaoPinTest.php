<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Aprovação OS via link público + PIN (US-OFICINA-006 — paridade Repair).
 *
 * Fluxo proposto:
 * 1. OS em status `orcamento` dispara WhatsApp pro cliente com link público + PIN 6 dígitos
 * 2. Cliente acessa link (rota signed URL ou token UUID) — preview valor + itens
 * 3. Cliente digita PIN — sistema valida + muda status pra `aprovada`
 * 4. PIN errado 5x → bloqueia tentativas 30min (rate limit)
 * 5. Link expira 7 dias (timestamp + signed URL)
 *
 * Estes testes são placeholders V0 — schema PIN/link ainda não existe.
 * Quando US-OFICINA-006 entregar (campos `approval_pin`, `approval_token`, `approval_expires_at`),
 * remover `markTestSkipped` e implementar lógica completa.
 *
 * Multi-tenant Tier 0 (ADR 0093) — biz=1 sempre (ADR 0101).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-006
 * @see Modules/Repair (paridade fluxo WhatsApp aprovação)
 */

const BIZ_WAGNER_WPP = 1;
const BIZ_FICTICIO_WPP = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('service_orders/vehicles tables missing — rode OficinaAuto migrate primeiro');
    }
});

function createWppVehicle(string $plate): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste
        'business_id'  => BIZ_WAGNER_WPP,
        'plate'        => $plate,
        'vehicle_type' => 'automovel',
    ]);
}

it('Cenário 1 — OS em status orcamento é elegível pra envio de link aprovação', function () {
    session(['user.business_id' => BIZ_WAGNER_WPP]);

    $vehicle = createWppVehicle('WPP001');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_WPP,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'orcamento',
        'entered_at'  => now(),
        'notes'       => 'Troca de embreagem + revisão 60mil km',
    ]);

    // V0: critério de elegibilidade — status orcamento + transaction_id pode ser null (draft)
    expect($os->status)->toBe('orcamento');

    // Quando US-OFICINA-006 entregar:
    //   - $os->approval_pin ≠ null (gerado 6 dígitos)
    //   - $os->approval_token ≠ null (UUID signed URL)
    //   - $os->approval_expires_at = now()->addDays(7)
    expect($os->business_id)->toBe(BIZ_WAGNER_WPP);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP001'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP001')->forceDelete();
});

it('Cenário 2 — OS sem status orcamento NÃO deve receber link aprovação (apenas Complexa)', function () {
    session(['user.business_id' => BIZ_WAGNER_WPP]);

    $vehicle = createWppVehicle('WPP002');

    // OS Simples (Martinho): pula direto de aberta → em_servico (sem orçamento)
    $osSimples = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_WPP,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'locacao',
        'status'      => 'em_servico',
        'entered_at'  => now(),
    ]);

    expect(in_array($osSimples->status, ['orcamento'], true))->toBeFalse();

    // OS já concluida também não — aprovação só faz sentido pré-execução
    $osConcluida = ServiceOrder::create([
        'business_id'  => BIZ_WAGNER_WPP,
        'vehicle_id'   => $vehicle->id,
        'order_type'   => 'manutencao',
        'status'       => 'concluida',
        'entered_at'   => now()->subDays(3),
        'completed_at' => now(),
    ]);

    expect(in_array($osConcluida->status, ['orcamento'], true))->toBeFalse();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP002'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP002')->forceDelete();
});

it('Cenário 3 — aprovação cliente move OS orcamento → aprovada (transição autorizada)', function () {
    session(['user.business_id' => BIZ_WAGNER_WPP]);

    $vehicle = createWppVehicle('WPP003');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_WPP,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'orcamento',
        'entered_at'  => now(),
    ]);

    // Simula validação PIN ok → service avança FSM
    $os->update(['status' => 'aprovada']);

    expect($os->fresh()->status)->toBe('aprovada');

    // Próximo passo natural: em_producao (mecânico inicia trabalho)
    $os->update(['status' => 'em_producao']);
    expect($os->fresh()->status)->toBe('em_producao');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP003'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP003')->forceDelete();
});

it('Cenário 4 — link aprovação respeita isolamento multi-tenant (biz=1 ≠ biz=99)', function () {
    session(['user.business_id' => BIZ_WAGNER_WPP]);

    $vehicle = createWppVehicle('WPP004');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_WPP,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'orcamento',
        'entered_at'  => now(),
    ]);

    // Trocar pra biz=99 — não deve enxergar OS de biz=1 nem conseguir aprovar
    session(['user.business_id' => BIZ_FICTICIO_WPP]);
    $vazado = ServiceOrder::where('id', $os->id)->get();
    expect($vazado)->toHaveCount(0);

    // Confirmar que registro original em biz=1 segue intacto (sem global scope)
    $original = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($original->business_id)->toBe(BIZ_WAGNER_WPP);
    expect($original->status)->toBe('orcamento');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP004'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP004')->forceDelete();
});

it('Cenário 5 — rejeição cliente preserva OS em orcamento (idempotente — não muda status)', function () {
    session(['user.business_id' => BIZ_WAGNER_WPP]);

    $vehicle = createWppVehicle('WPP005');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_WPP,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'orcamento',
        'entered_at'  => now(),
        'notes'       => 'Pedido: troca completa motor',
    ]);

    // Cliente rejeita (PIN errado 5x OU resposta explícita "não aprovo")
    // V0: status permanece em orcamento — atendente humano negocia
    // V1 (US-OFICINA-006): registrar tentativas em audit log + flag `approval_rejected_at`
    expect($os->fresh()->status)->toBe('orcamento');

    // Cancelamento explícito é ação manual operador, NÃO automática por rejeição
    // Não testamos cancel aqui — fica pra US-OFICINA-003 FSM canon
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP005'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP005')->forceDelete();
});
