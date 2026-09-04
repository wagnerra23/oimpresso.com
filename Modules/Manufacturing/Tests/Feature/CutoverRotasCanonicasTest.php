<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * O módulo de Fabricação serve as telas React nos ENDEREÇOS CANÔNICOS, sem rota alternativa.
 *
 * ORIGEM (2026-09-04): o [F] clicou em "Configurações" dentro da tela React de Receitas e caiu
 * na tela Blade antiga. A investigação achou o quadro maior — as ondas US-MANU-002..005 tinham
 * subido em rotas PARALELAS (`/manufacturing/v2/*`), e o menu lateral, que aponta pras ações
 * canônicas, seguia servindo Blade em 3 das 4 entradas. A tela de Insumos não tinha entrada
 * nenhuma: só abria digitando a URL.
 *
 * Pedido [F], textual: *"colocar todo o módulo de Fabricação já em produção, com os links e
 * vínculos reais, sem rotas alternativas"*, sobre a aprovação [W] da família em produção.
 *
 * PRÉ-CONDIÇÃO MEDIDA antes de cortar: a regra "F5 CUTOVER sem aviso prévio cliente + canary"
 * (proibicoes.md) nomeia a ROTA LIVRE — e [F] confirmou 2026-09-04 que ela **não usa**
 * Fabricação. Sem cliente na tela, a população da regra é vazia aqui.
 *
 * O QUE O CUTOVER **NÃO** FEZ: remover ou renomear rota Blade. Nenhuma sumiu; o Blade responde
 * no MESMO endereço com `?legacy=1`, e o ramo AJAX que alimenta o DataTables segue intacto.
 * É o Non-Goal do `Recipes.charter.md` — *"não remove nem renomeia rota Blade legacy"* — que
 * continua valendo e é defendido aqui.
 *
 * ORÁCULO das rotas é o registry em runtime (`Route::getRoutes()`), nunca leitura do
 * `Routes/web.php`: um href pode estar escrito certo e apontar pra rota que não existe.
 *
 * @covers-us US-MANU-002, US-MANU-003, US-MANU-004, US-MANU-005
 */

/** Telas Inertia do módulo que renderizam a barra `mfg-tabs`. */
function mfgTelasComAbas(): array
{
    return ['Recipes', 'Report', 'Settings', 'Insumos'];
}

/** Os endereços canônicos do módulo, um por tela. */
function mfgRotasCanonicas(): array
{
    return [
        '/manufacturing/recipe',
        '/manufacturing/production',
        '/manufacturing/report',
        '/manufacturing/settings',
        '/manufacturing/insumos',
    ];
}

function mfgFonteDaTela(string $tela): string
{
    $caminho = base_path("resources/js/Pages/Manufacturing/{$tela}.tsx");

    expect(file_exists($caminho))->toBeTrue("A tela {$tela}.tsx sumiu de resources/js/Pages/Manufacturing/.");

    return (string) file_get_contents($caminho);
}

/** Extrai o bloco `<nav className="mfg-tabs" ...>...</nav>` — só a barra de abas. */
function mfgBlocoDeAbas(string $fonte, string $tela): string
{
    $inicio = strpos($fonte, '<nav className="mfg-tabs"');
    expect($inicio)->not->toBeFalse("{$tela}.tsx não tem a barra <nav className=\"mfg-tabs\">.");

    $fim = strpos($fonte, '</nav>', (int) $inicio);
    expect($fim)->not->toBeFalse("{$tela}.tsx abre a barra de abas e não fecha.");

    return substr($fonte, (int) $inicio, ((int) $fim) - ((int) $inicio));
}

/** Hrefs declarados DENTRO da barra de abas. */
function mfgHrefsDasAbas(string $bloco): array
{
    preg_match_all('~href="(/manufacturing[^"]*)"~', $bloco, $m);

    return array_values(array_unique($m[1]));
}

it('nenhuma aba aponta pra rota alternativa /v2/ — o cutover levou tudo pro canônico', function () {
    $violacoes = [];

    foreach (mfgTelasComAbas() as $tela) {
        foreach (mfgHrefsDasAbas(mfgBlocoDeAbas(mfgFonteDaTela($tela), $tela)) as $href) {
            if (str_contains($href, '/v2/')) {
                $violacoes[] = "{$tela}.tsx -> {$href}";
            }
        }
    }

    expect($violacoes)->toBe(
        [],
        'Aba apontando pra rota alternativa: '.implode(', ', $violacoes)
        .'. O pedido [F] foi "sem rotas alternativas" — /v2/* é 301 pro canônico, não destino.'
    );
});

it('toda aba usa Link do Inertia, nunca âncora crua (que sai do SPA)', function () {
    $violacoes = [];

    foreach (mfgTelasComAbas() as $tela) {
        // `<a className="mfg-tab"` faz reload de página inteira e derruba o estado do SPA.
        if (str_contains(mfgBlocoDeAbas(mfgFonteDaTela($tela), $tela), '<a className="mfg-tab"')) {
            $violacoes[] = $tela;
        }
    }

    expect($violacoes)->toBe(
        [],
        'Âncora crua na barra de abas de: '.implode(', ', $violacoes).'. Use <Link> do Inertia.'
    );
});

