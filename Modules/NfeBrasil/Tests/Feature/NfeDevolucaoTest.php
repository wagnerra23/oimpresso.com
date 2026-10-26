<?php

declare(strict_types=1);

// @covers-us US-NFE-064 — devolução de venda copia a nota de origem (playbook Fiscal thread 20).
// @covers R-NFE-026 · R-NFE-027

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\DevolucaoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * Thread 20 · a base e as alíquotas da devolução vêm da nota de origem, nunca da regra vigente.
 *
 * A nota de origem é montada pelo próprio `NfeService::buildXml` (o mesmo XML que vai à SEFAZ),
 * e a devolução é lida DELA. Os valores esperados têm a conta à mão ao lado (REGRA MESTRE):
 * o 2º caminho, independente do código, está no `_saida-20.md`.
 *
 * Tenant fictício 98 (ADR 0358) e cliente fictício 99 — nunca biz=1 nem biz=4. Os casos de XML
 * leem `nfe_business_configs` e os de saldo gravam em `nfe_emissoes`: MySQL-only, como o
 * `CnpjAlfanumericoTest` de onde o helper de XML foi copiado.
 */

const DEV20_NUMEROS = [992001, 992002, 992003, 992004, 992005, 992006];

function dev20MySql(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('MySQL-only: buildXml lê nfe_business_configs e o saldo grava em nfe_emissoes.');
    }
}

/** NF-e de venda montada pelo buildXml real: cada item é [cfop, qtd, vUn, vProd, pICMS, vICMS, vPIS]. */
function dev20Xml(int $bizId, array $dadosExtra, array $itens): string
{
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));
    $business = (object) ['id' => $bizId, 'cidade_id' => 0, 'regime' => 3, 'ambiente' => 2, 'numero_serie_nfe' => '1'];
    $emit = [
        'uf' => 'SP', 'cod_municipio' => '3550308', 'municipio' => 'SAO PAULO',
        'cnpj' => '11222333000181', 'razao_social' => 'EMPRESA TESTE DEVOLUCAO LTDA', // pii-allowlist (CNPJ fictício de documentação)
        'nome_fantasia' => 'TESTE', 'ie' => '110042490114', 'crt' => 3,
        'logradouro' => 'RUA DE TESTE', 'numero_end' => '100', 'bairro' => 'CENTRO', 'cep' => '01001000', 'ambiente' => 2,
    ];
    $emissao = new NfeEmissao();
    $emissao->business_id = $bizId;
    $emissao->modelo = '55';
    $emissao->serie = '1';
    $emissao->numero = 1;

    $dets = [];
    $tot = ['v_prod' => 0.0, 'v_bc_icms' => 0.0, 'v_icms' => 0.0, 'v_pis' => 0.0, 'v_cofins' => 0.0];
    foreach ($itens as $i => [$cfop, $q, $vun, $vprod, $picms, $vicms, $vpis]) {
        $dets[] = [
            'cprod' => 'SKU' . ($i + 1), 'xprod' => 'PRODUTO ' . ($i + 1), 'ncm' => '61091000', 'cfop' => $cfop,
            'ucm' => 'UN', 'qcom' => $q, 'vuncom' => $vun, 'vprod' => $vprod, 'utrib' => 'UN',
            'qtrib' => $q, 'vuntrib' => $vun, 'ind_tot' => 1,
            'icms'   => ['cst_csosn' => '00', 'orig' => 0, 'modbc' => 3, 'vbc' => $vprod, 'picms' => $picms, 'vicms' => $vicms],
            'pis'    => ['cst' => '01', 'vbc' => $vprod, 'ppis' => 1.65, 'vpis' => $vpis],
            'cofins' => ['cst' => '01', 'vbc' => $vprod, 'pcofins' => 7.60, 'vcofins' => 0.0],
        ];
        $tot['v_prod'] += $vprod;
        $tot['v_bc_icms'] += $vprod;
        $tot['v_icms'] += $vicms;
        $tot['v_pis'] += $vpis;
    }

    $dados = $dadosExtra + [
        'nat_op' => 'VENDA DE MERCADORIA',
        'dest' => [
            'nome' => 'CLIENTE TESTE LTDA', 'cnpj' => '11222333000181', 'ind_ie_dest' => '9', // pii-allowlist (CNPJ fictício)
            'logradouro' => 'AV DO CLIENTE', 'numero' => '200', 'bairro' => 'CENTRO',
            'municipio' => 'SAO PAULO', 'cod_municipio' => '3550308', 'uf' => 'SP', 'cep' => '01310100',
        ],
        'dets' => $dets,
        'total' => $tot + ['v_nf' => $tot['v_prod'], 'v_desc' => 0, 'v_frete' => 0],
        'pag' => [['tpag' => '01', 'vpag' => $tot['v_prod']]],
    ];

    $m = new ReflectionMethod(NfeService::class, 'buildXml');
    $m->setAccessible(true);

    return (string) $m->invoke($svc, $business, $emissao, $dados, $emit);
}

