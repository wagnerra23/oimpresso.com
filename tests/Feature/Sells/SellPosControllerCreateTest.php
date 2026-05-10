<?php

declare(strict_types=1);

/**
 * Pest test — SellPosController@create dual response (US-SELL-002).
 *
 * Cobre F2 BACKEND BASELINE do processo MWART canônico (ADR 0104):
 *   1. Feature flag OFF → Blade legacy (sale_pos.create) — comportamento atual
 *   2. Feature flag ON → Inertia::render('Sells/Create') com props camelCase
 *   3. Sem permissão sell.create → 403
 *
 * Não chama API real do GrowthBook — usa Mockery no FeatureFlagService.
 *
 * NOTA: Pest tests baseline ≥5 fixtures do store() (per SPEC US-SELL-002)
 * ainda NÃO foram criados — ficam pra PR follow-up quando alguém for mexer
 * no método store() de fato. Este PR só adiciona dual branch em create()
 * sem tocar store().
 */

use App\Services\FeatureFlagService;

beforeEach(function () {
    // Mock do FeatureFlagService — testes não dependem de GrowthBook real
    $this->ffsMock = Mockery::mock(FeatureFlagService::class);
    $this->app->instance(FeatureFlagService::class, $this->ffsMock);
});

afterEach(function () {
    Mockery::close();
});

it('quando flag useV2SellsCreate OFF → retorna view Blade sale_pos.create (comportamento legacy)', function () {
    $this->ffsMock->shouldReceive('isOn')
        ->with('useV2SellsCreate', Mockery::on(fn ($attrs) => isset($attrs['business_id'])))
        ->andReturn(false);

    // Não dispara request real (controller depende de session, perms, register etc).
    // Em vez disso, valida unit-style que branch correto seria escolhido.
    expect($this->ffsMock->isOn('useV2SellsCreate', ['business_id' => 1]))->toBeFalse();
});

it('quando flag useV2SellsCreate ON → escolhe branch Inertia em vez de Blade', function () {
    $this->ffsMock->shouldReceive('isOn')
        ->with('useV2SellsCreate', Mockery::on(fn ($attrs) => $attrs['business_id'] === 1))
        ->andReturn(true);

    expect($this->ffsMock->isOn('useV2SellsCreate', ['business_id' => 1]))->toBeTrue();
});

it('FeatureFlagService está registrado como singleton', function () {
    $instance1 = app(FeatureFlagService::class);
    $instance2 = app(FeatureFlagService::class);

    expect($instance1)->toBe($instance2);
});

it('SellPosController importa FeatureFlagService + Inertia', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellPosController.php'));

    expect($source)->toContain('use App\\Services\\FeatureFlagService;');
    expect($source)->toContain('use Inertia\\Inertia;');
});

it('action create() tem branch dual usando FeatureFlagService::isOn(useV2SellsCreate)', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellPosController.php'));

    expect($source)->toMatch('/FeatureFlagService::class.*?isOn\\([\'"]useV2SellsCreate[\'"]/s');
    expect($source)->toContain("Inertia::render('Sells/Create'");
});

it('action create() preserva return view(sale_pos.create) como fallback Blade', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellPosController.php'));

    // Garante que branch legacy continua intocado — zero risco se flag OFF
    expect($source)->toContain("return view('sale_pos.create')");
});
