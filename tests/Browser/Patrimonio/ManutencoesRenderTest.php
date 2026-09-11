<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `resources/js/Pages/Patrimonio/Manutencoes.tsx`.
 *
 * Irmão de `BensRenderTest.php` (#7179), `AlocacoesRenderTest.php` e
 * `ConfiguracoesRenderTest.php`. Mesmo harness; o que muda é a tela, a rota, as âncoras —
 * e o fato de esta ser a ÚNICA das quatro que já tinha crédito `e2e`.
 *
 * ── O QUE MUDA AQUI: crédito EMPRESTADO vira crédito GANHO ────────────────────
 * As irmãs entraram onde não havia cobertura nenhuma. Esta tela JÁ conta como coberta no
 * eixo `e2e` — mas por empréstimo: `Patrimonio/Manutencoes` está em
 * `tests/Browser/visreg-screens.json`, e o `screen-coverage-map` computa o eixo como a
 * UNIÃO `e2e.length > 0 || hasVisregContract` (o próprio mapa documenta, `:17-21`, que "14
 * dos 18 eram crédito de visreg lido como E2E"). Medido em 2026-09-11: o `--screen` dizia
 * `e2e (Browser) ✗ nenhum teste Browser cita o path` enquanto a catraca a contava.
 *
 * Este arquivo NÃO muda o número do eixo (a união já era 1). Ele muda a NATUREZA dele: de
 * "existe uma foto de pixel dessa tela" para "existe um teste que a abre e mede o DOM".
 *
 * ── E É ISSO QUE DESTRAVA A REMOÇÃO DA BASELINE, sem fazê-la aqui ─────────────
 * A decisão [W] de 2026-09-09 ("não é mais para usar baseline / sempre apodrece") pede que
 * a foto de pixel saia. O #7179 mostrou que ela NÃO pode sair sozinha: removê-la derrubava
 * o crédito e2e e reprovava o `screen-coverage-gate`, que é **required** — foi por isso que
 * o #7174 foi fechado. Com este teste no lugar, o crédito deixa de depender da foto, e a
 * remoção passa a ser um passo seguro e separável.
 *
 * Deliberadamente NÃO removo a baseline neste PR: removê-la também tornaria esta tela
 * `uncovered` no fail-closed do `visual-regression` (o `contracted` de `ui-impact.mjs:504`
 * vem SÓ do manifesto), trocando um verde por um vermelho. Isso é decisão [W], não efeito
 * colateral de um PR de teste.
 *
 * ── A ÂNCORA CONDICIONAL — MEDIDA, e é por isso que são 3 e não 4 ─────────────
 * O contrato (#7208) declara QUATRO âncoras: `cabecalho`, `subnav`, `alerta`, `tabela`. Mas
 * `alerta` vive DENTRO de `{!permissoes.vejo_todas ? (...) : null}` — ele só existe pra quem
 * vê apenas as próprias manutenções. No harness o usuário é o admin do tenant, e a cadeia
 * foi medida ponta a ponta:
 *
 *   · `vejo_todas = auth()->user()->can('asset.view_all_maintenance')`
 *     (`AssetMaitenanceController:279`);
 *   · `asset.view_all_maintenance` NÃO está na lista especial do `Gate::before`
 *     (`['backup','superadmin','manage_modules']`), então cai no `else`
 *     (`AuthServiceProvider:42-45`);
 *   · o `else` concede a quem `hasRole('Admin#'.$business_id)`, e o
 *     `VisregTenantSeeder::ensureAdminRole()` cria e vincula exatamente `Admin#1`.
 *
 * Logo `vejo_todas` é TRUE no harness e o aviso NÃO renderiza. Assertar as 4 âncoras deixaria
 * este step PERMANENTEMENTE vermelho — o anti-padrão que o `ConciliacaoIndexTest` já custou
 * (#6476→#6632, 6/6 vermelho invisível porque `continue-on-error` publica
 * `conclusion=success`). Aqui a ausência do `alerta` não é tolerada em silêncio: ela é
 * ASSERTADA como contrato no terceiro caso.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (Tier 0 — `memory/proibicoes.md` §Ambiente). Lane: step
 * "E2E de render · Patrimonio/Manutencoes" do `visual-regression.yml`, que já provê Chromium.
 */

// `App\Business` / `App\User`, NÃO `App\Models\*`: fork do UltimatePOS, models do núcleo na
// raiz de `app/` (`App\Models\Business` deu "Class not found" no run 33677097272).
use App\Business;
use App\User;

// OBRIGATÓRIO nesta lane: o `phpunit.xml` fixa `DB_CONNECTION=sqlite` + `:memory:` e o job não
// exporta `DB_CONNECTION` no processo — sem isto o Pest nasce em sqlite vazio e morre em
// `no such table: business`. Copiado de `PixelBaselineTest.php`.
beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

/**
 * As âncoras do contrato que renderizam para QUEM VÊ TUDO — 3 das 4 declaradas em
 * `prototipo-ui/contrato/patrimonio-manutencoes.contract.json`. A quarta (`alerta`) é
 * condicional; ver o docblock do topo e o terceiro caso.
 */
const ANCORAS_MANUTENCOES_ADMIN = ['cabecalho', 'subnav', 'tabela'];

/**
 * Âncoras que o contrato (#7208) DECLARA — as 4, incluindo a condicional `alerta`. O filtro
 * abaixo usa esta lista, e não a das 3 esperadas, de propósito: se um dia o `alerta` passar a
 * renderizar pra quem vê tudo, ele ENTRA na sequência medida e o primeiro caso reprova — que
 * é o comportamento correto, porque o terceiro caso afirma que ele não deve estar lá.
 */
const ANCORAS_MANUTENCOES_CONTRATO = ['cabecalho', 'subnav', 'alerta', 'tabela'];

/**
 * Sequência de `data-contract` DA TELA, como o browser pintou (ordem de documento).
 *
 * ⚠️ FILTRA pelas âncoras que o contrato DESTA tela declara. Sem o filtro, o teste lê também
 * as âncoras do SHELL — em 2026-09-11 o #7212 deu cinco a ele (`sb-modos`, `sb-topo`,
 * `sb-corpo`, `sb-rodape`, `sb-alcas`, em `AppShellV2.tsx:595-621`) e o irmão
 * `BensRenderTest`, que é ENFORCING, reprovou sem que a tela dele tivesse mudado.
 *
 * Filtrar pelo conjunto DECLARADO (em vez de excluir o prefixo `sb-`) é o corte certo: um
 * denylist de prefixo quebraria na primeira âncora de shell fora desse padrão.
 */
function ancorasDaTelaManJs(array $declaradas): string
{
    $querido = json_encode(array_values($declaradas));

    return '(() => { const querido = ' . $querido . ';'
        . ' return Array.from(document.querySelectorAll("[data-contract]"))'
        . ' .map((el) => el.getAttribute("data-contract"))'
        . ' .filter((a) => querido.includes(a)).join(","); })()';
}

/** Há scroll horizontal no documento? Mede o que o browser RESOLVEU, não a classe. */
const SCROLL_X_MAN_JS = <<<'JS'
(() => (document.documentElement.scrollWidth > document.documentElement.clientWidth) ? 'rola' : 'nao-rola')()
JS;

/**
 * Abre `/asset/asset-maintenance` autenticada, pelo mesmo `/_visreg-login/{id}` das suítes de
 * CoreScreens. Skip explícito (e não falha) quando o tenant não foi seedado.
 */
function abrirManutencoes(): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/asset/asset-maintenance'));
}

/**
 * Espera a sequência de âncoras ESTABILIZAR — a prop `manutencoes` é `Inertia::defer`, então
 * ler o DOM no primeiro paint é medir meio-caminho (`memory/proibicoes.md` §5 2026-08-24).
 *
 * Devolve o ÚLTIMO valor lido — nunca o esperado — pra que a asserção falhe dizendo o que de
 * fato apareceu. Espera-pelo-esperado que devolve o esperado não sabe reprovar.
 */
function ancorasManEstaveis($page, string $esperado): string
{
    $js = ancorasDaTelaManJs(ANCORAS_MANUTENCOES_CONTRATO);
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

it('RENDER · as âncoras do contrato de Patrimonio/Manutencoes chegam ao DOM, na ordem declarada', function () {
    // O caso que o gate estático NÃO alcança: ele casa `data-contract` no TEXTO do `.tsx`;
    // aqui prova-se que o browser as pintou, na sequência que o contrato exige.
    $page = abrirManutencoes()->resize(1280, 800);
    $esperado = implode(',', ANCORAS_MANUTENCOES_ADMIN);

    expect(ancorasManEstaveis($page, $esperado))
        ->toBe($esperado, 'sequência de data-contract resolvida pelo browser em /asset/asset-maintenance');
});

it('RENDER · o cabeçalho e o filtro por situação aparecem renderizados', function () {
    // Copy declarada em `patrimonio-manutencoes.contract.json`, derivada da âncora
    // `AbaManutencoes` (:475-540) mais o rótulo da aba no shell (:839). Independente de dado:
    // título e a barra de filtros montam com a lista vazia.
    $page = abrirManutencoes()->resize(1280, 800);
    ancorasManEstaveis($page, implode(',', ANCORAS_MANUTENCOES_ADMIN));

    $page->assertSee('Manutenções');
    $page->assertSee('Situação');
});

it('CONTRATO · para quem vê TODAS as manutenções, o aviso de escopo NÃO aparece', function () {
    // A quarta âncora do contrato (`alerta`) é condicional em `!permissoes.vejo_todas`. Este
    // caso a fixa como CONTRATO em vez de tolerar a ausência em silêncio — e é o complemento
    // exato do UC que diz que o aviso aparece pra quem só vê as suas.
    //
    // Medido (a cadeia inteira, não presumido): `vejo_todas` é
    // `can('asset.view_all_maintenance')` (`AssetMaitenanceController:279`); essa ability não
    // está na lista especial do `Gate::before`, então cai no `else` que concede a quem
    // `hasRole('Admin#'.$business_id)` (`AuthServiceProvider:42-45`); e o
    // `VisregTenantSeeder::ensureAdminRole()` cria e vincula `Admin#1`. Logo o usuário do
    // harness vê tudo, e o aviso não deve existir no DOM.
    $page = abrirManutencoes()->resize(1280, 800);
    $sequencia = ancorasManEstaveis($page, implode(',', ANCORAS_MANUTENCOES_ADMIN));

    expect($sequencia)
        ->not->toContain('alerta', 'o aviso de escopo restrito não pode renderizar pra quem tem asset.view_all_maintenance');
});

it('RENDER · a 1280 (monitor da Larissa/ROTA LIVRE) a página não ganha scroll horizontal', function () {
    // UX Target DECLARADO no charter (`Manutencoes.charter.md:99`): "Tabela legível a 1280px,
    // o monitor do piloto — larguras declaradas por coluna". Contrato escrito, não invenção
    // deste arquivo: um vermelho aqui é ACHADO.
    $page = abrirManutencoes()->resize(1280, 800);
    ancorasManEstaveis($page, implode(',', ANCORAS_MANUTENCOES_ADMIN));

    expect((string) $page->script(SCROLL_X_MAN_JS))
        ->toBe('nao-rola', 'scrollWidth vs clientWidth do documento a 1280');
});
