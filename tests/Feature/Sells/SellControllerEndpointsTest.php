<?php

declare(strict_types=1);

/**
 * Pest test estrutural — SellController endpoints US-SELL-008.
 *
 * Cobre os 2 endpoints REST canon do Cockpit Pattern V2 (ADR 0110):
 *   1. SellController@inertiaList — GET /sells-list-json
 *   2. SellController@sheetData — GET /sells/{id}/sheet-data
 *   3. SellController@index — Inertia::render('Sells/Index') quando não-AJAX
 *
 * Anti-regressão das gotchas conhecidas:
 *   - total_paid via subquery transaction_payments (NÃO coluna direct)
 *   - is_return tratado em soma (positive vs negative)
 *   - Permission scope explícito (defesa em profundidade — Tier 0)
 *   - Multi-tenant: business_id where clause obrigatória (ADR 0093)
 */

const SELL_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';

function readSellController(): string
{
    return file_get_contents(base_path(SELL_CONTROLLER_PATH));
}

// ─── SellController@index — Inertia render (Cockpit Pattern V2) ──────────────

it('SellController@index ainda existe', function () {
    $source = readSellController();
    expect($source)->toMatch('/public function index\\(\\)/');
});

it('SellController@index render Inertia Sells/Index (US-SELL-008)', function () {
    $source = readSellController();
    expect($source)->toContain("Inertia\\Inertia::render('Sells/Index'");
});

it('SellController@index passa sellKpis com 5 contadores canon', function () {
    $source = readSellController();
    expect($source)->toContain("'sellKpis'");
    expect($source)->toContain("'total'");
    expect($source)->toContain("'paid'");
    expect($source)->toContain("'due'");
    expect($source)->toContain("'partial'");
    expect($source)->toContain("'overdue'");
});

it('SellController@index calcula overdue via DATE_ADD pay_term (ADR 0110)', function () {
    $source = readSellController();
    // Pattern: DATE_ADD(transaction_date, INTERVAL pay_term_number DAY/MONTH) < CURDATE
    expect($source)->toContain('DATE_ADD(transaction_date');
    expect($source)->toContain('CURDATE()');
});

it('SellController@index passa permissions canon (create + view)', function () {
    $source = readSellController();
    expect($source)->toContain("'permissions'");
    expect($source)->toContain("'create'");
    expect($source)->toContain("direct_sell.access");
    expect($source)->toContain("direct_sell.view");
});

it('SellController@index respeita request()->ajax() pra DataTables legacy (NÃO regrediu)', function () {
    $source = readSellController();
    // O if (request()->ajax()) deve existir e VIR ANTES do Inertia::render.
    expect($source)->toMatch('/if\\s*\\(\\s*request\\(\\)->ajax\\(\\)\\s*\\)/');
});

// ─── SellController@inertiaList — GET /sells-list-json ──────────────────────

it('SellController@inertiaList existe (endpoint REST canon)', function () {
    $source = readSellController();
    expect($source)->toMatch('/public function inertiaList\\(/');
});

it('SellController@inertiaList tem permission gate (direct_sell.view + variants)', function () {
    $source = readSellController();
    expect($source)->toContain('direct_sell.view');
    expect($source)->toContain('view_own_sell_only');
    expect($source)->toContain('view_commission_agent_sell');
    // Aborta 403 se não tem permissão
    expect($source)->toContain('abort(403)');
});

it('SellController@inertiaList aplica multi-tenant scope (business_id) — ADR 0093 Tier 0', function () {
    $source = readSellController();
    expect($source)->toMatch('/inertiaList[\\s\\S]*?business_id.*\\$business_id/');
    expect($source)->toContain("session()->get('user.business_id')");
});

it('SellController@inertiaList total_paid usa SUBQUERY transaction_payments (NÃO coluna direct)', function () {
    $source = readSellController();
    // Regression do hotfix: total_paid não existe em transactions.
    // Pattern canon: COALESCE(SUM(IF(tp.is_return = 0, tp.amount, tp.amount * -1))) FROM transaction_payments
    expect($source)->toContain('transaction_payments');
    expect($source)->toContain('tp.is_return');
    expect($source)->toContain('total_paid');
    // Garante que NÃO tem 'transactions.total_paid' direct (regrediria o bug)
    expect($source)->not->toContain("'transactions.total_paid'");
});

it('SellController@inertiaList pill overdue usa pay_term + DATE_ADD < CURDATE', function () {
    $source = readSellController();
    expect($source)->toMatch('/payment_status[\\s\\S]*?===\\s*[\'"]overdue[\'"]/');
    expect($source)->toContain('pay_term_number');
    expect($source)->toContain('pay_term_type');
    expect($source)->toContain('DATE_ADD');
});

