<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `resources/js/Pages/Patrimonio/Bens.tsx`.
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `git grep -l "Patrimonio/" tests/Browser/` (2026-09-10) devolvia **só o manifesto
 * `visreg-screens.json`** — nenhum teste Browser exercitava esta tela. Toda a
 * cobertura `e2e` que o `screen-coverage-map` creditava a ela vinha do contrato
 * visreg, não de teste: o próprio mapa documenta que "14 dos 18 eram crédito de
 * visreg lido como E2E" (`scripts/qa/screen-coverage-map.mjs:17-21`).
 *
 * Consequência prática, e é o que motivou este arquivo: ao remover a baseline de
 * pixel de `Patrimonio/Bens` (decisão [W] 2026-09-09 — "não é mais para usar
 * baseline / sempre apodrece", registrada no cabeçalho do `screen-coverage-gate.yml`),
 * o eixo e2e caiu de 61 para 60 e o gate **required** reprovou. A cobertura tinha de
 * virar real em vez de emprestada.
 *
 * ── O QUE PROVA, e que NENHUM Pest de contrato alcança ────────────────────────
 * O `contrato-de-tela.mjs` lê a **FONTE** (`.tsx`) — ele casa `data-contract="..."`
 * no texto do arquivo. A própria `KpiGrid.tsx` do módulo já registra a fronteira:
 * *"presença na fonte ≠ presença no DOM"*. Uma âncora pode estar no `.tsx` e não
 * chegar ao browser (ramo condicional, componente que retorna null, erro de render).
 * Este arquivo mede o **DOM renderizado** — é o outro lado dessa fronteira.
 *
 * ── POR QUE NENHUM CASO AQUI DEPENDE DE DADO ──────────────────────────────────
 * História datada, não precaução genérica: entre #6476 e #6632 o step do
 * `ConciliacaoIndexTest` ficou VERMELHO 6/6 (`esperado '2', último '0'`) porque as
 * telas daquele grupo de rotas renderizavam com business 0 sob o auth-bridge — e o
 * vermelho era invisível, porque `continue-on-error` publica `conclusion=success`.
 * Um segundo step permanentemente vermelho ensina o time a ignorar vermelho. Todo
 * caso abaixo vale com a lista VAZIA (que é o estado do tenant de visreg hoje) e
 * continua valendo no dia em que houver bem cadastrado.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (Tier 0 — `memory/proibicoes.md` §Ambiente: Pest nunca roda
 * na máquina local). A lane é o step "E2E de render · Patrimonio/Bens" do
 * `visual-regression.yml`, que já provê Chromium — por isso este arquivo NÃO carrega
 * guard de `class_exists`: quem garante o browser é a lane, não o arquivo. (Guard
 * copiado de teste órfão foi o que produziu `4 skipped (0 assertions)` marcado
 * SUCCESS no run 33675746851.)
 */

// `App\Business` / `App\User`, NÃO `App\Models\*`: este é um fork do UltimatePOS e os
// models do núcleo vivem na raiz de `app/`. Escrever `App\Models\*` por convenção
// Laravel devolveu `Class "App\Models\Business" not found` no run 33677097272.
use App\Business;
use App\User;

// OBRIGATÓRIO nesta lane: o `phpunit.xml` fixa `DB_CONNECTION=sqlite` + `:memory:` e o
// job não exporta `DB_CONNECTION` no ambiente do processo — então todo Pest daqui
// nasceria em sqlite vazio e morreria em `no such table: business`. Copiado de
// `PixelBaselineTest.php`, que é quem faz isso funcionar.
beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

/** As 4 âncoras que `prototipo-ui/contrato/patrimonio-bens.contract.json` declara, na ordem. */
const ANCORAS_BENS = ['cabecalho', 'subnav', 'filtros', 'tabela'];

/**
 * Sequência de `data-contract` DA TELA, como o browser pintou (ordem de documento).
 *
 * ⚠️ FILTRA pelas âncoras que o contrato DESTA tela declara, e a razão é um incidente real:
 * a versão anterior lia `document.querySelectorAll('[data-contract]')` SEM filtro, o que só
 * funcionava enquanto a página fosse a única a emitir âncoras. Em 2026-09-11 o #7212 deu ao
 * SHELL cinco âncoras próprias (`sb-modos`, `sb-topo`, `sb-corpo`, `sb-rodape`, `sb-alcas`,
 * em `AppShellV2.tsx:595-621`) e este teste — que é ENFORCING — passou a reprovar com
 *   -'cabecalho,subnav,filtros,tabela'
 *   +'sb-modos,sb-topo,sb-corpo,sb-rodape,sb-alcas,cabecalho,subnav,filtros,tabela'
 * sem que nada da tela de Bens tivesse mudado.
 *
 * Filtrar pelo conjunto DECLARADO (em vez de excluir um prefixo `sb-`) é o corte certo: um
 * denylist de prefixo quebraria de novo na primeira âncora de shell que não começasse com
 * `sb-`. Assim o teste segue pegando âncora FALTANDO e âncora FORA DE ORDEM — que é o que
 * ele existe pra pegar — e para de depender de quem mais pinta âncora na página.
 */
function ancorasDaTelaJs(array $declaradas): string
{
    $querido = json_encode(array_values($declaradas));

    return '(() => { const querido = ' . $querido . ';'
        . ' return Array.from(document.querySelectorAll("[data-contract]"))'
        . ' .map((el) => el.getAttribute("data-contract"))'
        . ' .filter((a) => querido.includes(a)).join(","); })()';
}

/**
 * Há scroll horizontal no documento? Mede o que o browser resolveu, não a classe.
 *
 * Devolve STRING, não boolean, de propósito: o único retorno de `script()` provado nesta
 * suíte é string (`SidebarAutoRailTest` compara com `toBe('rail')`). Coerção de boolean
 * pelo driver não está verificada, e este arquivo não pode ser rodado antes do PR
 * (Pest = CI/CT-100 only) — então não se aposta no que não foi medido.
 */
const SCROLL_X_JS = <<<'JS'
(() => (document.documentElement.scrollWidth > document.documentElement.clientWidth) ? 'rola' : 'nao-rola')()
JS;

/**
 * Abre `/asset/assets` autenticada, pelo mesmo `/_visreg-login/{id}` que as suítes de
 * CoreScreens usam. Skip explícito (e não falha) quando o tenant não foi seedado: o
 * arquivo não pode fingir que mediu um ambiente que não existe.
 */
function abrirBens(): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/asset/assets'));
}

