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
        // Wave 2026-05-21 paridade 5 tabs (US-CRM-063..067): transactions deferred substituído
        // por sales deferred (SalesTab é a tab Vendas — paginação via Inertia partial reload only:['sales']).
        ->toContain('data="sales"')
        ->not->toContain(': any');
});

test('Cliente/Show.tsx — 4 tabs canon (Extrato/Vendas/Pagamentos/Documentos)', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Show.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain("label: 'Extrato'")
        ->toContain("label: 'Vendas'")
        ->toContain("label: 'Pagamentos'")
        ->toContain("label: 'Documentos & Notas'")
        ->toContain('LedgerTab')
        ->toContain('SalesTab')
        ->toContain('PaymentsTab')
        ->toContain('DocumentsTab')
        ->toContain('ActionsMenu');
});

test('Cliente/Show.charter.md — ADR 0149 YAML', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Show.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('mwart_pattern_reuse:')
        ->toContain('Show');
});
