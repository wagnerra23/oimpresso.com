<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Tier 0 (ADR 0093) do `GET /payments/view-payment/{id}` (`TransactionPaymentController@viewPayment`).
 *
 * O `viewPayment()` buscava o pagamento por `findOrFail($id)` e a venda dele por id, os dois
 * sem conferir a empresa, e renderizava o modal com cliente e local da venda. Mesma família do
 * `update()` (#9374) e do `destroy()` (#9379), eixo LER.
 *
 * Só responde dentro de `request()->ajax()`: sem `X-Requested-With` o teste passaria por
 * não-execução. O controle (ver o próprio) prova que o modal renderiza neste ambiente, para que
 * o 404 alheio não seja confundido com erro de render.
 *
 * ⛔ Tenant 98 × o 2º business semeado. NUNCA biz=4. Valores sintéticos.
 */
uses(DatabaseTransactions::class);

function tpViewVenda(int $bizId): array
{
    $userId = EstoqueFixture::userId($bizId);
    $txId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => DB::table('contacts')->where('business_id', $bizId)->orderBy('id')->value('id'),
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'final_total' => 100.00, 'total_before_tax' => 100.00, 'ref_no' => 'TPVIEW-'.uniqid(),
        'transaction_date' => now(), 'created_by' => $userId, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $ref = 'TPVIEW-PAY-'.uniqid();
    $payId = (int) DB::table('transaction_payments')->insertGetId([
        'business_id' => $bizId, 'transaction_id' => $txId, 'amount' => 100.00, 'method' => 'cash',
        'paid_on' => '2026-10-01 08:00:00', 'created_by' => $userId,
        'payment_ref_no' => $ref, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['tx_id' => $txId, 'payment_id' => $payId, 'ref' => $ref];
}

function tpViewAbrir($test, int $paymentId)
{
    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/payments/view-payment/'.$paymentId);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_payments', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}).");
        }
    }

    $this->bizId = EstoqueFixture::businessId();
    $this->outroBiz = EstoqueFixture::secondBusinessId();
    if ($this->outroBiz === null) {
        $this->markTestSkipped('Só há 1 business semeado — sem adversário cross-tenant.');
    }
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    // Cache limpo ANTES: no CT 100 ele persiste entre runs e devolve id que o rollback apagou.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('sell.payments', 'web');
    $this->user->givePermissionTo('sell.payments');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))
        ->first();
    $this->actingAs(User::findOrFail($this->user->id));
    session([
        'user.business_id' => $this->bizId, 'user.id' => $this->user->id,
        'business.id' => $this->bizId,
        'business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'business.enabled_modules' => (array) json_decode(
            (string) DB::table('business')->where('id', $this->bizId)->value('enabled_modules'), true
        ),
        'currency' => [
            'id' => (int) ($currency->id ?? 1), 'code' => (string) ($currency->code ?? 'BRL'),
            'symbol' => (string) ($currency->symbol ?? 'R$'),
            'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
            'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
        ],
    ]);
});

it('abre o pagamento da própria empresa (controle: o modal renderiza)', function () {
    $proprio = tpViewVenda($this->bizId);

    $r = tpViewAbrir($this, $proprio['payment_id']);

    $r->assertOk();
    expect($r->getContent())->toContain($proprio['ref']);
});

it('não abre pagamento de outra empresa (404, sem os dados dela na resposta)', function () {
    $alheio = tpViewVenda($this->outroBiz);

    $r = tpViewAbrir($this, $alheio['payment_id']);

    $r->assertNotFound();
    expect(str_contains((string) $r->getContent(), $alheio['ref']))->toBeFalse();
});
