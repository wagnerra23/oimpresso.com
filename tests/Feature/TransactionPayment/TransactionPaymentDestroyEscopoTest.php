<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Tier 0 (ADR 0093) do `DELETE /payments/{id}` (`TransactionPaymentController@destroy`).
 *
 * O `destroy()` buscava o pagamento por `findOrFail($id)` sem conferir a empresa e apagava:
 * pagamento de venda (recalcula o status da venda alheia) e adiantamento (desconta o saldo do
 * cliente alheio e apaga os filhos). Mesma família do UC-TPEDT-07 do `update()`, eixo apagar.
 *
 * O `destroy()` só age dentro de `request()->ajax()`: sem `X-Requested-With` o teste passaria
 * por não-execução. O controle positivo (apagar o próprio) prova que a requisição chega lá.
 *
 * ⛔ Tenant 98 × o 2º business semeado. NUNCA biz=4. Valores sintéticos.
 */
uses(DatabaseTransactions::class);

function tpDelVenda(int $bizId): array
{
    $userId = EstoqueFixture::userId($bizId);
    $txId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => DB::table('contacts')->where('business_id', $bizId)->orderBy('id')->value('id'),
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'final_total' => 100.00, 'total_before_tax' => 100.00, 'ref_no' => 'TPDEL-'.uniqid(),
        'transaction_date' => now(), 'created_by' => $userId, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $payId = (int) DB::table('transaction_payments')->insertGetId([
        'business_id' => $bizId, 'transaction_id' => $txId, 'amount' => 100.00, 'method' => 'cash',
        'paid_on' => '2026-10-01 08:00:00', 'created_by' => $userId,
        'payment_ref_no' => 'TPDEL-PAY-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['tx_id' => $txId, 'payment_id' => $payId];
}

/** Adiantamento (sem venda) do primeiro contato do business. */
function tpDelAdiantamento(int $bizId): array
{
    $contactId = (int) DB::table('contacts')->where('business_id', $bizId)->orderBy('id')->value('id');
    $payId = (int) DB::table('transaction_payments')->insertGetId([
        'business_id' => $bizId, 'transaction_id' => null, 'amount' => 50.00, 'method' => 'advance',
        'payment_for' => $contactId, 'paid_on' => '2026-10-01 08:00:00',
        'created_by' => EstoqueFixture::userId($bizId), 'payment_ref_no' => 'TPDEL-ADV-'.uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['contact_id' => $contactId, 'payment_id' => $payId];
}

function tpDelApagar($test, int $paymentId)
{
    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->delete('/payments/'.$paymentId);
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

    // Sem roles semeadas na lane: a permissão entra direto no user. O cache é limpo ANTES:
    // no CT 100 ele persiste entre runs e devolve id de permissão que o rollback já apagou.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('delete_sell_payment', 'web');
    $this->user->givePermissionTo('delete_sell_payment');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs(User::findOrFail($this->user->id));
    session([
        'user.business_id' => $this->bizId, 'user.id' => $this->user->id,
        'business.id' => $this->bizId,
        'business.enabled_modules' => (array) json_decode(
            (string) DB::table('business')->where('id', $this->bizId)->value('enabled_modules'), true
        ),
    ]);
});

it('apaga o pagamento da própria empresa (controle: a requisição chega ao destroy)', function () {
    $proprio = tpDelVenda($this->bizId);

    $r = tpDelApagar($this, $proprio['payment_id']);

    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(DB::table('transaction_payments')->where('id', $proprio['payment_id'])->exists())->toBeFalse();
});

it('não apaga pagamento de venda de outra empresa (404, linha e venda intactas)', function () {
    $alheio = tpDelVenda($this->outroBiz);

    tpDelApagar($this, $alheio['payment_id'])->assertNotFound();

    expect(DB::table('transaction_payments')->where('id', $alheio['payment_id'])->exists())->toBeTrue();
    expect(DB::table('transactions')->where('id', $alheio['tx_id'])->value('payment_status'))->toBe('paid');
});

it('não apaga adiantamento de outra empresa nem mexe no saldo do cliente dela', function () {
    $alheio = tpDelAdiantamento($this->outroBiz);
    $saldoAntes = (string) DB::table('contacts')->where('id', $alheio['contact_id'])->value('balance');

    tpDelApagar($this, $alheio['payment_id'])->assertNotFound();

    expect(DB::table('transaction_payments')->where('id', $alheio['payment_id'])->exists())->toBeTrue();
    expect((string) DB::table('contacts')->where('id', $alheio['contact_id'])->value('balance'))->toBe($saldoAntes);
});
