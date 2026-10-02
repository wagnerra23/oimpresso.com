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
        // Débito vermelho e crédito verde, em token do DS desde o #3387 (2026-06-29, era
        // text-rose-700/text-emerald-700). Ancorado na célula (`tabular-nums`) porque
        // text-destructive solto também aparece no KpiCard e não provaria a coluna.
        ->toContain('tabular-nums text-destructive')  // débito
        ->toContain('tabular-nums text-success')      // crédito
        ->not->toContain(': any');
});

test('Cliente/Ledger.charter.md — divergence ADR 0149 declared', function () {
    $charterPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Ledger.charter.md';
    expect($charterPath)->toBeReadableFile();
    expect(file_get_contents($charterPath))
        ->toContain('divergence_from_blueprint:')
        ->toContain('tabela financeira densa');
});
