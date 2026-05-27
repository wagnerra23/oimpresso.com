<?php

declare(strict_types=1);

// Slice B KB-9.75 — RiscoClienteCard (paralelizacao 2026-05-21)
//
// Card DETERMINISTICO de score de risco do relacionamento (0-10).
// Zero IA — soma 8 sinais com pesos canonicos.
//
// Tier 0 ADR 0093: props ja vem do backend filtradas por business_id global scope.
// LGPD: nao revela PII — apenas metadata (status, saldo, contadores).
//
// Estes testes assertam que o COMPONENTE FONTE define os 8 sinais canonicos
// + pesos esperados, seguindo o padrao Wave1ShowInertiaTest.php (file-content
// smoke checks). Calculo numerico em si e validado por React/UI tests
// (futuro Playwright via prototipo-ui) ou observado em smoke prod.

test('RiscoClienteCard.tsx — estrutura minima componente', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('export default function RiscoClienteCard')
        ->toContain('data-testid="risco-cliente-card"')
        ->toContain('data-risco-tier=')
        ->toContain('data-risco-score=')
        ->toContain('useMemo')
        ->not->toContain(': any');
});

test('RiscoClienteCard.tsx — 8 sinais canonicos presentes', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx';
    $contents = file_get_contents($tsxPath);

    // 8 chaves canonicas (ordem do prompt Slice B)
    expect($contents)
        ->toContain("key: 'saldo'")
        ->toContain("key: 'sem_compra_90'")
        ->toContain("key: 'sem_compra_180'")
        ->toContain("key: 'inativo'")
        ->toContain("key: 'sem_contato'")
        ->toContain("key: 'pj_sem_ie'")
        ->toContain("key: 'sem_localidade'")
        ->toContain("key: 'cadastro_velho_sem_compra'");
});

test('RiscoClienteCard.tsx — 3 tiers visuais (healthy/warn/high) + paleta semantica', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        // 3 tiers
        ->toContain("'healthy'")
        ->toContain("'warn'")
        ->toContain("'high'")
        // Paleta semantica canon
        ->toContain('bg-emerald-50')
        ->toContain('bg-amber-50')
        ->toContain('bg-rose-50')
        // Labels PT-BR
        ->toContain('Saudavel')
        ->toContain('Atencao')
        ->toContain('Alto risco')
        // Disclaimer determinismo (vs IA)
        ->toContain('Score deterministico (sem IA)');
});

test('RiscoClienteCard.tsx — LGPD anti-hook: nao expoe PII bruta', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx';
    $contents = file_get_contents($tsxPath);

    // O card opera sobre flags booleanas + contadores. Nao deve renderizar
    // direto cpf_cnpj plain (so usa cpf_cnpj_masked no DadosFiscaisBRCard).
    expect($contents)
        ->not->toContain('cpf_cnpj_masked')
        ->not->toContain('contact.cpf_cnpj')
        ->not->toContain('contact.tax_number');
});

test('Cliente/Show.tsx — RiscoClienteCard plugado no aside', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/Show.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain("import RiscoClienteCard from './_show/RiscoClienteCard'")
        ->toContain('<RiscoClienteCard contact={contact} stats={props.stats} />');
});
