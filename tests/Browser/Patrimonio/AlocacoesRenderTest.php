<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `resources/js/Pages/Patrimonio/Alocacoes.tsx`.
 *
 * Irmão de `BensRenderTest.php` (#7179): mesma forma, mesmas razões, mesmo harness. O
 * que muda é a tela, a rota e as âncoras.
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Patrimonio/Alocacoes` (2026-09-11):
 * trio ✓ · RUNBOOK ✓ · 4/4 UC citados por teste — e **`e2e (Browser) ✗ nenhum teste
 * Browser cita o path`**. Diferente da irmã Bens, aqui não havia nem crédito emprestado:
 * `Patrimonio/Alocacoes` NÃO está em `tests/Browser/visreg-screens.json` (medido: dos 52
 * entries, os de Patrimônio são `Index` e `Manutencoes`). A tela era descoberta no eixo
 * e2e, ponto — então este arquivo ADICIONA cobertura, não substitui empréstimo.
 *
 * ── O QUE PROVA, e que NENHUM Pest de contrato alcança ────────────────────────
 * O `contrato-de-tela.mjs` lê a **FONTE**: casa `data-contract="..."` no texto do `.tsx`.
 * A `KpiGrid.tsx` do próprio módulo registra a fronteira — *"presença na fonte ≠ presença
 * no DOM"*. Uma âncora pode estar no `.tsx` e não chegar ao browser (ramo condicional,
 * componente que retorna null, erro de render). Aqui mede-se o DOM RENDERIZADO.
 *
 * As 4 âncoras vêm de `prototipo-ui/contrato/patrimonio-alocacoes.contract.json` (#7204),
 * que deriva de `prototipo-ui/cowork/patrimonio-page.jsx` :: `AbaAlocacoes` (:409-474).
 * Este arquivo é o OUTRO LADO do mesmo contrato.
 *
 * ── POR QUE NENHUM CASO AQUI DEPENDE DE DADO ──────────────────────────────────
 * História datada, não precaução genérica: entre #6476 e #6632 o step do
 * `ConciliacaoIndexTest` ficou VERMELHO 6/6 porque as telas daquele grupo renderizavam com
 * business 0 sob o auth-bridge — e o vermelho era invisível, porque `continue-on-error`
 * publica `conclusion=success`. Step permanentemente vermelho ensina o time a ignorar
 * vermelho.
 *
 * Por isso a copy asserida é só a HARDCODED no `.tsx`, que monta com a lista vazia: o
 * título do `PageHeader` e os dois rótulos fixos do `Segmented`. Deliberadamente FORA:
 * os rótulos de coluna (não medi se o `DataTable` pinta `<th>` com zero linha) e
 * `indeterminado`, que só aparece em linha sem prazo.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (Tier 0 — `memory/proibicoes.md` §Ambiente: Pest nunca roda na
 * máquina local). A lane é o step "E2E de render · Patrimonio/Alocacoes" do
 * `visual-regression.yml`, que já provê Chromium — por isso este arquivo NÃO carrega guard
 * de `class_exists`: quem garante o browser é a lane, não o arquivo. (Guard copiado de
 * teste órfão foi o que produziu `4 skipped (0 assertions)` marcado SUCCESS no run
 * 33675746851.)
 */

// `App\Business` / `App\User`, NÃO `App\Models\*`: este é um fork do UltimatePOS e os models
// do núcleo vivem na raiz de `app/`. Escrever `App\Models\*` por convenção Laravel devolveu
// `Class "App\Models\Business" not found` no run 33677097272 do irmão Bens.
use App\Business;
use App\User;

// OBRIGATÓRIO nesta lane: o `phpunit.xml` fixa `DB_CONNECTION=sqlite` + `:memory:` e o job não
// exporta `DB_CONNECTION` no ambiente do processo — sem isto o Pest nasce em sqlite vazio e
// morre em `no such table: business`. Copiado de `PixelBaselineTest.php`, que é quem faz isso
// funcionar nesta lane.
beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

/** As 4 âncoras que `prototipo-ui/contrato/patrimonio-alocacoes.contract.json` declara, na ordem. */
const ANCORAS_ALOCACOES = ['cabecalho', 'subnav', 'filtros', 'tabela'];

/** Sequência de `data-contract` COMO O BROWSER PINTOU (ordem de documento). */
const ANCORAS_ALOC_NO_DOM_JS = <<<'JS'
(() => Array.from(document.querySelectorAll('[data-contract]'))
  .map((el) => el.getAttribute('data-contract'))
  .join(','))()
JS;

/**
 * Há scroll horizontal no documento? Mede o que o browser RESOLVEU, não a classe —
 * `memory/proibicoes.md` §5 2026-07-16 ("medir a propriedade errada e chamar de verificado").
 *
 * Devolve STRING, não boolean, de propósito: o único retorno de `script()` provado nesta suíte
 * é string (`SidebarAutoRailTest` compara com `toBe('rail')`). Coerção de boolean pelo driver
 * não está verificada, e este arquivo não pôde ser rodado antes do PR.
 */
const SCROLL_X_ALOC_JS = <<<'JS'
(() => (document.documentElement.scrollWidth > document.documentElement.clientWidth) ? 'rola' : 'nao-rola')()
JS;

/**
 * Abre `/asset/allocation` autenticada, pelo mesmo `/_visreg-login/{id}` que as suítes de
 * CoreScreens usam. Skip explícito (e não falha) quando o tenant não foi seedado: o arquivo
 * não pode fingir que mediu um ambiente que não existe.
 */
function abrirAlocacoes(): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/asset/allocation'));
}

/**
 * Espera a sequência de âncoras ESTABILIZAR. A tela usa `Inertia::defer` na prop `alocacoes`,
 * então ler o DOM no primeiro paint é medir meio-caminho — o erro catalogado em
 * `memory/proibicoes.md` §5 2026-08-24 ("declarar que a tela não renderiza medindo o DOM
 * durante o lazy-load"). 20 tentativas de 0,1s.
 *
 * Devolve o ÚLTIMO valor lido — nunca o esperado — pra que a asserção falhe dizendo o que de
 * fato apareceu. Espera-pelo-esperado que devolve o esperado não sabe reprovar.
 */
function ancorasAlocEstaveis($page, string $esperado): string
{
    $visto = '';
    for ($i = 0; $i < 20; $i++) {
        $visto = (string) $page->script(ANCORAS_ALOC_NO_DOM_JS);
        if ($visto === $esperado) {
            return $visto;
        }
        $page->wait(0.1);
    }

    return $visto;
}

it('RENDER · as 4 âncoras do contrato de Patrimonio/Alocacoes chegam ao DOM, na ordem declarada', function () {
    // O caso que o gate estático NÃO alcança: ele casa `data-contract` no TEXTO do `.tsx`;
    // aqui prova-se que o browser as pintou, e na sequência que o contrato exige.
    $page = abrirAlocacoes()->resize(1280, 800);
    $esperado = implode(',', ANCORAS_ALOCACOES);

    expect(ancorasAlocEstaveis($page, $esperado))
        ->toBe($esperado, 'sequência de data-contract resolvida pelo browser em /asset/allocation');
});

it('RENDER · o cabeçalho e o recorte por situação do contrato aparecem renderizados', function () {
    // Copy declarada em `patrimonio-alocacoes.contract.json`, derivada da âncora `AbaAlocacoes`
    // (:409-474) mais o rótulo da aba no shell (:838). Independente de dado: o título e as duas
    // opções fixas do `Segmented` montam com a lista vazia.
    $page = abrirAlocacoes()->resize(1280, 800);
    ancorasAlocEstaveis($page, implode(',', ANCORAS_ALOCACOES));

    $page->assertSee('Alocações');
    $page->assertSee('Ativas');
    $page->assertSee('Todas');
});

it('RENDER · a 1280 (monitor da Larissa/ROTA LIVRE) a página não ganha scroll horizontal', function () {
    // UX Target DECLARADO no charter (`Alocacoes.charter.md:118`): "Cabe em 1280px (monitor do
    // piloto) sem scroll horizontal NA PÁGINA: a tabela rola dentro do próprio container". Não é
    // invenção deste arquivo — é contrato escrito, e por isso um vermelho aqui é ACHADO.
    //
    // Nota honesta: esta tela usa `minTableWidth={1500}` (a irmã Bens usa 1280), então é ela que
    // exercita o caso mais largo do mesmo `DataTable`. Se o container não segurar, é aqui que
    // aparece primeiro — que é exatamente o motivo de o caso existir.
    $page = abrirAlocacoes()->resize(1280, 800);
    ancorasAlocEstaveis($page, implode(',', ANCORAS_ALOCACOES));

    expect((string) $page->script(SCROLL_X_ALOC_JS))
        ->toBe('nao-rola', 'scrollWidth vs clientWidth do documento a 1280');
});
