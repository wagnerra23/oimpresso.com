<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — PROBES DE CONFORMÂNCIA DS NO CHROMIUM REAL (PACOTE-Q9 PR-3).
 *
 * Espelho no CI da SEMÂNTICA do qa-conformance.js v2 do Cowork (G1–G6, camada 2) — portamos
 * a semântica, não o arquivo. As classes que SÓ existem com computed-style/layout real:
 *
 *   G2 controle nativo — checkbox/radio VISÍVEL com accent-color:auto (azul do browser) = 🔴
 *                        (classe do erro 06-10a).
 *   G3 papel de token  — regra APLICADA no DOM com var(--*-fg) em background/fill ou
 *                        var(--*-bg) em color/stroke = 🔴. O espelho ESTÁTICO (regex no .css)
 *                        vive em scripts/conformance-gate.mjs; este pega o caminho que o
 *                        estático não vê (styled-components/inline/Tailwind arbitrary).
 *   G4 overflow-x      — drawer/dialog com scrollWidth > clientWidth COM O ESTADO
 *                        "adicionando" ABERTO (o add-row é o que vazava — erro 06-10b).
 *
 * TELA NÚCLEO: Produção/Oficina com o drawer de OS aberto + form DVI "adicionar item" ativo
 * (o estado exato que estourava a largura no caso real).
 *
 * CONTROLE-NEGATIVO EMBUTIDO (L-31 / "todo ✅ tem que ter sido visto falhar"): cada probe é
 * vista 🔴 com bug INJETADO (style/elemento descartável, revertido no mesmo teste) e 🟢 no
 * limpo. Gate que nunca falhou não vale.
 *
 * PADRÃO ESPELHADO: A11yAxeBrowserTest (cross-process DB + /_visreg-login) + RichUITest
 * (criação/limpeza de Vehicle+ServiceOrder por prefixo). NUNCA biz=4 (ADR 0101).
 *
 * ⚠️ HONESTIDADE (ADR 0108): NÃO rodado local (Pest Browser = CI/CT 100 only). Validado por
 * php -l + espelhamento dos dois padrões acima que já rodam verdes no visual-regression.yml.
 *
 * @see scripts/conformance-gate.mjs (espelho estático G3 + controle-negativo vitest)
 * @see tests/Browser/CoreScreens/A11yAxeBrowserTest.php (padrão espelhado)
 * @see .github/workflows/visual-regression.yml (gate que invoca)
 */

use App\Business;
use App\User;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

beforeEach(function () {
    // CROSS-PROCESS DB (igual A11yAxe/AuthBridge): o browser usa MySQL (.env), o test
    // process realinha pro MESMO MySQL pra enxergar/criar o tenant seedado.
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');

    \Carbon\Carbon::setTestNow('2026-06-10 12:00:00');
});

afterEach(function () {
    // Limpeza ServiceOrders ANTES dos vehicles (FK) — mesmo idioma do RichUITest.
    ServiceOrder::withoutGlobalScopes()->where('notes', 'like', 'PROBE-G%')->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'like', 'PRBG%')->forceDelete();
    \Carbon\Carbon::setTestNow();
});

// ── Probes JS (semântica portada do qa-conformance.js v2 — IIFE puras, read-only) ──────────

/** G2: nº de checkbox/radio NATIVOS visíveis com accent-color ausente/auto. */
const PROBE_G2 = <<<'JS'
(() => {
  const vis = (el) => el.getClientRects().length
    && getComputedStyle(el).visibility !== 'hidden'
    && getComputedStyle(el).display !== 'none';
  return [...document.querySelectorAll('input[type="checkbox"], input[type="radio"]')]
    .filter(vis)
    .filter((el) => { const ac = getComputedStyle(el).accentColor; return !ac || ac === 'auto'; })
    .length;
})()
JS;

/** G3: nº de regras com papel de token invertido cujo seletor CASA o DOM atual. */
const PROBE_G3 = <<<'JS'
(() => {
  const BG = /(?:^|;)\s*(?:background(?:-color)?|fill)\s*:[^;]*var\(--[a-z0-9-]*-fg[,)]/i;
  const FG = /(?:^|;)\s*(?:color|stroke)\s*:[^;]*var\(--[a-z0-9-]*-bg[,)]/i;
  let bad = 0;
  for (const sheet of document.styleSheets) {
    let rules; try { rules = sheet.cssRules; } catch { continue; }
    if (!rules) continue;
    for (const r of rules) {
      if (!r.selectorText || !r.style) continue;
      const d = r.style.cssText;
      if (!BG.test(d) && !FG.test(d)) continue;
      try { if (document.querySelector(r.selectorText)) bad++; } catch { /* seletor exótico */ }
    }
  }
  return bad;
})()
JS;

/** G4: nº de drawers/dialogs visíveis estourando a própria largura (overflow-x). */
const PROBE_G4 = <<<'JS'
(() => {
  const vis = (el) => el.getClientRects().length;
  return [...document.querySelectorAll('[role="dialog"], aside[class*="drawer"]')]
    .filter(vis)
    .filter((el) => el.scrollWidth > el.clientWidth + 1)
    .length;
})()
JS;

