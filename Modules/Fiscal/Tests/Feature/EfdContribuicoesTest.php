<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Fiscal\Services\EfdContribuicoesGeneratorService;

uses(Tests\TestCase::class);

require_once __DIR__.'/../Support/sped-sem-banco.php';

/**
 * US-FISCAL-011 — EFD-Contribuições do Lucro Presumido, cumulativo, consolidado (F550 + M200/M600).
 *
 * Leiaute: Guia Prático da EFD-Contribuições v1.35 (18/06/2021), leiaute 006. Ordem dos campos
 * conferida com o `nfephp-org/sped-efd`. Roda sem banco: só a leitura é trocada (tenant 98).
 *
 * Fixture e conta feita por fora (Decimal, ROUND_HALF_UP), não pelo código sob teste:
 *   NF-e 55:  2 notas · 10.000,00      NFC-e 65: 3 notas · 2.345,67      receita 12.345,67
 *   PIS    12.345,67 × 0,65% =  80,246855 →  80,25
 *   COFINS 12.345,67 × 3,00% = 370,3701   → 370,37
 */
function efdSemBanco(array $fontes = []): EfdContribuicoesGeneratorService
{
    $fontes = array_merge([
        'regime' => 'lucro_presumido',
        'contador' => (object) ['nome' => 'CONTADOR TESTE', 'cpf' => '52998224725', 'crc' => 'SC-000098/O', 'email' => 'contador@exemplo.test'],
        'receitas' => [['modelo' => '55', 'valor' => 10000.00, 'qtd' => 2], ['modelo' => '65', 'valor' => 2345.67, 'qtd' => 3]],
    ], $fontes);

    return new class($fontes) extends EfdContribuicoesGeneratorService {
        public function __construct(private array $f)
        {
        }

        protected function carregarRegime(int $businessId): ?string
        {
            return $this->f['regime'];
        }

        protected function carregarBusiness(int $businessId): ?object
        {
            return (object) ['id' => $businessId, 'name' => 'CI TENANT 98', 'razao_social' => 'CI TENANT 98 COMÉRCIO LTDA',
                'ie' => '123456789', 'cep' => '88700-000', 'rua' => 'RUA DO TESTE', 'numero' => '98', 'bairro' => 'CENTRO', 'cidade_id' => 1];
        }

        protected function carregarContador(int $businessId): ?object
        {
            return $this->f['contador'];
        }

        protected function carregarReceitas(int $businessId, CarbonImmutable $ini, CarbonImmutable $fim): array
        {
            return $this->f['receitas'];
        }

        protected function resolverCnpj(int $businessId): ?string
        {
            return '11222333000181';
        }

        protected function carregarUfDoEstabelecimento(int $businessId): ?string
        {
            return 'SC';
        }

        protected function carregarCodigoMunicipio(object $business): ?string
        {
            return '4207304';
        }
    };
}

/** @return array<string, list<list<string>>> registros agrupados pelo REG */
function registrosEfd(string $txt): array
{
    $por = [];
    foreach (camposSped($txt) as $c) {
        $por[$c[0]][] = $c;
    }

    return $por;
}

beforeEach(fn () => config(['fiscal.efd_contribuicoes_enabled' => true]));

it('UC-FCONT-01 · receita do mês vira F550 e a apuração cumulativa no M200/M210 e M600/M610', function () {
    $r = registrosEfd(efdSemBanco()->gerar(98, 2026, 1));

    expect($r['0000'][0])->toBe(['0000', '006', '0', '', '', '01012026', '31012026', 'CI TENANT 98 COMERCIO LTDA',
        '11222333000181', 'SC', '4207304', '', '00', '2'])
        ->and($r['0110'][0])->toBe(['0110', '2', '', '1', '2'])
        ->and($r['F550'][0])->toBe(['F550', '12345,67', '01', '0,00', '12345,67', '0,6500', '80,25',
            '01', '0,00', '12345,67', '3,0000', '370,37', '', '', '', ''])
        ->and($r['M200'][0])->toBe(['M200', '0,00', '0,00', '0,00', '0,00', '0,00', '0,00', '0,00', '80,25', '0,00', '0,00', '80,25', '80,25'])
        ->and($r['M205'][0])->toBe(['M205', '12', '810902', '80,25'])
        ->and($r['M210'][0])->toBe(['M210', '51', '12345,67', '12345,67', '0,00', '0,00', '12345,67', '0,6500', '', '',
            '80,25', '0,00', '0,00', '0,00', '0,00', '80,25'])
        ->and($r['M600'][0][11])->toBe('370,37')
        ->and($r['M605'][0])->toBe(['M605', '12', '217201', '370,37'])
        ->and($r['M610'][0][15])->toBe('370,37');
});

it('UC-FCONT-01 · o 1900 consolida os documentos por modelo e soma a mesma receita do F550', function () {
    $r = registrosEfd(efdSemBanco()->gerar(98, 2026, 1));

    expect($r['1900'])->toBe([
        ['1900', '11222333000181', '55', '', '', '00', '10000,00', '2', '01', '01', '', '', ''],
        ['1900', '11222333000181', '65', '', '', '00', '2345,67', '3', '01', '01', '', '', ''],
    ]);
});

it('UC-FCONT-02 · todo x990 conta o próprio bloco, e 9900/9999 batem com o arquivo', function () {
    $campos = camposSped(efdSemBanco()->gerar(98, 2026, 1));
    $porBloco = [];
    $porReg = [];
    $declarado = [];
    foreach ($campos as $c) {
        $porBloco[$c[0][0]] = ($porBloco[$c[0][0]] ?? 0) + 1;
        $porReg[$c[0]] = ($porReg[$c[0]] ?? 0) + 1;
        if ($c[0] === '9900') {
            $declarado[$c[1]] = (int) $c[2];
        }
    }
    foreach ($campos as $c) {
        if (str_ends_with($c[0], '990')) {
            expect((int) $c[1])->toBe($porBloco[$c[0][0]], "{$c[0]} não conta as linhas do bloco");
        }
    }

    expect($declarado)->toBe($porReg)
        ->and((int) end($campos)[1])->toBe(count($campos))
        ->and(array_values(array_unique(array_map(fn ($c) => $c[0][0], $campos))))
        ->toBe(['0', 'A', 'C', 'D', 'F', 'I', 'M', 'P', '1', '9']);
});

it('UC-FCONT-03 · recusa em vez de inventar: regime, CPF do contador, mês sem receita e flag', function () {
    expect(fn () => efdSemBanco(['regime' => 'simples'])->gerar(98, 2026, 1))->toThrow(InvalidArgumentException::class, 'dispensada')
        ->and(fn () => efdSemBanco(['regime' => 'lucro_real'])->gerar(98, 2026, 1))->toThrow(InvalidArgumentException::class, 'Lucro Real')
        ->and(fn () => efdSemBanco(['contador' => (object) ['nome' => 'X', 'cpf' => null, 'crc' => '1']])->gerar(98, 2026, 1))
        ->toThrow(InvalidArgumentException::class, 'CPF do contador')
        ->and(fn () => efdSemBanco(['receitas' => []])->gerar(98, 2026, 1))->toThrow(InvalidArgumentException::class, 'Sem receita');

    config(['fiscal.efd_contribuicoes_enabled' => false]);
    expect(fn () => efdSemBanco()->gerar(98, 2026, 1))->toThrow(RuntimeException::class, 'desligada');
});
