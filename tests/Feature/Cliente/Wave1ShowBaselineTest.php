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

test('Cliente/Show — defer em stats e transactions', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain("'stats' => Inertia::defer")
        ->toContain("'transactions' => Inertia::defer");
});

test('Cliente/Show — config mwart has cliente_show flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    $contents = file_get_contents($configPath);

    expect($contents)->toContain("'cliente_show'")
        ->toContain('MWART_CLIENTE_SHOW');
});
