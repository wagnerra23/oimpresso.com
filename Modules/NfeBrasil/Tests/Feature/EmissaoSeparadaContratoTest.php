<?php
// @covers-us US-NFE-069

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Http\Controllers\NfseController;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Http\Controllers\NfeEmissaoController;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;
use Tests\Support\EstoqueFixture;

/**
 * US-NFE-069 · UC-FLX-27 — "Uma OS, duas notas": a venda mista sai em NF-e (parte mercadoria) e
 * NFS-e (parte serviço), cada uma com o total da sua parte. Casos UC-FNFSE-10..12 em
 * resources/js/Pages/Fiscal/Nfse.casos.md.
 *
 * REGRA MESTRE de valor — a venda de prova é a do PR-1 (3 × 33,33 + instalação 150,00, desconto
 * 17,50, frete 20,00, total 252,49), com a regra de rateio decidida por [W] em 2026-10-21:
 *   caminho 1, à mão: serviço 150,00 − 17,50 × 150,00 / 249,99 (= 10,50) = 139,50;
 *                     mercadoria 99,99 − 7,00 + 20,00 = 112,99.
 *   caminho 2, no teste: NF-e (vNF) + NFS-e (valor_servicos) = final_total da venda.
 *
 * Nada vai à SEFAZ nem à Sefin: o NfeService espião guarda o `dadosNfe` e o serviço de NFS-e
 * espião guarda o payload. MySQL · tenant fictício 98 contra 99 (ADR 0358) — fora do MySQL pula;
 * leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

function sep2Motor(): MotorTributarioService
{
    return new class extends MotorTributarioService {
        public function __construct() {}

        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '5102', csosn: '102', cst: null,
                aliquota_icms: 0.0, aliquota_pis: 0.0, aliquota_cofins: 0.0, aliquota_ipi: 0.0,
                valor_icms: 0.0, valor_pis: 0.0, valor_cofins: 0.0, valor_ipi: 0.0,
                nivel_usado: 4, regra_id: null,
            );
        }
    };
}

/** NfeService que não vai à SEFAZ: guarda o `dadosNfe` e devolve uma emissão não salva. */
function sep2NfeEspiao(ArrayObject $cap): NfeService
{
    return new class($cap) extends NfeService {
        public function __construct(private ArrayObject $cap)
        {
            parent::__construct(\Mockery::mock(CertificadoService::class), null, sep2Motor());
        }

        public function emitir(int $businessId, array $dadosNfe): NfeEmissao
        {
            $this->cap['dados'] = $dadosNfe;

            return new NfeEmissao(['business_id' => $businessId, 'status' => 'enviando']);
        }
    };
}

/** Serviço de NFS-e que monta o payload de verdade, mas não despacha o job. */
function sep2NfseEspiao(ArrayObject $cap): NfseEmissaoService
{
    return new class($cap) extends NfseEmissaoService {
        public function __construct(private ArrayObject $cap)
        {
            parent::__construct(\Mockery::mock(NfseProviderInterface::class));
        }

        public function despacharEmissaoAsync(NfseEmissaoPayload $payload): void
        {
            $this->cap['payload'] = $payload;
        }
    };
}

