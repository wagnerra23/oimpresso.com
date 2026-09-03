<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `Home/Index` (Visão geral, `/dashboard-legacy`).
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs` (2026-09-03) devolve pra `Home`:
 * `Telas 1 · Charter 1 · E2E 1 · Score 1 · VRT 1 · L2 0`. O `E2E 1` é da UNIÃO
 * `Browser ∪ VRT` — e a perna Browser está VAZIA: `git grep "Pages/Home/Index"
 * -- tests/Browser/` devolve ZERO. Como o eixo `a11y` do mesmo report é
 * `e2e.some((b) => b.hasAxe)` (screen-coverage-map.mjs:654), sem teste Browser
 * citando o path a tela tem `a11y=false` por construção — e tem: ela não está
 * entre os 8/211 do agregado.
 *
 * O CONTRATO já está defendido no servidor: 16 UC ↔ 18 testes em
 * `tests/Feature/Home/` (lane `dashboard-pest.yml`), e a fidelidade de PIXEL em
 * `PixelBaselineTest` via `tests/Browser/visreg-screens.json`. O que NADA
 * exercita é a tela RENDERIZADA num browser real. Este arquivo cobre só esse
 * eixo — não duplica assert de contrato nenhum.
 *
 * ── POR QUE O CASO 1 É O MAIS VALIOSO (e não é teoria) ───────────────────────
 * O gate `contrato-de-tela` lê as âncoras `data-contract` na ORDEM DO FONTE — o
 * próprio `Index.tsx:287` diz isso com todas as letras. Ler o fonte não prova
 * que a âncora CHEGA ao DOM, e a diferença já mordeu NESTA tela: o #6395
 * (`fix(dashboard): a ordenacao era INERTE e a ancora do cabecalho nunca chegou
 * ao DOM`, 2026-08-28) é exatamente esse defeito, com o gate verde o tempo todo.
 * É a classe LC-30 (correção que passa no CI inteiro e é inerte no runtime).
 *
 * O risco é estrutural, não histórico: 4 das 5 âncoras estão em COMPONENTES
 * React (`<KpiGrid data-contract="kpis">`, `<Grid data-contract="graficos">`),
 * não em elementos DOM. Elas só chegam ao HTML porque `KpiGrid.tsx:61` e
 * `layout/grid.tsx:52` espalham `{...rest}` / `{...props}` — conferido neste PR.
 * Trocar esses componentes por um que não espalhe apaga a âncora do DOM sem
 * mudar uma linha do `Index.tsx`, e nenhum gate atual enxergaria.
 *
 * ── POR QUE ESTE ARQUIVO NÃO SEMEIA DADO ─────────────────────────────────────
 * Mesma decisão (e mesma razão) do `tests/Browser/Financeiro/CaixaIndexTest.php`:
 * um caso que afirme sobre LINHA nasce vermelho quando a fixture não monta, e um
 * step permanentemente vermelho ensina o time a ignorar vermelho. Todo caso abaixo
 * vale com o tenant sem movimento (hoje) e continua valendo quando ele tiver venda.
 *
 * Diferente do Caixa num ponto MEDIDO: a causa-raiz que deixou `Conciliacao` e
 * `Cobranca` vermelhos (grupo de rotas sem `SetSessionData` → `business 0` sob o
 * auth-bridge) NÃO se aplica aqui. `/dashboard-legacy` (`routes/web.php:527`)
 * está no grupo de `routes/web.php:473`, que declara
 * `['setData','auth','SetSessionData','language','timezone','AdminSidebarMenu','CheckUserLogin']`.
 * A sessão é populada. Isso é razão pra ESPERAR verde — não recibo de que ficou:
 * o veredito sai do run, não deste comentário.
 *
 * ── O QUE PROVA ──────────────────────────────────────────────────────────────
 *   1. `UC-DASH-17` — as âncoras `data-contract` chegam ao DOM RENDERIZADO, e a
 *      ordem observada é subsequência da ordem canônica do contrato. Com controle
 *      positivo embutido (a probe devolve sentinela em vez de lista vazia quando
 *      não acha âncora nenhuma).
 *   2. `UC-DASH-18` — zero violação axe CRITICAL na tela autenticada (mesmo
 *      ratchet level 0 do `A11yAxeBrowserTest`). É o assert que move o eixo
 *      `a11y` do screen-coverage de 0 pra 1 nesta tela.
 *   3. anti-regressão do `SerieAcessivel`: todo painel de gráfico com série
 *      renderizada tem a sua tabela `sr-only` — o invariante que impede o SVG de
 *      voltar a ser desenho mudo (a alternativa textual vive em `Index.tsx:358`).
 *
 * ── O QUE **NÃO** PROVA (resíduo declarado, não maquiado) ────────────────────
 *   - a LINHA da grade e o drawer de detalhe (`GradesPainel.tsx:267`, "clique
 *     para abrir o detalhe"): exigem movimento no tenant. `GradesPainel` tem
 *     `emptyMessage`, então a lista vazia é estado legítimo — assertar clique
 *     aqui seria cravar a fixture de hoje. O caso 3 sofre do mesmo limite e por
 *     isso é escrito como CONCORDÂNCIA (`0|0` passa; `1|0` falha), nunca presença.
 *   - a troca de aba por query string (`?aba=`) — já é `UC-DASH-10`, provado no
 *     servidor por par positivo+negativo em `GradesDoPainelTest`. Repetir aqui
 *     duplicaria régua consolidada.
 *   - NÃO gera baseline de pixel: nenhum `screenshot()`. A tela JÁ está em
 *     `tests/Browser/visreg-screens.json` (âncora `Contrapartidas`) e este
 *     arquivo não toca nessa baseline.
 *   - ESCRITA: nada aqui escreve no banco. A tela é read-only sobre DINHEIRO
 *     (REGRA MESTRE, memory/proibicoes.md).
 *
 * ── TENANT ───────────────────────────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 do gate visual, o tenant FICTÍCIO do
 * `VisregTenantSeeder` no MySQL `oimpresso_test` — nunca produção. `biz=4`
 * (ROTA LIVRE) é PROIBIDO em teste (ADR 0358). O admin tem role `Admin#1`, e
 * `Gate::before` (AuthServiceProvider:34-47) concede `dashboard.data` — é o que
 * faz as 4 âncoras internas renderizarem.
 *
 * ── EXECUÇÃO (CT 100 / CI — nunca local: memory/proibicoes.md + ADR 0062) ────
 *   tailscale ssh root@ct100-mcp "docker exec oimpresso-staging ./vendor/bin/pest tests/Browser/Home/VisaoGeralIndexTest.php"
 *
 * HONESTIDADE (ADR 0108) — o que FOI e o que NÃO foi verificado antes do PR:
 *   OK  `php -l`: recibo no corpo do PR, com controle negativo (uma cópia com um
 *       parêntese a menos sai rc≠0) — lint de sintaxe, não execução de teste.
 *   NÃO o teste em si: Pest Browser é CI/CT-100 only e `vendor/` não existe nesta
 *       worktree. Só usa API já provada verde nos Browser tests deste repo:
 *       `visit` · `resize` · `assertSee` · `script` · `wait` ·
 *       `assertNoConsoleLogs` · `assertNoAccessibilityIssues`.
 * Nasce ADVISORY no workflow (ADR 0261/0275: gate novo nasce advisory; promoção a
 * enforcing = remover `continue-on-error` após 2 verdes que EXECUTARAM — `skipped`
 * lido como verde é o presence-gate de sempre).
 *
 * @see resources/js/Pages/Home/Index.tsx (tela sob teste)
 * @see resources/js/Pages/Home/Index.charter.md (Goals · Non-Goals · anti-hooks)
 * @see resources/js/Pages/Home/Index.casos.md (UC-DASH-01..18)
 * @see prototipo-ui/contrato/dashboard-visao-geral.contract.json (ordem canônica das âncoras)
 * @see tests/Browser/Financeiro/CaixaIndexTest.php (padrão espelhado)
 * @see tests/Browser/CoreScreens/A11yAxeBrowserTest.php (harness do axe)
 * @see .github/workflows/visual-regression.yml (step que invoca)
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

