<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre, na regra pura, UC-FBP-01 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Modules\Fisio\Services\Laudo\MatrizIfbrm;

/**
 * Matriz IFBr-M do laudo de SC (Deliberação 500/CIB/2026). Regra do formulário: o total é a soma
 * do MENOR valor de cada um dos 8 domínios. Serviço puro, sem TestCase: roda em qualquer lane.
 * Os totais esperados foram feitos à mão (conta em cada caso).
 */
function fbpTodos(int $valor): array
{
    return array_fill_keys(MatrizIfbrm::itens(), $valor);
}

it('UC-FBP-01 · o formulário tem 8 domínios e 30 itens, na ordem e com os códigos do modelo', function () {
    $itens = MatrizIfbrm::itens();
    expect(MatrizIfbrm::DOMINIOS)->toHaveCount(8)
        ->and($itens)->toHaveCount(30)
        ->and($itens[0])->toBe('1.1')
        ->and($itens[29])->toBe('8.3')
        ->and(array_map(fn (array $d) => count($d['itens']), array_values(MatrizIfbrm::DOMINIOS)))->toBe([4, 4, 5, 4, 4, 3, 3, 3]);
});

it('UC-FBP-01 · bordas: todos 100 dá 800; todos 25 dá 200', function () {
    expect(MatrizIfbrm::pontuar(fbpTodos(100))['total'])->toBe(800)   // 8 × 100
        ->and(MatrizIfbrm::pontuar(fbpTodos(25))['total'])->toBe(200); // 8 × 25
});

it('UC-FBP-01 · conta o menor de cada domínio, não a média nem a soma dos itens', function () {
    $m = fbpTodos(100);
    // Domínio 3: um item 25 e o resto 100 → o domínio vale 25.
    $m['3.4'] = 25;
    // Domínio 5: 75, 50, 100, 100 → vale 50.
    $m['5.1'] = 75;
    $m['5.2'] = 50;
    $p = MatrizIfbrm::pontuar($m);
    // 6 domínios × 100 + 25 + 50 = 675.
    expect($p['por_dominio']['3'])->toBe(25)->and($p['por_dominio']['5'])->toBe(50)->and($p['total'])->toBe(675);
});

it('UC-FBP-01 · total exatamente 500', function () {
    $m = fbpTodos(100);
    // 4 domínios em 100 e 4 domínios em 25: 400 + 100 = 500.
    foreach (['1.1', '2.2', '3.3', '4.4'] as $item) {
        $m[$item] = 25;
    }
    expect(MatrizIfbrm::pontuar($m)['total'])->toBe(500);
});

it('UC-FBP-01 · sem os 30 itens não há total; aponta o que falta', function () {
    $m = fbpTodos(100);
    unset($m['7.2'], $m['8.1']);
    $p = MatrizIfbrm::pontuar($m);
    expect($p['completo'])->toBeFalse()->and($p['total'])->toBeNull()
        ->and($p['faltando'])->toBe(['7.2', '8.1'])
        ->and($p['por_dominio']['7'])->toBeNull()->and($p['por_dominio']['1'])->toBe(100);
});

it('UC-FBP-01 · recusa item fora do formulário e valor fora da escala; aceita vazio e texto numérico', function () {
    expect(fn () => MatrizIfbrm::validar(['9.1' => 100]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MatrizIfbrm::validar(['1.1' => 60]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MatrizIfbrm::validar(['1.1' => '75.5']))->toThrow(InvalidArgumentException::class)
        ->and(MatrizIfbrm::validar(['1.1' => '75', '1.2' => null, '1.3' => '']))->toBe(['1.1' => 75]);
});
