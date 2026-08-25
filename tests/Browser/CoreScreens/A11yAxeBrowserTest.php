<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — A11Y AXE-CORE EM BROWSER REAL (Fase 3 da determinização de a11y).
 *
 * POR QUE ESTE TESTE EXISTE (o que jsdom NÃO pega):
 *   - Fase 1 (✅ #2359): ratchet jsx-a11y no lint — pega a11y ESTÁTICA no .tsx (alt ausente,
 *     label órfão, role inválido). Não renderiza nada.
 *   - Fase 2: axe-core em jsdom (thread) — pega a11y no DOM SIMULADO. Mas jsdom NÃO tem
 *     layout engine: cor computada, contraste, ordem de foco, viewport, nome acessível
 *     computado no DOM real → axe pula ou erra essas regras em jsdom.
 *   - Fase 3 (ESTE arquivo): axe-core no CHROMIUM REAL (Playwright via pest-plugin-browser).
 *     Pega o que só existe com layout+render de verdade: **contraste de cor (color-contrast),
 *     ordem de foco, ARIA-em-contexto, nome acessível computado no DOM real**.
 *
 * Determinístico: axe é REGRA (WCAG 2.1 A/AA), não juiz LLM. Refs auditoria
 * memory/sessions/2026-06-06-arte-llm-judge-para-deterministico.md.
 *
 * COMO O AXE É INJETADO (sem CDN, sem node_modules manual):
 *   O `pestphp/pest-plugin-browser` v4 JÁ BUNDLEIA `axe.min.js` e o injeta como init-script
 *   do Playwright em TODA página (ver vendor pest-plugin-browser/src/Playwright/InitScript.php
 *   → prepend de resources/js/axe.min.js antes do hook de console). Logo `window.axe` está
 *   disponível em toda página carregada por `visit()` — não precisa carregar de CDN.
 *
 *   A assertion nativa `assertNoAccessibilityIssues(int $level)` (MakesConsoleAssertions):
 *     1. espera networkidle + document.readyState === 'complete'
 *     2. roda `await window.axe.run()` na página
 *     3. filtra violações por severidade (rank <= $level) e asserta vazio
 *
 *   Mapa de severidade (Pest\Browser\Enums\AccessibilityIssueLevel):
 *     level 0 = critical
 *     level 1 = critical + serious   ← alvo final da Fase 3
 *     level 2 = + moderate
 *     level 3 = + minor (tudo)
 *
 * RATCHET (igual jsx-a11y / axe-jsdom): começamos no PISO MAIS CONSERVADOR — level 0
 * (CRITICAL only). Critical é a classe mais rara e mais grave; assertar 0 critical numa
 * tela autenticada real é o gate honesto pra Fase 3 nascer VERDE sem allowlist cego.
 * Próximo degrau do ratchet (PR follow-up, quando esta passar verde no CI): subir pra
 * level 1 (critical + serious) — provavelmente exigirá corrigir contraste/foco antes,
 * por isso NÃO entra já (não falhamos a suíte às cegas). A escolha está documentada aqui
 * em vez de allowlist de violações conhecidas: o piso level-0 É o baseline explícito.
 *
 * PADRÃO ESPELHADO: tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (o padrão que de fato
 * RODA no gate visual via auth-bridge cross-process). Mesma config de DB cross-process,
 * mesmo VisregTenantSeeder, mesma rota /_visreg-login/{id}?to=<tela>. SEM skip-guard: roda
 * só pelo path que o workflow invoca explicitamente (chromium garantido).
 *
 * ⚠️ HONESTIDADE (ADR 0108 + hook block-test-fora-ct100): este teste NÃO foi rodado local —
 * Pest Browser só roda no CI (visual-regression.yml, chromium garantido) ou no CT 100.
 * Validado por: (a) `php -l` sintaxe, (b) espelhamento EXATO do AuthBridgeSmokeTest que já
 * roda verde, (c) API nativa confirmada no fonte do plugin v4.3.1. VALIDA NO CT 100/CI.
 *
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (padrão espelhado)
 * @see .github/workflows/visual-regression.yml (gate que invoca)
 * @see routes/web.php (rota _visreg-login, guard !isProduction)
 * @see memory/decisions/0108-regressao-visual-pest-browser-tier-2.md
 */

use App\Business;
use App\User;

beforeEach(function () {
    // CROSS-PROCESS DB (igual AuthBridgeSmokeTest): o browser (subprocesso) usa MySQL
    // (.env, schema-squash #2221), mas o test process usa sqlite :memory: (phpunit.xml).
    // Realinha o test process pro MESMO MySQL pra enxergar o tenant seedado.
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');

    \Carbon\Carbon::setTestNow('2026-06-06 12:00:00');
});
afterEach(fn () => \Carbon\Carbon::setTestNow());

/**
 * UMA tela core (a mesma que o AuthBridgeSmokeTest já cobre e prova verde no CI):
 * Financeiro/Unificado. [rota, âncora-de-texto que prova que montou — não 403/login/erro].
 *
 * Conservador por design: 1 tela, critical-only. Ampliar pro núcleo-6 é PR follow-up
 * (depois que esta nascer verde), exatamente como o AuthBridge ampliou as públicas.
 */
