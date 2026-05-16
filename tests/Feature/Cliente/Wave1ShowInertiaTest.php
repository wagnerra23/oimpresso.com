<?php

declare(strict_types=1);

test('Cliente/Show.tsx — structure + AppShellV2 + Deferred', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Show.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain('export default function ClienteShow')
        ->toContain('<Deferred')
        ->toContain('data="stats"')
        ->toContain('data="transactions"')
        ->not->toContain(': any');
});

test('Cliente/Show.charter.md — ADR 0149 YAML', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Show.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('mwart_pattern_reuse:')
        ->toContain('Show');
});
