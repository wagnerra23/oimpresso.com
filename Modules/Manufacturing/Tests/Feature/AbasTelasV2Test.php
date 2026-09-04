<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * A barra de abas das telas React do módulo tem que levar a telas React.
 *
 * ORIGEM (2026-09-04): o [F] clicou em "Configurações" dentro da tela React de Receitas e
 * caiu na tela Blade antiga. Medido: `Recipes.tsx` e `Report.tsx` traziam uma âncora crua
 * apontando pra rota legada do módulo, enquanto `Insumos.tsx` — feita depois — apontava pra
 * irmã em React. Três telas concordavam entre si e duas contradiziam; nenhum teste via isso.
 *
 * O QUE ESTE TESTE **NÃO** É: ele não exige cutover. As rotas Blade legadas seguem vivas e
 * intocadas de propósito (`RUNBOOK-settings.md` §"Rota nova, sem cutover") — trocar o que o
 * MENU LATERAL abre é decisão [W] e não está aqui. O contrato defendido é mais estreito:
 * uma vez DENTRO das telas novas, a navegação entre elas não pode jogar o usuário pra fora
 * do SPA.
 *
 * ORÁCULO das rotas é o registry em runtime (`Route::getRoutes()`), não leitura do
 * `Routes/web.php` — um href pode estar escrito certo e apontar pra rota que não existe.
 *
 * @covers-us US-MANU-002, US-MANU-003, US-MANU-005
 */

/** Telas Inertia do módulo que renderizam a barra `mfg-tabs`. */
const TELAS_COM_ABAS = ['Recipes', 'Report', 'Settings', 'Insumos'];

/** Rotas Blade legadas do módulo — legítimas como URL, proibidas como destino de aba. */
const ROTAS_LEGADAS = [
    '/manufacturing/settings',
    '/manufacturing/report',
    '/manufacturing/production',
];

function fonteDaTela(string $tela): string
{
    $caminho = base_path("resources/js/Pages/Manufacturing/{$tela}.tsx");

    expect(file_exists($caminho))->toBeTrue("A tela {$tela}.tsx sumiu de resources/js/Pages/Manufacturing/.");

    return (string) file_get_contents($caminho);
}

/** Extrai o bloco `<nav className="mfg-tabs" ...>...</nav>` — só a barra de abas. */
function blocoDeAbas(string $fonte, string $tela): string
{
    $inicio = strpos($fonte, '<nav className="mfg-tabs"');
    expect($inicio)->not->toBeFalse("{$tela}.tsx não tem a barra <nav className=\"mfg-tabs\">.");

    $fim = strpos($fonte, '</nav>', (int) $inicio);
    expect($fim)->not->toBeFalse("{$tela}.tsx abre a barra de abas e não fecha.");

    return substr($fonte, (int) $inicio, ((int) $fim) - ((int) $inicio));
}

/** Hrefs declarados DENTRO da barra de abas. */
function hrefsDasAbas(string $bloco): array
{
    preg_match_all('~href="(/manufacturing[^"]*)"~', $bloco, $m);

    return array_values(array_unique($m[1]));
}

it('nenhuma aba das telas React aponta pra rota Blade legada', function () {
    $violacoes = [];

    foreach (TELAS_COM_ABAS as $tela) {
        $bloco = blocoDeAbas(fonteDaTela($tela), $tela);

        foreach (hrefsDasAbas($bloco) as $href) {
            if (in_array($href, ROTAS_LEGADAS, true)) {
                $violacoes[] = "{$tela}.tsx -> {$href}";
            }
        }
    }

    expect($violacoes)->toBe(
        [],
        'Aba de tela React apontando pra tela Blade: '.implode(', ', $violacoes)
        .'. A aba tem que levar à tela IRMÃ em React (/manufacturing/v2/*).'
    );
});

it('toda aba usa Link do Inertia, nunca âncora crua (que sai do SPA)', function () {
    $violacoes = [];

    foreach (TELAS_COM_ABAS as $tela) {
        $bloco = blocoDeAbas(fonteDaTela($tela), $tela);

        // `<a className="mfg-tab"` faz reload de página inteira e derruba o estado do SPA.
        if (str_contains($bloco, '<a className="mfg-tab"')) {
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

    // Controle positivo: se o registry vier vazio ou sem o módulo, o teste abaixo passaria
    // por vacuidade — e um verde desses não vale nada.
    expect($registradas)->toContain(
        '/manufacturing/v2/settings',
        'O registry não trouxe as rotas do Manufacturing — a medição está inválida, não verde.'
    );

    $quebrados = [];

    foreach (TELAS_COM_ABAS as $tela) {
        $bloco = blocoDeAbas(fonteDaTela($tela), $tela);

        foreach (hrefsDasAbas($bloco) as $href) {
            if (! in_array($href, $registradas, true)) {
                $quebrados[] = "{$tela}.tsx -> {$href}";
            }
        }
    }

    expect($quebrados)->toBe(
        [],
        'Aba apontando pra rota que não existe: '.implode(', ', $quebrados)
    );
});

it('a tela de Insumos é alcançável a partir das telas irmãs', function () {
    // US-MANU-005 subiu em 2026-09-04 sem nenhuma entrada: nem menu lateral, nem aba. A tela
    // existia e só abria digitando a URL. Este assert é o que impede repetir isso.
    $semLink = [];

    foreach (['Recipes', 'Report', 'Settings'] as $tela) {
        $bloco = blocoDeAbas(fonteDaTela($tela), $tela);

        if (! in_array('/manufacturing/v2/insumos', hrefsDasAbas($bloco), true)) {
            $semLink[] = $tela;
        }
    }

    expect($semLink)->toBe(
        [],
        'Telas sem aba pra Insumos: '.implode(', ', $semLink).'. A tela ficaria inalcançável.'
    );
});
