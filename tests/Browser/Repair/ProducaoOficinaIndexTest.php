<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `Repair/ProducaoOficina/Index` (kanban de producao).
 *
 * ── O BURACO QUE ISTO FECHA (medido, nao suposto) ────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs` em `origin/main` e0f2b79c86 (2026-09-05):
 *
 *     Modulo              Telas  Charter  E2E  Score   VRT   L2
 *     Financeiro             21       21    5     21     2    1
 *     Ponto                  21       21   11     20    11    1
 *     Repair                 14       14    0     13     0    0
 *
 * O Repair era o UNICO modulo grande zerado nos tres eixos (E2E, VRT, a11y) — e nao por
 * falta de tela: sao 14, todas com charter. Este arquivo e o primeiro E2E do modulo.
 *
 * ── O QUE ESTE ARQUIVO NAO FAZ (e por que) ───────────────────────────────────
 * NAO duplica assert de contrato. O servidor desta tela JA esta defendido por 5 suites em
 * `Modules/Repair/Tests/Feature/` (ProducaoOficinaTest · ...RefactorTest · ...VendaDerivada
 * ExpandedTest · ...Onda5CompartilharTest · ...FaseBVendaDerivadaCardTest) — 44 casos que
 * provam props, shape do payload, mapping reverso do /move, escopo Tier 0 e vocabulario
 * shared. O que NENHUMA delas faz e RENDERIZAR a tela num browser. Este arquivo cobre so
 * esse eixo (§5 2026-07-09 "duplica regua consolidada").
 *
 * NAO gera baseline de pixel: zero `screenshot()` / `assertScreenshotMatches()`. A tela nao
 * esta em `tests/Browser/visreg-screens.json` e este arquivo NAO a coloca la — um source no
 * manifesto sem o `.snap` versionado faz `VisregThreshold` chamar `test()->fail("baseline
 * contratada ausente")` (Support/VisregThreshold.php:189-196), ou seja nasceria VERMELHO.
 * A baseline se gera no `workflow_dispatch` de visual-regression.yml (input `screens`) e a
 * imagem nova e aprovacao [W] (gate F1.5) — nao e ato deste PR.
 *
 * ── POR QUE TODO CASO E INDEPENDENTE DE DADO ─────────────────────────────────
 * O `VisregTenantSeeder` NAO semeia `repair_statuses` nem `job_sheets` (grep: zero
 * ocorrencia), entao o `ProducaoOficinaController::index()` cai nos dois early-returns de
 * `renderMock()` e a tela renderiza `data_source: 'mock'` — 17 OS deterministicas em 5
 * colunas. MESMO ASSIM nenhum caso abaixo crava contagem: no dia em que alguem semear
 * status + OS a tela vira `live`, e em `live` o `jobSheetToCard()` emite `slot => null` em
 * TODO card (Controller:315-316), o que zera qualquer filtro de slot. Um assert de numero
 * nasceria verde hoje e vermelho nesse dia, sem regressao nenhuma ter acontecido. Logo os
 * casos afirmam ESTRUTURA e TRANSICAO DE FORMA — nunca quantidade.
 *
 * ── O QUE PROVA (e que Pest de contrato nao alcanca) ─────────────────────────
 *   1. UC-RPOE-01 — o kanban monta autenticado em 1280 (quirk Larissa do charter) e 1440,
 *      com as 5 colunas NA ORDEM do charter e zero erro de console;
 *   2. UC-RPOE-02 — cabe em 1280 sem scroll horizontal. E o UX Target mais dificil da tela
 *      (`grid-cols-5` num monitor estreito) e NENHUM grep no `.tsx` responde por ele;
 *   3. UC-RPOE-03 — o chip de `slot`/`area` liga o contador comparativo e o "Limpar
 *      filtros", e o "Limpar filtros" restaura. Filtro client-side (`useMemo`) que nenhum
 *      teste de servidor alcanca;
 *   4. UC-RPOE-04 — Non-Goal do charter: zero modal (`<dialog>`/`[role=dialog]`) e zero
 *      `<form>` no `<main>`. Com controle positivo (5 sections) pra o guard nao ser vacuo;
 *   5. UC-RPOE-05 — zero violacao axe CRITICAL (mesmo ratchet level 0 do A11yAxeBrowserTest);
 *   6. UC-RPOE-06 — CAPTURA do defeito de teclado medido (ver abaixo).
 *
 * ── UC-RPOE-06 E CARACTERIZACAO, NAO CONTRATO SATISFEITO ──────────────────────
 * Medido no `.tsx` em origin/main e0f2b79c86: o card e `<article draggable onClick>`
 * (Index.tsx:418-428) com `role=` 0 · `tabIndex` 0 · `onKeyDown` 0 · `aria-keyshortcuts` 0.
 * Logo o card nao tabula, nao ativa por Enter/Espaco, nao se anuncia, e o arrasto entre
 * colunas nao tem alternativa por teclado (WCAG 2.1.1 e 2.5.7).
 *
 * O axe NAO pega essa classe: nao existe regra pra "handler de clique em elemento nao
 * interativo" (o axe le o DOM, e um `<article>` sem `role` e indistinguivel de um
 * `<article>` decorativo), e o piso do gate e level 0 = CRITICAL only. A sonda e ADITIVA.
 *
 * O caso fica VERDE ENQUANTO O DEFEITO EXISTIR. Quando o DS entregar card acessivel, ele
 * fica VERMELHO — e esse vermelho e o sinal de sucesso. O conserto e INVERTER o esperado
 * (`operaveis === total`), NUNCA deletar o caso. Consertar o DS nao e escopo desta sessao;
 * capturar em teste e.
 *
 * ── O QUE **NAO** PROVA (residuo declarado, nao maquiado) ────────────────────
 *   - o DRAG-AND-DROP de verdade. HTML5 drag nativo nao e dirigivel pelas APIs deste
 *     harness (`script()` nao sintetiza um DataTransfer que o React aceite ponta-a-ponta).
 *     O POST `/move`, o mapping reverso e o escopo `business_id` JA estao provados por
 *     `ProducaoOficinaTest` ("move endpoint respeita business_id" / "rejeita coluna
 *     invalida") e `ProducaoOficinaRefactorTest`. Aqui ficaria pior e duplicado.
 *   - o DRAWER e o card de venda derivada (ADR 0192 / FASE B). Abrir o drawer exige clicar
 *     num card, e o clique de card e justamente o que o UC-RPOE-06 documenta como quebrado
 *     por teclado; o conteudo do drawer ja tem 15 GUARDs em
 *     `ProducaoOficinaFaseBVendaDerivadaCardTest`. Fica pra PR follow-up junto do conserto
 *     de a11y — quando o card virar controle de verdade, um `click()` de driver passa a ser
 *     a interacao honesta.
 *   - a COR do chip ativo / do banner ambar de `pending_approval`. Exige baseline de pixel,
 *     que este PR nao cria (ver acima).
 *   - ESCRITA: nada aqui muda uma linha no banco. A tela e read-mostly e o teste nao chama
 *     `/move`.
 *
 * ── TENANT (convencao DESTE gate) ────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 do gate visual: o tenant FICTICIO do
 * `VisregTenantSeeder` no MySQL `oimpresso_test`, NAO producao. `biz=4` (ROTA LIVRE) e
 * PROIBIDO em teste ([ADR 0358](memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
 * Mesmo idioma do `AuthBridgeSmokeTest` e do `Financeiro/CaixaIndexTest`.
 *
 * A rota ABRE pra esse tenant: o grupo `Modules/Repair/Routes/web.php:9` tem
 * `SetSessionData` (logo `session('user.business_id')` e populado — este arquivo NAO sofre
 * o business-0 documentado no `CaixaIndexTest`), e nao ha middleware de modulo/permissao no
 * grupo nem checagem no `index()`. O ponto cego de MODULO declarado no VisregTenantSeeder
 * (camada 1 nao semeada) afeta a SIDEBAR, nao a abertura da pagina.
 *
 * ── EXECUCAO ─────────────────────────────────────────────────────────────────
 * CI apenas (`.github/workflows/visual-regression.yml`). MEDIDO em 2026-09-05: o container
 * `oimpresso-staging` do CT 100 tem `vendor/pestphp/pest-plugin-browser` mas NAO tem node,
 * npx, `~/.cache/ms-playwright` nem binario chromium — logo Pest Browser NAO roda la, e o
 * checkout de la esta velho (nao tinha sequer o CaixaIndexTest). Nao repito aqui o comando
 * `docker exec ... pest` que outros docblocks sugerem: eu tentei e ele nao executa.
 *
 * HONESTIDADE (ADR 0108) — o que FOI e o que NAO foi verificado antes do PR:
 *   OK  `php -l` no container CT 100 (PHP 8.4.22): `No syntax errors detected`, rc=0. E lint
 *       de SINTAXE, nao execucao de teste — nao cai no bloqueio Tier 0 de rodar Pest/PHPStan
 *       local (memory/proibicoes.md). Provado NAO-CARIMBO por controle negativo: uma copia
 *       com `expect(;` anexado (mutacao conferida — o arquivo cresceu 10 bytes, senao o
 *       controle seria invalido) sai `Parse error ... unexpected token ";"`, rc=255.
 *   OK  as 9 sondas JS foram EXTRAIDAS deste arquivo (parseadas do nowdoc, nunca retypadas —
 *       copia diverge da fonte no primeiro edit) e rodadas em jsdom 25.0.1 contra DOMs que
 *       espelham o `.tsx`: 33 checagens, 14 delas NEGATIVAS. Entre elas o controle de
 *       PROVENIENCIA do UC-RPOE-06 — um DOM com o card ja consertado (`role="button"` +
 *       `tabindex="0"` + `aria-keyshortcuts`) faz a sonda devolver `17|17|17`, provando que o
 *       caso VAI ficar vermelho no dia do conserto, como projetado. Harness versionado em
 *       `scripts/tests/producao-oficina-probes.test.mjs` (roda sob demanda, nao esta em CI).
 *       jsdom nao tem layout engine: a sonda de OVERFLOW so o Chromium responde de verdade —
 *       la ela prova a LOGICA de comparacao, nao a largura real.
 *   NAO o teste em si: Pest Browser e CI-only (medido acima).
 * So usa API ja provada verde nos Browser tests deste repo: `visit` · `resize` · `assertSee`
 * · `assertNoConsoleLogs` · `script` · `wait` · `assertNoAccessibilityIssues`. O clique via
 * `element.click()` dentro do `script()` e o idioma do `FinanceiroFlowBaselineTest:126` e do
 * `CaixaIndexTest` — evita o hover que um clique de driver deixaria no chip.
 * Nasce ADVISORY no workflow (ADR 0261/0275: gate novo nasce advisory, 2 verdes, enforcing).
 *
 * @see resources/js/Pages/Repair/ProducaoOficina/Index.tsx (tela sob teste)
 * @see resources/js/Pages/Repair/ProducaoOficina/Index.charter.md (Goals · Non-Goals · UX Targets)
 * @see resources/js/Pages/Repair/ProducaoOficina/Index.casos.md (UC-RPOE-01..06)
 * @see Modules/Repair/Http/Controllers/ProducaoOficinaController.php (props + fallback mock)
 * @see tests/Browser/Financeiro/CaixaIndexTest.php (harness espelhado)
 * @see .github/workflows/visual-regression.yml (step que invoca)
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

const PRODUCAO_ROTA = '/repair/producao-oficina';

/** Sinal de PRONTIDAO. Ver o shell nao prova que o kanban montou; as 5 colunas existem com
 *  ou sem card (coluna vazia renderiza "Nenhuma OS"), entao sao o sinal independente de dado. */
const PRODUCAO_JS_PRONTO = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const gridKanban = () => {
    let melhor = null; let max = 0;
    for (const el of document.querySelectorAll('*')) {
      let n = 0;
      for (const c of el.children) { if (c.tagName === 'SECTION') n++; }
      if (n > max) { max = n; melhor = el; }
    }
    return melhor;
  };
  const grid = gridKanban();
  if (!grid) return 'ESPERANDO';
  const cols = [...grid.children].filter((c) => c.tagName === 'SECTION');
  if (cols.length < 5) return 'ESPERANDO';
  return cols.every((c) => txt(c.querySelector('h3')).length > 0) ? 'PRONTO' : 'ESPERANDO';
})()
JS;

/** `N|label1|label2|...` das colunas em ORDEM DE DOM. Pega tanto a perda/adicao de coluna
 *  (o N muda) quanto a reordenacao (a juncao muda) num assert so. */
const PRODUCAO_JS_COLUNAS = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const gridKanban = () => {
    let melhor = null; let max = 0;
    for (const el of document.querySelectorAll('*')) {
      let n = 0;
      for (const c of el.children) { if (c.tagName === 'SECTION') n++; }
      if (n > max) { max = n; melhor = el; }
    }
    return melhor;
  };
  const grid = gridKanban();
  if (!grid) return 'GRID-AUSENTE';
  const cols = [...grid.children].filter((c) => c.tagName === 'SECTION');
  return String(cols.length) + '|' + cols.map((c) => txt(c.querySelector('h3'))).join('|');
})()
JS;

