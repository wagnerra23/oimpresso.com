<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia stock_adjustment/index (MWART Wave2 B5).
 */

const SA_IDX_INERTIA_PATH = 'resources/js/Pages/StockAdjustment/Index.tsx';
const SA_IDX_CHARTER_PATH = 'resources/js/Pages/StockAdjustment/Index.charter.md';
const SA_IDX_CONTROLLER_PATH = 'app/Http/Controllers/StockAdjustmentController.php';
const SA_IDX_RUNBOOK_PATH = 'memory/requisitos/Inventory/RUNBOOK-stock-adjustment-index.md';
const SA_IDX_VISUAL_PATH = 'memory/requisitos/Inventory/stock-adjustment-index-visual-comparison.md';

function readSAIndexInertia(): string
{
    return file_get_contents(base_path(SA_IDX_INERTIA_PATH));
}

function readSAControllerInertia(): string
{
    return file_get_contents(base_path(SA_IDX_CONTROLLER_PATH));
}

it('Page Index.tsx existe', function () {
    expect(file_exists(base_path(SA_IDX_INERTIA_PATH)))->toBeTrue();
});

it('Charter + Runbook + Visual existem (ADR 0149 + 0114)', function () {
    expect(file_exists(base_path(SA_IDX_CHARTER_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_IDX_RUNBOOK_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_IDX_VISUAL_PATH)))->toBeTrue();
    $charter = file_get_contents(base_path(SA_IDX_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('Page importa AppShellV2 + PageHeader', function () {
    $source = readSAIndexInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/StockAdjustmentIndex\\.layout\\s*=/');
});

it('Page declara AdjustmentRow + AdjustmentType + Permissions', function () {
    $source = readSAIndexInertia();
    expect($source)->toContain('interface AdjustmentRow');
    expect($source)->toContain("type AdjustmentType");
    expect($source)->toContain('interface Permissions');
});

it('Page tem type pill com 2 estados (normal/abnormal)', function () {
    $source = readSAIndexInertia();
    expect($source)->toContain("normal:");
    expect($source)->toContain("abnormal:");
});

it('Page respeita view_purchase_price (esconde valores)', function () {
    $source = readSAIndexInertia();
    expect($source)->toContain('permissions.view_purchase_price');
});

it('Controller index() tem dual path Inertia (?v=2)', function () {
    $source = readSAControllerInertia();
    expect($source)->toContain("Inertia::render('StockAdjustment/Index'");
    expect($source)->toContain('private function indexInertia');
});

it('Controller indexInertia PRESERVA business_id Tier 0 + permitted_locations', function () {
    $source = readSAControllerInertia();
    expect($source)->toContain("'transactions.business_id', \$business_id");
    expect($source)->toContain('permitted_locations');
});

it('Controller indexInertia PRESERVA view_own_purchase ownership scope', function () {
    $source = readSAControllerInertia();
    expect($source)->toContain('view_own_purchase');
});

it('Page NÃO tem business_id hardcoded', function () {
    $source = readSAIndexInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});

it('Controller NÃO usa withoutGlobalScopes sem comentário SUPERADMIN', function () {
    $source = readSAControllerInertia();
    if (str_contains($source, 'withoutGlobalScopes')) {
        expect($source)->toMatch('/SUPERADMIN/i');
    } else {
        expect(true)->toBeTrue();
    }
});
