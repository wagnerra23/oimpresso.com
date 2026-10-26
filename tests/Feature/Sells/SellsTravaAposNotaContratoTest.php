<?php

// @covers-us US-SELL-072

declare(strict_types=1);
// Cobre UC-SEDIT-13, UC-SEDIT-14, UC-SEDIT-15, UC-SEDIT-16, UC-SEDIT-17, UC-SEDIT-18
// (resources/js/Pages/Sells/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Contracts\Fiscal\NotaDaVenda;
use App\Services\Sells\TravaDaVenda;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Venda com nota ou pagamento trava itens e valores (US-SELL-072).
 *
 * ÂNCORA: decisão [W] 2026-10-20, textual — "Trava itens e valores após nota". Com nota fiscal
 * válida na venda, itens, valores e cliente não se editam (cancela-se a nota antes). Com pagamento
 * só os valores travam. Campos de texto seguem editáveis. Sem nota nem pagamento, nada muda.
 *
 * Os casos saem dessa decisão, não do código. Caminho sob teste: PUT /pos/{id}
 * (SellPosController@update), o mesmo do Blade e da tela React.
 *
 * ⛔ Tenant fictício 98 (ADR 0358) contra o 2º business semeado. Nunca biz=4.
 * ⚠️ REGRA MESTRE VALOR: todo caso que recusa confere que total, linhas e estoque NÃO mudaram; o
 *    caso sem trava confere o mesmo total que o update dava antes desta US (UC-SEDIT-18).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_sell_lines', 'transaction_payments', 'nfe_emissoes', 'nfse_emissoes'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->bizId = EstoqueFixture::businessId(); // 98
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business semeado.');
    }
    $this->actingAs($this->user);

    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))->first();
    $business = \App\Business::findOrFail($this->bizId);
    $business->date_format = 'd/m/Y';
    $business->time_format = 24;
    session([
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
        'business' => $business, // o update lê a sessão de produção (modelo, SetSessionData)
        'currency' => [
            'id' => (int) ($currency->id ?? 1), 'code' => (string) ($currency->code ?? 'BRL'),
            'symbol' => (string) ($currency->symbol ?? 'R$'),
            'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
            'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
        ],
    ]);
    foreach (['sell.update', 'direct_sell.access', 'direct_sell.update'] as $perm) {
        Permission::findOrCreate($perm, 'web');
        $this->user->givePermissionTo($perm);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // ANTI-VÁCUO: os módulos fiscais se registraram na tag — senão "não travou" seria vácuo.
    expect(iterator_to_array(app()->tagged(NotaDaVenda::TAG)))->not->toBeEmpty();
});

/** Venda direta final: 1 linha, qtd 2 × 50 = 100, sem desconto, sem nota, sem pagamento. */
function stravaVenda(object $test, int $bizId): array
{
    $loc = EstoqueFixture::locationId($bizId);
    $p = EstoqueFixture::singleProduct($bizId);
    EstoqueFixture::setStock($p, 0, $loc, 10);
    $uid = (int) DB::table('users')->where('business_id', $bizId)->orderBy('id')->value('id');
    $compra = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'type' => 'purchase', 'status' => 'received', 'location_id' => $loc,
        'payment_status' => 'paid', 'transaction_date' => now()->subDays(3), 'total_before_tax' => 0, 'final_total' => 0,
        'created_by' => $uid, 'essentials_duration' => 0, 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
        'quantity' => 10, 'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
        'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $contato = (int) DB::table('contacts')->where('business_id', $bizId)->orderBy('id')->value('id');
    $venda = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $loc, 'contact_id' => $contato, 'type' => 'sell', 'status' => 'final',
        'is_direct_sale' => 1, 'payment_status' => 'due', 'transaction_date' => now()->subDay()->startOfMinute(),
        'discount_type' => 'fixed', 'discount_amount' => 0, 'shipping_charges' => 0,
        'total_before_tax' => 100, 'final_total' => 100, 'additional_notes' => 'nota antes',
        'created_by' => $uid, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha = (int) DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
        'quantity' => 2, 'quantity_returned' => 0, 'unit_price_before_discount' => 50, 'unit_price' => 50,
        'line_discount_type' => 'fixed', 'line_discount_amount' => 0, 'unit_price_inc_tax' => 50, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('variation_location_details')
        ->where('variation_id', $p->variations[0]['variation_id'])->where('location_id', $loc)->update(['qty_available' => 8]);

    return ['id' => $venda, 'loc' => $loc, 'contato' => $contato, 'produto' => $p, 'linha' => $linha];
}

