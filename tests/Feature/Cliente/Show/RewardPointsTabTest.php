<?php

declare(strict_types=1);

// Onda Final.E — Tab Reward Points (pontos fidelidade)
// Teste estrutural: componente + integração + scope business_id + condicional enable_rp.

test('RewardPointsTab.tsx — estrutura mínima componente', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/RewardPointsTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('export default function RewardPointsTab')
        ->toContain('data-testid="rewards-tab-root"')
        ->toContain('data-testid="rewards-tab-empty"')
        ->toContain('data-testid="rewards-tab-disabled"')
        ->toContain('data-testid="rewards-tab-skeleton"')
        ->not->toContain(': any');
});

test('RewardPointsTab.tsx — 4 summary cards + tabela 7 colunas', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/RewardPointsTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('acumulados')
        ->toContain('Resgatados')
        ->toContain('Expirados')
        ->toContain('Saldo disponível')
        ->toContain('>Data<')
        ->toContain('>Fatura<')
        ->toContain('>Total<')
        ->toContain('>Ganhos<')
        ->toContain('>Resgates<')
        ->toContain('>Desconto<')
        ->toContain('>Ação<');
});

test('Cliente/Show.tsx — integra RewardPointsTab como 8ª tab', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/Show.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("import RewardPointsTab")
        ->toContain("'rewards'")
        ->toContain("label: 'Pontos'")
        ->toContain('<RewardPointsTab')
        ->toContain('data="reward_points"');
});

test('ContactController — Show injeta reward_points defer condicional enable_rp', function () {
    $path = __DIR__ . '/../../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'reward_points' => Inertia::defer")
        ->toContain("business.enable_rp")
        ->toContain("total_rp")
        ->toContain("rp_earned")
        ->toContain("rp_redeemed");
});

test('LedgerTab.tsx — Onda Final.F usa router.visit (SPA navigation, sem window.location)', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('router.visit')
        ->toContain('@inertiajs/react');
});