function dev20Tag(string $xml, string $tag): string
{
    return preg_match("#<{$tag}>([^<]*)</{$tag}>#", $xml, $mm) ? $mm[1] : '';
}

function dev20Svc(?NfeService $nfe = null): DevolucaoService
{
    return new DevolucaoService($nfe ?? new NfeService(\Mockery::mock(CertificadoService::class)));
}

/** NfeService que não vai à SEFAZ: guarda o `dadosNfe` e devolve uma emissão não salva. */
function dev20NfeEspiao(ArrayObject $capturado): NfeService
{
    return new class($capturado) extends NfeService {
        public function __construct(private ArrayObject $cap) {}

        public function emitir(int $businessId, array $dadosNfe): NfeEmissao
        {
            $this->cap['biz'] = $businessId;
            $this->cap['dados'] = $dadosNfe;

            return new NfeEmissao(['business_id' => $businessId, 'status' => 'enviando']);
        }
    };
}

function dev20Limpar(): void
{
    $bizs = [test()->seededTenant()->id, test()->seededSupportClientTenant()->id];
    NfeEmissao::withoutGlobalScopes()->withTrashed()->whereIn('business_id', $bizs)
        ->whereIn('numero', DEV20_NUMEROS)->forceDelete();
}

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        dev20Limpar();
    }
    \Mockery::close();
});

// ── R-NFE-026 · devolução copia a origem ────────────────────────────────────

it('R-NFE-026 · devolução copia a origem', function () {
    dev20MySql();
    $biz = test()->seededTenant()->id;

    // Venda autorizada com ICMS 18%: 2 × 50,00 = 100,00 → ICMS 100 × 18% = 18,00 · PIS 100 × 1,65% = 1,65.
    $origem = dev20Xml($biz, [], [['5102', 2.0, 50.00, 100.00, 18.00, 18.00, 1.65]]);
    $chave = preg_match('#Id="NFe(\d{44})"#', $origem, $mm) ? $mm[1] : '';
    expect($chave)->toHaveLength(44);

    // Depois da venda o contador muda a regra para 12%. A venda NOVA usa 12% (controle positivo):
    $motor12 = new class extends MotorTributarioService {
        public function __construct() {}

        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '5102', csosn: null, cst: '00',
                // Fração, como a régua guarda (UpsertRegraTributariaRequest: "0.18 = 18%"); o XML leva 12 (R-NFE-040).
                aliquota_icms: 0.12, aliquota_pis: 0.0165, aliquota_cofins: 0.076, aliquota_ipi: 0.0,
                valor_icms: round($produto->valor * 0.12, 2), valor_pis: 0.0, valor_cofins: 0.0, valor_ipi: 0.0,
                nivel_usado: 2,
            );
        }
    };
    $vendaNova = app(NfeService::class)->montarItensNfe(
        linhas: [['sell_line_id' => 1, 'product_id' => 1, 'cprod' => 'SKU1', 'xprod' => 'PRODUTO 1', 'ncm' => '61091000',
            'unidade' => 'UN', 'quantidade' => 2.0, 'valor_unitario' => 50.00]],
        valorNota: 100.00, frete: 0, ncmDefault: '61091000', motor: $motor12, businessId: $biz, ufOrigem: 'SP', ufDestino: 'SP',
    );
    expect($vendaNova['dets'][0]['icms']['picms'])->toBe(12.0)
        ->and($vendaNova['dets'][0]['icms']['vicms'])->toBe(12.0);   // 100 × 12% = 12,00

    // A devolução da nota de origem continua em 18% sobre a base da origem — o motor nem entra.
    $dev = dev20Svc()->montarDevolucao($origem, [1 => 2.0]);
    $det = $dev['dets'][0];
    expect($dev['chave'])->toBe($chave)
        ->and($det['cfop'])->toBe('1202')                // espelho de 5102
        ->and($det['icms']['picms'])->toBe(18.0)
        ->and($det['icms']['vbc'])->toBe(100.0)
        ->and($det['icms']['vicms'])->toBe(18.0)
        ->and($det['pis']['vpis'])->toBe(1.65)
        ->and($dev['total']['v_nf'])->toBe(100.0)
        ->and($dev['total']['v_icms'])->toBe(18.0);

    // E o XML da devolução sai com finNFe=4, tpNF=0 (entrada), refNFe = chave da origem.
    $xmlDev = dev20Xml($biz, ['nat_op' => 'DEVOLUCAO DE VENDA', 'fin_nfe' => 4, 'tp_nf' => 0, 'ref_nfe' => $chave],
        [['1202', 2.0, 50.00, 100.00, $det['icms']['picms'], $det['icms']['vicms'], $det['pis']['vpis']]]);
    expect(dev20Tag($xmlDev, 'finNFe'))->toBe('4')
        ->and(dev20Tag($xmlDev, 'tpNF'))->toBe('0')
        ->and(dev20Tag($xmlDev, 'refNFe'))->toBe($chave)
        ->and(dev20Tag($xmlDev, 'CFOP'))->toBe('1202')
        ->and(dev20Tag($xmlDev, 'pICMS'))->toBe('18.0000')
        ->and(dev20Tag($xmlDev, 'pICMS'))->toBe(dev20Tag($origem, 'pICMS'));   // a mesma da origem

    // Controle: venda normal segue finNFe=1, tpNF=1 e sem NFref (XML de sempre).
    expect(dev20Tag($origem, 'finNFe'))->toBe('1')
        ->and(dev20Tag($origem, 'tpNF'))->toBe('1')
        ->and(str_contains($origem, '<NFref>'))->toBeFalse();
})->group('nfe', 'R-NFE-026');

