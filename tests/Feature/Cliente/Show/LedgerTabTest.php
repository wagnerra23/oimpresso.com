<?php

declare(strict_types=1);

// Wave B — US-CRM-064 Tab Ledger inline
// Restrição Tier 0 ADR 0093: backend ContactController::getLedger() filtra business_id.
// Teste estrutural — Wave1LedgerInertiaTest cobre integração render.

test('LedgerTab.tsx — estrutura mínima componente', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('export default function LedgerTab')
        ->toContain('data-testid="ledger-tab-root"')
        ->toContain('data-testid="ledger-tab-skeleton"')
        ->not->toContain(': any');
});

test('LedgerTab.tsx — filtros: range datas + format 1/2/3 + location', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx';
    $contents = file_get_contents($tsxPath);

    // testids vivem em FORMAT_TESTIDS const (single-quoted) + lookup data-testid={...}
    expect($contents)
        ->toContain('Data inicial')
        ->toContain('Data final')
        ->toContain("'format_1'")
        ->toContain("'format_2'")
        ->toContain("'format_3'")
        ->toContain("'ledger-format-format_1'")
        ->toContain("'ledger-format-format_2'")
        ->toContain("'ledger-format-format_3'")
        ->toContain('Local')
        ->toContain('Todos');
});

test('LedgerTab.tsx — resumo período + resumo geral all-time', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('data-testid="ledger-summary-period"')
        ->toContain('data-testid="ledger-summary-all"')
        ->toContain('Resumo do período')
        ->toContain('Resumo geral (all-time)')
        ->toContain('opening_balance');
});

test('LedgerTab.tsx — export PDF + email modal', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('data-testid="ledger-pdf-btn"')
        ->toContain('data-testid="ledger-email-btn"')
        ->toContain('data-testid="ledger-email-modal"')
        ->toContain("'/contacts/send-ledger'")
        ->toContain('X-CSRF-TOKEN')
        ->toContain("action=pdf");
});

test('LedgerTab.tsx — empty state + dark mode tokens', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Nenhum lançamento no período selecionado.')
        ->toContain('dark:text-rose-400')
        ->toContain('dark:text-emerald-400')
        ->toContain('bg-background')
        ->toContain('text-muted-foreground');
});

test('LedgerTab.tsx — endpoint legacy preservado (não inventa rota nova)', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx';
    $contents = file_get_contents($tsxPath);

    // Rota canon /contacts/ledger já existe em routes/web.php linha 185
    expect($contents)->toContain('/contacts/ledger?contact_id=');
});
