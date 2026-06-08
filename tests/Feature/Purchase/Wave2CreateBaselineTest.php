<?php

declare(strict_types=1);

/**
 * F2 BACKEND BASELINE — purchase/create (MWART Wave2 B5).
 *
 * Garante que a Blade legacy NÃO foi quebrada pela introdução do dual path Inertia.
 * Cobertura ADR 0104 §F2 (regressão zero).
 *
 * Status: estrutural (file-existence + assinatura do método).
 */

const CREATE_BLADE_LEGACY = 'resources/views/purchase/create.blade.php';
const CREATE_CONTROLLER = 'app/Http/Controllers/PurchaseController.php';

function readCreateController(): string
{
    return file_get_contents(base_path(CREATE_CONTROLLER));
}

// ─── F2 BASELINE: legacy preserved ───────────────────────────────────────────

it('Blade legacy create.blade.php existe e continua referência canônica', function () {
    expect(file_exists(base_path(CREATE_BLADE_LEGACY)))->toBeTrue();
    $blade = file_get_contents(base_path(CREATE_BLADE_LEGACY));
    expect($blade)->toContain("Form::open([");
    expect($blade)->toContain("PurchaseController::class");
});

it('Controller create() PRESERVA return view("purchase.create") (fallback legacy)', function () {
    $source = readCreateController();
    expect($source)->toContain("return view('purchase.create')");
});

it('Controller create() PRESERVA permission check purchase.create (Tier 0)', function () {
    $source = readCreateController();
    expect($source)->toContain("auth()->user()->can('purchase.create')");
});

it('Controller create() PRESERVA business_id global scope da sessão', function () {
    $source = readCreateController();
    expect($source)->toContain("session()->get('user.business_id')");
});

it('Controller create() PRESERVA BusinessLocation::forDropdown filtrado por business_id', function () {
    $source = readCreateController();
    expect($source)->toContain("BusinessLocation::forDropdown(\$business_id");
});

it('Controller create() PRESERVA isSubscribed gate', function () {
    $source = readCreateController();
    expect($source)->toContain("isSubscribed(\$business_id)");
});