$rota = '/financeiro/unificado';
$ancora = 'Financeiro';

it('Financeiro/Unificado — 0 violações axe CRITICAL no browser real (auth bridge)', function () use ($rota, $ancora) {
    // Tenant SEEDADO (VisregTenantSeeder roda no workflow): business 1 + admin id=1 +
    // role spatie Admin#1 (Gate::before concede tudo). Sem seed = skip (não falha) —
    // idêntico ao guard do AuthBridgeSmokeTest.
    // orderBy('id') = biz 1 determinístico: o gate também seeda 98 (VisregEmptyTenantSeeder)
    // e 99 (VisregTenantBLeakSeeder) — sem ordem explícita o "first" é o que o MySQL devolver.
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    // 1 visit: loga o admin no subprocesso + redireciona pra tela → carrega autenticada.
    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota));

    // Gate 1: a tela montou de verdade (não caiu em 403/login/erro) antes de auditar a11y.
    $page->assertSee($ancora);

    // Gate 2 (Fase 3): axe-core no Chromium real. level 0 = CRITICAL only (piso do ratchet).
    // window.axe já injetado pelo plugin (InitScript). Pega contraste/foco/ARIA-em-contexto
    // que o jsdom da Fase 2 não enxerga.
    $page->assertNoAccessibilityIssues(level: 0);
});

/**
 * LANDMARKS DO SHELL — contrato estrutural que o axe NÃO pega no piso atual.
 *
 * POR QUE NÃO BASTA O TESTE ACIMA (medido em 2026-08-21, no worktree nervous-gates-91eadf):
 *   - A regra que cobriria isto no axe é `region` ("todo conteúdo dentro de landmark"),
 *     de severidade MODERATE e tag best-practice — não WCAG A/AA. O gate acima roda
 *     `assertNoAccessibilityIssues(level: 0)` = CRITICAL only, logo `region` é filtrada
 *     fora por severidade. Só apareceria em level 2 (+moderate), dois degraus acima do
 *     ratchet atual — e o docblock do topo já registra que nem o level 1 passa hoje.
 *   - O outro caminho de a11y do repo tampouco vê: `a11y-ratchet.mjs` é jsx-a11y ESTÁTICO
 *     (sem regra de landmark) e `tests/a11y-primitives.test.tsx` renderiza componentes
 *     ISOLADOS, nunca o shell. Ambos cegos a landmark de página por construção.
 *
 * POR QUE A ASSERÇÃO É SOBRE O DOM RENDERIZADO, e não um grep no .tsx:
 *   grep no fonte mediria o DISCO, não o render — o landmark nasce de JSX condicional
 *   (o <nav> do topbar, por exemplo, vive atrás de `hideTopbar` que é `true` por default).
 *   Só o browser real responde "existe landmark nesta página?".
 *
 * O QUE ESTE TESTE TRAVA (e o que NÃO trava):
 *   ✅ o shell emite exatamente 1 <main>  — pega tanto o sumiço quanto o DUPLICADO
 *      (a colisão real que este PR corrigiu: Produto/StockHistory e FinPresentationMode
 *      emitiam <main> próprio DENTRO do AppShellV2).
 *   ✅ existe ≥1 <nav>, e a navegação primária está FORA do <main> — que é o contrato
 *      de verdade: sem isso o leitor de tela não tem como pular a sidebar.
 *   ❌ NÃO afirma nada sobre as outras 212 telas — mede a tela deste gate. Ampliar é
 *      PR follow-up, igual ao ratchet acima.
 *
 * ⚠️ HONESTIDADE (idem docblock do topo): Pest Browser não roda local (hook
 * block-test-fora-ct100 + ADR 0108). Validado por `php -l` e por espelhar a API
 * `$page->script()` já usada em ConformanceProbesTest. VALIDA NO CI/CT 100.
 */
it('AppShellV2 — landmarks: 1 <main>, nav primária fora dele (auth bridge)', function () use ($rota, $ancora) {
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota));
    $page->assertSee($ancora);

    // 1) Existe exatamente UM <main>. 0 = o defeito original (AppShellV2 emitia <div
    //    className="main-body">). >1 = landmark-no-duplicate-main (tela remontou um <main>
    //    próprio dentro do shell).
    expect($page->script('document.querySelectorAll("main").length'))
        ->toBe(1, 'shell deve emitir exatamente 1 <main> (0 = landmark ausente; >1 = duplicado)');

    // 2) Existe navegação com landmark.
    expect($page->script('document.querySelectorAll("nav").length'))
        ->toBeGreaterThanOrEqual(1, 'shell deve emitir ao menos 1 <nav> (navegação primária da sidebar)');

    // 3) O CONTRATO de fato: a navegação primária está FORA do <main>. Se estivesse dentro,
    //    o <main> existiria mas não serviria pra pular a sidebar — landmark decorativo.
    expect($page->script(<<<'JS'
(() => {
  const nav = document.querySelector('nav[aria-label="Navegação principal"]');
  if (!nav) return 'ausente';
  return document.querySelector('main')?.contains(nav) ? 'dentro' : 'fora';
})()
JS))->toBe('fora', 'a nav primária deve ficar FORA do <main> (senão o landmark não permite pular a sidebar)');
});
