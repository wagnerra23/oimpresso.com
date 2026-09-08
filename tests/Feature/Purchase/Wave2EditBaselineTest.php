<?php

declare(strict_types=1);

/**
 * F2 BACKEND BASELINE — purchase/edit (MWART Wave2 B5).
 * Garante zero regressão Blade após dual path Inertia.
 *
 * ── Rastreabilidade (casos-gate G-2 · ADR 0264) ────────────────────────────
 * Contrato: resources/js/Pages/Purchase/Edit.casos.md
 *   @covers-uc UC-PUREDT-01  o path Blade legacy do edit continua de pe
 *   @covers-uc UC-PUREDT-03  gate temporal canBeEdited (transaction_edit_days)
 *   @covers-uc UC-PUREDT-04  bloqueio isReturnExist (compra com devolucao nao edita)
 *   @covers-uc UC-PUREDT-05  permission purchase.update obrigatoria no edit()
 *
 * ⚠️ NATUREZA DA COBERTURA — ESTRUTURAL: 5 asserts de casamento de texto no fonte,
 * ZERO requests HTTP. Provam que a CHAMADA ao gate existe; nao montam compra fora da
 * janela nem com devolucao, e nao observam a recusa. Classe LC-11 (presence-gate).
 * Nota: o update() nao tem assert proprio de permissao — so o edit(). Ver [BACKLOG].
 */

const EDIT_BLADE_LEGACY = 'resources/views/purchase/edit.blade.php';
const EDIT_CONTROLLER = 'app/Http/Controllers/PurchaseController.php';

function readPurchaseEditController(): string
{
    return file_get_contents(base_path(EDIT_CONTROLLER));
}

it('Blade legacy edit.blade.php existe', function () {
    expect(file_exists(base_path(EDIT_BLADE_LEGACY)))->toBeTrue();
});

it('Controller edit() PRESERVA return view("purchase.edit") (fallback legacy)', function () {
    $source = readPurchaseEditController();
    expect($source)->toContain("return view('purchase.edit')");
});

it('Controller edit() PRESERVA permission purchase.update + canBeEdited time-gate', function () {
    $source = readPurchaseEditController();
    expect($source)->toContain("auth()->user()->can('purchase.update')");
    expect($source)->toContain("canBeEdited(\$id, \$edit_days)");
});

it('Controller edit() PRESERVA isReturnExist bloqueio', function () {
    $source = readPurchaseEditController();
    expect($source)->toContain("isReturnExist(\$id)");
});

it('Controller edit() PRESERVA Transaction::where(business_id) Tier 0', function () {
    $source = readPurchaseEditController();
    expect($source)->toMatch('/Transaction::where\\(.business_id., \\$business_id\\)/');
});
