<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `resources/js/Pages/Patrimonio/Configuracoes.tsx`.
 *
 * Irmão de `BensRenderTest.php` (#7179) e `AlocacoesRenderTest.php`: mesma forma, mesmo
 * harness, mesmas razões. O que muda é a tela, a rota e as âncoras — e uma guarda de
 * permissão que as irmãs não têm (ver abaixo, porque ela foi MEDIDA antes de escrever).
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Patrimonio/Configuracoes` (2026-09-11):
 * trio ✓ · RUNBOOK ✓ · 4/4 UC citados por teste — e **`e2e (Browser) ✗ nenhum teste
 * Browser cita o path`**. E aqui não havia nem crédito emprestado: `Patrimonio/Configuracoes`
 * NÃO está em `tests/Browser/visreg-screens.json`. Este arquivo ADICIONA cobertura.
 *
 * ── A GUARDA DE PERMISSÃO, E POR QUE ELA NÃO INVIABILIZA ESTE TESTE ───────────
 * Diferente de Bens e do Painel — que passam por `AssetController` com
 * `can('superadmin') || hasThePermissionInSubscription(...)` —, o
 * `AssetSettingsController:47` acrescenta `&& ! $is_admin` e responde **403** sem ele. Um
 * 403 aqui deixaria o step PERMANENTEMENTE vermelho, que é o anti-padrão que o
 * `ConciliacaoIndexTest` já custou (#6476→#6632, 6/6 vermelho invisível). Então foi medido
 * antes, não presumido:
 *
 *   · `Util::is_admin($user)` é `$user->hasRole('Admin#'.$business_id)` (`app/Utils/Util.php:486`);
 *   · o `VisregTenantSeeder::ensureAdminRole()` CRIA a role `Admin#1` e a vincula ao admin
 *     do tenant (`model_has_roles`) — logo `is_admin` é verdadeiro no harness;
 *   · a primeira metade da guarda é LITERALMENTE a mesma de `AssetController:97`, que já
 *     renderiza verde nesta lane (Bens, 3 runs) e sustenta a baseline do Painel.
 *
 * Se ainda assim der 403, o sintoma é inequívoco: a sequência de âncoras volta VAZIA e o
 * primeiro caso falha dizendo exatamente o que apareceu. Nasce advisory justamente porque
 * não pude executá-lo.
 *
 * ── O QUE PROVA, e que NENHUM Pest de contrato alcança ────────────────────────
 * O `contrato-de-tela.mjs` casa `data-contract` no TEXTO do `.tsx`; aqui mede-se o DOM
 * RENDERIZADO — a fronteira que a `KpiGrid.tsx` do módulo registra ("presença na fonte ≠
 * presença no DOM"). As 5 âncoras vêm de
 * `prototipo-ui/contrato/patrimonio-configuracoes.contract.json` (#7207), que deriva de
 * `prototipo-ui/cowork/patrimonio-page.jsx` :: `AbaConfig` (:589-626).
 *
 * Esta tela é a que mais ganha com o teste: a âncora `notificacoes` tem copy VAZIA no
 * contrato (o protótipo tem um `h3` "Notificações", a tela tem dois Cards nomeados), então
 * o gate estático só pode provar que a âncora existe no texto. Quem prova que ela chegou ao
 * DOM, e na posição entre `prefixos` e `acoes`, é este arquivo.
 *
 * ── POR QUE NENHUM CASO AQUI DEPENDE DE DADO ──────────────────────────────────
 * Mesma história datada das irmãs (#6476→#6632: telas renderizando com business 0 sob o
 * auth-bridge, vermelho invisível porque `continue-on-error` publica `conclusion=success`).
 * A copy asserida é só a HARDCODED no `.tsx`: título, o CardTitle dos prefixos, um rótulo
 * de campo e o botão de salvar — tudo monta sem nenhuma linha no banco. Esta tela é um
 * formulário de configuração, então nem tem lista pra estar vazia.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (Tier 0 — `memory/proibicoes.md` §Ambiente). A lane é o step
 * "E2E de render · Patrimonio/Configuracoes" do `visual-regression.yml`, que já provê
 * Chromium — por isso este arquivo NÃO carrega guard de `class_exists`: quem garante o
 * browser é a lane, não o arquivo.
 */

// `App\Business` / `App\User`, NÃO `App\Models\*`: fork do UltimatePOS, os models do núcleo
// vivem na raiz de `app/` (`App\Models\Business` deu "Class not found" no run 33677097272).
use App\Business;
use App\User;

// OBRIGATÓRIO nesta lane: o `phpunit.xml` fixa `DB_CONNECTION=sqlite` + `:memory:` e o job não
// exporta `DB_CONNECTION` no ambiente do processo — sem isto o Pest nasce em sqlite vazio e
// morre em `no such table: business`. Copiado de `PixelBaselineTest.php`.
beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

/** As 5 âncoras que `prototipo-ui/contrato/patrimonio-configuracoes.contract.json` declara, na ordem. */
const ANCORAS_CONFIGURACOES = ['cabecalho', 'subnav', 'prefixos', 'notificacoes', 'acoes'];

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
function ancorasDaTelaCfgJs(array $declaradas): string
{
    $querido = json_encode(array_values($declaradas));

    return '(() => { const querido = ' . $querido . ';'
        . ' return Array.from(document.querySelectorAll("[data-contract]"))'
        . ' .map((el) => el.getAttribute("data-contract"))'
        . ' .filter((a) => querido.includes(a)).join(","); })()';
}

/**
 * Há scroll horizontal no documento? Mede o que o browser RESOLVEU, não a classe —
 * `memory/proibicoes.md` §5 2026-07-16 ("medir a propriedade errada e chamar de verificado").
 *
 * Devolve STRING, não boolean, de propósito: o único retorno de `script()` provado nesta suíte
 * é string (`SidebarAutoRailTest` compara com `toBe('rail')`).
 */
const SCROLL_X_CFG_JS = <<<'JS'
(() => (document.documentElement.scrollWidth > document.documentElement.clientWidth) ? 'rola' : 'nao-rola')()
JS;

/**
 * Abre `/asset/settings` autenticada, pelo mesmo `/_visreg-login/{id}` das suítes de
 * CoreScreens. Skip explícito (e não falha) quando o tenant não foi seedado: o arquivo não
 * pode fingir que mediu um ambiente que não existe.
 */
function abrirConfiguracoes(): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/asset/settings'));
}

/**
 * Espera a sequência de âncoras ESTABILIZAR — ler o DOM no primeiro paint é medir
 * meio-caminho (`memory/proibicoes.md` §5 2026-08-24). 20 tentativas de 0,1s.
 *
 * Devolve o ÚLTIMO valor lido — nunca o esperado — pra que a asserção falhe dizendo o que de
 * fato apareceu. Espera-pelo-esperado que devolve o esperado não sabe reprovar. É também o
 * que torna o 403 legível: a guarda do controller devolveria sequência VAZIA.
 */
function ancorasCfgEstaveis($page, string $esperado): string
{
    $js = ancorasDaTelaCfgJs(ANCORAS_CONFIGURACOES);
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

it('RENDER · as 5 âncoras do contrato de Patrimonio/Configuracoes chegam ao DOM, na ordem declarada', function () {
    // O caso que o gate estático NÃO alcança — e nesta tela ele vale dobrado: a âncora
    // `notificacoes` tem copy VAZIA no contrato, então o gate estático só prova que ela existe
    // no TEXTO do `.tsx`. Que ela chegou ao DOM, e na posição entre `prefixos` e `acoes`, só
    // este caso prova.
    $page = abrirConfiguracoes()->resize(1280, 800);
    $esperado = implode(',', ANCORAS_CONFIGURACOES);

    expect(ancorasCfgEstaveis($page, $esperado))
        ->toBe($esperado, 'sequência de data-contract resolvida pelo browser em /asset/settings');
});

it('RENDER · o cabeçalho, a seção de prefixos e o botão de salvar aparecem renderizados', function () {
    // Copy declarada em `patrimonio-configuracoes.contract.json`, derivada da âncora
    // `AbaConfig` (:589-626) mais o rótulo da aba no shell (:842). Independente de dado: esta
    // tela é formulário de configuração — monta sem nenhuma linha no banco.
    $page = abrirConfiguracoes()->resize(1280, 800);
    ancorasCfgEstaveis($page, implode(',', ANCORAS_CONFIGURACOES));

    $page->assertSee('Configurações');
    $page->assertSee('Prefixos de código');
    $page->assertSee('Prefixo do código de alocação');
    $page->assertSee('Salvar configurações');
});

it('RENDER · a 1280 (monitor da Larissa/ROTA LIVRE) a página não ganha scroll horizontal', function () {
    // UX Target DECLARADO no charter (`Configuracoes.charter.md:118`): "Cabe em 1280px
    // (monitor do piloto) sem scroll horizontal". Não é invenção deste arquivo — é contrato
    // escrito, e por isso um vermelho aqui é ACHADO, não ruído.
    $page = abrirConfiguracoes()->resize(1280, 800);
    ancorasCfgEstaveis($page, implode(',', ANCORAS_CONFIGURACOES));

    expect((string) $page->script(SCROLL_X_CFG_JS))
        ->toBe('nao-rola', 'scrollWidth vs clientWidth do documento a 1280');
});