/**
 * Espera a sequência de âncoras ESTABILIZAR. A tela usa `Inertia::defer` na prop `bens`,
 * então ler o DOM no primeiro paint é medir meio-caminho — o erro catalogado em
 * `memory/proibicoes.md` §5 2026-08-24 ("declarar que a tela não renderiza medindo o DOM
 * durante o lazy-load"). 20 tentativas de 0,1s.
 *
 * Devolve o ÚLTIMO valor lido — nunca o esperado — pra que a asserção falhe dizendo o que
 * de fato apareceu. Espera-pelo-esperado que devolve o esperado não sabe reprovar.
 */
function ancorasEstaveis($page, string $esperado): string
{
    $js = ancorasDaTelaJs(ANCORAS_BENS);
    $visto = '';
    for ($i = 0; $i < 20; $i++) {
        $visto = (string) $page->script($js);
        if ($visto === $esperado) {
            return $visto;
        }
        $page->wait(0.1);
    }

    return $visto;
}

it('RENDER · as 4 âncoras do contrato de Patrimonio/Bens chegam ao DOM, na ordem declarada', function () {
    // Este é o caso que o gate estático NÃO alcança: ele casa `data-contract` no texto do
    // `.tsx`; aqui prova-se que o browser as pintou, e na sequência que o contrato exige.
    $page = abrirBens()->resize(1280, 800);
    $esperado = implode(',', ANCORAS_BENS);

    expect(ancorasEstaveis($page, $esperado))
        ->toBe($esperado, 'sequência de data-contract resolvida pelo browser em /asset/assets');
});

it('RENDER · o cabeçalho e os filtros do contrato aparecem renderizados', function () {
    // Copy declarada em `patrimonio-bens.contract.json`, derivada da âncora
    // `prototipo-ui/cowork/patrimonio-page.jsx`. Independente de dado: header e barra de
    // filtros montam com a lista vazia.
    $page = abrirBens()->resize(1280, 800);
    $esperado = implode(',', ANCORAS_BENS);
    ancorasEstaveis($page, $esperado);

    $page->assertSee('Bens');
    $page->assertSee('Categoria');
    $page->assertSee('Local');
    $page->assertSee('Tipo de compra');
});

it('RENDER · a 1280 (monitor da Larissa/ROTA LIVRE) a página não ganha scroll horizontal', function () {
    // UX Target do charter: "cabe em 1280px sem scroll horizontal". A tabela tem
    // `minTableWidth={1280}` e rola DENTRO do próprio container — quem não pode rolar é o
    // documento. Mede `scrollWidth > clientWidth` (o que o browser resolveu), não a classe.
    $page = abrirBens()->resize(1280, 800);
    ancorasEstaveis($page, implode(',', ANCORAS_BENS));

    expect((string) $page->script(SCROLL_X_JS))
        ->toBe('nao-rola', 'scrollWidth vs clientWidth do documento a 1280');
});
