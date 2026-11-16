<?php

declare(strict_types=1);
// Cobre UC-PIDX-50 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * D-30 (decisão [F] 2026-11-12), a parte da venda: o kit segue "Permitir vender sem estoque" como qualquer
 * produto. Desligada, a venda não passa de quantos kits os componentes permitem; ligada, passa e os
 * componentes ficam negativos. E o kit não tem estoque próprio (aprovado pelo [F] em 2026-11-16): mesmo
 * com "Controlar estoque" ligado no cadastro, a venda não casa a linha do kit com compra nem mexe no
 * saldo do kit, e excluir a venda devolve só os componentes (Util::temSaldoProprio).
 *
 * A venda é a do Blade (`sale_pos/product_row.blade.php` manda a linha do kit com `product_type=combo` e a
 * lista `combo` dos componentes), gravada por `/pos` (SellPosController@store, o caminho de produção). A
 * venda React (Create/CreateV3) ainda não monta o kit (backlog P-066, Sells/Create.casos.md).
 *
 * Dupla prova da REGRA MESTRE (estoque): o kit é 2 A + 1 B, com A = 6 e B = 5 em estoque e o mesmo lastro
 * de compra.
 *   Caminho 1 — conta à mão: kits possíveis = min(6 ÷ 2, 5 ÷ 1) = 3. Vender 3 baixa A para 0 e B para 2.
 *   Caminho 2 — ProductUtil::calculateComboQuantity (a conta que a venda Blade usa para o saldo do kit)
 *               dá os mesmos 3 antes da venda.
 *
 * Setup copiado de SellsCreateV3GravarContratoTest.php (lastro de compra, sessão com o modelo Business),
 * com outro prefixo: os arquivos rodam no mesmo processo da lane. Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

/** Componente com $qtd em estoque e o mesmo lastro de compra (sem lastro o store recusa por PurchaseSellMismatch). */
function kitvComponente(object $test, float $qtd): object
{
    $produto = EstoqueFixture::singleProduct($test->bizId);
    EstoqueFixture::setStock($produto, 0, $test->locationId, $qtd);
    $compraId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $test->bizId, 'type' => 'purchase', 'status' => 'received',
        'location_id' => $test->locationId, 'payment_status' => 'paid',
        'transaction_date' => now()->subDay(), 'total_before_tax' => 0, 'final_total' => 0,
        'created_by' => $test->user->id, 'essentials_duration' => 0,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId, 'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => $qtd,
        'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
        'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);

    return $produto;
}

/** Kit = 2 A + 1 B. `$controla` = "Controlar estoque" do próprio kit (o cadastro vem com ele ligado). */
function kitvKit(object $test, object $a, object $b, bool $controla): object
{
    $kit = EstoqueFixture::singleProduct($test->bizId, $controla);
    DB::table('products')->where('id', $kit->productId)->update(['type' => 'combo']);
    DB::table('variations')->where('id', $kit->variationId())->update([
        'combo_variations' => json_encode([
            ['variation_id' => $a->variationId(), 'quantity' => 2, 'unit_id' => $a->unitId],
            ['variation_id' => $b->variationId(), 'quantity' => 1, 'unit_id' => $b->unitId],
        ]),
    ]);

    return $kit;
}

/** A recusa é a de estoque (PurchaseSellMismatch), que cita o componente — não qualquer erro. */
function kitvRecusaPorEstoque(string $msg, object $a, object $b): bool
{
    foreach ([$a, $b] as $p) {
        $nome = (string) DB::table('products')->where('id', $p->productId)->value('name');
        if ($nome !== '' && str_contains($msg, $nome)) {
            return true;
        }
    }

    return false;
}

function kitvVenderSemEstoque(object $test, bool $liga): void
{
    $atual = json_decode((string) DB::table('business')->where('id', $test->bizId)->value('pos_settings'), true) ?: [];
    $atual['allow_overselling'] = $liga ? 1 : 0;
    DB::table('business')->where('id', $test->bizId)->update(['pos_settings' => json_encode($atual)]);
}

/**
 * O saldo do próprio kit no local e as linhas do kit casadas com compra. Os dois ficam em zero: o kit não
 * tem estoque próprio (D-30).
 *
 * @return array{saldo: float, casadas: int}
 */
function kitvSaldoDoKit(object $test, object $kit): array
{
    return [
        'saldo' => EstoqueFixture::currentStock($kit, 0, $test->locationId),
        'casadas' => DB::table('transaction_sell_lines_purchase_lines as m')
            ->join('transaction_sell_lines as l', 'l.id', '=', 'm.sell_line_id')
            ->join('transactions as t', 't.id', '=', 'l.transaction_id')
            ->where('t.business_id', $test->bizId)
            ->where('l.product_id', $kit->productId)
            ->count(),
    ];
}

