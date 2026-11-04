<?php

declare(strict_types=1);

// Contrato da tela: resources/js/Pages/Fiscal/Nfe.casos.md — UC-FNFE-61 (GTIN) · UC-FNFE-62 (CEST)

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\GtinCest;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * Derivado do leiaute e do pedido, não do código: o cadastro do produto (código de barras e CEST) vai
 * para o XML, mas só o que a SEFAZ aceita. GTIN inválido = "SEM GTIN" (senão a nota é rejeitada);
 * CEST só com 7 dígitos (Convênio ICMS 142/2018).
 *
 * GTIN de fixture: 789100031550 + dígito 7 (soma ponderada 3/1 da direita → 7), prefixo 789 (Brasil).
 */
const CG_GTIN_OK = '7891000315507';
const CG_GTIN_DIGITO_ERRADO = '7891000315508';

function motorCestGtin(): MotorTributarioService
{
    return new class extends MotorTributarioService {
        public function __construct() {}

        public function calcularComDestino(ProdutoFiscalContext $p, int $businessId, string $ufOrigem, string $ufDestino,
            ?float $aliquotaInternaDestino = null, ?bool $destinatarioContribuinte = null, ?int $operacaoId = null,
            ?string $dataEmissao = null): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '5405', csosn: '500', cst: null,
                aliquota_icms: 0.0, aliquota_pis: 0.0, aliquota_cofins: 0.0, aliquota_ipi: 0.0,
                valor_icms: 0.0, valor_pis: 0.0, valor_cofins: 0.0, valor_ipi: 0.0, nivel_usado: 2,
            );
        }
    };
}

/** @param list<array{gtin:?string, cest:?string}> $cadastro */
function xmlCestGtin(array $cadastro): DOMXPath
{
    $linhas = [];
    foreach ($cadastro as $i => $c) {
        $linhas[] = ['sell_line_id' => $i + 1, 'product_id' => 9100 + $i, 'cprod' => 'SKU' . $i, 'xprod' => 'Produto ' . $i,
            'ncm' => '21039021', 'unidade' => 'UN', 'quantidade' => 1.0, 'valor_unitario' => 10.00,
            'gtin' => GtinCest::gtin($c['gtin']), 'cest' => GtinCest::cest($c['cest'])];
    }
    $itens = app(NfeService::class)->montarItensNfe(
        linhas: $linhas, valorNota: 10.00 * count($linhas), frete: 0, ncmDefault: '21039021', motor: motorCestGtin(),
        businessId: 98, ufOrigem: 'SC', ufDestino: 'SC', internaDestino: 0.17, destinatarioContribuinte: true,
    );
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));
    $business = (object) ['id' => 98, 'cidade_id' => 0, 'regime' => 1, 'ambiente' => 2, 'numero_serie_nfe' => '1'];
    $emit = ['uf' => 'SC', 'cod_municipio' => '4218707', 'municipio' => 'TUBARAO', 'cnpj' => '11222333000181',
        'razao_social' => 'EMPRESA FIXTURE LTDA', 'nome_fantasia' => 'FIXTURE', 'ie' => '250000000', 'crt' => 1,
        'logradouro' => 'RUA', 'numero_end' => '1', 'bairro' => 'CENTRO', 'cep' => '88700000', 'ambiente' => 2];
    $emissao = new NfeEmissao();
    $emissao->business_id = 98;
    $emissao->modelo = '55';
    $emissao->serie = '1';
    $emissao->numero = 1;
    $dados = [
        'nat_op' => 'VENDA DE MERCADORIA',
        'dest' => ['nome' => 'CLIENTE FIXTURE', 'cnpj' => '99888777000199', 'ind_ie_dest' => '1', 'ie' => '12345678',
            'logradouro' => 'RUA', 'numero' => '10', 'bairro' => 'CENTRO', 'municipio' => 'TUBARAO',
            'cod_municipio' => '4218707', 'uf' => 'SC', 'cep' => '88700000'],
        'dets' => $itens['dets'], 'total' => $itens['total'],
        'pag' => [['tpag' => '01', 'vpag' => $itens['total']['v_nf']]], 'valor_total' => $itens['total']['v_nf'],
    ];
    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);
    $dom = new DOMDocument();
    $dom->loadXML((string) $m->invoke($svc, $business, $emissao, $dados, $emit));
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');

    return $xp;
}

function campoCestGtin(DOMXPath $xp, string $q): ?string
{
    return $xp->query($q)->item(0)?->textContent;
}

it('UC-FNFE-61 · só GTIN válido passa; inválido, curto ou vazio vira nulo', function () {
    expect(GtinCest::gtin(CG_GTIN_OK))->toBe(CG_GTIN_OK);
    expect(GtinCest::gtin(CG_GTIN_DIGITO_ERRADO))->toBeNull();
    // Os códigos de 15/16 dígitos do Delphi (medidos no banco do Sabor Brasil) não são GTIN.
    expect(GtinCest::gtin('7891000315507123'))->toBeNull();
    expect(GtinCest::gtin('SKU-123'))->toBeNull();
    expect(GtinCest::gtin(null))->toBeNull();
});

it('UC-FNFE-62 · CEST só com 7 dígitos', function () {
    expect(GtinCest::cest('17.092.00'))->toBe('1709200');
    expect(GtinCest::cest('170920'))->toBeNull();
    expect(GtinCest::cest(''))->toBeNull();
});

it('UC-FNFE-61 · UC-FNFE-62 · o XML leva cEAN, cEANTrib e CEST do cadastro', function () {
    if (! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('nfe_business_configs não existe nesta lane (roda na nfebrasil-pest, MySQL).');
    }
    $xp = xmlCestGtin([
        ['gtin' => CG_GTIN_OK, 'cest' => '1709200'],
        ['gtin' => CG_GTIN_DIGITO_ERRADO, 'cest' => null],
    ]);

    expect(campoCestGtin($xp, '//n:det[@nItem="1"]/n:prod/n:cEAN'))->toBe(CG_GTIN_OK);
    expect(campoCestGtin($xp, '//n:det[@nItem="1"]/n:prod/n:cEANTrib'))->toBe(CG_GTIN_OK);
    expect(campoCestGtin($xp, '//n:det[@nItem="1"]/n:prod/n:CEST'))->toBe('1709200');
    // CEST vem logo depois do NCM (ordem do leiaute).
    $ncm = $xp->query('//n:det[@nItem="1"]/n:prod/n:NCM')->item(0);
    $seguinte = $ncm?->nextSibling;
    while ($seguinte !== null && $seguinte->nodeType !== XML_ELEMENT_NODE) {
        $seguinte = $seguinte->nextSibling;
    }
    expect($seguinte?->localName)->toBe('CEST');

    // Controle: GTIN com dígito errado e sem CEST = como era antes.
    expect(campoCestGtin($xp, '//n:det[@nItem="2"]/n:prod/n:cEAN'))->toBe('SEM GTIN');
    expect(campoCestGtin($xp, '//n:det[@nItem="2"]/n:prod/n:cEANTrib'))->toBe('SEM GTIN');
    expect($xp->query('//n:det[@nItem="2"]/n:prod/n:CEST')->length)->toBe(0);
});
