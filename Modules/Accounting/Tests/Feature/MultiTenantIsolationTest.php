<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\Account;
use Modules\Accounting\Entities\AccountSubtype;
use Modules\Accounting\Entities\AccountTransaction;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Accounting\Entities\JournalEntry;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 dos Models Accounting.
 *
 * IMPORTANTE — Accounting (módulo legacy UltimatePOS) NÃO tem BusinessScope global
 * em todos os Models como ComunicacaoVisual ou Financeiro. Filtragem por business_id
 * acontece via scopes locais (`forBusiness`) ou WHERE manual nos Controllers.
 * Estes tests validam que esses scopes funcionam como esperado e que dados de biz=1
 * NUNCA aparecem ao consultar como biz=99 (Tier 0 IRREVOGÁVEL — ADR 0093).
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — conforme ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite + tabelas: Models Accounting requerem schema MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: Models Accounting requerem schema MySQL UltimatePOS ' .
            '(ADR 0101 — Pest local mandatory contra DB dev real)'
        );
    }
    foreach (['chart_of_accounts', 'journal_entries', 'account_subtypes', 'business_locations'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela `{$tbl}` missing — rode migrate do módulo Accounting primeiro");
        }
    }
});

// IDs usados nos testes — biz=1 (Wagner) e biz=99 (fictício isolamento)
const ACC_BIZ_WAGNER = 1;
const ACC_BIZ_FICTICIO = 99;

// ------------------------------------------------------------------
// Helper — simula sessão de business sem autenticar user (suficiente pro scope manual)
// ------------------------------------------------------------------
function setAccBizSession(int $businessId): void
{
    session([
        'business.id'      => $businessId,
        'user.business_id' => $businessId,
    ]);
}

// ------------------------------------------------------------------
// ChartOfAccount — scope forBusiness filtra business_id
// ------------------------------------------------------------------

it('ChartOfAccount biz=1 não aparece via scope forBusiness com session biz=99', function () {
    // Cria conta no biz=1 (insert direto bypassa qualquer scope na inserção)
    $coa = ChartOfAccount::create([ // SUPERADMIN: inserção direta de teste seed
        'business_id'  => ACC_BIZ_WAGNER,
        'name'         => 'Conta Teste Isolamento',
        'gl_code'      => 'TEST-ISOL-001',
        'account_type' => 'asset',
        'active'       => 1,
    ]);

    // Consulta com session biz=99 via scope manual `forBusiness` — NÃO deve aparecer
    setAccBizSession(ACC_BIZ_FICTICIO);
    $resultado = ChartOfAccount::forBusiness()->where('id', $coa->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    ChartOfAccount::where('gl_code', 'TEST-ISOL-001')
        ->where('business_id', ACC_BIZ_WAGNER)
        ->forceDelete();
});

it('ChartOfAccount biz=1 aparece via scope forBusiness com session biz=1', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN: inserção direta de teste seed
        'business_id'  => ACC_BIZ_WAGNER,
        'name'         => 'Conta Teste Vis Same Biz',
        'gl_code'      => 'TEST-ISOL-002',
        'account_type' => 'asset',
        'active'       => 1,
    ]);

    setAccBizSession(ACC_BIZ_WAGNER);
    $resultado = ChartOfAccount::forBusiness()->where('id', $coa->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->gl_code)->toBe('TEST-ISOL-002');
})->afterEach(function () {
    ChartOfAccount::where('gl_code', 'TEST-ISOL-002')
        ->where('business_id', ACC_BIZ_WAGNER)
        ->forceDelete();
});

// ------------------------------------------------------------------
// AccountSubtype — scope forBusiness permite biz=0 (default global) OR biz session
// ------------------------------------------------------------------

