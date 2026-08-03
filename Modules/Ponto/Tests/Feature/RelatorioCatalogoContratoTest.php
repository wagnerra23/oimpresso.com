<?php

declare(strict_types=1);

use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do catálogo de relatórios (`/ponto/relatorios`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Relatorios/Index.casos.md → UC-RELIDX-01..02
 *
 * Os UC derivam do SDD §6.5 `CU-PONTO-14` ("o catálogo de relatórios não promete o
 * que não entrega") + o fluxo F8 (§5.3). NÃO do `.tsx`.
 *
 * ── O que estes casos NÃO fazem ────────────────────────────────────────────
 * Não enumeram as 7 chaves indisponíveis de hoje, e não cravam o status 501.
 * Enumerar transformaria toda implementação legítima (uma `false` que vira `true`)
 * numa reprova; cravar o 501 reprovaria a melhoria de UX de trocá-lo por um 404 com
 * mensagem ou um redirect com toast. O assert é sobre a FORMA do contrato:
 * o indicador existe, o conjunto dos indisponíveis não é vazio enquanto `gerar()`
 * recusar, e a recusa não devolve sucesso.
 *
 * Não toca DB: o catálogo é um array estático no controller.
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 *
 * Contrato: resources/js/Pages/Ponto/Relatorios/Index.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\RelatorioController
 */

/**
 * GET com cabeçalho Inertia.
 *
 * MEDIDO na run 30779959209: `$this->get(...)` cru devolve o HTML da página (o
 * Inertia só responde JSON quando o request se declara Inertia), então
 * `->json('props...')` estoura "Invalid JSON was returned from the route" — o caso
 * morre sem exercer nada. Mesmo helper que o EspelhoContratoTest usa.
 */
function relInertiaGet(string $url)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get($url);
}

it('UC-RELIDX-01 · relatório não implementado aparece marcado como indisponível', function () {
    $this->actAsAdmin();

    $resp = relInertiaGet('/ponto/relatorios');
    $resp->assertStatus(200);

    $relatorios = collect($resp->json('props.relatorios') ?? []);

    // Pré-condição anti-vácuo: catálogo vazio faria "todos declaram disponibilidade"
    // ser verdade por vacuidade (proibicoes.md §5 2026-07-24 LC-13).
    expect($relatorios)->not->toBeEmpty('O catálogo precisa listar relatórios.');

    // (a) Todo item declara disponibilidade — sem isso o operador não tem como saber
    //     antes de clicar.
    $semFlag = $relatorios->filter(fn ($r) => ! array_key_exists('disponivel', $r));
    expect($semFlag)->toBeEmpty(
        'Todo relatório do catálogo tem de declarar se está disponível — CU-PONTO-14.'
    );

    // (b) Enquanto houver relatório não implementado, ele tem de estar declarado como
    //     indisponível. Não enumeramos QUAIS: implementar um é trocar false→true, e
    //     um assert por chave transformaria a entrega legítima em reprova.
    $indisponiveis = $relatorios->filter(fn ($r) => $r['disponivel'] === false);
    expect($indisponiveis)->not->toBeEmpty(
        'US-PONTO-006 (AFD legacy) e US-PONTO-009 (AEJ) seguem `_pendente_` no SPEC e '
        . '`RelatorioController@gerar()` é abort(501) para qualquer chave — logo o catálogo '
        . 'tem de declarar pelo menos um indisponível. Se este assert falhar, alguém marcou '
        . 'os relatórios como prontos sem o gerador existir (CU-PONTO-14).'
    );
});

it('UC-RELIDX-02 · nenhum relatório do catálogo entrega download sem aviso', function () {
    $this->actAsAdmin();

    $catalogo = collect(relInertiaGet('/ponto/relatorios')->json('props.relatorios') ?? []);
    $indisponivel = $catalogo->first(fn ($r) => $r['disponivel'] === false);

    // Pré-condição anti-vácuo: sem um indisponível no catálogo, não há o que exercer.
    expect($indisponivel)->not->toBeNull(
        'Precisa haver ao menos um relatório indisponível para exercer a recusa.'
    );

    $resp = $this->get("/ponto/relatorios/{$indisponivel['chave']}");

    // "Não é sucesso" — e não "é exatamente 501": trocar o 501 por 404-com-mensagem ou
    // por redirect-com-toast é correção legítima de UX, e não pode reprovar aqui.
    expect($resp->isSuccessful())->toBeFalse(
        "O relatório '{$indisponivel['chave']}' está marcado como indisponível, então pedir a "
        . 'geração dele NÃO pode responder com sucesso. Entregar arquivo vazio ou página em '
        . 'branco numa fiscalização é pior que recusar — CU-PONTO-14.'
    );
});
