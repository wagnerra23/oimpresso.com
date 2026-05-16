<?php

declare(strict_types=1);

/**
 * Pest INERTIA — Pages/Sells/Drafts.tsx + SellController@getDrafts branch Inertia (Wave 1 W1-A).
 *
 * F4 QA do ADR 0104. Cobre:
 *   1. Page + charter existem
 *   2. Persistent Layout AppShellV2
 *   3. Interface SellsDraftsPageProps tipada (kpis + filters + permissions + urls)
 *   4. Controller branch X-Inertia + Inertia::render
 *   5. customers dropdown deferred (defer pattern Tier 0)
 *   6. KPI agregado eager (count leve)
 *   7. Tier 0 multi-tenant preservado
 *   8. Permission gate draft.view_all OR draft.view_own
 */

const DRAFTS_PAGE_PATH = 'resources/js/Pages/Sells/Drafts.tsx';
const DRAFTS_CHARTER_PATH = 'resources/js/Pages/Sells/Drafts.charter.md';
const DRAFTS_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';

function readDraftsPage(): string
{
    return file_get_contents(base_path(DRAFTS_PAGE_PATH));
}

function readDraftsMethod(): string
{
    $source = file_get_contents(base_path(DRAFTS_CONTROLLER_PATH));
    preg_match('/public function getDrafts\(\).*?(?=public function )/s', $source, $matches);
    return $matches[0] ?? '';
}

it('Page Drafts.tsx + Charter existem', function () {
    expect(file_exists(base_path(DRAFTS_PAGE_PATH)))->toBeTrue();
    expect(file_exists(base_path(DRAFTS_CHARTER_PATH)))->toBeTrue();
});

it('Charter Drafts declara mwart_pattern_reuse YAML', function () {
    $charter = file_get_contents(base_path(DRAFTS_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
    expect($charter)->toContain('derived_screens:');
});

it('Page Drafts Persistent Layout AppShellV2', function () {
    $source = readDraftsPage();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toMatch('/SellsDrafts\\.layout\\s*=\\s*\\(page/');
});

it('Page Drafts declara SellsDraftsPageProps tipada', function () {
    $source = readDraftsPage();
    expect($source)->toContain('SellsDraftsPageProps');
    expect($source)->toContain('kpis');
    expect($source)->toContain('filters');
    expect($source)->toContain('permissions');
    expect($source)->toContain('urls');
});

it('Page Drafts usa <Deferred data="customers"> (defer dropdown grande)', function () {
    expect(readDraftsPage())->toContain('<Deferred data="customers"');
});

it('Page Drafts NÃO usa sessionStorage', function () {
    expect(readDraftsPage())->not->toContain('sessionStorage');
});

it('Page Drafts NÃO usa cor crua', function () {
    expect(readDraftsPage())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Controller getDrafts() tem branch X-Inertia render', function () {
    $method = readDraftsMethod();
    expect($method)->toContain("request()->header('X-Inertia')");
    expect($method)->toContain("Inertia::render('Sells/Drafts'");
});

it('Controller getDrafts() usa Inertia::defer pro customers dropdown', function () {
    expect(readDraftsMethod())->toContain('Inertia::defer(');
});

it('Controller getDrafts() Tier 0 multi-tenant (ADR 0093)', function () {
    expect(readDraftsMethod())->toContain("session()->get('user.business_id')");
});

it('Controller getDrafts() NÃO usa withoutGlobalScopes', function () {
    expect(readDraftsMethod())->not->toContain('withoutGlobalScopes');
});

it('Controller getDrafts() permission gate draft.view_all OR draft.view_own', function () {
    $method = readDraftsMethod();
    expect($method)->toMatch("/can\\('draft\\.view_all'\\)/");
    expect($method)->toMatch("/can\\('draft\\.view_own'\\)/");
    expect($method)->toContain('abort(403');
});

it('Controller getDrafts() preserva Blade fallback view sale_pos.draft', function () {
    expect(readDraftsMethod())->toContain("view('sale_pos.draft')");
});

it('Controller getDrafts() filtra status=draft + sub_status IS NULL no count', function () {
    $method = readDraftsMethod();
    expect($method)->toContain("'status', 'draft'");
    expect($method)->toContain('whereNull(');
});
