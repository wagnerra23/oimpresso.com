<?php

declare(strict_types=1);

/**
 * Camada 2 — Conformância de CONTRATO DS por computed-style (Pest 4 browser, determinístico).
 *
 * Diferente da Camada 1 (conformance-gate.mjs · cor crua estática no CSS), esta camada checa o
 * VALOR COMPUTADO no runtime real — pega drift que o CSS estático esconde (cascade/override/tema).
 * Asserções batem o `ds`/`regua` do charter `Pages/Sells/Index.charter.md` (ds: v6 · roxo 295).
 *
 * IDs estáveis (espelham memory/requisitos/Sells/sells-index-dsv6-visual-comparison.md · Vendas.casos.md):
 *   UC-V09 — accent do primary "Nova venda" == token ROXO (oklch hue 250–330), NÃO verde. ← pega o drift verde×roxo.
 *   UC-V12 — 2 temas: card de KPI NÃO pode ser quase-branco no dark (dívida `--surface`/`.os-kpi #fff`).
 *   UC-V11 — detalhe abre drawer role=dialog largura canon (~480). [stub honesto — ver nota no teste]
 *
 * Camada META (controle-negativo · regra do MÉTODO "todo ✅ tem que ter sido visto falhar"):
 *   UC-V09 injeta verde no botão em runtime e EXIGE que o classificador rejeite (hue fora de 250–330).
 *   Sem isso o teste é fajuto. A classificação rgb→oklch roda no browser (porta o qa-conformance.js 1:1).
 *
 * Locator resiliente: classe canônica do DS (`.os-btn.primary` bg=var(--accent), sells-cowork.css:2211).
 *
 * Rodar:  ./vendor/bin/pest tests/Browser/Sells/IndexConformanceTest.php
 * Pré-req local: composer install · npm install · npx playwright install chromium · DB de teste.
 * (Em máquina sem browser bootável, os testes pulam graceful — igual CreateScreenshotTest.php.)
 *
 * Refs: ADR 0235/0190 (roxo 295) · ADR 0107 (gate visual F3) · ADR 0209 (ratchet) · PROMPT_PARA_CODE_CONFORMANCE-GATE.md.
 */

use App\User;

// ── helpers JS (portam a lógica de classificação de cor do qa-conformance.js) ────────────────
//
// OKLab/OKLCH conversion (Björn Ottosson) — sRGB(0..255) → OKLCH. Roda NO BROWSER via $page->script().
// Retorna {h, L}: h=hue OKLCH (graus 0–360), L=lightness OKLab (0–1). Mesma classe que o gabarito usa.
function sellsConformanceColorProbeJs(string $selector): string
{
    return <<<JS
    (() => {
      const el = document.querySelector('{$selector}');
      if (!el) return { found: false };
      const cs = getComputedStyle(el).backgroundColor;          // "rgb(r, g, b)" | "rgba(...)"
      const m = cs.match(/[\d.]+/g);
      if (!m || m.length < 3) return { found: true, transparent: true, raw: cs };
      const [r, g, b] = m.slice(0, 3).map(Number);
      const f = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
      const lr = f(r), lg = f(g), lb = f(b);
      const l = 0.4122214708*lr + 0.5363325363*lg + 0.0514459929*lb;
      const mm = 0.2119034982*lr + 0.6806995451*lg + 0.1073969566*lb;
      const s = 0.0883024619*lr + 0.2817188376*lg + 0.6299787005*lb;
      const l_ = Math.cbrt(l), m_ = Math.cbrt(mm), s_ = Math.cbrt(s);
      const L = 0.2104542553*l_ + 0.7936177850*m_ - 0.0040720468*s_;
      const A = 1.9779984951*l_ - 2.4285922050*m_ + 0.4505937099*s_;
      const B = 0.0259040371*l_ + 0.7827717662*m_ - 0.8086757660*s_;
      let h = Math.atan2(B, A) * 180 / Math.PI; if (h < 0) h += 360;
      return { found: true, transparent: false, raw: cs, h, L };
    })()
    JS;
}

function sellsConformanceLogin(string $permission = 'sell.view'): User
{
    $user = User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo($permission);

    return $user;
}

$browserAusente = fn () => ! class_exists(\Pest\Browser\Bootstrap::class);

