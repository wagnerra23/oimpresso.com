<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia path purchase/edit (MWART Wave2 B5).
 */

const EDIT_INERTIA_PATH = 'resources/js/Pages/Purchase/Edit.tsx';
const EDIT_CHARTER_PATH = 'resources/js/Pages/Purchase/Edit.charter.md';
const EDIT_CONTROLLER_PATH = 'app/Http/Controllers/PurchaseController.php';
const EDIT_RUNBOOK_PATH = 'memory/requisitos/Inventory/RUNBOOK-purchase-edit.md';
const EDIT_VISUAL_PATH = 'memory/requisitos/Inventory/purchase-edit-visual-comparison.md';

function readEditInertia(): string
{
    return file_get_contents(base_path(EDIT_INERTIA_PATH));
}

function readEditControllerInertia(): string
{
    return file_get_contents(base_path(EDIT_CONTROLLER_PATH));
}

it('Page Edit.tsx existe', function () {
    expect(file_exists(base_path(EDIT_INERTIA_PATH)))->toBeTrue();
});

it('Charter Edit.charter.md existe (ADR 0149)', function () {
    expect(file_exists(base_path(EDIT_CHARTER_PATH)))->toBeTrue();
    $content = file_get_contents(base_path(EDIT_CHARTER_PATH));
    expect($content)->toContain('mwart_pattern_reuse:');
});

it('RUNBOOK + visual-comparison existem', function () {
    expect(file_exists(base_path(EDIT_RUNBOOK_PATH)))->toBeTrue();
    expect(file_exists(base_path(EDIT_VISUAL_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 + PageHeader', function () {
    $source = readEditInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/PurchaseEdit\\.layout\\s*=/');
});

it('Page declara interface PurchaseEditPageProps + PurchaseEditPayload', function () {
    $source = readEditInertia();
    expect($source)->toContain('interface PurchaseEditPageProps');
    expect($source)->toContain('interface PurchaseEditPayload');
});

it('Page pré-popula useForm com purchase prop', function () {
    $source = readEditInertia();
    expect($source)->toContain('purchase.ref_no');
    expect($source)->toContain('purchase.purchase_lines');
    expect($source)->toContain("_method: 'PUT'");
});

it('Page submete POST /purchases/{id} (method spoofing PUT)', function () {
    $source = readEditInertia();
    expect($source)->toContain('form.post(`/purchases/${purchase.id}`');
});

it('Controller edit() tem dual path (Inertia atrás de ?v=2 OU header)', function () {
    $source = readEditControllerInertia();
    expect($source)->toContain('private function editInertia');
    expect($source)->toContain("Inertia::render('Purchase/Edit'");
});

it('Controller editInertia PRESERVA business_id Tier 0 + tipa int', function () {
    $source = readEditControllerInertia();
    expect($source)->toMatch('/editInertia\\(\\s*int \\$business_id/');
});

it('Controller editInertia serializa purchase_lines com tipos seguros (sem any leak)', function () {
    $source = readEditControllerInertia();
    expect($source)->toContain("'purchase_lines' => \$purchaseLines");
});

it('Page NÃO tem business_id hardcoded', function () {
    $source = readEditInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});
