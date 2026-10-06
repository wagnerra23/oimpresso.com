<?php

declare(strict_types=1);

// @covers-us US-NFE-002 — tributação por item (produto.NCM) na emissão · thread 17 fase 2B.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * Thread 17 (playbook Fiscal) · NfeService::montarItensNfe — um `det` por linha da venda.
 *
 * Os números abaixo são a PROVA DE VALOR da REGRA MESTRE (memory/proibicoes.md):
 * calculados à mão e por uma calculadora independente (Decimal, rateio de Hamilton),
 * registrados no `_saida-17.md`. O teste é o segundo caminho.
 *
 * Motor falso: alíquotas fixas (ICMS 18% · PIS 1,65% · COFINS 7,6%) e arredondamento
 * `round(base × alíq, 2)` — igual ao `MotorTributarioService::fmt`. Registra cada chamada.
 */

function motorFake17(ArrayObject $chamadas, ?int $regraId = null): MotorTributarioService
{
    return new class($chamadas, $regraId) extends MotorTributarioService {
        public function __construct(private ArrayObject $log, private ?int $regraId) {}

        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            $this->log[] = ['ncm' => $produto->ncm, 'valor' => $produto->valor, 'override' => $produto->fiscal_rule_override_id];

            return new TributoCalculado(
                cfop: '5102', csosn: null, cst: '00',
                aliquota_icms: 0.18, aliquota_pis: 0.0165, aliquota_cofins: 0.076, aliquota_ipi: 0.0,
                valor_icms: round($produto->valor * 0.18, 2),
                valor_pis: round($produto->valor * 0.0165, 2),
                valor_cofins: round($produto->valor * 0.076, 2),
                valor_ipi: 0.0,
                nivel_usado: 2,
                regra_id: $this->regraId,
            );
        }
    };
}

function linha17(int $id, float $qtd, float $vun, string $ncm): array
{
    return [
        'sell_line_id' => $id, 'product_id' => 9000 + $id, 'cprod' => "SKU{$id}",
        'xprod' => "Produto {$id}", 'ncm' => $ncm, 'unidade' => 'UN',
        'quantidade' => $qtd, 'valor_unitario' => $vun,
    ];
}

function montar17(array $linhas, float $total, float $frete, MotorTributarioService $motor): array
{
    return app(NfeService::class)->montarItensNfe(
        linhas: $linhas, valorNota: $total, frete: $frete, ncmDefault: '49111090',
        motor: $motor, businessId: 98, ufOrigem: 'SC', ufDestino: 'SC',
    );
}

it('R-NFE-023 · um det por linha com NCM do produto', function () {
    $chamadas = new ArrayObject();
    $r = montar17([
        linha17(1, 2, 10.00, '61091000'),
        linha17(2, 1, 25.50, '6203.42.00'),
        linha17(3, 3, 3.3333, '39269090'),
    ], 55.50, 0, motorFake17($chamadas));

    expect($r['dets'])->toHaveCount(3);
    expect(array_column($r['dets'], 'ncm'))->toBe(['61091000', '62034200', '39269090']);
    expect(array_column($r['dets'], 'xprod'))->toBe(['Produto 1', 'Produto 2', 'Produto 3']);
    expect(array_column($r['dets'], 'vprod'))->toBe([20.0, 25.5, 10.0]);
    expect(count($chamadas))->toBe(3);
    expect(array_column($chamadas->getArrayCopy(), 'ncm'))->toBe(['61091000', '62034200', '39269090']);
});

it('R-NFE-023 · controle positivo: 1 linha gera 1 det com o total da venda', function () {
    $chamadas = new ArrayObject();
    $r = montar17([linha17(1, 1, 150.00, '61091000')], 150.00, 0, motorFake17($chamadas));

    expect($r['dets'])->toHaveCount(1);
    expect($r['dets'][0]['vprod'])->toBe(150.0);
    expect($r['total']['v_nf'])->toBe(150.0);
    // Mesmos impostos da fase 2A (item único sobre o total): 27,00 · 2,48 · 11,40
    expect([$r['total']['v_icms'], $r['total']['v_pis'], $r['total']['v_cofins']])->toBe([27.0, 2.48, 11.4]);
    expect($r['metadata'])->toBeNull();
});

it('R-NFE-024 · linha sem NCM usa padrão e marca', function () {
    $chamadas = new ArrayObject();
    $r = montar17([
        linha17(1, 1, 10.00, '61091000'),
        linha17(2, 1, 5.00, '0'),
        linha17(3, 1, 5.00, ''),
    ], 20.00, 0, motorFake17($chamadas));

    expect(array_column($r['dets'], 'ncm'))->toBe(['61091000', '49111090', '49111090']);
    expect($r['metadata']['itens_ncm_padrao'])->toBe([
        ['sell_line_id' => 2, 'product_id' => 9002],
        ['sell_line_id' => 3, 'product_id' => 9003],
    ]);
});

it('R-NFE-024 · controle: sem NCM padrão válido não emite', function () {
    $chamadas = new ArrayObject();
    app(NfeService::class)->montarItensNfe(
        linhas: [linha17(1, 1, 10.00, '0')], valorNota: 10.00, frete: 0, ncmDefault: '',
        motor: motorFake17($chamadas), businessId: 98, ufOrigem: 'SC', ufDestino: 'SC',
    );
})->throws(RuntimeException::class, 'sem NCM padrão');

