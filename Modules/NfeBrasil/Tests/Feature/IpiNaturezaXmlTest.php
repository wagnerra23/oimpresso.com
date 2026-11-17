<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * UC-NFTR-66 · o IPI calculado pela natureza da tela vai ao XML (RUNBOOK-tributacao §Naturezas,
 * dependência 1). Antes, o gerador gravava `vIPI = 0.00` fixo e não montava o grupo IPI: a nota
 * de uma indústria saía sem o imposto.
 *
 * REGRA MESTRE (cálculo de valor) — prova por dois caminhos:
 *  (1) este arquivo: o que entra no item (CST, cEnq, base, alíquota, valor) é o que sai no grupo
 *      IPI, e o total soma o IPI no vIPI e no vNF (1.000 + 100 = 1.100);
 *  (2) `NaturezaTelaMotorTest` UC-NFTR-66: o motor calcula esse valor (1.000 × 5% = 50).
 * Prova de segurança: item sem a chave `ipi` (tudo que não é natureza da tela) sai sem grupo IPI e
 * com `vIPI` 0,00, como sempre saiu.
 *
 * Empresa 98, tenant fictício (ADR 0358). Usa só `buildXml` por reflection, sem SEFAZ nem certificado;
 * a única leitura de banco é a configuração da reforma (sem linha = legado).
 */

beforeEach(function () {
    // Mesmo corte do SerializacaoIbsCbsTest: buildXml lê `cidades` e `nfe_business_configs`, que só
    // existem no schema MySQL (ADR 0101). Roda na lane `nfebrasil-pest` (MySQL).
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: buildXml lê cidades e nfe_business_configs (schema MySQL, ADR 0101)');
    }
    session(['business.id' => 98]);
});

function ipiXml(?array $ipi, float $vIpi = 0.0, float $vProd = 1000.00): string
{
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));

    $business = (object) [
        'id' => 98, 'cidade_id' => 0, 'regime' => 3, 'ambiente' => 2,
        'numero_serie_nfe' => '1', 'ncm_padrao' => '77071004',
    ];
    $emit = [
        'uf' => 'SC', 'cod_municipio' => '4205407', 'municipio' => 'FLORIANOPOLIS',
        'cnpj' => '11222333000181', 'razao_social' => 'INDUSTRIA TESTE IPI LTDA', 'nome_fantasia' => 'TESTE IPI',
        'ie' => '251234567', 'crt' => 3, 'logradouro' => 'RUA DE TESTE', 'numero_end' => '100',
        'bairro' => 'CENTRO', 'cep' => '88010000', 'ambiente' => 2,
    ];

    $emissao = new NfeEmissao();
    $emissao->business_id = 98;
    $emissao->transaction_id = null;
    $emissao->modelo = '55';
    $emissao->serie = '1';
    $emissao->numero = 1;

    $det = [
        'cprod' => 'TESTE-IPI', 'xprod' => 'PRODUTO INDUSTRIALIZADO', 'ncm' => '77071004', 'cfop' => '5101',
        'ucm' => 'UN', 'qcom' => 1.0, 'vuncom' => $vProd, 'vprod' => $vProd,
        'utrib' => 'UN', 'qtrib' => 1.0, 'vuntrib' => $vProd, 'ind_tot' => 1,
        'icms' => ['cst_csosn' => '00', 'orig' => 0, 'modbc' => 3, 'vbc' => $vProd, 'picms' => 17.00, 'vicms' => round($vProd * 0.17, 2)],
        'pis' => ['cst' => '01', 'vbc' => $vProd, 'ppis' => 0.65, 'vpis' => round($vProd * 0.0065, 2)],
        'cofins' => ['cst' => '01', 'vbc' => $vProd, 'pcofins' => 3.00, 'vcofins' => round($vProd * 0.03, 2)],
    ];
    if ($ipi !== null) {
        $det['ipi'] = $ipi;
    }

    $vNf = $vProd + $vIpi;
    $dados = [
        'transaction_id' => null,
        'nat_op' => 'VENDA DE PRODUCAO',
        'dest' => [
            'nome' => 'CLIENTE TESTE LTDA', 'cnpj' => '99888777000199', 'ind_ie_dest' => '9',
            'logradouro' => 'AV DO CLIENTE', 'numero' => '200', 'bairro' => 'CENTRO',
            'municipio' => 'FLORIANOPOLIS', 'cod_municipio' => '4205407', 'uf' => 'SC', 'cep' => '88010001',
        ],
        'dets' => [$det],
        'total' => [
            'v_prod' => $vProd, 'v_bc_icms' => $vProd, 'v_icms' => round($vProd * 0.17, 2),
            'v_pis' => round($vProd * 0.0065, 2), 'v_cofins' => round($vProd * 0.03, 2),
            'v_ipi' => $vIpi, 'v_nf' => $vNf, 'v_desc' => 0, 'v_frete' => 0,
        ],
        'pag' => [['tpag' => '01', 'vpag' => $vNf]],
        'valor_total' => $vNf,
        'inf_cpl' => 'Teste IPI natureza.',
    ];

    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);

    return (string) $m->invoke($svc, $business, $emissao, $dados, $emit);
}

