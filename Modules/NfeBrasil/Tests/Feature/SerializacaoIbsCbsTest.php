<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-021 / ADR 0321 (PR-D) — serialização do grupo UB (IBS/CBS) no XML.
 *
 * REGRA MESTRE (memory/proibicoes.md · cálculo de valor): o grupo UB só é serializado
 * quando o business está opt-in (schema PL_010 via `reforma_tributaria_modo` full/hybrid)
 * E há CST IBS/CBS configurado pro item. Legacy/Simples → grupo omitido → XML byte-idêntico.
 *
 * Prova de valor por 2 caminhos: (1) Pest numérico — vIBSUF/vCBS no XML == base×alíquota;
 * (2) cross-check motor — o valor que o MotorTributarioService calcula é o que sai no XML.
 * Prova de segurança: flag OFF (e flag ON sem regra) = XML idêntico ao de hoje (normalizado).
 *
 * Business isolado 999999 (não toca dogfood biz=1). MySQL-only (ADR 0101) — nfe_business_configs
 * não existe no SQLite :memory: da lane de sanidade; roda na lane `nfebrasil-pest` (MySQL),
 * que instala o lock pinado (sped-nfe e075ec4, ADR 0321) — mesma lib que a produção instala.
 */

const IBSCBS_BIZ = 999999;

/**
 * Invoca NfeService::buildXml (private) via reflection, sem tocar SEFAZ/certificado.
 * Passa emitOverride com uf/cod_municipio → dispensa linhas em business_locations/cidades.
 * Única dependência de DB é nfe_business_configs (lido por schemaReforma).
 *
 * @param  array<string,mixed>|null  $ibscbs  sub-array IBS/CBS do item (null = item sem reforma)
 */
function montarXmlIbsCbs(?array $ibscbs, float $vProd = 1000.00): string
{
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));

    $business = (object) [
        'id'               => IBSCBS_BIZ,
        'cidade_id'        => 0,
        'regime'           => 3, // CRT 3 = Regime Normal (ICMS por CST)
        'ambiente'         => 2, // homologação
        'numero_serie_nfe' => '1',
        'ncm_padrao'       => '61091000',
    ];

    $emitOverride = [
        'uf'            => 'SP',
        'cod_municipio' => '3550308', // São Paulo (IBGE)
        'municipio'     => 'SAO PAULO',
        'cnpj'          => '11222333000181',
        'razao_social'  => 'EMPRESA TESTE REFORMA LTDA',
        'nome_fantasia' => 'TESTE REFORMA',
        'ie'            => '110042490114',
        'crt'           => 3,
        'logradouro'    => 'RUA DE TESTE',
        'numero_end'    => '100',
        'bairro'        => 'CENTRO',
        'cep'           => '01001000',
        'ambiente'      => 2,
    ];

    $emissao = new NfeEmissao();
    $emissao->business_id    = IBSCBS_BIZ;
    $emissao->transaction_id = null;
    $emissao->modelo         = '55';
    $emissao->serie          = '1';
    $emissao->numero         = 1;

    $det = [
        'cprod'   => 'TESTE-1',
        'xprod'   => 'PRODUTO TESTE REFORMA',
        'ncm'     => '61091000',
        'cfop'    => '5102',
        'ucm'     => 'UN',
        'qcom'    => 1.0,
        'vuncom'  => $vProd,
        'vprod'   => $vProd,
        'utrib'   => 'UN',
        'qtrib'   => 1.0,
        'vuntrib' => $vProd,
        'ind_tot' => 1,
        'icms'    => [
            'cst_csosn' => '00', // tributada integralmente (CRT 3)
            'orig'      => 0,
            'modbc'     => 3,
            'vbc'       => $vProd,
            'picms'     => 18.00,
            'vicms'     => round($vProd * 0.18, 2),
        ],
        'pis'    => ['cst' => '01', 'vbc' => $vProd, 'ppis' => 1.65, 'vpis' => round($vProd * 0.0165, 2)],
        'cofins' => ['cst' => '01', 'vbc' => $vProd, 'pcofins' => 7.60, 'vcofins' => round($vProd * 0.076, 2)],
    ];
    if ($ibscbs !== null) {
        $det['ibscbs'] = $ibscbs;
    }

    $dadosNfe = [
        'transaction_id' => null,
        'nat_op'         => 'VENDA DE MERCADORIA',
        'dest' => [
            'nome'          => 'CLIENTE TESTE LTDA',
            'cnpj'          => '99888777000199',
            'ind_ie_dest'   => '9',
            'logradouro'    => 'AV DO CLIENTE',
            'numero'        => '200',
            'bairro'        => 'CENTRO',
            'municipio'     => 'SAO PAULO',
            'cod_municipio' => '3550308',
            'uf'            => 'SP',
            'cep'           => '01310100',
        ],
        'dets'  => [$det],
        'total' => [
            'v_prod'    => $vProd,
            'v_bc_icms' => $vProd,
            'v_icms'    => round($vProd * 0.18, 2),
            'v_pis'     => round($vProd * 0.0165, 2),
            'v_cofins'  => round($vProd * 0.076, 2),
            'v_nf'      => $vProd,
            'v_desc'    => 0,
            'v_frete'   => 0,
        ],
        'pag'         => [['tpag' => '01', 'vpag' => $vProd]],
        'valor_total' => $vProd,
        'inf_cpl'     => 'Teste serializacao IBS/CBS.',
    ];

    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);

    return (string) $m->invoke($svc, $business, $emissao, $dadosNfe, $emitOverride);
}