it('R-NFE-025 · totais somam itens e o desconto da venda é rateado (vNF = final_total)', function () {
    $chamadas = new ArrayObject();
    // 20,00 + 25,50 + 10,00 = 55,50 de produtos; venda fechou em 50,00 → desconto 5,50
    $r = montar17([
        linha17(1, 2, 10.00, '61091000'),
        linha17(2, 1, 25.50, '62034200'),
        linha17(3, 3, 3.3333, '39269090'),
    ], 50.00, 0, motorFake17($chamadas));

    expect(array_column($r['dets'], 'vdesc'))->toBe([1.98, 2.53, 0.99]);
    expect(array_column($chamadas->getArrayCopy(), 'valor'))->toBe([18.02, 22.97, 9.01]);
    expect($r['total']['v_prod'])->toBe(55.5);
    expect($r['total']['v_desc'])->toBe(5.5);
    expect($r['total']['v_nf'])->toBe(50.0);
    // vNF = vProd − vDesc + vFrete + vOutro
    expect(round($r['total']['v_prod'] - $r['total']['v_desc'] + $r['total']['v_frete'] + $r['total']['v_outro'], 2))->toBe(50.0);
    // Arredondamento por item: ICMS 3,24 + 4,13 + 1,62 = 8,99 (o item único da fase 2A dava 9,00)
    expect($r['total']['v_icms'])->toBe(8.99);
    expect($r['total']['v_icms'])->toBe(round(array_sum(array_map(fn ($d) => $d['icms']['vicms'], $r['dets'])), 2));
    expect([$r['total']['v_pis'], $r['total']['v_cofins']])->toBe([0.83, 3.8]);
});

it('R-NFE-025 · acréscimo vira frete (até o frete da venda) e o resto vOutro', function () {
    $chamadas = new ArrayObject();
    $frete = montar17([linha17(1, 2, 10.00, '61091000')], 25.00, 5.00, motorFake17($chamadas));
    expect([$frete['total']['v_frete'], $frete['total']['v_outro'], $frete['total']['v_nf']])->toBe([5.0, 0.0, 25.0]);

    $outro = montar17([linha17(1, 2, 10.00, '61091000')], 21.00, 0, motorFake17($chamadas));
    expect([$outro['total']['v_frete'], $outro['total']['v_outro'], $outro['total']['v_nf']])->toBe([0.0, 1.0, 21.0]);
    expect($outro['dets'][0]['voutro'])->toBe(1.0);
});

it('R-NFE-025 · linha de valor zero (componente de combo) não vira det', function () {
    $chamadas = new ArrayObject();
    $r = montar17([linha17(1, 1, 30.00, '61091000'), linha17(2, 1, 0.0, '61091000')], 30.00, 0, motorFake17($chamadas));

    expect($r['dets'])->toHaveCount(1);
    expect(count($chamadas))->toBe(1);
});

it('R-NFE-025b · CST PIS/COFINS da regra: sem CST na regra cai no 07 e loga', function () {
    Log::spy();
    $chamadas = new ArrayObject();
    $r = montar17([linha17(1, 1, 10.00, '61091000'), linha17(2, 1, 5.00, '61091000')], 15.00, 0, motorFake17($chamadas));

    expect(array_map(fn ($d) => [$d['pis']['cst'], $d['cofins']['cst']], $r['dets']))->toBe([['07', '07'], ['07', '07']]);
    Log::shouldHaveReceived('info')->withArgs(
        fn ($msg, $ctx = []) => $msg === 'pis_cofins_cst_fallback' && ($ctx['itens'] ?? null) === 2
    )->once();
});

it('R-NFE-025c · idempotência com itens reais: venda autorizada devolve a mesma emissão', function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('Exige MySQL com nfe_emissoes (UNIQUE business_id+transaction_id).');
    }

    $biz = 98;
    $txId = 999717001;
    $id = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $biz, 'transaction_id' => $txId, 'modelo' => '65', 'serie' => '1',
        'numero' => 717001, 'status' => 'autorizada', 'cstat' => '100', 'motivo' => 'Autorizado',
        'valor_total' => 50.00, 'emitido_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    try {
        $chamadas = new ArrayObject();
        $itens = montar17([
            linha17(1, 2, 10.00, '61091000'),
            linha17(2, 1, 25.50, '62034200'),
            linha17(3, 3, 3.3333, '39269090'),
        ], 50.00, 0, motorFake17($chamadas));

        session(['business.id' => $biz]);
        $m = (new ReflectionClass(NfeService::class))->getMethod('emitirInterno');
        $m->setAccessible(true);
        $r = $m->invoke(app(NfeService::class), $biz, [
            'transaction_id' => $txId, 'modelo' => '65', 'dets' => $itens['dets'],
            'total' => $itens['total'], 'metadata' => $itens['metadata'], 'valor_total' => 50.00,
        ], '65', $txId);

        expect((int) $r->id)->toBe((int) $id);
        expect((int) $r->numero)->toBe(717001);
        expect(NfeEmissao::withoutGlobalScopes()->where('business_id', $biz)->where('transaction_id', $txId)->count())->toBe(1);
    } finally {
        NfeEmissao::withoutGlobalScopes()->withTrashed()->where('business_id', $biz)->where('transaction_id', $txId)->forceDelete();
    }
});
