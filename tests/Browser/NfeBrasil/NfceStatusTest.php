<?php

declare(strict_types=1);

/**
 * Pest 4 Browser · CHARACTERIZATION da tela NfeBrasil/Transactions/NfceStatus.
 *
 * ── O QUE ESTE ARQUIVO É (e o que NÃO é) ──────────────────────────────────────
 * É a PRIMEIRA rede de proteção de tela do módulo NfeBrasil (não existia
 * tests/Browser/NfeBrasil/). PoC do loop seguro de QA-de-tela (ADR 0249/0250).
 *
 * É CHARACTERIZATION, não spec do ideal: trava o COMPORTAMENTO DE HOJE do
 * NfceStatus.tsx (estado S0 do scorecard). Se um fix futuro (a metade "MUDAR",
 * que passa pelo gate visual do Wagner) alterar o que está afirmado aqui, o teste
 * QUEBRA DE PROPÓSITO — é o sinal de "alguém decidiu mudar", não um bug do teste.
 * Ao MUDAR a tela, atualize as asserções e o baseline visual no MESMO PR, com
 * aprovação humana do screenshot (anti-drift — ADR 0108/0250). Nunca "consertar"
 * o teste sozinho pra ficar verde.
 *
 * ── CONTRATO REAL DESTA TELA (lido do código, sem inventar) ───────────────────
 *   Rota Inertia : GET /nfe-brasil/transactions/{tx}/status
 *                  → NfeStatusController::showPage → Inertia::render(
 *                      'NfeBrasil/Transactions/NfceStatus', ['transaction_id' => $tx])
 *   Middleware   : web, auth, SetSessionData, language, timezone, AdminSidebarMenu
 *   Endpoint poll: GET /nfe-brasil/api/transactions/{tx}/nfe-status (hook useNfceStatus,
 *                  2s × 30 polls, abort no unmount). SEM emissão → status:null.
 *   Estado inicial sem emissão (caso desta tela em smoke): badge "Aguardando emissão",
 *   cStat "—", título "Status fiscal — Venda #{tx}".
 *
 * ── SMOKE biz=1 (ADR 0101 / 0250) ─────────────────────────────────────────────
 * SEMPRE business_id = 1 (nunca biz=4 / ROTA LIVRE). Persona dona = eliana (fiscal).
 *
 * ── VIEWPORTS ─────────────────────────────────────────────────────────────────
 * 1280 (canon ROTA LIVRE / Larissa-caixa) e 1440 (eliana desktop) — ADR 0250.
 *
 * ── EXECUÇÃO (CT 100 ONLY — não rodar local, memory/proibicoes.md) ────────────
 *   tailscale ssh root@ct100-mcp \
 *     "docker exec oimpresso-app ./vendor/bin/pest tests/Browser/NfeBrasil/NfceStatusTest.php"
 *
 * tests/Browser NÃO está no suite default (phpunit.xml): só roda no alvo explícito
 * com chromium. O `uses(TestCase::class)->in('Browser')` (tests/Pest.php) é o que
 * boota o app aqui — sem ele = BindingResolutionException [config].
 *
 * Locators resilientes (texto/role/testid), nunca classe CSS (L-24 / _PROPOSTA-0244).
 *
 * @see Modules/NfeBrasil/Http/Controllers/NfeStatusController.php (showPage + show)
 * @see resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx (tela sob teste)
 * @see resources/js/Hooks/useNfceStatus.ts (polling 2s, cap 30, abort unmount)
 * @see memory/governance/scorecards/screens/nfebrasil-transactions-nfcestatus.yaml
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php (idioma autenticado que de fato roda no gate)
 */

use App\User;

