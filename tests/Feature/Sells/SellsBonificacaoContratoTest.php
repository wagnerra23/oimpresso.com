<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-SBON-01 (resources/js/Pages/Sells/Create.casos.md) — venda marcada como bonificação.
 *
 * Pedido P-154 (Sabor Brasil): bonificação a supermercado. Esta parte prova só a MARCA:
 * o PDV envia `sub_type=bonificacao` (tests/js/sells-subtipo-venda.test.ts) e o
 * `SellPosController@store` grava o tipo, aceitando só `repair` e `bonificacao`.
 * Financeiro (sem título) e NF-e (CFOP 5910/6910) leem o tipo em outras partes.
 *
 * Dupla prova da REGRA MESTRE (valor/estoque): a venda comum e a bonificada diferem SÓ
 * no tipo. Caminho 1 = conta à mão (2 un × 50,00 = 100,00; estoque 10 − 2 = 8). Caminho 2 =
 * a comum gravada pelo mesmo store(), campo a campo. Se o tipo entrasse em algum cálculo,
 * total, linhas ou baixa de estoque divergiriam.
 *
 * Helpers copiados de SellsRepairSubtipoContratoTest.php (mesmo payload do transform do
 * Create.tsx), com outro nome: os dois arquivos rodam no mesmo processo da lane.
 *
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

/** Payload no formato do transform do Sells/Create.tsx (POST /pos). */
function bonificacaoPayload(int $locationId, int $contactId, int $productId, int $variationId, array $extra = []): array
{
    return array_merge([
        'location_id' => $locationId,
        'contact_id' => $contactId,
        'transaction_date' => now()->format('d/m/Y H:i'),
        'status' => 'final',
        'is_direct_sale' => 1,
        'is_save_and_print' => 0,
        'discount_type' => 'percentage',
        'discount_amount' => 0,
        'tax_rate_id' => null,
        'final_total' => 100,
        'payment' => [['amount' => 0, 'method' => 'cash', 'paid_on' => '', 'account_id' => null, 'note' => '']],
        'products' => [[
            'product_id' => $productId,
            'variation_id' => $variationId,
            'quantity' => 2,
            'unit_price' => 50,
            'unit_price_inc_tax' => 50,
            'item_tax' => 0,
            'tax_id' => null,
            'line_discount_type' => 'fixed',
            'line_discount_amount' => 0,
            'imei_number' => '',
            'enable_stock' => 1,
            'product_type' => 'single',
        ]],
    ], $extra);
}

