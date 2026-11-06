<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `resources/js/Pages/Fiscal/Das.tsx` (Fiscal/Das).
 *
 * ── O BURACO QUE ISTO FECHA (medido em 2026-11-05) ────────────────────────────
 * `git grep -l "Fiscal/Das" tests/Browser/` devolvia ZERO. A tela nasceu em 2026-10-27
 * (#10268) só com o Pest de controller (`Modules/Fiscal/Tests/Feature/DasControllerTest.php`),
 * que prova o PAYLOAD do Inertia, nunca o DOM. Auditoria do Fiscal, lote 5.
 *
 * ── O QUE PROVA ───────────────────────────────────────────────────────────────
 *   - UC-FDAS-03 no browser: sem anexo, o DOM pinta o estado vazio que pede o anexo, e
 *     nenhuma das âncoras de valor (`kpis`, `conta`, `faixas`) aparece.
 *   - UC-FDAS-04 no browser: com `anexo=I`, as âncoras de valor chegam ao DOM depois do
 *     `<Deferred data="apuracao">`, e a tabela de faixas do Anexo I tem 6 linhas.
 *   - UX Target do charter: cabe em 1280px sem scroll horizontal no documento.
 *   - axe-core CRITICAL (level 0, o piso do A11yAxeBrowserTest) nos dois estados.
 *
 * ── POR QUE A FLAG ESTÁ LIGADA AQUI ───────────────────────────────────────────
 * A rota responde 404 com `useV2FiscalDas` desligada (UC-FDAS-01). O harness liga a flag por
 * `FEATURE_FLAGS_FORCED_ON` em `scripts/tests/visreg-ci-setup.sh`, caminho INERTE em produção
 * (`FeatureFlagService::forcedOn` ignora o valor em `isProduction()`). Ligar para empresas
 * reais segue decisão [W] no GrowthBook.
 *
 * ── POR QUE NÃO ESTÁ NO visreg-screens.json ───────────────────────────────────
 * Entrar no manifesto põe a tela também na catraca `tema-escuro-producao`, que reprova Page
 * MEDIDA fora da baseline dela. Essa medição não foi feita para o DAS; o axe roda aqui.
 *
 * ── NENHUM CASO DEPENDE DE VALOR ──────────────────────────────────────────────
 * O tenant do harness não tem venda na competência; a apuração pode vir com `motivo` e
 * valores em "—". Os casos asseram estrutura (âncoras, linhas da tabela de faixas, ausência
 * de scroll), nunca número.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (memory/proibicoes.md §Ambiente). Step "E2E de render · Fiscal/Das
 * (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use App\Business;
use App\User;

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

/** Âncoras `data-contract` que o Das.tsx declara. */
const ANCORAS_DAS = ['cabecalho', 'vazio', 'kpis', 'conta', 'faixas', 'contador'];

function ancorasDasJs(): string
{
    $querido = json_encode(ANCORAS_DAS);

    return '(() => { const querido = ' . $querido . ';'
        . ' return Array.from(document.querySelectorAll("[data-contract]"))'
        . ' .map((el) => el.getAttribute("data-contract"))'
        . ' .filter((a) => querido.includes(a)).join(","); })()';
}

/** Espera a sequência estabilizar (prop `apuracao` é adiada). Devolve o último valor lido. */
function ancorasDasEstaveis($page, string $esperado): string
{
    $visto = '';
    for ($i = 0; $i < 30; $i++) {
        $visto = (string) $page->script(ancorasDasJs());
        if ($visto === $esperado) {
            return $visto;
        }
        $page->wait(0.1);
    }

    return $visto;
}

function abrirDas(string $query = ''): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/fiscal/das' . $query));
}

it('UC-FDAS-03 · sem anexo o browser pinta o pedido do anexo e nenhuma âncora de valor', function () {
    $page = abrirDas()->resize(1280, 800);

    expect(ancorasDasEstaveis($page, 'cabecalho,vazio'))
        ->toBe('cabecalho,vazio', 'data-contract pintados em /fiscal/das sem anexo');
    $page->assertSee('Escolha o anexo que o contador usa');
});

it('UC-FDAS-04 · com anexo=I as âncoras de valor chegam depois do Deferred, com 6 faixas', function () {
    $page = abrirDas('?anexo=I&competencia=2026-10')->resize(1280, 800);
    $esperado = 'cabecalho,kpis,conta,faixas,contador';

    expect(ancorasDasEstaveis($page, $esperado))
        ->toBe($esperado, 'data-contract pintados em /fiscal/das?anexo=I');
    $page->assertSee('Faixas do Anexo I');
    expect((string) $page->script('String(document.querySelectorAll(\'[data-contract="faixas"] tbody tr\').length)'))
        ->toBe('6', 'linhas da tabela de faixas do Anexo I');
});

it('RENDER · a 1280 a página não ganha scroll horizontal (com anexo)', function () {
    $page = abrirDas('?anexo=I&competencia=2026-10')->resize(1280, 800);
    ancorasDasEstaveis($page, 'cabecalho,kpis,conta,faixas,contador');

    expect((string) $page->script('(() => (document.documentElement.scrollWidth > document.documentElement.clientWidth) ? "rola" : "nao-rola")()'))
        ->toBe('nao-rola', 'scrollWidth vs clientWidth do documento a 1280');
});

it('A11Y · Fiscal/Das com 0 violações axe CRITICAL, nos dois estados', function (string $query, string $esperado) {
    $page = abrirDas($query)->resize(1280, 800);
    ancorasDasEstaveis($page, $esperado);

    $page->assertNoAccessibilityIssues(level: 0);
})->with([
    'sem anexo' => ['', 'cabecalho,vazio'],
    'anexo I' => ['?anexo=I&competencia=2026-10', 'cabecalho,kpis,conta,faixas,contador'],
]);
