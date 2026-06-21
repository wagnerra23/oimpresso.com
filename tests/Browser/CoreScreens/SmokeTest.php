<?php

declare(strict_types=1);

/**
 * Pest 4 Browser smoke — telas-núcleo montam no AppShellV2 sem erro de console.
 *
 * Origem: handoff Cowork 2026-06-02 (PROMPT_PARA_CODE_REFORCO-APPSHELL-TESTES §B).
 * Espelha o idioma de tests/Browser/Sells/CreateScreenshotTest.php (ADR 0108).
 *
 * Camadas de validação por tela:
 *   - Tier 1 estrutural: tests/Feature/Architecture/CoreScreensIntegrityTest.php (roda sempre)
 *   - Tier 2 browser (este arquivo): runtime real renderizado — OPT-IN (precisa chromium)
 *
 * Rodar (CI ou local com chromium):
 *   composer install && npm install && npx playwright install chromium
 *   ./vendor/bin/pest tests/Browser/CoreScreens/
 *
 * tests/Browser NÃO está no suite default (phpunit.xml) — só roda quando alvo
 * explícito, então não quebra o `pest` normal em ambiente sem chromium.
 *
 * Locators resilientes (texto/role), nunca classe CSS (L-24 / _PROPOSTA-0244).
 *
 * @see tests/Feature/Architecture/CoreScreensIntegrityTest.php
 */

use App\Business;
use App\User;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-06-02 12:00:00');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

$browserMissing = ! class_exists(\Pest\Browser\Bootstrap::class);

/**
 * Telas-núcleo: rota + um texto-âncora que prova que a tela (não um erro) montou.
 * Permissão mínima exigida pelo controller pra não cair em 403.
 *
 * Ampliado 2026-06-02 (worklist TRAVA-SEGUNDA Martinho) pro núcleo-6 de retenção
 * (Cliente · Produto/Preço · Venda · Fiscal NF-e/NFS-e · Financeiro · Oficina).
 * Âncoras = substrings reais do título/PageHeader de cada Page (não classe CSS).
 * Slug de permissão é best-effort (try/catch) — se 403 em CI, ajustar o slug.
 */
$screens = [
    // — núcleo-6 retenção —
    'Clientes'             => ['/cliente',                    'customer.view',               'Cliente'],       // CU-1
    'Produto'              => ['/produto',                    'product.view',                'Produto'],       // CU-2
    'Venda'                => ['/sells',                      'sell.view',                   'Vendas'],        // CU-3
    'Fiscal/Cockpit'       => ['/fiscal',                     'fiscal.cockpit.access',       'Notas Fiscais'], // CU-4
    'Fiscal/NF-e'          => ['/fiscal/nfe',                 'fiscal.nfe.access',           'NF-e'],          // CU-4
    'Fiscal/NFS-e'         => ['/fiscal/nfse',                'fiscal.nfse.access',          'NFS-e'],         // CU-4
    'Financeiro/Unificado' => ['/financeiro/unificado',      'financeiro.unificado.access', 'Financeiro'],   // CU-5
    // Âncora = H1 do workspace unificado (#2544) — renderiza mesmo sem o FSM seedado.
    'Oficina/OS'           => ['/oficina-auto/ordens-servico', 'oficinaauto.orders.view',   'Oficina Auto'],  // CU-6
    // — cobertura herdada (handoff Cowork 2026-06-02 §B) —
    'Compras'              => ['/compras',                    'compras.view',                'Compras'],
];

foreach ($screens as $nome => [$rota, $permissao, $ancora]) {
    it("{$nome} monta no AppShellV2 sem erro de console", function () use ($rota, $permissao, $ancora) {
        $user = User::factory()->create(['business_id' => 1]);
        // Permissão best-effort: se o nome exato não existir no seed, segue (o gate
        // real do teste é montar sem erro de console; ajustar o slug se 403 em CI).
        try {
            $user->givePermissionTo($permissao);
        } catch (\Throwable) {
            // slug de permissão pode variar por módulo — não falha o smoke por isso.
        }

        $page = visit($rota);

        // A tela tem que renderizar conteúdo (âncora) e não vazar erro de console.
        $page->assertSee($ancora)
            ->assertNoConsoleLogs();
    })->skip($browserMissing, 'pest-plugin-browser/chromium ausente — roda só no CI visual');
}
