<?php

declare(strict_types=1);

test('Cliente/Edit baseline — controller has edit($id) + Inertia branch', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('public function edit($id)')
        ->toContain("Inertia::render('Cliente/Edit'")
        ->toContain("shouldRenderInertiaCliente('cliente_edit'");
});

test('Cliente/Edit — config mwart has cliente_edit flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    expect(file_get_contents($configPath))
        ->toContain("'cliente_edit'")
        ->toContain('MWART_CLIENTE_EDIT');
});

test('Cliente/Edit — multi-tenant scope no branch Inertia', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect(file_get_contents($controllerPath))
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)");
});
