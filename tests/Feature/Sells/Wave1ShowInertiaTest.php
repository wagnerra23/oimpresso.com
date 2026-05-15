<?php

declare(strict_types=1);

/**
 * Pest INERTIA — Pages/Sells/Show.tsx + SellController@show branch Inertia (Wave 1 W1-A).
 *
 * F4 QA do ADR 0104. Cobre:
 *   1. Page Inertia existe no path esperado
 *   2. Persistent Layout AppShellV2
 *   3. Interface SellsShowPageProps declarada (TypeScript contract)
 *   4. Controller tem branch Inertia (header X-Inertia)
 *   5. Controller usa Inertia::defer pro detail payload
 *   6. Multi-tenant Tier 0 (business_id) preservado no branch Inertia (ADR 0093)
 *   7. Permission gate ativo
 *   8. ZERO uso de withoutGlobalScopes no branch Inertia (Tier 0 IRREVOGÁVEL)
 *   9. FSM safety: current_stage_key apenas exposto, NUNCA setado
 */

const SHOW_PAGE_PATH = 'resources/js/Pages/Sells/Show.tsx';
const SHOW_CHARTER_PATH = 'resources/js/Pages/Sells/Show.charter.md';
const SHOW_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';

function readShowPage(): string
{
    return file_get_contents(base_path(SHOW_PAGE_PATH));
}

function readShowMethod(): string
{
    $source = file_get_contents(base_path(SHOW_CONTROLLER_PATH));
    preg_match('/public function show\(\$id\).*?(?=public function )/s', $source, $matches);
    return $matches[0] ?? '';
}

it('Page Inertia Sells/Show.tsx existe', function () {
    expect(file_exists(base_path(SHOW_PAGE_PATH)))->toBeTrue();
});

it('Charter Show.charter.md existe (MWART canon)', function () {
    expect(file_exists(base_path(SHOW_CHARTER_PATH)))->toBeTrue();
});

it('Charter Show declara mwart_pattern_reuse YAML (ADR 0149)', function () {
    $charter = file_get_contents(base_path(SHOW_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
    expect($charter)->toContain('blueprint_cowork:');
    expect($charter)->toContain('derived_screens:');
});

it('Page importa AppShellV2 (Persistent Layout)', function () {
    expect(readShowPage())->toContain('@/Layouts/AppShellV2');
});

it('Page usa Persistent Layout via .layout = (page) =>', function () {
    $source = readShowPage();
    expect($source)->toMatch('/SellsShow\\.layout\\s*=\\s*\\(page/');
    expect($source)->toContain('<AppShellV2>');
});

it('Page NÃO envolve em <AppShell> inline (preference_persistent_layouts)', function () {
    expect(readShowPage())->not->toMatch('/<AppShell[^V][^2>]/');
});

it('Page declara interface SellsShowPageProps (TypeScript contract)', function () {
    $source = readShowPage();
    expect($source)->toContain('SellsShowPageProps');
    expect($source)->toContain('saleId');
    expect($source)->toContain('headline');
    expect($source)->toContain('permissions');
});

it('Page usa <Deferred data="detail"> wrap pra payload pesado (Tier 0 inertia-defer)', function () {
    $source = readShowPage();
    expect($source)->toContain('<Deferred data="detail"');
});

it('Page NÃO usa sessionStorage (canon = localStorage prefixed)', function () {
    expect(readShowPage())->not->toContain('sessionStorage');
});

it('Page NÃO usa cor crua proibida ADR 0110', function () {
    expect(readShowPage())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Controller show() tem branch X-Inertia (Wave 1 dual response)', function () {
    expect(readShowMethod())->toContain("request()->header('X-Inertia')");
});

it('Controller show() usa Inertia::render(Sells/Show, ...) no branch', function () {
    expect(readShowMethod())->toContain("Inertia::render('Sells/Show'");
});

it('Controller show() usa Inertia::defer pro detail (skill inertia-defer-default Tier B)', function () {
    expect(readShowMethod())->toContain('Inertia::defer(');
});

it('Controller show() branch Inertia preserva scope business_id (Tier 0 ADR 0093)', function () {
    $method = readShowMethod();
    // Branch Inertia roda APÓS query base com where business_id, então toda
    // expansão Inertia::render herda isolation. Sanity: scope continua presente.
    expect($method)->toContain("where('business_id', \$business_id)");
});

it('Controller show() NÃO usa withoutGlobalScopes (Tier 0 IRREVOGÁVEL)', function () {
    expect(readShowMethod())->not->toContain('withoutGlobalScopes');
});

it('Controller show() permission gate ativo (abort 403 quando sem perm)', function () {
    $method = readShowMethod();
    expect($method)->toContain("can('sell.view')");
    expect($method)->toContain("can('direct_sell.access')");
    expect($method)->toContain("can('view_own_sell_only')");
    expect($method)->toContain('abort(403');
});

it('Controller show() preserva branch Blade legacy view(sale_pos.show) (back-compat)', function () {
    expect(readShowMethod())->toContain("view('sale_pos.show')");
});

it('Controller show() expõe current_stage_key MAS NÃO seta (FSM safety ADR 0143)', function () {
    $method = readShowMethod();
    // Headline tem `'current_stage_key' => null` (apenas leitura), nunca write/update.
    expect($method)->toContain("'current_stage_key'");
    // Operações de mutação no campo current_stage_id são proibidas no método show.
    expect($method)->not->toMatch('/\$\w+->current_stage_id\s*=/');
});

it('Charter declara screen-pattern reuse de Sells/Index/SaleSheet (ADR 0149)', function () {
    $charter = file_get_contents(base_path(SHOW_CHARTER_PATH));
    expect($charter)->toContain('vendas-cockpit');
});