const VISAO_ROTA = '/dashboard-legacy';

/**
 * Ordem canônica das âncoras, na ordem do fonte — a MESMA que
 * `prototipo-ui/contrato/dashboard-visao-geral.contract.json` declara e que o
 * gate `contrato-de-tela` lê no `.tsx`. Aqui ela é conferida contra o DOM.
 */
const VISAO_ANCORAS_CANON = ['cabecalho', 'kpis', 'contrapartidas', 'graficos', 'grades'];

/**
 * Sinal de PRONTIDÃO. Ver o título não prova que o corpo montou. As 4 âncoras
 * internas vivem sob `can_dashboard_data && totals` (`Index.tsx:225`) e são
 * SÍNCRONAS; `graficos` está sob `<Deferred>` e chega depois — por isso ele NÃO
 * entra no sinal de prontidão (esperar por ele cravaria a existência de série).
 */
const VISAO_JS_PRONTO = <<<'JS'
(() => {
  const tem = (nome) => document.querySelector('[data-contract="' + nome + '"]') !== null;
  return (tem('cabecalho') && tem('kpis') && tem('contrapartidas') && tem('grades'))
    ? 'PRONTO'
    : 'ESPERANDO';
})()
JS;

/**
 * Âncoras `data-contract` na ORDEM DE DOCUMENTO. Devolve sentinela quando não acha
 * nenhuma — uma lista vazia comparada contra uma subsequência passaria vacuamente,
 * que é o presence-gate invertido.
 */
