<?php
// @covers-us US-PCP-034

declare(strict_types=1);

use App\Contracts\Compras\CustoDoInsumo;
use App\Domain\Producao\CustoDoFabricado;
use Carbon\CarbonInterface;

/**
 * US-PCP-034 — custo do produto fabricado numa conta só (Fabricação × Produção).
 *
 * Âncora: memory/requisitos/Producao/SPEC.md US-PCP-034 (material pelo custo médio + mão de obra +
 * perdas). Regra mestre (valor): cada número abaixo foi feito à mão sobre o arranjo e é o segundo
 * caminho da prova; o primeiro é o serviço.
 *
 * O custo médio em si (compras recebidas até a data) já é provado por
 * Modules/Compras/Tests/Feature/CustoDoInsumoServiceTest. Aqui o insumo tem custo fixo, para o
 * teste medir só a fórmula — e o que a conta PEDE ao custo: empresa e método `medio`.
 *
 * Tenant fictício 98 (ADR 0358); nada é gravado, o banco só faz a conta em DECIMAL.
 */


if (! function_exists('custoFabricadoInsumoFixo')) {
function custoFabricadoInsumoFixo(array $porVariacao): CustoDoInsumo
{
    return new class($porVariacao) implements CustoDoInsumo
    {
        /** @var list<array{biz:int, variation:int, metodo:?string}> */
        public array $pedidos = [];

        public function __construct(private array $porVariacao) {}

        public function naData(int $businessId, int $variationId, CarbonInterface|string $data, ?string $metodo = null): array
        {
            $this->pedidos[] = ['biz' => $businessId, 'variation' => $variationId, 'metodo' => $metodo];

            return ['custo' => $this->porVariacao[$variationId] ?? null, 'metodo' => (string) $metodo,
                'data' => (string) $data, 'compras' => 1, 'ultima_compra_em' => null, 'motivo' => null];
        }

        public function metodoDaEmpresa(int $businessId): string
        {
            return 'ultimo';
        }

        public function metodoDoInsumo(int $businessId, int $variationId): ?string
        {
            return 'ultimo';
        }
    };
}
}

it('UC-PCP34-01: material pelo custo médio + mão de obra + perdas, por unidade boa', function () {
    app()->instance(CustoDoInsumo::class, custoFabricadoInsumoFixo([11 => '16.0000', 12 => '2.5000']));

    // À mão: material = 3 × 16 + 10,5 × 2,5 = 48,00 + 26,25 = 74,25 · mão de obra 12,50
    // perdas = (74,25 + 12,50) × 2 ÷ 10 = 17,35 · total 104,10 · por unidade 104,10 ÷ 10 = 10,4100
    $r = (new CustoDoFabricado)->calcular(98, [
        ['variation_id' => 11, 'quantidade' => '3'],
        ['variation_id' => 12, 'quantidade' => '10.5'],
    ], '12.50', '10', '2', '2026-10-27');

    expect($r)->toMatchArray([
        'material' => '74.25', 'mao_de_obra' => '12.50', 'perdas' => '17.35', 'total' => '104.10',
        'por_unidade' => '10.4100', 'metodo' => 'medio', 'incompleto' => false, 'sem_custo' => [],
    ]);
});

it('UC-PCP34-02: pede o custo MÉDIO e da própria empresa, mesmo com a regra da empresa em último', function () {
    app()->instance(CustoDoInsumo::class, $fake = custoFabricadoInsumoFixo([11 => '1.0000', 12 => '1.0000']));

    (new CustoDoFabricado)->calcular(98, [
        ['variation_id' => 11, 'quantidade' => '1'],
        ['variation_id' => 12, 'quantidade' => '1'],
    ], '0', '1', '0', '2026-10-27');

    expect($fake->pedidos)->toHaveCount(2);
    foreach ($fake->pedidos as $pedido) {
        expect($pedido['biz'])->toBe(98);
        expect($pedido['metodo'])->toBe('medio');
    }
});

it('UC-PCP34-03: arredonda uma vez para centavos, meio para cima, sem float', function () {
    app()->instance(CustoDoInsumo::class, custoFabricadoInsumoFixo([11 => '1.0025']));

    // À mão: 2 × 1,0025 = 2,0050 → 2,01 (meio para cima) · perdas = 2,01 × 1 ÷ 3 = 0,6700
    // total 2,68 · por unidade 2,68 ÷ 3 = 0,893333… → 0,8933
    $r = (new CustoDoFabricado)->calcular(98, [['variation_id' => 11, 'quantidade' => '2']], '0', '3', '1', '2026-10-27');

    expect($r['material'])->toBe('2.01');
    expect($r['perdas'])->toBe('0.67');
    expect($r['total'])->toBe('2.68');
    expect($r['por_unidade'])->toBe('0.8933');
});

it('UC-PCP34-04: insumo sem custo fica fora do material e a resposta sai incompleta', function () {
    app()->instance(CustoDoInsumo::class, custoFabricadoInsumoFixo([11 => '5.0000']));

    $r = (new CustoDoFabricado)->calcular(98, [
        ['variation_id' => 11, 'quantidade' => '2'],
        ['variation_id' => 13, 'quantidade' => '4'],
    ], '0', '1', '0', '2026-10-27');

    expect($r['material'])->toBe('10.00');
    expect($r['incompleto'])->toBeTrue();
    expect($r['sem_custo'])->toBe([13]);
});

it('UC-PCP34-05: sem o módulo Compras, nenhum insumo tem custo e nada é inventado', function () {
    app()->forgetInstance(CustoDoInsumo::class);
    app()->offsetUnset(CustoDoInsumo::class);

    $r = (new CustoDoFabricado)->calcular(98, [['variation_id' => 11, 'quantidade' => '2']], '7.00', '1', '0', '2026-10-27');

    expect($r['material'])->toBe('0.00');
    expect($r['total'])->toBe('7.00');
    expect($r['incompleto'])->toBeTrue();
    expect($r['sem_custo'])->toBe([11]);
});

it('UC-PCP34-06: recusa quantidade boa zero e valores negativos', function (string $boa, string $perda, string $mao) {
    app()->instance(CustoDoInsumo::class, custoFabricadoInsumoFixo([]));

    (new CustoDoFabricado)->calcular(98, [], $mao, $boa, $perda, '2026-10-27');
})->with([
    'boa zero' => ['0', '0', '0'],
    'perda negativa' => ['1', '-1', '0'],
    'mão de obra negativa' => ['1', '0', '-0.01'],
])->throws(InvalidArgumentException::class);

it('UC-PCP34-07: nasce desligada', function () {
    expect(config('custo_fabricado.unificado', false))->toBeFalse();
    expect(CustoDoFabricado::ligada())->toBeFalse();
});
