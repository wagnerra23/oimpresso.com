<?php

declare(strict_types=1);

// @covers-us US-NFE-075 — NF-e com destinatário e CFOP vindos de fora da venda (R-NFE-046).

use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * R-NFE-046 · NfeService::destinatarioDaEmissao + NfeService::comCfop.
 *
 * Sem banco: o destinatário externo chega como array com as chaves de `contacts` (é o formato que o
 * pedido de marketplace monta a partir do comprador). O CFOP é fixture: nenhum valor aqui é a
 * escolha do contador — o teste só prova que o informado vence o da regra.
 */
function externo46(array $campos = []): array
{
    return array_merge([
        'name' => 'Comprador Fixture',
        'tax_number' => '529.982.247-25', # pii-allowlist: CPF fictício de fixture
        'address_line_1' => 'Rua das Flores', 'numero' => '100', 'neighborhood' => 'Centro',
        'city' => 'Curitiba', 'city_code' => '4106902', 'state' => 'pr',
        'zip_code' => '80000-000', 'email' => 'fixture@example.test',
    ], $campos);
}

it('UC-FNFE-55 · o destinatário externo vence o cliente da venda e sai com a UF dele', function () {
    $tx = new App\Transaction(['business_id' => 98, 'contact_id' => 999999]);

    $dest = app(NfeService::class)->destinatarioDaEmissao($tx, 'SC', externo46());

    expect($dest['cpf'])->toBe('52998224725');
    expect($dest['nome'])->toBe('Comprador Fixture');
    expect($dest['uf'])->toBe('PR');
    expect($dest['ind_ie_dest'])->toBe('9');
    expect($dest['cep'])->toBe('80000000');
});

it('UC-FNFE-55 · externo sem CPF/CNPJ recusa — não cai no cliente da venda nem no anônimo', function () {
    $tx = new App\Transaction(['business_id' => 98, 'contact_id' => 999999]);

    expect(fn () => app(NfeService::class)->destinatarioDaEmissao($tx, 'SC', externo46(['tax_number' => ''])))
        ->toThrow(RuntimeException::class, 'sem CPF/CNPJ');
});

it('UC-FNFE-56 · o CFOP informado vence o da regra em todos os itens, e o resto do item fica igual', function () {
    $dets = [
        ['cfop' => '6102', 'xprod' => 'A', 'v_prod' => 10.0],
        ['cfop' => '5102', 'xprod' => 'B', 'v_prod' => 20.0],
    ];

    $r = NfeService::comCfop($dets, '6108');

    expect(array_column($r, 'cfop'))->toBe(['6108', '6108']);
    expect(array_column($r, 'xprod'))->toBe(['A', 'B']);
    expect(array_column($r, 'v_prod'))->toBe([10.0, 20.0]);
});

it('UC-FNFE-56 · CFOP fora do formato recusa', function (string $cfop) {
    expect(fn () => NfeService::comCfop([['cfop' => '5102']], $cfop))
        ->toThrow(RuntimeException::class, 'CFOP inválido');
})->with(['', '510', '51020', '0102', '8102', 'abcd']);