const VISAO_JS_ANCORAS = <<<'JS'
(() => {
  const nodes = [...document.querySelectorAll('[data-contract]')];
  if (nodes.length === 0) return 'NENHUMA-ANCORA';
  return nodes.map((n) => n.getAttribute('data-contract')).join('>');
})()
JS;

/**
 * CONCORDÂNCIA entre gráfico desenhado e alternativa textual. `SerieAcessivel`
 * (`Index.tsx:352`) devolve `null` com série vazia, e `GraficosVendas` devolve
 * `null` sem `charts` — logo o par certo é `svg == tabela`, e não "tabela existe".
 * `0|0` (tenant sem venda) passa; `1|0` (alguém removeu o `SerieAcessivel`) falha.
 */
const VISAO_JS_SERIE_ACESSIVEL = <<<'JS'
(() => {
  const painel = document.querySelector('[data-contract="graficos"]');
  if (!painel) return 'AUSENTE|AUSENTE';
  const svgs = painel.querySelectorAll('svg').length;
  const tabelas = painel.querySelectorAll('table.sr-only').length;
  return String(svgs > 0 ? 1 : 0) + '|' + String(tabelas > 0 ? 1 : 0);
})()
JS;

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico A11yAxe/AuthBridge/Caixa): o browser usa MySQL
    // (.env), o test process usa sqlite :memory: (phpunit.xml) — realinha pro MESMO
    // MySQL do gate pra resolver o admin do tenant. Este arquivo NÃO escreve no banco.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/**
 * Admin do tenant fictício do gate. Falha ALTO: tenant ausente não pode virar verde
 * silencioso — smoke sem render seria falso positivo (idem Caixa/Conciliacao).
 */
function visaoGeralAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder nao rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: nao da pra autenticar.');
    }

    return $admin;
}