/** Payload da tela React (edicaoVenda.ts::linhaParaEnvio), números em pt-BR. */
function stravaPayload(array $v, float $qtd = 2, array $extra = []): array
{
    $pt = fn ($n) => number_format((float) $n, 4, ',', '');

    return $extra + [
        '_method' => 'PUT',
        'transaction_date' => (string) DB::table('transactions')->where('id', $v['id'])->value('transaction_date'),
        'contact_id' => $v['contato'], 'location_id' => $v['loc'], 'status' => 'final',
        'discount_type' => 'fixed', 'discount_amount' => $pt(0), 'tax_rate_id' => null, 'shipping_charges' => $pt(0),
        'calcular_total_no_servidor' => 1, 'additional_notes' => 'nota antes',
        'products' => [[
            'transaction_sell_lines_id' => $v['linha'], 'product_id' => $v['produto']->productId,
            'variation_id' => $v['produto']->variations[0]['variation_id'], 'quantity' => $pt($qtd),
            'unit_price' => $pt(50), 'unit_price_inc_tax' => $pt(50), 'item_tax' => $pt(0), 'tax_id' => null,
            'line_discount_amount' => $pt(0), 'line_discount_type' => 'fixed', 'imei_number' => '',
        ]],
    ];
}

function stravaEstado(array $v): array
{
    return [
        'final_total' => (string) DB::table('transactions')->where('id', $v['id'])->value('final_total'),
        'contact_id' => (int) DB::table('transactions')->where('id', $v['id'])->value('contact_id'),
        'linhas' => DB::table('transaction_sell_lines')->where('transaction_id', $v['id'])->orderBy('id')
            ->get(['quantity', 'unit_price_inc_tax'])->map(fn ($r) => array_map('strval', (array) $r))->all(),
        'estoque' => EstoqueFixture::currentStock($v['produto'], 0, $v['loc']),
    ];
}

