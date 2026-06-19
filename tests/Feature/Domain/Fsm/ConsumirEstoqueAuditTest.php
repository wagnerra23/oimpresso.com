<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Fsm\SideEffects\ConsumirEstoque;
use App\Product;
use App\Transaction;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (FV-B4).
uses(DatabaseTransactions::class);

/**
 * R1 (DOC-RAIZ-ESTOQUE §8) — ConsumirEstoque (FSM) deve baixar `qty_available` por um
 * caminho AUDITÁVEL. Antes do fix, usava `DB::table(...)->update()` que bypassa o
 * LogsActivity → o consumo de reserva NÃO aparecia na trilha `inventory.stock`.
 *
 * Este teste prova INV-1: ao consumir uma reserva, o saldo baixa E gera entry
 * `activity_log` com log_name='inventory.stock' (mesma trilha do ProductUtil).
 *
 * Ambiente: requer schema UltimatePOS real (MySQL) — o fix usa modelo Eloquent só
 * quando `activity_log` existe; em sqlite cai no fallback DB::table (sem audit). Por isso
 * este teste faz skip gracioso fora do MySQL. Rodar no CT 100:
 *   php artisan test --filter=ConsumirEstoqueAudit
 *
 * @see app/Domain/Fsm/SideEffects/ConsumirEstoque.php
 * @see app/VariationLocationDetails.php (LogsActivity 'inventory.stock')
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('R1 audit-path só vale em MySQL (sqlite usa fallback DB::table sem activity_log).');
    }
    foreach (['variation_location_details', 'products', 'activity_log', 'stock_reservations', 'transactions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente: {$t}.");
        }
    }
});

it('ConsumirEstoque baixa qty_available E grava trilha activity_log inventory.stock (R1/INV-1)', function () {
    $vld = VariationLocationDetails::query()
        ->whereIn('product_id', Product::withoutGlobalScopes()->where('enable_stock', 1)->pluck('id'))
        ->where('qty_available', '>=', 2)
        ->first();

    if ($vld === null) {
        $this->markTestSkipped('Sem VLD stock-managed com saldo >= 2 pra exercitar consumo.');
    }

    $product = Product::withoutGlobalScopes()->find($vld->product_id);
    $biz = (int) $product->business_id;

    // Subject FSM: qualquer Eloquent com business_id + getKey(). Transaction real do biz serve.
    $tx = Transaction::withoutGlobalScopes()->where('business_id', $biz)->first();
    if ($tx === null) {
        $this->markTestSkipped('Sem transaction no business pra servir de subject.');
    }

    session(['user.business_id' => $biz]);

    StockReservation::create([
        'business_id'    => $biz,
        'transaction_id' => $tx->getKey(),
        'product_id'     => $product->id,
        'variation_id'   => $vld->variation_id,
        'location_id'    => $vld->location_id,
        'qty_reserved'   => 1.0,
        'status'         => StockReservation::STATUS_ACTIVE,
    ]);

    $antes = (float) $vld->qty_available;

    (new ConsumirEstoque)->execute($tx);

    // 1. Saldo baixou exatamente a quantidade reservada.
    expect(round((float) $vld->fresh()->qty_available, 4))->toBe(round($antes - 1.0, 4));

    // 2. Trilha de auditoria gerada (antes do fix: NÃO existia).
    $log = Activity::query()
        ->where('subject_type', VariationLocationDetails::class)
        ->where('subject_id', $vld->id)
        ->where('log_name', 'inventory.stock')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect((float) ($log->properties['attributes']['qty_available'] ?? -1))
        ->toBe(round($antes - 1.0, 4));
});
