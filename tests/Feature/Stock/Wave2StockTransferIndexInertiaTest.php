<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia stock_transfers/index (MWART Wave2 B5).
 */

const ST_IDX_INERTIA_PATH = 'resources/js/Pages/StockTransfer/Index.tsx';
const ST_IDX_CHARTER_PATH = 'resources/js/Pages/StockTransfer/Index.charter.md';
const ST_IDX_CONTROLLER_PATH = 'app/Http/Controllers/StockTransferController.php';
const ST_IDX_RUNBOOK_PATH = 'memory/requisitos/Inventory/RUNBOOK-stock-transfer-index.md';
const ST_IDX_VISUAL_PATH = 'memory/requisitos/Inventory/stock-transfer-index-visual-comparison.md';

function readStockTransferIndexInertia(): string
{
    return file_get_contents(base_path(ST_IDX_INERTIA_PATH));
}

function readStockTransferControllerInertia(): string
{
    return file_get_contents(base_path(ST_IDX_CONTROLLER_PATH));
}

it('Page Index.tsx existe', function () {
    expect(file_exists(base_path(ST_IDX_INERTIA_PATH)))->toBeTrue();
});

it('Charter Index.charter.md existe (ADR 0149)', function () {
    expect(file_exists(base_path(ST_IDX_CHARTER_PATH)))->toBeTrue();
    $content = file_get_contents(base_path(ST_IDX_CHARTER_PATH));
    expect($content)->toContain('mwart_pattern_reuse:');
});

it('RUNBOOK + visual existem', function () {
    expect(file_exists(base_path(ST_IDX_RUNBOOK_PATH)))->toBeTrue();
    expect(file_exists(base_path(ST_IDX_VISUAL_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 + PageHeader (canon V2)', function () {
    $source = readStockTransferIndexInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/StockTransferIndex\\.layout\\s*=/');
});

it('Page declara TransferRow + Filters + Permissions interfaces', function () {
    $source = readStockTransferIndexInertia();
    expect($source)->toContain('interface TransferRow');
    expect($source)->toContain('interface Filters');
    expect($source)->toContain('interface Permissions');
});

it('Page tem coluna origem→destino única (UX clean)', function () {
    $source = readStockTransferIndexInertia();
    expect($source)->toContain('location_from');
    expect($source)->toContain('location_to');
    expect($source)->toContain('ArrowRight');
});

it('Page respeita permission view_purchase_price (esconde valor)', function () {
    $source = readStockTransferIndexInertia();
    expect($source)->toContain('permissions.view_purchase_price');
});

it('Controller index() tem dual path Inertia (?v=2)', function () {
    $source = readStockTransferControllerInertia();
    expect($source)->toContain("Inertia::render('StockTransfer/Index'");
    expect($source)->toContain('private function indexInertia');
});

it('Controller indexInertia PRESERVA Tier 0 business_id + type sell_transfer', function () {
    $source = readStockTransferControllerInertia();
    expect($source)->toContain("'transactions.business_id', \$business_id");
    expect($source)->toContain("'transactions.type', 'sell_transfer'");
});

it('Controller indexInertia PRESERVA permitted_locations filter (R-XFER-001)', function () {
    $source = readStockTransferControllerInertia();
    expect($source)->toContain("permitted_locations()");
});

it('Page NÃO tem business_id hardcoded', function () {
    $source = readStockTransferIndexInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});

it('Controller NÃO usa withoutGlobalScopes sem comentário SUPERADMIN', function () {
    $source = readStockTransferControllerInertia();
    if (str_contains($source, 'withoutGlobalScopes')) {
        expect($source)->toMatch('/SUPERADMIN/i');
    } else {
        expect(true)->toBeTrue();
    }
});
