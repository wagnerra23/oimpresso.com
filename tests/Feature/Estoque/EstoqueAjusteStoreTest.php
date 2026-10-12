<?php

declare(strict_types=1);

// @covers-uc UC-AJCRT-03 UC-AJCRT-04 (resources/js/Pages/StockAdjustment/Create.casos.md)

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * POST /stock-adjustments (`store`) — o salvar do ajuste de estoque pelos DOIS formatos de envio.
 *
 * CONTRATO: DOC-RAIZ-ESTOQUE §3 (`stock_adjustment`: SAI do local pela quantidade ajustada).
 *
 * POR QUE EXISTE: até 2026-10-11 nenhum teste postava no `store()` do ajuste, e a tela React
 * (padrão do menu desde o #9096) mandava a data em ISO — o `uf_date()` estourava, o catch fazia
 * rollBack e nada era salvo. Aqui o mesmo cenário é salvo duas vezes — no formato da Blade (data
 * e números no formato da empresa) e no da tela React (data ISO, vírgula decimal sem milhar) — e
 * o saldo final tem de ser o MESMO. É a dupla prova da REGRA MESTRE (memory/proibicoes.md).
 *
 * ⚠️ Eixo ESTOQUE. Vermelho aqui é achado, não teste a "consertar": a correção volta a [W].
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    $this->userId = EstoqueFixture::userId($this->biz);
    $this->usuario = User::find($this->userId);
    Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
    $this->usuario->givePermissionTo('purchase.create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Sessão como o SetSessionData monta em produção: `business` é o MODEL completo
    // (`mapPurchaseSell` lê chaves dele direto da sessão).
    $negocio = Business::find($this->biz);
    $this->sessao = [
        'user.id' => $this->userId,
        'user.business_id' => $this->biz,
        'business' => $negocio,
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];
    // Formato de data da EMPRESA (o que a Blade manda) — derivado do business, nunca chutado.
    $this->formatoEmpresa = $negocio->date_format . ((int) $negocio->time_format === 12 ? ' h:i A' : ' H:i');

    /** Produto com 10 no local, com lastro de compra (sem ele `mapPurchaseSell` aborta e faz rollBack). */
    $this->produtoComSaldo = function (int $local) {
        $produto = EstoqueFixture::singleProduct($this->biz);
        EstoqueFixture::setStock($produto, 0, $local, 10.0);
        $compraId = (int) DB::table('transactions')->insertGetId([
            'business_id' => $this->biz, 'type' => 'purchase', 'status' => 'received',
            'location_id' => $local, 'payment_status' => 'paid', 'transaction_date' => now()->subDay(),
            'total_before_tax' => 0, 'final_total' => 0, 'created_by' => $this->userId,
            'essentials_duration' => 0, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $compraId, 'product_id' => $produto->productId,
            'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => 10,
            'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
            'purchase_price' => 8, 'purchase_price_inc_tax' => 8, 'item_tax' => 0,
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);

        return $produto;
    };

    /** Item como a linha da tela manda. */
    $this->item = fn ($produto, string $quantidade, string $preco): array => [
        'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'],
        'enable_stock' => 1,
        'quantity' => $quantidade,
        'unit_price' => $preco,
    ];

    $this->salvar = fn (array $payload) => $this->actingAs($this->usuario)
        ->withSession($this->sessao)
        ->post('/stock-adjustments', $payload);

    /** O `store()` responde com redirect + `status` na sessão; sem success=1 o resto mediria um rollback. */
    $this->exigirSucesso = function ($resposta): void {
        $resposta->assertRedirect();
        $status = session('status');
        expect($status['success'] ?? null)->toBe(
            1,
            'o store() precisa COMPLETAR, senao o teste mede um rollback. msg: '
                . json_encode($status['msg'] ?? null, JSON_UNESCAPED_UNICODE)
        );
    };

    $this->ultimoAjuste = fn (int $local) => DB::table('transactions')->where('business_id', $this->biz)
        ->where('type', 'stock_adjustment')->where('location_id', $local)->orderByDesc('id')->first();
});