function sep2Request(int $biz, array $dados = []): Request
{
    $r = Request::create('/', 'POST', $dados);
    $r->setLaravelSession(app('session.store'));
    // Sem isto `$request->user()` é null e todo caso de permissão daria 403 pelo motivo errado.
    $r->setUserResolver(fn () => auth()->user());
    $r->session()->put(['business.id' => $biz, 'user.business_id' => $biz]);
    session(['business.id' => $biz, 'user.business_id' => $biz]);

    return $r;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_naturezas_produto') || ! Schema::hasTable('nfse_provider_configs')) {
        $this->markTestSkipped('MySQL com nfe_naturezas_produto e nfse_provider_configs.');
    }
    try {
        $this->biz = $this->seededTenant()->id;
        $this->outra = $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: ' . $e->getMessage());
    }

    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $this->biz],
        ['tributacao_default' => json_encode(['ncm_default' => '49111090', 'cfop' => '5102']), 'updated_at' => now()],
    );
    if (! DB::table('nfse_provider_configs')->where('business_id', $this->biz)->whereNull('deleted_at')->exists()) {
        DB::table('nfse_provider_configs')->insert([
            'business_id' => $this->biz, 'municipio_codigo_ibge' => '4218707', 'lc116_codigo_default' => '1.05',
            'aliquota_iss' => 0.05, 'ambiente' => 'homologacao', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param list<array{0:?string,1:float,2:float,3:?string}> $linhas [natureza, qtd, preço, código] */
    $this->venda = function (int $biz, array $linhas, float $final, float $frete): int {
        $tx = (int) DB::table('transactions')->insertGetId([
            'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz), 'type' => 'sell',
            'status' => 'final', 'payment_status' => 'due', 'transaction_date' => now(), 'invoice_no' => 'SEP2-' . uniqid(),
            'total_before_tax' => $final, 'final_total' => $final, 'shipping_charges' => $frete,
            'created_by' => EstoqueFixture::userId($biz), 'essentials_duration' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
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
        }

        return $tx;
    };
    $this->mista = fn (int $biz) => ($this->venda)($biz, [['mercadoria', 3, 33.33, null], ['servico', 1, 150.00, '24.01']], 252.49, 20.00);
});

afterEach(fn () => \Mockery::close());

it('UC-FNFSE-10 · a NF-e da venda separada leva só a parte mercadoria, com o total e o frete dela', function () {
    $tx = ($this->mista)($this->biz);
    $cap = new ArrayObject();

    sep2NfeEspiao($cap)->emitirParaTransaction(\App\Transaction::findOrFail($tx), '55', true);
    $d = $cap['dados'];

    expect($d['valor_total'])->toBe(112.99)
        ->and($d['total']['v_nf'])->toBe(112.99)
        ->and($d['pag'][0]['vpag'])->toBe(112.99)
        ->and(count($d['dets']))->toBe(1)
        ->and($d['dets'][0]['qcom'])->toBe(3.0)
        ->and($d['total']['v_prod'])->toBe(99.99)
        ->and($d['metadata']['separacao']['servico_total'])->toBe(139.50);
    // R-NFE-025 ([W] 2026-10-21, #9738): o frete da parte sai inteiro em vFrete e o desconto dela em vDesc.
    // Antes saía vFrete 13,00 e vDesc 0 (o desconto consumia o frete).
    expect($d['total']['v_frete'])->toBe(20.0)
        ->and($d['total']['v_desc'])->toBe(7.0);
    // os componentes da NF-e fecham o vNF (como a SEFAZ valida)
    expect(round($d['total']['v_prod'] - $d['total']['v_desc'] + $d['total']['v_frete'] + $d['total']['v_outro'], 2))->toBe(112.99);
    // segundo caminho: NF-e + NFS-e = final_total da venda, centavo a centavo
    expect((int) round($d['total']['v_nf'] * 100) + (int) round($d['metadata']['separacao']['servico_total'] * 100))->toBe(25249);
});

it('UC-FNFSE-10 · sem a separação a NF-e continua recusando o item de serviço (controle)', function () {
    $tx = ($this->mista)($this->biz);

    expect(fn () => sep2NfeEspiao(new ArrayObject())->emitirParaTransaction(\App\Transaction::findOrFail($tx), '55'))
        ->toThrow(RuntimeException::class, 'emita NFS-e');
});

it('UC-FNFSE-11 · a NFS-e da venda separada leva o total da parte serviço e o código do contador, ignorando o valor do corpo', function () {
    $tx = ($this->mista)($this->biz);
    $cap = new ArrayObject();
    $this->actingAs($this->usuarioComPermissoes(['nfse.emit']));

    $r = (new NfseController(sep2NfseEspiao($cap)))
        ->emitirParaTransaction(sep2Request($this->biz, ['separada' => '1', 'valor_servicos' => '252.49']), $tx);

    expect($r->getStatusCode())->toBe(202)
        ->and($cap['payload']->valorServicos)->toBe(139.50)
        ->and($cap['payload']->lc116Codigo)->toBe('24.01')
        ->and($cap['payload']->transactionId)->toBe($tx);
});

it('UC-FNFSE-11 · sem a separação a NFS-e segue com o final_total da venda (controle)', function () {
    $tx = ($this->mista)($this->biz);
    $cap = new ArrayObject();
    $this->actingAs($this->usuarioComPermissoes(['nfse.emit']));

    (new NfseController(sep2NfseEspiao($cap)))->emitirParaTransaction(sep2Request($this->biz), $tx);

    expect($cap['payload']->valorServicos)->toBe(252.49);
});

it('UC-FNFSE-12 · natureza pendente bloqueia as duas notas da venda separada', function () {
    $tx = ($this->venda)($this->biz, [['pendente', 1, 40.00, null], ['servico', 1, 60.00, '24.01']], 100.00, 0.0);
    $cap = new ArrayObject();
    $this->actingAs($this->usuarioComPermissoes(['nfse.emit']));

    $r = (new NfseController(sep2NfseEspiao($cap)))->emitirParaTransaction(sep2Request($this->biz, ['separada' => '1']), $tx);
    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData(true)['error'])->toBe('natureza_pendente')
        ->and($cap->offsetExists('payload'))->toBeFalse();

    expect(fn () => sep2NfeEspiao(new ArrayObject())->emitirParaTransaction(\App\Transaction::findOrFail($tx), '55', true))
        ->toThrow(RuntimeException::class, 'natureza fiscal a decidir');
});

it('UC-FNFSE-12 · a prévia das duas partes exige permissão de NFS-e e não lê venda de outra empresa', function () {
    $tx = ($this->mista)($this->biz);
    $ctl = app(NfeEmissaoController::class);

    $this->actingAs($this->usuarioComPermissoes([]));
    expect($ctl->separacao(sep2Request($this->biz), $tx)->getStatusCode())->toBe(403);

    $this->actingAs($this->usuarioComPermissoes(['nfse.emit']));
    $ok = $ctl->separacao(sep2Request($this->biz), $tx)->getData(true);
    expect($ok['separavel'])->toBeTrue()
        ->and($ok['mercadoria']['total'])->toBe(112.99)
        ->and($ok['servico']['total'])->toBe(139.5)
        ->and($ok['servico']['desconto'])->toBe(10.5);

    // Tier 0: venda do 99 pedida pelo 98 → 404; a dona enxerga (a recusa é do tenant, não do dado).
    $alheia = ($this->mista)($this->outra);
    expect($ctl->separacao(sep2Request($this->biz), $alheia)->getStatusCode())->toBe(404);
    $this->actingAs($this->usuarioComPermissoes(['nfse.emit'], \App\Business::findOrFail($this->outra)));
    expect($ctl->separacao(sep2Request($this->outra), $alheia)->getData(true)['servico']['total'])->toBe(139.5);
});
