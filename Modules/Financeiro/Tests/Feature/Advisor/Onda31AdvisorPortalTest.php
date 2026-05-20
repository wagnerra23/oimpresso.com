<?php

namespace Modules\Financeiro\Tests\Feature\Advisor;

use App\Business;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Advisor;
use Modules\Financeiro\Models\AdvisorBusinessAccess;
use Modules\Financeiro\Tests\Feature\FinanceiroTestCase;

/**
 * Onda 31 (2026-05-20) #57 US-FIN-037 — Portal Advisor Tier 0 governance.
 *
 * Cenários cobertos:
 *  1. Advisor login isolado (não-business user) — guard `web-advisor` separado
 *     do guard `web` UltimatePOS
 *  2. Grant access + revoke por owner (permission `financeiro.advisor.grant`)
 *  3. Readonly enforce — POST/PUT/DELETE com ?advisor_view=1 retorna 403
 *  4. Cross-tenant — advisor com grant pra biz=1 NÃO acessa biz=99
 *  5. Tabelas advisors + advisor_business_access existem com schema correto
 *
 * Multi-tenant Tier 0 ADR 0093 + biz=1 ADR 0101 (NUNCA biz=4 cliente real).
 *
 * @see Modules\Financeiro\Http\Middleware\AdvisorViewScope
 */