/** Zera os campos voláteis (aleatórios/tempo) pra permitir comparação byte-a-byte determinística. */
function normalizarVolatil(string $xml): string
{
    return preg_replace(
        [
            '#<cNF>\d+</cNF>#',
            '#<cDV>\d+</cDV>#',
            '#<dhEmi>[^<]+</dhEmi>#',
            '#<dhSaiEnt>[^<]+</dhSaiEnt>#',
            '#Id="NFe\d+"#',
        ],
        ['<cNF>X</cNF>', '<cDV>X</cDV>', '<dhEmi>X</dhEmi>', '<dhSaiEnt>X</dhSaiEnt>', 'Id="NFeX"'],
        $xml,
    ) ?? $xml;
}

/** Extrai o texto de uma tag simples (default namespace, sem prefixo). Primeira ocorrência. */
function tag(string $xml, string $name): ?string
{
    return preg_match("#<{$name}>([^<]*)</{$name}>#", $xml, $mm) ? $mm[1] : null;
}

/**
 * Isola o fragmento do grupo UB do item `<IBSCBS>...</IBSCBS>`. Necessário porque
 * <CST> e <vBC> também aparecem em ICMS/PIS/COFINS — buscar no XML inteiro pegaria
 * o CST do ICMS. `</IBSCBS>` não colide com `</IBSCBSTot>` (strings distintas).
 */
function fragIbsCbs(string $xml): string
{
    return preg_match('#<IBSCBS>(.*?)</IBSCBS>#s', $xml, $mm) ? $mm[1] : '';
}

/** IBS/CBS de exemplo: base 1.000, IBS 0,1% (fração 0.001) e CBS 8,8% (fração 0.088). */
function ibscbsExemplo(float $vProd = 1000.00): array
{
    return [
        'cst'          => '000',
        'cst_cbs'      => '000',
        'c_class_trib' => '000001',
        'vbc'          => $vProd,
        'aliquota_ibs' => 0.001,
        'aliquota_cbs' => 0.088,
        'valor_ibs'    => round($vProd * 0.001, 2),
        'valor_cbs'    => round($vProd * 0.088, 2),
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: nfe_business_configs requer schema MySQL (ADR 0101)');
    }
    session(['business.id' => IBSCBS_BIZ]);
    NfeBusinessConfig::withoutGlobalScopes()->where('business_id', IBSCBS_BIZ)->delete();
    NfeFiscalRule::withoutGlobalScopes()->where('business_id', IBSCBS_BIZ)->delete();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        NfeBusinessConfig::withoutGlobalScopes()->where('business_id', IBSCBS_BIZ)->delete();
        NfeFiscalRule::withoutGlobalScopes()->where('business_id', IBSCBS_BIZ)->delete();
    }
    \Mockery::close();
});

function configModo(string $modo): void
{
    NfeBusinessConfig::create([
        'business_id'             => IBSCBS_BIZ,
        'regime'                  => 'lucro_presumido',
        'tributacao_default'      => ['cfop' => '5102', 'cst' => '000'],
        'reforma_tributaria_modo' => $modo,
    ]);
}

it('modo full + regra IBS/CBS → serializa grupo UB com valores corretos', function () {
    configModo('full');
    $xml = montarXmlIbsCbs(ibscbsExemplo());

    // Estrutura do grupo UB (NT 2025.002 · PL_010_V1).
    expect($xml)->toContain('<IBSCBS>')
        ->toContain('<gIBSCBS>')
        ->toContain('<gIBSUF>')
        ->toContain('<gIBSMun>')
        ->toContain('<gCBS>')
        ->toContain('<IBSCBSTot>');

    // Escopo no fragmento do item (CST/vBC também existem em ICMS/PIS/COFINS).
    $frag = fragIbsCbs($xml);

    // CST único do grupo (usa cst_ibs) + classe de tributação.
    expect(tag($frag, 'CST'))->toBe('000');
    expect(tag($frag, 'cClassTrib'))->toBe('000001');

    // gIBSUF recebe o IBS combinado; gIBSMun zerado (modelagem v1 sem split).
    expect(tag($frag, 'pIBSUF'))->toBe('0.1000');   // 0.001 × 100
    expect(tag($frag, 'vIBSUF'))->toBe('1.00');     // 1000 × 0.001
    expect(tag($frag, 'pIBSMun'))->toBe('0.0000');
    expect(tag($frag, 'vIBSMun'))->toBe('0.00');
    expect(tag($frag, 'vIBS'))->toBe('1.00');       // vIBSUF + vIBSMun

    // CBS.
    expect(tag($frag, 'pCBS'))->toBe('8.8000');     // 0.088 × 100
    expect(tag($frag, 'vCBS'))->toBe('88.00');      // 1000 × 0.088
})->group('nfe');

