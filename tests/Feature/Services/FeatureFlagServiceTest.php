<?php

declare(strict_types=1);

/**
 * Pest test — App\Services\FeatureFlagService (US-INFRA-001 fase B).
 *
 * Cobre:
 *   1. Fallback offline-safe quando .env não tem GROWTHBOOK_SDK_KEY/API_HOST
 *   2. Fallback offline-safe quando GrowthBook responde não-2xx
 *   3. Cache 60s — chamadas subsequentes não refazem HTTP
 *   4. clearCache() força refresh imediato
 *   5. Defaults conservadores (OFF) pra flags ausentes
 *
 * Não toca rede real — usa Http::fake() e Cache::flush() entre testes.
 */

use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('retorna fallback default quando GROWTHBOOK_SDK_KEY ausente no .env', function () {
    config(['env.GROWTHBOOK_SDK_KEY' => '']);
    putenv('GROWTHBOOK_SDK_KEY=');
    putenv('GROWTHBOOK_API_HOST=');

    $service = new FeatureFlagService();

    // useV2SellsCreate tem fallback default TRUE (flip Wagner 2026-05-27, pós-cadeia
    // de hotfixes — ver FeatureFlagService::$fallbackDefaults). Sem GrowthBook
    // configurado, isOn() cai no fallback e retorna esse default.
    expect($service->isOn('useV2SellsCreate'))->toBeTrue();
    // flagInexistente não está em $fallbackDefaults → false conservador.
    expect($service->isOn('flagInexistente'))->toBeFalse();
});

it('retorna fallback default quando GrowthBook API responde 5xx', function () {
    putenv('GROWTHBOOK_SDK_KEY=sdk-test-fake');
    putenv('GROWTHBOOK_API_HOST=https://growthbook-api.test.local');

    Http::fake([
        'growthbook-api.test.local/*' => Http::response('Bad Gateway', 502),
    ]);

    $service = new FeatureFlagService();

    // 2026-06-04 — GrowthBook inacessível (5xx) DEVE cair no fallback default
    // (offline-safe). useV2SellsCreate=true. Antes este teste afirmava false,
    // encodando o bug que derrubava a tela React de venda pro Blade quando o
    // GrowthBook do CT 100 caía. Ver FeatureFlagService::isOn (array_key_exists).
    expect($service->isOn('useV2SellsCreate'))->toBeTrue();
});

it('retorna ON quando GrowthBook fornece flag habilitada', function () {
    putenv('GROWTHBOOK_SDK_KEY=sdk-test-fake');
    putenv('GROWTHBOOK_API_HOST=https://growthbook-api.test.local');

    Http::fake([
        'growthbook-api.test.local/*' => Http::response([
            'status' => 200,
            'features' => [
                'useV2SellsCreate' => [
                    'defaultValue' => true,
                ],
            ],
        ], 200),
    ]);

    $service = new FeatureFlagService();

    expect($service->isOn('useV2SellsCreate', ['business_id' => 1]))->toBeTrue();
});

it('cache 60s evita HTTP repetido em chamadas subsequentes', function () {
    putenv('GROWTHBOOK_SDK_KEY=sdk-test-fake');
    putenv('GROWTHBOOK_API_HOST=https://growthbook-api.test.local');

    Http::fake([
        'growthbook-api.test.local/*' => Http::response([
            'status' => 200,
            'features' => ['useV2SellsCreate' => ['defaultValue' => true]],
        ], 200),
    ]);

    $service = new FeatureFlagService();

    $service->isOn('useV2SellsCreate');
    $service->isOn('useV2SellsCreate');
    $service->isOn('useV2SellsCreate');

    Http::assertSentCount(1);
});

it('clearCache() força refresh imediato', function () {
    putenv('GROWTHBOOK_SDK_KEY=sdk-test-fake');
    putenv('GROWTHBOOK_API_HOST=https://growthbook-api.test.local');

    Http::fake([
        'growthbook-api.test.local/*' => Http::response([
            'status' => 200,
            'features' => ['useV2SellsCreate' => ['defaultValue' => true]],
        ], 200),
    ]);

    $service = new FeatureFlagService();

    $service->isOn('useV2SellsCreate');
    $service->clearCache();
    $service->isOn('useV2SellsCreate');

    Http::assertSentCount(2);
});

it('flag ausente nos features retornados retorna fallback', function () {
    putenv('GROWTHBOOK_SDK_KEY=sdk-test-fake');
    putenv('GROWTHBOOK_API_HOST=https://growthbook-api.test.local');

    Http::fake([
        'growthbook-api.test.local/*' => Http::response([
            'status' => 200,
            'features' => [],
        ], 200),
    ]);

    $service = new FeatureFlagService();

    expect($service->isOn('useV2SellsCreate'))->toBeTrue(); // flag ausente nos features → fallback default TRUE
    expect($service->isOn('flagDesconhecida'))->toBeFalse(); // sem fallback explícito → false
});
