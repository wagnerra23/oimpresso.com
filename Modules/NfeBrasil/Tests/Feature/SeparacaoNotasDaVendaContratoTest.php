<?php
// @covers-us US-NFE-069

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\SeparacaoNotasDaVenda;
use Tests\Support\EstoqueFixture;

/**
 * US-NFE-069 · UC-FLX-27 / UC-FLX-31 — a venda se separa em parte mercadoria (NF-e) e parte
 * serviço (NFS-e). Âncora: memory/requisitos/NfeBrasil/SPEC.md US-NFE-069, aceite "separar as
 * linhas"; casos em resources/js/Pages/Fiscal/Nfse.casos.md (UC-FNFSE-06..09).
 *
 * REGRA MESTRE de valor — duas provas por caso: o esperado é conta à mão (escrita no teste) e
 * cada caso refaz a conta por um segundo caminho, sem banco e em reais (separacaoEsperada).
 * O rateio foi decidido por [W] em 2026-10-21 (SeparacaoNotasDaVenda::REGRA_RATEIO).
 *
 * Os casos com banco usam o tenant fictício 98 contra o 99 (ADR 0358) e pulam fora do MySQL —
 * leia assertions, não "0 failed" (LC-13). Os casos sem banco rodam em qualquer lane.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Segundo caminho: em reais, com round() do PHP, sem a aritmética de centavos do serviço.
 *
 * @param list<array{0:string,1:float,2:float}> $linhas [natureza, quantidade, preço]
 * @return array{0:float,1:float} [total mercadoria, total serviço]
 */
function separacaoEsperada(array $linhas, float $finalTotal, float $frete): array
{
    $m = 0.0;
    $s = 0.0;
    foreach ($linhas as [$nat, $qtd, $preco]) {
        $v = round($qtd * $preco, 2);
        $nat === 'servico' ? $s += $v : $m += $v;
    }
    $freteNfe = $m > 0 ? $frete : 0.0;
    $resto = $finalTotal - $m - $s - $frete;
    $restoServico = ($s + $m) > 0 ? round($resto * $s / ($s + $m), 2, PHP_ROUND_HALF_UP) : 0.0;
    $servico = round($s + $restoServico + ($frete - $freteNfe), 2);

    return [round($finalTotal - $servico, 2), $servico];
}

function sepLinhas(array $linhas): array
{
    return array_map(fn ($l) => ['natureza' => $l[0], 'quantidade' => $l[1], 'valor_unitario' => $l[2]], $linhas);
}

/** Os três exemplos do PR: [linhas, final_total, frete, mercadoria à mão, serviço à mão]. */
dataset('vendas_exemplo', [
    'só mercadoria: 2×50,00 + 1×30,00, frete 15,00' => [[['mercadoria', 2, 50.00], ['mercadoria', 1, 30.00]], 145.00, 15.00, 145.00, 0.00],
    'só serviço: 200,00 + 80,00, desconto 10%'      => [[['servico', 1, 200.00], ['servico', 1, 80.00]], 252.00, 0.00, 0.00, 252.00],
    'mista: 3×33,33 + instalação 150,00, desconto 7% (17,50), frete 20,00' => [[['mercadoria', 3, 33.33], ['servico', 1, 150.00]], 252.49, 20.00, 112.99, 139.50],
]);

it('UC-FNFSE-06 · a venda se separa em mercadoria e serviço e as duas partes somam o final_total, centavo a centavo', function (array $linhas, float $final, float $frete, float $mMao, float $sMao) {
    $r = SeparacaoNotasDaVenda::dividir(sepLinhas($linhas), $final, $frete);

    expect($r['mercadoria']['total'])->toBe($mMao)
        ->and($r['servico']['total'])->toBe($sMao);
    // invariante: soma em centavos inteiros, sem float solto
    expect((int) round($r['mercadoria']['total'] * 100) + (int) round($r['servico']['total'] * 100))
        ->toBe((int) round($final * 100));
    // segundo caminho, independente
    [$m2, $s2] = separacaoEsperada($linhas, $final, $frete);
    expect($r['mercadoria']['total'])->toBe($m2)
        ->and($r['servico']['total'])->toBe($s2);
    expect(count($r['mercadoria']['linhas']) + count($r['servico']['linhas']))->toBe(count($linhas));
})->with('vendas_exemplo');

it('UC-FNFSE-07 · o desconto é rateado pelo bruto e o frete vai inteiro para a NF-e', function () {
    // mista: bruto 99,99 + 150,00 = 249,99; resto = 252,49 − 249,99 − 20,00 = −17,50
    // serviço: 17,50 × 150,00 / 249,99 = 10,5004 → 10,50; mercadoria absorve 7,00
    $r = SeparacaoNotasDaVenda::dividir(sepLinhas([['mercadoria', 3, 33.33], ['servico', 1, 150.00]]), 252.49, 20.00);

    expect($r['servico']['desconto'])->toBe(10.50)
        ->and($r['mercadoria']['desconto'])->toBe(7.00)
        ->and($r['mercadoria']['frete'])->toBe(20.00)
        ->and($r['servico']['frete'])->toBe(0.0)
        ->and($r['frete_sem_mercadoria'])->toBeFalse()
        ->and($r['regra'])->toBe(SeparacaoNotasDaVenda::REGRA_RATEIO);
});

