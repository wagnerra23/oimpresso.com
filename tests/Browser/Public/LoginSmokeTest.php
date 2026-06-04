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
 * @see tests/Browser/CoreScreens/SmokeTest.php (Fase B — telas autenticadas, pendente harness)
 */

// SEM guard de skip: este teste roda só pelo path `tests/Browser/Public/` que o
// workflow visual-regression invoca explicitamente (chromium garantido). O guard
// `class_exists(\Pest\Browser\Bootstrap::class)` dos outros testes estava ERRADO
// (classe não existe em runtime → skip silencioso eterno = stub). Aqui ele roda.
it('/login/old (público) renderiza o formulário de login Blade', function () {
    // Mira /login/old (Blade legacy UltimatePOS, markup #login-form verificado em
    // resources/views/auth/login.blade.php). O /login canônico renderiza outra view
    // (sem #login-form). Âncora estrutural locale-free: form + campos montaram (não
    // é 500/branco). assertNoJavascriptErrors fica pra Fase B (assets legacy no CI).
    visit('/login/old')
        ->assertVisible('#login-form')
        ->assertVisible('#username')
        ->assertVisible('#password');
});
