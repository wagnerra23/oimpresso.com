<?php

namespace Modules\Financeiro\Tests\Feature;

use App\Account;
use App\Business;
use Illuminate\Database\QueryException;
use Modules\Financeiro\Models\AccountsLegacyMap;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;

/**
 * Testes Tier 0 multi-tenant pras 2 migrations da Fase 5b:
 *  - accounts_legacy_map (bridge nova pra accounts core)
 *  - fin_contas_bancarias.legacy_source/legacy_id/legacy_imported_at
 *
 * Garantem que:
 *  1. BusinessScope isola accounts_legacy_map cross-tenant
 *  2. UNIQUE composto permite mesmo legacy_id em business_ids diferentes
 *  3. UNIQUE composto bloqueia duplicidade no mesmo business_id
 *  4. ContaBancaria aceita legacy_* no fillable
 *  5. Superadmin com can('superadmin') vê cross-tenant deliberadamente
 *
 * Conforme padrão de MultiTenantIsolationTest (R-FIN-001).
 *
 * @group multi_tenant
 * @group fase5b
 */
class AccountsLegacyMapMultiTenantTest extends FinanceiroTestCase
{
    private const LEGACY_SOURCE = 'wr-comercial-delphi-test-fase5b';
    private const LEGACY_ID = 'TEST-MIG-001';

    /** @var int[] Account IDs criados durante os tests, pra cleanup. */
    private array $createdAccountIds = [];

    private function firstAccountFor(int $businessId): ?Account
    {
        return Account::where('business_id', $businessId)->first();
    }

    /**
     * Cria Account nova dedicada pro test (necessário quando o test cria
     * fin_contas_bancarias — UNIQUE em account_id impede reuso de Account
     * existente que já tem ContaBancaria 1:1).
     */
    private function createTestAccount(int $businessId, string $suffix = ''): Account
    {
        $userId = $this->admin?->id ?? 1;
        $marker = 'TEST_FASE5B_' . substr((string) microtime(true), -8) . $suffix;

        $account = new Account([
            'business_id' => $businessId,
            'name' => $marker,
            'account_number' => $marker,
            'account_type' => 'saving_current',
        ]);
        $account->created_by = $userId;
        $account->save();

        $this->createdAccountIds[] = $account->id;
        return $account;
    }

    private function cleanupLegacyMap(): void
    {
        AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('legacy_source', self::LEGACY_SOURCE)
            ->forceDelete();
    }

    private function cleanupLegacyContaBancaria(): void
    {
        ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('legacy_source', self::LEGACY_SOURCE)
            ->forceDelete();
    }

    private function cleanupTestAccounts(): void
    {
        foreach ($this->createdAccountIds as $id) {
            Account::where('id', $id)->delete();
        }
        $this->createdAccountIds = [];
    }

    protected function tearDown(): void
    {
        // Ordem importa: legacy_map e fin_contas_bancarias têm FK pra accounts,
        // então deletamos elas antes das accounts criadas no test.
        $this->cleanupLegacyMap();
        $this->cleanupLegacyContaBancaria();
        $this->cleanupTestAccounts();
        parent::tearDown();
    }

    // ------------------------------------------------------------------------
    // 1) Isolamento BusinessScope
    // ------------------------------------------------------------------------