// ── UC-V09 — accent do primary é ROXO 295, não verde (+ controle-negativo inline) ────────────
it('UC-V09 · accent do "Nova venda" é roxo 295 (oklch hue 250–330), não verde', function () {
    sellsConformanceLogin();

    $page = visit('/sells');
    $page->wait('text=Nova venda', timeout: 8000);

    // (a) SENSIBILIDADE/PARIDADE: o botão real tem que ser roxo.
    $probe = (array) $page->script(sellsConformanceColorProbeJs('.os-btn.primary'));
    expect($probe['found'] ?? false)->toBeTrue('botão .os-btn.primary "Nova venda" não encontrado');
    expect($probe['transparent'] ?? true)->toBeFalse('accent do primary não pode ser transparente');
    expect($probe['h'])->toBeGreaterThanOrEqual(250.0)
        ->and($probe['h'])->toBeLessThanOrEqual(330.0, "accent fora do roxo (hue {$probe['h']}°) — drift de identidade (ADR 0190/0235)");

    // (b) CONTROLE-NEGATIVO (Camada META): injeta verde em runtime → o classificador TEM que rejeitar.
    //     Prova que a asserção (a) realmente PEGA o drift verde×roxo (gate nunca-visto-vermelho não vale).
    $page->script("document.querySelector('.os-btn.primary').style.setProperty('background-color', 'rgb(34, 197, 94)', 'important')"); // verde tailwind-500
    $bug = (array) $page->script(sellsConformanceColorProbeJs('.os-btn.primary'));
    $verdeRoxo = ($bug['h'] >= 250.0 && $bug['h'] <= 330.0);
    expect($verdeRoxo)->toBeFalse("controle-negativo falhou: verde (hue {$bug['h']}°) passou como roxo — a asserção UC-V09 não discrimina");
})->skip($browserAusente, 'pest-plugin-browser não bootável neste ambiente');

// ── UC-V12 — 2 temas: card de KPI não pode ser quase-branco no DARK ──────────────────────────
it('UC-V12 · no tema escuro o card de KPI não fica quase-branco (dívida --surface)', function () {
    sellsConformanceLogin();

    $page = visit('/sells')->inDarkMode();
    $page->wait('text=Nova venda', timeout: 8000);

    $probe = (array) $page->script(sellsConformanceColorProbeJs('.os-kpi'));
    expect($probe['found'] ?? false)->toBeTrue('card .os-kpi não encontrado');

    // No dark, a superfície do card tem que ser ESCURA. Card quase-branco = L (OKLab) ~0.95+.
    // Teto generoso 0.85 separa "card escuro legítimo" de "branco vazado" sem flake.
    if (! ($probe['transparent'] ?? false)) {
        expect($probe['L'])->toBeLessThan(0.85, "card de KPI quase-branco no dark (L={$probe['L']}) — UC-V12 / .os-kpi #fff (régua L-114)");
    }
})->skip($browserAusente, 'pest-plugin-browser não bootável neste ambiente');

// ── UC-V11 — detalhe abre drawer role=dialog largura canon ~480 ──────────────────────────────
// NOTA HONESTA (L-26/27): este UC exige (1) venda semeada no DB de teste pra ter linha clicável e
// (2) o locator estável do drawer de detalhe (componente Sheet, não o `.vd-pal` da palette). Nenhum
// dos dois foi verificável offline nesta sessão (vendor/DB ausentes). Em vez de cravar um locator
// adivinhado que erra em CI, deixo o stub explícito com o critério de aceite pronto pra preencher
// quando rodar com app+DB. O ratchet do método (cobertura só sobe) cobra o preenchimento.
// Placeholder puro (sem visit/browser) — registra o UC e o critério; preencher quando rodar com app+DB.
// TODO(camada-2): semear venda no beforeEach, clicar a 1ª linha, então:
//   $w = $page->script("(() => { const d=document.querySelector('[role=dialog].os-drawer'); return d ? d.getBoundingClientRect().width : -1; })()");
//   expect($w)->toBeGreaterThanOrEqual(380.0)->and($w)->toBeLessThanOrEqual(600.0);
it('UC-V11 · detalhe abre drawer role=dialog largura ~480 (380–600)', function () {
    expect(true)->toBeTrue();
})->todo('precisa de venda semeada + locator do drawer de detalhe (não o .vd-pal) — preencher com app+DB');
