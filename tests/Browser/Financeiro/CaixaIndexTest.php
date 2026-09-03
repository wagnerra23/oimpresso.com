<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `Financeiro/Caixa/Index` (Caixa do turno).
 *
 * ── O BURACO QUE ISTO FECHA (medido, nao suposto) ────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Financeiro/Caixa/Index` (2026-09-03):
 * trio OK · scorecard OK · RUNBOOK OK · LIGADA (charter live · sinal prod-flags) ·
 * **5/5 UC citados por teste** — e `e2e (Browser): nenhum teste Browser cita o path`.
 * O CONTRATO ja esta defendido no servidor (`CaixaControllerTest`, lane required
 * `PHP / Pest (Financeiro · MySQL)`) e NADA exercita a tela RENDERIZADA. Este arquivo
 * cobre so esse eixo — nao duplica assert de contrato nenhum.
 *
 * ── POR QUE ESTE ARQUIVO NAO SEMEIA DADO (a decisao que mais importa aqui) ────
 * O irmao `ConciliacaoIndexTest` (mesmo grupo de rotas, mesmo harness) semeia fixture e
 * afirma sobre a LINHA. Ele esta VERMELHO em CI desde que entrou — 6 de 6 casos,
 * `Nao estabilizou: as 2 linhas de fixture montarem — esperado '2', ultimo '0'`
 * (run 33747138995, step "E2E de render · Financeiro/Conciliacao"). O vermelho e
 * invisivel porque `continue-on-error: true` faz o step publicar `conclusion=success`
 * (o `outcome` e que fica `failure`) — ler a conclusion e o presence-gate de sempre.
 *
 * CAUSA MEDIDA (nao lida): as telas deste grupo renderizam com **business 0** sob o
 * auth-bridge do gate.
 *   1. o grupo `Modules/Financeiro/Routes/web.php:57` NAO tem `SetSessionData` —
 *      compare com os grupos das linhas 35 e 45, que tem;
 *   2. `/_visreg-login` (routes/web.php:316) faz `session()->forget(['user', ...])` e
 *      redireciona direto pro alvo, entao ninguem reconstroi o bloco `user`;
 *   3. `CaixaController:50` e `ConciliacaoController:54` leem `(int) session('user.business_id')`
 *      SEM fallback, enquanto `UnificadoController:62` tem `?: $request->user()?->business_id`
 *      — e e por isso que `FinanceiroFlowBaselineTest` (12 passed) enxerga linha e o
 *      Conciliacao nao. NAO e cross-process de DB: o `ConformanceProbesTest` escreve fixture
 *      do processo de teste (`Vehicle`/`ServiceOrder::create`) e ve o dado renderizado — passa
 *      no MESMO run. Em producao o sintoma nao aparece porque a sessao ja vem populada por
 *      requests anteriores em grupos que tem o middleware.
 *
 * Logo: um caso que afirmasse sobre LINHA aqui nasceria VERMELHO. Um segundo step
 * permanentemente vermelho ensina o time a ignorar vermelho — o oposto do objetivo.
 * Todo caso abaixo e INDEPENDENTE DE DADO: vale com a lista vazia (hoje) e continua valendo
 * no dia em que a sessao for resolvida e a lista voltar a ter turno.
 *
 * ── O QUE PROVA (e que Pest de contrato nao alcanca) ─────────────────────────
 *   1. a tela monta autenticada em 1280 (Larissa/ROTA LIVRE) e 1440 (Eliana) sem erro de
 *      console, com o banner anti-confusao do charter — `UC-FCX-01` no eixo RENDER;
 *   2. `?limit` fora da faixa chega ao USUARIO ja clampado ("limite 10" / "limite 200") —
 *      `UC-FCX-03`; o contrato prova a prop, isto prova o texto que a pessoa le;
 *   3. o pill "Abertos" filtra por PARTIAL RELOAD (`router.visit only:['caixas','filters']`,
 *      D-14): a URL ganha `?status=open`, o estado ativo troca de pill, e a sentinela em
 *      `window` SOBREVIVE — logo nao houve reload de pagina. `UC-FCX-02` no eixo RENDER;
 *   4. Non-Goal + anti-hook do charter: ZERO `<form>` e ZERO botao de mutacao de caixa
 *      (Abrir/Fechar/Editar/Sangria/Suprimento/Reforco) dentro do `<main>`. Com controle
 *      positivo embutido (`pills=3`) pra o guard nao ser vacuo;
 *   5. o KPI e o rodape CONCORDAM com a lista: "Saldo nos ultimos N" e "Mostrando ultimos N"
 *      contra a contagem real de `tbody tr` — invariante self-consistent, sobrevive ao dado
 *      voltar e falha se o KPI passar a mentir sobre a tabela;
 *   6. zero violacao axe CRITICAL (mesmo ratchet level 0 do `A11yAxeBrowserTest`).
 *
 * ── O QUE **NAO** PROVA (residuo declarado, nao maquiado) ────────────────────
 *   - A ARITMETICA do "Saldo nos ultimos N" (SUM credit - debit dos rows exibidos, Goal do
 *     charter). E o assert de maior valor da tela e esta BLOQUEADO pela causa acima: com
 *     business 0 nao ha linha pra somar. Quando a sessao for resolvida, a probe pronta e
 *     comparar o KPI com a soma das colunas Entradas/Saidas do DOM. Registrado, nao escondido.
 *   - a cor verde/vermelha de Entradas/Saidas, o badge Aberto/Fechado e a affordancia POR
 *     LINHA (incl. o botao "Lancar agora" do ADR 0183) — todos exigem linha renderizada.
 *   - ESCRITA: nada aqui clica em "Lancar agora". Esta tela e READ-ONLY sobre DINHEIRO
 *     (REGRA MESTRE, memory/proibicoes.md) e o teste nao escreve UMA linha no banco.
 *   - NAO gera baseline de pixel: nenhum `screenshot()`/`assertScreenshotMatches()`. A tela
 *     nao esta em `tests/Browser/visreg-screens.json` e este PR nao a coloca la.
 *
 * ── TENANT (convencao DESTE gate) ────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 do gate visual: o tenant FICTICIO do
 * `VisregTenantSeeder` no MySQL `oimpresso_test`, NAO producao. `biz=4` (ROTA LIVRE) e
 * PROIBIDO em teste (ADR 0358) ainda que seja o tenant que usa esta tela em prod. O 98 daria
 * empty-state por construcao (e o tenant-vazio). Mesmo idioma do `AuthBridgeSmokeTest`.
 *
 * ── EXECUCAO (CT 100 / CI — nunca local: memory/proibicoes.md + ADR 0062) ────
 *   tailscale ssh root@ct100-mcp "docker exec oimpresso-staging ./vendor/bin/pest tests/Browser/Financeiro/CaixaIndexTest.php"
 *
 * HONESTIDADE (ADR 0108) — o que FOI e o que NAO foi verificado antes do PR:
 *   OK  `php -l` (PHP 8.4.21): `No syntax errors detected`. E lint de sintaxe, nao execucao
 *       de teste — nao cai no bloqueio Tier 0 de rodar Pest/PHPStan local (memory/proibicoes.md).
 *       Provado NAO-CARIMBO por controle negativo: uma copia com um `)` a menos sai rc=255.
 *   OK  as 6 probes JS foram extraidas DESTE arquivo e rodadas em jsdom contra DOMs que
 *       espelham o `.tsx` — 23 checagens, incl. 4 controles POSITIVOS (o guard morde
 *       "Abrir caixa", "Sangria" e `<form>`; a concordancia morde o rodape que mente) e 6
 *       NEGATIVOS (probe sem alvo devolve sentinela, nunca verde). jsdom nao tem layout
 *       engine: a cor computada dos pills so o Chromium responde — la o jsdom prova a
 *       LOGICA DE SELECAO e a troca, nao a cor real.
 *   NAO o teste em si: `vendor/` nao existe nesta worktree e Pest Browser e CI/CT-100 only.
 * So usa API ja provada verde nos Browser tests deste repo: `visit` · `resize` · `assertSee` ·
 * `assertNoConsoleLogs` · `script` · `wait` · `assertNoAccessibilityIssues`. O clique via
 * `element.click()` dentro do `script()` e o idioma do `FinanceiroFlowBaselineTest:126` e evita
 * o hover que um clique de driver deixaria no pill — hover mudaria a cor computada que o caso 3
 * compara. `OtelHelper::spanBiz` (usado so por ESTE controller entre as telas do gate) foi
 * conferido: `span()` e pass-through com `otel.enabled=false`, e o workflow nao seta
 * `OTEL_ENABLED` — logo nao ha risco de fatal pela ext ausente.
 * Nasce ADVISORY no workflow (ADR 0261/0275: gate novo nasce advisory, 2 verdes, enforcing).
 *
 * @see resources/js/Pages/Financeiro/Caixa/Index.tsx (tela sob teste)
 * @see resources/js/Pages/Financeiro/Caixa/Index.charter.md (Goals · Non-Goals · anti-hooks)
 * @see resources/js/Pages/Financeiro/Caixa/Index.casos.md (UC-FCX-01..05)
 * @see tests/Browser/CoreScreens/A11yAxeBrowserTest.php (harness espelhado)
 * @see .github/workflows/visual-regression.yml (step que invoca)
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

const CAIXA_ROTA = '/financeiro/caixa';

/** Sinal de PRONTIDAO. Ver o cabecalho nao prova que o corpo montou, e esperar por uma
 *  CONTAGEM de linhas cravaria a lista vazia de hoje no assert — o rodape existe com ou sem
 *  turno, entao ele e o sinal independente de dado. */
const CAIXA_JS_PRONTO = <<<'JS'
(() => {
  const rodape = [...document.querySelectorAll('span')]
    .find((s) => ((s.textContent || '').replace(/\s+/g, ' ').trim()).startsWith('Mostrando'));
  return rodape ? 'PRONTO' : 'ESPERANDO';
})()
JS;

/** Triple `linhas|N do KPI|N do rodape`. Ancorado em TEXTO (prefixo ASCII), nunca em classe
 *  CSS (L-24). O primeiro numero do card e o N de "Saldo nos ultimos <N> mostrados". */
const CAIXA_JS_CONCORDANCIA = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const num = (s) => { const m = s.match(/(\d+)/); return m ? m[1] : 'SEM-NUMERO'; };
  const linhas = document.querySelectorAll('tbody tr').length;
  const kpi = [...document.querySelectorAll('div')].find((d) => txt(d).startsWith('Saldo nos '));
  const rodape = [...document.querySelectorAll('span')].find((s) => txt(s).startsWith('Mostrando'));
  if (!kpi) return 'KPI-SALDO-AUSENTE';
  if (!rodape) return 'RODAPE-AUSENTE';
  return String(linhas) + '|' + num(txt(kpi)) + '|' + num(txt(rodape));
})()
JS;

/** `N|limite` do rodape "Mostrando ultimos <N> (limite <L>)". So o L e assertado — o N
 *  depende de haver turno, e este arquivo e independente de dado por desenho. */
const CAIXA_JS_RODAPE = <<<'JS'
(() => {
  const alvo = [...document.querySelectorAll('span')]
    .find((s) => ((s.textContent || '').replace(/\s+/g, ' ').trim()).startsWith('Mostrando'));
  if (!alvo) return 'RODAPE-AUSENTE';
  const t = (alvo.textContent || '').replace(/\s+/g, ' ').trim();
  const m = t.match(/(\d+)\D+(\d+)/);
  return m ? (m[1] + '|' + m[2]) : ('SEM-NUMEROS:' + t);
})()
JS;

/** Cor de fundo COMPUTADA dos pills "Todos" e "Abertos". Comparo as duas em vez de cravar
 *  um literal: continua valido se o token for retunado, e falha se o estado ativo parar de
 *  trocar. So o Chromium responde `getComputedStyle` — jsdom nao tem layout engine. */
const CAIXA_JS_PILLS = <<<'JS'
(() => {
  const achar = (rot) => [...document.querySelectorAll('button')]
    .find((b) => (b.textContent || '').replace(/\s+/g, ' ').trim() === rot);
  const cor = (rot) => { const el = achar(rot); return el ? getComputedStyle(el).backgroundColor : 'PILL-AUSENTE'; };
  return cor('Todos') + ' :: ' + cor('Abertos');
})()
JS;

/** Marca a sentinela e clica no pill. Sentinela em `window` morre num reload de pagina e
 *  sobrevive a um partial reload do Inertia — e a prova do `only: ['caixas','filters']`. */
const CAIXA_JS_CLICAR_ABERTOS = <<<'JS'
(() => {
  const el = [...document.querySelectorAll('button')]
    .find((b) => (b.textContent || '').replace(/\s+/g, ' ').trim() === 'Abertos');
  if (!el) return 'PILL-AUSENTE';
  window.__caixaSentinela = 'vivo';
  el.click();
  return 'OK';
})()
JS;

/** Guard do Non-Goal: `<form>` e botao de mutacao dentro do `<main>` (a nav primaria fica
 *  FORA dele — contrato provado pelo A11yAxeBrowserTest). `pills=3` e o CONTROLE POSITIVO:
 *  sem ele, um probe que nao achasse botao nenhum passaria por "nada proibido". */
const CAIXA_JS_AFFORDANCIA = <<<'JS'
(() => {
  const main = document.querySelector('main');
  if (!main) return 'MAIN-AUSENTE';
  const rotulos = [...main.querySelectorAll('button')]
    .map((b) => (b.textContent || '').replace(/\s+/g, ' ').trim())
    .filter((s) => s.length > 0);
  const proibidos = rotulos.filter((s) => /(abrir|fechar|editar|excluir|remover|sangria|suprimento|refor)/i.test(s));
  const pills = rotulos.filter((s) => s === 'Todos' || s === 'Abertos' || s === 'Fechados').length;
  return 'forms=' + main.querySelectorAll('form').length
    + ' proibidos=' + (proibidos.join(',') || 'nenhum')
    + ' pills=' + pills;
})()
JS;

beforeEach(function () {
    // CROSS-PROCESS DB (identico A11yAxe/AuthBridge): o browser usa MySQL (.env), o test
    // process usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL do gate pra
    // resolver o admin do tenant. Este arquivo NAO escreve nada no banco.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/** Admin do tenant ficticio do gate. Falha ALTO: tenant ausente nao pode virar verde
 *  silencioso — smoke sem render seria falso positivo (idem ConciliacaoIndexTest). */
function caixaAdmin(): User
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

/** Abre a tela autenticada (auth bridge cross-process). `$query` entra na rota alvo. */
function caixaAbrirTela(string $query = '', int $largura = 1280, int $altura = 800)
{
    $admin = caixaAdmin();

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode(CAIXA_ROTA . $query))
        ->resize($largura, $altura)
        ->assertSee('Caixa do turno');

    // Todo caso abaixo le o DOM; esperar a prontidao AQUI, uma vez, evita repetir a espera
    // (e evita o flake de ler antes do commit do React) em cada um deles.
    caixaEsperar($page, CAIXA_JS_PRONTO, 'PRONTO', 'o corpo da tela montar');

    return $page;
}

/** Espera o React estabilizar. Ver o cabecalho nao significa que o corpo montou — mesma
 *  razao do `aguardarAlvoVisual` do FinanceiroFlowBaselineTest. */
function caixaEsperar($page, string $js, string $esperado, string $oque): void
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

it('UC-FCX-01 · render — a tela monta autenticada com o banner e os KPI', function (int $w, int $h) {
    $page = caixaAbrirTela('', $w, $h);

    // Ancora do CORPO da pagina (o titulo tambem vive no breadcrumb/shell): se cair em
    // 403/login/erro, este assert e o que denuncia.
    $page->assertSee('Por que essa tela existe?');

    // UX target do charter: "Diferenciacao Fluxo vs Caixa entendida em <= 30s" — o banner
    // anti-confusao existe e cita as duas telas.
    $page->assertSee('Caixa do turno (POS)');

    // Goal do charter: 4 KPI cards. O 4o tem rotulo dinamico ("Saldo nos ultimos N
    // mostrados") e e assertado pelo invariante de concordancia, abaixo.
    $page->assertSee('Caixas registrados')
        ->assertSee('Caixas abertos agora')
        ->assertSee('Soma de fechamentos');

    // Goal do charter: pill segmented control Todos | Abertos | Fechados.
    $page->assertSee('Todos')->assertSee('Abertos')->assertSee('Fechados');

    $page->assertNoConsoleLogs();
})->with([[1280, 800], [1440, 900]]);

it('UC-FCX-03 · render — ?limit fora da faixa chega ao usuario ja clampado', function (string $query, string $limiteEsperado) {
    // O `CaixaControllerTest` prova a prop `filters.limit`; aqui o que se prova e o TEXTO que
    // a pessoa le no rodape. Deep-link com limite absurdo nao pode derrubar a tela nem mentir
    // sobre o limite aplicado (aceite do UC: "ajustado para a borda", nunca 500).
    $page = caixaAbrirTela($query);

    // A probe devolve `N|limite` quando acha o rodape e uma SENTINELA sem `|` quando nao
    // acha — checar o separador antes do explode faz a falha dizer o que houve, em vez de
    // estourar num indice indefinido.
    $rodape = (string) $page->script(CAIXA_JS_RODAPE);
    expect($rodape)->toContain('|');

    [, $limite] = explode('|', $rodape);
    expect($limite)->toBe($limiteEsperado);
    $page->assertNoConsoleLogs();
})->with([['?limit=5', '10'], ['?limit=9999', '200']]);

it('UC-FCX-02 · render — o pill filtra por partial reload, sem recarregar a pagina', function () {
    $page = caixaAbrirTela();

    // CONTROLE POSITIVO: os dois estados sao visualmente distintos ANTES do clique. Sem isto,
    // o assert de troca abaixo passaria por nada (duas cores iguais trocariam consigo mesmas).
    [$todosAntes, $abertosAntes] = explode(' :: ', (string) $page->script(CAIXA_JS_PILLS));
    expect($todosAntes)->not->toContain('AUSENTE')
        ->and($abertosAntes)->not->toContain('AUSENTE')
        ->and($todosAntes)->not->toBe($abertosAntes);

    expect($page->script(CAIXA_JS_CLICAR_ABERTOS))->toBe('OK');

    // Devolve 'SIM' quando satisfeito e a query CRUA quando nao — assim a falha mostra o que
    // a URL virou, em vez de so dizer que nao bateu com um literal.
    caixaEsperar(
        $page,
        "(() => (window.location.search.indexOf('status=open') >= 0 ? 'SIM' : ('AINDA:' + window.location.search)))()",
        'SIM',
        'a URL ganhar status=open',
    );

    // A sentinela sobreviveu => foi partial reload do Inertia, nao `location.reload()`. E a
    // metade de UI do D-14 (`only: ['caixas','filters']`) que o teste de contrato nao ve.
    expect($page->script("(() => String(window.__caixaSentinela || 'PERDIDO'))()"))->toBe('vivo');

    // E o estado ativo TROCOU de pill — prova que `filters` voltou no partial e re-renderizou.
    [$todosDepois, $abertosDepois] = explode(' :: ', (string) $page->script(CAIXA_JS_PILLS));
    expect($todosDepois)->toBe($abertosAntes)
        ->and($abertosDepois)->toBe($todosAntes);

    expect($page->script('(() => window.location.pathname)()'))->toBe(CAIXA_ROTA);
    $page->assertNoConsoleLogs();
});

it('render — a tela nao oferece afordancia de mutacao de caixa (Non-Goal do charter)', function () {
    $page = caixaAbrirTela();

    // ARMADILHA DE COPY, e ela e o controle NEGATIVO deste guard: o rodape da tela contem
    // literalmente o verbo proibido ("Fechar caixa registradora"), apontando pro POS. Um guard
    // que procurasse o TEXTO da pagina morderia aqui — por isso o escopo e `<button>`.
    $page->assertSee('Fechar caixa registradora');

    // Non-Goals do charter: nao abre, nao fecha, nao edita valor. Anti-hooks: "aparecer botao
    // de mutacao" e "edit inline em closing_amount" = drift pra F6 Hard, que exige ADR.
    // `pills=3` e o controle POSITIVO (o probe LE botoes de verdade). O botao "Lancar agora"
    // (ADR 0183 PR C) e excecao SANCIONADA, nao casa o predicado e so aparece com turno.
    expect($page->script(CAIXA_JS_AFFORDANCIA))->toBe('forms=0 proibidos=nenhum pills=3');
});

it('render — o KPI e o rodape concordam com a lista (o KPI nao mente sobre a tabela)', function () {
    $page = caixaAbrirTela();

    $triple = (string) $page->script(CAIXA_JS_CONCORDANCIA);
    expect($triple)->toContain('|');

    [$linhas] = explode('|', $triple);

    // Invariante SELF-CONSISTENT: nao crava contagem nenhuma, so exige que os tres numeros
    // sejam o mesmo. Vale com a lista vazia (hoje) e continua valendo quando ela voltar a ter
    // turno; o que NAO sobrevive e o KPI/rodape passar a contar coisa diferente da tabela.
    expect($triple)->toBe($linhas . '|' . $linhas . '|' . $linhas);
});

it('a11y — zero violacao axe CRITICAL na tela renderizada', function () {
    // Mesmo ratchet do A11yAxeBrowserTest: level 0 = CRITICAL only (piso honesto; `serious`
    // inclui contraste, fora do escopo deste PR). Audita o chrome real desta tela — banner,
    // grid de KPI, pills e empty-state — que nenhum outro gate renderiza hoje.
    caixaAbrirTela()->assertNoAccessibilityIssues(level: 0);
});
