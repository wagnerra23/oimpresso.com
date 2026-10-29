<?php
// @covers-us US-SELL-078

declare(strict_types=1);

use App\Services\Sells\IcmsStDaVenda;
use App\Services\Sells\IcmsStDivergenteException;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;
use Tests\Support\EstoqueFixture;

/**
 * UC-VST-01..05 · US-SELL-078 — o ICMS-ST entra no fechamento da venda ([W] 2026-10-26) pela
 * mesma conta da NF-e, e venda = vNF = título.
 *
 * Âncora: resources/js/Pages/Sells/Create.casos.md (UC-VST-*), derivado da US-SELL-078.
 *
 * Números feitos à mão (os mesmos do R-NFE-043 do Fiscal): item de 1.000,00 de SC para
 * contribuinte no RJ, ICMS próprio 12%, MVA 40%, interna do RJ 18%.
 *   base ST = 1.000,00 × 1,40 = 1.400,00 · ST = 1.400,00 × 0,18 − 120,00 = 132,00
 *   total da venda = 1.000,00 + 132,00 = 1.132,00 = vNF = valor do título
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transactions', 'icms_st_valor')
        || ! class_exists(NfeService::class)) {
        $this->markTestSkipped('Schema/NfeBrasil ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->user = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, 'ST');
    DB::table('business')->where('id', $this->biz)->update(['ncm_padrao' => '49111090']);

    // Motor do R-NFE-043 (o mesmo fixture do Fiscal): ST de 132,00 sobre item de 1.000,00.
    $this->app->instance(MotorTributarioService::class, new class extends MotorTributarioService {
        public function __construct() {}

        public function calcularComDestino(ProdutoFiscalContext $p, int $businessId, string $ufOrigem, string $ufDestino,
            ?float $aliquotaInternaDestino = null, ?bool $destinatarioContribuinte = null, ?int $operacaoId = null,
            ?string $dataEmissao = null): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '6102', csosn: null, cst: '10',
                aliquota_icms: 0.12, aliquota_pis: 0.0, aliquota_cofins: 0.0, aliquota_ipi: 0.0,
                valor_icms: 120.0, valor_pis: 0.0, valor_cofins: 0.0, valor_ipi: 0.0, nivel_usado: 2,
                mva: 0.40, base_st: 1400.0, valor_st: 132.0,
            );
        }
    });
    // Emitente em SC e interna do RJ 18% (a tabela por UF é cadastro do contador).
    $this->partialMock(NfeService::class, function ($m) {
        $m->shouldReceive('resolverEmitente')->andReturn(['uf' => 'SC']);
        $m->shouldReceive('internaDoDestino')->andReturn(0.18);
    });

    // Contribuinte = tem IE (indicador 1); consumidor final = sem IE (indicador 9).
    $this->cliente = function (string $uf, bool $contribuinte = true) {
        return (int) DB::table('contacts')->insertGetId([
            'business_id' => $this->biz, 'type' => 'customer', 'name' => 'Cliente ST ' . $uf,
            'contact_id' => 'ST-' . uniqid(), 'tax_number' => substr(str_pad((string) random_int(1, PHP_INT_MAX), 14, '7'), 0, 14),
            'state' => $uf, 'inscricao_estadual' => $contribuinte ? '123456789' : null, 'indicador_ie' => $contribuinte ? 1 : 9,
            'created_by' => $this->user, 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    DB::table('products')->where('id', $this->produto->productId)->update(['ncm' => '61091000']);

    // Venda gravada de 1 item a 1.000,00, com o total que a tela mandou.
    $this->venda = function (int $cliente, float $total) {
        $v = EstoqueFixture::saleWithLine($this->produto, 0, $this->loc, 1.0, 1000.0);
        DB::table('transactions')->where('id', $v['transaction_id'])
            ->update(['contact_id' => $cliente, 'final_total' => $total, 'payment_status' => 'due']);

        return Transaction::findOrFail($v['transaction_id']);
    };
    $this->svc = app(IcmsStDaVenda::class);
});

it('UC-VST-01 o caixa recebe o ST do carrinho pela conta da NF-e', function () {
    $user = $this->usuarioComPermissoes(['sell.create'], \App\Business::findOrFail($this->biz));

    $this->actingAs($user)
        ->withSession(['user.business_id' => $this->biz, 'business.id' => $this->biz])
        ->postJson('/sells/icms-st', [
            'location_id' => $this->loc, 'contact_id' => ($this->cliente)('RJ'),
            'itens' => [['variation_id' => $this->produto->variations[0]['variation_id'], 'quantidade' => 1, 'valor_unitario' => 1000]],
            'total_sem_st' => 1000, 'frete' => 0,
        ])
        ->assertOk()->assertJsonPath('icms_st', '132.00');
});

it('UC-VST-02 ao gravar, a venda guarda o ST e venda = vNF = título', function () {
    $venda = ($this->venda)(($this->cliente)('RJ'), 1132.0);

    $this->svc->conferirEGravar($venda, '132.00');

    expect((string) DB::table('transactions')->where('id', $venda->id)->value('icms_st_valor'))->toBe('132.0000');

    // 2º caminho: a emissão parte de baseDaNota (= final_total − ST, a mesma chamada do
    // emitirParaTransaction) e o montarItensNfe soma o vST de volta.
    $nfe = app(NfeService::class);
    $base = $nfe->baseDaNota(Transaction::findOrFail($venda->id));
    expect($base)->toBe(1000.0);
    $notas = $nfe->montarItensNfe(
        linhas: $nfe->linhasDaVenda($this->biz, $venda->id), valorNota: $base, frete: 0,
        ncmDefault: '49111090', motor: app(MotorTributarioService::class), businessId: $this->biz,
        ufOrigem: 'SC', ufDestino: 'RJ', internaDestino: 0.18, destinatarioContribuinte: true,
    );
    expect($notas['total']['v_nf'])->toBe(1132.0);

    // Título do Financeiro nasce do final_total (que já traz o ST).
    if (class_exists(\Modules\Financeiro\Services\TituloAutoService::class) && Schema::hasTable('fin_titulos')) {
        $titulo = app(\Modules\Financeiro\Services\TituloAutoService::class)->sincronizarDeTransacao(Transaction::findOrFail($venda->id));
        expect($titulo)->not->toBeNull();
        expect((float) $titulo->valor_total)->toBe(1132.0);
    }
});

it('UC-VST-03 total enviado sem o ST é recusado com o valor certo', function () {
    $venda = ($this->venda)(($this->cliente)('RJ'), 1000.0);

    expect(fn () => $this->svc->conferirEGravar($venda, '0'))
        ->toThrow(IcmsStDivergenteException::class, 'O ICMS-ST desta venda é R$ 132,00');
});

it('UC-VST-04 consumidor final da mesma UF não tem ST', function () {
    $venda = ($this->venda)(($this->cliente)('SC', false), 1000.0);

    $this->svc->conferirEGravar($venda, '0');

    expect((string) DB::table('transactions')->where('id', $venda->id)->value('icms_st_valor'))->toBe('0.0000');
});

it('UC-VST-06 contribuinte da mesma UF paga o ST pela mesma conta da NF-e', function () {
    // P-153 · Sabor Brasil: tempero vendido a supermercado da mesma UF, com ST. Mesma conta do
    // UC-VST-01 (o motor do fixture devolve 132,00); antes deste caso a mesma UF dava sempre 0.
    $venda = ($this->venda)(($this->cliente)('SC'), 1132.0);

    $this->svc->conferirEGravar($venda, '132.00');

    expect((string) DB::table('transactions')->where('id', $venda->id)->value('icms_st_valor'))->toBe('132.0000');
});

it('UC-VST-05 venda sem cliente identificado não tem ST', function () {
    $v = EstoqueFixture::saleWithLine($this->produto, 0, $this->loc, 1.0, 1000.0);
    $venda = Transaction::findOrFail($v['transaction_id']);

    expect($this->svc->daVenda($venda))->toBe(0.0);
});
