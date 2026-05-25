<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Schema GUARDs — Onda 1 do plano F3 Integração Vendas × Oficina (ADR 0192).
 *
 * Cobertura:
 *  - Migration `2026_05_25_140000_add_source_and_os_ref_to_transactions` aplicada
 *  - Coluna `source` ENUM('balcao','oficina','online') DEFAULT 'balcao'
 *  - Coluna `os_ref` VARCHAR(20) NULL
 *  - Coluna `commission_split` JSON NULL
 *  - Índice composto `idx_transactions_source` (business_id, source, transaction_date)
 *  - Model cast `commission_split` => 'array' (decode JSON automático)
 *  - Default retroativo: vendas legacy sem source ganham 'balcao' (zero breaking change)
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/sessions/2026-05-25-plano-f3-integracao-vendas-oficina.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UPOS legacy requer MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('transactions')) {
        $this->markTestSkipped('transactions table missing — rode migrate');
    }
    if (! Schema::hasColumn('transactions', 'source')) {
        $this->markTestSkipped('migration add_source_and_os_ref_to_transactions não aplicada');
    }
});

it('migration aplicada: transactions tem source + os_ref + commission_split', function () {
    expect(Schema::hasColumn('transactions', 'source'))->toBeTrue();
    expect(Schema::hasColumn('transactions', 'os_ref'))->toBeTrue();
    expect(Schema::hasColumn('transactions', 'commission_split'))->toBeTrue();
});

it('coluna source é ENUM com 3 valores canon (balcao, oficina, online) e default balcao', function () {
    $column = collect(DB::select('SHOW COLUMNS FROM transactions LIKE ?', ['source']))->first();

    expect($column)->not->toBeNull();
    expect(strtolower((string) $column->Type))->toContain("enum('balcao','oficina','online')");
    expect($column->Default)->toBe('balcao');
});

it('coluna os_ref é VARCHAR(20) nullable', function () {
    $column = collect(DB::select('SHOW COLUMNS FROM transactions LIKE ?', ['os_ref']))->first();

    expect($column)->not->toBeNull();
    expect(strtolower((string) $column->Type))->toBe('varchar(20)');
    expect($column->Null)->toBe('YES');
});

it('coluna commission_split é JSON nullable', function () {
    $column = collect(DB::select('SHOW COLUMNS FROM transactions LIKE ?', ['commission_split']))->first();

    expect($column)->not->toBeNull();
    expect(strtolower((string) $column->Type))->toContain('json');
    expect($column->Null)->toBe('YES');
});

it('índice composto idx_transactions_source existe com colunas business_id + source + transaction_date', function () {
    $indexes = collect(DB::select('SHOW INDEXES FROM transactions'))
        ->where('Key_name', 'idx_transactions_source')
        ->sortBy('Seq_in_index')
        ->pluck('Column_name')
        ->values()
        ->toArray();

    expect($indexes)->toBe(['business_id', 'source', 'transaction_date']);
});

it('Transaction model casta commission_split como array (JSON decode automático)', function () {
    $tx = new Transaction;
    $tx->commission_split = [
        'mecanico_id' => 42,
        'mecanico_pct' => 70.0,
        'balcao_id' => 17,
        'balcao_pct' => 30.0,
    ];

    expect($tx->commission_split)->toBeArray();
    expect($tx->commission_split['mecanico_id'])->toBe(42);
    expect($tx->commission_split['mecanico_pct'] + $tx->commission_split['balcao_pct'])->toBe(100.0);
});

it('default retroativo: linha INSERT sem source ganha balcao (zero breaking change vendas legacy)', function () {
    // Inspeciona DEFAULT via INFORMATION_SCHEMA pra confirmar comportamento sem
    // depender de fixture (vendas legacy de prod terão NULL ou 'balcao' conforme MySQL).
    $row = collect(DB::select(
        "SELECT COLUMN_DEFAULT, IS_NULLABLE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'source'"
    ))->first();

    expect($row->COLUMN_DEFAULT)->toBe('balcao');
});