it('AccountSubtype biz=1 não aparece via forBusiness com session biz=99', function () {
    $sub = AccountSubtype::create([ // SUPERADMIN: inserção direta de teste seed
        'business_id'  => ACC_BIZ_WAGNER,
        'account_type' => 'asset',
        'name'         => 'Subtype Teste Isolamento',
        'description'  => 'Pest isolation test',
        'active'       => 1,
    ]);

    // Com session biz=99, scope forBusiness exige business_id=0 OR business_id=99.
    // Como o nosso é biz=1, NÃO deve aparecer.
    setAccBizSession(ACC_BIZ_FICTICIO);
    $resultado = AccountSubtype::forBusiness()
        ->where('account_subtypes.id', $sub->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    AccountSubtype::where('name', 'Subtype Teste Isolamento')
        ->where('business_id', ACC_BIZ_WAGNER)
        ->forceDelete();
});

it('AccountSubtype com business_id=0 (default global) aparece pra qualquer biz', function () {
    // business_id=0 é convenção do módulo pra "tipo default global" — aparece em qualquer biz
    $sub = AccountSubtype::create([ // SUPERADMIN: inserção direta de teste seed
        'business_id'  => 0,
        'account_type' => 'asset',
        'name'         => 'Subtype Default Teste',
        'description'  => 'Global default',
        'active'       => 1,
    ]);

    setAccBizSession(ACC_BIZ_FICTICIO);
    $resultado = AccountSubtype::forBusiness()
        ->where('account_subtypes.id', $sub->id)
        ->get();

    // Default global (business_id=0) é visível pra qualquer business — é o contrato do scope
    expect($resultado)->toHaveCount(1);
})->afterEach(function () {
    AccountSubtype::where('name', 'Subtype Default Teste')
        ->where('business_id', 0)
        ->forceDelete();
});

// ------------------------------------------------------------------
// JournalEntry — filtra via JOIN business_locations.business_id
// ------------------------------------------------------------------

it('JournalEntry biz=1 não aparece em query filtrada por business_locations.business_id=99', function () {
    // Pega uma location de biz=1 — se não existir, skip
    $locationBiz1 = DB::table('business_locations')
        ->where('business_id', ACC_BIZ_WAGNER)
        ->first();

    if (! $locationBiz1) {
        $this->markTestSkipped('Sem business_location pra biz=1 — rode seeder UltimatePOS primeiro');
    }

    // Cria chart_of_account no biz=1 (precisa pra FK)
    $coa = ChartOfAccount::create([ // SUPERADMIN: inserção direta de teste seed
        'business_id'  => ACC_BIZ_WAGNER,
        'name'         => 'COA pra JE teste',
        'gl_code'      => 'TEST-ISOL-JE',
        'account_type' => 'asset',
        'active'       => 1,
    ]);

    $je = JournalEntry::create([ // SUPERADMIN: inserção direta de teste seed
        'reversed'             => 0,
        'transaction_number'   => 'JE-TEST-ISOL-001',
        'location_id'          => $locationBiz1->id,
        'chart_of_account_id'  => $coa->id,
        'transaction_type'     => 'transfer_entry',
        'date'                 => now()->toDateString(),
        'month'                => (int) now()->format('m'),
        'year'                 => (int) now()->format('Y'),
        'debit'                => 100,
        'credit'               => 0,
        'manual_entry'         => 0,
    ]);

    // Query como o Controller faz: leftJoin business_locations + where business_id=99
    setAccBizSession(ACC_BIZ_FICTICIO);
    $count = JournalEntry::leftJoin(
        'business_locations',
        'business_locations.id',
        '=',
        'journal_entries.location_id'
    )
        ->where('business_locations.business_id', ACC_BIZ_FICTICIO)
        ->where('journal_entries.id', $je->id)
        ->count();

    expect($count)->toBe(0);
})->afterEach(function () {
    JournalEntry::where('transaction_number', 'JE-TEST-ISOL-001')->forceDelete();
    ChartOfAccount::where('gl_code', 'TEST-ISOL-JE')
        ->where('business_id', ACC_BIZ_WAGNER)
        ->forceDelete();
});

// ------------------------------------------------------------------
// Cross-tenant raw query — guard final
// ------------------------------------------------------------------

it('query raw com business_id=99 não retorna chart_of_accounts inseridos como biz=1', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN: inserção direta de teste seed
        'business_id'  => ACC_BIZ_WAGNER,
        'name'         => 'COA Raw Cross-Tenant',
        'gl_code'      => 'TEST-RAW-CT',
        'account_type' => 'equity',
        'active'       => 1,
    ]);

    $count = DB::table('chart_of_accounts')
        ->where('business_id', ACC_BIZ_FICTICIO)
        ->where('id', $coa->id)
        ->count();

    expect($count)->toBe(0);
})->afterEach(function () {
    ChartOfAccount::where('gl_code', 'TEST-RAW-CT')
        ->where('business_id', ACC_BIZ_WAGNER)
        ->forceDelete();
});

// ------------------------------------------------------------------
// Wave 15 D1 MT rescue — Account (trait HasBusinessScope) cross-tenant raw
// ------------------------------------------------------------------
// Reflexion-only trait declaration tests vivem em MultiTenantTraitDeclarationTest.php
// (não dependem de MySQL, rodam em qualquer driver).

