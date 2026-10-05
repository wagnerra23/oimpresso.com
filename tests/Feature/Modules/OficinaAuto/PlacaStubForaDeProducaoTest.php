<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupException;
use Modules\OficinaAuto\Services\PlacaLookup\PlacaLookupResult;
use Modules\OficinaAuto\Services\VehicleLookupService;

// Tests\TestCase já aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * Consulta de placa: o driver `stub` INVENTA dados a partir da placa e não pode responder em
 * produção (decisão [W] 2026-10-05). Medido antes do conserto: produção (APP_ENV "live") estava em
 * stub, sem nenhuma variável OFICINA_* no .env, e o botão Buscar da web preenchia dado falso.
 *
 * Não toca DB (camada de serviço pura). NÃO derivado do código: o contrato é a decisão acima.
 */
beforeEach(function () {
    config()->set('otel.enabled', false);
    config()->set('oficina-auto.placa_lookup.cache_ttl', 600);
    Cache::flush();
    $this->ambienteOriginal = app()['env'];
});

afterEach(function () {
    app()['env'] = $this->ambienteOriginal;
});

it('stub em produção (APP_ENV live ou production): a consulta lança "não configurado" e não inventa dado', function () {
    config()->set('oficina-auto.placa_lookup.driver', 'stub');

    foreach (['live', 'production'] as $ambiente) {
        app()['env'] = $ambiente;
        expect(VehicleLookupService::disponivel())->toBeFalse();
        expect(fn () => (new VehicleLookupService())->lookup('ABC1D23', 98))->toThrow(PlacaLookupException::class);
    }
});

it('stub em teste e staging continua respondendo (controle positivo)', function () {
    config()->set('oficina-auto.placa_lookup.driver', 'stub');

    foreach (['testing', 'staging', 'local'] as $ambiente) {
        app()['env'] = $ambiente;
        expect(VehicleLookupService::disponivel())->toBeTrue();
        expect((new VehicleLookupService())->lookup('ABC1D23', 98))->toBeInstanceOf(PlacaLookupResult::class);
    }
});

it('resultado do stub já cacheado não é servido em produção', function () {
    config()->set('oficina-auto.placa_lookup.driver', 'stub');
    app()['env'] = 'testing';
    (new VehicleLookupService())->lookup('ABC1D23', 98);

    app()['env'] = 'live';
    expect(fn () => (new VehicleLookupService())->lookup('ABC1D23', 98))->toThrow(PlacaLookupException::class);
});

it('driver http em produção está disponível; driver desconhecido não', function () {
    app()['env'] = 'live';

    config()->set('oficina-auto.placa_lookup.driver', 'http');
    expect(VehicleLookupService::disponivel())->toBeTrue();

    config()->set('oficina-auto.placa_lookup.driver', 'outro');
    expect(VehicleLookupService::disponivel())->toBeFalse();
});
