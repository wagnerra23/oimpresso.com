<?php

declare(strict_types=1);
// @covers-us US-FISIO-011
// Cobre, na regra pura, UC-FPS-13 e UC-FPS-14 (Fisio/Pacientes/Show.casos.md).

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

it('o catálogo tem só os 5 livres, nenhum enunciado inventado e referência em todos', function () {
    $todos = CatalogoQuestionarios::todos();
    expect(array_keys($todos))->toBe(CatalogoQuestionarios::LIVRES)
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
        ->and(count($todos['spadi']['itens']))->toBe(13);
});
