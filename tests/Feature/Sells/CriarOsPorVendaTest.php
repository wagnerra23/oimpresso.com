<?php

declare(strict_types=1);

use App\Business;
use App\Services\CriarOsPorVendaService;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * US-OFICINA-OS-LINK — CriarOsPorVendaService coverage.
 *
 * Cobre:
 *   - mode='single'   → 1 OS, transaction_sell_line_id=NULL
 *   - mode='per_line' → N OS (uma por sellLine)
 *   - mode='auto'     → respeita business.os_default_per_line
 *   - Idempotência    → chamar 2× não duplica
 *   - Cross-tenant    → biz=99 não cria OS pra venda biz=1 (ADR 0093 + 0101)
 *   - Mode inválido   → throw RuntimeException
 *
 * Pattern Pest dual-mode SQLite/MySQL: skip se SQLite (schema UltimatePOS exige MySQL).
 *
 * @see app/Services/CriarOsPorVendaService.php
 * @see Modules/OficinaAuto/Database/Migrations/2026_05_12_230001_add_transaction_sell_line_id_to_service_orders.php
 */

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;
const TEST_PLATE_PREFIX = 'OSTL';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('OficinaAuto migrations não rodadas — service_orders/vehicles ausentes.');
    }
    if (! Schema::hasColumn('service_orders', 'transaction_sell_line_id')) {
        $this->markTestSkipped('Migration 2026_05_12_230001 não rodada (transaction_sell_line_id ausente).');
    }
    if (! Schema::hasColumn('business', 'os_default_per_line')) {
        $this->markTestSkipped('Migration 2026_05_12_230002 não rodada (os_default_per_line ausente).');
    }
});

/**
 * Cria veículo placeholder pra biz dado.
 */
function osTestVehicle(string $suffix, int $bizId = BIZ_WAGNER): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'  => $bizId,
        'plate'        => TEST_PLATE_PREFIX . $suffix,
        'vehicle_type' => 'automovel',
    ]);
}

/**
 * Cria Transaction sell minimal pra biz dado.
 */
function osTestTransaction(int $bizId, int $contactId = 0, string $invoiceNo = 'INV-OS-TEST'): Transaction
{
    $tx = new Transaction();
    $tx->business_id = $bizId;
    $tx->location_id = 1; // assume location 1 existe (UltimatePOS seeder)
    $tx->type = 'sell';
    $tx->status = 'final';
    $tx->payment_status = 'due';
    $tx->contact_id = $contactId ?: 1;
    $tx->invoice_no = $invoiceNo . '-' . uniqid();
    $tx->transaction_date = now();
    $tx->total_before_tax = 100;
    $tx->final_total = 100;
    $tx->created_by = 1;
    $tx->save();

    return $tx;
}

function osTestSellLine(Transaction $tx, int $productId = 1): TransactionSellLine
{
    $line = new TransactionSellLine();
    $line->transaction_id = $tx->id;
    $line->product_id = $productId;
    $line->variation_id = 1;
    $line->quantity = 1;
    $line->unit_price = 100;
    $line->unit_price_inc_tax = 100;
    $line->save();

    return $line;
}

afterEach(function () {
    // Cleanup: remove OS de teste + vehicles de teste + transactions com prefix.
    ServiceOrder::withoutGlobalScopes()
        ->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'like', TEST_PLATE_PREFIX . '%'))
        ->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'like', TEST_PLATE_PREFIX . '%')->forceDelete();
    DB::table('transaction_sell_lines')->where('transaction_id', '>', 0)
        ->whereIn('transaction_id', function ($q) {
            $q->select('id')->from('transactions')->where('invoice_no', 'like', 'INV-OS-TEST-%');
        })->delete();
    DB::table('transactions')->where('invoice_no', 'like', 'INV-OS-TEST-%')->delete();
});

// ─── mode='single' ───────────────────────────────────────────────────────────

it('mode=single cria 1 OS com transaction_sell_line_id=NULL', function () {
    session(['user.business_id' => BIZ_WAGNER]);
    osTestVehicle('S1');
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx);

    $service = app(CriarOsPorVendaService::class);
    $result = $service->criar($tx->fresh('sell_lines'), 'single');

    expect($result['created'])->toHaveCount(1);
    expect($result['mode_resolved'])->toBe('single');

    $os = $result['created']->first();
    expect($os->transaction_id)->toBe($tx->id);
    expect($os->transaction_sell_line_id)->toBeNull();
    expect($os->business_id)->toBe(BIZ_WAGNER);
    expect($os->status)->toBe('aberta');
});

// ─── mode='per_line' ─────────────────────────────────────────────────────────

