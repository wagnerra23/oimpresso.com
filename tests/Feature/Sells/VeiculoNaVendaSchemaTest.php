<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (FV-B4).

/**
 * ADR 0251 — Veículo na venda direta de oficina (transactions.vehicle_id).
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 * Multi-tenant Tier 0 (ADR 0093): Vehicle tem global scope por business_id.
 *
 * Schema UltimatePOS completo só existe no MySQL (CT 100 oficina-staging) — o
 * lane SQLite do CI pula (mesmo padrão de VehicleCrudTest).
 *
 * @see memory/decisions/0251-veiculo-na-venda-direta-oficina.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

defined('BIZ_WAGNER') || define('BIZ_WAGNER', 1);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema transactions/vehicles requer MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('schema base ausente — rode as migrations UltimatePOS + OficinaAuto');
    }
});

it('migration adicionou transactions.vehicle_id (ADR 0251)', function () {
    expect(Schema::hasColumn('transactions', 'vehicle_id'))->toBeTrue();
});

it('transactions.vehicle_id é nullable — venda sem veículo é o caso comum (vestuário)', function () {
    $col = collect(DB::select('SHOW COLUMNS FROM transactions WHERE Field = ?', ['vehicle_id']))->first();
    expect($col)->not->toBeNull();
    expect($col->Null)->toBe('YES');
});

it('existe FK fk_transactions_vehicle pra vehicles', function () {
    $createSql = (string) (collect(DB::select('SHOW CREATE TABLE transactions'))->first()->{'Create Table'} ?? '');
    expect($createSql)->toContain('fk_transactions_vehicle');
});

it('Vehicle do biz=1 não vaza pro escopo de outro business (Tier 0 ADR 0093)', function () {
    session(['user.business_id' => BIZ_WAGNER]);

    $meu = Vehicle::create([
        'business_id'  => BIZ_WAGNER,
        'plate'        => 'VND0251',
        'vehicle_type' => 'automovel',
    ]);

    // Cria veículo de OUTRO business (99) bypassa scope só pra inserir.
    $outro = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => 99,
        'plate'        => 'OTR0251',
        'vehicle_type' => 'automovel',
    ]);

    // Com a sessão biz=1, o global scope só enxerga o veículo do biz=1.
    $visiveis = Vehicle::pluck('plate')->all();
    expect($visiveis)->toContain('VND0251');
    expect($visiveis)->not->toContain('OTR0251');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->whereIn('plate', ['VND0251', 'OTR0251'])->forceDelete();
});
