<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-V380 / UC-V382 (resources/js/Pages/Sells/CreateV3.casos.md) — a V3 grava a venda.
 *
 * A V3 não tem store: ela posta em `/pos` (SellPosController@store, o caminho de produção)
 * o payload montado por `_components/v3/gravacao-dominio.ts`. A fixture
 * `tests/fixtures/sells-v3/venda-simples.json` é a MESMA que o vitest usa para provar que a
 * tela monta aquele payload — aqui ela é postada com os ids trocados pelos do tenant 98.
 *
 * Dupla prova da REGRA MESTRE (valor/estoque):
 *   Caminho 1 — conta à mão: 3 × 19,90 − 10% = 53,73 · 2 × 50,00 = 100,00 ·
 *               153,73 − 5,00 + frete 7,50 = 156,23; estoque 10 − 3 = 7 e 10 − 2 = 8.
 *   Caminho 2 — a mesma venda pelo payload do Create (formato do transform do Create.tsx),
 *               gravada pelo mesmo store(), comparada campo a campo.
 *
 * Setup copiado de SellsBonificacaoContratoTest.php (lastro de compra, sessão com o modelo
 * Business), com outro prefixo: os arquivos rodam no mesmo processo da lane.
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

function v3gravarFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/sells-v3/venda-simples.json')), true);
}

/** Produto com 10 em estoque e lastro de compra (sem lastro o store recusa por PurchaseSellMismatch). */
function v3gravarProduto(object $test): object
{
    $produto = EstoqueFixture::singleProduct($test->bizId);
    EstoqueFixture::setStock($produto, 0, $test->locationId, 10);
    $compraId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $test->bizId, 'type' => 'purchase', 'status' => 'received',
        'location_id' => $test->locationId, 'payment_status' => 'paid',
        'transaction_date' => now()->subDay(), 'total_before_tax' => 0, 'final_total' => 0,
        'created_by' => $test->user->id, 'essentials_duration' => 0,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId, 'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => 10,
        'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
        'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);

    return $produto;
}

/** Payload da V3: o da fixture, com os ids do tenant de teste no lugar dos de cena. */
function v3gravarPayloadV3(object $test, object $a, object $b): array
{
    $p = v3gravarFixture()['payload'];
    $p['location_id'] = $test->locationId;
    $p['contact_id'] = $test->contactId;
    $p['transaction_date'] = now()->format('d/m/Y H:i');
    foreach ([$a, $b] as $i => $produto) {
        $p['products'][$i]['product_id'] = $produto->productId;
        $p['products'][$i]['variation_id'] = (int) $produto->variations[0]['variation_id'];
    }

    return $p;
}

/** O mesmo pedido no formato do transform do Create.tsx (com as chaves de UI que ele carrega). */
function v3gravarPayloadCreate(object $test, object $a, object $b): array
{
    $linha = fn (object $produto, float $q, float $preco, float $desc) => [
        'product_id' => $produto->productId,
        'variation_id' => (int) $produto->variations[0]['variation_id'],
        'name' => 'x', 'variation' => null, 'sku' => 'x',
        'quantity' => $q, 'unit_price' => $preco, 'discount' => $desc, 'discount_type' => 'percentage',
        'imei_number' => '', 'unit_price_inc_tax' => $preco, 'item_tax' => 0, 'tax_id' => null,
        'line_discount_type' => 'percentage', 'line_discount_amount' => $desc,
        'enable_stock' => 1, 'product_type' => 'single',
    ];
    $pagamentos = [['amount' => 156.23, 'method' => 'cash', 'paid_on' => '', 'account_id' => null, 'note' => '']];

    return [
        'location_id' => $test->locationId, 'contact_id' => $test->contactId,
        'transaction_date' => now()->format('d/m/Y H:i'), 'status' => 'final',
        'invoice_scheme_id' => null, 'invoice_no' => '', 'pay_term_number' => '', 'pay_term_type' => 'days',
        'sinal_minimo_pct' => null, 'price_group_id' => null, 'commission_agent_id' => null,
        'tax_rate_id' => null, 'vehicle_id' => null,
        'products' => [$linha($a, 3, 19.9, 10), $linha($b, 2, 50, 0)],
        'payments' => $pagamentos, 'payment' => $pagamentos,
        'discount_type' => 'fixed', 'discount_amount' => 5,
        'rp_redeemed' => 0, 'rp_redeemed_amount' => '0.00', 'notes' => '', 'staff_note' => '',
        'is_recurring' => 0, 'customer_secondary_address' => '', 'sell_document' => null,
        'shipping' => ['details' => '', 'address' => '', 'cost' => 7.5, 'status' => '', 'deliver_to' => ''],
        'is_direct_sale' => 1, 'is_save_and_print' => 0,
        'commission_agent' => null, 'price_group' => null, 'default_price_group' => null,
        'sale_note' => '', 'additional_notes' => '',
        'shipping_details' => '', 'shipping_address' => '', 'shipping_charges' => 7.5,
        'shipping_status' => '', 'delivered_to' => '',
        'additional_expense_key_1' => '', 'additional_expense_value_1' => 0,
        'final_total' => 156.23,
    ];
}

