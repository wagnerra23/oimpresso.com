<?php

use App\Product;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-002 — trait LogsActivity em App\Product + App\VariationLocationDetails.
 *
 * Valida (per SPEC + ADR 0127):
 *   - Product: alterar sell_price_inc_tax / sku / name / enable_stock gera entry
 *     em activity_log com log_name='inventory.product' e diff old/new
 *   - VariationLocationDetails: alterar qty_available gera entry com
 *     log_name='inventory.stock' (ajuste de estoque rastreavel)
 *   - Multi-tenant Tier 0: queries scoped por business_id (ADR 0093)
 *
 * Ambiente: requer schema UltimatePOS real (mysql dev). Em sqlite :memory:
 * (CI default phpunit.xml) faz markTestSkipped graceful — padrao do projeto.
 *
 * Refs: ADR 0127 §F1 padronizacao, ADR 0093 Tier 0, ADR 0101 smoke biz=1
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

    $this->product = Product::where('business_id', $this->business->id)->first();
    if (! $this->product) {
        $this->markTestSkipped('Sem product no business.');
    }

    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

it('cenario 1: update Product.sell_price_inc_tax gera activity_log entry com log_name=inventory.product', function () {
    $oldPrice = (float) $this->product->sell_price_inc_tax;
    $newPrice = $oldPrice + 10.00;

    $this->product->sell_price_inc_tax = $newPrice;
    $this->product->save();

    $log = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $this->product->id)
        ->where('log_name', 'inventory.product')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull('LogsActivity deveria ter gravado entry on update');
    expect($log->business_id)->toBe($this->business->id);

    $old = $log->properties['old'] ?? null;
    $new = $log->properties['attributes'] ?? null;
    expect($old)->not->toBeNull('properties.old deve estar populado em update (logOnlyDirty)');
    expect((float) ($old['sell_price_inc_tax'] ?? 0))->toBe($oldPrice);
    expect((float) ($new['sell_price_inc_tax'] ?? 0))->toBe($newPrice);
});

it('cenario 2: update Product em campo NAO logado (image) NAO gera entry duplicado', function () {
    $countBefore = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $this->product->id)
        ->count();

    // 'image' NAO esta no logOnly — alteracao nao deve criar entry
    $this->product->image = 'test-img-'.uniqid().'.png';
    $this->product->save();

    $countAfter = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $this->product->id)
        ->count();

    expect($countAfter)->toBe($countBefore, 'campo fora do logOnly nao deve gerar nova entry');
});

it('cenario 3: update VariationLocationDetails.qty_available gera entry com log_name=inventory.stock', function () {
    $vld = VariationLocationDetails::where('product_id', $this->product->id)
        ->first();
    if (! $vld) {
        $this->markTestSkipped('Sem variation_location_details pro product piloto.');
    }

    $oldQty = (float) $vld->qty_available;
    $newQty = $oldQty + 5.0;

    $vld->qty_available = $newQty;
    $vld->save();

    $log = Activity::query()
        ->where('subject_type', VariationLocationDetails::class)
        ->where('subject_id', $vld->id)
        ->where('log_name', 'inventory.stock')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull('VLD update deveria gerar entry inventory.stock');
    $old = $log->properties['old'] ?? null;
    $new = $log->properties['attributes'] ?? null;
    expect((float) ($old['qty_available'] ?? 0))->toBe($oldQty);
    expect((float) ($new['qty_available'] ?? 0))->toBe($newQty);
});

it('cenario 4: multi-tenant Tier 0 — Product activity nao vaza cross-tenant', function () {
    $oldPrice = (float) $this->product->sell_price_inc_tax;
    $this->product->sell_price_inc_tax = $oldPrice + 1.00;
    $this->product->save();

    $logsThisBiz = Activity::query()
        ->where('business_id', $this->business->id)
        ->where('subject_type', Product::class)
        ->where('subject_id', $this->product->id)
        ->count();

    expect($logsThisBiz)->toBeGreaterThan(0);

    $logsOtherBiz = Activity::query()
        ->where('business_id', '!=', $this->business->id)
        ->where('subject_type', Product::class)
        ->where('subject_id', $this->product->id)
        ->count();

    expect($logsOtherBiz)->toBe(0, 'activity nao deve vazar pra outro business_id (Tier 0)');
});
