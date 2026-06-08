<?php

declare(strict_types=1);

/**
 * Pest BASELINE — SellController@getDrafts ANTES da migração MWART (Wave 1 W1-A).
 *
 * F2 BACKEND BASELINE do ADR 0104. Cobre:
 *   1. Permission gate: draft.view_all OR draft.view_own
 *   2. business_id session scope (Tier 0 ADR 0093)
 *   3. forDropdown helpers (locations, customers, sales_representative)
 *   4. Branch view('sale_pos.draft') preservado fallback
 *
 * AJAX `getDraftDatables` (linha 2040) cobre filtro draft real — não duplicar.
 */

const CONTROLLER_PATH_DRAFTS = 'app/Http/Controllers/SellController.php';

function readDraftsController(): string
{
    return file_get_contents(base_path(CONTROLLER_PATH_DRAFTS));
}

it('Método getDrafts() está declarado e público', function () {
    $source = readDraftsController();
    expect($source)->toMatch('/public function getDrafts\(\)/');
});

it('getDrafts() bloqueia sem permissão draft.view_all NEM draft.view_own (403)', function () {
    $source = readDraftsController();
    preg_match('/public function getDrafts\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toMatch("/can\\('draft\\.view_all'\\)/");
    expect($block)->toMatch("/can\\('draft\\.view_own'\\)/");
    expect($block)->toContain('abort(403');
});

it('getDrafts() pega business_id da session (Tier 0)', function () {
    $source = readDraftsController();
    preg_match('/public function getDrafts\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("session()->get('user.business_id')");
});

it('getDrafts() carrega dropdowns (BusinessLocation, Contact customers, User sales_representative)', function () {
    $source = readDraftsController();
    preg_match('/public function getDrafts\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain('BusinessLocation::forDropdown');
    expect($block)->toContain('Contact::customersDropdown');
    expect($block)->toContain('User::forDropdown');
});

it('getDrafts() preserva view sale_pos.draft (Blade legacy fallback)', function () {
    $source = readDraftsController();
    preg_match('/public function getDrafts\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("view('sale_pos.draft')");
});

it('getDraftDatables (AJAX irmão) filtra status=draft no DB (Tier 0 scope)', function () {
    $source = readDraftsController();
    preg_match('/public function getDraftDatables\(\).*?(?=public function )/s', $source, $matches);
    $block = $matches[0] ?? '';
    expect($block)->toContain("'transactions.business_id', \$business_id");
    expect($block)->toContain("'transactions.status', 'draft'");
});