/** Posta e devolve venda, linhas (na ordem dos produtos), pagamentos e saldo de cada produto. */
function v3gravarVender(object $test, callable $montar): array
{
    $a = v3gravarProduto($test);
    $b = v3gravarProduto($test);
    $response = $test->post('/pos', $montar($test, $a, $b));

    $id = DB::table('transaction_sell_lines')->where('product_id', $a->productId)->value('transaction_id');
    \PHPUnit\Framework\Assert::assertNotNull(
        $id,
        'store() não gravou a venda. status='.$response->status()
            .' erros='.json_encode(session('errors')?->getBag('default')->all() ?? [], JSON_UNESCAPED_UNICODE)
    );

    return [
        'response' => $response,
        'venda' => DB::table('transactions')->where('id', $id)->first(),
        'linhas' => DB::table('transaction_sell_lines')->where('transaction_id', $id)->orderBy('product_id')->get(),
        'pago' => (float) DB::table('transaction_payments')->where('transaction_id', $id)->sum('amount'),
        'saldos' => [
            EstoqueFixture::currentStock($a, 0, $test->locationId),
            EstoqueFixture::currentStock($b, 0, $test->locationId),
        ],
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId(); // tenant 98 (ADR 0358 — nunca biz=4).
    $this->locationId = EstoqueFixture::locationId($this->bizId);
    $this->contactId = (int) DB::table('contacts')->where('business_id', $this->bizId)->orderBy('id')->value('id');
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user || $this->contactId === 0) {
        $this->markTestSkipped('Sem user ou contato no business seeded.');
    }

    $this->actingAs($this->user);
    $business = \App\Business::findOrFail($this->bizId);
    $business->date_format = 'd/m/Y';
    $business->time_format = 24;
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id, 'business' => $business]);

    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))
        ->first();
    session(['currency' => [
        'id' => (int) ($currency->id ?? 1),
        'code' => (string) ($currency->code ?? 'BRL'),
        'symbol' => (string) ($currency->symbol ?? 'R$'),
        'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
        'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
    ]]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('sell.create', 'web');
    $this->user->givePermissionTo('sell.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('UC-V380 · a venda da V3 grava o mesmo total, as mesmas linhas, o mesmo pagamento e a mesma baixa de estoque que a do Create', function () {
    $v3 = v3gravarVender($this, 'v3gravarPayloadV3');
    $create = v3gravarVender($this, 'v3gravarPayloadCreate');

    // Caminho 1 — conta à mão.
    expect((float) $v3['venda']->final_total)->toBe(156.23);
    expect((float) $v3['venda']->shipping_charges)->toBe(7.5);
    expect((float) $v3['venda']->discount_amount)->toBe(5.0);
    expect($v3['pago'])->toBe(156.23);
    expect($v3['venda']->payment_status)->toBe('paid');
    expect($v3['saldos'])->toBe([7.0, 8.0]);

    // Caminho 2 — campo a campo contra a venda do Create.
    foreach (['final_total', 'total_before_tax', 'tax_amount', 'discount_type', 'discount_amount', 'shipping_charges', 'payment_status', 'status', 'type'] as $campo) {
        expect((string) $v3['venda']->{$campo})->toBe((string) $create['venda']->{$campo});
    }
    expect($v3['linhas'])->toHaveCount(2);
    expect($create['linhas'])->toHaveCount(2);
    foreach ([0, 1] as $i) {
        foreach (['quantity', 'unit_price_before_discount', 'unit_price', 'unit_price_inc_tax', 'line_discount_type', 'line_discount_amount'] as $campo) {
            expect((string) $v3['linhas'][$i]->{$campo})->toBe((string) $create['linhas'][$i]->{$campo});
        }
    }
    expect($v3['pago'])->toBe($create['pago']);
    expect($v3['saldos'])->toBe($create['saldos']);
    expect((int) $v3['venda']->business_id)->toBe($this->bizId);
});

it('UC-V382 · com a flag desligada a tela segue preview; ligada, entrega o local e o cliente padrão DESTA empresa', function () {
    // Cliente padrão da empresa (o "consumidor final" do getWalkInCustomer). O seed pode não
    // marcar nenhum; aqui marca o contato do tenant 98 dentro da transação do teste.
    if (! DB::table('contacts')->where('business_id', $this->bizId)->where('is_default', 1)->whereIn('type', ['customer', 'both'])->exists()) {
        DB::table('contacts')->where('id', $this->contactId)->update(['is_default' => 1, 'type' => 'customer']);
    }

    config(['feature-flags.forced_on' => '']);
    $this->get('/sells/create-v3')->assertInertia(fn ($page) => $page
        ->component('Sells/CreateV3')
        ->missing('gravacao'));

    config(['feature-flags.forced_on' => 'useV3SellsGravar']);
    $resposta = $this->get('/sells/create-v3');
    $resposta->assertInertia(fn ($page) => $page
        ->component('Sells/CreateV3')
        ->has('gravacao.locationId')
        ->has('gravacao.contactId')
        ->where('cena.itens', []));

    $props = $resposta->viewData('page')['props'];
    expect((int) DB::table('business_locations')->where('id', $props['gravacao']['locationId'])->value('business_id'))->toBe($this->bizId);
    expect((int) DB::table('contacts')->where('id', $props['gravacao']['contactId'])->value('business_id'))->toBe($this->bizId);
});
