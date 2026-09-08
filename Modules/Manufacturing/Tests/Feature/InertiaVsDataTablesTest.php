<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Navegar entre as abas não pode devolver JSON do DataTables.
 *
 * BUG EM PRODUÇÃO (2026-09-04, achado por [M] clicando nas abas depois do cutover):
 *
 *   "All Inertia requests must receive a valid Inertia response, however a plain JSON
 *    response was received."
 *
 * MECANISMO: o cliente do Inertia manda `X-Requested-With: XMLHttpRequest`, logo
 * `request()->ajax()` é TRUE numa navegação SPA. Os controllers do módulo tinham o ramo
 * do DataTables guardado SÓ por `ajax()`, então a visita do Inertia caía nele e recebia
 * `{"draw":0,"recordsTotal":...}` em vez de uma resposta Inertia.
 *
 * POR QUE NÃO APARECEU ANTES DO CUTOVER: só se chega nessas ações por navegação SPA
 * quando uma aba aponta pra elas. Antes, as abas iam pros endereços `/v2/*`, cujos métodos
 * (`indexV2`/`reportV2`) não têm ramo AJAX. O cutover ligou as abas aos endereços
 * canônicos — e o ramo AJAX passou a ser alcançado.
 *
 * O GUARDA é o idioma já canônico no repo (mesmo do middleware `AdminSidebarMenu`,
 * documentado em `Modules/Financeiro/.../CoworkSidebarController` §PEGADINHA CRÍTICA):
 *
 *     if (request()->ajax() && ! request()->header('X-Inertia')) { ... }
 *
 * NÃO é teste de rota nem de fonte de tela — é do CONTRATO da resposta.
 *
 * @covers-us US-MANU-002, US-MANU-003, US-MANU-004, US-MANU-005
 */
it('as ações do cutover guardam o ramo DataTables contra requisição Inertia', function () {
    // Ações que (a) renderizam Inertia e (b) têm ramo AJAX no mesmo método. `destroy()` tem
    // ramo AJAX e NÃO renderiza Inertia — fora do escopo de propósito.
    $alvos = [
        'RecipeController' => 1,      // index()
        'ProductionController' => 2,  // index() + getManufacturingReport()
    ];

    $desprotegidos = [];

    foreach ($alvos as $classe => $esperado) {
        $fonte = (string) file_get_contents(
            base_path("Modules/Manufacturing/Http/Controllers/{$classe}.php")
        );

        $comGuarda = substr_count($fonte, "request()->ajax() && ! request()->header('X-Inertia')");

        if ($comGuarda < $esperado) {
            $desprotegidos[] = "{$classe} ({$comGuarda} de {$esperado})";
        }
    }

    expect($desprotegidos)->toBe(
        [],
        'Ramo DataTables sem guarda de Inertia em: '.implode(', ', $desprotegidos)
        .'. Navegar pra essa tela por aba devolve JSON e estoura o Inertia.'
    );
});

it('todo método que serve endereço canônico guarda seu ramo AJAX', function () {
    // ⚠️ A 1ª versão deste assert varria por método procurando `Inertia::render` junto de
    // `ajax()` cru — e NÃO MORDIA. Motivo: `ProductionController@index` DELEGA pro `indexV2`,
    // então o `render` está noutro método e a varredura não via nada. Assert que não morde é
    // pior que assert nenhum, porque dá confiança falsa. Bite test flagrou; trocado por este.
    //
    // Aqui a lista é o conjunto de métodos que servem os ENDEREÇOS CANÔNICOS (os que uma
    // navegação Inertia alcança). `destroy()` tem ramo AJAX e fica fora de propósito: nenhuma
    // aba navega pra ele.
    $servemInertia = [
        'RecipeController' => ['index', 'insumos'],
        'ProductionController' => ['index', 'getManufacturingReport'],
        'SettingsController' => ['index'],
    ];

    $problemas = [];

    foreach ($servemInertia as $classe => $metodos) {
        $linhas = file(base_path("Modules/Manufacturing/Http/Controllers/{$classe}.php"));
        $atual = null;

        foreach ($linhas as $linha) {
            if (preg_match('~public function (\w+)~', $linha, $m)) {
                $atual = $m[1];
            }

            if (! in_array($atual, $metodos, true)) {
                continue;
            }

            // Pula COMENTÁRIO: as notas que explicam a pegadinha citam `request()->ajax()`
            // sem o guarda na mesma linha, e sem isto o assert acusa o código já corrigido
            // (aconteceu no bite test — mesma família do ratchet que leu o próprio comentário).
            $limpa = ltrim($linha);
            if (str_starts_with($limpa, '//') || str_starts_with($limpa, '*') || str_starts_with($limpa, '/*')) {
                continue;
            }

            if (str_contains($linha, 'request()->ajax()')
                && ! str_contains($linha, "header('X-Inertia')")) {
                $problemas[] = "{$classe}::{$atual}";
            }
        }
    }

    expect(array_unique($problemas))->toBe(
        [],
        'Método que serve endereço canônico com ramo ajax() cru: '.implode(', ', array_unique($problemas))
        .'. A visita SPA cai no ramo AJAX e recebe JSON em vez de resposta Inertia.'
    );
});
