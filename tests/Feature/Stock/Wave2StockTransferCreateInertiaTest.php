<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia stock_transfers/create (MWART Wave2 B5).
 */

const ST_CR_INERTIA_PATH = 'resources/js/Pages/StockTransfer/Create.tsx';
const ST_CR_CHARTER_PATH = 'resources/js/Pages/StockTransfer/Create.charter.md';
const ST_CR_CONTROLLER_PATH = 'app/Http/Controllers/StockTransferController.php';
const ST_CR_RUNBOOK_PATH = 'memory/requisitos/Inventory/RUNBOOK-stock-transfer-create.md';
const ST_CR_VISUAL_PATH = 'memory/requisitos/Inventory/stock-transfer-create-visual-comparison.md';

function readSTCreateInertia(): string
{
    return file_get_contents(base_path(ST_CR_INERTIA_PATH));
}

function readSTCreateControllerInertia(): string
{
    return file_get_contents(base_path(ST_CR_CONTROLLER_PATH));
}

it('Page Create.tsx existe', function () {
    expect(file_exists(base_path(ST_CR_INERTIA_PATH)))->toBeTrue();
});

it('Charter + Runbook + Visual existem (ADR 0149 + 0114)', function () {
    expect(file_exists(base_path(ST_CR_CHARTER_PATH)))->toBeTrue();
    expect(file_exists(base_path(ST_CR_RUNBOOK_PATH)))->toBeTrue();
    expect(file_exists(base_path(ST_CR_VISUAL_PATH)))->toBeTrue();

    $charter = file_get_contents(base_path(ST_CR_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('Page importa AppShellV2 + PageHeader (canon V2)', function () {
    $source = readSTCreateInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/StockTransferCreate\\.layout\\s*=/');
});

it('Page declara TransferLineDraft + Permissions interfaces', function () {
    $source = readSTCreateInertia();
    expect($source)->toContain('interface TransferLineDraft');
    expect($source)->toContain('interface Permissions');
    expect($source)->toContain('interface StockTransferCreatePageProps');
});

it('Page valida R-XFER-004 (origem ≠ destino) client-side', function () {
    $source = readSTCreateInertia();
    expect($source)->toContain('origemDestinoIguais');
    expect($source)->toContain('AlertCircle');
    expect($source)->toContain('disabled={form.processing || origemDestinoIguais}');
});

it('Page filtra destino removendo origem selecionada (UX prevent)', function () {
    $source = readSTCreateInertia();
    expect($source)->toContain(".filter(([id]) => id !== form.data.location_id)");
});

it('Page respeita view_purchase_price (esconde colunas custo)', function () {
    $source = readSTCreateInertia();
    expect($source)->toContain('permissions.view_purchase_price');
});

it('Page submete POST /stock-transfers', function () {
    $source = readSTCreateInertia();
    expect($source)->toContain("form.post('/stock-transfers'");
});

it('Controller create() tem dual path Inertia (?v=2)', function () {
    $source = readSTCreateControllerInertia();
    expect($source)->toContain("Inertia::render('StockTransfer/Create'");
    expect($source)->toContain('private function createInertia');
});

it('Controller createInertia PRESERVA business_id Tier 0', function () {
    $source = readSTCreateControllerInertia();
    expect($source)->toMatch('/createInertia\\(int \\$business_id/');
});

it('Page NÃO tem business_id hardcoded', function () {
    $source = readSTCreateInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});