it('R-NFE-026 · CFOP espelho e CFOP desconhecido recusa', function () {
    expect(DevolucaoService::cfopEspelho('5102'))->toBe('1202')
        ->and(DevolucaoService::cfopEspelho('6102'))->toBe('2202')
        ->and(DevolucaoService::cfopEspelho('5405'))->toBe('1411')
        ->and(DevolucaoService::cfopEspelho('5101'))->toBe('1201')
        ->and(DevolucaoService::cfopEspelho('6403'))->toBe('2411');
    expect(fn () => DevolucaoService::cfopEspelho('5949'))->toThrow(RuntimeException::class, 'sem espelho');
})->group('nfe', 'R-NFE-026');

it('R-NFE-026 · emitirDevolucao manda finNFe 4, refNFe e a base da origem, sem pagamento', function () {
    dev20MySql();
    Storage::fake();
    $biz = test()->seededTenant()->id;
    $xml = dev20Xml($biz, [], [['5102', 2.0, 50.00, 100.00, 18.00, 18.00, 1.65]]);
    $chave = preg_match('#Id="NFe(\d{44})"#', $xml, $mm) ? $mm[1] : '';
    Storage::put('nfe-brasil/dev20/origem.xml', $xml);
    $origem = NfeEmissao::create([
        'business_id' => $biz, 'modelo' => '55', 'serie' => '1', 'numero' => 992001, 'chave_44' => $chave,
        'status' => 'autorizada', 'xml_path' => 'nfe-brasil/dev20/origem.xml', 'valor_total' => 100,
    ]);

    $cap = new ArrayObject();
    dev20Svc(dev20NfeEspiao($cap))->emitirDevolucao($biz, $origem->id, [1 => 1.0]);

    $d = $cap['dados'];
    expect($cap['biz'])->toBe($biz)
        ->and($d['modelo'])->toBe('55')
        ->and($d['fin_nfe'])->toBe(4)
        ->and($d['tp_nf'])->toBe(0)
        ->and($d['ref_nfe'])->toBe($chave)
        ->and($d['dest']['cnpj'])->toBe('11222333000181') // pii-allowlist (CNPJ fictício) — veio da origem
        ->and($d['pag'])->toBe([['tpag' => '90', 'vpag' => 0]])
        ->and($d['dets'][0]['icms']['picms'])->toBe(18.0)
        ->and($d['dets'][0]['icms']['vicms'])->toBe(9.0)     // 1 de 2: 18,00 / 2
        ->and($d['valor_total'])->toBe(50.0)
        ->and($d['metadata']['devolucao'])->toBe(['origem_id' => $origem->id, 'chave_origem' => $chave, 'itens' => [1 => 1.0]]);
})->group('nfe', 'R-NFE-026');

it('R-NFE-026 · nota de outra empresa ou não autorizada não é devolvida', function () {
    dev20MySql();
    Storage::fake();
    $biz = test()->seededTenant()->id;
    $outro = test()->seededSupportClientTenant()->id;
    $chave = str_repeat('3', 44);
    $deOutro = NfeEmissao::create([
        'business_id' => $outro, 'modelo' => '55', 'serie' => '1', 'numero' => 992002, 'chave_44' => $chave,
        'status' => 'autorizada', 'xml_path' => 'x.xml', 'valor_total' => 1,
    ]);
    $rejeitada = NfeEmissao::create([
        'business_id' => $biz, 'modelo' => '55', 'serie' => '1', 'numero' => 992003, 'chave_44' => $chave,
        'status' => 'rejeitada', 'xml_path' => 'x.xml', 'valor_total' => 1,
    ]);

    $cap = new ArrayObject();
    expect(fn () => dev20Svc(dev20NfeEspiao($cap))->emitirDevolucao($biz, $deOutro->id, [1 => 1.0]))
        ->toThrow(RuntimeException::class, 'não encontrada nesta empresa');
    expect(fn () => dev20Svc(dev20NfeEspiao($cap))->emitirDevolucao($biz, $rejeitada->id, [1 => 1.0]))
        ->toThrow(RuntimeException::class, 'Só se devolve nota autorizada');
    expect(isset($cap['dados']))->toBeFalse();
})->group('nfe', 'R-NFE-026');

