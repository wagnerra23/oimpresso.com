<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Entities\CmsPage;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Repair\Entities\JobSheet;

uses(Tests\TestCase::class);

/**
 * Sprint 4 — adoção trait HasArquivos em 3 consumers (ADR 0123).
 *
 * Cobertura:
 * - JobSheet (Repair) — fotos OS via foto_arquivos
 * - CmsPage (CMS) — feature_image via feature_image_arquivo
 * - BoletoRemessa (Financeiro) — PDF via pdf_arquivo
 *
 * Backward compat: cada accessor retorna null se sem arquivo (não throw),
 * preservando coluna legacy (`feature_image`/`pdf_path`/Media morphMany).
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 4 plano
 */

// Guard SQLite: Models com BusinessScope / accessors que chamam relações morfológicas
// requerem schema MySQL UltimatePOS (repair_job_sheets, cms_pages, etc).
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models com BusinessScope requerem schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
    }
});

it('Repair JobSheet usa trait HasArquivos', function () {
    $traits = (new ReflectionClass(JobSheet::class))->getTraitNames();
    expect($traits)->toContain('Modules\\Arquivos\\Concerns\\HasArquivos');
});

it('Repair JobSheet expõe foto_arquivos accessor (Collection)', function () {
    $jobsheet = new JobSheet();
    expect(method_exists($jobsheet, 'arquivos'))->toBeTrue();
    // Sem arquivos relacionados, accessor retorna Collection vazia
    $fotos = $jobsheet->foto_arquivos;
    expect($fotos)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

it('Repair JobSheet preserva relação media() legacy', function () {
    $jobsheet = new JobSheet();
    expect(method_exists($jobsheet, 'media'))->toBeTrue();
});

it('Cms CmsPage usa trait HasArquivos', function () {
    $traits = (new ReflectionClass(CmsPage::class))->getTraitNames();
    expect($traits)->toContain('Modules\\Arquivos\\Concerns\\HasArquivos');
});

it('Cms CmsPage feature_image_arquivo accessor retorna null sem arquivo', function () {
    $page = new CmsPage();
    expect($page->feature_image_arquivo)->toBeNull();
});

it('Cms CmsPage feature_image_url accessor preserva fallback legacy', function () {
    // Sem arquivo + sem feature_image string -> null
    $page = new CmsPage();
    expect($page->feature_image_url)->toBeNull();

    // Com feature_image string legacy -> URL legacy
    $page2 = new CmsPage();
    $page2->feature_image = 'logo.png';
    expect($page2->feature_image_url)->toContain('uploads/cms/logo.png');
});

it('Financeiro BoletoRemessa usa trait HasArquivos', function () {
    $traits = (new ReflectionClass(BoletoRemessa::class))->getTraitNames();
    expect($traits)->toContain('Modules\\Arquivos\\Concerns\\HasArquivos');
});

it('Financeiro BoletoRemessa pdf_arquivo accessor retorna null sem arquivo', function () {
    $boleto = new BoletoRemessa();
    expect($boleto->pdf_arquivo)->toBeNull();
});

it('Financeiro BoletoRemessa preserva HasFactory + SoftDeletes + BusinessScope', function () {
    $traits = (new ReflectionClass(BoletoRemessa::class))->getTraitNames();
    expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
    expect($traits)->toContain('Illuminate\Database\Eloquent\SoftDeletes');
    expect($traits)->toContain('Modules\Financeiro\Models\Concerns\BusinessScope');
    // HasArquivos coexiste sem conflito
    expect($traits)->toContain('Modules\Arquivos\Concerns\HasArquivos');
});
