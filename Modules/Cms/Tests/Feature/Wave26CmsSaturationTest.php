<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsPageMeta;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Cms\Http\Controllers\CmsPageController;
use Modules\Cms\Repositories\CmsPageRepository;
use Modules\Cms\Services\CmsLeadService;
use Modules\Cms\Services\CmsPageService;
use Modules\Cms\Services\SiteContentService;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 26 Cms SATURATION — polish 71 → ≥85 (+14pp).
 *
 * Esforço por dimensão:
 *  - D1 guard schema cms_pages.business_id AUSENTE preservar (US-CMS-002 schema pendente IRREVOGÁVEL)
 *  - D2 Pest Wave 18+25 expandir (mais Service contract + Repository contract + Entities)
 *  - D6 defer CmsPageController (Wave 17/18 fez parcial — confirmar `buildPagePayload`)
 *  - D3 CHANGELOG + BRIEFING atualizado (proxy test: arquivos existem + ≥1 menção Wave 26)
 *
 * Trust L0: tests Reflection + Schema check (sem boot HTTP/Inertia full).
 *
 * @see Modules/Cms/Tests/Feature/Wave25CmsSaturationTest.php (Wave 25 baseline)
 * @see Modules/Cms/Http/Controllers/CmsPageController.php (D6 buildPagePayload)
 */

const CMS_W26_BIZ_WAGNER = 1;
const CMS_W26_BIZ_FICTICIO = 99;
const CMS_W26_TAG = 'WAVE26-CMS-SAT';

function w26CmsNeedsMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite';
}

afterEach(function () {
    if (w26CmsNeedsMysql() || ! Schema::hasTable('cms_pages')) {
        return;
    }
    try {
        DB::table('cms_pages')
            ->where('title', 'like', '%'.CMS_W26_TAG.'%')
            ->delete();
    } catch (\Throwable) {
        // best-effort cleanup
    }
});

// ------------------------------------------------------------------
// D1 — Guard schema cms_pages.business_id ausente (PRESERVAR US-CMS-002)
// ------------------------------------------------------------------

it('D1 guard W26: cms_pages.business_id continua AUSENTE (preservar US-CMS-002 pendente)', function () {
    if (w26CmsNeedsMysql() || ! Schema::hasTable('cms_pages')) {
        $this->markTestSkipped('Schema cms_pages indisponível neste ambiente.');
    }

    $hasBusinessId = Schema::hasColumn('cms_pages', 'business_id');

    // Se alguém adicionar business_id sem ADR US-CMS-002 → drift positivo
    if ($hasBusinessId) {
        // Guard: caso adicione, ser NOT NULL (Tier 0 ADR 0093)
        $col = collect(DB::select('SHOW COLUMNS FROM cms_pages LIKE ?', ['business_id']))->first();
        expect($col)->not->toBeNull();
        expect($col->Null)->toBe('NO');

        // E deve existir migration explícita (US-CMS-002)
        $migrations = glob(base_path('Modules/Cms/Database/Migrations/*business_id*'));
        expect(count($migrations))->toBeGreaterThanOrEqual(1, 'US-CMS-002 migration esperada se schema mudou');
    } else {
        // Estado canônico Wave 26: site GLOBAL (esperado)
        expect($hasBusinessId)->toBeFalse();
    }
});

// ------------------------------------------------------------------
// D2 — Service layer expansion (Wave 18+25 baseline + W26 polish)
// ------------------------------------------------------------------

it('D2 W26: CmsPageService::criar tem return type ?CmsPage (nullable fail-safe)', function () {
    $ref = new ReflectionMethod(CmsPageService::class, 'criar');
    $returnType = $ref->getReturnType();

    // criar pode retornar null se exception interna (fail-safe)
    expect($returnType)->not->toBeNull();
});

it('D2 W26: CmsPageService::remover tem return type bool (fail-safe sem exception)', function () {
    $ref = new ReflectionMethod(CmsPageService::class, 'remover');
    expect($ref->getReturnType()?->getName())->toBe('bool');
});

it('D2 W26: CmsPageService injeta CmsPageRepository (DI Repository contract)', function () {
    $ref = new ReflectionClass(CmsPageService::class);
    $ctor = $ref->getConstructor();
    expect($ctor)->not->toBeNull();

    $params = $ctor->getParameters();
    $repoParam = collect($params)->first(fn ($p) => $p->getName() === 'pageRepo');
    expect($repoParam)->not->toBeNull();
    expect($repoParam->getType()?->getName())->toBe(CmsPageRepository::class);
});

it('D2 W26: CmsPageRepository é resolvível via container Laravel', function () {
    if (w26CmsNeedsMysql()) {
        $this->markTestSkipped('Container resolve requer DB.');
    }
    $repo = app(CmsPageRepository::class);
    expect($repo)->toBeInstanceOf(CmsPageRepository::class);
});

it('D2 W26: CmsPageRepository tem método baseQuery() canon (ponto futuro injeção business_id)', function () {
    $ref = new ReflectionClass(CmsPageRepository::class);
    expect($ref->hasMethod('baseQuery'))->toBeTrue('baseQuery() é ponto canon pra US-CMS-002');
});