// ── R-NFE-027 · parcial proporcional com saldo ──────────────────────────────

it('R-NFE-027 · parcial proporcional com saldo', function () {
    dev20MySql();
    $biz = test()->seededTenant()->id;
    // 10 × 9,99 = 99,90 · ICMS 18% = 17,98 (99,90 × 0,18 = 17,982) · PIS 1,65 informado.
    $origem = dev20Xml($biz, [], [['5102', 10.0, 9.99, 99.90, 18.00, 17.98, 1.65]]);
    $svc = dev20Svc();

    // Devolve 4 de 10: vProd 99,90 × 4/10 = 39,96 · ICMS round(1798 × 0,4) = 719 → 7,19 · PIS round(165 × 0,4) = 66 → 0,66.
    $d4 = $svc->montarDevolucao($origem, [1 => 4.0]);
    expect($d4['dets'][0]['vprod'])->toBe(39.96)
        ->and($d4['dets'][0]['icms']['vicms'])->toBe(7.19)
        ->and($d4['dets'][0]['icms']['vbc'])->toBe(39.96)
        ->and($d4['dets'][0]['pis']['vpis'])->toBe(0.66)
        ->and($d4['dets'][0]['icms']['picms'])->toBe(18.0)
        ->and($d4['total']['v_nf'])->toBe(39.96);

    // Mais 7 com 4 já devolvidos: passa do saldo (6).
    expect(fn () => $svc->montarDevolucao($origem, [1 => 7.0], [1 => 4.0]))
        ->toThrow(RuntimeException::class, 'saldo 6');

    // Controle positivo: as 6 restantes fecham a linha exata — 99,90 − 39,96 = 59,94 · 17,98 − 7,19 = 10,79 · 1,65 − 0,66 = 0,99.
    $d6 = $svc->montarDevolucao($origem, [1 => 6.0], [1 => 4.0]);
    expect($d6['dets'][0]['vprod'])->toBe(59.94)
        ->and($d6['dets'][0]['icms']['vicms'])->toBe(10.79)
        ->and($d6['dets'][0]['pis']['vpis'])->toBe(0.99)
        ->and(round($d4['total']['v_icms'] + $d6['total']['v_icms'], 2))->toBe(17.98);

    // Item que não existe na origem é recusado.
    expect(fn () => $svc->montarDevolucao($origem, [2 => 1.0]))->toThrow(RuntimeException::class, 'não existem');
})->group('nfe', 'R-NFE-027');

it('R-NFE-027 · saldo soma só devoluções vivas da mesma empresa e da mesma origem', function () {
    dev20MySql();
    $biz = test()->seededTenant()->id;
    $outro = test()->seededSupportClientTenant()->id;
    $base = ['modelo' => '55', 'serie' => '1', 'chave_44' => str_repeat('3', 44), 'valor_total' => 1];
    $origem = NfeEmissao::create($base + ['business_id' => $biz, 'numero' => 992001, 'status' => 'autorizada']);
    $dev = fn (int $b, int $n, string $st, array $itens, int $o) => NfeEmissao::create($base + [
        'business_id' => $b, 'numero' => $n, 'status' => $st,
        'metadata' => ['devolucao' => ['origem_id' => $o, 'chave_origem' => $base['chave_44'], 'itens' => $itens]],
    ]);
    $dev($biz, 992002, 'autorizada', [1 => 4.0], $origem->id);   // conta
    $dev($biz, 992003, 'enviando', [1 => 1.0, 2 => 2.0], $origem->id); // conta (número reservado)
    $dev($biz, 992004, 'rejeitada', [1 => 5.0], $origem->id);    // não conta: número não declarado
    $dev($biz, 992005, 'autorizada', [1 => 3.0], 999999999);    // outra origem
    $dev($outro, 992006, 'autorizada', [1 => 9.0], $origem->id); // outra empresa

    expect(dev20Svc()->saldoDevolvido($biz, $origem->id))->toBe([1 => 5.0, 2 => 2.0]);
})->group('nfe', 'R-NFE-027');
