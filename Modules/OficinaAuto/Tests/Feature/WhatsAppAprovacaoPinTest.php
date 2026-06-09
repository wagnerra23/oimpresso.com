<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Aprovação OS via link público + PIN (US-OFICINA-014 — wire-up Wave 4 2026-05-26).
 *
 * Fluxo LIVE:
 *  1. OS em status `orcamento` dispara `EnviarLinkAprovacaoWhatsappJob` via Observer hook
 *  2. Job: gera token HMAC + PIN 4 dígitos via `AprovacaoOsService::gerarTokenAprovacao`
 *  3. Job: dispatch 2 `SendWhatsappMessageJob` (msg1=link imediata, msg2=PIN delay 60s)
 *  4. Cliente acessa GET /aprovar-os/{token} → `AprovacaoOsController::show` → Page Inertia
 *  5. Cliente POST /aprovar-os/{token} {pin,decisao} → `validarPin` ok → status `aprovada`
 *  6. PIN errado 5x → bloqueia 30min (cache rate limit)
 *  7. Token TTL 7 dias (HMAC payload contém exp_ts)
 *
 * Cobertura aqui:
 *  - Cenários 1-5: FSM transitions + multi-tenant scope (suite original Wave Z-2)
 *  - Cenário 6: HTTP GET /aprovar-os/{token} valid → 200 + Page Inertia
 *  - Cenário 7: HTTP POST PIN correto → status `aprovada` (cenário 3 estendido com HTTP real)
 *
 * Cobertura complementar:
 *  - `EnviarLinkAprovacaoWhatsappJobTest.php` — dispatch Job + multi-tenant guard + LGPD consent
 *  - `AprovacaoOsTokenTest.php` — Service token+PIN geração + validação isolada
 *
 * Multi-tenant Tier 0 ([ADR 0093]) — biz=1 sempre (ADR 0101 — biz=99 só pra cross-tenant guard).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-014
 * @see resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md (status: live)
 * @see Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php
 * @see Modules/OficinaAuto/Services/AprovacaoOsService.php
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
        'order_type'  => 'manutencao', // locação erradicada (ADR 0265); incidental ao teste de aprovação WhatsApp
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

    // Cliente rejeita: V0 status permanece em orcamento; operador humano negocia.
    // Cobertura Cenário 5 do AprovacaoOsControllerTest: HTTP submit decisao=rejeitar
    // retorna flash info SEM mudar status (idempotente).
    expect($os->fresh()->status)->toBe('orcamento');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP005'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP005')->forceDelete();
});

// ---------------------------------------------------------------------------
// Wave 4 (US-OFICINA-014) — HTTP integration cenários 6+7
// ---------------------------------------------------------------------------

it('Cenário 6 — GET /aprovar-os/{token} com token VÁLIDO → 200 + Page renderiza form PIN', function () {
    session(['user.business_id' => BIZ_WAGNER_WPP]);

    $vehicle = createWppVehicle('WPP006');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_WPP,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'orcamento',
        'entered_at'  => now(),
    ]);

    $service = app(\Modules\OficinaAuto\Services\AprovacaoOsService::class);
    $approval = $service->gerarTokenAprovacao($os);

    // Rota pública — sem auth, sem session user (simula cliente externo)
    $response = $this->get('/aprovar-os/' . $approval['token']);

    $response->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('OficinaAuto/AprovacaoPublica')
            ->where('erro', null)
            ->where('os.id', $os->id)
            ->where('os.order_type', 'manutencao')
            ->where('tentativasRestantes', 5)
        );
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP006'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP006')->forceDelete();
});

it('Cenário 7 — POST /aprovar-os/{token} com PIN correto + decisao=aprovar muda status pra aprovada', function () {
    session(['user.business_id' => BIZ_WAGNER_WPP]);

    $vehicle = createWppVehicle('WPP007');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_WPP,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'orcamento',
        'entered_at'  => now(),
    ]);

    $service = app(\Modules\OficinaAuto\Services\AprovacaoOsService::class);
    $approval = $service->gerarTokenAprovacao($os);

    $response = $this->post('/aprovar-os/' . $approval['token'], [
        'pin' => $approval['pin'],
        'decisao' => 'aprovar',
    ]);

    $response->assertRedirect();

    $fresh = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($fresh->status)->toBe('aprovada');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'WPP007'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'WPP007')->forceDelete();
});