it('mode=per_line cria N OS (uma por sellLine)', function () {
    session(['user.business_id' => BIZ_WAGNER]);
    osTestVehicle('PL1');
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx, 1);
    osTestSellLine($tx, 1);
    osTestSellLine($tx, 1);

    $service = app(CriarOsPorVendaService::class);
    $result = $service->criar($tx->fresh('sell_lines'), 'per_line');

    expect($result['created'])->toHaveCount(3);
    expect($result['mode_resolved'])->toBe('per_line');

    $sellLineIds = $result['created']->pluck('transaction_sell_line_id')->all();
    expect($sellLineIds)->not->toContain(null);
    expect(array_unique($sellLineIds))->toHaveCount(3);
});

// ─── mode='auto' ─────────────────────────────────────────────────────────────

it('mode=auto respeita business.os_default_per_line=false → single', function () {
    session(['user.business_id' => BIZ_WAGNER]);
    DB::table('business')->where('id', BIZ_WAGNER)->update(['os_default_per_line' => false]);

    osTestVehicle('A1');
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx);
    osTestSellLine($tx);

    $service = app(CriarOsPorVendaService::class);
    $result = $service->criar($tx->fresh('sell_lines'), 'auto');

    expect($result['mode_resolved'])->toBe('single');
    expect($result['created'])->toHaveCount(1);
});

it('mode=auto respeita business.os_default_per_line=true → per_line', function () {
    session(['user.business_id' => BIZ_WAGNER]);
    DB::table('business')->where('id', BIZ_WAGNER)->update(['os_default_per_line' => true]);

    osTestVehicle('A2');
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx);
    osTestSellLine($tx);

    $service = app(CriarOsPorVendaService::class);
    $result = $service->criar($tx->fresh('sell_lines'), 'auto');

    expect($result['mode_resolved'])->toBe('per_line');
    expect($result['created'])->toHaveCount(2);

    // Restaura default pra não vazar pra outros tests.
    DB::table('business')->where('id', BIZ_WAGNER)->update(['os_default_per_line' => false]);
});

// ─── Idempotência ────────────────────────────────────────────────────────────

it('chamar 2× em mode=single não duplica OS', function () {
    session(['user.business_id' => BIZ_WAGNER]);
    osTestVehicle('I1');
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx);

    $service = app(CriarOsPorVendaService::class);

    $first = $service->criar($tx->fresh('sell_lines'), 'single');
    expect($first['created'])->toHaveCount(1);

    $second = $service->criar($tx->fresh('sell_lines'), 'single');
    expect($second['created'])->toHaveCount(0);
    expect($second['existing'])->toHaveCount(1);
    expect($second['existing']->first()->id)->toBe($first['created']->first()->id);
});

it('chamar 2× em mode=per_line não duplica OS por linha', function () {
    session(['user.business_id' => BIZ_WAGNER]);
    osTestVehicle('I2');
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx);
    osTestSellLine($tx);

    $service = app(CriarOsPorVendaService::class);

    $first = $service->criar($tx->fresh('sell_lines'), 'per_line');
    expect($first['created'])->toHaveCount(2);

    $second = $service->criar($tx->fresh('sell_lines'), 'per_line');
    expect($second['created'])->toHaveCount(0);
    expect($second['existing'])->toHaveCount(2);
});

// ─── Cross-tenant guard ──────────────────────────────────────────────────────

it('cross-tenant: session biz=99 não cria OS pra venda biz=1 (ADR 0093 + 0101)', function () {
    // Cria venda biz=1 (sem session correspondente — simula privilege escalation attempt).
    osTestVehicle('CT1', BIZ_WAGNER);
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx);

    // Atacante seta session biz=99 e tenta criar OS pra venda biz=1.
    session(['user.business_id' => BIZ_FICTICIO]);

    $service = app(CriarOsPorVendaService::class);

    expect(fn () => $service->criar($tx->fresh('sell_lines'), 'single'))
        ->toThrow(RuntimeException::class, 'Cross-tenant violation');
});

// ─── Mode inválido ───────────────────────────────────────────────────────────

it('mode inválido lança RuntimeException', function () {
    session(['user.business_id' => BIZ_WAGNER]);
    osTestVehicle('M1');
    $tx = osTestTransaction(BIZ_WAGNER);
    osTestSellLine($tx);

    $service = app(CriarOsPorVendaService::class);

    expect(fn () => $service->criar($tx->fresh('sell_lines'), 'modo_invalido'))
        ->toThrow(RuntimeException::class, 'Modo inválido');
});

// ─── Endpoint Controller (estrutural) ────────────────────────────────────────

it('SellController@createOs existe + valida mode + permission gate', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    expect($source)->toMatch('/public function createOs\\(/');
    expect($source)->toContain("'in:auto,single,per_line'");
    expect($source)->toContain('direct_sell.view');
    expect($source)->toContain('CriarOsPorVendaService');
    expect($source)->toContain('mode_resolved');
});

it('rota POST /sells/{id}/create-os registrada', function () {
    $source = file_get_contents(base_path('routes/web.php'));
    expect($source)->toContain("/sells/{id}/create-os");
    expect($source)->toContain("'createOs'");
});
