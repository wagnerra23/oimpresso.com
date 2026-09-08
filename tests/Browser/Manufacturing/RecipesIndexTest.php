<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `Manufacturing/Recipes` (Fabricação · Receitas).
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `memory/governance/screen-coverage-baseline.json` (gravado 2026-08-31) registra
 * `Manufacturing: {total:1, charter:1, scorecard:1, e2e:0, a11y:0, visreg:0}` — e nem
 * conta a tela nova, que entrou em 2026-09-02 (#6546). O contrato dela vive no servidor
 * (`Wave29RecipeInertiaTest`) e NADA exercita a tela RENDERIZADA. Este arquivo cobre só
 * esse eixo — não duplica assert de contrato nenhum.
 *
 * ── POR QUE ESTE ARQUIVO NÃO SEMEIA DADO ─────────────────────────────────────
 * Mesma decisão (e mesma razão) do irmão `Financeiro/CaixaIndexTest`: o
 * `Financeiro/ConciliacaoIndexTest` semeia fixture e afirma sobre a LINHA, e está VERMELHO
 * desde que entrou (6/6, `esperado '2', último '0'`) porque aquelas telas renderizam com
 * **business 0** sob o auth-bridge. Um segundo step permanentemente vermelho ensina o time
 * a ignorar vermelho.
 *
 * A causa lá era o grupo de rotas SEM `SetSessionData` (`Modules/Financeiro/Routes/web.php:57`).
 * O grupo do Manufacturing **TEM** o middleware (`Modules/Manufacturing/Routes/web.php:8`),
 * então é plausível que aqui a sessão resolva e a lista renderize — mas isso é HIPÓTESE DE
 * LEITURA, não medição: não pude executar (ver HONESTIDADE). Enquanto não houver 1 verde que
 * EXECUTOU, todo caso abaixo é independente de dado: vale com a lista vazia e continua valendo
 * quando ela vier cheia. Se o render de linha for confirmado, os 6 casos de interação do
 * `[BACKLOG]` do casos.md são o ratchet-up natural — e é por isso que o caso 4 MEDE a
 * contagem sem cravá-la.
 *
 * ── O QUE PROVA (e que Pest de contrato não alcança) ─────────────────────────
 *   1. a tela monta autenticada em 1280 (Larissa/ROTA LIVRE) e 1440, com os 4 KPIs e os
 *      rótulos de coluna do §4.2, sem erro de console — `UC-RECIPE-01` no eixo RENDER;
 *   2. as abas do §4.1 apontam pra rotas que EXISTEM hoje, e a aba **Insumos NÃO existe**
 *      (§18.3: "sem backend, a aba não sai") — Non-Goal do charter, hoje sem gate nenhum;
 *   3. `/` foca a busca (R-04) COM o controle negativo da própria regra: `/` disparado de
 *      dentro de um campo NÃO rouba o foco. Lógica 100% client-side (`Recipes.tsx:70-84`);
 *   4. o contador do cabeçalho, o subtítulo do 1º KPI e a lista CONCORDAM — invariante
 *      self-consistent, não crava contagem, e falha se o cabeçalho passar a mentir;
 *   5. Non-Goal "não escreve nada": ZERO `<form>` e ZERO botão de mutação na raiz da tela,
 *      com controle POSITIVO embutido (`ordenaveis=7`) pra o guard não ser vácuo. É o que
 *      defende o §18.1 ("não implemente esse fator 2" — markup em massa é Tier 0 de VALOR);
 *   6. `NaN`/`Infinity` não chegam à tela — metade de EXIBIÇÃO do R-13 (o cálculo em si é do
 *      `Wave29RecipeInertiaTest`; aqui provo que nenhum caminho vaza sujeira pro usuário);
 *   7. zero violação axe CRITICAL (mesmo ratchet level 0 do `A11yAxeBrowserTest`).
 *
 * ── O QUE **NÃO** PROVA (resíduo declarado, não maquiado) ────────────────────
 *   - Os 6 casos de interação do `[BACKLOG]` (busca casando SKU/categoria, quais KPIs
 *     filtram, ordenação voltando à pág. 1, "selecionar todas" nas FILTRADAS, faixas de
 *     margem, drawer fechando com `esc`/scrim). Todos exigem LINHA renderizada. Fora DE
 *     PROPÓSITO até haver 1 verde que executou — E2E frágil vira ruído.
 *   - "cabe em 1280 sem scroll horizontal fora do `.mfg-tablewrap`" (UX target do charter):
 *     probe de `scrollWidth` não foi escrita porque não dá pra calibrá-la sem executar.
 *   - A ficha PT-07 impressa (com custo / via de produção, R-22): é `window.print()` e
 *     `@media print`; browser test não fotografa impressão. Segue sem gate.
 *   - ESCRITA: nada aqui clica em nada que mute. A tela é 100% leitura por charter, e este
 *     arquivo não escreve UMA linha no banco.
 *   - NÃO gera baseline de pixel: nenhum `screenshot()`. A tela não está em
 *     `tests/Browser/visreg-screens.json` e este PR não a coloca lá.
 *
 * ── TENANT (convenção DESTE gate) ────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 do gate visual, tenant FICTÍCIO do
 * `VisregTenantSeeder` no MySQL `oimpresso_test`. `biz=4` (ROTA LIVRE) é PROIBIDO em teste
 * (ADR 0358); 98 é o tenant-vazio. Mesmo idioma de `AuthBridgeSmokeTest`.
 *
 * ── EXECUÇÃO (CT 100 / CI — nunca local: memory/proibicoes.md + ADR 0062) ────
 *   tailscale ssh root@ct100-mcp "docker exec oimpresso-staging ./vendor/bin/pest tests/Browser/Manufacturing/RecipesIndexTest.php"
 *
 * HONESTIDADE (ADR 0108) — o que FOI e o que NÃO foi verificado antes do PR:
 *   OK  `php -l` (PHP 8.5.6): `No syntax errors detected`. É lint de sintaxe, não execução
 *       de teste — não cai no bloqueio Tier 0 de rodar Pest local (memory/proibicoes.md).
 *   OK  as 5 probes JS foram extraídas DESTE arquivo e rodadas em jsdom contra um DOM que
 *       espelha o `.tsx`, com controles POSITIVOS e NEGATIVOS (probe sem alvo devolve
 *       sentinela, nunca verde). jsdom não tem layout engine: prova a LÓGICA DE SELEÇÃO e o
 *       comportamento de foco, não medida de layout.
 *   NÃO o teste em si: Pest Browser é CI/CT-100 only e o Tailscale desta máquina está em
 *       `NoState` (ssh pro ct100 fecha a conexão). Nasce ADVISORY no workflow (ADR 0261/0275:
 *       gate novo nasce advisory → 2 verdes que EXECUTARAM → enforcing).
 * Só usa API já provada verde nos Browser tests deste repo: `visit` · `resize` · `assertSee` ·
 * `assertDontSee` · `assertNoConsoleLogs` · `script` · `wait` · `assertNoAccessibilityIssues`.
 * O `dispatchEvent` dentro do `script()` é o idioma do `element.click()` do
 * `FinanceiroFlowBaselineTest:126` — evita que um `press` de driver deixe hover/scroll.
 *
 * @see resources/js/Pages/Manufacturing/Recipes.tsx (tela sob teste)
 * @see resources/js/Pages/Manufacturing/Recipes.charter.md (Goals · Non-Goals · anti-hooks)
 * @see resources/js/Pages/Manufacturing/Recipes.casos.md (UC-RECIPE-00..07 + [BACKLOG])
 * @see memory/requisitos/Manufacturing/RUNBOOK-recipes.md (F1 PLAN)
 * @see .github/workflows/visual-regression.yml (step que invoca)
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

const MFG_ROTA = '/manufacturing/recipe';

/** Sinal de PRONTIDÃO. Ver o `<h1>` não prova que o corpo montou (o título também vive no
 *  shell); os dois containers de contrato existem com ou sem receita, então são o sinal
 *  independente de dado. Ancorado em `data-contract`, não em classe de estilo (L-24). */
const MFG_JS_PRONTO = <<<'JS'
(() => {
  const kpis = document.querySelector('[data-contract="kpis"]');
  const lista = document.querySelector('[data-contract="lista"]');
  return (kpis && lista) ? 'PRONTO' : 'ESPERANDO';
})()
JS;

/** Rótulos + destino de cada aba do §4.1. A aba ativa não é link (é `span[aria-current]`),
 *  então devolve ATUAL. Sentinela `NAV-AUSENTE` impede verde por ausência. */
const MFG_JS_ABAS = <<<'JS'
(() => {
  const nav = document.querySelector('nav[aria-label="Manufacturing"]');
  if (!nav) return 'NAV-AUSENTE';
  const itens = [...nav.children].map((el) => {
    const bruto = (el.textContent || '').replace(/\s+/g, ' ').trim();
    const rot = bruto.replace(/\s*\d+(\s*·\s*\d+\s*rasc\.)?$/, '').trim();
    const atual = el.getAttribute('aria-current') === 'page';
    const destino = el.getAttribute('href') || (atual ? 'ATUAL' : 'SEM-DESTINO');
    return rot + '=' + destino;
  });
  return itens.length ? itens.join('|') : 'NAV-VAZIA';
})()
JS;

/** R-04, metade POSITIVA: `/` fora de campo foca a busca. Devolve `antes->depois` pra a
 *  falha dizer se o problema foi o foco inicial ou o handler. */
const MFG_JS_FOCO = <<<'JS'
(() => {
  const inp = document.querySelector('input[aria-label="Buscar receita"]');
  if (!inp) return 'BUSCA-AUSENTE';
  const antes = document.activeElement === inp ? 'BUSCA' : 'OUTRO';
  window.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));
  const depois = document.activeElement === inp ? 'BUSCA' : 'OUTRO';
  return antes + '->' + depois;
})()
JS;

