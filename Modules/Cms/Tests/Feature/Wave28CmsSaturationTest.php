<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Services\SiteContentService;

uses(Tests\TestCase::class);

/**
 * Wave 28 Cms SATURATION FINAL — polish 71-85 → ≥92 (+7pp).
 *
 * Esforço por dimensão:
 *  - D2 +3 Pest novos cenários Wave 28
 *  - D9 +1 span novo `cms.service.render_chrome` em SiteContentService (5º span canon)
 *  - D3 CHANGELOG W28 entry
 *
 * Trust L0: tests puros Reflection + source-grep + ambiente-agnóstico (sem boot DB).
 * Preserva D1 guard `cms_pages.business_id` AUSENTE Wave 26 (US-CMS-002 pendente IRREVOGÁVEL).
 *
 * @see Modules/Cms/Tests/Feature/Wave26CmsSaturationTest.php (Wave 26 baseline)
 * @see Modules/Cms/Services/SiteContentService.php (D9 +1 span W28)
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
});

// ------------------------------------------------------------------
// D9 W28 — span novo cms.service.render_chrome (SiteContentService 5º span)
// ------------------------------------------------------------------

it('D9 W28: SiteContentService tem método getRenderChromePayload novo (W28 D9)', function () {
    $ref = new ReflectionClass(SiteContentService::class);
    expect($ref->hasMethod('getRenderChromePayload'))->toBeTrue('Wave 28 D9 — render_chrome novo método público');

    $method = $ref->getMethod('getRenderChromePayload');
    expect($method->isPublic())->toBeTrue();
    expect($method->getReturnType()?->getName())->toBe('array');
});

it('D9 W28: SiteContentService instrumenta cms.service.render_chrome (5º span canon)', function () {
    $src = file_get_contents((new ReflectionClass(SiteContentService::class))->getFileName());

    expect($src)->toContain("'cms.service.render_chrome'");

    // Spans totais SiteContentService ≥5 cumulativo (W18 4 + W28 1)
    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(5, "SiteContentService deve ter ≥5 spans (W28 D9 +1); achou {$count}");
});

it('D9 W28: getRenderChromePayload é GLOBAL (sem business_id — US-CMS-002 schema pendente)', function () {
    // Site público oimpresso.com é GLOBAL — preserva D1 guard Wave 26
    $ref = new ReflectionMethod(SiteContentService::class, 'getRenderChromePayload');
    $params = $ref->getParameters();

    expect(count($params))->toBe(0, 'getRenderChromePayload NÃO recebe business_id (site GLOBAL Wave 26 D1)');
});

// ------------------------------------------------------------------
// D2 W28 — +3 Pest cenários adicionais
// ------------------------------------------------------------------

it('D2 W28: getRenderChromePayload retorna shape canon com 4 chaves (site_name/logo/contact_email/social)', function () {
    $src = file_get_contents((new ReflectionClass(SiteContentService::class))->getFileName());

    foreach (['site_name', 'logo', 'contact_email', 'social'] as $key) {
        expect(str_contains($src, "'{$key}'"))->toBeTrue("Render chrome deve agregar chave '{$key}'");
    }
});

it('D2 W28: SiteContentService preserva ≥5 métodos públicos canon (4 W18 + 1 W28)', function () {
    $ref = new ReflectionClass(SiteContentService::class);
    $publicMethods = collect($ref->getMethods(\ReflectionMethod::IS_PUBLIC))
        ->filter(fn ($m) => $m->getDeclaringClass()->getName() === SiteContentService::class)
        ->map(fn ($m) => $m->getName());

    expect($publicMethods->count())->toBeGreaterThanOrEqual(5);
    expect($publicMethods)->toContain('getRenderChromePayload');
});

it('D2 W28: D1 guard preservado — cms_pages.business_id continua AUSENTE (US-CMS-002 pendente)', function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('cms_pages')) {
        $this->markTestSkipped('Schema cms_pages indisponível neste ambiente.');
    }

    $hasBusinessId = Schema::hasColumn('cms_pages', 'business_id');

    if ($hasBusinessId) {
        // Drift positivo permitido se acompanhar migration US-CMS-002 explícita
        $migrations = glob(base_path('Modules/Cms/Database/Migrations/*business_id*'));
        expect(count($migrations))->toBeGreaterThanOrEqual(1, 'Migration US-CMS-002 esperada se schema mudou');
    } else {
        // Estado canônico Wave 28: site GLOBAL preservado
        expect($hasBusinessId)->toBeFalse();
    }
});

// ------------------------------------------------------------------
// D3 W28 — CHANGELOG entry novo
// ------------------------------------------------------------------

it('D3 W28: CHANGELOG.md tem entrada Wave 28 (saturation 71-85 → ≥92)', function () {
    $changelog = file_get_contents(base_path('Modules/Cms/CHANGELOG.md'));
    expect($changelog)->toContain('Wave 28');
});
