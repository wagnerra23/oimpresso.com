<?php

declare(strict_types=1);

test('Cliente/Map.tsx — split-pane + AppShellV2 + mapa embutido OpenStreetMap', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Map.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain('export default function ClienteMap')
        ->toContain('Mapa de clientes')
        // O embed saiu do Google para o OpenStreetMap em 2026-08-26 (#6303, Onda 3 da paridade).
        // O charter proíbe mandar lat,lng ao Google (custo, opt-in) — a 2ª linha guarda isso.
        ->toContain('openstreetmap.org/export/embed.html')
        ->not->toContain('maps.google.com')
        ->not->toContain(': any');
});

test('Cliente/Map.charter.md — divergence ADR 0149 declared', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Map.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('divergence_from_blueprint:')
        ->toContain('split-screen');
});