/** A venda gravada com o kit, a mais recente da empresa do teste. */
function kitvVendaDoKit(object $test, object $kit): int
{
    return (int) DB::table('transactions')
        ->join('transaction_sell_lines', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
        ->where('transactions.business_id', $test->bizId)
        ->where('transaction_sell_lines.product_id', $kit->productId)
        ->orderByDesc('transactions.id')
        ->value('transactions.id');
}

/**
 * Posta a venda de $kits kits como a linha do Blade a monta. Devolve se gravou, a mensagem do store e os
 * saldos de A e B depois.
 *
 * @return array{gravou: bool, msg: string, saldos: array{0: float, 1: float}}
 */
function kitvVender(object $test, object $kit, object $a, object $b, int $kits, bool $kitControla): array
{
    $total = 10.0 * $kits;
    $pagamento = [['amount' => $total, 'method' => 'cash', 'paid_on' => '', 'account_id' => null, 'note' => '']];
    // O que o servidor registrar como erro vai para a mensagem do teste: sem isso uma recusa por payload
    // incompleto parece recusa por estoque.
    $erros = [];
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($e) use (&$erros) {
        if (in_array($e->level, ['emergency', 'alert', 'critical', 'error'], true)) {
            $erros[] = mb_substr((string) $e->message, 0, 300);
        }
    });
    // As chaves do payload da venda (as mesmas do SellsCreateV3GravarContratoTest): o store lê algumas delas
    // (tax_rate_id, rp_redeemed…) antes de abrir a transação, e a falta de uma desfaz até o setup do teste.
    $response = $test->post('/pos', [
        'location_id' => $test->locationId, 'contact_id' => $test->contactId,
        'transaction_date' => now()->format('d/m/Y H:i'), 'status' => 'final',
        'invoice_scheme_id' => null, 'invoice_no' => '', 'pay_term_number' => '', 'pay_term_type' => 'days',
        'sinal_minimo_pct' => null, 'price_group_id' => null, 'commission_agent_id' => null,
        'tax_rate_id' => null, 'vehicle_id' => null,
        'products' => [[
            'product_id' => $kit->productId, 'variation_id' => $kit->variationId(),
            'name' => 'kit', 'variation' => null, 'sku' => 'kit',
            'quantity' => $kits, 'unit_price' => 10, 'discount' => 0, 'discount_type' => 'percentage',
            'imei_number' => '', 'unit_price_inc_tax' => 10, 'item_tax' => 0, 'tax_id' => null,
            'line_discount_type' => 'percentage', 'line_discount_amount' => 0,
            'enable_stock' => $kitControla ? 1 : 0, 'product_type' => 'combo',
            // A lista que o product_row.blade.php manda: a quantidade de cada componente já multiplicada pelos kits.
            'combo' => [
                ['product_id' => $a->productId, 'variation_id' => $a->variationId(), 'quantity' => 2 * $kits],
                ['product_id' => $b->productId, 'variation_id' => $b->variationId(), 'quantity' => 1 * $kits],
            ],
        ]],
        'payments' => $pagamento, 'payment' => $pagamento,
        'discount_type' => 'fixed', 'discount_amount' => 0,
        'rp_redeemed' => 0, 'rp_redeemed_amount' => '0.00', 'notes' => '', 'staff_note' => '',
        'is_recurring' => 0, 'customer_secondary_address' => '', 'sell_document' => null,
        'shipping' => ['details' => '', 'address' => '', 'cost' => 0, 'status' => '', 'deliver_to' => ''],
        'is_direct_sale' => 1, 'is_save_and_print' => 0,
        'commission_agent' => null, 'price_group' => null, 'default_price_group' => null,
        'sale_note' => '', 'additional_notes' => '',
        'shipping_details' => '', 'shipping_address' => '', 'shipping_charges' => 0,
        'shipping_status' => '', 'delivered_to' => '',
        'additional_expense_key_1' => '', 'additional_expense_value_1' => 0,
        'final_total' => $total,
    ]);

    $gravou = DB::table('transaction_sell_lines')
        ->join('transactions', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
        ->where('transactions.business_id', $test->bizId)
        ->where('transaction_sell_lines.product_id', $kit->productId)
        ->exists();
    $status = session('status');

    return [
        'gravou' => $gravou,
        'msg' => (is_array($status) ? (string) ($status['msg'] ?? '') : ('http ' . $response->status())) . ($erros ? ' · log: ' . implode(' | ', $erros) : ''),
        'saldos' => [EstoqueFixture::currentStock($a, 0, $test->locationId), EstoqueFixture::currentStock($b, 0, $test->locationId)],
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

    $this->a = kitvComponente($this, 6);
    $this->b = kitvComponente($this, 5);
});

it('UC-PIDX-50 · sem vender sem estoque: 3 kits gravam e baixam os componentes; 4 kits não gravam e o estoque fica como estava', function () {
    kitvVenderSemEstoque($this, false);
    $kit = kitvKit($this, $this->a, $this->b, false);

    // Caminho 2 — a conta que a venda Blade usa para o saldo do kit, antes de vender.
    $combo = json_decode((string) DB::table('variations')->where('id', $kit->variationId())->value('combo_variations'), true);
    expect((int) app(\App\Utils\ProductUtil::class)->calculateComboQuantity($this->locationId, $combo))->toBe(3);

    $quatro = kitvVender($this, $kit, $this->a, $this->b, 4, false);
    expect($quatro['gravou'])->toBeFalse('o store gravou 4 kits sem vender sem estoque');
    expect(kitvRecusaPorEstoque($quatro['msg'], $this->a, $this->b))->toBeTrue('a recusa não foi por estoque: ' . $quatro['msg']);
    expect($quatro['saldos'])->toBe([6.0, 5.0]);

    // Caminho 1 — à mão: 3 kits = 6 de A e 3 de B.
    $tres = kitvVender($this, $kit, $this->a, $this->b, 3, false);
    expect($tres['gravou'])->toBeTrue('o store recusou 3 kits: ' . $tres['msg']);
    expect($tres['saldos'])->toBe([0.0, 2.0]);
});

it('UC-PIDX-50 · com vender sem estoque: 4 kits gravam e os componentes ficam negativos', function () {
    kitvVenderSemEstoque($this, true);
    $kit = kitvKit($this, $this->a, $this->b, false);

    $quatro = kitvVender($this, $kit, $this->a, $this->b, 4, false);
    expect($quatro['gravou'])->toBeTrue('o store recusou 4 kits com vender sem estoque: ' . $quatro['msg']);
    // À mão: A 6 − 8 = −2; B 5 − 4 = 1.
    expect($quatro['saldos'])->toBe([-2.0, 1.0]);
});

it('UC-PIDX-50 · o kit com "Controlar estoque" ligado (o padrão do cadastro) segue a mesma regra e não tem saldo próprio', function () {
    kitvVenderSemEstoque($this, false);
    $kit = kitvKit($this, $this->a, $this->b, true);

    $quatro = kitvVender($this, $kit, $this->a, $this->b, 4, true);
    expect($quatro['gravou'])->toBeFalse('o store gravou 4 kits sem vender sem estoque');
    // A recusa cita o componente que falta. Antes da correção ela citava o próprio kit, que pedia compra do kit.
    expect(kitvRecusaPorEstoque($quatro['msg'], $this->a, $this->b))->toBeTrue('a recusa não foi pelo componente: ' . $quatro['msg']);
    expect($quatro['saldos'])->toBe([6.0, 5.0]);

    $tres = kitvVender($this, $kit, $this->a, $this->b, 3, true);
    expect($tres['gravou'])->toBeTrue('o store recusou 3 kits: ' . $tres['msg']);
    expect($tres['saldos'])->toBe([0.0, 2.0]);
    expect(kitvSaldoDoKit($this, $kit))->toBe(['saldo' => 0.0, 'casadas' => 0]);
});

it('UC-PIDX-50 · kit com "Controlar estoque" e vender sem estoque: o kit não fica negativo, e excluir a venda devolve só os componentes', function () {
    kitvVenderSemEstoque($this, true);
    $kit = kitvKit($this, $this->a, $this->b, true);

    $quatro = kitvVender($this, $kit, $this->a, $this->b, 4, true);
    expect($quatro['gravou'])->toBeTrue('o store recusou 4 kits com vender sem estoque: ' . $quatro['msg']);
    // Caminho 1 — à mão: A 6 − 8 = −2; B 5 − 4 = 1; o kit fica em 0 (antes da correção ia a −4).
    expect($quatro['saldos'])->toBe([-2.0, 1.0]);
    expect(kitvSaldoDoKit($this, $kit))->toBe(['saldo' => 0.0, 'casadas' => 0]);
    // Caminho 2 — o saldo do kit lido por outra via: nenhuma linha do kit com saldo em variation_location_details.
    expect(DB::table('variation_location_details')->where('product_id', $kit->productId)->where('qty_available', '<>', 0)->count())->toBe(0);

    // Excluir a venda passa por TransactionUtil::adjustQuantity: os componentes voltam e o kit segue em 0.
    $saida = app(\App\Utils\TransactionUtil::class)->deleteSale($this->bizId, kitvVendaDoKit($this, $kit));
    expect($saida['success'] ?? null)->toBeTrue((string) ($saida['msg'] ?? ''));
    expect([
        EstoqueFixture::currentStock($this->a, 0, $this->locationId),
        EstoqueFixture::currentStock($this->b, 0, $this->locationId),
    ])->toBe([6.0, 5.0]);
    expect(kitvSaldoDoKit($this, $kit)['saldo'])->toBe(0.0);
});
