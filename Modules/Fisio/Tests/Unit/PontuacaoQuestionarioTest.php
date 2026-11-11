<?php

declare(strict_types=1);
// @covers-us US-FISIO-011
// Cobre, na regra pura, UC-FPS-13, UC-FPS-14, UC-FPS-40 e UC-FPS-41 (Fisio/Pacientes/Show.casos.md).

use Modules\Fisio\Services\Questionarios\CatalogoQuestionarios;
use Modules\Fisio\Services\Questionarios\PontuacaoQuestionario;
use Modules\Fisio\Services\Questionarios\RespostaInvalidaException;

/**
 * Pontuação dos questionários de uso livre — serviço PURO (sem DB, sem sessão), roda em qualquer lane.
 *
 * Âncora do contrato: a ESTRUTURA publicada de cada instrumento (ADR 0437 §8; fontes no docblock de
 * PontuacaoQuestionario): RMDQ = nº de "sim" em 24 itens; PSFS = média das atividades 0–10
 * (Stratford 1995); LEFS = soma de 20 itens 0–4 (Binkley 1999); SPADI = subescalas ÷50 e ÷80 e
 * total ÷130 (Roach 1991). Os números esperados abaixo saem dessas regras, contados à mão — não do
 * código. Conteúdo clínico aguarda revisão da Dra. Dejeane.
 */
function upqItens(int $quantos, int|callable $valor): array
{
    $itens = [];
    for ($n = 1; $n <= $quantos; $n++) {
        $itens[$n] = is_callable($valor) ? $valor($n) : $valor;
    }

    return ['itens' => $itens];
}

function upq(string $instrumento, array $respostas): array
{
    return (new PontuacaoQuestionario())->pontuar($instrumento, $respostas);
}

it('UC-FPS-13 · EVA devolve o valor marcado (0 a 10)', function () {
    expect(upq('eva', ['valor' => 7])['escore'])->toBe(7.0)
        ->and(upq('eva', ['valor' => '3.5'])['escore'])->toBe(3.5)
        ->and(upq('eva', ['valor' => 0])['escore'])->toBe(0.0);
});

it('UC-FPS-13 · RMDQ conta os "sim" dos 24 itens (booleano ou 0/1)', function () {
    // itens 1, 5 e 24 marcados = 3
    $r = upq('rmdq', upqItens(24, fn (int $n) => in_array($n, [1, 5, 24], true)));
    expect($r['escore'])->toBe(3.0)
        ->and(upq('rmdq', upqItens(24, 1))['escore'])->toBe(24.0)
        ->and(upq('rmdq', upqItens(24, 0))['escore'])->toBe(0.0);
});

it('UC-FPS-13 · PSFS é a média das notas das atividades', function () {
    $r = upq('psfs', ['atividades' => [
        ['descricao' => 'subir escada', 'nota' => 4],
        ['descricao' => 'correr 5 km', 'nota' => 2],
        ['descricao' => 'carregar o filho', 'nota' => 7],
    ]]);
    // (4 + 2 + 7) / 3 = 4,333… → 4,33
    expect($r['escore'])->toBe(4.33)
        ->and($r['respostas']['atividades'][1])->toBe(['descricao' => 'correr 5 km', 'nota' => 2]);
});

it('UC-FPS-13 · LEFS soma os 20 itens (0 a 80)', function () {
    expect(upq('lefs', upqItens(20, 4))['escore'])->toBe(80.0)
        ->and(upq('lefs', upqItens(20, fn (int $n) => $n <= 10 ? 3 : 1))['escore'])->toBe(40.0); // 10×3 + 10×1
});

it('UC-FPS-13 · SPADI: dor ÷50, incapacidade ÷80 e total ÷130, em %', function () {
    // dor (itens 1–5) = 5×6 = 30 → 60%; incapacidade (6–13) = 8×2 = 16 → 20%; total 46/130 = 35,38%
    $r = upq('spadi', upqItens(13, fn (int $n) => $n <= 5 ? 6 : 2));
    expect($r['detalhe'])->toBe(['dor' => 60.0, 'incapacidade' => 20.0])
        ->and($r['escore'])->toBe(35.38);
});

