<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — SMOKE PÚBLICO (Fase A · US-GOV-013 / ADR 0108).
 *
 * Primeiro teste de browser que RODA DE VERDADE no gate visual (sem continue-on-error).
 * Prova o pipeline end-to-end — chromium sobe → app Laravel boota (DB via schema-squash
 * do #2221) → rota renderiza → asserção — numa rota SEM auth (`/login`, Blade legacy
 * UltimatePOS), pra destravar o gate sem a complexidade de auth cross-process + tenant
 * session + seed das telas autenticadas (essas são a Fase B).
 *
 * Âncora estrutural (locale-free): o form de login (#login-form + #username + #password)
 * — não depende de tradução nem de texto visível variável.
 *
 * Roda só onde há chromium (CI visual-regression). O `uses(TestCase::class)->in('Browser')`
 * de tests/Pest.php é o que boota o app aqui (sem ele = BindingResolutionException [config]).
 *
 * @see .github/workflows/visual-regression.yml (step "Run Pest Browser tests")
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (Fase B — telas autenticadas, entregue)
 */

// SEM guard de skip: este teste roda só pelo path `tests/Browser/Public/` que o
// workflow visual-regression invoca explicitamente (chromium garantido). O guard
// `class_exists(\Pest\Browser\Bootstrap::class)` dos outros testes estava ERRADO
// (classe não existe em runtime → skip silencioso eterno = stub). Aqui ele roda.
it('/_smoke-probe (público) renderiza — pipeline visual end-to-end', function () {
    // Probe determinístico (rota não-prod, view standalone zero-deps): prova
    // chromium → app boota (schema-squash #2221) → rota → render → screenshot.
    // Páginas públicas legacy (login/welcome) 500am no test env minimal ($request
    // ausente / deps de layout) — o GATE pegou isso (smoke morde). Probe dedicado
    // dá o 200 determinístico pra Fase A; telas reais autenticadas = Fase B.
    visit('/_smoke-probe')
        ->assertSee('visual-gate-smoke-ok')
        ->assertVisible('[data-testid="smoke-probe"]');
});
