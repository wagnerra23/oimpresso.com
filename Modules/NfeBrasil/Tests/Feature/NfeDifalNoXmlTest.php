<?php

declare(strict_types=1);

// @covers-us US-NFE-072 — o DIFAL da venda interestadual vai ao XML (R-NFE-037).

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Exceptions\AliquotaInternaNaoCadastradaException;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * R-NFE-037 · grupo ICMSUFDest no XML (NfeService::buildXml via reflection, sem SEFAZ).
 *
 * Fixture (dupla prova, conta à mão no PR): item de 1.000,00 de SC para não contribuinte no RJ,
 * ICMS interestadual 12%, interna do RJ 20%, FCP do RJ 2%.
 *   DIFAL = 1.000,00 × (0,20 − 0,12) = 80,00 · FCP = 1.000,00 × 0,02 = 20,00 · vNF = 1.000,00.
 * Tenant fictício 98 (ADR 0358). A única leitura de banco é nfe_business_configs (schemaReforma).
 */

beforeEach(function () {
    if (! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('nfe_business_configs não existe nesta lane (roda na nfebrasil-pest, MySQL).');
    }
});

function xmlDifal37(?array $difal, float $vIcmsUfDest = 0.0, float $vFcpUfDest = 0.0): string
{
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));
    $business = (object) ['id' => 98, 'cidade_id' => 0, 'regime' => 3, 'ambiente' => 2, 'numero_serie_nfe' => '1'];
    $emit = [
        'uf' => 'SC', 'cod_municipio' => '4218707', 'municipio' => 'TUBARAO', 'cnpj' => '11222333000181',
        'razao_social' => 'EMPRESA FIXTURE LTDA', 'nome_fantasia' => 'FIXTURE', 'ie' => '250000000',
        'crt' => 3, 'logradouro' => 'RUA FIXTURE', 'numero_end' => '1', 'bairro' => 'CENTRO',
        'cep' => '88700000', 'ambiente' => 2,
    ];
    $emissao = new NfeEmissao();
    $emissao->business_id = 98;
    $emissao->modelo = '55';
    $emissao->serie = '1';
    $emissao->numero = 1;

    $det = [
        'cprod' => 'SKU1', 'xprod' => 'PRODUTO FIXTURE', 'ncm' => '61091000', 'cfop' => '6108',
        'ucm' => 'UN', 'qcom' => 1.0, 'vuncom' => 1000.0, 'vprod' => 1000.0, 'utrib' => 'UN',
        'qtrib' => 1.0, 'vuntrib' => 1000.0, 'ind_tot' => 1,
        'icms' => ['cst_csosn' => '00', 'orig' => 0, 'modbc' => 3, 'vbc' => 1000.0, 'picms' => 12.00, 'vicms' => 120.0],
        'pis' => ['cst' => '07', 'vbc' => 0, 'ppis' => 0, 'vpis' => 0],
        'cofins' => ['cst' => '07', 'vbc' => 0, 'pcofins' => 0, 'vcofins' => 0],
        'difal' => $difal,
    ];

    $dados = [
        'nat_op' => 'VENDA DE MERCADORIA',
        'dest' => [
            'nome' => 'CLIENTE FIXTURE', 'cpf' => '52998224725', 'ind_ie_dest' => '9',
            'logradouro' => 'RUA DESTINO', 'numero' => '10', 'bairro' => 'CENTRO',
            'municipio' => 'RIO DE JANEIRO', 'cod_municipio' => '3304557', 'uf' => 'RJ', 'cep' => '20000000',
        ],
        'dets' => [$det],
        'total' => [
            'v_prod' => 1000.0, 'v_bc_icms' => 1000.0, 'v_icms' => 120.0, 'v_pis' => 0, 'v_cofins' => 0,
            'v_icms_uf_dest' => $vIcmsUfDest, 'v_fcp_uf_dest' => $vFcpUfDest,
            'v_nf' => 1000.0, 'v_desc' => 0, 'v_frete' => 0,
        ],
        'pag' => [['tpag' => '01', 'vpag' => 1000.0]],
        'valor_total' => 1000.0,
    ];

    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);

    return (string) $m->invoke($svc, $business, $emissao, $dados, $emit);
}

function tag37(string $xml, string $pai, string $nome): ?string
{
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
    $no = $xp->query("//n:{$pai}/n:{$nome}")->item(0);

    return $no?->textContent;
}

it('UC-FNFE-34 · venda a não contribuinte de outra UF leva o grupo ICMSUFDest e o total dele', function () {
    $xml = xmlDifal37(
        ['vbc' => 1000.0, 'p_interna' => 0.20, 'p_inter' => 0.12, 'p_fcp' => 0.02, 'v_fcp' => 20.0, 'v_difal' => 80.0],
        80.0,
        20.0,
    );

    expect(tag37($xml, 'ICMSUFDest', 'vBCUFDest'))->toBe('1000.00');
    expect(tag37($xml, 'ICMSUFDest', 'pICMSUFDest'))->toBe('20.0000');
    expect(tag37($xml, 'ICMSUFDest', 'pICMSInter'))->toBe('12.00');
    expect(tag37($xml, 'ICMSUFDest', 'pICMSInterPart'))->toBe('100.00');
    expect(tag37($xml, 'ICMSUFDest', 'vICMSUFDest'))->toBe('80.00');
    expect(tag37($xml, 'ICMSUFDest', 'pFCPUFDest'))->toBe('2.0000');
    expect(tag37($xml, 'ICMSUFDest', 'vFCPUFDest'))->toBe('20.00');
    expect(tag37($xml, 'ICMSUFDest', 'vICMSUFRemet'))->toBe('0.00');
    expect(tag37($xml, 'ICMSTot', 'vICMSUFDest'))->toBe('80.00');
    expect(tag37($xml, 'ICMSTot', 'vFCPUFDest'))->toBe('20.00');
    // O DIFAL é do remetente para a UF de destino: não entra no valor da nota.
    expect(tag37($xml, 'ICMSTot', 'vNF'))->toBe('1000.00');
    expect(tag37($xml, 'ide', 'idDest'))->toBe('2');
});

it('UC-FNFE-35 · item sem DIFAL não leva o grupo e o total fica zero, como antes', function () {
    $xml = xmlDifal37(null);

    expect(str_contains($xml, '<ICMSUFDest>'))->toBeFalse();
    expect(tag37($xml, 'ICMSTot', 'vICMSUFDest'))->toBe('0.00');
    expect(tag37($xml, 'ICMSTot', 'vNF'))->toBe('1000.00');
});

it('UC-FNFE-36 · interestadual a não contribuinte sem interna cadastrada recusa com a UF no texto', function () {
    $svc = app(NfeService::class);
    $m = new ReflectionMethod(NfeService::class, 'garantirInternaDoDifal');
    $m->setAccessible(true);

    expect(fn () => $m->invoke($svc, true, 'SC', 'RJ', false, null))
        ->toThrow(AliquotaInternaNaoCadastradaException::class, 'Alíquota interna de RJ não cadastrada');

    // Controles: com a interna, contribuinte, mesma UF ou sem destinatário, não recusa.
    $m->invoke($svc, true, 'SC', 'RJ', false, 0.20);
    $m->invoke($svc, true, 'SC', 'RJ', true, null);
    $m->invoke($svc, true, 'SC', 'SC', false, null);
    $m->invoke($svc, false, 'SC', 'RJ', null, null);
    expect(true)->toBeTrue();
});