/** `scrollWidth|clientWidth` do documento. Overflow horizontal = scrollWidth > clientWidth.
 *  So o Chromium responde de verdade — jsdom nao tem layout engine. */
const PRODUCAO_JS_OVERFLOW = <<<'JS'
(() => {
  const d = document.documentElement;
  return String(d.scrollWidth) + '|' + String(d.clientWidth);
})()
JS;

/** Forma do contador: SIMPLES ("N OS · M aguardando aprovacao") vs COMPARATIVO
 *  ("X de N OS · Y de M aguardando aprovacao"). Ancorado no TEXTO, nunca em classe. */
const PRODUCAO_JS_CONTADOR = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const candidatos = [...document.querySelectorAll('div, span, p')]
    .filter((el) => txt(el).includes('aguardando aprova'));
  if (candidatos.length === 0) return 'CONTADOR-AUSENTE';
  const alvo = candidatos[candidatos.length - 1];
  const t = txt(alvo);
  return /\bde\s+\d+\s+OS\b/.test(t) ? 'COMPARATIVO' : 'SIMPLES';
})()
JS;

/** Clica o PRIMEIRO chip que nao seja "Todos", achando-o pela estrutura relativa ao proprio
 *  "Todos" — assim funciona pra QUALQUER `slot_config` (o default e Box B1..B4 + Elevador
 *  E1..E2, mas cada vertical configura o seu via `business.repair_settings`). */
