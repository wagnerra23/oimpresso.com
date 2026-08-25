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

/*
|--------------------------------------------------------------------------
| Override por ambiente — FEATURE_FLAGS_FORCED_ON (config/feature-flags.php)
|--------------------------------------------------------------------------
|
| Existe pra uma tela atras de flag poder ENTRAR no gate visual: sem
| GROWTHBOOK_SDK_KEY no runner, getFeatures() volta vazio, isOn() cai no
| fallback() e a rota devolve o Blade — o assertInertia do PixelBaselineTest
| reprova e a baseline nunca nasce (medido 2026-08-20, PR #5977).
|
| Os dois lados sao cobertos: com a env a flag resolve true; SEM a env nada
| muda (controle negativo). Mais o fail-closed de producao.
|
*/

it('config/feature-flags.php esta carregado (nao e o teste que inventa a chave)', function () {
    // Sem esta assercao os testes abaixo passariam mesmo se o arquivo de config
    // nao existisse — eles setam config() na mao. Aqui a chave tem que vir DO
    // ARQUIVO: ausente, config() devolveria null.
    expect(config('feature-flags.forced_on'))->not->toBeNull();
});

it('override por env liga a flag mesmo sem GrowthBook configurado', function () {
    putenv('GROWTHBOOK_SDK_KEY=');
    putenv('GROWTHBOOK_API_HOST=');
    config(['feature-flags.forced_on' => 'useV2OfficeimpressoLogs']);

    $service = new FeatureFlagService();

    // Sem o override esta flag seria false: nao esta em $fallbackDefaults.
    expect($service->isOn('useV2OfficeimpressoLogs'))->toBeTrue();
});

it('override por env vence o GrowthBook que responde OFF (determinismo do harness)', function () {
    putenv('GROWTHBOOK_SDK_KEY=sdk-test-fake');
    putenv('GROWTHBOOK_API_HOST=https://growthbook-api.test.local');
    config(['feature-flags.forced_on' => 'useV2OfficeimpressoLogs']);

    Http::fake([
        'growthbook-api.test.local/*' => Http::response([
            'status' => 200,
            'features' => ['useV2OfficeimpressoLogs' => ['defaultValue' => false]],
        ], 200),
    ]);

    $service = new FeatureFlagService();

    // Consultado ANTES do GrowthBook: o harness nao pode depender do que o SDK
    // responde naquele instante. Por isso nem HTTP acontece.
    expect($service->isOn('useV2OfficeimpressoLogs'))->toBeTrue();
    Http::assertNothingSent();
});

it('aceita lista separada por virgula e tolera espacos', function () {
    putenv('GROWTHBOOK_SDK_KEY=');
    putenv('GROWTHBOOK_API_HOST=');
    config(['feature-flags.forced_on' => 'flagA , useV2OfficeimpressoLogs,flagC']);

    $service = new FeatureFlagService();

    expect($service->isOn('flagA'))->toBeTrue();
    expect($service->isOn('useV2OfficeimpressoLogs'))->toBeTrue();
    expect($service->isOn('flagC'))->toBeTrue();
    // Nao listada continua no comportamento normal (false conservador).
    expect($service->isOn('flagD'))->toBeFalse();
});

it('CONTROLE NEGATIVO: sem a env, o comportamento atual nao muda', function () {
    putenv('GROWTHBOOK_SDK_KEY=');
    putenv('GROWTHBOOK_API_HOST=');
    config(['feature-flags.forced_on' => '']); // default do config

    $service = new FeatureFlagService();

    // Exatamente as assercoes do primeiro teste deste arquivo: o override
    // desligado nao pode alterar nada do que ja existia.
    expect($service->isOn('useV2SellsCreate'))->toBeTrue();   // fallbackDefaults
    expect($service->isOn('flagInexistente'))->toBeFalse();   // sem fallback
    expect($service->isOn('useV2OfficeimpressoLogs'))->toBeFalse(); // sem override
});

it('FAIL-CLOSED: em producao o override e ignorado mesmo preenchido', function () {
    putenv('GROWTHBOOK_SDK_KEY=');
    putenv('GROWTHBOOK_API_HOST=');
    config(['feature-flags.forced_on' => 'useV2OfficeimpressoLogs']);

    app()->detectEnvironment(fn () => 'production');

    $service = new FeatureFlagService();

    // Ligar flag pra business de producao e decisao de cutover do [W], feita no
    // GrowthBook ou no $fallbackDefaults — nunca por env de harness.
    expect(app()->isProduction())->toBeTrue();
    expect($service->isOn('useV2OfficeimpressoLogs'))->toBeFalse();
});