/** R-04, metade NEGATIVA (o `emCampo` do handler): `/` disparado DE DENTRO de um campo não
 *  rouba o foco de quem estava focado. Sem este par, o caso positivo passaria mesmo num
 *  handler que focasse a busca em QUALQUER tecla. */
const MFG_JS_FOCO_EM_CAMPO = <<<'JS'
(() => {
  const inp = document.querySelector('input[aria-label="Buscar receita"]');
  const th = [...document.querySelectorAll('button')]
    .find((b) => (b.textContent || '').replace(/\s+/g, ' ').trim().startsWith('Categoria'));
  if (!inp || !th) return 'ALVO-AUSENTE';
  th.focus();
  if (document.activeElement !== th) return 'FOCO-NAO-PEGOU';
  inp.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));
  return document.activeElement === th ? 'MANTEVE' : 'ROUBOU';
})()
JS;

/** `cabecalho|kpi|linhas|paginacao`. Os dois primeiros saem do MESMO `recipes.length` por
 *  caminhos diferentes do JSX; as linhas são os `[role="button"]` da lista (o cabeçalho da
 *  grade não tem role, e o Checkbox do shadcn é `role="checkbox"`). A paginação é achada por
 *  TEXTO (`a–b de N`), nunca por classe (L-24). */
const MFG_JS_CONCORDANCIA = <<<'JS'
(() => {
  const txt = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const n = (s) => { const m = s.match(/(\d+)/); return m ? m[1] : 'SEM-NUMERO'; };
  const cab = document.querySelector('[data-contract="cabecalho"] p');
  if (!cab) return 'CABECALHO-AUSENTE';
  const sub = [...document.querySelectorAll('[data-contract="kpis"] span')]
    .find((s) => txt(s).indexOf('dia das') > 0);
  if (!sub) return 'KPI-SUB-AUSENTE';
  const linhas = document.querySelectorAll('[data-contract="lista"] [role="button"]').length;
  const pag = [...document.querySelectorAll('span')]
    .map(txt).find((t) => /^\d+–\d+ de \d+$/.test(t)) || 'SEM-PAG';
  return n(txt(cab)) + '|' + n(txt(sub)) + '|' + String(linhas) + '|' + pag;
})()
JS;

