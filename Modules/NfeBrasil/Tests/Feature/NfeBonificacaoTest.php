<?php

declare(strict_types=1);

// @covers-us US-NFE-077 — NF-e de bonificação: CFOP 5910/6910, natOp BONIFICACAO, tPag 90 (pedido P-154).

use App\Events\SellCreatedOrModified;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Listeners\EmitirNfceAoFinalizarVenda;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * P-154 · Sabor Brasil bonifica supermercado: a venda com `transactions.sub_type = 'bonificacao'`
 * sai em NF-e com CFOP 5910 (dentro do estado) ou 6910 (fora), natOp BONIFICACAO e o pagamento
 * tPag 90 (sem pagamento, vPag 0). Itens, tributação e totais são os da venda.
 *
 * O XML é montado pelo próprio `buildXml` (privado, via reflection, sem SEFAZ nem certificado),
 * para a mesma venda nas duas formas: a comum e a bonificada. Valores e CFOPs são fixture.
 * Tenant fictício 98 (`seededTenant`) — nunca biz=1 nem biz=4 (ADR 0358). Nada é gravado.
 */
function p154Svc(): NfeService
{
    return new NfeService(\Mockery::mock(CertificadoService::class));
}

/** NF-e de venda comum (CFOP 5102, tPag 01) com dois itens: 100,00 + 50,00. */
function p154DadosComum(): array
{
    $item = fn (string $cprod, float $v) => [
        'cprod' => $cprod, 'xprod' => "TEMPERO {$cprod}", 'ncm' => '21039021', 'cfop' => '5102',
        'ucm' => 'UN', 'qcom' => 1.0, 'vuncom' => $v, 'vprod' => $v,
        'utrib' => 'UN', 'qtrib' => 1.0, 'vuntrib' => $v, 'ind_tot' => 1,
        'icms' => ['cst_csosn' => '00', 'orig' => 0, 'modbc' => 3, 'vbc' => $v, 'picms' => 18.00, 'vicms' => round($v * 0.18, 2)],
        'pis' => ['cst' => '01', 'vbc' => $v, 'ppis' => 1.65, 'vpis' => round($v * 0.0165, 2)],
        'cofins' => ['cst' => '01', 'vbc' => $v, 'pcofins' => 7.60, 'vcofins' => round($v * 0.076, 2)],
    ];

    return [
        'transaction_id' => null,
        'nat_op' => 'VENDA DE MERCADORIA',
        'dest' => [
            'nome' => 'SUPERMERCADO FIXTURE LTDA', 'cnpj' => '99888777000199', 'ind_ie_dest' => '9',
            'logradouro' => 'AV FIXTURE', 'numero' => '200', 'bairro' => 'CENTRO', 'municipio' => 'SAO PAULO',
            'cod_municipio' => '3550308', 'uf' => 'SP', 'cep' => '01310100',
        ],
        'dets' => [$item('A', 100.00), $item('B', 50.00)],
        'total' => [
            'v_prod' => 150.00, 'v_bc_icms' => 150.00, 'v_icms' => 27.00, 'v_pis' => 2.48,
            'v_cofins' => 11.40, 'v_nf' => 150.00, 'v_desc' => 0, 'v_frete' => 0,
        ],
        'pag' => [['tpag' => '01', 'vpag' => 150.00]],
        'valor_total' => 150.00,
        'inf_cpl' => 'Venda PDV fixture.',
    ];
}

function p154Xml(array $dadosNfe): string
{
    $bizId = test()->seededTenant()->id;
    $business = (object) ['id' => $bizId, 'cidade_id' => 0, 'regime' => 3, 'ambiente' => 2, 'numero_serie_nfe' => '1', 'ncm_padrao' => '21039021'];
    $emit = [
        'uf' => 'SP', 'cod_municipio' => '3550308', 'municipio' => 'SAO PAULO', 'cnpj' => '11222333000181',
        'razao_social' => 'EMPRESA FIXTURE LTDA', 'nome_fantasia' => 'FIXTURE', 'ie' => '110042490114', 'crt' => 3,
        'logradouro' => 'RUA FIXTURE', 'numero_end' => '100', 'bairro' => 'CENTRO', 'cep' => '01001000', 'ambiente' => 2,
    ];
    $emissao = new NfeEmissao();
    $emissao->business_id = $bizId;
    $emissao->modelo = '55';
    $emissao->serie = '1';
    $emissao->numero = 1;

    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);

    return (string) $m->invoke(p154Svc(), $business, $emissao, $dadosNfe, $emit);
}

/** @return list<string> todas as ocorrências da tag simples (sem namespace prefixado). */
function p154Tags(string $xml, string $nome): array
{
    preg_match_all("#<{$nome}>([^<]*)</{$nome}>#", $xml, $mm);

    return $mm[1];
}

