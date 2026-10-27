<?php

declare(strict_types=1);

use Modules\NfeBrasil\Support\UfBrasil;

uses(Tests\TestCase::class);

require_once __DIR__.'/../Support/sped-sem-banco.php';

/**
 * US-FISCAL-028 fatia 2 — identificação do emitente nos registros 0000 e 0005.
 *
 * Leiaute: Guia Prático EFD-ICMS/IPI v3.2.3 (registros 0000 e 0005). Fontes: as mesmas do grupo
 * `emit` da NF-e (proposta 2026-09-04 `sped-identificacao-do-emitente-fonte-de-verdade`).
 * Roda sem banco, pelo mesmo helper `spedSemBanco` da fatia 1.
 */
function linhaSped(string $txt, string $reg): array
{
    foreach (camposSped($txt) as $c) {
        if ($c[0] === $reg) {
            return $c;
        }
    }

    throw new RuntimeException("registro {$reg} ausente");
}

it('UC-FSPED-15 · o 0000 identifica o emitente pelas fontes da NF-e, campo a campo', function () {
    $zero = linhaSped(spedSemBanco()->gerar(98, 2026, 1), '0000');

    // REG, COD_VER, COD_FIN, DT_INI, DT_FIN, NOME, CNPJ, CPF, UF, IE, COD_MUN, IM, SUFRAMA, IND_PERFIL, IND_ATIV
    expect($zero)->toBe([
        '0000', '020', '0', '01012026', '31012026', 'CI TENANT 98 (FICTICIO) LTDA', '11222333000181', '',
        'SC', '123456789', '4207304', '', '', 'A', '1',
    ]);
});

it('UC-FSPED-15 · o 0005 põe cada parte do endereço no seu campo', function () {
    $cinco = linhaSped(spedSemBanco()->gerar(98, 2026, 1), '0005');

    // REG, FANTASIA, CEP, END, NUM, COMPL, BAIRRO, FONE, FAX, EMAIL
    expect($cinco)->toBe(['0005', 'CI TENANT 98', '88700000', 'RUA DO TESTE', '98', '', 'CENTRO', '48999990098', '', '']);
});

it('UC-FSPED-15 · UF gravada por extenso vira a sigla', function () {
    $zero = linhaSped(spedSemBanco(fontes: ['uf' => 'Santa Catarina'])->gerar(98, 2026, 1), '0000');

    expect($zero[8])->toBe('SC')
        ->and(UfBrasil::sigla('são paulo'))->toBe('SP')
        ->and(UfBrasil::sigla('RIO GRANDE DO SUL'))->toBe('RS')
        ->and(UfBrasil::sigla('Narnia'))->toBeNull();
});

it('UC-FSPED-16 · sem CNPJ, IE, UF, município ou endereço o arquivo é recusado — nada de SP ou 9999999', function () {
    $casos = [
        'CNPJ' => [[], ['cnpj' => null]],
        'inscrição estadual' => [['ie' => '00000000000'], []],
        'UF do estabelecimento' => [[], ['uf' => 'Narnia']],
        'município (código IBGE)' => [[], ['cod_mun' => null]],
        'endereço (CEP, logradouro e bairro)' => [['rua' => '*'], []],
    ];

    foreach ($casos as $falta => [$business, $fontes]) {
        expect(fn () => spedSemBanco(sobrescrever: $business, fontes: $fontes)->gerar(98, 2026, 1))
            ->toThrow(InvalidArgumentException::class, $falta);
    }
});