it('Oficina drawer (estado "adicionando" ABERTO) — probes G2/G3/G4 limpos + controle-negativo discrimina', function () {
    $business = Business::first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    // FSM da Oficina é per-business e o visual-regression.yml NÃO roda OficinaAutoFsmSeeder
    // (só VisregTenantSeeder) — sem o processo `oficina_mecanica_os` o Quadro de OS renderiza
    // ZERO colunas e o card PRBG34 nunca aparece (falha do run 27292898190 pós-redirect
    // ADR 0265). Seeder idempotente (firstOrCreate) — teste auto-suficiente em qualquer env.
    (new \Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder())->runForBusiness((int) $business->id);

    // Dados mínimos pro drawer existir: 1 veículo + 1 OS aberta (idioma RichUITest).
    $vehicle = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => $business->id,
        'plate'          => 'PRBG34',
        'vehicle_type'   => 'cacamba_estacionaria',
        'current_status' => 'manutencao',
    ]);
    ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id' => $business->id,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'entered_at'  => now()->subDay(),
        'notes'       => 'PROBE-G34 — conformance probes',
    ]);

    // Tela núcleo autenticada + drawer aberto + form DVI "adicionar" ativo (o estado que vazava).
    // ADR 0265: kanban canônico único = Quadro de OS (producao-oficina virou redirect 301).
    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/oficina-auto/ordens-servico/board'));
    $page->assertSee('PRBG34');
    $page->click('PRBG34');
    $page->assertSee('Fotos & Laudo');           // drawer rico montou
    $page->click('Adicionar primeiro item');     // estado "adicionando" ABERTO
    // O Select do form nasce com preset (mostra "Motor · óleo + filtro", não o placeholder),
    // então "Sistema a vistoriar" só existe como aria-label — provado por screenshot do run
    // 27274045670. Presença do form = combobox com esse accessible name dentro do dialog.
    expect($page->script(
        '!!document.querySelector(\'[role="dialog"] [aria-label="Sistema a vistoriar"]\')'
    ))->toBe(true, 'form DVI "adicionando" deve estar aberto no drawer');

    // ── 🟢 no limpo ──────────────────────────────────────────────────────────
    expect($page->script(PROBE_G2))->toBe(0, 'G2: controle nativo visível com accent-color:auto');
    expect($page->script(PROBE_G3))->toBe(0, 'G3: papel de token invertido aplicado no DOM');
    expect($page->script(PROBE_G4))->toBe(0, 'G4: drawer/dialog com overflow-x no estado adicionando');

    // ── 🔴 no bug injetado (controle-negativo, revertido no mesmo teste) ─────
    // N-G2: checkbox nativo com accent-color:auto forçado dentro do dialog.
    $g2Durante = $page->script(<<<'JS'
(() => {
  const host = document.querySelector('[role="dialog"]') || document.body;
  const el = document.createElement('input');
  el.type = 'checkbox'; el.id = '__probe_neg_g2';
  el.style.cssText = 'accent-color:auto;width:16px;height:16px;';
  host.appendChild(el);
  const vis = (e) => e.getClientRects().length;
  const n = [...document.querySelectorAll('input[type="checkbox"], input[type="radio"]')]
    .filter(vis)
    .filter((e) => { const ac = getComputedStyle(e).accentColor; return !ac || ac === 'auto'; })
    .length;
  el.remove();
  return n;
})()
JS);
    expect($g2Durante)->toBeGreaterThan(0, 'N-G2: probe não viu o checkbox accent-color:auto injetado — gate cego');

    // N-G3: regra -fg-em-background + elemento que a casa.
    $g3Durante = $page->script(<<<'JS'
(() => {
  const st = document.createElement('style');
  st.textContent = '.__probe-neg-role { background: var(--origin-MFG-fg); }';
  document.head.appendChild(st);
  const el = document.createElement('i');
  el.className = '__probe-neg-role';
  document.body.appendChild(el);
  const BG = /(?:^|;)\s*(?:background(?:-color)?|fill)\s*:[^;]*var\(--[a-z0-9-]*-fg[,)]/i;
  let bad = 0;
  for (const sheet of document.styleSheets) {
    let rules; try { rules = sheet.cssRules; } catch { continue; }
    if (!rules) continue;
    for (const r of rules) {
      if (!r.selectorText || !r.style || !BG.test(r.style.cssText)) continue;
      try { if (document.querySelector(r.selectorText)) bad++; } catch {}
    }
  }
  st.remove(); el.remove();
  return bad;
})()
JS);
    expect($g3Durante)->toBeGreaterThan(0, 'N-G3: probe não viu a inversão de papel injetada — gate cego');

    // N-G4: filho de 9999px dentro do dialog aberto.
    $g4Durante = $page->script(<<<'JS'
(() => {
  const host = document.querySelector('[role="dialog"]');
  if (!host) return -1;
  const el = document.createElement('div');
  el.style.cssText = 'min-width:9999px;height:1px;';
  host.appendChild(el);
  const n = [...document.querySelectorAll('[role="dialog"], aside[class*="drawer"]')]
    .filter((e) => e.getClientRects().length)
    .filter((e) => e.scrollWidth > e.clientWidth + 1)
    .length;
  el.remove();
  return n;
})()
JS);
    expect($g4Durante)->toBeGreaterThan(0, 'N-G4: probe não viu o estouro de largura injetado — gate cego');

    // ── 🟢 de volta no limpo (injeções revertidas no mesmo tick) ─────────────
    expect($page->script(PROBE_G2))->toBe(0);
    expect($page->script(PROBE_G3))->toBe(0);
    expect($page->script(PROBE_G4))->toBe(0);
});
