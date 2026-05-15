<?php

declare(strict_types=1);

/**
 * Pest INERTIA — Pages/Sells/Quotations.tsx + SellController@getQuotations branch Inertia (Wave 1 W1-A).
 *
 * F4 QA do ADR 0104. Cobre:
 *   1. Page + charter existem
 *   2. Persistent Layout AppShellV2
 *   3. Interface SellsQuotationsPageProps tipada
 *   4. Controller branch X-Inertia + Inertia::render('Sells/Quotations', ...)
 *   5. Customers deferred
 *   6. Filtro DB sub_status='quotation' (canon UltimatePOS)
 *   7. Tier 0 multi-tenant
 *   8. Permission gate quotation.view_all OR quotation.view_own
 */

const QUOTATIONS_PAGE_PATH = 'resources/js/Pages/Sells/Quotations.tsx';
const QUOTATIONS_CHARTER_PATH = 'resources/js/Pages/Sells/Quotations.charter.md';
const QUOTATIONS_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';

function readQuotationsPage(): string
{
    return file_get_contents(base_path(QUOTATIONS_PAGE_PATH));
}

function readQuotationsMethod(): string
{
    $source = file_get_contents(base_path(QUOTATIONS_CONTROLLER_PATH));
    preg_match('/public function getQuotations\(\).*?(?=public function )/s', $source, $matches);
    return $matches[0] ?? '';
}

it('Page Quotations.tsx + Charter existem', function () {
    expect(file_exists(base_path(QUOTATIONS_PAGE_PATH)))->toBeTrue();
    expect(file_exists(base_path(QUOTATIONS_CHARTER_PATH)))->toBeTrue();
});

it('Charter Quotations declara mwart_pattern_reuse YAML', function () {
    $charter = file_get_contents(base_path(QUOTATIONS_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('Page Quotations Persistent Layout AppShellV2', function () {
    $source = readQuotationsPage();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toMatch('/SellsQuotations\\.layout\\s*=\\s*\\(page/');
});

it('Page Quotations declara SellsQuotationsPageProps', function () {
    $source = readQuotationsPage();
    expect($source)->toContain('SellsQuotationsPageProps');
    expect($source)->toContain('kpis');
    expect($source)->toContain('urls');
});

it('Page Quotations usa <Deferred data="customers">', function () {
    expect(readQuotationsPage())->toContain('<Deferred data="customers"');
});

it('Page Quotations NÃO usa sessionStorage / cor crua', function () {
    $source = readQuotationsPage();
    expect($source)->not->toContain('sessionStorage');
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Controller getQuotations() branch X-Inertia + Inertia::render', function () {
    $method = readQuotationsMethod();
    expect($method)->toContain("request()->header('X-Inertia')");
    expect($method)->toContain("Inertia::render('Sells/Quotations'");
});

it('Controller getQuotations() usa Inertia::defer pro customers', function () {
    expect(readQuotationsMethod())->toContain('Inertia::defer(');
});

it('Controller getQuotations() Tier 0 + filtro sub_status=quotation', function () {
    $method = readQuotationsMethod();
    expect($method)->toContain("where('business_id'");
    expect($method)->toContain("'sub_status', 'quotation'");
});

it('Controller getQuotations() permission gate quotation.view_all OR quotation.view_own', function () {
    $method = readQuotationsMethod();
    expect($method)->toMatch("/can\\('quotation\\.view_all'\\)/");
    expect($method)->toMatch("/can\\('quotation\\.view_own'\\)/");
    expect($method)->toContain('abort(403');
});

it('Controller getQuotations() preserva Blade fallback view sale_pos.quotations', function () {
    expect(readQuotationsMethod())->toContain("view('sale_pos.quotations')");
});

it('Controller getQuotations() NÃO usa withoutGlobalScopes', function () {
    expect(readQuotationsMethod())->not->toContain('withoutGlobalScopes');
});