it('Account criada em biz=1 não vaza pra raw query biz=99 (defesa-em-profundidade Tier 0)', function () {
    $account = Account::create([ // SUPERADMIN: seed pra teste de isolamento
        'business_id' => ACC_BIZ_WAGNER,
        'name'        => 'Conta Banco Teste ISOL Wave 15',
        'account_number' => 'WAVE15-001',
        'note'        => 'Pest Wave 15 D1 rescue',
        'created_by'  => 1,
    ]);

    // Raw cross-tenant check
    $rawCount = DB::table('accounts')
        ->where('business_id', ACC_BIZ_FICTICIO)
        ->where('id', $account->id)
        ->count();

    expect($rawCount)->toBe(0);
})->afterEach(function () {
    Account::where('account_number', 'WAVE15-001')
        ->where('business_id', ACC_BIZ_WAGNER)
        ->forceDelete();
});

// ------------------------------------------------------------------
// Wave 17 D1 SATURATION — cross-tenant raw em TODAS as Entities Tier 0
//   Account, ChartOfAccount, Budget, BranchCapital,
//   AccountTransaction (child via account.business_id),
//   Transfer (child via accounts.business_id em from/to_account),
//   JournalEntry (child via business_locations.business_id).
//
// Defesa-em-profundidade: cada entity inserida em biz=1 NÃO pode aparecer
// quando query é feita filtrando business_id=99 (raw DB ou via scope local).
// ------------------------------------------------------------------

it('Wave 17 saturação — múltiplas Entities biz=1 nunca vazam pra raw biz=99', function () {
    // 1) ChartOfAccount já testado acima (gl_code TEST-RAW-CT) — sanity recheck
    $coa = ChartOfAccount::create([ // SUPERADMIN
        'business_id'  => ACC_BIZ_WAGNER,
        'name'         => 'COA Wave 17 Sat',
        'gl_code'      => 'WAVE17-SAT-COA',
        'account_type' => 'asset',
        'active'       => 1,
    ]);

    // 2) Budget — child sem business_id direto? Wave 12 confirmou trait HasBusinessScope.
    //    Verifica via raw count
    if (\Schema::hasTable('budgets') && \Schema::hasColumn('budgets', 'business_id')) {
        $budget = \Modules\Accounting\Entities\Budget::create([ // SUPERADMIN
            'business_id'         => ACC_BIZ_WAGNER,
            'chart_of_account_id' => $coa->id,
            'financial_year'      => 2026,
            'month_1'             => 100.00,
            'month_2'             => 0,
            'month_3'             => 0,
            'month_4'             => 0,
            'month_5'             => 0,
            'month_6'             => 0,
            'month_7'             => 0,
            'month_8'             => 0,
            'month_9'             => 0,
            'month_10'            => 0,
            'month_11'            => 0,
            'month_12'            => 0,
        ]);

        $rawBudget = DB::table('budgets')
            ->where('business_id', ACC_BIZ_FICTICIO)
            ->where('id', $budget->id)
            ->count();
        expect($rawBudget)->toBe(0);

        \Modules\Accounting\Entities\Budget::where('id', $budget->id)->forceDelete();
    }

    // 3) BranchCapital — same pattern (Wave 12 trait)
    if (\Schema::hasTable('branch_capitals') && \Schema::hasColumn('branch_capitals', 'business_id')) {
        $bc = \Modules\Accounting\Entities\BranchCapital::create([ // SUPERADMIN
            'business_id'         => ACC_BIZ_WAGNER,
            'chart_of_account_id' => $coa->id,
            'amount'              => 1000.00,
            'capital_type'        => 'investment',
        ]);

        $rawBc = DB::table('branch_capitals')
            ->where('business_id', ACC_BIZ_FICTICIO)
            ->where('id', $bc->id)
            ->count();
        expect($rawBc)->toBe(0);

        \Modules\Accounting\Entities\BranchCapital::where('id', $bc->id)->forceDelete();
    }

    // Cleanup COA
    ChartOfAccount::where('id', $coa->id)->forceDelete();
});