/** Guard do Non-Goal "não escreve nada". Escopo = raiz da tela (`data-screen-label`), pra a
 *  nav do shell ficar fora. `ordenaveis=7` é o CONTROLE POSITIVO: sem ele, um probe que não
 *  achasse botão nenhum passaria por "nada proibido". "Nova receita" é `<a>`, não botão — é
 *  link pro CRUD legado, afordância sancionada pelo charter. */
const MFG_JS_LEITURA = <<<'JS'
(() => {
  const raiz = document.querySelector('[data-screen-label]');
  if (!raiz) return 'RAIZ-AUSENTE';
  const rot = [...raiz.querySelectorAll('button')]
    .map((b) => (b.textContent || '').replace(/\s+/g, ' ').trim());
  const proibidos = rot.filter((s) => /(salvar|excluir|remover|produzir|finalizar|atualizar pre)/i.test(s));
  const ordenaveis = rot.filter((s) => /[\u21f5\u2191\u2193]/.test(s)).length;
  return 'forms=' + raiz.querySelectorAll('form').length
    + ' proibidos=' + (proibidos.join(',') || 'nenhum')
    + ' ordenaveis=' + String(ordenaveis);
})()
JS;

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico A11yAxe/AuthBridge): o browser usa MySQL (.env), o processo
    // de teste usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL do gate só pra
    // resolver o admin do tenant. Este arquivo NÃO escreve nada no banco.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/** Admin do tenant fictício do gate. Falha ALTO: tenant ausente não pode virar verde
 *  silencioso — smoke sem render seria falso positivo (idem CaixaIndexTest). */
