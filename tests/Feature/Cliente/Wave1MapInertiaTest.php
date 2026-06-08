<?php

declare(strict_types=1);

test('Cliente/Map.tsx — split-pane + AppShellV2 + Google Maps embed', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Map.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain('export default function ClienteMap')
        ->toContain('Mapa de clientes')
        ->toContain('maps.google.com/maps')
        ->not->toContain(': any');
});

test('Cliente/Map.charter.md — divergence ADR 0149 declared', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Map.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('divergence_from_blueprint:')
        ->toContain('split-screen');
});