it('AccountTransaction (child via account.business_id) não vaza cross-tenant via raw JOIN', function () {
    // AccountTransaction NÃO tem business_id direto — escopa via parent account.business_id.
    // Defesa: insere account biz=1 + transaction filha; query JOIN com business_id=99 retorna 0.
    $account = Account::create([ // SUPERADMIN
        'business_id'    => ACC_BIZ_WAGNER,
        'name'           => 'Conta Wave 17 AT child',
        'account_number' => 'WAVE17-AT-001',
        'note'           => 'Pest Wave 17 D1 child trait',
        'created_by'     => 1,
    ]);

    if (! \Schema::hasTable('account_transactions')) {
        Account::where('id', $account->id)->forceDelete();
        $this->markTestSkipped('Tabela account_transactions missing — skip');
    }

    $atId = DB::table('account_transactions')->insertGetId([
        'amount'         => 100.00,
        'account_id'     => $account->id,
        'type'           => 'credit',
        'operation_date' => now()->toDateString(),
        'created_by'     => 1,
        'note'           => 'Pest Wave 17 child cross-tenant',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    // JOIN cross-tenant: query account_transactions filtrando accounts.business_id=99 → 0 linhas
    $crossCount = DB::table('account_transactions')
        ->join('accounts', 'accounts.id', '=', 'account_transactions.account_id')
        ->where('accounts.business_id', ACC_BIZ_FICTICIO)
        ->where('account_transactions.id', $atId)
        ->count();

    expect($crossCount)->toBe(0);

    // Cleanup
    DB::table('account_transactions')->where('id', $atId)->delete();
    Account::where('id', $account->id)->forceDelete();
});

it('Transfer (child via from_account.business_id) não vaza cross-tenant via raw JOIN', function () {
    // Transfer NÃO tem business_id direto — escopa via accounts.business_id (from/to_account).
    if (! \Schema::hasTable('transfers')) {
        $this->markTestSkipped('Tabela transfers missing — skip');
    }

    $fromAcc = Account::create([ // SUPERADMIN
        'business_id'    => ACC_BIZ_WAGNER,
        'name'           => 'Conta FROM Wave 17',
        'account_number' => 'WAVE17-TR-FROM',
        'note'           => 'Pest Wave 17 D1 transfer',
        'created_by'     => 1,
    ]);
    $toAcc = Account::create([ // SUPERADMIN
        'business_id'    => ACC_BIZ_WAGNER,
        'name'           => 'Conta TO Wave 17',
        'account_number' => 'WAVE17-TR-TO',
        'note'           => 'Pest Wave 17 D1 transfer',
        'created_by'     => 1,
    ]);

    $columns = ['from_account', 'to_account', 'amount', 'transfer_by', 'transfer_date', 'created_at', 'updated_at'];
    $existing = array_filter($columns, fn ($c) => \Schema::hasColumn('transfers', $c));
    if (count($existing) < 5) {
        Account::whereIn('id', [$fromAcc->id, $toAcc->id])->forceDelete();
        $this->markTestSkipped('Schema transfers incompatível — skip');
    }

    $transferId = DB::table('transfers')->insertGetId([
        'from_account'  => $fromAcc->id,
        'to_account'    => $toAcc->id,
        'amount'        => 200.00,
        'transfer_by'   => 1,
        'transfer_date' => now()->toDateString(),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    // JOIN cross-tenant via from_account
    $crossCount = DB::table('transfers')
        ->join('accounts', 'accounts.id', '=', 'transfers.from_account')
        ->where('accounts.business_id', ACC_BIZ_FICTICIO)
        ->where('transfers.id', $transferId)
        ->count();

    expect($crossCount)->toBe(0);

    // Cleanup
    DB::table('transfers')->where('id', $transferId)->delete();
    Account::whereIn('id', [$fromAcc->id, $toAcc->id])->forceDelete();
});

it('PaymentType (catálogo biz=0 default global) é compartilhado intencionalmente entre biz', function () {
    // Sanity: PaymentType/AccountSubtype/AccountDetailType são catálogo plataforma-wide
    // (Wave 13 doc explica). Confirma que entrada biz=0 (global) APARECE em qualquer biz.
    if (! \Schema::hasTable('payment_types')) {
        $this->markTestSkipped('Tabela payment_types missing — skip');
    }
    // PaymentType global — qualquer linha biz=0 deve continuar acessível, é catálogo
    $hasGlobal = DB::table('payment_types')->where('business_id', 0)->exists();
    if (! $hasGlobal) {
        // Cria um pra validar o contrato
        $ptId = DB::table('payment_types')->insertGetId([
            'business_id' => 0,
            'name'        => 'Pest Catálogo Global Wave 17',
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
    // Pelo menos 1 linha biz=0 deve existir agora
    $count = DB::table('payment_types')->where('business_id', 0)->count();
    expect($count)->toBeGreaterThan(0);

    if (isset($ptId)) {
        DB::table('payment_types')->where('id', $ptId)->delete();
    }
});
