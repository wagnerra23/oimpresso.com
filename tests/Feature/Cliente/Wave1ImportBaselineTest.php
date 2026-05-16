<?php

declare(strict_types=1);

test('Cliente/Import baseline — controller has getImportContacts() + Inertia branch', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('public function getImportContacts(')
        ->toContain("Inertia::render('Cliente/Import'")
        ->toContain("shouldRenderInertiaCliente('cliente_import'");
});

test('Cliente/Import — config mwart has cliente_import flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    expect(file_get_contents($configPath))
        ->toContain("'cliente_import'")
        ->toContain('MWART_CLIENTE_IMPORT');
});