const PRODUCAO_JS_CLICAR_CHIP = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const todos = [...document.querySelectorAll('button')].find((b) => txt(b) === 'Todos');
  if (!todos || !todos.parentElement) return 'CHIP-TODOS-AUSENTE';
  const irmaos = [...todos.parentElement.querySelectorAll('button')];
  const alvo = irmaos.find((b) => txt(b) !== 'Todos');
  if (!alvo) return 'CHIP-OPCAO-AUSENTE';
  alvo.click();
  return 'CLICOU:' + txt(alvo);
})()
JS;

/** Presenca (e clique) do "Limpar filtros" — o botao so existe com filtro ativo. */
const PRODUCAO_JS_LIMPAR = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const alvo = [...document.querySelectorAll('button')].find((b) => txt(b) === 'Limpar filtros');
  if (!alvo) return 'LIMPAR-AUSENTE';
  alvo.click();
  return 'CLICOU';
})()
JS;

/** So a PRESENCA do "Limpar filtros", sem clicar (usado antes de ativar o filtro). */
const PRODUCAO_JS_LIMPAR_EXISTE = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  return [...document.querySelectorAll('button')].some((b) => txt(b) === 'Limpar filtros')
    ? 'EXISTE' : 'AUSENTE';
})()
JS;

/** `total|operaveis|keyshortcuts` dos cards do kanban.
 *  - total       = `<article draggable>` dentro das colunas (o card do charter);
 *  - operaveis   = quantos sao alcancaveis por teclado (tag focavel OU tabindex >= 0 OU
 *                  role interativo). E a medida do UC-RPOE-06;
 *  - keyshortcuts= quantos elementos da PAGINA declaram `aria-keyshortcuts` (alternativa de
 *                  teclado pro arrasto — WCAG 2.5.7). */