beforeEach(function () {
    // Relógio fixo: estabiliza qualquer timestamp/"emitido_em" e o baseline visual.
    \Carbon\Carbon::setTestNow('2026-06-07 12:00:00');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

// ⚠️ GUARD QUEBRADO — este teste NUNCA executou. `Pest\Browser\Bootstrap` não existe no
// pest-plugin-browser v4.3.1 (provado em runtime: `php -r` → false; o vizinho real é
// `Pest\Bootstrappers\*`, outro namespace). Logo `$browserMissing` é SEMPRE true → skip
// eterno. Somado a isso, nenhum workflow invoca este path. Mesmo diagnóstico já registrado
// em tests/Browser/Public/PublicSmokeTest.php:24-27.
// Mantido intencionalmente até [W] decidir entre religar (fixar guard + invocar no
// visual-regression.yml) ou remover — ver PR de remoção de falsa cobertura.
$browserMissing = ! class_exists(\Pest\Browser\Bootstrap::class);

// Transaction de smoke SEM emissão NFC-e: caracteriza o estado-base "Aguardando
// emissão" (o endpoint /nfe-status responde status:null). Número alto e fixo pra
// não colidir com seed e manter o baseline visual determinístico.
const NFCE_SMOKE_TX = 9090901;

/**
 * Viewports canônicos do método (ADR 0250). O caso de cada viewport gera um
 * baseline visual próprio (screenshot nomeado por largura) — a curadoria humana
 * aprova cada um (anti-drift).
 */
dataset('viewports', [
    'desktop-1280' => [1280, 800],
    'desktop-1440' => [1440, 900],
]);

it('monta a tela de status NFC-e (biz=1) sem erro de console — characterization S0', function (int $w, int $h) {
    // biz=1 SEMPRE (ADR 0101). Permissão best-effort: o gate real é montar sem
    // erro; a rota não exige permissão extra (status da própria venda do tenant).
    $user = User::factory()->create(['business_id' => 1]);

    $page = visit("/nfe-brasil/transactions/" . NFCE_SMOKE_TX . "/status")
        ->inViewport($w, $h);

    // ── Âncoras de comportamento ATUAL (characterization) ──
    // Título PT-BR vindo do PageHeader (Goal do charter: Head + título PT-BR).
    $page->assertSee('Status fiscal — Venda #' . NFCE_SMOKE_TX)
        // Subtítulo explicativo (1 linha) — charter Goal "texto explicativo curto".
        ->assertSee('Acompanhe o resultado da emissão da NFC-e')
        // Card de detalhe da nota.
        ->assertSee('NFC-e da venda #' . NFCE_SMOKE_TX)
        // Labels do <dl> read-only (information hierarchy atual).
        ->assertSee('Situação na SEFAZ')
        ->assertSee('Código de status (cStat)')
        // Estado-base sem emissão: badge "Aguardando emissão" (statusView default).
        ->assertSee('Aguardando emissão')
        // Ação sempre presente (charter Goal: refetch manual).
        ->assertSee('Verificar agora')
        // Ação de navegação (charter Goal: link voltar pra vendas).
        ->assertSee('Voltar para vendas');

    // ── Non-Goals / Anti-hooks do charter viram GUARD aqui (read-only) ──
    // Sem emissão NÃO mostra ações terminais (DANFE só quando autorizada;
    // Reemitir só quando rejeitada/denegada). Trava que a tela não vaza ações.
    $page->assertDontSee('Baixar DANFE')
        ->assertDontSee('Reemitir nota');

    // Zero erro de console (charter UX Target: 0 erros JS) + smoke geral.
    $page->assertNoConsoleLogs();

    // Baseline visual por viewport (curadoria humana aprova — anti-drift ADR 0108).
    $page->screenshot("nfebrasil-nfcestatus-aguardando-{$w}");
})->with('viewports')->skip($browserMissing, 'pest-plugin-browser/chromium ausente — roda só no CT 100 (gate visual)');

it('não vaza erro de acessibilidade crítico (axe WCAG) — piso a11y', function (int $w, int $h) {
    // A11y é dimensão de peso ALTO pra eliana (fiscal usa leitor de tela / alto
    // contraste). axe pega ~30-40% das violações (ADR 0250) — trava o PISO, não
    // substitui auditoria humana. Characterization: o estado de HOJE não pode
    // introduzir violação crítica; o scorecard já registra os gaps a11y a fechar
    // na metade MUDAR (aria-live no polling, aria-busy no spinner).
    $user = User::factory()->create(['business_id' => 1]);

    visit("/nfe-brasil/transactions/" . NFCE_SMOKE_TX . "/status")
        ->inViewport($w, $h)
        ->assertNoSmoke()        // sem erro de runtime/console antes do axe
        ->assertNoAccessibilityIssues(); // zero violação crítica/serious WCAG (axe-core)
})->with('viewports')->skip($browserMissing, 'pest-plugin-browser/chromium ausente — roda só no CT 100 (gate visual)');

it('ação "Verificar agora" continua na tela sem navegar (refetch, não reload) — characterization', function () {
    // Anti-pattern do charter: "Reload full após status final". A ação Verificar
    // agora é refetch via hook — a tela permanece (não muda de rota). Trava esse
    // comportamento de HOJE.
    $user = User::factory()->create(['business_id' => 1]);

    $page = visit("/nfe-brasil/transactions/" . NFCE_SMOKE_TX . "/status")
        ->inViewport(1280, 800)
        ->assertSee('Verificar agora')
        ->click('Verificar agora');

    // Continua na mesma tela (sem reload/redirect) e sem erro de console.
    $page->assertSee('Status fiscal — Venda #' . NFCE_SMOKE_TX)
        ->assertNoConsoleLogs();
})->skip($browserMissing, 'pest-plugin-browser/chromium ausente — roda só no CT 100 (gate visual)');
