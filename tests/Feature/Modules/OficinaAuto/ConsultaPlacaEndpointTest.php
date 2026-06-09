<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Http\Controllers\VehicleController;
use Modules\OficinaAuto\Services\VehicleLookupService;

// Tests\TestCase já aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * VehicleController@consultaPlaca — endpoint AJAX de consulta de placa (charter v2).
 *
 * Driver default `stub` (sem rede). Valida: happy path (found + dados técnicos),
 * placa inválida (422), não-encontrada (found=false), e a invariante LGPD de que
 * a resposta NUNCA carrega proprietário.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa) — ADR 0101.
 *
 * @see Modules\OficinaAuto\Http\Controllers\VehicleController::consultaPlaca
 */

const BIZ_WAGNER_PLACA = 1;

beforeEach(function () {
    config()->set('otel.enabled', false);
    config()->set('oficina-auto.placa_lookup.driver', 'stub');

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer User biz=1 do schema UltimatePOS (ADR 0101)');
    }
});

function consultaPlacaUser(): \App\User
{
    $user = \App\User::query()->where('business_id', BIZ_WAGNER_PLACA)->first();

    if (! $user) {
        test()->markTestSkipped('User biz=1 ausente — rode UltimatePOS seeders primeiro');
    }

    return $user;
}

it('consultaPlaca devolve found=true + dados técnicos para placa válida', function () {
    session(['user.business_id' => BIZ_WAGNER_PLACA]);
    $this->actingAs(consultaPlacaUser());

    $request = \Illuminate\Http\Request::create(
        '/oficina-auto/veiculos/consulta-placa',
        'POST',
        ['plate' => 'ABC1D23']
    );

    $response = app(VehicleController::class)->consultaPlaca($request, app(VehicleLookupService::class));
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200);
    expect($payload['found'])->toBeTrue();
    expect($payload['data'])->toHaveKeys(['plate', 'brand', 'model', 'fields']);
    expect($payload['data']['fields'])->not->toBeEmpty();
});

it('consultaPlaca NUNCA devolve proprietário (escopo só dados técnicos)', function () {
    session(['user.business_id' => BIZ_WAGNER_PLACA]);
    $this->actingAs(consultaPlacaUser());

    $request = \Illuminate\Http\Request::create('/x', 'POST', ['plate' => 'ABC1D23']);
    $response = app(VehicleController::class)->consultaPlaca($request, app(VehicleLookupService::class));

    $flat = strtolower(json_encode($response->getData(true), JSON_UNESCAPED_UNICODE));

    expect($flat)->not->toContain('proprietario');
    expect($flat)->not->toContain('owner');
    expect($flat)->not->toContain('cpf');
});

it('consultaPlaca devolve 422 para placa de formato inválido', function () {
    session(['user.business_id' => BIZ_WAGNER_PLACA]);
    $this->actingAs(consultaPlacaUser());

    $request = \Illuminate\Http\Request::create('/x', 'POST', ['plate' => 'XX']);
    $response = app(VehicleController::class)->consultaPlaca($request, app(VehicleLookupService::class));

    expect($response->getStatusCode())->toBe(422);
    expect($response->getData(true)['found'])->toBeFalse();
});

it('consultaPlaca devolve found=false para placa não-encontrada (NF*)', function () {
    session(['user.business_id' => BIZ_WAGNER_PLACA]);
    $this->actingAs(consultaPlacaUser());

    $request = \Illuminate\Http\Request::create('/x', 'POST', ['plate' => 'NFA1B23']);
    $response = app(VehicleController::class)->consultaPlaca($request, app(VehicleLookupService::class));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['found'])->toBeFalse();
});
