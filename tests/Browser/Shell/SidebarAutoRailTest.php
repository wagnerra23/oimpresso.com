<?php

declare(strict_types=1);

/**
 * Pest 4 Browser · CONTRATO do auto-rail do shell (ADR UI-0030).
 *
 * ── O QUE ESTE ARQUIVO DEFENDE ────────────────────────────────────────────────
 * O `AppShellV2` nasce em RAIL (56px) em vez de expandido (260px) quando a
 * viewport é estreita (<= AUTO_RAIL_MAX_W = 1280), e volta a expandir quando ela
 * alarga — ENQUANTO o usuário não tiver escolhido manualmente. Escolha manual
 * (alça de colapsar ou ⌘\) é persistida em `oimpresso.sb.mode` e VENCE em
 * qualquer largura.
 *
 * ── POR QUE MEDIR `grid-template-columns`, e não a classe ─────────────────────
 * `data-sidebar` é o que o React MANDA; `grid-template-columns` é o que o browser
 * RESOLVEU depois da cascata da `cockpit.css` (que tem media query própria em
 * `max-width:1280px`). Afirmar sobre o atributo mediria a minha intenção, não o
 * layout — o erro catalogado em `memory/proibicoes.md` §5 2026-07-16 ("medir a
 * propriedade errada e chamar de verificado"). Aqui os dois são medidos: o
 * atributo (estado do React) E a coluna resolvida (o que o usuário vê).
 *
 * ── UCs defendidos (resources/js/Layouts/AppShellV2.casos.md) ─────────────────
 *   UC-SHELL-01 · viewport estreita nasce em rail
 *   UC-SHELL-02 · viewport larga nasce expandida
 *   UC-SHELL-03 · escolha manual vence a largura
 *   UC-SHELL-04 · sem escolha manual, o modo acompanha o resize ao vivo
 *
 * ── ONDE RODA ────────────────────────────────────────────────────────────────
 * CT 100 / CI apenas (Tier 0 — `memory/proibicoes.md` §Ambiente). A lane é o step
 * "Contrato do shell · auto-rail ≤1280" do `visual-regression.yml`, que já provê
 * Chromium — por isso o arquivo NÃO carrega guard de skip (ver nota abaixo).
 */

use App\Models\Business;
use App\Models\User;

// SEM guard `class_exists(...)` de propósito. A 1ª versão deste arquivo copiou
// `$browserMissing = ! class_exists(\Pest\Browser\Bootstrap::class)` do
// `tests/Browser/NfeBrasil/NfceStatusTest.php` — e o resultado foi
// `4 skipped (0 assertions)` num step marcado SUCCESS (run 33675746851): o teste
// não rodou e o verde não distinguia isso de ter rodado. A classe não resolve na
// versão instalada do plugin, e ninguém tinha percebido porque o NfceStatusTest
// **não é citado por lane nenhuma** (`grep NfceStatusTest .github/workflows/` = 0)
// — o guard quebrado nunca foi observável. Todo teste Browser que de fato roda
// (PixelBaseline, IsolatedStates, os 3 de fluxo, Conciliacao) não tem guard: quem
// garante o Chromium é a lane, não o arquivo.

/** Largura resolvida da 1ª coluna do grid `.cockpit` (o que o browser pintou). */
const COLS_JS = <<<'JS'
(() => {
  const el = document.querySelector('.cockpit');
  if (!el) return 'SEM_COCKPIT';
  return getComputedStyle(el).gridTemplateColumns.split(' ')[0];
})()
JS;

const MODE_JS = '(document.querySelector(".cockpit") || {}).dataset?.sidebar ?? "SEM_COCKPIT"';

/**
 * Abre uma tela do núcleo autenticada. Reusa o `/_visreg-login/{id}` que as
 * suítes de CoreScreens já usam (mesmo bootstrap do gate visual).
 */
function abrirShell(): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/forja/aprovacoes'));
}

it('UC-SHELL-01 · a 1280 (monitor do [W]) o shell nasce em RAIL de 56px', function () {
    $page = abrirShell()->inViewport(1280, 800);

    expect($page->script(MODE_JS))->toBe('rail', 'estado do React a 1280');
    expect($page->script(COLS_JS))->toBe('56px', 'coluna resolvida pelo browser a 1280');
});

it('UC-SHELL-02 · a 1440 o shell nasce EXPANDIDO de 260px', function () {
    $page = abrirShell()->inViewport(1440, 900);

    expect($page->script(MODE_JS))->toBe('expanded', 'estado do React a 1440');
    expect($page->script(COLS_JS))->toBe('260px', 'coluna resolvida pelo browser a 1440');
});

it('UC-SHELL-03 · escolha manual persistida VENCE a largura (não é sobrescrita a 1280)', function () {
    // Nasce largo e sem chave: expandido por largura.
    $page = abrirShell()->inViewport(1440, 900);
    expect($page->script(COLS_JS))->toBe('260px', 'pré-condição: expandido a 1440');

    // Simula a escolha explícita do usuário (é o que a alça e o ⌘\ gravam).
    $page->script('localStorage.setItem("oimpresso.sb.mode", "expanded")');

    // Estreita: sem a trava, o listener rebaixaria pra rail.
    $page->inViewport(1280, 800);

    expect($page->script(MODE_JS))->toBe('expanded', 'escolha manual deve sobreviver ao resize');
    expect($page->script(COLS_JS))->toBe('260px', 'coluna resolvida após estreitar com escolha manual');
});

it('UC-SHELL-04 · SEM escolha manual, o modo acompanha o resize ao vivo (1440 → 1280 → 1440)', function () {
    // Este é o delta deliberado vs o protótipo, que só decide no mount: aqui
    // plugar/desplugar monitor externo não deixa o shell no modo errado.
    $page = abrirShell()->inViewport(1440, 900);
    $page->script('localStorage.removeItem("oimpresso.sb.mode")');
    expect($page->script(COLS_JS))->toBe('260px', 'pré-condição: expandido a 1440 sem chave');

    $page->inViewport(1280, 800);
    expect($page->script(COLS_JS))->toBe('56px', 'estreitou → rail');

    $page->inViewport(1440, 900);
    expect($page->script(COLS_JS))->toBe('260px', 'alargou → volta a expandir');
});