function ipiTag(string $xml, string $name): ?string
{
    return preg_match("#<{$name}>([^<]*)</{$name}>#", $xml, $mm) ? $mm[1] : null;
}

it('UC-NFTR-66 · IPI tributado (CST 50): o grupo IPI sai com cEnq, base, alíquota e valor, e entra no vIPI e no vNF', function () {
    $xml = ipiXml(['cst' => '50', 'c_enq' => '999', 'vbc' => 1000.0, 'pipi' => 10.0, 'vipi' => 100.0], 100.0);

    expect(preg_match('#<IPI>(.*?)</IPI>#s', $xml, $g))->toBe(1);
    $grupo = $g[1];
    // À mão: 1.000 × 10% = 100,00 · vNF = 1.000 + 100 = 1.100,00
    expect(ipiTag($grupo, 'cEnq'))->toBe('999')
        ->and($grupo)->toContain('<IPITrib>')
        ->and(ipiTag($grupo, 'CST'))->toBe('50')
        ->and(ipiTag($grupo, 'vBC'))->toBe('1000.00')
        ->and((float) ipiTag($grupo, 'pIPI'))->toBe(10.0)
        ->and(ipiTag($grupo, 'vIPI'))->toBe('100.00');

    preg_match('#<ICMSTot>(.*?)</ICMSTot>#s', $xml, $t);
    expect(ipiTag($t[1], 'vIPI'))->toBe('100.00')
        ->and(ipiTag($t[1], 'vNF'))->toBe('1100.00');
});

it('UC-NFTR-66 · IPI não tributado (CST 53): o grupo sai sem valor (IPINT)', function () {
    $xml = ipiXml(['cst' => '53', 'c_enq' => '999', 'vbc' => 0.0, 'pipi' => 0.0, 'vipi' => 0.0]);

    expect(preg_match('#<IPI>(.*?)</IPI>#s', $xml, $g))->toBe(1);
    expect($g[1])->toContain('<IPINT>')
        ->and($g[1])->not->toContain('<IPITrib>')
        ->and(ipiTag($g[1], 'CST'))->toBe('53');
    preg_match('#<ICMSTot>(.*?)</ICMSTot>#s', $xml, $t);
    expect(ipiTag($t[1], 'vIPI'))->toBe('0.00')
        ->and(ipiTag($t[1], 'vNF'))->toBe('1000.00');
});

it('UC-NFTR-66 · item sem natureza da tela: nenhum grupo IPI e vIPI 0,00, como antes', function () {
    $xml = ipiXml(null);

    expect($xml)->not->toContain('<IPI>');
    preg_match('#<ICMSTot>(.*?)</ICMSTot>#s', $xml, $t);
    expect(ipiTag($t[1], 'vIPI'))->toBe('0.00')
        ->and(ipiTag($t[1], 'vNF'))->toBe('1000.00');
});