afterEach(fn () => \Mockery::close());

it('UC-FNFE-57 · CFOP 5910 dentro do estado e 6910 fora; natOp e pagamento de bonificação', function () {
    $dentro = NfeService::comoBonificacao(p154DadosComum(), 'SP', 'SP');
    $fora = NfeService::comoBonificacao(p154DadosComum(), 'SP', 'RJ');

    expect(array_column($dentro['dets'], 'cfop'))->toBe(['5910', '5910']);
    expect(array_column($fora['dets'], 'cfop'))->toBe(['6910', '6910']);
    expect($dentro['nat_op'])->toBe('BONIFICACAO');
    expect($dentro['pag'])->toBe([['tpag' => '90', 'vpag' => 0]]);
    // Totais e o resto do item ficam os da venda.
    expect($dentro['total'])->toBe(p154DadosComum()['total']);
    expect(array_column($dentro['dets'], 'vprod'))->toBe([100.00, 50.00]);
});

it('UC-FNFE-57 · o CFOP informado por quem chama ainda vence; exterior recusa', function () {
    expect(array_column(NfeService::comoBonificacao(p154DadosComum(), 'SP', 'SP', '5911')['dets'], 'cfop'))
        ->toBe(['5911', '5911']);
    expect(fn () => NfeService::comoBonificacao(p154DadosComum(), 'SP', 'EX'))
        ->toThrow(RuntimeException::class, 'exterior');
});

it('UC-FNFE-57 · dupla prova no XML: mesmos itens, vProd e vNF iguais; CFOP, natOp e tPag mudam', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: buildXml lê nfe_business_configs.');
    }

    $comum = p154Xml(p154DadosComum());
    $bonif = p154Xml(NfeService::comoBonificacao(p154DadosComum(), 'SP', 'SP'));

    expect(p154Tags($comum, 'vProd'))->toBe(p154Tags($bonif, 'vProd'));
    expect(p154Tags($bonif, 'vProd'))->toBe(['100.00', '50.00', '150.00']);
    expect(p154Tags($comum, 'vNF'))->toBe(['150.00']);
    expect(p154Tags($bonif, 'vNF'))->toBe(['150.00']);

    expect(p154Tags($comum, 'CFOP'))->toBe(['5102', '5102']);
    expect(p154Tags($bonif, 'CFOP'))->toBe(['5910', '5910']);
    expect(p154Tags($comum, 'natOp'))->toBe(['VENDA DE MERCADORIA']);
    expect(p154Tags($bonif, 'natOp'))->toBe(['BONIFICACAO']);
    expect(p154Tags($comum, 'tPag'))->toBe(['01']);
    expect(p154Tags($bonif, 'tPag'))->toBe(['90']);
    expect(p154Tags($comum, 'vPag'))->toBe(['150.00']);
    expect(p154Tags($bonif, 'vPag'))->toBe(['0.00']);
});

it('UC-FNFE-58 · NFC-e de bonificação recusa antes de montar a nota', function () {
    $tx = (new Transaction())->forceFill([
        'id' => 999154, 'business_id' => test()->seededTenant()->id, 'type' => 'sell',
        'sub_type' => 'bonificacao', 'final_total' => 150.00,
    ]);

    expect(fn () => p154Svc()->emitirParaTransaction($tx, '65'))
        ->toThrow(RuntimeException::class, 'bonificação: emita NF-e (modelo 55)');
});

it('UC-FNFE-58 · só sub_type bonificacao é bonificação (venda comum, devolução e outros seguem iguais)', function () {
    foreach ([null, '', 'repair', 'sell_return', 'BONIFICACAO'] as $sub) {
        expect(NfeService::ehBonificacao((object) ['sub_type' => $sub]))->toBeFalse();
    }
    expect(NfeService::ehBonificacao((object) ['sub_type' => 'bonificacao']))->toBeTrue();
});

it('UC-FNFE-58 · a NFC-e automática do PDV não dispara para bonificação; a venda comum segue o fluxo', function () {
    config(['nfebrasil.auto_emission_on_sell_completed' => false]);
    $venda = fn (?string $sub) => (new Transaction())->forceFill([
        'id' => 999154, 'business_id' => test()->seededTenant()->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'sub_type' => $sub,
    ]);
    $msgFlag = 'NFC-e auto-emission DISABLED — listener no-op (global flag)';

    Log::spy();
    (new EmitirNfceAoFinalizarVenda())->handle(new SellCreatedOrModified($venda('bonificacao')));
    Log::shouldNotHaveReceived('info', [$msgFlag, \Mockery::any()]);

    (new EmitirNfceAoFinalizarVenda())->handle(new SellCreatedOrModified($venda(null)));
    Log::shouldHaveReceived('info', [$msgFlag, \Mockery::any()]);
});
