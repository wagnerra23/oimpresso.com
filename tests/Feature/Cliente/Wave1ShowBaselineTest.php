<?php

declare(strict_types=1);

test('Cliente/Show baseline — controller has show($id) + Inertia branch', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('public function show($id)')
        ->toContain("Inertia::render('Cliente/Show'")
        ->toContain("shouldRenderInertiaCliente('cliente_show'");
});

test('Cliente/Show — defer em stats + transactions + sales', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    // Wave 2026-05-21 paridade 5 tabs adicionou `sales` defer (US-CRM-065 SalesTab paginação).
    // transactions defer mantido por backward compat de partial reload externo (deprecado, sem UI hoje).
    expect($contents)
        ->toContain("'stats' => Inertia::defer")
        ->toContain("'transactions' => Inertia::defer")
        ->toContain("'sales' => Inertia::defer");
});

test('Cliente/Show — buildClienteSalesPaginator helper + business_id scope', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('private function buildClienteSalesPaginator')
        ->toContain('transactions.business_id')
        ->toContain("'sell'");
});

test('Cliente/Show — config mwart has cliente_show flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    $contents = file_get_contents($configPath);

    expect($contents)->toContain("'cliente_show'")
        ->toContain('MWART_CLIENTE_SHOW');
});
