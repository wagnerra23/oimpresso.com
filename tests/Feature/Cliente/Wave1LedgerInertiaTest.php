<?php

declare(strict_types=1);

test('Cliente/Ledger.tsx — tabela débito/crédito + filters + AppShellV2', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Ledger.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('AppShellV2')
        ->toContain('export default function ClienteLedger')
        ->toContain('Débito')
        ->toContain('Crédito')
        ->toContain('Saldo')
        ->toContain('text-rose-700')         // débito canon semântico
        ->toContain('text-emerald-700')      // crédito canon semântico
        ->not->toContain(': any');
});

test('Cliente/Ledger.charter.md — divergence ADR 0149 declared', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Ledger.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('divergence_from_blueprint:')
        ->toContain('tabela financeira densa');
});
