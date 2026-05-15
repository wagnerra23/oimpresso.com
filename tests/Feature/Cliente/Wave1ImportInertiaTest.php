<?php

declare(strict_types=1);

test('Cliente/Import.tsx — wizard upload + AppShellV2', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Import.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain('useForm')
        ->toContain('forceFormData')
        ->toContain('post(\'/contacts/import\'')
        ->toContain('Importar clientes')
        ->not->toContain(': any');
});

test('Cliente/Import.charter.md — divergence ADR 0149 declared', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Import.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('divergence_from_blueprint:')
        ->toContain('wizard upload');
});
