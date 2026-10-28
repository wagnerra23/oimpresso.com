<?php

declare(strict_types=1);

// @covers-uc UC-TREDT-01 UC-TREDT-02 UC-TREDT-03 (resources/js/Pages/StockTransfer/Edit.casos.md)

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * GET /stock-transfers/{id}/edit (Page React `StockTransfer/Edit`) e PUT /stock-transfers/{id}
 * no formato que a tela React manda.
 *
 * CONTRATO: DOC-RAIZ-ESTOQUE §3 (transferência SAI da origem e ENTRA no destino pela mesma
 * quantidade) + RUNBOOK-stock-transfer-edit (o que muda, o que fica travado, Tier 0).
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

    $negocio = Business::find($this->biz);
    $this->sessao = [
        'user.id' => $this->userId,
        'user.business_id' => $this->biz,
        'business' => $negocio,
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];

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

    $this->item = fn ($produto, string $quantidade, string $preco, ?int $linhaVenda = null): array => array_filter([
        'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'],
        'enable_stock' => 1,
        'quantity' => $quantidade,
        'base_unit_multiplier' => '1',
        'product_unit_id' => $produto->unitId,
        'unit_price' => $preco,
        'transaction_sell_lines_id' => $linhaVenda,
    ], fn ($v) => $v !== null);

    /** Transferência PENDENTE de 3 un: origem 10, destino 2 (pendente não move saldo). */
    $this->pendente = function (): array {
        $origem = EstoqueFixture::locationId($this->biz, '-TE-ORIG-' . uniqid());
        $destino = EstoqueFixture::locationId($this->biz, '-TE-DEST-' . uniqid());
        $produto = EstoqueFixture::singleProduct($this->biz);
        EstoqueFixture::setStock($produto, 0, $origem, 10.0);
        EstoqueFixture::setStock($produto, 0, $destino, 2.0);
        ($this->darLastro)($produto, $origem, 10.0);

        $this->actingAs($this->usuario)->withSession($this->sessao)->post('/stock-transfers', [
            'transaction_date' => '2026-10-27 09:30', 'ref_no' => '', 'status' => 'pending',
            'location_id' => $origem, 'transfer_location_id' => $destino,
            'additional_notes' => '', 'shipping_charges' => '0', 'final_total' => 37.5,
            'products' => [($this->item)($produto, '3', '12,5')],
        ]);
        $venda = DB::table('transactions')->where('business_id', $this->biz)->where('type', 'sell_transfer')
            ->where('location_id', $origem)->orderByDesc('id')->first();
        expect($venda)->not->toBeNull('a transferência pendente de partida precisa existir');

        return [$origem, $destino, $produto, $venda];
    };
});

it('UC-TREDT-01 [T0] transferência de outra empresa não abre (404); sem purchase.create, 403', function () {
    [, , , $venda] = ($this->pendente)();

    // Par positivo: a própria abre.
    $this->actingAs($this->usuario)->withSession($this->sessao)->get("/stock-transfers/{$venda->id}/edit")->assertOk();

    $outro = EstoqueFixture::secondBusinessId();
    if ($outro !== null) {
        $alheio = User::find(EstoqueFixture::userId($outro));
        $alheio->givePermissionTo('purchase.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($alheio)
            ->withSession(['user.id' => $alheio->id, 'user.business_id' => $outro] + $this->sessao)
            ->get("/stock-transfers/{$venda->id}/edit")
            ->assertStatus(404);
    }

    $semPermissao = User::findOrFail((int) DB::table('users')->insertGetId([
        'first_name' => 'TE sem permissao', 'username' => 'te_semperm_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $this->biz, 'created_at' => now(), 'updated_at' => now(),
    ]));
    $this->actingAs($semPermissao)->withSession(['user.id' => $semPermissao->id] + $this->sessao)
        ->get("/stock-transfers/{$venda->id}/edit")
        ->assertStatus(403);
});

it('UC-TREDT-02 a edição abre a Page React com as linhas gravadas e origem/destino da transferência', function () {
    [$origem, $destino, $produto, $venda] = ($this->pendente)();
    $linhaVenda = (int) DB::table('transaction_sell_lines')->where('transaction_id', $venda->id)->value('id');

    $this->actingAs($this->usuario)->withSession($this->sessao)
        ->get("/stock-transfers/{$venda->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p
            ->component('StockTransfer/Edit', false)
            ->where('transferencia.id', (int) $venda->id)
            ->where('transferencia.status', 'pending')
            ->where('transferencia.location_id', $origem)
            ->where('transferencia.transfer_location_id', $destino)
            ->has('transferencia.linhas', 1)
            ->where('transferencia.linhas.0.transaction_sell_lines_id', $linhaVenda)
            ->where('transferencia.linhas.0.quantidade', fn ($q) => (float) $q === 3.0)
            ->where('transferencia.linhas.0.dados.variation_id', $produto->variations[0]['variation_id'])
            // saldo da ORIGEM, sem a pendente ter baixado nada
            ->where('transferencia.linhas.0.dados.qty_available', fn ($q) => (float) $q === 10.0)
        );

    // `?classico=1` mantém a Blade até o cutover.
    $this->actingAs($this->usuario)->withSession($this->sessao)
        ->get("/stock-transfers/{$venda->id}/edit?classico=1")
        ->assertOk()
        ->assertViewIs('stock_transfer.edit');
});

it('UC-TREDT-03 editar a pendente para 4 un e concluir tira 4 da origem e põe 4 no destino (formato da tela React)', function () {
    [$origem, $destino, $produto, $venda] = ($this->pendente)();
    $linhaVenda = (int) DB::table('transaction_sell_lines')->where('transaction_id', $venda->id)->value('id');

    $r = $this->actingAs($this->usuario)->withSession($this->sessao)->put("/stock-transfers/{$venda->id}", [
        'transaction_date' => '2026-10-27 10:00',   // ISO — o que o datetime-local produz
        'status' => 'completed',
        'additional_notes' => 'corrigido',
        'shipping_charges' => '0',
        'final_total' => 50,
        'products' => [($this->item)($produto, '4', '12,5', $linhaVenda)],
    ]);
    $r->assertRedirect();
    expect(session('status')['success'] ?? null)->toBe(1, 'o update() precisa COMPLETAR. msg: '
        . json_encode(session('status')['msg'] ?? null, JSON_UNESCAPED_UNICODE));

    // Dupla prova (REGRA MESTRE): antes origem 10 / destino 2 / total 12 → depois 6 / 6 / 12.
    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(6.0, 'origem: 10 - 4');
    expect(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(6.0, 'destino: 2 + 4');
    expect(EstoqueFixture::totalStock($produto))->toBe(12.0, 'transferência não cria nem destrói saldo');

    $depois = DB::table('transactions')->where('id', $venda->id)->first();
    expect($depois->status)->toBe('final');
    expect((string) $depois->transaction_date)->toBe('2026-10-27 10:00:00');
    // A linha foi EDITADA, não duplicada.
    $linhas = DB::table('transaction_sell_lines')->where('transaction_id', $venda->id)->get();
    expect($linhas)->toHaveCount(1);
    expect((float) $linhas[0]->quantity)->toBe(4.0);
    expect((float) $linhas[0]->unit_price)->toBe(12.5);
});
