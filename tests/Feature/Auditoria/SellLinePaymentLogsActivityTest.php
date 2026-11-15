<?php

use App\TransactionPayment;
use App\TransactionSellLine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Auditoria\Services\AuditEntryService;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\EstoqueFixture;

/**
 * US-AUDIT-004 — trait LogsActivity em App\TransactionSellLine + App\TransactionPayment.
 *
 * Valida (per SPEC + ADR 0127):
 *   - SellLine: alterar quantity / unit_price_inc_tax gera entry sales.sell_line
 *   - Payment: alterar amount / method gera entry sales.payment
 *   - Multi-tenant Tier 0: o log fica no tenant da venda, nao no da sessao (ADR 0093)
 *
 * A venda, o item e o pagamento nascem aqui (dentro da transacao): o seed do CI nao tem
 * nenhum deles, e procurar "o primeiro do tenant" fazia estes testes pularem em todo run.
 * Tenants ficticios 98 x 99 (ADR 0358), nunca biz=4.
 */

uses(DatabaseTransactions::class);

function auditVendaFixture(int $businessId): TransactionSellLine
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $venda = EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($businessId), 2.0);

    return TransactionSellLine::findOrFail($venda['sell_line_id']);
}

function auditVendaUltimoLog(string $subjectType, int $subjectId, string $logName): ?Activity
{
    return Activity::query()
        ->where('subject_type', $subjectType)
        ->where('subject_id', $subjectId)
        ->where('log_name', $logName)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — estes testes rodam na lane MySQL (auditoria-pest).');
    }

    $this->business = $this->seededTenant();
    $this->user = \App\User::where('business_id', $this->business->id)->firstOrFail();
    $this->sellLine = auditVendaFixture((int) $this->business->id);

    $this->payment = TransactionPayment::findOrFail(DB::table('transaction_payments')->insertGetId([
        'transaction_id' => $this->sellLine->transaction_id,
        'business_id'    => $this->business->id,
        'amount'         => 40.00,
        'method'         => 'cash',
        'is_return'      => 0,
        'paid_on'        => now(),
        'created_by'     => $this->user->id,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]));

    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

it('cenario 1: update TransactionSellLine.unit_price_inc_tax gera entry sales.sell_line', function () {
    $oldPrice = (float) $this->sellLine->unit_price_inc_tax;
    $newPrice = $oldPrice + 1.50;

    $this->sellLine->unit_price_inc_tax = $newPrice;
    $this->sellLine->save();

    $log = auditVendaUltimoLog(TransactionSellLine::class, $this->sellLine->id, 'sales.sell_line');

    expect($log)->not->toBeNull('SellLine update deveria gerar entry');
    expect((float) ($log->properties['old']['unit_price_inc_tax'] ?? 0))->toBe($oldPrice);
    expect((float) ($log->properties['attributes']['unit_price_inc_tax'] ?? 0))->toBe($newPrice);
});

it('cenario 2: update TransactionPayment.amount gera entry sales.payment', function () {
    $oldAmount = (float) $this->payment->amount;
    $newAmount = $oldAmount + 0.01; // delta minimo

    $this->payment->amount = $newAmount;
    $this->payment->save();

    $log = auditVendaUltimoLog(TransactionPayment::class, $this->payment->id, 'sales.payment');

    expect($log)->not->toBeNull('Payment update deveria gerar entry');
    expect((float) ($log->properties['old']['amount'] ?? 0))->toBe($oldAmount);
    expect((float) ($log->properties['attributes']['amount'] ?? 0))->toBe($newAmount);
});

it('cenario 3: multi-tenant Tier 0 — log do item fica no tenant da venda e nao aparece no outro', function () {
    $outro = $this->seededSupportClientTenant();
    $linhaOutro = auditVendaFixture((int) $outro->id);

    // A sessao continua no tenant principal: o tenant do log vem da venda do item.
    $this->sellLine->quantity = (float) $this->sellLine->quantity + 1;
    $this->sellLine->save();
    $linhaOutro->quantity = (float) $linhaOutro->quantity + 1;
    $linhaOutro->save();

    $meu = auditVendaUltimoLog(TransactionSellLine::class, $this->sellLine->id, 'sales.sell_line');
    $alheio = auditVendaUltimoLog(TransactionSellLine::class, $linhaOutro->id, 'sales.sell_line');

    expect((int) $meu->business_id)->toBe((int) $this->business->id);
    expect((int) $alheio->business_id)->toBe((int) $outro->id);

    $ids = collect(app(AuditEntryService::class)->list((int) $this->business->id, ['event' => 'updated'], 200)->items())
        ->pluck('id')->all();

    expect(in_array($meu->id, $ids, true))->toBeTrue();
    expect(in_array($alheio->id, $ids, true))->toBeFalse();
});
