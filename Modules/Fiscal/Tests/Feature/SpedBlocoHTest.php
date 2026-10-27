<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

require_once __DIR__.'/../Support/sped-sem-banco.php';

/**
 * US-FISCAL-028 fatia 3 — Bloco H (inventário de 31/12 na EFD de fevereiro).
 *
 * Leiaute: Guia Prático EFD-ICMS/IPI v3.2.3, registros H001/H005/H010, 0190, 0200 e 0500.
 * A posição do estoque vem de `InventarioSped` (mesma conta do `getOpeningClosingStock`); aqui
 * ela é injetada, e o que se prova é a montagem dos registros e a trava da dupla prova.
 *
 * Fixture (valor e quantidade escolhidos para exercitar o arredondamento):
 *   CAM-P  10 un    custo total 150,00  → VL_UNIT 15,000000 · VL_ITEM 150,00
 *   TEC-1   2,5 m   custo total  33,333 → VL_UNIT 13,333200 · VL_ITEM  33,33
 *   VL_INV = 150,00 + 33,33 = 183,33   (estoque final do sistema: 183,333)
 */
function inventarioFixture(float $canonico = 183.333): array
{
    return [
        'itens' => [
            ['variation_id' => 1, 'cod' => 'CAM-P', 'descr' => 'CAMISETA P', 'unid' => 'UN', 'ncm' => '61091000', 'cest' => '', 'qtd' => 10.0, 'valor' => 150.0],
            ['variation_id' => 2, 'cod' => 'TEC-1', 'descr' => 'TECIDO', 'unid' => 'M', 'ncm' => '52081100', 'cest' => '2806300', 'qtd' => 2.5, 'valor' => 33.333],
        ],
        'soma_bruta' => 183.333,
        'negativos' => 0,
        'total_canonico' => $canonico,
    ];
}

/** @return array<string, list<list<string>>> registros agrupados pelo REG */
function registrosSped(string $txt): array
{
    $por = [];
    foreach (camposSped($txt) as $c) {
        $por[$c[0]][] = $c;
    }

    return $por;
}

it('UC-FSPED-17 · fevereiro traz o inventário de 31/12 do ano anterior, fechando no H005', function () {
    $r = registrosSped(spedSemBanco(fontes: ['inventario' => inventarioFixture()])->gerar(98, 2026, 2));

    expect($r['H001'][0])->toBe(['H001', '0'])
        ->and($r['H005'][0])->toBe(['H005', '31122025', '183,33', '01'])
        ->and($r['H010'])->toBe([
            ['H010', 'CAM-P', 'UN', '10,000', '15,000000', '150,00', '0', '', '', '1.1.3.01', ''],
            ['H010', 'TEC-1', 'M', '2,500', '13,333200', '33,33', '0', '', '', '1.1.3.01', ''],
        ]);
});

it('UC-FSPED-17 · cada item do H010 existe no 0200 com a unidade do inventário, e a conta no 0500', function () {
    $r = registrosSped(spedSemBanco(fontes: ['inventario' => inventarioFixture()])->gerar(98, 2026, 2));

    $doze = array_column($r['0200'], null, 1);
    expect($doze['TEC-1'])->toBe(['0200', 'TEC-1', 'TECIDO', '', '', 'M', '00', '52081100', '', '52', '', '', '2806300'])
        ->and(array_column($r['0190'], 1))->toContain('M')
        ->and($r['0500'][0])->toBe(['0500', '01022026', '01', 'A', '4', '1.1.3.01', 'ESTOQUE DE MERCADORIAS']);

    // Todo 0200 tem os 12 campos do Guia v3.2.3 (antes faltava o CEST).
    foreach ($r['0200'] as $linha) {
        expect(count($linha) - 1)->toBe(12);
    }
});

it('UC-FSPED-17 · fora de fevereiro o bloco H segue sem dados', function () {
    $r = registrosSped(spedSemBanco(fontes: ['inventario' => inventarioFixture()])->gerar(98, 2026, 1));

    expect($r['H001'][0])->toBe(['H001', '1'])
        ->and($r)->not->toHaveKey('H005')
        ->and($r)->not->toHaveKey('0500');
});

it('UC-FSPED-18 · inventário que não bate com o estoque final do sistema não sai (dupla prova)', function () {
    expect(fn () => spedSemBanco(fontes: ['inventario' => inventarioFixture(canonico: 190.0)])->gerar(98, 2026, 2))
        ->toThrow(RuntimeException::class, 'diverge do estoque final do sistema');

    // Meio centavo de diferença ainda é o mesmo número (arredondamento da soma).
    expect(spedSemBanco(fontes: ['inventario' => inventarioFixture(canonico: 183.336)])->gerar(98, 2026, 2))
        ->toContain('|H005|31122025|183,33|01|');
});
