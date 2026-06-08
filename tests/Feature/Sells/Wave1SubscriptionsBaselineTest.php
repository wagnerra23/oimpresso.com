<?php

declare(strict_types=1);

/**
 * Pest BASELINE — SellPosController@listSubscriptions ANTES da migração MWART (Wave 1 W1-A).
 *
 * F2 BACKEND BASELINE do ADR 0104. Cobre:
 *   1. Permission gate: sell.view OR direct_sell.access
 *   2. business_id session scope (Tier 0 ADR 0093)
 *   3. Filtro DB obrigatório: type='sell' + status='final' + is_recurring=1
 *   4. permitted_locations (location-level scoping)
 *   5. Branch view('sale_pos.subscriptions') preservado fallback
 *   6. AJAX DataTables retornado se request()->ajax()
 */

const CONTROLLER_PATH_SUBSCRIPTIONS = 'app/Http/Controllers/SellPosController.php';

function readSubscriptionsController(): string
{
    return file_get_contents(base_path(CONTROLLER_PATH_SUBSCRIPTIONS));
}

it('Método listSubscriptions() está declarado e público', function () {
    $source = readSubscriptionsController();
    expect($source)->toMatch('/public function listSubscriptions\(\)/');
});

it('listSubscriptions() bloqueia sem permissão sell.view NEM direct_sell.access (403)', function () {
    $source = readSubscriptionsController();
    preg_match('/public function listSubscriptions\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toMatch("/can\\('sell\\.view'\\)/");
    expect($block)->toMatch("/can\\('direct_sell\\.access'\\)/");
    expect($block)->toContain('abort(403');
});

it('listSubscriptions() respeita Tier 0 multi-tenant (transactions.business_id)', function () {
    $source = readSubscriptionsController();
    preg_match('/public function listSubscriptions\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("'transactions.business_id', \$business_id");
});

it('listSubscriptions() filtra is_recurring=1 + status=final + type=sell', function () {
    $source = readSubscriptionsController();
    preg_match('/public function listSubscriptions\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("'transactions.type', 'sell'");
    expect($block)->toContain("'transactions.status', 'final'");
    expect($block)->toContain("'transactions.is_recurring', 1");
});

it('listSubscriptions() respeita permitted_locations (location-level scoping)', function () {
    $source = readSubscriptionsController();
    preg_match('/public function listSubscriptions\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain('permitted_locations');
});

it('listSubscriptions() preserva view sale_pos.subscriptions (Blade legacy fallback)', function () {
    $source = readSubscriptionsController();
    preg_match('/public function listSubscriptions\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("view('sale_pos.subscriptions')");
});

it('listSubscriptions() processa AJAX DataTables (back-compat)', function () {
    $source = readSubscriptionsController();
    preg_match('/public function listSubscriptions\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain('request()->ajax()');
    expect($block)->toContain('Datatables::of');
});
