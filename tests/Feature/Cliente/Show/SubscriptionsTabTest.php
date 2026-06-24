<?php

declare(strict_types=1);
// @covers-us US-CRM-069

// Onda Final.D — Tab Assinaturas (subscriptions / transactions is_recurring=1)
// Teste estrutural: componente + integração + scope business_id + contact_id.

test('SubscriptionsTab.tsx — estrutura mínima componente', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SubscriptionsTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('export default function SubscriptionsTab')
        ->toContain('data-testid="subscriptions-tab-root"')
        ->toContain('data-testid="subscriptions-tab-empty"')
        ->toContain('data-testid="subscriptions-tab-skeleton"')
        ->not->toContain(': any');
});

test('SubscriptionsTab.tsx — 8 colunas + status Ativa/Pausada + intervalo PT-BR', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SubscriptionsTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('>Data<')
        ->toContain('>Nº Assinatura<')
        ->toContain('>Local<')
        ->toContain('>Intervalo<')
        ->toContain('>Repetições<')
        ->toContain('>Geradas<')
        ->toContain('>Status<')
        ->toContain('>Ação<')
        ->toContain('Ativa')
        ->toContain('Pausada')
        ->toContain("a cada");
});

test('Cliente/Show.tsx — integra SubscriptionsTab como 7ª tab', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/Show.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("import SubscriptionsTab")
        ->toContain("'subscriptions'")
        ->toContain("label: 'Assinaturas'")
        ->toContain('<SubscriptionsTab')
        ->toContain('data="subscriptions"');
});

test('ContactController — Show injeta subscriptions defer scope business_id + is_recurring', function () {
    $path = __DIR__ . '/../../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'subscriptions' => Inertia::defer")
        ->toContain("transactions.is_recurring")
        ->toContain("transactions.contact_id")
        ->toContain("recur_parent_id")
        ->toContain("'subscription_no'");
});