it('UC-AJCRT-03 · ajuste salvo no formato da tela React baixa 3 do local (10 → 7)', function () {
    $local = EstoqueFixture::locationId($this->biz, '-AJ-' . uniqid());
    $produto = ($this->produtoComSaldo)($local);

    $resposta = ($this->salvar)([
        'location_id' => $local, 'ref_no' => '',
        'transaction_date' => '2026-10-11 09:30',   // ISO — o que o datetime-local produz
        'adjustment_type' => 'normal', 'additional_notes' => '',
        'total_amount_recovered' => '0', 'final_total' => 37.5,
        'products' => [($this->item)($produto, '3', '12,5')],   // vírgula decimal, sem milhar
    ]);
    ($this->exigirSucesso)($resposta);

    expect(EstoqueFixture::currentStock($produto, 0, $local))->toBe(7.0, 'local: 10 - 3');

    $ajuste = ($this->ultimoAjuste)($local);
    expect((string) $ajuste->transaction_date)->toBe('2026-10-11 09:30:00');
    $linha = DB::table('stock_adjustment_lines')->where('transaction_id', $ajuste->id)->first();
    expect((float) $linha->quantity)->toBe(3.0);
    expect((float) $linha->unit_price)->toBe(12.5);
    expect((int) $linha->product_id)->toBe($produto->productId);
});

it('UC-AJCRT-03 · o MESMO cenário salvo no formato da Blade dá o mesmo saldo (segundo caminho)', function () {
    $local = EstoqueFixture::locationId($this->biz, '-AJ-' . uniqid());
    $produto = ($this->produtoComSaldo)($local);

    $resposta = ($this->salvar)([
        'location_id' => $local, 'ref_no' => '',
        'transaction_date' => now()->format($this->formatoEmpresa),   // formato da empresa
        'adjustment_type' => 'normal', 'additional_notes' => '',
        'total_amount_recovered' => '0,00', 'final_total' => 37.5,
        'products' => [($this->item)($produto, '3,00', '12,50')],
    ]);
    ($this->exigirSucesso)($resposta);

    expect(EstoqueFixture::currentStock($produto, 0, $local))->toBe(7.0, 'local: 10 - 3');

    $linha = DB::table('stock_adjustment_lines')->where('transaction_id', ($this->ultimoAjuste)($local)->id)->first();
    expect((float) $linha->quantity)->toBe(3.0);
    expect((float) $linha->unit_price)->toBe(12.5);
});

it('UC-AJCRT-03 · preço com 3 casas, preço de milhar e valor recuperado chegam sem inflar', function () {
    $local = EstoqueFixture::locationId($this->biz, '-AJ-' . uniqid());
    $produto = ($this->produtoComSaldo)($local);
    $outro = ($this->produtoComSaldo)($local);

    // `paraNumUf` (o envio da tela): 12.125 -> "12,125", 1500 -> "1500", 1234.5 -> "1234,5".
    // Com ponto ("12.125") o num_uf leria milhar e gravaria 12125 — o incidente de 2026-06-05.
    $resposta = ($this->salvar)([
        'location_id' => $local, 'ref_no' => '',
        'transaction_date' => '2026-10-11T09:30',
        'adjustment_type' => 'abnormal', 'additional_notes' => '',
        'total_amount_recovered' => '1234,5', 'final_total' => 1512.125,
        'products' => [
            ($this->item)($produto, '1', '12,125'),
            ($this->item)($outro, '1', '1500'),
        ],
    ]);
    ($this->exigirSucesso)($resposta);

    $ajuste = ($this->ultimoAjuste)($local);
    $precos = DB::table('stock_adjustment_lines')->where('transaction_id', $ajuste->id)->orderBy('id')
        ->pluck('unit_price')->map(fn ($p) => (float) $p)->all();
    expect($precos)->toBe([12.125, 1500.0]);
    expect((float) $ajuste->total_amount_recovered)->toBe(1234.5);
    expect($ajuste->adjustment_type)->toBe('abnormal');
});

it('UC-AJCRT-04 · o ajuste baixa só o local escolhido — outro local do mesmo produto não muda', function () {
    $local = EstoqueFixture::locationId($this->biz, '-AJ-' . uniqid());
    $outroLocal = EstoqueFixture::locationId($this->biz, '-AJ-OUTRO-' . uniqid());
    $produto = ($this->produtoComSaldo)($local);
    EstoqueFixture::setStock($produto, 0, $outroLocal, 6.0);

    $resposta = ($this->salvar)([
        'location_id' => $local, 'ref_no' => '',
        'transaction_date' => '2026-10-11 09:30',
        'adjustment_type' => 'normal', 'additional_notes' => '',
        'total_amount_recovered' => '0', 'final_total' => 25,
        'products' => [($this->item)($produto, '2', '12,5')],
    ]);
    ($this->exigirSucesso)($resposta);

    expect(EstoqueFixture::currentStock($produto, 0, $local))->toBe(8.0, 'local ajustado: 10 - 2');
    expect(EstoqueFixture::currentStock($produto, 0, $outroLocal))->toBe(6.0, 'outro local: intacto');
});