/** Espera o React estabilizar. Mesmo idioma do `caixaEsperar`. */
function visaoGeralEsperar($page, string $js, string $esperado, string $oque): void
{
    $ultimo = null;
    for ($i = 0; $i < 24; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === $esperado) {
            return;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Nao estabilizou: {$oque} — esperado '{$esperado}', ultimo '{$ultimo}'.");
}

/** Abre a tela autenticada (auth bridge cross-process). */
function visaoGeralAbrirTela(int $largura = 1280, int $altura = 800)
{
    $admin = visaoGeralAdmin();

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode(VISAO_ROTA))
        ->resize($largura, $altura)
        ->assertSee('Visão geral');

    visaoGeralEsperar($page, VISAO_JS_PRONTO, 'PRONTO', 'as ancoras sincronas montarem');

    return $page;
}

it('UC-DASH-17 · render — as âncoras data-contract chegam ao DOM na ordem do contrato', function (int $w, int $h) {
    $page = visaoGeralAbrirTela($w, $h);

    // Âncora de TEXTO do corpo (não do breadcrumb): se cair em 403/login/erro, é este
    // assert que denuncia antes de qualquer leitura de DOM.
    $page->assertSee('Contrapartidas');

    $observada = (string) $page->script(VISAO_JS_ANCORAS);

    // Controle positivo: a probe nunca devolve vazio — ou tem âncora, ou tem sentinela.
    expect($observada)->not->toBe(
        'NENHUMA-ANCORA',
        'nenhuma [data-contract] no DOM — o gate contrato-de-tela le o FONTE e nao veria isto (classe do #6395)'
    );

    $lista = explode('>', $observada);

    // 1) As 4 síncronas chegaram. `graficos` fica fora: vive sob <Deferred> e o painel
    //    some com `charts` vazio — cobrá-lo aqui cravaria a fixture de hoje.
    foreach (['cabecalho', 'kpis', 'contrapartidas', 'grades'] as $ancora) {
        expect($lista)->toContain($ancora);
    }

    // 2) A ordem OBSERVADA é subsequência da CANÔNICA. Subsequência (e não igualdade)
    //    porque `graficos` pode legitimamente faltar; o que não pode é vir trocada.
    $canon = array_values(array_filter(
        VISAO_ANCORAS_CANON,
        static fn (string $a): bool => in_array($a, $lista, true)
    ));
    expect($lista)->toBe($canon);

    $page->assertNoConsoleLogs();
})->with([[1280, 800], [1440, 900]]);

it('UC-DASH-18 · a11y — 0 violações axe CRITICAL na Visão geral autenticada', function () {
    $page = visaoGeralAbrirTela();

    // Gate 1: a tela montou de verdade antes de auditar a11y (senão auditaríamos o login).
    $page->assertSee('Contrapartidas');

    // Gate 2: axe-core no Chromium real. level 0 = CRITICAL only — o mesmo piso de
    // ratchet do A11yAxeBrowserTest, escolhido lá em vez de allowlist de violações.
    // Subir pra level 1 (+serious) é PR follow-up, nunca carona.
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-DASH-18 · a11y — todo gráfico desenhado tem a sua alternativa textual', function () {
    $page = visaoGeralAbrirTela();
    $page->assertSee('Contrapartidas');

    // O <Deferred> resolve depois do corpo síncrono; dar-lhe a chance antes de medir.
    // Não é espera POR gráfico (isso exigiria série) — é espera pelo defer terminar.
    $page->wait(2);

    $concordancia = (string) $page->script(VISAO_JS_SERIE_ACESSIVEL);

    // `AUSENTE|AUSENTE` = sem painel de gráficos (charts vazio): estado legítimo no
    // tenant sem venda. `1|1` = gráfico + tabela sr-only. `1|0` = o SVG voltou a ser
    // desenho mudo — quem não enxerga não recebe número nenhum.
    expect($concordancia)->toBeIn(['AUSENTE|AUSENTE', '0|0', '1|1']);
});
