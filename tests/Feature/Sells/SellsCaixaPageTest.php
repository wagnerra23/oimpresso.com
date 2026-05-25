<?php

declare(strict_types=1);

/**
 * Pest GUARDs estruturais — Sells/Caixa/Index Onda 6 (ADR 0192 A1 KB-9.75).
 *
 * Cobre:
 *  - SellController@inertiaCaixa existe + assina Request + render Inertia 'Sells/Caixa/Index'
 *  - Permission gate canon (direct_sell.view + variants) ANTES de qualquer query
 *  - Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL: business_id em todo where() (defesa em profundidade)
 *  - Shape canon das props: porFormaPagamento + porOrigem + caixaAberto + cashRegisterId + dateSelected
 *  - Refs OS clicáveis: shape {id, invoice_no, os_ref} (cross-módulo dispatch event)
 *  - Rota /vendas/caixa registrada em routes/web.php (nome 'vendas.caixa')
 *  - Charter rascunho + visual-comparison existem (governance Tier 0)
 *
 * Pattern Pest estrutural (lê source, não roda Eloquent — SQLite incompatível
 * com schema UltimatePOS MySQL conforme ADR 0101). Mesmo padrão de
 * SellControllerEndpointsTest.php.
 *
 * @see app/Http/Controllers/SellController.php::inertiaCaixa()
 * @see resources/js/Pages/Sells/Caixa/Index.tsx
 * @see resources/js/Pages/Sells/Caixa/Index.charter.md
 * @see memory/requisitos/Sells/Caixa-r1-visual-comparison.md
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 */

const CAIXA_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';
const CAIXA_ROUTES_PATH = 'routes/web.php';
const CAIXA_PAGE_PATH = 'resources/js/Pages/Sells/Caixa/Index.tsx';
const CAIXA_CHARTER_PATH = 'resources/js/Pages/Sells/Caixa/Index.charter.md';
const CAIXA_VISUAL_COMPARISON_PATH = 'memory/requisitos/Sells/Caixa-r1-visual-comparison.md';

function caixaRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── SellController@inertiaCaixa — endpoint Inertia render ──────────────────

it('SellController@inertiaCaixa existe (Onda 6 ADR 0192)', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    expect($source)->toMatch('/public function inertiaCaixa\\(/');
});

it('SellController@inertiaCaixa render Inertia Sells/Caixa/Index', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    expect($source)->toContain("Inertia\\Inertia::render('Sells/Caixa/Index'");
});

it('SellController@inertiaCaixa tem permission gate direct_sell.view ANTES da query (defesa Tier 0)', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    // Gate canonical paridade Sells/Index — abort 403 se nenhum dos 3 cans
    expect($source)->toMatch('/inertiaCaixa[\\s\\S]*?direct_sell\\.view[\\s\\S]*?view_own_sell_only[\\s\\S]*?view_commission_agent_sell[\\s\\S]*?abort\\(403/');
});

it('SellController@inertiaCaixa aplica multi-tenant scope business_id ADR 0093 Tier 0', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    // Múltiplos where() repetindo business_id (defesa em profundidade)
    expect($source)->toMatch('/inertiaCaixa[\\s\\S]*?business_id.*\\$business_id/');
    expect($source)->toMatch('/inertiaCaixa[\\s\\S]*?session\\(\\)->get\\([\'"]user\\.business_id[\'"]\\)/');
});

it('SellController@inertiaCaixa retorna porFormaPagamento agregado via transaction_payments', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    expect($source)->toContain("'porFormaPagamento'");
    // Agrega via tp (transaction_payments) — pay_method canonical UPOS
    expect($source)->toContain('transaction_payments');
});

it('SellController@inertiaCaixa retorna porOrigem com COALESCE source default balcao', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    expect($source)->toContain("'porOrigem'");
    // Default 'balcao' retroativo pra vendas legacy (migration default)
    expect($source)->toContain("COALESCE(transactions.source, 'balcao')");
});

