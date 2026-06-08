<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Modules\Cms\Http\Requests\DeleteCmsPageRequest;
use Modules\Cms\Http\Requests\UpdateSiteDetailsRequest;
use Modules\Cms\Services\CmsLeadService;
use Modules\Cms\Services\CmsPageService;
use Modules\Cms\Services\CmsRenderService;
use Modules\Cms\Services\SiteContentService;

uses(Tests\TestCase::class);

/**
 * Wave 27 — Cms polish 71-85 → ≥88 (2026-05-17).
 *
 * Scope da Wave 27 (Bucket `core_horizontal_estavel`):
 *   - D8.c: +2 FormRequests novos (DeleteCmsPageRequest, UpdateSiteDetailsRequest)
 *           pra cobrir delete + endpoint slim text-only.
 *   - D9.a: 4º Service (CmsRenderService) com 3 spans novos
 *           (`cms.render.meta_for_page`, `cms.render.site_default_meta`,
 *           `cms.render.tracking_snippets`) — eleva total spans Cms Services
 *           além do baseline Wave 18.
 *   - D2: regression test garantindo OtelHelper canônico + bindable via DI.
 *
 * Multi-tenant Tier 0 (ADR 0093): `cms_pages.business_id` AUSENTE preservado
 * intencionalmente (US-CMS-002 schema pendente) — site público GLOBAL.
 * Caso alguém adicione a coluna sem ADR, `Wave25CmsSaturationTest` detecta.
 *
 * Zero-cost: testes rodam sem DB — só Validator + Reflection + source-grep.
 */

// ---------- D8.c — DeleteCmsPageRequest ----------

it('027.01 DeleteCmsPageRequest aceita type vazio (delete sem filtro)', function () {
    $rules = (new DeleteCmsPageRequest)->rules();
    $v = Validator::make([], $rules);
    expect($v->fails())->toBeFalse();
});

it('027.02 DeleteCmsPageRequest aceita type whitelisted (page/post/banner)', function () {
    $rules = (new DeleteCmsPageRequest)->rules();
    foreach (['page', 'post', 'banner'] as $tipo) {
        $v = Validator::make(['type' => $tipo], $rules);
        expect($v->fails())->toBeFalse("type {$tipo} deveria ser aceito");
    }
});

it('027.03 DeleteCmsPageRequest rejeita type fora do whitelist (anti-mapping)', function () {
    $rules = (new DeleteCmsPageRequest)->rules();
    $v = Validator::make(['type' => '../../../etc/passwd'], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('type'))->toBeTrue();
});

// ---------- D8.c — UpdateSiteDetailsRequest (slim REST) ----------

it('027.04 UpdateSiteDetailsRequest aceita payload vazio (PATCH parcial)', function () {
    $rules = (new UpdateSiteDetailsRequest)->rules();
    $v = Validator::make([], $rules);
    expect($v->fails())->toBeFalse();
});

it('027.05 UpdateSiteDetailsRequest valida emails (mail_us, notifiable_email)', function () {
    $rules = (new UpdateSiteDetailsRequest)->rules();
    $v = Validator::make([
        'mail_us'          => 'nao-eh-email',
        'notifiable_email' => 'outro-invalido',
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('mail_us'))->toBeTrue();
    expect($v->errors()->has('notifiable_email'))->toBeTrue();
});

it('027.06 UpdateSiteDetailsRequest limita faqs em 65535 chars (TEXT MySQL)', function () {
    $rules = (new UpdateSiteDetailsRequest)->rules();
    $v = Validator::make(['faqs' => str_repeat('a', 65536)], $rules);
    expect($v->fails())->toBeTrue();
});

it('027.07 UpdateSiteDetailsRequest aceita payload realista de contato', function () {
    $rules = (new UpdateSiteDetailsRequest)->rules();
    $v = Validator::make([
        'mail_us'    => 'contato@oimpresso.com',
        'contact_us' => 'Av Brasil 100, Florianópolis/SC',
        'follow_us'  => '@oimpresso',
    ], $rules);
    expect($v->fails())->toBeFalse();
});

// ---------- D9.a — CmsRenderService observability ----------

it('027.10 CmsRenderService é resolvível via container (DI ok)', function () {
    $svc = app(CmsRenderService::class);
    expect($svc)->toBeInstanceOf(CmsRenderService::class);
});

it('027.11 CmsRenderService usa OtelHelper canônico (App\Util\OtelHelper)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Services/CmsRenderService.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->not->toContain('OpenTelemetry\API\Trace\TracerProviderInterface');
});

it('027.12 CmsRenderService tem 3 spans canon nos métodos públicos', function () {
    $src = file_get_contents(base_path('Modules/Cms/Services/CmsRenderService.php'));

    $esperados = [
        'cms.render.meta_for_page',
        'cms.render.site_default_meta',
        'cms.render.tracking_snippets',
    ];

    foreach ($esperados as $span) {
        expect($src)->toContain("OtelHelper::spanBiz('{$span}'");
    }

    // Cada método público crítico tem ≥1 span (regression detection)
    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(3);
});

it('027.13 Cms agora tem ≥4 Services com OtelHelper instrumentado (W27 polish)', function () {
    $services = [
        base_path('Modules/Cms/Services/CmsPageService.php'),
        base_path('Modules/Cms/Services/CmsLeadService.php'),
        base_path('Modules/Cms/Services/SiteContentService.php'),
        base_path('Modules/Cms/Services/CmsRenderService.php'),
    ];

    $totalSpans = 0;
    foreach ($services as $f) {
        expect(file_exists($f))->toBeTrue("Service ausente: {$f}");
        $src = file_get_contents($f);
        expect($src)->toContain('use App\Util\OtelHelper;');
        $totalSpans += substr_count($src, 'OtelHelper::spanBiz(');
    }

    // Wave 18 baseline: ~10 spans em 3 services. Wave 27: +3 (CmsRenderService).
    // Margem mínima conservadora pra acomodar refactor futuro.
    expect($totalSpans)->toBeGreaterThanOrEqual(12);
});

it('027.14 CmsRenderService::truncateMeta corta meta_description em 160 chars (SEO)', function () {
    $svc = app(CmsRenderService::class);
    $ref = new ReflectionMethod(CmsRenderService::class, 'truncateMeta');
    $ref->setAccessible(true);

    $longo = str_repeat('a', 300);
    $resultado = $ref->invoke($svc, $longo);

    expect(strlen($resultado))->toBeLessThanOrEqual(160);
    expect($resultado)->toEndWith('...');
});

it('027.15 Services CMS preservados (CmsPageService, CmsLeadService, SiteContentService) — sem regressão DI', function () {
    expect(app(CmsPageService::class))->toBeInstanceOf(CmsPageService::class);
    expect(app(CmsLeadService::class))->toBeInstanceOf(CmsLeadService::class);
    expect(app(SiteContentService::class))->toBeInstanceOf(SiteContentService::class);
});
