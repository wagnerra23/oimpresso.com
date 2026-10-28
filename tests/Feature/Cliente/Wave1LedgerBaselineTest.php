<?php

declare(strict_types=1);

test('Cliente/Ledger baseline — controller has getLedger() + Inertia branch', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('public function getLedger(')
        ->toContain("Inertia::render('Cliente/Ledger'");
});

test('Cliente/Ledger — PDF action preserved (legacy flow não impactado)', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    // Desde o cutover de 2026-10-27 o ramo PDF devolve logo depois do mPDF; o resto é React.
    expect($contents)->toContain("\$mpdf->Output(\$output_file_name, 'I');");
});

test('Cliente/Ledger — config mwart has cliente_ledger flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    expect(file_get_contents($configPath))
        ->toContain("'cliente_ledger'")
        ->toContain('MWART_CLIENTE_LEDGER');
});