it('UC-FNFSE-07 · o centavo que não divide fica na mercadoria, nunca some', function () {
    // 1,00 + 1,00, desconto de 0,01: a fatia do serviço é 0,005 → 0,01 (meio para cima); a mercadoria fica sem desconto
    $r = SeparacaoNotasDaVenda::dividir(sepLinhas([['mercadoria', 1, 1.00], ['servico', 1, 1.00]]), 1.99, 0.0);

    expect($r['servico']['total'])->toBe(0.99)
        ->and($r['mercadoria']['total'])->toBe(1.00)
        ->and(round($r['servico']['total'] + $r['mercadoria']['total'], 2))->toBe(1.99);
});

it('UC-FNFSE-07 · venda só de serviço com frete leva o frete para a NFS-e e avisa', function () {
    $r = SeparacaoNotasDaVenda::dividir(sepLinhas([['servico', 1, 100.00]]), 110.00, 10.00);

    expect($r['servico']['total'])->toBe(110.00)
        ->and($r['mercadoria']['total'])->toBe(0.0)
        ->and($r['frete_sem_mercadoria'])->toBeTrue();
});

it('UC-FNFSE-09 · natureza pendente fica na mercadoria e volta listada, sem decidir imposto', function () {
    $r = SeparacaoNotasDaVenda::dividir([
        ['product_id' => 7, 'natureza' => 'pendente', 'quantidade' => 1, 'valor_unitario' => 40.00],
        ['product_id' => 8, 'natureza' => 'servico', 'quantidade' => 1, 'valor_unitario' => 60.00],
    ], 100.00, 0.0);

    expect($r['pendentes'])->toBe([7])
        ->and($r['mercadoria']['total'])->toBe(40.00)
        ->and($r['servico']['total'])->toBe(60.00);
});

describe('com banco (tenant 98 contra 99)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_naturezas_produto')) {
            $this->markTestSkipped('MySQL com nfe_naturezas_produto (migração 2026_10_14_000003).');
        }
        try {
            $this->biz = $this->seededTenant()->id;
            $this->outra = $this->seededSupportClientTenant()->id;
        } catch (\Throwable $e) {
            $this->markTestSkipped('Schema UltimatePOS ausente: ' . $e->getMessage());
        }
        $this->venda = function (int $biz, array $linhas, float $final, float $frete): int {
            $tx = (int) DB::table('transactions')->insertGetId([
                'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz), 'type' => 'sell',
                'status' => 'final', 'payment_status' => 'due', 'transaction_date' => now(),
                'total_before_tax' => $final, 'final_total' => $final, 'shipping_charges' => $frete,
                'created_by' => EstoqueFixture::userId($biz), 'essentials_duration' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $ids = [];
            foreach ($linhas as [$natureza, $qtd, $preco, $codigo]) {
                $p = EstoqueFixture::singleProduct($biz, false);
                DB::table('transaction_sell_lines')->insert([
                    'transaction_id' => $tx, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
                    'quantity' => $qtd, 'quantity_returned' => 0, 'unit_price' => $preco, 'unit_price_inc_tax' => $preco,
                    'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($natureza !== null) {
                    DB::table('nfe_naturezas_produto')->insert([
                        'business_id' => $biz, 'product_id' => $p->productId, 'natureza' => $natureza,
                        'codigo_servico' => $codigo, 'fonte' => 'teste', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $ids[] = $p->productId;
            }
            $this->produtos = $ids;

            return $tx;
        };
    });

    it('UC-FNFSE-06 · lê a natureza e o código de serviço que o contador gravou', function () {
        $tx = ($this->venda)($this->biz, [['mercadoria', 3, 33.33, null], ['servico', 1, 150.00, '24.01']], 252.49, 20.00);

        $r = app(SeparacaoNotasDaVenda::class)->separar($this->biz, $tx);

        expect($r['mercadoria']['total'])->toBe(112.99)
            ->and($r['servico']['total'])->toBe(139.50)
            ->and($r['servico']['linhas'][0]['codigo_servico'])->toBe('24.01')
            ->and($r['mercadoria']['linhas'][0]['natureza'])->toBe('mercadoria');
    });

    it('UC-FNFSE-08 · venda de outra empresa é recusada', function () {
        $tx = ($this->venda)($this->outra, [['servico', 1, 100.00, '24.01']], 100.00, 0.0);

        expect(fn () => app(SeparacaoNotasDaVenda::class)->separar($this->biz, $tx))
            ->toThrow(ModelNotFoundException::class);
        // a dona enxerga a própria venda (âncora positiva: a recusa acima é do tenant, não do dado)
        expect(app(SeparacaoNotasDaVenda::class)->separar($this->outra, $tx)['servico']['total'])->toBe(100.00);
    });

    it('UC-FNFSE-08 · natureza gravada por outra empresa não classifica a linha', function () {
        $tx = ($this->venda)($this->biz, [[null, 1, 80.00, null]], 80.00, 0.0);
        DB::table('nfe_naturezas_produto')->insert([
            'business_id' => $this->outra, 'product_id' => $this->produtos[0], 'natureza' => 'servico',
            'codigo_servico' => '24.01', 'fonte' => 'teste', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = app(SeparacaoNotasDaVenda::class)->separar($this->biz, $tx);

        expect($r['mercadoria']['total'])->toBe(80.00)
            ->and($r['servico']['linhas'])->toBe([]);
    });
});
