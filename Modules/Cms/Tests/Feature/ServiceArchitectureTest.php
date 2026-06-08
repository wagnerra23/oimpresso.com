<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Wave 18 — Saturação D4 Architecture (Cms 63 → ≥90).
 *
 * Garante que o módulo evoluiu para o pattern Controller (thin) → Service →
 * Repository, e que cada peça nova possui instrumentação D9.a obrigatória.
 *
 * Por que análise textual em vez de boot HTTP:
 * - Sem dependência de schema/DB → roda em qualquer ambiente Pest.
 * - Detecta drift se alguém remover Service/Repository/OTel num PR futuro.
 *
 * Cobertura:
 *  D4.a Service/Controller ratio
 *   001. CmsPageService.php existe
 *   002. CmsLeadService.php existe
 *   003. SiteContentService.php existe (preservado da Wave 17)
 *   004. CmsPageRepository.php existe
 *  D4.d AuditLog + OTel
 *   005. CmsPageService instrumentado com OtelHelper::spanBiz
 *   006. CmsLeadService instrumentado com OtelHelper::spanBiz
 *   007. CmsPageRepository instrumentado com OtelHelper::spanBiz
 *   008. CmsPage Entity tem LogsActivity (audit trail D7.b)
 *  Multi-tenant Tier 0
 *   009. CmsPageRepository declara baseQuery() ponto único pra business_id scope
 *   010. CmsPageService cita Tier 0 ADR 0093 no docblock
 */

it('001. CmsPageService.php existe e namespace canônico', function () {
    $path = base_path('Modules/Cms/Services/CmsPageService.php');
    expect(file_exists($path))->toBeTrue("CmsPageService.php deveria existir em {$path}");
    expect(file_get_contents($path))->toContain('namespace Modules\Cms\Services;');
});

it('002. CmsLeadService.php existe e namespace canônico', function () {
    $path = base_path('Modules/Cms/Services/CmsLeadService.php');
    expect(file_exists($path))->toBeTrue("CmsLeadService.php deveria existir em {$path}");
    expect(file_get_contents($path))->toContain('namespace Modules\Cms\Services;');
});

it('003. SiteContentService.php preservado (Wave 17)', function () {
    $path = base_path('Modules/Cms/Services/SiteContentService.php');
    expect(file_exists($path))->toBeTrue();
});

it('004. CmsPageRepository.php existe e namespace canônico', function () {
    $path = base_path('Modules/Cms/Repositories/CmsPageRepository.php');
    expect(file_exists($path))->toBeTrue("CmsPageRepository.php deveria existir em {$path}");
    expect(file_get_contents($path))->toContain('namespace Modules\Cms\Repositories;');
});

it('005. CmsPageService instrumentado com OtelHelper::spanBiz (D9.a)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Services/CmsPageService.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain('OtelHelper::spanBiz(');
    expect($src)->toContain("'cms.service.page.criar'");
    expect($src)->toContain("'cms.service.page.atualizar'");
    expect($src)->toContain("'cms.service.page.remover'");
});

it('006. CmsLeadService instrumentado com OtelHelper::spanBiz (D9.a)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Services/CmsLeadService.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("'cms.service.lead.capturar'");
});

it('007. CmsPageRepository instrumentado com OtelHelper::spanBiz (D9.a)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Repositories/CmsPageRepository.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("'cms.repo.por_tipo'");
    expect($src)->toContain("'cms.repo.por_titulo'");
    expect($src)->toContain("'cms.repo.blog_habilitado'");
});

it('008. CmsPage Entity usa LogsActivity (D7.b audit trail LGPD)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Entities/CmsPage.php'));
    expect($src)->toContain('use Spatie\Activitylog\Traits\LogsActivity;');
    expect($src)->toContain('use LogsActivity;');
});

it('009. CmsPageRepository declara baseQuery() ponto único (multi-tenant ADR 0093)', function () {
    $src = file_get_contents(base_path('Modules/Cms/Repositories/CmsPageRepository.php'));
    expect($src)->toContain('protected function baseQuery()');
    expect($src)->toContain('US-CMS-002');
});

it('010. CmsPageService cita Tier 0 ADR 0093 no docblock', function () {
    $src = file_get_contents(base_path('Modules/Cms/Services/CmsPageService.php'));
    expect($src)->toContain('ADR 0093');
});
