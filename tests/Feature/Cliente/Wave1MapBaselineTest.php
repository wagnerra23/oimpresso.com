<?php

declare(strict_types=1);

test('Cliente/Map baseline — controller has contactMap() + Inertia branch', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('public function contactMap(')
        ->toContain("Inertia::render('Cliente/Map'")
        ->toContain("shouldRenderInertiaCliente('cliente_map'");
});

test('Cliente/Map — multi-tenant scope', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect(file_get_contents($controllerPath))
        ->toContain("Contact::where('business_id', \$business_id)")
        ->toContain("->whereNotNull('position')");
});

test('Cliente/Map — config mwart has cliente_map flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    expect(file_get_contents($configPath))
        ->toContain("'cliente_map'")
        ->toContain('MWART_CLIENTE_MAP');
});
