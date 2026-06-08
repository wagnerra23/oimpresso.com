<?php

declare(strict_types=1);

/**
 * Fix 2026-06-08 — abrir a venda via Inertia em TODAS as tabelas que linkam /sells/{id}.
 *
 * SellController@show só renderiza a tela moderna Sells/Show quando há header
 * X-Inertia; `<a href>`/window.location fazem navegação full-page (sem o header)
 * → caem no partial Blade legado cru ("ficou uma merda"). Por isso todo link
 * in-app pra /sells/{id} usa <Link>/router.visit (Inertia), que envia X-Inertia.
 *
 * Companheiro do guard em Show/SalesTabTest.php (que cobre a aba Vendas).
 * Este cobre as demais abas/drawers: Pagamentos, Pontos, Assinaturas, Nota Fiscal.
 *
 * GUARDs estruturais (file_get_contents). Ref: PR SalesTab Inertia + #2451.
 */

dataset('telas_que_linkam_venda', [
    'PaymentsTab (Pagamentos)'   => ['resources/js/Pages/Cliente/_show/PaymentsTab.tsx'],
    'RewardPointsTab (Pontos)'   => ['resources/js/Pages/Cliente/_show/RewardPointsTab.tsx'],
    'SubscriptionsTab (Assina.)' => ['resources/js/Pages/Cliente/_show/SubscriptionsTab.tsx'],
    'NotaDrawer (Fiscal)'        => ['resources/js/Pages/Fiscal/_components/NotaDrawer.tsx'],
]);

test('abre /sells/{id} via Inertia <Link> (não <a href> cru → partial feio)', function (string $rel) {
    $path = __DIR__ . '/../../../' . $rel;
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);

    expect($contents)
        // Importa Link do Inertia.
        ->toContain("from '@inertiajs/react'")
        ->toMatch('/import \{[^}]*\bLink\b[^}]*\} from \'@inertiajs\/react\'/')
        // Usa <Link ... href={`/sells/...`}> em vez de <a ... href={`/sells/...`}>.
        ->toContain('/sells/$')
        // NÃO pode ter <a href> apontando pra /sells/ (regressão pro partial cru).
        ->not->toMatch('/<a\b[^>]*href=\{`\/sells\/\$/');
})->with('telas_que_linkam_venda');
