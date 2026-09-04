<?php

declare(strict_types=1);

use Modules\Fiscal\Http\Controllers\CockpitController;

uses(Tests\TestCase::class);

/**
 * UC-FCKP-13 · CU-FISC-16 — a declaração de procedência não pode mentir.
 *
 * POR QUE ESTE TESTE É ESTÁTICO (sem banco, sem HTTP)
 * ---------------------------------------------------
 * A regressão que ele defende não é de runtime — é de MANUTENÇÃO. Quando o #6541
 * trocou a lista mockada pelo NotasUnifiedService, o protótipo Cowork e o SDD §5.4.1
 * continuaram afirmando "demonstração" para `notas` e `savedViewCounts`: dois
 * documentos descrevendo um código que já tinha mudado. Se a declaração de
 * procedência puder envelhecer do mesmo jeito, a tela passa a mentir com selo — pior
 * que não ter selo nenhum, porque o selo é justamente o que pede confiança.
 *
 * Por ser estático, ele roda em TODAS as lanes (inclusive a advisory em SQLite, onde
 * `CockpitControllerTest` e `CockpitCacheTest` pulam por exigirem schema MySQL). Um
 * teste que pula sai com `0 failed` sem ter medido nada (LC-13) — e este contrato é
 * barato demais para ficar dependendo do CT 100 estar de pé.
 *
 * NÃO É TAUTOLÓGICO (proibicoes §5 2026-06-05): a asserção não sai da implementação.
 * Sai do CU-FISC-16 do SDD §6.5 — *"a contadora precisa conseguir dizer o que é
 * leitura real e o que é demonstração"* —, que só se cumpre se as três pontas
 * concordarem: o método que produz o dado, a linha que declara a origem, e a chave
 * que a tela sela.
 */

/**
 * A ponte declarada entre a superfície selada e o método que a alimenta.
 *
 * Esta constante é o CONTRATO, não um espelho do código: ela diz qual método deveria
 * estar por trás de cada chave marcada como demonstração. Trocar `mockSefazStatus`
 * por um serviço real sem mexer aqui e na declaração faz o teste falhar — que é
 * exatamente o alarme desejado.
 */
const SUPERFICIES_DE_DEMONSTRACAO = [
    'sefaz'    => 'mockSefazStatus',
    'eventos'  => 'mockEventos',
    'contabil' => 'mockContabilData',
    'writeoff' => 'mockWriteOffSummary',
];

const ORIGENS_VALIDAS = ['real', 'demonstracao'];

function procedenciaDoCockpit(): array
{
    $metodo = new ReflectionMethod(CockpitController::class, 'procedencia');
    $metodo->setAccessible(true);

    return $metodo->invoke(new CockpitController());
}

function metodosMockDoCockpit(): array
{
    $nomes = array_map(
        fn (ReflectionMethod $m) => $m->getName(),
        (new ReflectionClass(CockpitController::class))->getMethods(),
    );

    return array_values(array_filter($nomes, fn (string $n) => str_starts_with($n, 'mock')));
}

it('UC-FCKP-13 · CU-FISC-16 · todo método mock* do controller tem superfície declarada como demonstração', function () {
    $procedencia = procedenciaDoCockpit();
    $declaradosDemo = array_keys(array_filter(
        $procedencia,
        fn (array $p) => $p['origem'] === 'demonstracao',
    ));

    foreach (metodosMockDoCockpit() as $metodo) {
        $chave = array_search($metodo, SUPERFICIES_DE_DEMONSTRACAO, true);

        expect($chave)->not->toBeFalse(
            "o método {$metodo}() serve dado inventado e nenhuma chave de procedência o cobre — "
            . 'a tela mostraria o número sem selo, indistinguível de leitura real'
        );
        expect($declaradosDemo)->toContain(
            $chave,
            "a superfície '{$chave}' é alimentada por {$metodo}() mas está declarada como leitura real"
        );
    }
});

it('UC-FCKP-13 · CU-FISC-16 · nenhuma superfície declarada demonstração sobrevive ao método mock* sumir', function () {
    $procedencia = procedenciaDoCockpit();
    $metodosMock = metodosMockDoCockpit();

    foreach ($procedencia as $chave => $p) {
        if ($p['origem'] !== 'demonstracao') {
            continue;
        }

        expect(SUPERFICIES_DE_DEMONSTRACAO)->toHaveKey(
            $chave,
            "'{$chave}' está declarada como demonstração e o contrato não sabe qual método a alimenta"
        );
        expect($metodosMock)->toContain(
            SUPERFICIES_DE_DEMONSTRACAO[$chave],
            "'{$chave}' segue marcada como demonstração, mas o método que a alimentava não existe mais — "
            . 'se ela virou dado real, a declaração precisa mudar no mesmo diff'
        );
    }
});

it('UC-FCKP-13 · CU-FISC-16 · toda chave que a tela sela existe na declaração do controller', function () {
    $tsx = file_get_contents(base_path('resources/js/Pages/Fiscal/Cockpit.tsx'));
    expect($tsx)->not->toBeFalse('Cockpit.tsx não foi lido — o caminho mudou?');

    preg_match_all('/<SeloProcedencia[^>]*chave="([^"]+)"/', $tsx, $achados);
    $chavesDaTela = array_unique($achados[1]);

    // Controle positivo: se a regex parar de casar, o teste passaria por vacuidade —
    // `foreach` sobre lista vazia é sempre verde (LC-13, `0 failed` ≠ executou).
    expect(count($chavesDaTela))->toBeGreaterThanOrEqual(
        count(SUPERFICIES_DE_DEMONSTRACAO),
        'a tela sela menos superfícies do que existem mocks — ou a extração quebrou'
    );

    $declaradas = array_keys(procedenciaDoCockpit());

    foreach ($chavesDaTela as $chave) {
        expect($declaradas)->toContain(
            $chave,
            "a tela sela '{$chave}' e o controller não declara essa chave — o selo simplesmente "
            . 'não aparece, sem erro nenhum, e a superfície volta a parecer leitura real'
        );
    }
});

it('UC-FCKP-13 · CU-FISC-16 · toda superfície declara origem do vocabulário fechado e uma explicação', function () {
    foreach (procedenciaDoCockpit() as $chave => $p) {
        expect(ORIGENS_VALIDAS)->toContain(
            $p['origem'] ?? null,
            "'{$chave}' declara uma origem fora do vocabulário — a tela não saberia que tom usar"
        );
        expect(trim($p['explica'] ?? ''))->not->toBe(
            '',
            "'{$chave}' não explica de onde vem o dado; o selo abriria um tooltip vazio"
        );
    }
});
