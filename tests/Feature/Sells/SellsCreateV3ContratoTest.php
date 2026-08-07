<?php

// @covers-us US-SELL-058

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Contrato da tela de venda V3 (preview de design) — `GET /sells/create-v3`.
 *
 * UC-V301 — a rota de leitura existe e é servida por SellsV3Controller@create.
 * UC-V302 — NENHUMA rota de escrita aponta pro SellsV3Controller (a tela não grava).
 * UC-V303 — invariante de isolamento: o V3 não encosta nos artefatos da tela viva.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A tela V3 só tem razão de existir enquanto for PARALELA. A restrição de negócio
 * que a originou ([L] 2026-08-06) é que `Sells/Create.tsx` — operada pela ROTA LIVRE,
 * 99% do volume — não pode mudar. O dia em que o V3 começar a importar componente da
 * tela viva, ou ganhar um POST, a premissa cai em silêncio e ninguém percebe: o diff
 * seguinte parece inocente.
 *
 * Então o contrato guardado aqui não é "a tela renderiza bonito" — é a FRONTEIRA.
 * Derivado do §Contrato de não-regressão da US-SELL-058 e do §Fronteira do
 * CreateV3.casos.md, NUNCA lido do .tsx (teste derivado do código é tautológico —
 * memory/proibicoes.md §5, 2026-06-05).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ANTI-VÁCUO
 * ─────────────────────────────────────────────────────────────────────────────
 * "Não existe rota de escrita" e "não referencia a tela viva" são asserções sobre
 * AUSÊNCIA — passam verde de graça se o alvo nem existir (controller não roteado,
 * arquivo ausente, regex que não casa). Por isso cada UC abre com um controle
 * POSITIVO que prova que o alvo está lá antes de afirmar o que ele não tem.
 * (memory/proibicoes.md §5, 2026-07-24: verde por não-execução.)
 */

const V3_CONTROLLER_PATH = 'app/Http/Controllers/SellsV3Controller.php';
const V3_PAGE_PATH = 'resources/js/Pages/Sells/CreateV3.tsx';
const VIVA_PAGE_PATH = 'resources/js/Pages/Sells/Create.tsx';
const VIVA_CONTROLLER_PATH = 'app/Http/Controllers/SellPosController.php';

/** @return list<\Illuminate\Routing\Route> */
function rotasDoV3(): array
{
    $encontradas = [];
    foreach (Route::getRoutes() as $rota) {
        if (str_contains($rota->getActionName(), 'SellsV3Controller')) {
            $encontradas[] = $rota;
        }
    }

    return $encontradas;
}

it('UC-V301 — serve GET /sells/create-v3 por SellsV3Controller@create', function () {
    // Controle positivo: o roteador está carregado de verdade.
    expect(count(Route::getRoutes()->getRoutes()))->toBeGreaterThan(100);

    $rota = Route::getRoutes()->getByName('sells.create-v3');

    expect($rota)->not->toBeNull();
    expect($rota->uri())->toBe('sells/create-v3');
    expect($rota->methods())->toContain('GET');
    expect($rota->getActionName())->toContain('SellsV3Controller@create');
});

it('UC-V302 — nenhuma rota de escrita aponta pro SellsV3Controller', function () {
    $rotas = rotasDoV3();

    // Controle positivo: o controller ESTÁ roteado. Sem isto, "0 rotas de escrita"
    // seria verdade também num mundo onde o controller não existe.
    expect($rotas)->not->toBeEmpty();

    $verbosDeEscrita = [];
    foreach ($rotas as $rota) {
        foreach ($rota->methods() as $metodo) {
            if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $verbosDeEscrita[] = $metodo.' '.$rota->uri();
            }
        }
    }

    expect($verbosDeEscrita)->toBe([]);

    // E o controller não carrega os métodos de escrita do padrão resource.
    $fonte = file_get_contents(base_path(V3_CONTROLLER_PATH));
    expect($fonte)->toBeString()->not->toBeEmpty();
    expect($fonte)->not->toContain('function store(');
    expect($fonte)->not->toContain('function update(');
    expect($fonte)->not->toContain('function destroy(');
});

it('UC-V303 — o V3 não encosta nos artefatos da tela viva (fronteira)', function () {
    // Controle positivo: os quatro arquivos existem e têm conteúdo. Uma asserção de
    // "não referencia" sobre arquivo ausente passaria por vácuo.
    foreach ([V3_CONTROLLER_PATH, V3_PAGE_PATH, VIVA_PAGE_PATH, VIVA_CONTROLLER_PATH] as $caminho) {
        expect(file_exists(base_path($caminho)))->toBeTrue("esperado existir: {$caminho}");
        expect(trim((string) file_get_contents(base_path($caminho))))->not->toBeEmpty();
    }

    // O controller do preview não delega nem estende o da tela viva.
    //
    // Medido no que o PARSER vê, não no texto cru: o docblock do V3 CITA
    // "SellPosController@create" para explicar por que a tela existe — prosa legítima,
    // e um `toContain` sobre o arquivo inteiro reprovaria a própria documentação.
    // Acoplamento real é `use`/`extends`/`new`/`::` — e isso vive fora de comentário.
    $codigoSemComentario = implode('', array_map(
        static fn ($token) => is_array($token) ? $token[1] : $token,
        array_filter(
            token_get_all(file_get_contents(base_path(V3_CONTROLLER_PATH))),
            static fn ($token) => ! is_array($token)
                || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true),
        ),
    ));

    // Controle positivo do próprio stripper: o corpo real sobreviveu ao filtro.
    expect($codigoSemComentario)->toContain('class SellsV3Controller');
    expect($codigoSemComentario)->toContain('function create(');

    expect($codigoSemComentario)->not->toContain('SellPosController');

    // A Page do preview não importa a Page viva nem os _components que ela consome.
    // Asserção sobre o ESPECIFICADOR de import — não sobre o texto do arquivo, senão
    // um comentário citando o próprio caminho (`Pages/Sells/CreateV3.tsx`) casaria com
    // "Pages/Sells/Create" por substring e reprovaria sozinho.
    preg_match_all(
        '/^\s*import\s[^;]*?from\s+[\'"]([^\'"]+)[\'"]/m',
        file_get_contents(base_path(V3_PAGE_PATH)),
        $matches,
    );
    $origens = $matches[1] ?? [];

    // Controle positivo: a Page importa alguma coisa (regex casou de fato).
    expect($origens)->not->toBeEmpty();

    foreach ($origens as $origem) {
        expect(str_ends_with($origem, '/Create'))->toBeFalse("importa a tela viva: {$origem}");
        expect(str_contains($origem, 'Sells/_components'))->toBeFalse("importa _components da viva: {$origem}");
    }
});
