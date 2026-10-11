<?php

declare(strict_types=1);

// @covers-uc UC-TRCRT-03 UC-TRCRT-04 UC-TRCRT-05 (resources/js/Pages/StockTransfer/Create.casos.md)

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * POST /stock-transfers (`store`) e GET /stock-transfers/produto-linha — o salvar da transferência
 * pelos DOIS formatos de envio e os dados da linha que a tela React usa.
 *
 * CONTRATO: DOC-RAIZ-ESTOQUE §3 (transferência SAI da origem e ENTRA no destino pela mesma
 * quantidade) + charter da tela (`completed` move o estoque na hora; `pending` não move).
 *
 * POR QUE EXISTE: até 2026-10-11 nenhum teste postava no `store()` da transferência, e a tela
 * React (padrão do menu desde o #9096) mandava a data em ISO e os itens sem produto. Aqui o mesmo
 * cenário é salvo duas vezes — no formato da Blade (data e números no formato da empresa) e no
 * formato da tela React (data ISO, vírgula decimal sem milhar) — e o saldo final tem de ser o
 * MESMO. É a dupla prova da REGRA MESTRE (memory/proibicoes.md): dois caminhos, números concretos.
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
    // (`mapPurchaseSell` lê chaves dele direto da sessão — ver EstoqueTransferenciaIdempotenciaTest).
    $negocio = Business::find($this->biz);
    $this->sessao = [
        'user.id' => $this->userId,
        'user.business_id' => $this->biz,
        'business' => $negocio,
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];
    // Formato de data da EMPRESA (o que a Blade manda) — derivado do business, nunca chutado.
    $this->formatoEmpresa = $negocio->date_format . ((int) $negocio->time_format === 12 ? ' h:i A' : ' H:i');

    // Lastro de compra na origem: sem ele `mapPurchaseSell` aborta e o catch faz rollBack.
    $this->darLastro = function ($produto, int $location, float $qtd): void {
        $compraId = (int) DB::table('transactions')->insertGetId([
            'business_id' => $this->biz, 'type' => 'purchase', 'status' => 'received',
            'location_id' => $location, 'payment_status' => 'paid', 'transaction_date' => now()->subDay(),
            'total_before_tax' => 0, 'final_total' => 0, 'created_by' => $this->userId,
            'essentials_duration' => 0, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $compraId, 'product_id' => $produto->productId,
            'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => $qtd,
            'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
            'purchase_price' => 8, 'purchase_price_inc_tax' => 8, 'item_tax' => 0,
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
    };

    /** Cenário: produto com 10 na origem e 2 no destino. */
    $this->cenario = function (): array {
        $origem = EstoqueFixture::locationId($this->biz, '-TS-ORIG-' . uniqid());
        $destino = EstoqueFixture::locationId($this->biz, '-TS-DEST-' . uniqid());
        $produto = EstoqueFixture::singleProduct($this->biz);
        EstoqueFixture::setStock($produto, 0, $origem, 10.0);
        EstoqueFixture::setStock($produto, 0, $destino, 2.0);
        ($this->darLastro)($produto, $origem, 10.0);

        return [$origem, $destino, $produto];
    };

    /** Item como a linha da tela manda: produto, variação, unidade, quantidade e preço. */
    $this->item = fn ($produto, string $quantidade, string $preco): array => [
        'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'],
        'enable_stock' => 1,
        'quantity' => $quantidade,
        'base_unit_multiplier' => 1,
        'product_unit_id' => $produto->unitId,
        'unit_price' => $preco,
    ];

    $this->salvar = fn (array $payload) => $this->actingAs($this->usuario)
        ->withSession($this->sessao)
        ->post('/stock-transfers', $payload);

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
});

