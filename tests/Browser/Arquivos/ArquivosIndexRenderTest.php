<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER das 4 vistas de `resources/js/Pages/Arquivos/Index.tsx`.
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Arquivos/Index` (2026-11-16):
 * `e2e (Browser) ✗ nenhum teste Browser cita o path`. O único "E2E" da tela era
 * `e2e/arquivos-index.spec.ts`, um `test.fixme` carimbado pelo `criar-tela.mjs` que
 * nunca executou. Este arquivo o substitui (o stub foi apagado no mesmo PR).
 *
 * ── O QUE PROVA, e que o Pest de contrato não alcança ─────────────────────────
 * Os 9 UCs do `Index.casos.md` são defendidos em `ArquivosAdminControllerTest.php`, que
 * lê o PAYLOAD do controller. Nenhum deles prova que o browser pintou a vista: o
 * `contrato-de-tela.mjs` casa `data-contract` no TEXTO do `.tsx`. Aqui mede-se o DOM —
 * as âncoras de cada vista, na ordem, depois de o `<Deferred>` resolver.
 *
 * ── POR QUE NENHUM CASO DEPENDE DE DADO ───────────────────────────────────────
 * O tenant do visreg não semeia arquivo. Cada caso vale com o acervo VAZIO e continua
 * valendo com dado: onde a vista muda de forma conforme o dado (o Cofre sem disco mostra
 * só o estado vazio), as duas formas são aceitas explicitamente, e a de erro
 * ("Não foi possível medir…") reprova.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (Tier 0 — `memory/proibicoes.md` §Ambiente). Lane: step
 * "E2E de render · Arquivos/Index (ADVISORY)" do `visual-regression.yml`, que provê o
 * Chromium e o auth-bridge `/_visreg-login/{id}`. A permissão `arquivos.access` chega ao
 * usuário pelo papel `Admin#{business}` (`Gate::before` do `AuthServiceProvider`), o mesmo
 * caminho que a baseline de pixel de `/arquivos` já usa.
 *
 * A auditoria axe desta tela NÃO mora aqui: é a entrada `"a11y": true` de `Arquivos` em
 * `tests/Browser/visreg-screens.json`, que o `A11yAxeBrowserTest` deriva (uma declaração,
 * dois consumidores — ver o docblock dele).
 *
 * Contrato: `resources/js/Pages/Arquivos/Index.casos.md` (UC-INDEX-01 acervo · 02 trilha ·
 * 03 cofre · 04 retenção) e `governance/design/contracts/arquivos-index.contract.json`.
 */

// `App\Business` / `App\User`: fork do UltimatePOS, os models do núcleo vivem em `app/`.
use App\Business;
use App\User;

// Mesmo motivo do `PixelBaselineTest`: o `phpunit.xml` fixa sqlite em memória e o job não
// exporta `DB_CONNECTION`.
beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

/** Todas as âncoras que `arquivos-index.contract.json` declara (shell e afins ficam fora do filtro). */
const ARQ_ANCORAS = [
    'cabecalho', 'abas',
    'acervo-filtros', 'acervo',
    'retencao', 'retencao-kpis', 'retencao-politica', 'retencao-regras',
    'cofre', 'cofre-discos', 'cofre-achados',
    'trilha-filtros', 'trilha',
];

/**
 * Lê, do DOM, a sequência de `data-contract` da tela e o texto de uma âncora.
 * Devolve "seq|texto" — string, porque é o único retorno de `script()` provado na suíte.
 */
function arqLeituraJs(string $ancoraTexto): string
{
    $querido = json_encode(ARQ_ANCORAS);
    $alvo = json_encode($ancoraTexto);

    return '(() => { const q = ' . $querido . ';'
        . ' const seq = Array.from(document.querySelectorAll("[data-contract]"))'
        . ' .map((el) => el.getAttribute("data-contract")).filter((a) => q.includes(a)).join(",");'
        . ' const el = document.querySelector(\'[data-contract="\' + ' . $alvo . ' + \'"]\');'
        . ' return seq + "|" + (el ? el.innerText.replace(/\s+/g, " ") : ""); })()';
}

/** Abre `/arquivos?tab=<vista>` autenticada pelo auth-bridge do visreg. */
function arqAbrir(string $vista): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/arquivos?tab=' . $vista))
        ->resize(1280, 800);
}

