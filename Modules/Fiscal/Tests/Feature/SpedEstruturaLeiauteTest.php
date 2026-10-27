<?php

declare(strict_types=1);

use Modules\Fiscal\Services\Sped\EfdIcmsIpiLeiaute;

uses(Tests\TestCase::class);

require_once __DIR__.'/../Support/sped-sem-banco.php';

/**
 * US-FISCAL-028 fatia 1 — estrutura do arquivo EFD-ICMS/IPI conforme o leiaute oficial.
 *
 * Leiaute de referência: NT EFD ICMS IPI 2025.001 v1.0 (leiaute 020, 01/01/2026 a
 * 31/12/2026) e Guia Prático EFD-ICMS/IPI v3.2.3 (atualização 06/05/2026), baixados do
 * portal SPED em 2026-10-27.
 *
 * Roda o gerador INTEIRO, sem banco: só a leitura (business + NF-e) é trocada por um
 * tenant fictício 98 em memória. Tudo que sai no TXT passa pelo código de produção.
 */
it('UC-FSPED-11 · COD_VER do 0000 segue a tabela 3.1.1 da NT 2025.001 pelo ano do período', function () {
    expect(camposSped(spedSemBanco()->gerar(98, 2025, 1))[0])->toMatchArray([0 => '0000', 1 => '019'])
        ->and(camposSped(spedSemBanco()->gerar(98, 2026, 1))[0])->toMatchArray([0 => '0000', 1 => '020']);
});

it('UC-FSPED-11 · ano sem leiaute mapeado é recusado, não gerado com a versão de outro ano', function () {
    expect(fn () => EfdIcmsIpiLeiaute::codVer(2027))
        ->toThrow(InvalidArgumentException::class, 'não implementado');
});

it('UC-FSPED-12 · os 10 blocos obrigatórios aparecem, na ordem do Guia Prático', function () {
    $ordem = [];
    foreach (camposSped(spedSemBanco()->gerar(98, 2026, 1)) as $c) {
        $bloco = $c[0][0];
        if (end($ordem) !== $bloco) {
            $ordem[] = $bloco;
        }
    }

    expect($ordem)->toBe(EfdIcmsIpiLeiaute::ORDEM_DOS_BLOCOS)
        ->and($ordem)->toBe(['0', 'B', 'C', 'D', 'E', 'G', 'H', 'K', '1', '9']);
});

it('UC-FSPED-13 · cada x990 conta as linhas do próprio bloco, e 9900/9999 batem com o arquivo', function () {
    $campos = camposSped(spedSemBanco()->gerar(98, 2026, 1));

    $porBloco = [];
    $porReg = [];
    $declarado9900 = [];
    foreach ($campos as $c) {
        $porBloco[$c[0][0]] = ($porBloco[$c[0][0]] ?? 0) + 1;
        $porReg[$c[0]] = ($porReg[$c[0]] ?? 0) + 1;
        if ($c[0] === '9900') {
            $declarado9900[$c[1]] = (int) $c[2];
        }
    }

    foreach ($campos as $c) {
        // O 9990 também conta o 9999 (Guia 3.2.3, registro 9990, campo 02) — e o
        // 9999 começa com '9', então $porBloco['9'] já o inclui.
        if (str_ends_with($c[0], '990')) {
            expect((int) $c[1])->toBe($porBloco[$c[0][0]], "{$c[0]} não conta as linhas do bloco");
        }
    }

    expect($declarado9900)->toBe($porReg)
        ->and((int) end($campos)[1])->toBe(count($campos));
});

it('UC-FSPED-14 · mês sem NF-e: C001 sem dados, e 0001/E001/1001/9001 com o único valor válido', function () {
    $porReg = [];
    foreach (camposSped(spedSemBanco()->gerar(98, 2026, 1)) as $c) {
        $porReg[$c[0]] = $c;
    }

    expect($porReg['C001'][1])->toBe('1')
        ->and($porReg['C990'][1])->toBe('2')
        ->and($porReg['0001'][1])->toBe('0')
        ->and($porReg['E001'][1])->toBe('0')
        ->and($porReg['9001'][1])->toBe('0')
        ->and($porReg['B001'][1])->toBe('1')
        ->and($porReg['D001'][1])->toBe('1')
        ->and($porReg['G001'][1])->toBe('1')
        ->and($porReg['K001'][1])->toBe('1')
        ->and($porReg)->not->toHaveKey('K010');

    // 1010 é obrigatório e tem 13 indicadores (IND_EXP .. IND_REST_RESSARC_COMPL_ICMS).
    expect($porReg['1001'][1])->toBe('0')
        ->and(count($porReg['1010']) - 1)->toBe(13)
        ->and($porReg['1990'][1])->toBe('3');
});
