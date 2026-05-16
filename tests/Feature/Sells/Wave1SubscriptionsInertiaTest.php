<?php

declare(strict_types=1);

/**
 * Pest INERTIA — Pages/Sells/Subscriptions.tsx + SellPosController@listSubscriptions branch Inertia (Wave 1 W1-A).
 *
 * F4 QA do ADR 0104. Cobre:
 *   1. Page + charter existem
 *   2. Persistent Layout AppShellV2
 *   3. Interface SellsSubscriptionsPageProps tipada (kpis 3-tier + filters + permissions + urls)
 *   4. Controller branch X-Inertia + Inertia::render
 *   5. Customers deferred
 *   6. Filtro DB is_recurring=1 + status=final + type=sell
 *   7. KPIs ativas/pausadas/total agregados leves eager
 *   8. AJAX DataTables back-compat preservado (branch ajax)
 *   9. Permission gate sell.view OR direct_sell.access
 */

const SUBSCRIPTIONS_PAGE_PATH = 'resources/js/Pages/Sells/Subscriptions.tsx';
const SUBSCRIPTIONS_CHARTER_PATH = 'resources/js/Pages/Sells/Subscriptions.charter.md';
const SUBSCRIPTIONS_CONTROLLER_PATH = 'app/Http/Controllers/SellPosController.php';

function readSubscriptionsPage(): string
{
    return file_get_contents(base_path(SUBSCRIPTIONS_PAGE_PATH));
}

function readSubscriptionsMethod(): string
{
    $source = file_get_contents(base_path(SUBSCRIPTIONS_CONTROLLER_PATH));
    preg_match('/public function listSubscriptions\(\).*?(?=public function )/s', $source, $matches);
    return $matches[0] ?? '';
}

it('Page Subscriptions.tsx + Charter existem', function () {
    expect(file_exists(base_path(SUBSCRIPTIONS_PAGE_PATH)))->toBeTrue();
    expect(file_exists(base_path(SUBSCRIPTIONS_CHARTER_PATH)))->toBeTrue();
});

it('Charter Subscriptions declara mwart_pattern_reuse YAML', function () {
    $charter = file_get_contents(base_path(SUBSCRIPTIONS_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('Page Subscriptions Persistent Layout AppShellV2', function () {
    $source = readSubscriptionsPage();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toMatch('/SellsSubscriptions\\.layout\\s*=\\s*\\(page/');
});

it('Page Subscriptions declara SellsSubscriptionsPageProps com 3 KPIs', function () {
    $source = readSubscriptionsPage();
    expect($source)->toContain('SellsSubscriptionsPageProps');
    expect($source)->toContain('total: number');
    expect($source)->toContain('active: number');
    expect($source)->toContain('stopped: number');
});

it('Page Subscriptions tem toggle inline start/stop por linha', function () {
    $source = readSubscriptionsPage();
    expect($source)->toContain('toggleRecurring');
    expect($source)->toContain('urls.toggle');
});

it('Page Subscriptions usa <Deferred data="customers">', function () {
    expect(readSubscriptionsPage())->toContain('<Deferred data="customers"');
});

it('Page Subscriptions NÃO usa sessionStorage / cor crua', function () {
    $source = readSubscriptionsPage();
    expect($source)->not->toContain('sessionStorage');
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Controller listSubscriptions() branch X-Inertia + Inertia::render', function () {
    $method = readSubscriptionsMethod();
    expect($method)->toContain("request()->header('X-Inertia')");
    expect($method)->toContain("Inertia::render('Sells/Subscriptions'");
});

it('Controller listSubscriptions() Tier 0 + filtro is_recurring=1', function () {
    $method = readSubscriptionsMethod();
    expect($method)->toContain("where('business_id'");
    expect($method)->toContain("'is_recurring', 1");
});

it('Controller listSubscriptions() KPIs eager (total/active/stopped count)', function () {
    $method = readSubscriptionsMethod();
    expect($method)->toContain("'total'");
    expect($method)->toContain("'active'");
    expect($method)->toContain("'stopped'");
    expect($method)->toContain('whereNull(\'recur_stopped_on\')');
    expect($method)->toContain('whereNotNull(\'recur_stopped_on\')');
});

it('Controller listSubscriptions() usa Inertia::defer pro customers', function () {
    expect(readSubscriptionsMethod())->toContain('Inertia::defer(');
});

it('Controller listSubscriptions() AJAX DataTables preservado (back-compat)', function () {
    $method = readSubscriptionsMethod();
    expect($method)->toContain('request()->ajax()');
    expect($method)->toContain('Datatables::of');
});

it('Controller listSubscriptions() preserva Blade fallback view sale_pos.subscriptions', function () {
    expect(readSubscriptionsMethod())->toContain("view('sale_pos.subscriptions')");
});

it('Controller listSubscriptions() permission gate sell.view OR direct_sell.access', function () {
    $method = readSubscriptionsMethod();
    expect($method)->toMatch("/can\\('sell\\.view'\\)/");
    expect($method)->toMatch("/can\\('direct_sell\\.access'\\)/");
    expect($method)->toContain('abort(403');
});

it('Controller listSubscriptions() NÃO usa withoutGlobalScopes', function () {
    expect(readSubscriptionsMethod())->not->toContain('withoutGlobalScopes');
});
