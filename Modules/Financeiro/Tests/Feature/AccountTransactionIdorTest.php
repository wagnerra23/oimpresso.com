<?php

namespace Modules\Financeiro\Tests\Feature;

use App\Account;
use App\AccountTransaction;
use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * US-FIN-061 (P0) — IDOR cross-tenant em account_transactions (AccountController).
 *
 * Prova REAL (boota o app + bate na ROTA HTTP de verdade, não grep de source)
 * que o tenant A NÃO consegue ler/editar/deletar uma account_transaction do
 * tenant B, e que o re-pointing de account_id pra um account de outro tenant é
 * barrado.
 *
 * Isolamento Tier 0 (ADR 0093): account_transactions NÃO tem business_id — o
 * tenant é resolvido via account_id -> accounts.business_id. O fix escopa as 3
 * queries (edit/update/destroy) com whereHas('account', business_id) ANTES do
 * findOrFail, e valida o account_id RECEBIDO no update.
 *
 * Rotas (routes/web.php, prefixo `account`, middleware setData/auth/SetSessionData):
 *   GET  account/edit-account-transaction/{id}
 *   POST account/update-account-transaction/{id}
 *   GET  account/delete-account-transaction/{id}  (AJAX)
 *
 * MySQL-only: schema UltimatePOS (accounts/account_transactions) não roda em
 * sqlite. Skipa fora do MySQL real semeado (lane financeiro-pest.yml).
 *
 * @see app/Http/Controllers/AccountController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class AccountTransactionIdorTest extends TestCase
{
    /** @var array<string,int> ids criados pra cleanup determinístico */
    private array $created = [];

    private ?User $userA = null;
    private ?int $bizA = null;
    private ?int $bizB = null;
    private ?int $accountA = null;
    private ?int $accountB = null;
    private ?int $txA = null;
    private ?int $txB = null;

    protected function setUp(): void
    {
        parent::setUp();

        // MySQL-only: o schema UltimatePOS (accounts/account_transactions) não
        // existe em sqlite. Em CI roda na lane financeiro-pest.yml (MySQL real).
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('AccountTransaction IDOR roda só no MySQL real (lane financeiro-pest).');
        }
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('account_transactions')) {
            $this->markTestSkipped('Tabelas accounts/account_transactions ausentes — schema UltimatePOS.');
        }

        $this->seedTwoTenants();
    }

    protected function tearDown(): void
    {
        // Cleanup determinístico (sem RefreshDatabase — proibido na lane, dropa
        // o schema baseline + biz=1 seedado). Remove só o que este teste criou.
        DB::table('account_transactions')->whereIn('id', array_filter([$this->txA, $this->txB]))->delete();
        DB::table('accounts')->whereIn('id', array_filter([$this->accountA, $this->accountB]))->delete();
        foreach (['userA' => $this->userA?->id] as $uid) {
            if ($uid) {
                DB::table('model_has_permissions')->where('model_id', $uid)->where('model_type', User::class)->delete();
            }
        }
        DB::table('users')->whereIn('id', array_filter($this->created['users'] ?? []))->delete();
        DB::table('business')->whereIn('id', array_filter([$this->bizA, $this->bizB]))->delete();

        parent::tearDown();
    }

    private function seedTwoTenants(): void
    {
        $currencyId = optional(DB::table('currencies')->first())->id ?? 1;

        // Tenant A (atacante)
        $userAId = DB::table('users')->insertGetId([
            'first_name' => 'IDOR A', 'username' => 'idor_a_' . uniqid(),
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->bizA = DB::table('business')->insertGetId([
            'name' => 'IDOR Biz A', 'currency_id' => $currencyId, 'owner_id' => $userAId,
            'stop_selling_before' => 0, 'weighing_scale_setting' => '', 'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $userAId)->update(['business_id' => $this->bizA]);

        // Tenant B (vítima)
        $userBId = DB::table('users')->insertGetId([
            'first_name' => 'IDOR B', 'username' => 'idor_b_' . uniqid(),
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->bizB = DB::table('business')->insertGetId([
            'name' => 'IDOR Biz B', 'currency_id' => $currencyId, 'owner_id' => $userBId,
            'stop_selling_before' => 0, 'weighing_scale_setting' => '', 'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $userBId)->update(['business_id' => $this->bizB]);

        $this->created['users'] = [$userAId, $userBId];

        // Account de cada tenant
        $this->accountA = DB::table('accounts')->insertGetId([
            'business_id' => $this->bizA, 'name' => 'Conta A', 'account_number' => 'A-' . uniqid(),
            'created_by' => $userAId, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->accountB = DB::table('accounts')->insertGetId([
            'business_id' => $this->bizB, 'name' => 'Conta B', 'account_number' => 'B-' . uniqid(),
            'created_by' => $userBId, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Transação de cada tenant
        $this->txA = DB::table('account_transactions')->insertGetId([
            'account_id' => $this->accountA, 'type' => 'credit', 'amount' => 100.0000,
            'operation_date' => now(), 'created_by' => $userAId, 'note' => 'tx A original',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->txB = DB::table('account_transactions')->insertGetId([
            'account_id' => $this->accountB, 'type' => 'credit', 'amount' => 999.0000,
            'operation_date' => now(), 'created_by' => $userBId, 'note' => 'tx B original',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Usuário A com as permissions reais checadas pelo controller — assim o
        // teste prova o ISOLAMENTO (não um falso-positivo por faltar permission).
        Permission::firstOrCreate(['name' => 'edit_account_transaction', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete_account_transaction', 'guard_name' => 'web']);

        $this->userA = User::find($userAId);
        $this->userA->givePermissionTo('edit_account_transaction', 'delete_account_transaction');
    }

    private function actAsA(): void
    {
        session([
            'user.business_id' => $this->bizA,
            'user.id' => $this->userA->id,
            'business.id' => $this->bizA,
        ]);
        $this->actingAs($this->userA);
    }

    public function test_setup_cria_dois_tenants_reais(): void
    {
        $this->assertNotSame($this->bizA, $this->bizB);
        $this->assertDatabaseHas('account_transactions', ['id' => $this->txB, 'account_id' => $this->accountB]);
        $this->assertSame($this->bizB, (int) Account::find($this->accountB)->business_id);
    }

    /** EDIT (read) cross-tenant: A NÃO lê a tx de B → 404 (findOrFail escopado). */
    public function test_edit_cross_tenant_e_barrado(): void
    {
        $this->actAsA();

        // Própria tx (controle): o findOrFail escopado ENCONTRA → não é 404.
        // (não asserta 200 cheio pra não acoplar ao render do Blade/layout no CI).
        $own = $this->get("account/edit-account-transaction/{$this->txA}");
        $this->assertNotSame(404, $own->getStatusCode(), 'edit da própria tx NÃO pode 404 (scope quebraria o caso legítimo)');

        // Tx do tenant B: findOrFail escopado NÃO encontra → 404 (ou 403).
        // O findOrFail do editAccountTransaction está FORA de try/catch → 404 real.
        $cross = $this->get("account/edit-account-transaction/{$this->txB}");
        $this->assertContains($cross->getStatusCode(), [403, 404], 'edit cross-tenant deve ser barrado (403/404)');
        $this->assertNotSame(200, $cross->getStatusCode(), 'edit NUNCA pode devolver 200 pra tx de outro tenant');
    }

    /**
     * UPDATE (write) cross-tenant: A NÃO edita a tx de B + valor de B intacto.
     *
     * NB: o controller envolve o findOrFail num try/catch(\Exception) e devolve
     * {success:false} (HTTP 200) quando o tenant não bate — então a prova FORTE
     * é a INTEGRIDADE DO DADO no banco (a tx da vítima não muda), não o status.
     * Antes do fix, o findOrFail NU encontrava a tx de B e o save() a sobrescrevia.
     */
    public function test_update_cross_tenant_nao_altera_tx_da_vitima(): void
    {
        $this->actAsA();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->post("account/update-account-transaction/{$this->txB}", [
            'amount' => '1,00',
            'operation_date' => now()->format('d/m/Y'),
            'note' => 'HACKED by tenant A',
        ]);

        // A tx da vítima permanece INTACTA no banco (prova do isolamento).
        $txB = DB::table('account_transactions')->where('id', $this->txB)->first();
        $this->assertSame('tx B original', $txB->note, 'note da vítima NÃO pode ser sobrescrito');
        $this->assertEquals(999.0, (float) $txB->amount, 'amount da vítima NÃO pode mudar');
        $this->assertSame($this->accountB, (int) $txB->account_id, 'account_id da vítima NÃO pode mudar');
    }

    /** RE-POINTING: A edita a PRÓPRIA tx mas tenta apontá-la pra account de B → barrado. */
    public function test_update_repointing_para_account_de_outro_tenant_e_barrado(): void
    {
        $this->actAsA();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->post("account/update-account-transaction/{$this->txA}", [
            'amount' => '100,00',
            'operation_date' => now()->format('d/m/Y'),
            'note' => 'tentando re-apontar',
            'account_id' => $this->accountB, // account de OUTRO tenant
        ]);

        // A tx de A continua apontando pro account de A (re-pointing barrado pelo
        // Account::where(business_id)->findOrFail do account_id RECEBIDO).
        $txA = DB::table('account_transactions')->where('id', $this->txA)->first();
        $this->assertSame($this->accountA, (int) $txA->account_id, 'account_id NÃO pode ser re-apontado pro tenant B');
    }

    /** DELETE cross-tenant: A NÃO deleta a tx de B → tx de B continua viva. */
    public function test_delete_cross_tenant_nao_remove_tx_da_vitima(): void
    {
        $this->actAsA();

        $resp = $this->get("account/delete-account-transaction/{$this->txB}", [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // A tx da vítima continua viva (não soft-deletada).
        $this->assertDatabaseHas('account_transactions', ['id' => $this->txB, 'deleted_at' => null]);
    }
}