function stravaNfe(int $biz, int $transactionId, string $status): void
{
    DB::table('nfe_emissoes')->insert([
        'business_id' => $biz, 'transaction_id' => $transactionId, 'modelo' => '55', 'serie' => '9',
        'numero' => random_int(800000, 999999), 'status' => $status, 'valor_total' => 100.00,
        'emitido_em' => now(), 'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function stravaPagamento(object $test, array $v): void
{
    DB::table('transaction_payments')->insert([
        'transaction_id' => $v['id'], 'payment_for' => $v['contato'], 'business_id' => $test->bizId,
        'amount' => 100, 'method' => 'cash', 'is_return' => 0, 'paid_on' => now(),
        'created_by' => $test->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function stravaResultado(): array
{
    return ['success' => (int) data_get(session('status'), 'success', -1), 'msg' => (string) data_get(session('status'), 'msg', '')];
}

it('UC-SEDIT-13 · nota autorizada recusa mudar quantidade e nada muda', function () {
    $v = stravaVenda($this, $this->bizId);
    stravaNfe($this->bizId, $v['id'], 'autorizada');
    $antes = stravaEstado($v);

    $this->put("/pos/{$v['id']}", stravaPayload($v, 3))->assertSessionHasNoErrors();

    expect(stravaResultado())->toBe(['success' => 0, 'msg' => TravaDaVenda::MSG_NOTA]);
    expect(stravaEstado($v))->toBe($antes);
});

it('UC-SEDIT-13 · nota autorizada recusa trocar o cliente', function () {
    $v = stravaVenda($this, $this->bizId);
    $outro = (int) DB::table('contacts')->where('business_id', $this->bizId)->where('id', '!=', $v['contato'])->value('id');
    if ($outro === 0) {
        $this->markTestSkipped('Business 98 com um contato só.');
    }
    stravaNfe($this->bizId, $v['id'], 'autorizada');
    $antes = stravaEstado($v);

    $this->put("/pos/{$v['id']}", stravaPayload($v, 2, ['contact_id' => $outro]));

    expect(stravaResultado())->toBe(['success' => 0, 'msg' => TravaDaVenda::MSG_NOTA]);
    expect(stravaEstado($v))->toBe($antes);
});

it('UC-SEDIT-14 · nota autorizada deixa salvar a observação, sem mexer em total, linhas e estoque', function () {
    $v = stravaVenda($this, $this->bizId);
    stravaNfe($this->bizId, $v['id'], 'autorizada');
    $antes = stravaEstado($v);

    $this->put("/pos/{$v['id']}", stravaPayload($v, 2, ['additional_notes' => 'entregar na portaria']));

    expect(stravaResultado()['success'])->toBe(1);
    expect((string) DB::table('transactions')->where('id', $v['id'])->value('additional_notes'))->toBe('entregar na portaria');
    expect(stravaEstado($v))->toBe($antes);
});

it('UC-SEDIT-15 · nota cancelada não trava: a quantidade muda', function () {
    $v = stravaVenda($this, $this->bizId);
    stravaNfe($this->bizId, $v['id'], 'cancelada');

    $this->put("/pos/{$v['id']}", stravaPayload($v, 3));

    expect(stravaResultado()['success'])->toBe(1, json_encode(session('status')));
    expect(stravaEstado($v)['linhas'][0]['quantity'])->toBe('3.0000');
});

it('UC-SEDIT-16 · pagamento recusa mudar valores e deixa salvar a observação', function () {
    $v = stravaVenda($this, $this->bizId);
    stravaPagamento($this, $v);
    $antes = stravaEstado($v);

    $this->put("/pos/{$v['id']}", stravaPayload($v, 3));
    expect(stravaResultado())->toBe(['success' => 0, 'msg' => TravaDaVenda::MSG_PAGAMENTO]);
    expect(stravaEstado($v))->toBe($antes);

    $this->put("/pos/{$v['id']}", stravaPayload($v, 2, ['additional_notes' => 'cliente retira sábado']));
    expect(stravaResultado()['success'])->toBe(1, json_encode(session('status')));
    expect((string) DB::table('transactions')->where('id', $v['id'])->value('additional_notes'))->toBe('cliente retira sábado');
    expect(stravaEstado($v)['final_total'])->toBe($antes['final_total']);
    expect(stravaEstado($v)['linhas'])->toBe($antes['linhas']);
});

it('UC-SEDIT-17 · nota de outro business com o mesmo transaction_id não trava esta venda', function () {
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant.');
    }
    $v = stravaVenda($this, $this->bizId);
    stravaNfe($outroBiz, $v['id'], 'autorizada');
    // ANTI-VÁCUO: a nota alheia existe, apontando pra esta venda.
    expect(DB::table('nfe_emissoes')->where('business_id', $outroBiz)->where('transaction_id', $v['id'])->exists())->toBeTrue();

    $this->put("/pos/{$v['id']}", stravaPayload($v, 3));

    expect(stravaResultado()['success'])->toBe(1, json_encode(session('status')));
    expect(stravaEstado($v)['linhas'][0]['quantity'])->toBe('3.0000');
});

it('UC-SEDIT-18 · venda sem nota nem pagamento edita como antes (100 + 1 un de 50 = 150)', function () {
    $v = stravaVenda($this, $this->bizId);
    $trava = app(TravaDaVenda::class)->estado(\App\Transaction::findOrFail($v['id']));
    expect($trava['itens_valores'])->toBeFalse();

    $this->put("/pos/{$v['id']}", stravaPayload($v, 3));

    expect(stravaResultado()['success'])->toBe(1, json_encode(session('status')));
    $depois = stravaEstado($v);
    expect($depois['final_total'])->toBe('150.0000');
    expect($depois['linhas'][0]['quantity'])->toBe('3.0000');
    expect($depois['estoque'])->toBe(7.0); // 10 − 3
});

it('UC-SEDIT-13 · NFS-e emitida trava como a NF-e (itens, valores e cliente)', function () {
    $v = stravaVenda($this, $this->bizId);
    DB::table('nfse_emissoes')->insert([
        'business_id' => $this->bizId, 'transaction_id' => $v['id'], 'competencia' => '2037-01-01',
        'tomador_nome' => 'Tomador', 'descricao' => 'Serviço', 'valor_servicos' => 100.00, 'status' => 'emitida',
        'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $estado = app(TravaDaVenda::class)->estado(\App\Transaction::findOrFail($v['id']));

    expect($estado['nota'])->toBeTrue()
        ->and($estado['itens_valores'])->toBeTrue()
        ->and($estado['cliente'])->toBeTrue()
        ->and($estado['motivo'])->toBe(TravaDaVenda::MSG_NOTA);
});
