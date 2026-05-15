<?php

declare(strict_types=1);

test('Cliente/Ledger baseline — controller has getLedger() + Inertia branch', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('public function getLedger(')
        ->toContain("Inertia::render('Cliente/Ledger'")
        ->toContain("shouldRenderInertiaCliente('cliente_ledger'");
});

test('Cliente/Ledger — PDF action preserved (legacy flow não impactado)', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    // Branch Inertia exige action !== 'pdf' — preserva fluxo mPDF legacy.
    expect($contents)->toContain("request()->input('action') !== 'pdf'");
});

test('Cliente/Ledger — config mwart has cliente_ledger flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    expect(file_get_contents($configPath))
        ->toContain("'cliente_ledger'")
        ->toContain('MWART_CLIENTE_LEDGER');
});
