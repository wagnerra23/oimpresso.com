<?php

declare(strict_types=1);

use Modules\Cms\Repositories\CmsPageRepository;
use Modules\Cms\Services\CmsLeadService;
use Modules\Cms\Services\CmsPageService;
use Modules\Cms\Services\SiteContentService;

uses(Tests\TestCase::class);

/**
 * Wave 18 — Saturação D2 Pest + D4 architecture (Cms 63 → ≥90).
 *
 * Smoke tests de contrato: garante que as classes Service/Repository foram
 * registradas no autoloader corretamente e que a assinatura pública não regrediu.
 *
 * Não toca DB — apenas reflection + container resolve.
 *
 * Cobertura:
 *  023. CmsPageRepository resolve via container
 *  024. CmsPageRepository expõe assinatura pública canônica (porTipo, porTitulo, etc)
 *  025. CmsPageService resolve via container
 *  026. CmsPageService recebe CmsPageRepository por DI (constructor)
 *  027. CmsLeadService resolve via container
 *  028. CmsLeadService recebe PiiRedactor por DI (constructor)
 *  029. SiteContentService preservado e resolve via container
 *  030. Datasets cross-tenant: repository métodos não usam withoutGlobalScopes
 */

it('023. CmsPageRepository resolve via container Laravel', function () {
    $repo = app(CmsPageRepository::class);
    expect($repo)->toBeInstanceOf(CmsPageRepository::class);
});

it('024. CmsPageRepository expõe assinatura pública canônica', function () {
    $methods = collect((new ReflectionClass(CmsPageRepository::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->pluck('name')
        ->toArray();

    expect($methods)->toContain('porTipo');
    expect($methods)->toContain('porTitulo');
    expect($methods)->toContain('porTituloComMeta');
    expect($methods)->toContain('blogHabilitado');
    expect($methods)->toContain('contarPorTipo');
});

it('025. CmsPageService resolve via container Laravel', function () {
    $service = app(CmsPageService::class);
    expect($service)->toBeInstanceOf(CmsPageService::class);
});

it('026. CmsPageService recebe CmsPageRepository por DI (constructor)', function () {
    $params = (new ReflectionMethod(CmsPageService::class, '__construct'))->getParameters();
    $types = collect($params)->map(fn ($p) => $p->getType()?->getName())->toArray();

    expect($types)->toContain(CmsPageRepository::class);
});

it('027. CmsLeadService resolve via container Laravel', function () {
    $service = app(CmsLeadService::class);
    expect($service)->toBeInstanceOf(CmsLeadService::class);
});

it('028. CmsLeadService recebe PiiRedactor por DI (constructor)', function () {
    $params = (new ReflectionMethod(CmsLeadService::class, '__construct'))->getParameters();
    $types = collect($params)->map(fn ($p) => $p->getType()?->getName())->toArray();

    expect($types)->toContain('Modules\Jana\Services\Privacy\PiiRedactor');
});

it('029. SiteContentService preservado e resolve via container', function () {
    $service = app(SiteContentService::class);
    expect($service)->toBeInstanceOf(SiteContentService::class);
});

it('030. CmsPageRepository não usa withoutGlobalScopes sem justificativa (multi-tenant ADR 0093)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Repositories/CmsPageRepository.php'));
    // Deve não conter withoutGlobalScopes ou, se conter, deve ter comentário SUPERADMIN.
    if (str_contains($src, 'withoutGlobalScopes')) {
        expect($src)->toContain('// SUPERADMIN:');
    } else {
        expect(true)->toBeTrue(); // sem withoutGlobalScopes = ok
    }
});

it('031. CmsPageService não usa withoutGlobalScopes sem justificativa (multi-tenant ADR 0093)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Services/CmsPageService.php'));
    if (str_contains($src, 'withoutGlobalScopes')) {
        expect($src)->toContain('// SUPERADMIN:');
    } else {
        expect(true)->toBeTrue();
    }
});

it('032. CmsLeadService não usa withoutGlobalScopes sem justificativa (multi-tenant ADR 0093)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Services/CmsLeadService.php'));
    if (str_contains($src, 'withoutGlobalScopes')) {
        expect($src)->toContain('// SUPERADMIN:');
    } else {
        expect(true)->toBeTrue();
    }
});
