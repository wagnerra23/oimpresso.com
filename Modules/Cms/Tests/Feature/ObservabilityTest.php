<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class);

/**
 * Wave 16 — Governance v3 D9 Observability (Cms 60 → 63+).
 *
 * Smoke tests sem dependência de Schema/DB:
 *  D9.a OtelHelper instrumentation:
 *   001. CmsPageController importa OtelHelper (instrumentado spans CRUD)
 *   002. CmsController importa OtelHelper (instrumentado spans render público)
 *   003. SettingsController importa OtelHelper (instrumentado save admin)
 *   004. CmsPageController declara span 'cms.page.create'
 *   005. CmsPageController declara span 'cms.page.update'
 *   006. CmsPageController declara span 'cms.page.delete'
 *   007. CmsPageController declara span 'cms.page.render'
 *   008. CmsController declara span 'cms.home.render'
 *
 *  D9.b Log estruturado:
 *   009. CmsPageController emite Log::info com chave biz (multi-tenant Tier 0)
 *
 *  D9.c Health command:
 *   010. CmsHealthCommand existe + registrado em CmsServiceProvider
 *   011. Schedule cms:health configurado em CmsServiceProvider
 *   012. php artisan cms:health roda sem erro (exit 0/1)
 *
 * Multi-tenant Tier 0 (ADR 0093) — testes não tocam DB; análise textual + smoke artisan.
 * OTel zero-cost quando disabled (default test env) — não dispara sampler real.
 */

// ---------- D9.a OtelHelper instrumentation ----------

it('001. CmsPageController importa OtelHelper (instrumentado D9.a)', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsPageController.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain('OtelHelper::spanBiz(');
});

it('002. CmsController importa OtelHelper (instrumentado D9.a)', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsController.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain('OtelHelper::spanBiz(');
});

it('003. SettingsController importa OtelHelper (instrumentado D9.a)', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/SettingsController.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain('OtelHelper::spanBiz(');
});

it('004. CmsPageController declara span cms.page.create', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsPageController.php'));

    expect($source)->toContain("'cms.page.create'");
});

it('005. CmsPageController declara span cms.page.update', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsPageController.php'));

    expect($source)->toContain("'cms.page.update'");
});

it('006. CmsPageController declara span cms.page.delete', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsPageController.php'));

    expect($source)->toContain("'cms.page.delete'");
});

it('007. CmsPageController declara span cms.page.render', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsPageController.php'));

    expect($source)->toContain("'cms.page.render'");
});

it('008. CmsController declara spans cms.home.render + cms.blog.list + cms.blog.view', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsController.php'));

    expect($source)->toContain("'cms.home.render'");
    expect($source)->toContain("'cms.blog.list'");
    expect($source)->toContain("'cms.blog.view'");
});

// ---------- D9.b Log estruturado ----------

it('009. CmsPageController emite Log::info estruturado com chave biz (multi-tenant Tier 0)', function () {
    $source = file_get_contents(base_path('Modules/Cms/Http/Controllers/CmsPageController.php'));

    expect($source)->toContain('use Illuminate\Support\Facades\Log;');
    expect($source)->toContain("Log::info('cms.page.created'");
    expect($source)->toContain("'biz' => session('user.business_id')");
});

// ---------- D9.c Health command ----------

it('010. CmsHealthCommand classe existe + registrado em CmsServiceProvider', function () {
    expect(class_exists(\Modules\Cms\Console\Commands\CmsHealthCommand::class))->toBeTrue();

    $provider = file_get_contents(base_path('Modules/Cms/Providers/CmsServiceProvider.php'));
    expect($provider)->toContain('CmsHealthCommand::class');
});

it('011. Schedule cms:health configurado em CmsServiceProvider', function () {
    $provider = file_get_contents(base_path('Modules/Cms/Providers/CmsServiceProvider.php'));

    expect($provider)->toContain('registerScheduleCommands');
    expect($provider)->toContain("'cms:health");
    expect($provider)->toContain("->dailyAt('03:30')");
});

it('012. comando cms:health roda sem erro (smoke artisan exit 0 ou 1)', function () {
    $exitCode = Artisan::call('cms:health');

    // Exit 0 (tudo OK) ou 1 (algum check falhou em DB vazio de test).
    // Importante: não throw exception / não crash.
    expect($exitCode)->toBeIn([0, 1]);
});