    public function test_business_scope_isola_accounts_legacy_map_cross_tenant(): void
    {
        $this->actAsAdmin();
        $primary = $this->business;

        $other = Business::where('id', '!=', $primary->id)->first();
        if (! $other) {
            $this->markTestSkipped('Sem segundo business em DB pra testar isolamento real.');
        }

        $primaryAccount = $this->firstAccountFor($primary->id);
        if (! $primaryAccount) {
            $this->markTestSkipped("Sem account no business {$primary->id} — precisa seeder.");
        }

        // Cria registro no primary com bypass de scope
        AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $primary->id,
                'account_id' => $primaryAccount->id,
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID,
                'legacy_metadata' => ['test' => 'isolation'],
            ]);

        $isSuperadmin = auth()->user()->can('superadmin');
        if (! $isSuperadmin) {
            // Como user não-superadmin do primary: scope encontra
            $found = AccountsLegacyMap::where('legacy_source', self::LEGACY_SOURCE)->count();
            $this->assertEquals(1, $found, 'Scope deveria encontrar registro do próprio business');
        }

        // Logout + sessão de outro business → scope ATIVO
        auth()->logout();
        session(['user.business_id' => $other->id]);

        $foundCross = AccountsLegacyMap::where('legacy_source', self::LEGACY_SOURCE)->count();
        $this->assertEquals(
            0,
            $foundCross,
            'Scope NÃO deveria vazar dados cross-business (foundCross=' . $foundCross . ')'
        );
    }

    // ------------------------------------------------------------------------
    // 2) UNIQUE composto permite mesmo legacy_id em tenants diferentes
    // ------------------------------------------------------------------------

    public function test_unique_constraint_permite_mesmo_legacy_id_em_tenants_diferentes(): void
    {
        $this->actAsAdmin();
        $primary = $this->business;

        $other = Business::where('id', '!=', $primary->id)->first();
        if (! $other) {
            $this->markTestSkipped('Sem segundo business em DB.');
        }

        $primaryAccount = $this->firstAccountFor($primary->id);
        $otherAccount = $this->firstAccountFor($other->id);
        if (! $primaryAccount || ! $otherAccount) {
            $this->markTestSkipped('Falta account em algum dos businesses.');
        }

        // Cria mesmo (source, legacy_id) em 2 businesses — UPSERT idempotente
        // por tenant é a regra: dois clientes Delphi diferentes podem ter
        // ambos `CODIGO=1` em CONTAS, mas viram registros distintos no oimpresso.

        $row1 = AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $primary->id,
                'account_id' => $primaryAccount->id,
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID,
            ]);

        $row2 = AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $other->id,
                'account_id' => $otherAccount->id,
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID,
            ]);

        $this->assertNotEquals($row1->id, $row2->id, 'IDs PK devem ser diferentes');
        $this->assertEquals($primary->id, $row1->business_id);
        $this->assertEquals($other->id, $row2->business_id);
    }

    // ------------------------------------------------------------------------
    // 3) UNIQUE composto bloqueia duplicidade no mesmo tenant
    // ------------------------------------------------------------------------

    public function test_unique_constraint_bloqueia_duplicidade_no_mesmo_tenant(): void
    {
        $this->actAsAdmin();
        $primary = $this->business;
        $primaryAccount = $this->firstAccountFor($primary->id);
        if (! $primaryAccount) {
            $this->markTestSkipped("Sem account no business {$primary->id}.");
        }

        AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $primary->id,
                'account_id' => $primaryAccount->id,
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID,
            ]);

        $this->expectException(QueryException::class);

        // Segundo INSERT com mesma chave UNIQUE — deve falhar com SQLSTATE 23000
        AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $primary->id,
                'account_id' => $primaryAccount->id,
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID,
            ]);
    }

    // ------------------------------------------------------------------------
    // 4) fin_contas_bancarias aceita legacy_* no fillable
    // ------------------------------------------------------------------------

    public function test_fin_contas_bancarias_aceita_legacy_columns(): void
    {
        $this->actAsAdmin();
        $primary = $this->business;

        // Account dedicada pro test: fin_contas_bancarias.account_id tem
        // UNIQUE constraint (relação 1:1 com accounts). Reusar Account
        // existente que já tem ContaBancaria viola UNIQUE.
        $testAccount = $this->createTestAccount($primary->id, '_cb_legacy');

        $cb = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $primary->id,
                'account_id' => $testAccount->id,
                'banco_codigo' => '341',
                'agencia' => '0001',
                'carteira' => '109',
                'beneficiario_documento' => '00.000.000/0001-00',
                'beneficiario_razao_social' => 'TEST FASE5B',
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID,
            ]);

        $this->assertNotNull($cb->id);
        $this->assertEquals(self::LEGACY_SOURCE, $cb->legacy_source);
        $this->assertEquals(self::LEGACY_ID, $cb->legacy_id);

        // legacy_imported_at é preenchido pelo importer (não default DB) —
        // valida apenas o cast: quando preenchido, vira Carbon.
        $cbWithDate = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('id', $cb->id)
            ->first();
        $cbWithDate->legacy_imported_at = now();
        $cbWithDate->save();

        $reloaded = $cbWithDate->fresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $reloaded->legacy_imported_at,
            'legacy_imported_at deveria ser cast pra Carbon quando preenchido');
    }

    // ------------------------------------------------------------------------
    // 5) Superadmin atravessa scope (operação importer / Migration Factory)
    // ------------------------------------------------------------------------

    public function test_superadmin_pode_ler_accounts_legacy_map_cross_tenant(): void
    {
        $this->actAsAdmin();
        $primary = $this->business;

        $other = Business::where('id', '!=', $primary->id)->first();
        if (! $other) {
            $this->markTestSkipped('Sem segundo business em DB.');
        }

        $primaryAccount = $this->firstAccountFor($primary->id);
        $otherAccount = $this->firstAccountFor($other->id);
        if (! $primaryAccount || ! $otherAccount) {
            $this->markTestSkipped('Falta account em algum dos businesses.');
        }

        // Setup: registros em ambos businesses
        AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $primary->id,
                'account_id' => $primaryAccount->id,
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID . '-A',
            ]);

        AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $other->id,
                'account_id' => $otherAccount->id,
                'legacy_source' => self::LEGACY_SOURCE,
                'legacy_id' => self::LEGACY_ID . '-B',
            ]);

        // User normal logado no primary: scope ENCONTRA só seus
        if (! auth()->user()->can('superadmin')) {
            $found = AccountsLegacyMap::where('legacy_source', self::LEGACY_SOURCE)->count();
            $this->assertEquals(1, $found, 'User normal vê só registros do próprio tenant');
        }

        // Bypass explícito (caller superadmin OU CLI/Job sem sessão):
        // BusinessScopeImpl::apply() retorna sem aplicar scope.
        $allRows = AccountsLegacyMap::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('legacy_source', self::LEGACY_SOURCE)
            ->count();

        $this->assertEquals(
            2,
            $allRows,
            'withoutGlobalScope deveria atravessar tenants (operação superadmin/importer)'
        );
    }
}
