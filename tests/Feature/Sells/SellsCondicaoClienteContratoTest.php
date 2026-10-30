<?php

declare(strict_types=1);

use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\SinalDaOrdem;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-SCOND-01..03 (resources/js/Pages/Sells/Create.casos.md) — a condição do cliente preenche a venda.
 *
 * Pedido P-026 (NewPrint, Sabor Brasil, ArtLaser). O prazo do cliente já chegava à venda; o sinal
 * do cliente (P-025, `contacts.sinal_minimo_pct`) passa a vir na busca de cliente, a tela o
 * preenche e o vendedor pode trocar. O que a venda grava (`transactions.sinal_minimo_pct`) vale
 * para as OS dela no lugar do cliente e da empresa (`SinalDaOrdem::minimo`).
 *
 * REGRA MESTRE: o sinal não entra no total nem no estoque. Dupla prova: a venda com sinal grava
 * 100,00 (2 × 50,00, conta à mão) e o mesmo total da venda sem sinal; o exigido de 40% sobre
 * 100,00 é 40,00 à mão e pelo SinalDaOrdem (MySQL).
 *
 * Helpers copiados de SellsBonificacaoContratoTest.php (mesmo payload do transform do Create.tsx),
 * com outro nome: os arquivos rodam no mesmo processo da lane. Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

/** Payload no formato do transform do Sells/Create.tsx (POST /pos). */
function condicaoPayload(int $locationId, int $contactId, int $productId, int $variationId, array $extra = []): array
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
function condicaoVender(object $test, array $extra = []): array
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

    $response = $test->post('/pos', condicaoPayload(
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
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transactions', 'sinal_minimo_pct') || ! Schema::hasColumn('contacts', 'sinal_minimo_pct')) {
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


/** Cliente do teste com a condição própria; a venda é feita para ele. */
function condicaoCliente(object $test, ?string $sinal, ?int $prazo): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $test->bizId, 'type' => 'customer', 'name' => 'Cliente P026 '.uniqid(),
        'sinal_minimo_pct' => $sinal, 'pay_term_number' => $prazo, 'pay_term_type' => $prazo ? 'days' : null,
        'created_by' => $test->user->id, 'contact_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-SCOND-01 · a busca de cliente da venda traz o sinal e o prazo do cliente', function () {
    $cliente = condicaoCliente($this, '50.00', 28);
    $semCondicao = condicaoCliente($this, null, null);

    $linhas = collect($this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->getJson('/contacts/customers')->assertOk()->json())->keyBy('id');

    expect($linhas->has($cliente))->toBeTrue('o cliente do teste não voltou na busca da venda');
    expect((float) $linhas[$cliente]['sinal_minimo_pct'])->toBe(50.0)
        ->and((int) $linhas[$cliente]['pay_term_number'])->toBe(28)
        ->and($linhas[$semCondicao]['sinal_minimo_pct'])->toBeNull();
});

it('UC-SCOND-02 · a venda grava o sinal trocado pelo vendedor e ele vale no lugar do cliente e da empresa, sem mexer no total', function () {
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => $this->bizId], ['modelo' => 'cv-pequena', 'updated_at' => now()]);
    app(RegrasDaEmpresa::class)->mudarSinalMinimo($this->bizId, '30');
    $this->contactId = condicaoCliente($this, '50.00', 28);

    $trocada = condicaoVender($this, ['sinal_minimo_pct' => 40]);
    $doCliente = condicaoVender($this);
    $sinal = app(SinalDaOrdem::class);

    expect((string) $trocada['venda']->sinal_minimo_pct)->toBe('40.00')
        ->and($doCliente['venda']->sinal_minimo_pct)->toBeNull()
        ->and($sinal->minimo($this->bizId, (int) $trocada['venda']->id))->toBe('40.00')
        ->and($sinal->minimo($this->bizId, (int) $doCliente['venda']->id))->toBe('50.00');

    // Regra Mestre — caminho 1, à mão: 2 × 50,00 = 100,00; caminho 2: o mesmo total da venda sem sinal.
    expect((float) $trocada['venda']->final_total)->toBe(100.0)
        ->and((float) $trocada['venda']->final_total)->toBe((float) $doCliente['venda']->final_total)
        ->and($sinal->situacao($this->bizId, (int) $trocada['venda']->id, '40.00'))
        ->toBe(['pago' => '0.00', 'exigido' => '40.00', 'ok' => false]);

    // Venda com 0: dispensada, mesmo com cliente e empresa exigindo.
    $dispensada = condicaoVender($this, ['sinal_minimo_pct' => 0]);
    expect($sinal->minimo($this->bizId, (int) $dispensada['venda']->id))->toBeNull();
});

it('UC-SCOND-03 · sinal acima de 100% é recusado e a venda não é gravada', function () {
    $antes = (int) DB::table('transactions')->where('business_id', $this->bizId)->count();

    $this->post('/pos', condicaoPayload($this->locationId, $this->contactId, 1, 1, ['sinal_minimo_pct' => 101]))
        ->assertSessionHasErrors('sinal_minimo_pct');

    expect((int) DB::table('transactions')->where('business_id', $this->bizId)->count())->toBe($antes);
});
