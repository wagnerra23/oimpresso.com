<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\JournalEntry;
use Modules\Accounting\Services\JournalEntryService;

uses(Tests\TestCase::class);

/**
 * Smoke do JournalEntryService — extração thin do JournalEntryController (Wave J D4.a).
 *
 * 3 cenários:
 *  1. criar entrada balanceada (débito + crédito) com mesmo transaction_number
 *  2. reverter marca original reversed=1 e cria contrapartida
 *  3. isolamento multi-tenant — biz=1 não enxerga lançamentos biz=99 (ADR 0093/0101)
 *
 * Schema UltimatePOS exige seeders + plano-de-contas — SQLite guard pula em CI/local rápido.
 *
 * @see Modules/Accounting/Services/JournalEntryService.php
 * @see memory/requisitos/Accounting/BRIEFING.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: JournalEntry/ChartOfAccount UltimatePOS precisam schema MySQL + seeders (ADR 0101)'
        );
    }

    foreach (['business', 'business_locations', 'chart_of_accounts', 'journal_entries', 'payment_details', 'payment_types'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela `{$tbl}` missing — rode migrate UltimatePOS Accounting primeiro");
        }
    }
});

test('criar entrada balanceada gera débito e crédito com mesmo transaction_number', function () {
    $businessId = 1; // biz=1 NUNCA biz=4 cliente (ADR 0101)

    $location = DB::table('business_locations')->where('business_id', $businessId)->first();
    if (! $location) {
        $this->markTestSkipped('Sem business_location pra biz=1 — seeder Accounting não rodou');
    }

    $contas = DB::table('chart_of_accounts')->where('business_id', $businessId)->where('active', 1)->limit(2)->get();
    if ($contas->count() < 2) {
        $this->markTestSkipped('Plano de contas biz=1 vazio — seeder não rodou');
    }

    $userId = (int) (DB::table('users')->where('business_id', $businessId)->value('id') ?? 1);

    $service = new JournalEntryService();
    $numbers = $service->criarEntradaBalanceada([
        'location_id' => $location->id,
        'currency_id' => 1,
        'payment_type_id' => null,
        'date' => date('Y-m-d'),
        'journal_entry_data' => [
            [
                'debit' => $contas[0]->id,
                'credit' => $contas[1]->id,
                'amount' => 100.50,
                'notes' => 'Wave J smoke — biz=1',
            ],
        ],
    ], $userId);

    expect($numbers)->toHaveCount(1);

    $entries = JournalEntry::where('transaction_number', $numbers[0])->get();
    expect($entries)->toHaveCount(2)
        ->and($entries->whereNotNull('debit')->sum('debit'))->toEqual(100.50)
        ->and($entries->whereNotNull('credit')->sum('credit'))->toEqual(100.50);
});

test('reverter marca original como reversed=1 e cria contrapartida espelho', function () {
    $businessId = 1;
    $location = DB::table('business_locations')->where('business_id', $businessId)->first();
    if (! $location) {
        $this->markTestSkipped('Sem business_location biz=1');
    }
    $contas = DB::table('chart_of_accounts')->where('business_id', $businessId)->where('active', 1)->limit(2)->get();
    if ($contas->count() < 2) {
        $this->markTestSkipped('Plano de contas biz=1 vazio');
    }
    $userId = (int) (DB::table('users')->where('business_id', $businessId)->value('id') ?? 1);

    $service = new JournalEntryService();
    $numbers = $service->criarEntradaBalanceada([
        'location_id' => $location->id,
        'currency_id' => 1,
        'payment_type_id' => null,
        'date' => date('Y-m-d'),
        'journal_entry_data' => [
            ['debit' => $contas[0]->id, 'credit' => $contas[1]->id, 'amount' => 42.00, 'notes' => 'pra reverter'],
        ],
    ], $userId);

    $original = $numbers[0];

    $novo = $service->reverter($original, $businessId, $userId);

    $originais = JournalEntry::where('transaction_number', $original)->get();
    $contrapartida = JournalEntry::where('transaction_number', $novo)->get();

    expect($originais->every(fn ($e) => (int) $e->reversed === 1))->toBeTrue()
        ->and($contrapartida)->toHaveCount(2)
        ->and($contrapartida->whereNotNull('debit')->sum('debit'))->toEqual(42.00)
        ->and($contrapartida->whereNotNull('credit')->sum('credit'))->toEqual(42.00);
});

test('isolamento multi-tenant — reverter biz=1 não toca lançamentos biz=99', function () {
    $businessId = 1;
    $service = new JournalEntryService();

    // Tenta reverter transaction_number inexistente em biz=1 — não pode afetar nenhum registro
    $countBefore = JournalEntry::where('reversed', 1)->count();

    $service->reverter('TX-INEXISTENTE-WAVE-J-' . uniqid(), $businessId, 1);

    $countAfter = JournalEntry::where('reversed', 1)->count();

    expect($countAfter)->toEqual($countBefore);
});
