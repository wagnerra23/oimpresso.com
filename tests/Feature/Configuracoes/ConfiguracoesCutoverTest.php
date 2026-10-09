<?php

declare(strict_types=1);

use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations') || ! Schema::hasTable('printers') || ! Schema::hasTable('barcodes')) {
        $this->markTestSkipped('Schema UltimatePOS ausente; executar na lane MySQL.');
    }
    $this->growthbookEnvAnterior = [getenv('GROWTHBOOK_SDK_KEY'), getenv('GROWTHBOOK_API_HOST')];
    putenv('GROWTHBOOK_SDK_KEY=');
    putenv('GROWTHBOOK_API_HOST=');
    Cache::forget('growthbook.features');
    config(['feature-flags.forced_on' => '']);
    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes([
        'business_settings.access', 'access_all_locations', 'access_printers', 'barcode_settings.access',
    ], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id,
        'business.id' => $this->business->id, 'business.enabled_modules' => []]);
});

afterEach(function () {
    foreach (['GROWTHBOOK_SDK_KEY', 'GROWTHBOOK_API_HOST'] as $i => $key) {
        $value = $this->growthbookEnvAnterior[$i] ?? false;
        putenv($value === false ? $key : $key.'='.$value);
    }
    Cache::forget('growthbook.features');
});

$cfgCutover = [
    'UC-LOCAL-01' => ['/business-location', 'Configuracoes/Locais/Index', 'useV2ConfiguracoesLocais', 'business_location.index'],
    'UC-IMPR-01' => ['/printers', 'Configuracoes/Impressoras/Index', 'useV2ConfiguracoesImpressoras', 'printer.index'],
    'UC-ETQ-01' => ['/barcodes', 'Configuracoes/CodigoBarras/Index', 'useV2ConfiguracoesCodigoBarras', 'barcode.index'],
];

test('cutover sem GrowthBook responde React no GET comum do menu', function ($url, $component, $flag) {
    expect(app(FeatureFlagService::class)->isOn($flag, ['business_id' => 98]))->toBeTrue();
    expect(app(FeatureFlagService::class)->isOn($flag, ['business_id' => 99]))->toBeTrue();
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component($component));
})->with($cfgCutover);

test('cutover conserva rollback explícito do GrowthBook para Blade', function ($url, $component, $flag, $view) {
    putenv('GROWTHBOOK_SDK_KEY=sdk-test-fake');
    putenv('GROWTHBOOK_API_HOST=https://growthbook-cutover.test.invalid');
    Http::fake(['*' => Http::response(['features' => [$flag => ['defaultValue' => false]]])]);
    expect(app(FeatureFlagService::class)->isOn($flag, ['business_id' => $this->business->id]))->toBeFalse();
    $this->get($url)->assertOk()->assertViewIs($view);
})->with($cfgCutover);

test('cutover não concede acesso a usuário sem permissão', function ($url) {
    $this->actingAs($this->usuarioComPermissoes([], $this->business))->get($url)->assertForbidden();
})->with($cfgCutover);
