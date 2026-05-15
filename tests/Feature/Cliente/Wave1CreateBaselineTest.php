<?php

declare(strict_types=1);

test('Cliente/Create baseline — controller has create() + Inertia branch + mwart flag', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('public function create(')
        ->toContain("Inertia::render('Cliente/Create'")
        ->toContain("shouldRenderInertiaCliente('cliente_create'");
});

test('Cliente/Create — config mwart has cliente_create flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    $contents = file_get_contents($configPath);

    expect($contents)
        ->toContain("'cliente_create'")
        ->toContain("MWART_CLIENTE_CREATE");
});