const PRODUCAO_JS_CARDS_A11Y = <<<'JS'
(() => {
  const gridKanban = () => {
    let melhor = null; let max = 0;
    for (const el of document.querySelectorAll('*')) {
      let n = 0;
      for (const c of el.children) { if (c.tagName === 'SECTION') n++; }
      if (n > max) { max = n; melhor = el; }
    }
    return melhor;
  };
  const grid = gridKanban();
  if (!grid) return 'GRID-AUSENTE';
  const cards = [...grid.querySelectorAll('article[draggable]')];
  const FOCAVEL = ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA'];
  const ROLE_INTERATIVO = ['button', 'link', 'menuitem', 'option', 'tab', 'checkbox'];
  const operaveis = cards.filter((c) => {
    const ti = c.getAttribute('tabindex');
    if (ti !== null && Number(ti) >= 0) return true;
    if (FOCAVEL.includes(c.tagName)) return true;
    const role = (c.getAttribute('role') || '').toLowerCase();
    return ROLE_INTERATIVO.includes(role);
  }).length;
  const keyshortcuts = document.querySelectorAll('[aria-keyshortcuts]').length;
  return String(cards.length) + '|' + String(operaveis) + '|' + String(keyshortcuts);
})()
JS;

/** Guard do Non-Goal do charter: modal e `<form>`. `sections` e o CONTROLE POSITIVO — sem
 *  ele, uma sonda que nao achasse nada passaria por "nada proibido na pagina". */