/** Cria produto com 10 em estoque, posta a venda e devolve a venda gravada + o saldo depois. */
function bonificacaoVender(object $test, array $extra = []): array
{
    $produto = EstoqueFixture::singleProduct($test->bizId);
    EstoqueFixture::setStock($produto, 0, $test->locationId, 10);

    // LASTRO de compra (receita do EstoqueTransferenciaIdempotenciaTest): o `store()` chama
    // `mapPurchaseSell`, que sem purchase_lines na location lança PurchaseSellMismatch — e o
    // catch engole em `back()->withErrors(['venda'])`. Medido no 1º run do CI: as duas vendas
    // voltavam null por isto. Em produção o saldo sempre veio de uma entrada.
    $compraId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $test->bizId,
        'type' => 'purchase',
        'status' => 'received',
        'location_id' => $test->locationId,
        'payment_status' => 'paid',
        'transaction_date' => now()->subDay(),
        'total_before_tax' => 0,
        'final_total' => 0,
        'created_by' => $test->user->id,
        'essentials_duration' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId,
        'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => 10,
        'quantity_sold' => 0,
        'quantity_adjusted' => 0,
        'quantity_returned' => 0,
        'purchase_price' => 0,
        'purchase_price_inc_tax' => 0,
        'item_tax' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    // O catch do store() troca a exceção por "Something went wrong" e só a registra em
    // Log::emergency. Medido no 2º run do CI: com lastro, a recusa seguiu genérica — então a
    // causa real é capturada aqui e vai pra mensagem de falha abaixo.
    $logs = [];
    \Illuminate\Support\Facades\Event::listen(
        \Illuminate\Log\Events\MessageLogged::class,
        function ($e) use (&$logs) {
            if (in_array($e->level, ['emergency', 'error', 'critical'], true)) {
                $logs[] = mb_substr($e->message, 0, 400);
            }
        }
    );

    $response = $test->post('/pos', bonificacaoPayload(
        $test->locationId,
        $test->contactId,
        $produto->productId,
        (int) $produto->variations[0]['variation_id'],
        $extra,
    ));

    $transactionId = DB::table('transaction_sell_lines')
        ->where('product_id', $produto->productId)
        ->value('transaction_id');

    // Diagnóstico: venda não gravada = o store() recusou. A causa vai pra sessão ('venda'),
    // então ela entra na mensagem — sem isto o CI só mostra "null".
    \PHPUnit\Framework\Assert::assertNotNull(
        $transactionId,
        'store() não gravou a venda. status HTTP='.$response->status()
            .' erros='.json_encode(session('errors')?->getBag('default')->all() ?? [])
            .' log='.json_encode($logs, JSON_UNESCAPED_UNICODE)
    );

    return [
        'response' => $response,
        'venda' => $transactionId ? DB::table('transactions')->where('id', $transactionId)->first() : null,
        'linhas' => $transactionId ? DB::table('transaction_sell_lines')->where('transaction_id', $transactionId)->get() : collect(),
        'saldo' => EstoqueFixture::currentStock($produto, 0, $test->locationId),
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
    // `business` na sessão é o MODELO, como o SetSessionData:59 grava em produção — não array.
    // O store() lê `$business->enable_rp` como propriedade (TransactionUtil::calculateRewardPoints):
    // com array, lança "Attempt to read property on array" e o catch devolve "Something went
    // wrong" (medido no 3º run do CI). Formato de data fixado no objeto, sem salvar, pra bater
    // com o `transaction_date` do payload.
    $business = \App\Business::findOrFail($this->bizId);
    $business->date_format = 'd/m/Y';
    $business->time_format = 24;
    session([
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
        'business' => $business,
    ]);

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

it('UC-SBON-01 · venda marcada como bonificação é gravada como bonificação, com o mesmo valor e a mesma baixa de estoque da venda comum', function () {
    $comum = bonificacaoVender($this);
    $bonificada = bonificacaoVender($this, ['sub_type' => 'bonificacao']);

    expect($comum['venda'])->not->toBeNull();
    expect($bonificada['venda'])->not->toBeNull();

    expect($comum['venda']->sub_type)->toBeNull();
    expect($bonificada['venda']->sub_type)->toBe('bonificacao');

    // Caminho 1 — conta à mão: 2 un × 50,00 = 100,00.
    expect((float) $bonificada['venda']->final_total)->toBe(100.0);
    // Caminho 2 — campo a campo contra a venda comum.
    foreach (['final_total', 'total_before_tax', 'tax_amount', 'discount_amount'] as $campo) {
        expect((float) $bonificada['venda']->{$campo})->toBe((float) $comum['venda']->{$campo});
    }

    expect($bonificada['linhas'])->toHaveCount(1);
    expect($comum['linhas'])->toHaveCount(1);
    expect((float) $bonificada['linhas'][0]->quantity)->toBe((float) $comum['linhas'][0]->quantity);
    expect((float) $bonificada['linhas'][0]->unit_price_inc_tax)->toBe((float) $comum['linhas'][0]->unit_price_inc_tax);

    // Mesma baixa de estoque: 10 − 2 nas duas.
    expect($comum['saldo'])->toBe(8.0);
    expect($bonificada['saldo'])->toBe(8.0);

    // As duas voltam pra listagem de vendas.
    $comum['response']->assertRedirect(action([\App\Http\Controllers\SellController::class, 'index']));
    $bonificada['response']->assertRedirect(action([\App\Http\Controllers\SellController::class, 'index']));
});

it('UC-SBON-01 · tipo fora da lista vira venda comum (sub_type nunca é texto livre)', function () {
    $livre = bonificacaoVender($this, ['sub_type' => 'qualquer_coisa']);

    expect($livre['venda'])->not->toBeNull();
    expect($livre['venda']->sub_type)->toBeNull();
    expect((float) $livre['venda']->final_total)->toBe(100.0);
    expect($livre['saldo'])->toBe(8.0);
});