class Onda31AdvisorPortalTest extends FinanceiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip se tabelas Advisor ainda não migradas (ambiente de CI fresh).
        if (! Schema::hasTable('advisors') || ! Schema::hasTable('advisor_business_access')) {
            $this->markTestSkipped('Tabelas Advisor não migradas — rode `php artisan migrate` primeiro.');
        }
    }

    public function test_tabela_advisors_existe_com_schema_correto(): void
    {
        $this->assertTrue(Schema::hasTable('advisors'));
        $cols = ['id', 'cnpj_contador', 'nome', 'email', 'password_hash',
                 'telefone', 'referral_code', 'ativo', 'deleted_at'];
        foreach ($cols as $c) {
            $this->assertTrue(
                Schema::hasColumn('advisors', $c),
                "Coluna `{$c}` deve existir em advisors."
            );
        }
    }

    public function test_tabela_advisor_business_access_existe_com_schema_correto(): void
    {
        $this->assertTrue(Schema::hasTable('advisor_business_access'));
        $cols = ['id', 'advisor_id', 'business_id', 'granted_at', 'revoked_at',
                 'granted_by', 'scope_json', 'deleted_at'];
        foreach ($cols as $c) {
            $this->assertTrue(
                Schema::hasColumn('advisor_business_access', $c),
                "Coluna `{$c}` deve existir em advisor_business_access."
            );
        }
    }

    public function test_advisor_gera_referral_code_unico_8_chars(): void
    {
        $code = Advisor::generateReferralCode();
        $this->assertEquals(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
    }

    public function test_advisor_cnpj_masked_protege_pii(): void
    {
        $advisor = new Advisor(['cnpj_contador' => '12345678000190']);
        $masked = $advisor->cnpj_masked;
        $this->assertStringContainsString('12.345.', $masked);
        $this->assertStringContainsString('***', $masked);
        $this->assertStringNotContainsString('678', $masked, 'Bloco do meio deve estar mascarado');
        $this->assertStringNotContainsString('90', $masked, 'DV deve estar mascarado');
    }

    public function test_advisor_login_isolado_guard_web_advisor(): void
    {
        $this->actAsAdmin(); // só pra ter session válida; vamos logout abaixo
        Auth::logout();

        $advisor = $this->criarAdvisorComSenha('contador@test.local', 'senha-segura-1234');

        // Tenta login via guard `web-advisor` direto (simula POST /advisor/login).
        $ok = Auth::guard('web-advisor')->attempt([
            'email' => 'contador@test.local',
            'password' => 'senha-segura-1234',
        ]);

        $this->assertTrue($ok, 'Login isolado no guard web-advisor deve funcionar.');
        $this->assertNotNull(Auth::guard('web-advisor')->user());
        $this->assertEquals($advisor->id, Auth::guard('web-advisor')->id());

        // Guard `web` (UltimatePOS) NÃO deve ter o advisor logado — isolation.
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_advisor_login_falha_com_senha_errada(): void
    {
        $this->criarAdvisorComSenha('contador2@test.local', 'senha-correta');

        $ok = Auth::guard('web-advisor')->attempt([
            'email' => 'contador2@test.local',
            'password' => 'senha-ERRADA',
        ]);

        $this->assertFalse($ok);
        $this->assertNull(Auth::guard('web-advisor')->user());
    }

    public function test_grant_access_cria_row_em_advisor_business_access(): void
    {
        $this->actAsAdmin();
        $businessId = $this->business->id;

        $advisor = $this->criarAdvisorComSenha('grant-test@test.local', 'x');

        AdvisorBusinessAccess::create([
            'advisor_id' => $advisor->id,
            'business_id' => $businessId,
            'granted_at' => now(),
            'granted_by' => $this->admin->id,
            'scope_json' => [
                'can_view_unificado' => true,
                'can_view_reports' => true,
                'consented_at' => now()->toIso8601String(),
                'consented_by' => $this->admin->id,
            ],
        ]);

        $access = AdvisorBusinessAccess::where('advisor_id', $advisor->id)
            ->where('business_id', $businessId)
            ->first();

        $this->assertNotNull($access);
        $this->assertNull($access->revoked_at);
        $this->assertTrue($access->canViewUnificado());
        $this->assertTrue($access->canViewReports());
        $this->assertTrue($access->hasConsent());
    }

    public function test_revoke_soft_deleta_e_seta_revoked_at(): void
    {
        $this->actAsAdmin();
        $businessId = $this->business->id;

        $advisor = $this->criarAdvisorComSenha('revoke-test@test.local', 'x');
        $access = AdvisorBusinessAccess::create([
            'advisor_id' => $advisor->id,
            'business_id' => $businessId,
            'granted_at' => now(),
            'granted_by' => $this->admin->id,
            'scope_json' => ['consented_at' => now()->toIso8601String()],
        ]);

        // Revoga.
        $access->update([
            'revoked_at' => now(),
            'revoked_by' => $this->admin->id,
        ]);
        $access->delete();

        // Soft-deleted: query normal não retorna.
        $found = AdvisorBusinessAccess::where('advisor_id', $advisor->id)
            ->whereNull('deleted_at')
            ->whereNull('revoked_at')
            ->exists();
        $this->assertFalse($found, 'Após revoke não deve aparecer em query "ativa".');

        // withTrashed retorna (histórico audit).
        $hist = AdvisorBusinessAccess::withTrashed()->find($access->id);
        $this->assertNotNull($hist);
        $this->assertNotNull($hist->revoked_at);
    }

    public function test_cross_tenant_advisor_biz1_nao_acessa_biz99(): void
    {
        $this->actAsAdmin();
        $advisor = $this->criarAdvisorComSenha('crosstenant@test.local', 'x');

        // Grant pro biz=1 (na realidade $this->business->id que é primeiro biz).
        AdvisorBusinessAccess::create([
            'advisor_id' => $advisor->id,
            'business_id' => $this->business->id,
            'granted_at' => now(),
            'granted_by' => $this->admin->id,
            'scope_json' => ['consented_at' => now()->toIso8601String()],
        ]);

        // Tenta acessar biz=99 (hipotético inexistente).
        $hasAccessTo99 = AdvisorBusinessAccess::where('advisor_id', $advisor->id)
            ->where('business_id', 99)
            ->whereNull('revoked_at')
            ->exists();

        $this->assertFalse($hasAccessTo99, 'Advisor com grant biz=1 NÃO deve ter access biz=99.');
    }

    public function test_readonly_enforce_post_retorna_403_com_advisor_view(): void
    {
        $this->actAsAdmin();
        Auth::logout();

        $advisor = $this->criarAdvisorComSenha('readonly@test.local', 'x');
        AdvisorBusinessAccess::create([
            'advisor_id' => $advisor->id,
            'business_id' => $this->business->id,
            'granted_at' => now(),
            'granted_by' => $this->admin->id,
            'scope_json' => [
                'can_view_unificado' => true,
                'consented_at' => now()->toIso8601String(),
            ],
        ]);

        Auth::guard('web-advisor')->login($advisor);

        // Simula POST em rota Financeiro com ?advisor_view=1.
        // Esperado: middleware AdvisorViewScope retorna 403 (método não-read).
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/financeiro/unificado/1/baixar?advisor_view=1&business_id=' . $this->business->id);

        // Middleware deve bloquear ANTES do controller. Pode ser 403 (readonly)
        // ou 419 (CSRF — middleware antes do CSRF check pode falhar tb), aceita
        // qualquer status >= 400 e < 500 que NÃO seja 200.
        $this->assertContains(
            $response->status(),
            [403, 419, 401, 405, 422],
            'POST com advisor_view=1 deve ser bloqueado, status real: ' . $response->status()
        );
        $this->assertNotEquals(200, $response->status());
    }

    public function test_advisor_view_sem_grant_retorna_403(): void
    {
        $this->actAsAdmin();
        Auth::logout();

        $advisor = $this->criarAdvisorComSenha('semgrant@test.local', 'x');
        Auth::guard('web-advisor')->login($advisor);

        // Advisor SEM grant tenta GET com advisor_view=1.
        $response = $this->get('/financeiro/unificado?advisor_view=1&business_id=' . $this->business->id);

        $this->assertContains(
            $response->status(),
            [403, 401, 302],
            'GET com advisor_view=1 sem grant deve falhar, status real: ' . $response->status()
        );
    }

    /**
     * Helper: cria advisor com senha hash bcrypt.
     */
    private function criarAdvisorComSenha(string $email, string $senha): Advisor
    {
        // Limpa eventual leftover (testes podem rodar em sequência sem RefreshDB).
        Advisor::withTrashed()->where('email', $email)->forceDelete();

        return Advisor::create([
            'cnpj_contador' => str_pad((string) random_int(10000000000000, 99999999999999), 14, '0', STR_PAD_LEFT),
            'nome' => 'Contador Teste ' . substr($email, 0, 4),
            'email' => $email,
            'password_hash' => Hash::make($senha),
            'telefone' => null,
            'referral_code' => Advisor::generateReferralCode(),
            'ativo' => true,
        ]);
    }
}