it('UC-FPS-14 · item faltando ou fora da faixa é recusado, apontando o campo', function (string $instr, array $resp, string $campo) {
    try {
        upq($instr, $resp);
        $this->fail('esperava RespostaInvalidaException');
    } catch (RespostaInvalidaException $e) {
        expect($e->campo)->toBe($campo);
    }
})->with([
    'EVA acima de 10' => ['eva', ['valor' => 11], 'respostas.valor'],
    'EVA vazia' => ['eva', ['valor' => ''], 'respostas.valor'],
    'RMDQ com 23 itens' => ['rmdq', ['itens' => array_slice(upqItens(24, 0)['itens'], 0, 23, true)], 'respostas.itens.24'],
    'RMDQ com valor 2' => ['rmdq', upqItens(24, fn (int $n) => $n === 3 ? 2 : 0), 'respostas.itens.3'],
    'LEFS com 5 num item' => ['lefs', upqItens(20, fn (int $n) => $n === 7 ? 5 : 0), 'respostas.itens.7'],
    'LEFS com item a mais' => ['lefs', ['itens' => upqItens(20, 1)['itens'] + [21 => 1]], 'respostas.itens'],
    'SPADI com decimal' => ['spadi', upqItens(13, fn (int $n) => $n === 2 ? 2.5 : 0), 'respostas.itens.2'],
    'PSFS sem atividade' => ['psfs', ['atividades' => []], 'respostas.atividades'],
    'PSFS com 6 atividades' => ['psfs', ['atividades' => array_fill(0, 6, ['descricao' => 'x', 'nota' => 1])], 'respostas.atividades'],
    'PSFS sem descrição' => ['psfs', ['atividades' => [['descricao' => ' ', 'nota' => 5]]], 'respostas.atividades.0.descricao'],
]);

it('UC-FPS-14 · questionário bloqueado por licença ou desconhecido é recusado', function (string $instr) {
    expect(fn () => upq($instr, ['valor' => 1]))->toThrow(RespostaInvalidaException::class);
})->with(['odi', 'ndi', 'koos', 'quickdash', 'womac', 'tampa']);

it('o catálogo tem os 5 livres e os 4 de fibromialgia, nenhum enunciado inventado e referência em todos', function () {
    $todos = CatalogoQuestionarios::todos();
    expect(array_keys($todos))->toBe([...CatalogoQuestionarios::LIVRES, ...CatalogoQuestionarios::FIBROMIALGIA])
        ->and(array_intersect(array_keys($todos), CatalogoQuestionarios::BLOQUEADOS))->toBe([]);

    foreach ($todos as $codigo => $def) {
        expect($def['referencias'])->not->toBeEmpty("{$codigo} sem referência");
        foreach ($def['itens'] as $item) {
            // Texto literal da versão BR só entra conferido com a publicação (ADR 0437 §8).
            expect($item['texto'] === null || $def['texto_pendente'] === false)->toBeTrue("{$codigo} tem enunciado sem conferência");
        }
    }

    expect(count($todos['rmdq']['itens']))->toBe(24)
        ->and(count($todos['lefs']['itens']))->toBe(20)
        ->and(count($todos['spadi']['itens']))->toBe(13)
        ->and(count($todos['fiqr']['itens']))->toBe(21)
        ->and(count($todos['acr2016']['itens']))->toBe(26);

    // Versão do instrumento sempre declarada nos de fibromialgia (P-03: o estudo não mistura versões).
    foreach (CatalogoQuestionarios::FIBROMIALGIA as $codigo) {
        expect($todos[$codigo]['versao'])->toBeString()->not->toBe('');
    }
});

/*
 * Fibromialgia (etapa 07, P-03). Números esperados contados à mão a partir das regras publicadas:
 * FIQR (Bennett et al. 2009, Arthritis Res Ther 11:R120): função = soma 1–9 ÷ 3, impacto = soma
 * 10–11, sintomas = soma 12–21 ÷ 2, total = soma. ACR 2016 (Wolfe et al. 2016, Semin Arthritis
 * Rheum 46:319-29): (WPI ≥ 7 e SSS ≥ 5) ou (WPI 4–6 e SSS ≥ 9), dor em ≥ 4 de 5 regiões
 * (mandíbula, tórax e abdome fora) e sintomas há ≥ 3 meses.
 */