function mfgAdmin(): User
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

/** Espera o React estabilizar. Ver o cabeçalho não significa que o corpo montou — mesma
 *  razão do `caixaEsperar` do CaixaIndexTest. */
function mfgEsperar($page, string $js, string $esperado, string $oque): void
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

/** Abre a tela autenticada (auth bridge cross-process) e espera o corpo montar. */
function mfgAbrirTela(int $largura = 1280, int $altura = 800)
{
    $admin = mfgAdmin();

    // Âncora do CORPO: o `<h1>` "Manufacturing" também aparece no shell/sidebar, então o
    // assert usa a linha de subtítulo, que só existe nesta tela. Se cair em 403/login/erro,
    // é este assert que denuncia.
    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode(MFG_ROTA))
        ->resize($largura, $altura)
        ->assertSee('custo recalculado pelo preço atual dos ingredientes');

    mfgEsperar($page, MFG_JS_PRONTO, 'PRONTO', 'os containers de KPI e lista montarem');

    return $page;
}

it('UC-RECIPE-01 · render — a tela monta autenticada com os 4 KPIs e a grade', function (int $w, int $h) {
    $page = mfgAbrirTela($w, $h);

    // §4.2 do handoff — os 4 KPIs. Os rótulos são copy do contrato de tela
    // (`prototipo-ui/contrato/manufacturing-recipes.contract.json`), não invenção do teste.
    $page->assertSee('Custo médio / unidade')
        ->assertSee('Margem abaixo de 45%')
        ->assertSee('Desperdício ≥ 8%')
        ->assertSee('Produção do mês');

    // Colunas da grade: o que o usuário lê como promessa de custeio.
    $page->assertSee('Receita')
        ->assertSee('Categoria')
        ->assertSee('Custo total')
        ->assertSee('Custo unitário')
        ->assertSee('Margem');

    // Busca do §4.2 — o placeholder ensina o atalho que o caso do `/` exercita.
    $page->assertSee('Buscar receita por nome, SKU, categoria');

    $page->assertNoConsoleLogs();
})->with([[1280, 800], [1440, 900]]);

it('render — as abas do §4.1 apontam pra rotas que existem, Insumos inclusive', function () {
    $abas = (string) mfgAbrirTela()->script(MFG_JS_ABAS);

    expect($abas)->not->toContain('AUSENTE')
        ->and($abas)->not->toBe('NAV-VAZIA');

    // A aba ativa é a própria tela (span com aria-current), não um link pra ela mesma.
    expect($abas)->toContain('Receitas=ATUAL');

    // Endereços CANÔNICOS — desde o cutover de 2026-09-04 eles servem a tela React (antes
    // serviam Blade, e a tela React vivia em `/v2/*`). Rotas reais de `Routes/web.php`.
    expect($abas)->toContain('Relatório=/manufacturing/report')
        ->and($abas)->toContain('Configurações=/manufacturing/settings');

    // ⚠️ INVERTIDO em 2026-09-04. O assert anterior era `not->toContain('Insumos')`, ancorado
    // no §18.3 do handoff ("sem `usosDoInsumo`, a aba não sai") e no Non-Goal do charter. A
    // premissa CAIU: o backend saiu na US-MANU-005 e a tela subiu sem nenhuma entrada — só
    // abria digitando a URL. Agora a aba é exigida, não proibida.
    expect($abas)->toContain('Insumos');

    // "Ordens de produção" é gated por `permissions.prod`, então a PRESENÇA não é exigida —
    // mas se aparecer, o destino tem de ser o endereço CANÔNICO (que serve React desde o
    // cutover de 2026-09-04), nunca uma rota alternativa `/v2/`.
    if (str_contains($abas, 'Ordens de produção=')) {
        expect($abas)->toContain('Ordens de produção=/manufacturing/production');
        expect($abas)->not->toContain('/manufacturing/v2/');
    }
});

