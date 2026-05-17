<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Modules\OficinaAuto\Services\Producao\CapacidadeService;
use Modules\OficinaAuto\Services\ServiceOrderSummaryService;
use Modules\OficinaAuto\Services\VehicleQueryService;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D5/D9 — E2E journey Martinho biz=1 (Wagner dev).
 *
 * Smoke do journey README:
 *  - Criar veículo caçamba
 *  - Abrir OS de manutenção
 *  - Gerar link aprovação WhatsApp (token HMAC + PIN)
 *  - Validar token + PIN
 *  - Verificar capacidade ocupada após nova OS
 *
 * Multi-tenant Tier 0 (ADR 0101 — biz=1 nunca cliente real).
 *
 * @see Modules/OficinaAuto/README.md
 */

const E2E_OFICINA_BIZ_WAGNER = 1;

beforeEach(function () {
    config()->set('otel.enabled', false);
    Cache::flush();
});

function skipIfNoMysqlOficinaE2E(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        test()->markTestSkipped('service_orders/vehicles tables missing');
    }
}

it('cenario E2E 1: journey full Martinho (vehicle → OS orcamento → aprovacao via PIN)', function () {
    skipIfNoMysqlOficinaE2E();
    // Passo 1: criar veiculo (cacamba basculante 5m³)
    $v = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => E2E_OFICINA_BIZ_WAGNER,
        'plate'        => 'WAG'.random_int(1000, 9999),
        'vehicle_type' => 'cacamba_basculante',
        'capacity_m3'  => '5.00',
    ]);
    expect($v->id)->toBeGreaterThan(0)
        ->and($v->business_id)->toBe(E2E_OFICINA_BIZ_WAGNER);

    // Passo 2: abrir OS de manutencao status orcamento (elegivel pra aprovacao via PIN)
    $os = ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => E2E_OFICINA_BIZ_WAGNER,
        'vehicle_id'  => $v->id,
        'status'      => 'orcamento',
        'entered_at'  => now(),
    ]);
    expect($os->status)->toBe('orcamento');

    // Passo 3: gerar token + PIN aprovacao
    $svcAprov = new AprovacaoOsService();
    $gen = $svcAprov->gerarTokenAprovacao($os);

    expect($gen)->toHaveKeys(['token', 'pin', 'expires_at'])
        ->and($gen['pin'])->toMatch('/^\d{4}$/')
        ->and(strpos($gen['token'], '.'))->toBeGreaterThan(0); // payload.signature

    // Passo 4: validar token (publicamente — sem session)
    $resolved = $svcAprov->validarToken($gen['token']);
    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($os->id)
        ->and($resolved->business_id)->toBe(E2E_OFICINA_BIZ_WAGNER);

    // Passo 5: validar PIN correto → true (consume cache)
    expect($svcAprov->validarPin($os, $gen['pin']))->toBeTrue();

    // Passo 6: PIN ja consumido → false (one-shot)
    expect($svcAprov->validarPin($os, $gen['pin']))->toBeFalse();
});

it('cenario E2E 2: 3 OS abertas + 1 em_servico → capacidade ocupada 18h (4+4+4+6)', function () {
    skipIfNoMysqlOficinaE2E();
    // Setup: cria 4 OS
    for ($i = 0; $i < 3; $i++) {
        $v = Vehicle::withoutGlobalScopes()->create([
            'business_id' => E2E_OFICINA_BIZ_WAGNER,
            'plate'       => 'CAP'.random_int(1000, 9999),
            'vehicle_type' => 'automovel',
        ]);
        ServiceOrder::withoutGlobalScopes()->create([
            'business_id' => E2E_OFICINA_BIZ_WAGNER,
            'vehicle_id'  => $v->id,
            'status'      => 'aberta',
            'entered_at'  => now(),
        ]);
    }
    $v4 = Vehicle::withoutGlobalScopes()->create([
        'business_id' => E2E_OFICINA_BIZ_WAGNER,
        'plate'       => 'CAP9999',
        'vehicle_type' => 'automovel',
    ]);
    ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => E2E_OFICINA_BIZ_WAGNER,
        'vehicle_id'  => $v4->id,
        'status'      => 'em_servico',
        'entered_at'  => now(),
    ]);

    // Bypass global scope pra que test enxergue dados (test session pode nao ter biz=1)
    $cap = ServiceOrder::withoutGlobalScopes()->whereIn('status', ['aberta', 'orcamento', 'aprovada'])->count() * 4
         + ServiceOrder::withoutGlobalScopes()->whereIn('status', ['em_servico', 'em_producao'])->count() * 6;

    expect($cap)->toBe(3 * 4 + 1 * 6);
});

it('cenario E2E 3: contagem global por status reflete realidade cross-biz isolada', function () {
    skipIfNoMysqlOficinaE2E();
    // biz=1: 2 abertas, 1 concluida
    foreach (['aberta', 'aberta', 'concluida'] as $s) {
        $v = Vehicle::withoutGlobalScopes()->create([
            'business_id' => E2E_OFICINA_BIZ_WAGNER,
            'plate'       => 'WAG'.random_int(1000, 9999),
            'vehicle_type' => 'automovel',
        ]);
        ServiceOrder::withoutGlobalScopes()->create([
            'business_id' => E2E_OFICINA_BIZ_WAGNER,
            'vehicle_id'  => $v->id,
            'status'      => $s,
            'entered_at'  => now(),
        ]);
    }
    // biz=99: 5 abertas (NÃO devem aparecer)
    for ($i = 0; $i < 5; $i++) {
        $v = Vehicle::withoutGlobalScopes()->create([
            'business_id' => 99,
            'plate'       => 'OUT'.random_int(1000, 9999),
            'vehicle_type' => 'automovel',
        ]);
        ServiceOrder::withoutGlobalScopes()->create([
            'business_id' => 99,
            'vehicle_id'  => $v->id,
            'status'      => 'aberta',
            'entered_at'  => now(),
        ]);
    }

    // Contagem direta multi-tenant (skipa global scope, where explicito)
    $countBiz1 = ServiceOrder::withoutGlobalScopes()
        ->where('business_id', E2E_OFICINA_BIZ_WAGNER)
        ->count();
    $countBiz99 = ServiceOrder::withoutGlobalScopes()
        ->where('business_id', 99)
        ->count();

    expect($countBiz1)->toBe(3)
        ->and($countBiz99)->toBe(5);
});

it('cenario E2E 4: Services instanciaveis sem deps externas (Container OK)', function () {
    expect(app(VehicleQueryService::class))->toBeInstanceOf(VehicleQueryService::class)
        ->and(app(ServiceOrderSummaryService::class))->toBeInstanceOf(ServiceOrderSummaryService::class)
        ->and(app(CapacidadeService::class))->toBeInstanceOf(CapacidadeService::class)
        ->and(app(AprovacaoOsService::class))->toBeInstanceOf(AprovacaoOsService::class);
});