/** ACR 2016: áreas com dor (números 1–19), SSS e duração. */
function upqAcr(array $areas, int $fadiga, int $sono, int $cognicao, array $sim6m, int $tresMeses): array
{
    $itens = [];
    for ($n = 1; $n <= 19; $n++) {
        $itens[$n] = in_array($n, $areas, true) ? 1 : 0;
    }
    $itens[20] = $fadiga;
    $itens[21] = $sono;
    $itens[22] = $cognicao;
    foreach ([23, 24, 25] as $i => $n) {
        $itens[$n] = $sim6m[$i] ?? 0;
    }
    $itens[26] = $tresMeses;

    return ['itens' => $itens];
}

it('UC-FPS-40 · FIQR: limites 0 e 100', function () {
    expect(upq('fiqr', upqItens(21, 0))['escore'])->toBe(0.0)
        ->and(upq('fiqr', upqItens(21, 10))['escore'])->toBe(100.0)
        ->and(upq('fiqr', upqItens(21, 10))['detalhe'])->toBe(['funcao' => 30.0, 'impacto' => 20.0, 'sintomas' => 50.0]);
});

it('UC-FPS-40 · FIQR: domínios com pesos diferentes (÷3, ×1, ÷2)', function () {
    // função 9×6 = 54 ÷ 3 = 18; impacto 5 + 5 = 10; sintomas 10×7 = 70 ÷ 2 = 35; total 63
    $r = upq('fiqr', upqItens(21, fn (int $n) => $n <= 9 ? 6 : ($n <= 11 ? 5 : 7)));
    expect($r['detalhe'])->toBe(['funcao' => 18.0, 'impacto' => 10.0, 'sintomas' => 35.0])
        ->and($r['escore'])->toBe(63.0);

    // função 1+2+…+9 = 45 ÷ 3 = 15; impacto 3 + 4 = 7; sintomas 10×1 = 10 ÷ 2 = 5; total 27
    $r = upq('fiqr', upqItens(21, fn (int $n) => $n <= 9 ? $n : ($n === 10 ? 3 : ($n === 11 ? 4 : 1))));
    expect($r['escore'])->toBe(27.0);

    // função 10 ÷ 3 = 3,333… — o total não arredonda antes de somar: 3,333… + 0 + 0 = 3,33
    expect(upq('fiqr', upqItens(21, fn (int $n) => $n === 1 ? 10 : 0))['escore'])->toBe(3.33);
});

it('UC-FPS-41 · ACR 2016: os dois caminhos dos limiares e a fronteira de cada um', function (array $acr, int $wpi, int $sss, float $limiar) {
    $r = upq('acr2016', $acr);
    expect($r['detalhe']['wpi'])->toBe((float) $wpi)
        ->and($r['detalhe']['sss'])->toBe((float) $sss)
        ->and($r['escore'])->toBe((float) ($wpi + $sss))
        ->and($r['detalhe']['criterio_wpi_sss'])->toBe($limiar);
})->with([
    // 7 áreas em 4 regiões (2 sup. esq., 6 sup. dir., 10 inf. esq., 13 inf. dir., 15 16 17 axial); SSS 2+2+1 = 5
    'WPI 7 e SSS 5: atende' => [upqAcr([2, 6, 10, 13, 15, 16, 17], 2, 2, 1, [], 1), 7, 5, 1.0],
    'WPI 7 e SSS 4: não' => [upqAcr([2, 6, 10, 13, 15, 16, 17], 2, 1, 1, [], 1), 7, 4, 0.0],
    // WPI 4–6 exige SSS ≥ 9: 3+3+2 + 1 = 9
    'WPI 6 e SSS 9: atende' => [upqAcr([2, 6, 10, 13, 15, 16], 3, 3, 2, [1], 1), 6, 9, 1.0],
    'WPI 6 e SSS 8: não' => [upqAcr([2, 6, 10, 13, 15, 16], 3, 3, 2, [], 1), 6, 8, 0.0],
    'WPI 4 e SSS 12: atende' => [upqAcr([2, 6, 10, 13], 3, 3, 3, [1, 1, 1], 1), 4, 12, 1.0],
    'WPI 3 e SSS 12: não' => [upqAcr([2, 6, 10], 3, 3, 3, [1, 1, 1], 1), 3, 12, 0.0],
]);

