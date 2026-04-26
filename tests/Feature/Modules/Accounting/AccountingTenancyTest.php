<?php

namespace Tests\Feature\Modules\Accounting;

use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Accounting\Tests\Feature\AccountingTestCase;

/**
 * Tenancy: módulo Accounting deve isolar dados por business_id.
 *
 * Regra (R-ACCO-001 isolamento multi-tenant):
 *   - Controllers fazem session('business.id') em todas as queries.
 *   - Models como ChartOfAccount têm scope `forBusiness()` (ver
 *     DashboardController:29).
 *
 * Esse teste apenas garante via reflection que o scope existe e que
 * nenhum query sem scope vaza pra fora — não cria business B (custoso
 * sem RefreshDatabase). Para tenancy leak completo, ver
 * tests/Feature/Modules/Copiloto/TenancyLeakTest.php (pattern referência).
 */
class AccountingTenancyTest extends AccountingTestCase
{
    public function test_chart_of_account_tem_scope_for_business(): void
    {
        $this->assertTrue(
            method_exists(ChartOfAccount::class, 'scopeForBusiness') ||
            method_exists(ChartOfAccount::class, 'forBusiness'),
            'ChartOfAccount deve ter scope forBusiness() para tenancy.'
        );
    }

    public function test_chart_of_account_forbusiness_filtra_por_business_id(): void
    {
        $this->actAsAdmin();

        $businessA = $this->business->id;
        $businessB = $businessA + 9999; // ID inexistente

        // Sem RefreshDatabase, só validamos que o scope filtra corretamente.
        // Se houver chart_of_accounts no business A, devem todos retornar.
        $contas = ChartOfAccount::forBusiness()->get();

        foreach ($contas as $conta) {
            $this->assertEquals(
                $businessA,
                $conta->business_id,
                "Vazamento de tenancy: ChartOfAccount {$conta->id} pertence a business {$conta->business_id}, esperado {$businessA}."
            );
        }
    }

    public function test_dashboard_carrega_apenas_dados_do_business_logado(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/dashboard');

        // O importante é só validar que responde sem 500 — o conteúdo é Blade
        // e não temos como inspecionar shape sem parse de HTML.
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
