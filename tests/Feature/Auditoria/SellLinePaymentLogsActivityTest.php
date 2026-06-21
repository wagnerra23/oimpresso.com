<?php

use App\TransactionPayment;
use App\TransactionSellLine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-004 — trait LogsActivity em App\TransactionSellLine + App\TransactionPayment.
 *
 * Valida (per SPEC + ADR 0127):
 *   - SellLine: alterar quantity / unit_price_inc_tax gera entry sales.sell_line
 *   - Payment: alterar amount / method gera entry sales.payment
 *   - Multi-tenant Tier 0 (ADR 0093)
 *
 * Skip-graceful em sqlite memory (CI). Validacao real com mysql dev pre-merge.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode local com DB_CONNECTION=mysql (dev) ou aguarde CI integration job.');
    }

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->sellLine = TransactionSellLine::query()
        ->whereHas('transaction', fn ($q) => $q->where('business_id', $this->business->id))
        ->first();

    $this->payment = TransactionPayment::query()
        ->whereHas('transaction', fn ($q) => $q->where('business_id', $this->business->id))
        ->first();

    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

it('cenario 1: update TransactionSellLine.unit_price_inc_tax gera entry sales.sell_line', function () {
    if (! $this->sellLine) {
        $this->markTestSkipped('Sem TransactionSellLine no business — rode smoke /sells/create antes.');
    }

    $oldPrice = (float) $this->sellLine->unit_price_inc_tax;
    $newPrice = $oldPrice + 1.50;

    $this->sellLine->unit_price_inc_tax = $newPrice;
    $this->sellLine->save();

    $log = Activity::query()
        ->where('subject_type', TransactionSellLine::class)
        ->where('subject_id', $this->sellLine->id)
        ->where('log_name', 'sales.sell_line')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull('SellLine update deveria gerar entry');
    $old = $log->properties['old'] ?? null;
    $new = $log->properties['attributes'] ?? null;
    expect((float) ($old['unit_price_inc_tax'] ?? 0))->toBe($oldPrice);
    expect((float) ($new['unit_price_inc_tax'] ?? 0))->toBe($newPrice);
});

it('cenario 2: update TransactionPayment.amount gera entry sales.payment', function () {
    if (! $this->payment) {
        $this->markTestSkipped('Sem TransactionPayment no business.');
    }

    $oldAmount = (float) $this->payment->amount;
    $newAmount = $oldAmount + 0.01; // delta minimo

    $this->payment->amount = $newAmount;
    $this->payment->save();

    $log = Activity::query()
        ->where('subject_type', TransactionPayment::class)
        ->where('subject_id', $this->payment->id)
        ->where('log_name', 'sales.payment')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull('Payment update deveria gerar entry');
    $old = $log->properties['old'] ?? null;
    $new = $log->properties['attributes'] ?? null;
    expect((float) ($old['amount'] ?? 0))->toBe($oldAmount);
    expect((float) ($new['amount'] ?? 0))->toBe($newAmount);
});

it('cenario 3: multi-tenant Tier 0 — SellLine activity nao vaza cross-tenant', function () {
    if (! $this->sellLine) {
        $this->markTestSkipped('Sem TransactionSellLine.');
    }

    $this->sellLine->quantity = (float) $this->sellLine->quantity + 1;
    $this->sellLine->save();

    $logsThisBiz = Activity::query()
        ->where('business_id', $this->business->id)
        ->where('subject_type', TransactionSellLine::class)
        ->where('subject_id', $this->sellLine->id)
        ->count();

    expect($logsThisBiz)->toBeGreaterThan(0);

    $logsOtherBiz = Activity::query()
        ->where('business_id', '!=', $this->business->id)
        ->where('subject_type', TransactionSellLine::class)
        ->where('subject_id', $this->sellLine->id)
        ->count();

    expect($logsOtherBiz)->toBe(0, 'activity nao deve vazar pra outro business_id (Tier 0)');
});