it('UC-TRCRT-03 · concluída salva no formato da tela React tira 3 da origem e põe 3 no destino', function () {
    [$origem, $destino, $produto] = ($this->cenario)();

    $resposta = ($this->salvar)([
        'transaction_date' => '2026-10-11 09:30',   // ISO — o que o datetime-local produz
        'ref_no' => '', 'status' => 'completed',
        'location_id' => $origem, 'transfer_location_id' => $destino,
        'additional_notes' => '', 'shipping_charges' => '0', 'final_total' => 37.5,
        'products' => [($this->item)($produto, '3', '12,5')],   // vírgula decimal, sem milhar
    ]);
    ($this->exigirSucesso)($resposta);

    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(7.0, 'origem: 10 - 3');
    expect(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(5.0, 'destino: 2 + 3');
    expect(EstoqueFixture::totalStock($produto))->toBe(12.0, 'transferência não cria nem destrói saldo');

    $venda = DB::table('transactions')->where('business_id', $this->biz)->where('type', 'sell_transfer')
        ->where('location_id', $origem)->orderByDesc('id')->first();
    expect($venda->status)->toBe('final');
    expect((string) $venda->transaction_date)->toBe('2026-10-11 09:30:00');
    $linha = DB::table('transaction_sell_lines')->where('transaction_id', $venda->id)->first();
    expect((float) $linha->quantity)->toBe(3.0);
    expect((float) $linha->unit_price)->toBe(12.5);
    expect((int) $linha->product_id)->toBe($produto->productId);
});

it('UC-TRCRT-03 · o MESMO cenário salvo no formato da Blade dá o mesmo saldo (segundo caminho)', function () {
    [$origem, $destino, $produto] = ($this->cenario)();

    $resposta = ($this->salvar)([
        'transaction_date' => now()->format($this->formatoEmpresa),   // formato da empresa
        'ref_no' => '', 'status' => 'completed',
        'location_id' => $origem, 'transfer_location_id' => $destino,
        'additional_notes' => '', 'shipping_charges' => '0,00', 'final_total' => 37.5,
        'products' => [($this->item)($produto, '3,00', '12,50')],
    ]);
    ($this->exigirSucesso)($resposta);

    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(7.0, 'origem: 10 - 3');
    expect(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(5.0, 'destino: 2 + 3');

    $venda = DB::table('transactions')->where('business_id', $this->biz)->where('type', 'sell_transfer')
        ->where('location_id', $origem)->orderByDesc('id')->first();
    $linha = DB::table('transaction_sell_lines')->where('transaction_id', $venda->id)->first();
    expect((float) $linha->quantity)->toBe(3.0);
    expect((float) $linha->unit_price)->toBe(12.5);
});

it('UC-TRCRT-03 · preço com 3 casas e preço de milhar chegam sem inflar (vírgula decimal, sem ponto)', function () {
    [$origem, $destino, $produto] = ($this->cenario)();

    // `paraNumUf` (o envio da tela): 12.125 -> "12,125" e 1500 -> "1500". Com ponto ("12.125") o
    // num_uf leria milhar e gravaria 12125 — o incidente de 2026-06-05.
    $resposta = ($this->salvar)([
        'transaction_date' => '2026-10-11T09:30', 'ref_no' => '', 'status' => 'pending',
        'location_id' => $origem, 'transfer_location_id' => $destino,
        'additional_notes' => '', 'shipping_charges' => '0', 'final_total' => 1512.125,
        'products' => [
            ($this->item)($produto, '1', '12,125'),
            ($this->item)($produto, '1', '1500'),
        ],
    ]);
    ($this->exigirSucesso)($resposta);

    $venda = DB::table('transactions')->where('business_id', $this->biz)->where('type', 'sell_transfer')
        ->where('location_id', $origem)->orderByDesc('id')->first();
    $precos = DB::table('transaction_sell_lines')->where('transaction_id', $venda->id)->orderBy('id')
        ->pluck('unit_price')->map(fn ($p) => (float) $p)->all();
    expect($precos)->toBe([12.125, 1500.0]);
});

it('UC-TRCRT-04 · transferência pendente fica registrada e NÃO mexe no estoque', function () {
    [$origem, $destino, $produto] = ($this->cenario)();

    $resposta = ($this->salvar)([
        'transaction_date' => '2026-10-11 09:30', 'ref_no' => '', 'status' => 'pending',
        'location_id' => $origem, 'transfer_location_id' => $destino,
        'additional_notes' => '', 'shipping_charges' => '0', 'final_total' => 37.5,
        'products' => [($this->item)($produto, '3', '12,5')],
    ]);
    ($this->exigirSucesso)($resposta);

    // Anti-vácuo: a transferência EXISTE — o saldo intacto é do status, não de um rollback.
    $venda = DB::table('transactions')->where('business_id', $this->biz)->where('type', 'sell_transfer')
        ->where('location_id', $origem)->orderByDesc('id')->first();
    expect($venda)->not->toBeNull();
    expect($venda->status)->toBe('pending');
    expect(DB::table('transaction_sell_lines')->where('transaction_id', $venda->id)->count())->toBe(1);

    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(10.0);
    expect(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(2.0);
});

it('UC-TRCRT-05 · a linha traz o saldo do local de origem e o último custo de compra', function () {
    [$origem, , $produto] = ($this->cenario)();

    $r = $this->actingAs($this->usuario)->withSession($this->sessao)->getJson(
        '/stock-transfers/produto-linha?variation_id=' . $produto->variations[0]['variation_id'] . '&location_id=' . $origem
    );

    $r->assertOk();
    expect($r->json('product_id'))->toBe($produto->productId);
    expect($r->json('variation_id'))->toBe($produto->variations[0]['variation_id']);
    expect($r->json('enable_stock'))->toBe(1);
    expect((float) $r->json('qty_available'))->toBe(10.0);
    expect((float) $r->json('custo'))->toBe(8.0);   // o purchase_price_inc_tax do lastro
    expect($r->json('unit_id'))->toBe($produto->unitId);
    expect($r->json('sub_unidades.0.multiplicador'))->toEqual(1);
});

it('UC-TRCRT-05 · variação de outra empresa não devolve linha (404), e a própria devolve', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Sem 2º business semeado.');
    }
    [$origem, , $meu] = ($this->cenario)();
    $alheio = EstoqueFixture::singleProduct($outro);
    EstoqueFixture::setStock($alheio, 0, EstoqueFixture::locationId($outro, '-TS-ALHEIO-' . uniqid()), 5.0);

    $pedir = fn (int $variacao) => $this->actingAs($this->usuario)->withSession($this->sessao)
        ->getJson('/stock-transfers/produto-linha?variation_id=' . $variacao . '&location_id=' . $origem);

    // Par positivo: a mesma chamada com a variação própria responde — o 404 é do tenant.
    $pedir($meu->variations[0]['variation_id'])->assertOk();
    $pedir($alheio->variations[0]['variation_id'])->assertStatus(404);
});

it('UC-TRCRT-05 · sem purchase.create os dados da linha respondem 403', function () {
    [$origem, , $produto] = ($this->cenario)();
    // Usuário NOVO, sem papel nenhum: o primeiro usuário do negócio pode ser administrador.
    $semPermissao = User::findOrFail((int) DB::table('users')->insertGetId([
        'first_name' => 'TS sem permissao', 'username' => 'ts_semperm_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $this->biz, 'created_at' => now(), 'updated_at' => now(),
    ]));

    $this->actingAs($semPermissao)->withSession(['user.id' => $semPermissao->id] + $this->sessao)
        ->getJson('/stock-transfers/produto-linha?variation_id=' . $produto->variations[0]['variation_id'] . '&location_id=' . $origem)
        ->assertStatus(403);
});
