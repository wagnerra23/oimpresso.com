<?php

declare(strict_types=1);

// W1-B3 F4 QA — structural verification do Cliente/Index.tsx.

test('Cliente/Index.tsx exists e usa AppShellV2 + PageHeader pattern', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain("from '@/Layouts/AppShellV2'")
        ->toContain('Clientes')               // h1 PT-BR
        ->toContain('export default function ClienteIndex')
        ->not->toContain(': any')             // TS estrito
        ->not->toContain('sessionStorage');   // canon = localStorage
});

test('Cliente/Index.tsx — Inertia::defer wrapping em props caras', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($tsxPath);

    // Frontend wrap em <Deferred data="...">
    expect($contents)
        ->toContain('<Deferred')
        ->toContain('data="kpis"')
        ->toContain('data="customers"');
});

test('Cliente/Index.tsx — localStorage prefix oimpresso.cliente.* canon', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('oimpresso.cliente.lastStatus')
        ->not->toContain('window.sessionStorage');
});

test('Cliente/Index.tsx — charter file exists com ADR 0149 YAML', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.charter.md';
    expect($charterPath)->toBeReadableFile();

    $contents = file_get_contents($charterPath);
    expect($contents)
        ->toContain('mwart_pattern_reuse:')
        ->toContain('blueprint_cowork:')
        ->toContain('derived_screens:')
        ->toContain('Index');
});