it('SellController@inertiaList retorna is_overdue boolean derivado por linha', function () {
    $source = readSellController();
    expect($source)->toContain('is_overdue');
    expect($source)->toContain('Carbon');
});

it('SellController@inertiaList limita 200 max (anti-DoS)', function () {
    $source = readSellController();
    expect($source)->toMatch('/min\\(\\(int\\)\\s*\\$request->input\\([\'"]limit[\'"][\\s\\S]*?,\\s*200\\)/');
});

it('SellController@inertiaList retorna JSON shape canon (8 fields + computed)', function () {
    $source = readSellController();
    // Campos canon pra tabela Inertia
    expect($source)->toContain("'invoice_no'");
    expect($source)->toContain("'transaction_date'");
    expect($source)->toContain("'final_total'");
    expect($source)->toContain("'total_paid'");
    expect($source)->toContain("'payment_status'");
    expect($source)->toContain("'shipping_status'");
    expect($source)->toContain("'customer_name'");
    expect($source)->toContain("'location_name'");
});

// ─── SellController@sheetData — GET /sells/{id}/sheet-data ───────────────────

it('SellController@sheetData existe (endpoint drawer)', function () {
    $source = readSellController();
    expect($source)->toMatch('/public function sheetData\\(/');
});

it('SellController@sheetData tem permission gate igual inertiaList', function () {
    $source = readSellController();
    // Padrão repete: gate antes da query
    expect($source)->toMatch('/sheetData[\\s\\S]*?direct_sell\\.view/');
});

it('SellController@sheetData aplica multi-tenant scope (business_id)', function () {
    $source = readSellController();
    expect($source)->toMatch('/sheetData[\\s\\S]*?business_id/');
});

it('SellController@sheetData total_paid via payment_lines reduce com is_return (regression hotfix)', function () {
    $source = readSellController();
    expect($source)->toContain('payment_lines->reduce');
    expect($source)->toContain('is_return');
    // Não usa $sale->total_paid direct (não existe em transactions)
    expect($source)->not->toMatch('/\\(float\\)\\s*\\$sale->total_paid\\b/');
});

it('SellController@sheetData payment_lines eager load inclui is_return (campos minimal)', function () {
    $source = readSellController();
    expect($source)->toContain('payment_lines:id,transaction_id,amount,method,paid_on,note,is_return');
});

it('SellController@sheetData abort 404 quando venda não existe (UX defensiva)', function () {
    $source = readSellController();
    expect($source)->toMatch('/sheetData[\\s\\S]*?abort\\(404\\)/');
});

it('SellController@sheetData retorna JSON shape canon (header + lines + payments + urls)', function () {
    $source = readSellController();
    expect($source)->toContain("'lines'");
    expect($source)->toContain("'payments'");
    expect($source)->toContain("'customer'");
    expect($source)->toContain("'urls'");
    expect($source)->toContain("/sells/' . \$sale->id . '/edit");
    expect($source)->toContain("/sells/' . \$sale->id . '/print");
});

it('SellController@sheetData filtra type=sell + sub_type=null (não retorna outros tipos transação)', function () {
    $source = readSellController();
    expect($source)->toMatch('/sheetData[\\s\\S]*?->where\\([\'"]type[\'"],\\s*[\'"]sell[\'"]/');
    expect($source)->toMatch('/sheetData[\\s\\S]*?->whereNull\\([\'"]sub_type[\'"]/');
});

// ─── Routes — registradas em routes/web.php ──────────────────────────────────

it('Route GET /sells-list-json registrada (endpoint REST canon)', function () {
    $routes = file_get_contents(base_path('routes/web.php'));
    expect($routes)->toMatch('/Route::get\\([\'"]\\/sells-list-json[\'"]/');
    expect($routes)->toContain("[SellController::class, 'inertiaList']");
});

it('Route GET /sells/{id}/sheet-data registrada (endpoint drawer)', function () {
    $routes = file_get_contents(base_path('routes/web.php'));
    expect($routes)->toMatch('/Route::get\\([\'"]\\/sells\\/\\{id\\}\\/sheet-data[\'"]/');
    expect($routes)->toContain("[SellController::class, 'sheetData']");
});

it('Routes /sells-list-json + sheet-data declaradas ANTES do Route::resource (evita conflito {id})', function () {
    $routes = file_get_contents(base_path('routes/web.php'));
    // Pattern: ambas as routes especiais antes da resource genérica
    $listPos = strpos($routes, '/sells-list-json');
    $sheetPos = strpos($routes, '/sells/{id}/sheet-data');
    $resourcePos = strpos($routes, "Route::resource('sells'");
    expect($listPos)->toBeLessThan($resourcePos);
    expect($sheetPos)->toBeLessThan($resourcePos);
});