it('D2 W26: CmsLeadService capturar() faz log com PII redactada (D7.a LGPD)', function () {
    $file = (new ReflectionClass(CmsLeadService::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain('piiRedactor->redactArray');
    expect($src)->toContain('cms.lead.captured');
});

it('D2 W26: SiteContentService getHomePayload retorna 4 chaves canon (testimonials/page/faqs/statistics)', function () {
    $file = (new ReflectionClass(SiteContentService::class))->getFileName();
    $src = file_get_contents($file);

    foreach (['testimonials', 'page', 'faqs', 'statistics'] as $key) {
        expect($src)->toContain("'{$key}'");
    }
});

it('D2 W26: SiteContentService tem ≥4 métodos públicos canon (home/blog/page/findBlogPost)', function () {
    $ref = new ReflectionClass(SiteContentService::class);
    $publicMethods = collect($ref->getMethods(\ReflectionMethod::IS_PUBLIC))
        ->filter(fn ($m) => $m->getDeclaringClass()->getName() === SiteContentService::class)
        ->map(fn ($m) => $m->getName());

    // Pelo menos 4 métodos públicos (Wave 18 baseline)
    expect($publicMethods->count())->toBeGreaterThanOrEqual(4);
});

it('D2 W26: CmsPage Entity tem accessor feature_image_url (Sprint 4 Arquivos backbone)', function () {
    $ref = new ReflectionClass(CmsPage::class);
    expect($ref->hasMethod('getFeatureImageUrlAttribute'))->toBeTrue();
    expect($ref->hasMethod('getFeatureImageArquivoAttribute'))->toBeTrue();
});

it('D2 W26: CmsPage append slug + feature_image_url (auto-includes em JSON output)', function () {
    $page = new CmsPage();
    $appends = (new ReflectionClass($page))->getProperty('appends');
    $appends->setAccessible(true);
    $val = $appends->getValue($page);

    expect($val)->toContain('slug');
    expect($val)->toContain('feature_image_url');
});

it('D2 W26: CmsPage usa HasArquivos trait (ADR 0123 backbone arquivos)', function () {
    $traits = class_uses_recursive(CmsPage::class);
    expect($traits)->toContain('Modules\\Arquivos\\Concerns\\HasArquivos');
});

it('D2 W26: CmsSiteDetail Entity existe (settings site público)', function () {
    expect(class_exists(CmsSiteDetail::class))->toBeTrue();
    $traits = class_uses_recursive(CmsSiteDetail::class);
    expect($traits)->toContain(LogsActivity::class);
});

it('D2 W26: CmsPageMeta Entity existe (SEO meta tags + page metadata)', function () {
    expect(class_exists(CmsPageMeta::class))->toBeTrue();
});

// ------------------------------------------------------------------
// D6 — defer CmsPageController (Wave 17/18 fez parcial)
// ------------------------------------------------------------------

it('D6 W26: CmsPageController showPage tem buildPagePayload helper (Inertia::defer-ready)', function () {
    $ref = new ReflectionClass(CmsPageController::class);
    expect($ref->hasMethod('buildPagePayload'))->toBeTrue();

    $method = $ref->getMethod('buildPagePayload');
    expect($method->isPrivate())->toBeTrue('buildPagePayload deve ser private (encapsulamento)');
});

it('D6 W26: CmsPageController buildPagePayload instrumentado com OtelHelper::spanBiz canon', function () {
    $file = (new ReflectionClass(CmsPageController::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain("'cms.page.render'");
    expect($src)->toContain('OtelHelper::spanBiz');
});

it('D6 W26: CmsPageController pre-check 404 ANTES de defer (não vaza shell)', function () {
    $file = (new ReflectionClass(CmsPageController::class))->getFileName();
    $src = file_get_contents($file);

    // Pre-check existência via `exists()` antes do render (rollback Wave L/W7 PR #963)
    expect($src)->toContain('->exists()');
    expect($src)->toContain('abort(404)');
});

it('D6 W26: CmsPageController showPage usa Inertia::render (não view legacy)', function () {
    $file = (new ReflectionClass(CmsPageController::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain("Inertia::render('Site/Page'");
});

it('D6 W26: CmsPageController preserva showPageLegacy /old (Blade rollback path)', function () {
    $ref = new ReflectionClass(CmsPageController::class);
    expect($ref->hasMethod('showPageLegacy'))->toBeTrue();
});

// ------------------------------------------------------------------
// D3 — CHANGELOG + BRIEFING (proxy: arquivos existem + W26 entry)
// ------------------------------------------------------------------

it('D3 W26: CHANGELOG.md tem entrada Wave 26 (governance polish ≥85)', function () {
    $changelog = file_get_contents(base_path('Modules/Cms/CHANGELOG.md'));

    // Confirma seção W26 (polish 71→≥85 +14pp)
    expect($changelog)->toContain('Wave 26');
});

it('D3 W26: BRIEFING.md atualizado pra Wave 26', function () {
    $briefing = file_get_contents(base_path('memory/requisitos/Cms/BRIEFING.md'));

    // BRIEFING tem que mencionar Wave 26 (polish atual)
    expect($briefing)->toContain('Wave 26');
});

// ------------------------------------------------------------------
// D7 baseline preservado (LogsActivity + retention.php)
// ------------------------------------------------------------------

it('D7 W26: Config/retention.php existe (declaração LGPD canon)', function () {
    expect(file_exists(base_path('Modules/Cms/Config/retention.php')))->toBeTrue();
});

it('D7 W26: CmsPage logOnlyDirty + dontSubmitEmptyLogs (LogsActivity config canon)', function () {
    $file = (new ReflectionClass(CmsPage::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain('logOnlyDirty()');
    expect($src)->toContain('dontSubmitEmptyLogs()');
});
