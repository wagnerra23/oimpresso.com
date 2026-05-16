<?php

declare(strict_types=1);

/**
 * F2 BACKEND BASELINE — purchase/edit (MWART Wave2 B5).
 * Garante zero regressão Blade após dual path Inertia.
 */

const EDIT_BLADE_LEGACY = 'resources/views/purchase/edit.blade.php';
const EDIT_CONTROLLER = 'app/Http/Controllers/PurchaseController.php';

function readEditController(): string
{
    return file_get_contents(base_path(EDIT_CONTROLLER));
}

it('Blade legacy edit.blade.php existe', function () {
    expect(file_exists(base_path(EDIT_BLADE_LEGACY)))->toBeTrue();
});

it('Controller edit() PRESERVA return view("purchase.edit") (fallback legacy)', function () {
    $source = readEditController();
    expect($source)->toContain("return view('purchase.edit')");
});

it('Controller edit() PRESERVA permission purchase.update + canBeEdited time-gate', function () {
    $source = readEditController();
    expect($source)->toContain("auth()->user()->can('purchase.update')");
    expect($source)->toContain("canBeEdited(\$id, \$edit_days)");
});

it('Controller edit() PRESERVA isReturnExist bloqueio', function () {
    $source = readEditController();
    expect($source)->toContain("isReturnExist(\$id)");
});

it('Controller edit() PRESERVA Transaction::where(business_id) Tier 0', function () {
    $source = readEditController();
    expect($source)->toMatch('/Transaction::where\\(.business_id., \\$business_id\\)/');
});
