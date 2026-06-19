<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — GATE VISUAL DE PIXEL das telas núcleo-6 (Onda Q4 · US-GOV-013).
 *
 * Regressão visual deixava de ser pega por máquina: o visual-regression rodava smoke +
 * probes, mas NENHUM diff de pixel com baseline commitada — [W] como detector de
 * regressão visual é anti-padrão (L-38). Este teste fecha o elo:
 *
 *   - assertScreenshotMatches() do pest-plugin-browser: pixelmatch nativo
 *     (threshold 0.3 · maxDiffPixels 300 · maxDiffPixelRatio 1% · anti-aliasing
 *     detectado · animações/transições zeradas · fonte normalizada Arial).
 *   - Baseline = snapshot Pest COMMITADO (gerado no CI — runner ubuntu, rendering
 *     determinístico; baseline local de dev Windows NÃO vale, fontes divergem).
 *   - Mudança visual INTENCIONAL = `npm run visreg:update` no CI (workflow_dispatch
 *     do job pixel-diff) + commit consciente do snapshot novo com aprovação [W]
 *     (gate F1.5). NUNCA update automático.
 *
 * Mesmo harness auth-bridge do AuthBridgeSmokeTest (cross-process via /_visreg-login).
 * Carbon::setTestNow pra matar flakiness de datas (padrão CreateScreenshotTest).
 *
 * Telas núcleo-6 (mandato ONDAS-QUALIDADE Q4): Financeiro/Unificado · Compras ·
 * Clientes · Oficina/OS · Sells/Index · Sells/Create.
 *
 * @see .github/workflows/visual-regression.yml (job pixel-diff)
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (harness)
 */

use App\Business;
use App\User;

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');

    \Carbon\Carbon::setTestNow('2026-06-11 12:00:00');
});
afterEach(fn () => \Carbon\Carbon::setTestNow());

/**
 * Tela => [rota, âncora que prova que montou ANTES do screenshot (sem ela o snapshot
 * congelaria um loading/skeleton — falso baseline)].
 */
$screens = [
    'Financeiro/Unificado' => ['/financeiro/unificado',        'Financeiro'],
    'Compras'              => ['/compras',                     'Compras'],
    'Clientes'             => ['/cliente',                     'Clientes'],
    'Oficina/OS'           => ['/oficina-auto/ordens-servico', 'Oficina Auto'],
    'Sells/Index'          => ['/sells',                       'Vendas'],
    'Sells/Create'         => ['/sells/create',                'Adicionar venda'],
];

foreach ($screens as $nome => [$rota, $ancora]) {
    it("{$nome} bate com a baseline de pixel (núcleo-6)", function () use ($rota, $ancora) {
        $business = Business::first();
        if (! $business) {
            test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
        }
        $admin = User::where('business_id', $business->id)->orderBy('id')->first();
        if (! $admin) {
            test()->markTestSkipped('Sem user no business seedado.');
        }

        $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota));
        $page->assertSee($ancora);

        // ESTABILIZAÇÃO (diagnóstico runs 27370651063/27370956421 — diff views):
        // (a) controles NATIVOS (select / input date|datetime|time) pintam com variação
        //     subpixel run-a-run E carregam valores vivos (Data da venda = agora) →
        //     visibility:hidden preserva o layout e zera a variância;
        // (b) settle explícito mata o early-paint (baseline de 2KB com "?" de fonte
        //     não carregada que o networkidle+readyState do plugin não pegou).
        $page->script(<<<'JS'
            (() => {
              const s = document.createElement('style');
              s.textContent = 'select, input[type=date], input[type=datetime-local], input[type=time] { visibility: hidden !important; }';
              document.head.appendChild(s);
              return true;
            })()
        JS);
        $page->wait(1.5);

        // fullPage=false: viewport-only — full page em lista longa varia com o
        // conteúdo do seed; o viewport é o contrato visual estável.
        $page->assertScreenshotMatches(fullPage: false);
    });
}