it('render — a tecla / foca a busca, e dentro de campo NÃO rouba o foco (R-04)', function () {
    $page = mfgAbrirTela();

    // Metade positiva: o par `antes->depois` é o controle — provar que saiu de OUTRO pra
    // BUSCA é diferente de encontrar a busca já focada por acaso.
    expect($page->script(MFG_JS_FOCO))->toBe('OUTRO->BUSCA');

    // Metade negativa (o `emCampo` do handler em `Recipes.tsx:74`): sem ela, o caso acima
    // passaria mesmo num handler que focasse a busca em qualquer tecla.
    expect($page->script(MFG_JS_FOCO_EM_CAMPO))->toBe('MANTEVE');

    $page->assertNoConsoleLogs();
});

it('render — cabeçalho, KPI e lista concordam (o contador não mente sobre a grade)', function () {
    $triple = (string) mfgAbrirTela()->script(MFG_JS_CONCORDANCIA);
    expect($triple)->toContain('|');

    [$cabecalho, $kpi, $linhas, $pag] = explode('|', $triple);

    // Os dois números saem do mesmo `recipes.length` por caminhos diferentes do JSX. Não
    // crava contagem: vale com a lista vazia (hoje) e continua valendo quando ela vier cheia.
    expect($cabecalho)->not->toBe('SEM-NUMERO')
        ->and($kpi)->toBe($cabecalho);

    if ($pag === 'SEM-PAG') {
        // Sem paginação => tudo que existe está na tela (POR_PAG = 10 em `Recipes.tsx:39`).
        expect((int) $linhas)->toBe((int) $cabecalho);

        return;
    }

    // Com paginação, o rodapé "a–b de N" tem de falar do MESMO conjunto: N == cabeçalho, e a
    // página mostra exatamente b-a+1 linhas.
    preg_match('/^(\d+)\x{2013}(\d+) de (\d+)$/u', $pag, $m);
    expect($m)->not->toBeEmpty();
    expect($m[3])->toBe($cabecalho)
        ->and((int) $linhas)->toBe((int) $m[2] - (int) $m[1] + 1);
});

it('render — a tela não oferece afordância de escrita (Non-Goal do charter + §18.1)', function () {
    $page = mfgAbrirTela();

    // Charter: "Não escreve nada. A tela é 100% leitura — nenhum POST, PATCH ou DELETE parte
    // daqui" + "Não atualiza preço de venda em massa" (§18.1: "Não implemente esse fator 2";
    // escrever em N preços é Tier 0 de VALOR, `memory/proibicoes.md` §REGRA MESTRE).
    // `ordenaveis=7` é o controle positivo — o probe lê botões de verdade.
    expect($page->script(MFG_JS_LEITURA))->toBe('forms=0 proibidos=nenhum ordenaveis=7');
});

it('render — NaN e Infinity não chegam à tela (metade de exibição do R-13)', function () {
    // O cálculo é provado no servidor (`Wave29RecipeInertiaTest`: divisão por zero devolve 0).
    // Aqui a pergunta é outra: nenhum caminho de formatação vaza sujeira pro usuário. Vale com
    // a lista vazia e continua valendo com dado — é o tipo de assert que sobrevive.
    mfgAbrirTela()
        ->assertDontSee('NaN')
        ->assertDontSee('Infinity');
});

it('a11y — zero violação axe CRITICAL na tela renderizada', function () {
    // Mesmo ratchet do `A11yAxeBrowserTest`: level 0 = CRITICAL only (piso honesto; `serious`
    // inclui contraste, fora do escopo). Audita o chrome real desta tela — grid de KPI, chips,
    // grade e empty-state — que nenhum outro gate renderiza hoje. O charter já registra 2 ADRs
    // de DS abertas por esta tela (`0410` `--text-mute` reprova AA, `0411` `--accent` no
    // escuro), ambas de CONTRASTE: por isso o piso aqui é CRITICAL, não `serious`.
    mfgAbrirTela()->assertNoAccessibilityIssues(level: 0);
});