const PRODUCAO_JS_NON_GOAL = <<<'JS'
(() => {
  const gridKanban = () => {
    let melhor = null; let max = 0;
    for (const el of document.querySelectorAll('*')) {
      let n = 0;
      for (const c of el.children) { if (c.tagName === 'SECTION') n++; }
      if (n > max) { max = n; melhor = el; }
    }
    return melhor;
  };
  const grid = gridKanban();
  const main = document.querySelector('main');
  const dialogs = document.querySelectorAll('dialog, [role="dialog"], [role="alertdialog"]').length;
  const forms = main ? main.querySelectorAll('form').length : -1;
  const sections = grid ? [...grid.children].filter((c) => c.tagName === 'SECTION').length : 0;
  return 'dialogs=' + dialogs + ' forms=' + forms + ' sections=' + sections;
})()
JS;

beforeEach(function () {
    // CROSS-PROCESS DB (identico A11yAxe/AuthBridge/CaixaIndex): o browser usa MySQL (.env),
    // o test process usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL do gate pra
    // resolver o admin do tenant. Este arquivo NAO escreve nada no banco.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/** Admin do tenant ficticio do gate. Falha ALTO: tenant ausente nao pode virar verde
 *  silencioso — smoke sem render seria falso positivo (idem CaixaIndexTest). */
function producaoAdmin(): User
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

/** Espera o React estabilizar. Ver o shell nao significa que o kanban montou — mesma razao
 *  do `aguardarAlvoVisual` do FinanceiroFlowBaselineTest. */
function producaoEsperar($page, string $js, string $esperado, string $oque): void
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

/** Abre a tela autenticada (auth bridge cross-process) e espera o kanban montar. */
function producaoAbrirTela(int $largura = 1280, int $altura = 800)
{
    $admin = producaoAdmin();

    $page = visit('/_visreg-login/'.$admin->id.'?to='.urlencode(PRODUCAO_ROTA))
        ->resize($largura, $altura)
        // Ancora do CORPO da pagina (o contador da barra de filtro): se cair em 403/login/
        // erro, este assert e o que denuncia. Existe com ou sem OS.
        ->assertSee('aguardando aprovação');

    producaoEsperar($page, PRODUCAO_JS_PRONTO, 'PRONTO', 'o kanban montar as 5 colunas');

    return $page;
}

it('UC-RPOE-01 · render — o kanban monta com as 5 colunas na ordem do charter', function (int $w, int $h) {
    $page = producaoAbrirTela($w, $h);

    // Goal + Mission do charter: 5 colunas FIXAS, nesta ordem. O `ProducaoOficinaTest` prova
    // a prop `columns`; aqui se prova o que a pessoa VE, com os rotulos renderizados.
    $colunas = (string) $page->script(PRODUCAO_JS_COLUNAS);
    expect($colunas)->toBe('5|Recepção|Diagnóstico|Aguardando peças|Em execução|Pronto');

    $page->assertNoConsoleLogs();
})->with([[1280, 800], [1440, 900]]);

it('UC-RPOE-02 · render — cabe em 1280 sem scroll horizontal', function () {
    // UX Target do charter marcado como CRITICO (monitor 1280px). `grid-cols-5` num monitor
    // estreito e o cenario que mais facilmente estoura — e nenhum grep no .tsx responde.
    $page = producaoAbrirTela(1280, 800);

    $medida = (string) $page->script(PRODUCAO_JS_OVERFLOW);
    expect($medida)->toContain('|');

    [$scrollWidth, $clientWidth] = array_map('intval', explode('|', $medida));

    // CONTROLE POSITIVO: uma viewport que nao mediu (0) nao pode passar por "sem overflow".
    expect($clientWidth)->toBeGreaterThan(0);
    expect($scrollWidth)->toBeLessThanOrEqual($clientWidth);

    $page->assertNoConsoleLogs();
});

it('UC-RPOE-03 · render — o filtro liga o contador comparativo e o Limpar filtros', function () {
    $page = producaoAbrirTela();

    // CONTROLE POSITIVO do estado inicial: sem filtro o contador esta na forma SIMPLES e o
    // "Limpar filtros" NAO existe. Sem isto, o assert de transicao abaixo passaria por nada.
    expect((string) $page->script(PRODUCAO_JS_CONTADOR))->toBe('SIMPLES');
    expect((string) $page->script(PRODUCAO_JS_LIMPAR_EXISTE))->toBe('AUSENTE');

    // Clica o 1o chip que nao seja "Todos" — qualquer que seja o slot_config do tenant.
    $clique = (string) $page->script(PRODUCAO_JS_CLICAR_CHIP);
    expect($clique)->toStartWith('CLICOU:');

    // Filtro ativo: o contador vira comparativo ("X de N OS") e o "Limpar filtros" aparece.
    // Note que NAO se afirma o X — em `data_source: live` o Controller emite slot => null em
    // todo card e o X seria legitimamente 0.
    producaoEsperar($page, PRODUCAO_JS_CONTADOR, 'COMPARATIVO', 'o contador virar comparativo');
    expect((string) $page->script(PRODUCAO_JS_LIMPAR_EXISTE))->toBe('EXISTE');

    // E o "Limpar filtros" restaura o estado inicial (senao o botao e decorativo).
    expect((string) $page->script(PRODUCAO_JS_LIMPAR))->toBe('CLICOU');
    producaoEsperar($page, PRODUCAO_JS_CONTADOR, 'SIMPLES', 'o contador voltar ao simples');
    expect((string) $page->script(PRODUCAO_JS_LIMPAR_EXISTE))->toBe('AUSENTE');

    $page->assertNoConsoleLogs();
});

it('UC-RPOE-04 · Non-Goal — drawer é o único container, sem modal e sem form', function () {
    $page = producaoAbrirTela();

    // §UX Anti-patterns do charter: "Modal de qualquer tipo (drawer e o unico container)".
    // §Non-Goals: sem CRUD e sem edicao inline nesta tela — logo, sem <form> no <main>.
    // `sections=5` e o controle positivo: sem ele a sonda passaria numa pagina vazia.
    expect((string) $page->script(PRODUCAO_JS_NON_GOAL))
        ->toBe('dialogs=0 forms=0 sections=5');

    $page->assertNoConsoleLogs();
});

it('UC-RPOE-05 · a11y — zero violação axe CRITICAL', function () {
    $page = producaoAbrirTela();

    // Mesmo piso do A11yAxeBrowserTest: level 0 = CRITICAL only. Subir pra level 1
    // (critical + serious) e PR follow-up — nao se sobe as cegas.
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-RPOE-06 · a11y — o card do kanban não é alcançável nem operável por teclado (defeito capturado)', function () {
    $page = producaoAbrirTela();

    $medida = (string) $page->script(PRODUCAO_JS_CARDS_A11Y);
    expect($medida)->toContain('|');

    [$total, $operaveis, $keyshortcuts] = array_map('intval', explode('|', $medida));

    // CONTROLE POSITIVO: sem card renderizado, "nenhum card e operavel" seria verdade vazia.
    expect($total)->toBeGreaterThan(0);

    // ⚠️ CARACTERIZACAO — o defeito medido em origin/main e0f2b79c86 (2026-09-05):
    // o card e <article draggable onClick> sem role, sem tabIndex e sem onKeyDown, e a tela
    // nao declara nenhum aria-keyshortcuts (alternativa de teclado pro arrasto, WCAG 2.5.7).
    //
    // QUANDO O DS FOR CONSERTADO ESTE CASO FICA VERMELHO — e esse vermelho e o SINAL DE
    // SUCESSO. O conserto e inverter os dois asserts abaixo para:
    //     expect($operaveis)->toBe($total);
    //     expect($keyshortcuts)->toBeGreaterThan(0);
    // NUNCA deletar o caso.
    expect($operaveis)->toBe(0);
    expect($keyshortcuts)->toBe(0);
});
