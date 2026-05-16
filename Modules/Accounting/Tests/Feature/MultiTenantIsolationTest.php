<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccountSubtype;
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