/**
 * Espera a vista ESTABILIZAR: sequência aceita E o texto da âncora contendo um dos marcadores.
 * As 4 vistas usam `<Deferred>`; ler no primeiro paint é medir o skeleton (§5 2026-08-24).
 * Devolve o ÚLTIMO valor lido, nunca o esperado — assim a asserção falha dizendo o que viu.
 *
 * @param  list<string>  $sequencias  sequências de âncoras aceitas
 * @param  list<string>  $marcadores  copy que prova que o Deferred resolveu
 * @return array{0: string, 1: string}  [sequência, texto da âncora]
 */
function arqEstavel($page, string $ancoraTexto, array $sequencias, array $marcadores): array
{
    $js = arqLeituraJs($ancoraTexto);
    $seq = '';
    $texto = '';
    for ($i = 0; $i < 40; $i++) {
        [$seq, $texto] = array_pad(explode('|', (string) $page->script($js), 2), 2, '');
        $temMarcador = false;
        foreach ($marcadores as $m) {
            $temMarcador = $temMarcador || str_contains($texto, $m);
        }
        if (in_array($seq, $sequencias, true) && $temMarcador) {
            break;
        }
        $page->wait(0.1);
    }

    return [$seq, $texto];
}

it('UC-INDEX-01 · Arquivos/Index ACERVO: filtros e lista chegam ao DOM, na ordem do contrato', function () {
    $page = arqAbrir('acervo');
    [$seq, $texto] = arqEstavel($page, 'acervo', ['cabecalho,abas,acervo-filtros,acervo'], ['Vinculado a', 'Nenhum arquivo guardado ainda.']);

    expect($seq)->toBe('cabecalho,abas,acervo-filtros,acervo');
    // Tabela com as colunas do contrato OU o estado vazio declarado — nunca o skeleton.
    expect(str_contains($texto, 'Vinculado a') || str_contains($texto, 'Nenhum arquivo guardado ainda.'))->toBeTrue();
    $page->assertSee('Sensível');
    $page->assertSee('Descartar');
});

it('UC-INDEX-04 · Arquivos/Index RETENÇÃO: KPIs, política com base legal e regras chegam ao DOM', function () {
    $page = arqAbrir('retencao');
    $esperado = 'cabecalho,abas,retencao,retencao-kpis,retencao-politica,retencao-regras';
    [$seq, $texto] = arqEstavel($page, 'retencao', [$esperado], ['Base legal']);

    expect($seq)->toBe($esperado);
    expect($texto)->toContain('Vence em 30 dias');
    expect($texto)->toContain('Base legal');
    // Leitura pura: a vista não pode virar "não medido" em silêncio.
    expect($texto)->not->toContain('Não foi possível medir a retenção.');
});

it('UC-INDEX-03 · Arquivos/Index COFRE: espaço por disco e achados, ou o vazio declarado', function () {
    $page = arqAbrir('cofre');
    $comDisco = 'cabecalho,abas,cofre,cofre-discos,cofre-achados';
    $semDisco = 'cabecalho,abas,cofre';
    [$seq, $texto] = arqEstavel($page, 'cofre', [$comDisco, $semDisco], ['Achados', 'Nenhum arquivo guardado ainda.']);

    expect([$comDisco, $semDisco])->toContain($seq);
    expect($texto)->not->toContain('Não foi possível medir o cofre.');
    if ($seq === $semDisco) {
        expect($texto)->toContain('Nenhum arquivo guardado ainda.');
    } else {
        expect($texto)->toContain('Achados');
    }
});

it('UC-INDEX-02 · Arquivos/Index TRILHA: filtro e trilha read-only chegam ao DOM', function () {
    $page = arqAbrir('trilha');
    [$seq, $texto] = arqEstavel($page, 'trilha', ['cabecalho,abas,trilha-filtros,trilha'], ['Detalhe', 'Nenhum evento registrado ainda.']);

    expect($seq)->toBe('cabecalho,abas,trilha-filtros,trilha');
    expect(str_contains($texto, 'Detalhe') || str_contains($texto, 'Nenhum evento registrado ainda.'))->toBeTrue();
    // Append-only: nenhum controle de editar ou apagar linha dentro da trilha.
    $controles = (string) $page->script(
        '(() => Array.from(document.querySelectorAll(\'[data-contract="trilha"] button, [data-contract="trilha"] a\'))'
        . '.map((e) => (e.innerText + " " + (e.getAttribute("aria-label") || "")).toLowerCase())'
        . '.filter((t) => /editar|apagar|excluir/.test(t)).length + "")()'
    );
    expect($controles)->toBe('0');
});