it('SellController@inertiaCaixa refs OS shape canon {id, invoice_no, os_ref}', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    // Shape pra cross-módulo dispatch event oimpresso:open-venda
    expect($source)->toMatch('/inertiaCaixa[\\s\\S]*?[\'"]id[\'"][\\s\\S]*?[\'"]invoice_no[\'"][\\s\\S]*?[\'"]os_ref[\'"]/');
    // Refs apenas vendas com os_ref (oficina)
    expect($source)->toContain('whereNotNull');
    expect($source)->toContain('os_ref');
});

it('SellController@inertiaCaixa retorna caixaAberto bool + cashRegisterId', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    expect($source)->toContain("'caixaAberto'");
    expect($source)->toContain("'cashRegisterId'");
    expect($source)->toContain("CashRegister::where");
    expect($source)->toContain("'status', 'open'");
});

it('SellController@inertiaCaixa filtra por date param + default hoje', function () {
    $source = caixaRead(CAIXA_CONTROLLER_PATH);
    expect($source)->toContain("'dateSelected'");
    expect($source)->toMatch('/inertiaCaixa[\\s\\S]*?\\$request->input\\([\'"]date[\'"]/');
});

// ─── Rota /vendas/caixa registrada ────────────────────────────────────────

it('Rota /vendas/caixa registrada em routes/web.php (Onda 6)', function () {
    $routes = caixaRead(CAIXA_ROUTES_PATH);
    expect($routes)->toContain("/vendas/caixa");
    expect($routes)->toContain("'inertiaCaixa'");
    expect($routes)->toContain("vendas.caixa"); // name
});

it('Rota /cash-register/* legacy preservada (decisão coexistência Wagner 2026-05-25)', function () {
    $routes = caixaRead(CAIXA_ROUTES_PATH);
    // Rollback trivial — legacy Blade INTACTA
    expect($routes)->toContain('cash-register/close-register');
    expect($routes)->toContain('cash-register/register-details');
    expect($routes)->toContain("Route::resource('cash-register'");
});

// ─── Charter + visual-comparison (governance) ─────────────────────────────

it('Charter Sells/Caixa/Index.charter.md existe (status rascunho)', function () {
    expect(file_exists(base_path(CAIXA_CHARTER_PATH)))->toBeTrue();
    $charter = caixaRead(CAIXA_CHARTER_PATH);
    expect($charter)->toContain('page: /vendas/caixa');
    expect($charter)->toContain('status: rascunho');
    expect($charter)->toContain('parent_module: Sells');
    expect($charter)->toContain('0192'); // related_adrs
});

it('Visual comparison Caixa-r1 existe com 15 dimensões (skill mwart-comparative V4)', function () {
    expect(file_exists(base_path(CAIXA_VISUAL_COMPARISON_PATH)))->toBeTrue();
    $vc = caixaRead(CAIXA_VISUAL_COMPARISON_PATH);
    expect($vc)->toContain('15 dimensões');
    expect($vc)->toContain('vendas-extras.jsx');
    expect($vc)->toContain('Por origem');
});

// ─── Page Inertia ─────────────────────────────────────────────────────────

it('Sells/Caixa/Index.tsx existe + dispatch CustomEvent cross-módulo', function () {
    expect(file_exists(base_path(CAIXA_PAGE_PATH)))->toBeTrue();
    $tsx = caixaRead(CAIXA_PAGE_PATH);
    // Wrapper Cowork
    expect($tsx)->toContain("className=\"sells-cowork\"");
    expect($tsx)->toContain('vc-page');
    expect($tsx)->toContain('vc-card-source'); // section Por origem
    // Cross-módulo: navega /sells?open=ID (Sells/Index detecta na monta)
    expect($tsx)->toContain('/sells?open=');
});

it('Sells/Index.tsx detecta deep-link ?open=ID (Onda 6 cross-página)', function () {
    $tsx = caixaRead('resources/js/Pages/Sells/Index.tsx');
    // Pattern padrão URLSearchParams
    expect($tsx)->toContain("URLSearchParams");
    expect($tsx)->toContain("params.get('open')");
});
