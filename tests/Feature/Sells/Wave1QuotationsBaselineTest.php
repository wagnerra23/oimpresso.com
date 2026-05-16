<?php

declare(strict_types=1);

/**
 * Pest BASELINE — SellController@getQuotations ANTES da migração MWART (Wave 1 W1-A).
 *
 * F2 BACKEND BASELINE do ADR 0104. Cobre:
 *   1. Permission gate: quotation.view_all OR quotation.view_own
 *   2. business_id session scope (Tier 0 ADR 0093)
 *   3. Dropdowns auxiliares carregados
 *   4. Branch view('sale_pos.quotations') preservado fallback
 *   5. AJAX irmão getDraftDatables com is_quotation=1 + sub_status='quotation'
 */

const CONTROLLER_PATH_QUOTATIONS = 'app/Http/Controllers/SellController.php';

function readQuotationsController(): string
{
    return file_get_contents(base_path(CONTROLLER_PATH_QUOTATIONS));
}

it('Método getQuotations() está declarado e público', function () {
    $source = readQuotationsController();
    expect($source)->toMatch('/public function getQuotations\(\)/');
});

it('getQuotations() bloqueia sem permissão quotation.view_all NEM quotation.view_own (403)', function () {
    $source = readQuotationsController();
    preg_match('/public function getQuotations\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toMatch("/can\\('quotation\\.view_all'\\)/");
    expect($block)->toMatch("/can\\('quotation\\.view_own'\\)/");
    expect($block)->toContain('abort(403');
});

it('getQuotations() pega business_id da session (Tier 0)', function () {
    $source = readQuotationsController();
    preg_match('/public function getQuotations\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("session()->get('user.business_id')");
});

it('getQuotations() carrega dropdowns auxiliares', function () {
    $source = readQuotationsController();
    preg_match('/public function getQuotations\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain('BusinessLocation::forDropdown');
    expect($block)->toContain('Contact::customersDropdown');
    expect($block)->toContain('User::forDropdown');
});

it('getQuotations() preserva view sale_pos.quotations (Blade legacy fallback)', function () {
    $source = readQuotationsController();
    preg_match('/public function getQuotations\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("view('sale_pos.quotations')");
});

it('getDraftDatables filtra sub_status=quotation quando is_quotation=1', function () {
    $source = readQuotationsController();
    preg_match('/public function getDraftDatables\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("'transactions.sub_status', 'quotation'");
    expect($block)->toContain('is_quotation');
});
