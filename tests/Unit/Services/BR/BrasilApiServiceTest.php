<?php

declare(strict_types=1);

use App\Services\BR\BrasilApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

/**
 * Unit — App\Services\BR\BrasilApiService.
 *
 * Cobre:
 *   - CNPJ inválido (mod-11) → null SEM hit HTTP (economiza request)
 *   - CNPJ válido + API 200 → array normalizado canon
 *   - CNPJ válido + API 404 → null
 *   - CNPJ válido + API 5xx → null (não levanta)
 *   - Cache hit pula HTTP (Cache::remember)
 *
 * Slice 5a — Wagner 2026-05-21 paralelo.
 */
beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

it('retorna null pra CNPJ invalido sem hit HTTP', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['should_not' => 'be_called'], 200),
    ]);

    $service = new BrasilApiService;
    $result = $service->lookupCnpj('11.111.111/1111-11'); // pii-allowlist: fake CNPJ todos-1 que falha mod-11

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('retorna null pra CNPJ com menos de 14 digitos', function () {
    Http::preventStrayRequests();

    $service = new BrasilApiService;
    $result = $service->lookupCnpj('12345');

    expect($result)->toBeNull();
});

it('normaliza payload da BrasilAPI quando API retorna 200', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11444777000161' => Http::response([
            'cnpj' => '11444777000161',
            'razao_social' => 'ACME COMERCIAL LTDA',
            'nome_fantasia' => 'ACME',
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'numero' => '1000',
            'bairro' => 'Bela Vista',
            'municipio' => 'São Paulo',
            'uf' => 'SP',
            'qsa' => [],
        ], 200),
    ]);

    $service = new BrasilApiService;
    $result = $service->lookupCnpj('11.444.777/0001-61'); // pii-allowlist: fake CNPJ fixture BrasilAPI

    expect($result)
        ->toBeArray()
        ->and($result['cnpj'])->toBe('11444777000161')
        ->and($result['razao_social'])->toBe('ACME COMERCIAL LTDA')
        ->and($result['nome_fantasia'])->toBe('ACME')
        ->and($result['cep'])->toBe('01310100') // dígitos only
        ->and($result['logradouro'])->toBe('Avenida Paulista')
        ->and($result['numero'])->toBe('1000')
        ->and($result['bairro'])->toBe('Bela Vista')
        ->and($result['municipio'])->toBe('São Paulo')
        ->and($result['uf'])->toBe('SP');
});

it('retorna null quando API responde 404', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['message' => 'CNPJ not found'], 404),
    ]);

    $service = new BrasilApiService;
    $result = $service->lookupCnpj('11.444.777/0001-61'); // pii-allowlist: fake CNPJ fixture BrasilAPI

    expect($result)->toBeNull();
});

it('retorna null quando API responde 5xx (graceful)', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['error' => 'down'], 500),
    ]);

    $service = new BrasilApiService;
    $result = $service->lookupCnpj('11.444.777/0001-61'); // pii-allowlist: fake CNPJ fixture BrasilAPI

    expect($result)->toBeNull();
});

it('cache hit pula chamada HTTP na segunda vez', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11444777000161' => Http::response([
            'cnpj' => '11444777000161',
            'razao_social' => 'CACHED LTDA',
            'nome_fantasia' => null,
            'cep' => null,
            'logradouro' => null,
            'numero' => null,
            'bairro' => null,
            'municipio' => null,
            'uf' => null,
        ], 200),
    ]);

    $service = new BrasilApiService;

    // 1ª chamada — hit HTTP, popula cache.
    $first = $service->lookupCnpj('11.444.777/0001-61'); // pii-allowlist: fake CNPJ fixture BrasilAPI
    expect($first['razao_social'])->toBe('CACHED LTDA');
    Http::assertSentCount(1);

    // 2ª chamada — cache hit, NÃO incrementa HTTP count.
    $second = $service->lookupCnpj('11.444.777/0001-61'); // pii-allowlist: fake CNPJ fixture BrasilAPI
    expect($second['razao_social'])->toBe('CACHED LTDA');
    Http::assertSentCount(1); // ainda 1, sem nova chamada
});

it('trata payload com campos null/missing da API', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnpj' => '11444777000161',
            'razao_social' => 'PARTIAL LTDA',
            // outros campos ausentes
        ], 200),
    ]);

    $service = new BrasilApiService;
    $result = $service->lookupCnpj('11.444.777/0001-61'); // pii-allowlist: fake CNPJ fixture BrasilAPI

    expect($result)
        ->toBeArray()
        ->and($result['razao_social'])->toBe('PARTIAL LTDA')
        ->and($result['nome_fantasia'])->toBeNull()
        ->and($result['cep'])->toBeNull()
        ->and($result['logradouro'])->toBeNull();
});