it('UC-FPS-41 · ACR 2016: dor generalizada não conta mandíbula, tórax e abdome', function () {
    // 9 áreas, mas só em 3 regiões que contam: mandíbulas (1, 5), tórax (18) e abdome (19) não
    // entram na dor generalizada; o resto fica em sup. esq. (2, 3), sup. dir. (6) e axial (15, 16).
    $r = upq('acr2016', upqAcr([1, 2, 3, 5, 6, 15, 16, 18, 19], 3, 3, 3, [1, 1, 1], 1));
    expect($r['detalhe']['wpi'])->toBe(9.0)
        ->and($r['detalhe']['regioes_com_dor'])->toBe(3.0)
        ->and($r['detalhe']['criterio_wpi_sss'])->toBe(1.0)
        ->and($r['detalhe']['dor_generalizada'])->toBe(0.0)
        ->and($r['detalhe']['criterios_acr2016_atendidos'])->toBe(0.0);

    // Mandíbula sozinha não abre região: 1 (mand. esq.) + 6, 10, 13 → 3 regiões, não 4.
    expect(upq('acr2016', upqAcr([1, 6, 10, 13], 3, 3, 3, [1, 1, 1], 1))['detalhe']['regioes_com_dor'])->toBe(3.0);
});

it('UC-FPS-41 · ACR 2016: tudo atendido só com sintomas há 3 meses ou mais', function () {
    $sim = upq('acr2016', upqAcr([2, 6, 10, 13, 15, 16, 17], 2, 2, 1, [], 1));
    $nao = upq('acr2016', upqAcr([2, 6, 10, 13, 15, 16, 17], 2, 2, 1, [], 0));
    expect($sim['detalhe']['criterios_acr2016_atendidos'])->toBe(1.0)
        ->and($sim['detalhe']['regioes_com_dor'])->toBe(5.0) // 4 membros + axial
        ->and($nao['detalhe']['sintomas_3_meses'])->toBe(0.0)
        ->and($nao['detalhe']['criterios_acr2016_atendidos'])->toBe(0.0)
        ->and($nao['escore'])->toBe($sim['escore']); // a duração não muda o escore, só os critérios
});

it('UC-FPS-40 · PGIC guarda a categoria (1 a 7); sentar e levantar guarda as repetições', function () {
    expect(upq('pgic', ['valor' => 6])['escore'])->toBe(6.0)
        ->and(upq('pgic', ['valor' => 6])['respostas'])->toBe(['valor' => 6])
        ->and(upq('sentar_levantar_30s', ['valor' => '12'])['escore'])->toBe(12.0);
});

it('UC-FPS-40 · fibromialgia: resposta fora da faixa é recusada, apontando o campo', function (string $instr, array $resp, string $campo) {
    try {
        upq($instr, $resp);
        $this->fail('esperava RespostaInvalidaException');
    } catch (RespostaInvalidaException $e) {
        expect($e->campo)->toBe($campo);
    }
})->with([
    'FIQR com 20 itens' => ['fiqr', ['itens' => array_slice(upqItens(21, 0)['itens'], 0, 20, true)], 'respostas.itens.21'],
    'FIQR com 11 num item' => ['fiqr', upqItens(21, fn (int $n) => $n === 4 ? 11 : 0), 'respostas.itens.4'],
    'ACR área com 2' => ['acr2016', ['itens' => array_replace(upqAcr([], 0, 0, 0, [], 1)['itens'], [3 => 2])], 'respostas.itens.3'],
    'ACR fadiga com 4' => ['acr2016', upqAcr([], 4, 0, 0, [], 1), 'respostas.itens.20'],
    'ACR cefaleia com 2' => ['acr2016', upqAcr([], 0, 0, 0, [2], 1), 'respostas.itens.23'],
    'ACR sem duração' => ['acr2016', ['itens' => array_slice(upqAcr([], 0, 0, 0, [], 1)['itens'], 0, 25, true)], 'respostas.itens.26'],
    'PGIC com 0' => ['pgic', ['valor' => 0], 'respostas.valor'],
    'PGIC com 8' => ['pgic', ['valor' => 8], 'respostas.valor'],
    'PGIC com decimal' => ['pgic', ['valor' => 3.5], 'respostas.valor'],
    'sentar e levantar negativo' => ['sentar_levantar_30s', ['valor' => -1], 'respostas.valor'],
]);
