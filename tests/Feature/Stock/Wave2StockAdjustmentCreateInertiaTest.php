<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia stock_adjustment/create (MWART Wave2 B5).
 */

const SA_CR_INERTIA_PATH = 'resources/js/Pages/StockAdjustment/Create.tsx';
const SA_CR_CHARTER_PATH = 'resources/js/Pages/StockAdjustment/Create.charter.md';
const SA_CR_CONTROLLER_PATH = 'app/Http/Controllers/StockAdjustmentController.php';
const SA_CR_RUNBOOK_PATH = 'memory/requisitos/Inventory/RUNBOOK-stock-adjustment-create.md';
const SA_CR_VISUAL_PATH = 'memory/requisitos/Inventory/stock-adjustment-create-visual-comparison.md';

function readSACreateInertia(): string
{
    return file_get_contents(base_path(SA_CR_INERTIA_PATH));
}

function readSACreateControllerInertia(): string
{
    return file_get_contents(base_path(SA_CR_CONTROLLER_PATH));
}

it('Page Create.tsx existe', function () {
    expect(file_exists(base_path(SA_CR_INERTIA_PATH)))->toBeTrue();
});

it('Charter + Runbook + Visual existem (ADR 0149 + 0114)', function () {
    expect(file_exists(base_path(SA_CR_CHARTER_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_CR_RUNBOOK_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_CR_VISUAL_PATH)))->toBeTrue();
    $charter = file_get_contents(base_path(SA_CR_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('Page importa AppShellV2 + PageHeader', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/StockAdjustmentCreate\\.layout\\s*=/');
});

it('Page declara AdjustmentLineDraft + AdjustmentType + Permissions interfaces', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('interface AdjustmentLineDraft');
    expect($source)->toContain('type AdjustmentType');
    expect($source)->toContain('interface Permissions');
    expect($source)->toContain('interface StockAdjustmentCreatePageProps');
});

it('Page valida R-ADJ-003 (recovered <= total) client-side', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('recuperadoExcede');
    expect($source)->toContain('AlertCircle');
    expect($source)->toContain('disabled={form.processing || recuperadoExcede}');
});

it('Page tem 2 tipos adjustment_type (normal/abnormal) com cor diferenciada', function () {
    $source = readSACreateInertia();
    expect($source)->toContain("'normal'");
    expect($source)->toContain("'abnormal'");
    expect($source)->toContain("border-rose-300 bg-rose-50/20");
});

it('Page respeita view_purchase_price (esconde valor recuperado)', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('permissions.view_purchase_price');
});

it('Page submete POST /stock-adjustments', function () {
    $source = readSACreateInertia();
    expect($source)->toContain("form.post('/stock-adjustments'");
});

it('Controller create() tem dual path Inertia (?v=2)', function () {
    $source = readSACreateControllerInertia();
    expect($source)->toContain("Inertia::render('StockAdjustment/Create'");
    expect($source)->toContain('private function createInertia');
});

it('Controller createInertia PRESERVA business_id Tier 0', function () {
    $source = readSACreateControllerInertia();
    expect($source)->toMatch('/createInertia\\(int \\$business_id/');
});

it('Page NÃO tem business_id hardcoded', function () {
    $source = readSACreateInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});
