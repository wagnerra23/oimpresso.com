<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\OficinaAuto\Services\PlacaLookup\HttpPlacaProvider;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupException;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupResult;
use Modules\OficinaAuto\Services\PlacaLookup\StubPlacaProvider;
use Modules\OficinaAuto\Services\VehicleLookupService;

// Tests\TestCase já aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * VehicleLookupService + drivers (consulta de placa — charter Create v2).
 *
 * Não toca DB — testa a camada de serviço pura (adapter agnóstico + stub + http
 * fake + cache + escopo LGPD "só dados técnicos").
 *
 * Invariante LGPD travado: NENHUM payload pode carregar proprietário (nome/CPF).
 *
 * @see Modules\OficinaAuto\Services\VehicleLookupService
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    Cache::flush();
});

// ─── normalize / validate ──────────────────────────────────────────────────

it('normaliza placa removendo separadores e forçando uppercase', function () {
    expect(VehicleLookupService::normalizePlate('abc-1d23'))->toBe('ABC1D23');
    expect(VehicleLookupService::normalizePlate('abc 1234'))->toBe('ABC1234');
});

it('valida formato BR antiga e Mercosul, rejeita lixo', function () {
    expect(VehicleLookupService::isValidPlate('ABC1234'))->toBeTrue();  // antiga
    expect(VehicleLookupService::isValidPlate('ABC1D23'))->toBeTrue();  // Mercosul
    expect(VehicleLookupService::isValidPlate('abc-1d23'))->toBeTrue(); // normaliza antes
    expect(VehicleLookupService::isValidPlate('12345'))->toBeFalse();
    expect(VehicleLookupService::isValidPlate('ABCD123'))->toBeFalse();
});

// ─── StubPlacaProvider ───────────────────────────────────────────────────────

it('StubPlacaProvider é determinístico (mesma placa → mesmo resultado)', function () {
    $stub = new StubPlacaProvider();

    $a = $stub->lookup('ABC1D23');
    $b = $stub->lookup('ABC1D23');

    expect($a)->toBeInstanceOf(PlacaLookupResult::class);
    expect($a->brand)->toBe($b->brand);
    expect($a->model)->toBe($b->model);
    expect($a->manufactureYear)->toBe($b->manufactureYear);
});

it('StubPlacaProvider simula não-encontrada para placas NF*', function () {
    expect((new StubPlacaProvider())->lookup('NFA1B23'))->toBeNull();
});

// ─── Escopo LGPD — NUNCA proprietário ───────────────────────────────────────

it('resultado NÃO carrega proprietário (escopo só dados técnicos · sem PII de terceiro)', function () {
    $result = (new StubPlacaProvider())->lookup('ABC1D23');

    $payload = $result->toArray();
    $flat = strtolower(json_encode($payload, JSON_UNESCAPED_UNICODE));

    expect($payload)->toHaveKeys(['plate', 'brand', 'model', 'brand_model_label', 'fields']);
    expect($payload)->not->toHaveKey('owner_name');
    expect($payload)->not->toHaveKey('owner_document');
    expect($flat)->not->toContain('proprietario');
    expect($flat)->not->toContain('owner');
    expect($flat)->not->toContain('cpf');
    expect(array_keys($payload['fields']))->not->toContain('owner_name');
});

// ─── VehicleLookupService (com provider injetado) ───────────────────────────

it('service devolve null para placa de formato inválido sem chamar provider', function () {
    $service = new VehicleLookupService(new StubPlacaProvider());

    expect($service->lookup('123', 1))->toBeNull();
});

it('service cacheia resultado por business_id', function () {
    // Provider que conta chamadas — segunda lookup deve vir do cache.
    $counter = new class implements \Modules\OficinaAuto\Services\PlacaLookup\PlacaProvider {
        public int $calls = 0;

        public function lookup(string $plate): ?PlacaLookupResult
        {
            $this->calls++;

            return new PlacaLookupResult(plate: $plate, brand: 'Fiat', model: 'Uno');
        }
    };

    config()->set('oficina-auto.placa_lookup.cache_ttl', 600);
    $service = new VehicleLookupService($counter);

    $service->lookup('ABC1D23', 1);
    $service->lookup('ABC1D23', 1);

    expect($counter->calls)->toBe(1);
});

it('cache é isolada por tenant (Tier 0 — biz diferente não compartilha)', function () {
    $counter = new class implements \Modules\OficinaAuto\Services\PlacaLookup\PlacaProvider {
        public int $calls = 0;

        public function lookup(string $plate): ?PlacaLookupResult
        {
            $this->calls++;

            return new PlacaLookupResult(plate: $plate, brand: 'Fiat', model: 'Uno');
        }
    };

    config()->set('oficina-auto.placa_lookup.cache_ttl', 600);
    $service = new VehicleLookupService($counter);

    $service->lookup('ABC1D23', 1);
    $service->lookup('ABC1D23', 99); // outro tenant → não acha cache do biz=1

    expect($counter->calls)->toBe(2);
});

// ─── HttpPlacaProvider (fake) ────────────────────────────────────────────────

it('HttpPlacaProvider mapeia resposta do fornecedor via field_map', function () {
    Http::fake([
        '*' => Http::response([
            'marca'       => 'Volkswagen',
            'modelo'      => 'Gol',
            'ano'         => '2018',
            'anoModelo'   => '2019',
            'cor'         => 'Prata',
            'combustivel' => 'Flex',
            'chassi'      => '9BWZZZ377VT004251',
            'renavam'     => '12345678901',
        ], 200),
    ]);

    $provider = new HttpPlacaProvider([
        'base_url'  => 'https://fornecedor.example/consulta',
        'api_key'   => 'fake-key',
        'auth_mode' => 'query',
        'auth_key'  => 'token',
        'field_map' => [
            'brand'            => 'marca',
            'model'            => 'modelo',
            'manufacture_year' => 'ano',
            'model_year'       => 'anoModelo',
            'color'            => 'cor',
            'fuel_type'        => 'combustivel',
            'chassis'          => 'chassi',
            'renavam'          => 'renavam',
        ],
    ]);

    $result = $provider->lookup('ABC1D23');

    expect($result)->toBeInstanceOf(PlacaLookupResult::class);
    expect($result->brand)->toBe('Volkswagen');
    expect($result->model)->toBe('Gol');
    expect($result->manufactureYear)->toBe(2018);
    expect($result->modelYear)->toBe(2019);
    expect($result->color)->toBe('Prata');
});

it('HttpPlacaProvider trata 404 como não-encontrada (null, não exceção)', function () {
    Http::fake(['*' => Http::response([], 404)]);

    $provider = new HttpPlacaProvider(['base_url' => 'https://x.example', 'api_key' => 'k']);

    expect($provider->lookup('ABC1D23'))->toBeNull();
});

it('HttpPlacaProvider lança exceção em status de erro do fornecedor', function () {
    Http::fake(['*' => Http::response([], 500)]);

    $provider = new HttpPlacaProvider(['base_url' => 'https://x.example', 'api_key' => 'k']);

    $provider->lookup('ABC1D23');
})->throws(PlacaLookupException::class);

it('HttpPlacaProvider lança exceção quando não configurado', function () {
    $provider = new HttpPlacaProvider([]); // sem base_url/api_key

    $provider->lookup('ABC1D23');
})->throws(PlacaLookupException::class);