it('todo destino de aba existe no registry de rotas em runtime', function () {
    $registradas = collect(Route::getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods(), true))
        ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
        ->unique()
        ->all();

    // Controle positivo: sem isto, um registry vazio faria os asserts abaixo passarem por
    // vacuidade — e verde por não-medição não vale nada (LC-13).
    expect($registradas)->toContain(
        '/manufacturing/settings',
        'O registry não trouxe as rotas do Manufacturing — a medição está inválida, não verde.'
    );

    $quebrados = [];

    foreach (mfgTelasComAbas() as $tela) {
        foreach (mfgHrefsDasAbas(mfgBlocoDeAbas(mfgFonteDaTela($tela), $tela)) as $href) {
            if (! in_array($href, $registradas, true)) {
                $quebrados[] = "{$tela}.tsx -> {$href}";
            }
        }
    }

    expect($quebrados)->toBe([], 'Aba apontando pra rota que não existe: '.implode(', ', $quebrados));
});

it('os 5 endereços canônicos do módulo estão registrados', function () {
    $registradas = collect(Route::getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods(), true))
        ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
        ->unique()
        ->all();

    foreach (mfgRotasCanonicas() as $rota) {
        expect($registradas)->toContain($rota, "O endereço canônico {$rota} não está registrado.");
    }
});

it('as rotas /v2/* seguem existindo como redirect — link salvo não quebra', function () {
    $v2 = collect(Route::getRoutes())
        ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
        ->filter(fn ($u) => str_starts_with($u, '/manufacturing/v2/'))
        ->unique()
        ->values()
        ->all();

    // Sumir com elas quebraria favorito, histórico e link colado em conversa. 301 preserva
    // sem manter dois destinos vivos.
    foreach (['production', 'report', 'settings', 'insumos'] as $tela) {
        expect($v2)->toContain(
            "/manufacturing/v2/{$tela}",
            "O redirect /manufacturing/v2/{$tela} sumiu — link salvo passa a dar 404."
        );
    }
});

it('o escape ?legacy=1 que os controllers ANUNCIAM existe de fato nos três', function () {
    // Mesmo espírito do UC-RECIPE-06 (Wave29): mecanismo que anuncia saída tem que honrá-la,
    // senão a promessa vira instrução falsa pra próxima sessão.
    $alvos = [
        'ProductionController' => 2,   // index() e getManufacturingReport()
        'SettingsController' => 1,     // index()
        'RecipeController' => 1,       // index() — desde a US-MANU-001
    ];

    foreach ($alvos as $classe => $minimo) {
        $fonte = (string) file_get_contents(
            base_path("Modules/Manufacturing/Http/Controllers/{$classe}.php")
        );

        expect(substr_count($fonte, "request()->boolean('legacy')"))->toBeGreaterThanOrEqual(
            $minimo,
            "{$classe} anuncia ?legacy=1 em menos lugares do que os {$minimo} que fazem cutover."
        );
    }
});

it('nenhuma rota Blade do módulo foi removida no cutover', function () {
    // Non-Goal vivo do Recipes.charter.md: "não remove nem renomeia rota Blade legacy".
    // O cutover trocou o que a ação RENDERIZA, nunca o endereço.
    $registradas = collect(Route::getRoutes())
        ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
        ->unique()
        ->all();

    foreach ([
        '/manufacturing/recipe/create',
        '/manufacturing/production/create',
        '/manufacturing/add-ingredient',
        '/manufacturing/get-recipe-details',
    ] as $rota) {
        expect($registradas)->toContain($rota, "Rota Blade {$rota} sumiu — o cutover não podia removê-la.");
    }
});

it('a tela de Insumos é alcançável a partir das telas irmãs', function () {
    // US-MANU-005 subiu em 2026-09-04 sem menu e sem aba: a tela existia e só abria digitando
    // a URL. Este assert é o que impede repetir isso.
    $semLink = [];

    foreach (['Recipes', 'Report', 'Settings'] as $tela) {
        $hrefs = mfgHrefsDasAbas(mfgBlocoDeAbas(mfgFonteDaTela($tela), $tela));

        if (! in_array('/manufacturing/insumos', $hrefs, true)) {
            $semLink[] = $tela;
        }
    }

    expect($semLink)->toBe(
        [],
        'Telas sem aba pra Insumos: '.implode(', ', $semLink).'. A tela ficaria inalcançável.'
    );
});

it('o menu lateral tem entrada pra Insumos', function () {
    // O menu é Blade e é por onde o usuário realmente entra — a aba só ajuda quem já está
    // dentro do módulo. Sem esta entrada, Insumos volta a ser inalcançável pelo caminho normal.
    $sidebar = (string) file_get_contents(
        base_path('Modules/Manufacturing/Resources/views/layouts/partials/sidebar.blade.php')
    );

    expect($sidebar)->toContain(
        "'insumos'",
        'O menu lateral do módulo não referencia a ação de Insumos.'
    );
});