it('modo full → XML gerado é válido contra o XSD PL_010_V1', function () {
    configModo('full');
    $xml = montarXmlIbsCbs(ibscbsExemplo());

    // Localiza o schema NFe do PL_010 no vendor pinado (e075ec4).
    $candidatos = glob(base_path('vendor/nfephp-org/sped-nfe/schemes/PL_010_V1/nfe_v4.00.xsd')) ?: [];
    if ($candidatos === []) {
        test()->markTestSkipped('XSD PL_010_V1 (nfe_v4.00.xsd) não encontrado no vendor — schemaValidate pulado.');
    }

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    libxml_use_internal_errors(true);
    $ok   = $dom->schemaValidate($candidatos[0]);
    $errs = array_map(fn ($e) => trim($e->message), libxml_get_errors());
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    expect($ok)->toBeTrue(implode("\n", $errs));
})->group('nfe');

it('cross-check numérico: valor do MotorTributarioService é o que sai no XML', function () {
    configModo('full');

    // Regra fiscal com IBS/CBS (alíquotas em fração: 0.001 = 0,1%; 0.088 = 8,8%).
    NfeFiscalRule::create([
        'business_id'    => IBSCBS_BIZ,
        'ncm'            => '61091000',
        'uf_origem'      => 'SP',
        'uf_destino'     => 'SP',
        'cfop'           => '5102',
        'csosn'          => null,
        'cst'            => '00',
        'aliquota_icms'  => 0.0,
        'aliquota_pis'   => 0.0,
        'aliquota_cofins' => 0.0,
        'aliquota_ipi'   => 0.0,
        'c_class_trib'   => '000001',
        'cst_ibs'        => '000',
        'cst_cbs'        => '000',
        'aliquota_ibs'   => 0.001,
        'aliquota_cbs'   => 0.088,
    ]);

    // Motor resolve a regra → calcula valor_ibs = base × alíquota. base 1.000.
    $tributo = (new MotorTributarioService())->calcular(
        new ProdutoFiscalContext(ncm: '61091000', valor: 1000.00, description: 'x'),
        businessId: IBSCBS_BIZ,
        ufOrigem: 'SP',
        ufDestino: 'SP',
    );

    $ibscbs = [
        'cst'          => $tributo->cst_ibs,
        'cst_cbs'      => $tributo->cst_cbs,
        'c_class_trib' => $tributo->c_class_trib,
        'vbc'          => 1000.00,
        'aliquota_ibs' => $tributo->aliquota_ibs,
        'aliquota_cbs' => $tributo->aliquota_cbs,
        'valor_ibs'    => $tributo->valor_ibs,
        'valor_cbs'    => $tributo->valor_cbs,
    ];

    $frag = fragIbsCbs(montarXmlIbsCbs($ibscbs));

    // Caminho 1 (motor): valor calculado == valor no XML.
    expect((float) tag($frag, 'vIBSUF'))->toBe($tributo->valor_ibs);
    expect((float) tag($frag, 'vCBS'))->toBe($tributo->valor_cbs);

    // Caminho 2 (à mão): base × alíquota == valor no XML.
    expect((float) tag($frag, 'vIBSUF'))->toBe(round(1000.00 * 0.001, 2)); // 1.00
    expect((float) tag($frag, 'vCBS'))->toBe(round(1000.00 * 0.088, 2));   // 88.00
})->group('nfe');

it('modo legacy → sem grupo UB (byte-idêntico ao XML de hoje)', function () {
    configModo('legacy');
    $xml = montarXmlIbsCbs(ibscbsExemplo());

    expect($xml)->not->toContain('IBSCBS')
        ->not->toContain('gIBSUF')
        ->not->toContain('gCBS');
})->group('nfe');

it('modo full mas item SEM cst IBS/CBS → grupo omitido (inerte)', function () {
    configModo('full');
    $semCst = ibscbsExemplo();
    $semCst['cst'] = null; // Simples/fallback: motor devolve cst_ibs null → não serializa

    $xml = montarXmlIbsCbs($semCst);

    expect($xml)->not->toContain('IBSCBS')
        ->not->toContain('gIBSUF');
})->group('nfe');

it('turning-on sem regra == byte-idêntico ao legacy (o schema sozinho não muda o XML)', function () {
    // full SEM item ibscbs (nenhum grupo emitido).
    configModo('full');
    $xmlFullSemRegra = normalizarVolatil(montarXmlIbsCbs(null));

    // legacy (schema null).
    NfeBusinessConfig::withoutGlobalScopes()->where('business_id', IBSCBS_BIZ)->delete();
    configModo('legacy');
    $xmlLegacy = normalizarVolatil(montarXmlIbsCbs(null));

    expect($xmlFullSemRegra)->toBe($xmlLegacy);
})->group('nfe');
