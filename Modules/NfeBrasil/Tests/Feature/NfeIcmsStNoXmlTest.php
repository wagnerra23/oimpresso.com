<?php

declare(strict_types=1);

// @covers-us US-NFE-072 — o ICMS-ST calculado pelo motor vai ao XML e soma no vNF (R-NFE-043).

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * R-NFE-043 · ICMS-ST no grupo do CST e nos totais; vNF pela regra W16 do leiaute (soma vST).
 *
 * Dupla prova (fixture, conta à mão): item de 1.000,00 de SC para contribuinte no RJ, ICMS próprio 12%,
 * MVA 40%, interna do RJ 18%, sem IPI.
 *   base ST = 1.000,00 × 1,40 = 1.400,00 · ST = 1.400,00 × 0,18 − 120,00 = 252,00 − 120,00 = 132,00
 *   vNF = 1.000,00 + 132,00 = 1.132,00 (o comprador paga o imposto retido)
 */

function motorSt43(string $cst, ?string $csosn = null): MotorTributarioService
{
    return new class($cst, $csosn) extends MotorTributarioService {
        public function __construct(private string $cst, private ?string $csosn) {}

        public function calcularComDestino(ProdutoFiscalContext $p, int $businessId, string $ufOrigem, string $ufDestino,
            ?float $aliquotaInternaDestino = null, ?bool $destinatarioContribuinte = null, ?int $operacaoId = null,
            ?string $dataEmissao = null): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '6102', csosn: $this->csosn, cst: $this->csosn === null ? $this->cst : null,
                aliquota_icms: 0.12, aliquota_pis: 0.0, aliquota_cofins: 0.0, aliquota_ipi: 0.0,
                valor_icms: 120.0, valor_pis: 0.0, valor_cofins: 0.0, valor_ipi: 0.0, nivel_usado: 2,
                mva: 0.40, base_st: 1400.0, valor_st: 132.0,
            );
        }
    };
}

function montar43(MotorTributarioService $motor): array
{
    return app(NfeService::class)->montarItensNfe(
        linhas: [['sell_line_id' => 1, 'product_id' => 9001, 'cprod' => 'SKU1', 'xprod' => 'Produto 1',
            'ncm' => '61091000', 'unidade' => 'UN', 'quantidade' => 1.0, 'valor_unitario' => 1000.00]],
        valorNota: 1000.00, frete: 0, ncmDefault: '49111090', motor: $motor, businessId: 98,
        ufOrigem: 'SC', ufDestino: 'RJ', internaDestino: 0.18, destinatarioContribuinte: true,
    );
}

it('UC-FNFE-49 · item com ST leva os campos do ST e o vNF soma o vST', function () {
    $r = montar43(motorSt43('10'));
    $st = $r['dets'][0]['icms']['st'];

    expect($st)->toBe(['modbcst' => 4, 'pmvast' => 40.0, 'vbcst' => 1400.0, 'picmsst' => 18.0, 'vicmsst' => 132.0]);
    expect($r['total']['v_bc_st'])->toBe(1400.0);
    expect($r['total']['v_st'])->toBe(132.0);
    expect($r['total']['v_nf'])->toBe(1132.0);
    // Conferência da fórmula: base × interna − ICMS próprio.
    expect(round($st['vbcst'] * $st['picmsst'] / 100 - $r['dets'][0]['icms']['vicms'], 2))->toBe($st['vicmsst']);
});

it('UC-FNFE-49 · CSOSN 202 (Simples com ST) também leva o ST', function () {
    $r = montar43(motorSt43('', '202'));

    expect($r['dets'][0]['icms']['st']['vicmsst'])->toBe(132.0);
    expect($r['total']['v_nf'])->toBe(1132.0);
});

it('UC-FNFE-50 · regra com MVA mas CST sem ST é recusada com a saída', function () {
    expect(fn () => montar43(motorSt43('00')))
        ->toThrow(RuntimeException::class, 'não leva ST — ajuste a regra em Tributação');
});

it('UC-FNFE-49 · o XML leva o ST no ICMS10 e no ICMSTot, com vNF 1.132,00', function () {
    if (! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('nfe_business_configs não existe nesta lane (roda na nfebrasil-pest, MySQL).');
    }
    $itens = montar43(motorSt43('10'));
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));
    $business = (object) ['id' => 98, 'cidade_id' => 0, 'regime' => 3, 'ambiente' => 2, 'numero_serie_nfe' => '1'];
    $emit = ['uf' => 'SC', 'cod_municipio' => '4218707', 'municipio' => 'TUBARAO', 'cnpj' => '11222333000181',
        'razao_social' => 'EMPRESA FIXTURE LTDA', 'nome_fantasia' => 'FIXTURE', 'ie' => '250000000', 'crt' => 3,
        'logradouro' => 'RUA', 'numero_end' => '1', 'bairro' => 'CENTRO', 'cep' => '88700000', 'ambiente' => 2];
    $emissao = new NfeEmissao();
    $emissao->business_id = 98;
    $emissao->modelo = '55';
    $emissao->serie = '1';
    $emissao->numero = 1;
    $dados = [
        'nat_op' => 'VENDA DE MERCADORIA',
        'dest' => ['nome' => 'CLIENTE FIXTURE', 'cnpj' => '99888777000199', 'ind_ie_dest' => '1', 'ie' => '12345678',
            'logradouro' => 'RUA', 'numero' => '10', 'bairro' => 'CENTRO', 'municipio' => 'RIO DE JANEIRO',
            'cod_municipio' => '3304557', 'uf' => 'RJ', 'cep' => '20000000'],
        'dets' => $itens['dets'], 'total' => $itens['total'],
        'pag' => [['tpag' => '01', 'vpag' => $itens['total']['v_nf']]], 'valor_total' => $itens['total']['v_nf'],
    ];
    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);
    $xml = (string) $m->invoke($svc, $business, $emissao, $dados, $emit);

    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
    $v = fn (string $q) => $xp->query($q)->item(0)?->textContent;

    expect($v('//n:ICMS10/n:CST'))->toBe('10');
    expect($v('//n:ICMS10/n:vBCST'))->toBe('1400.00');
    expect($v('//n:ICMS10/n:pMVAST'))->toBe('40.0000');
    expect($v('//n:ICMS10/n:pICMSST'))->toBe('18.0000');
    expect($v('//n:ICMS10/n:vICMSST'))->toBe('132.00');
    expect($v('//n:ICMSTot/n:vBCST'))->toBe('1400.00');
    expect($v('//n:ICMSTot/n:vST'))->toBe('132.00');
    expect($v('//n:ICMSTot/n:vNF'))->toBe('1132.00');
    expect($v('//n:detPag/n:vPag'))->toBe('1132.00');
});
