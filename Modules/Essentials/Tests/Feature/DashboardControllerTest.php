<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Smoke tests para Modules/Essentials/Http/Controllers/DashboardController.
 *
 * Duas dashboards atendidas pelo mesmo controller:
 *   - GET /hrm/dashboard          → hrmDashboard()
 *   - GET /essentials/dashboard   → essentialsDashboard()
 *
 * Ambas ainda são views Blade (essentials::dashboard.*). Quando migrarem para
 * Inertia, este teste deve ganhar assertion de componente. Por enquanto,
 * cobre apenas o contrato público (autenticação + status code).
 *
 * Métodos prefixados com `test_` para PHPUnit 12 (anotação @test foi removida).
 */
class DashboardControllerTest extends EssentialsTestCase
{
    public function test_essentials_dashboard_exige_autenticacao(): void
    {
        $this->get('/essentials/dashboard')->assertRedirect('/login');
    }

    public function test_hrm_dashboard_exige_autenticacao(): void
    {
        $this->get('/hrm/dashboard')->assertRedirect('/login');
    }

    public function test_user_sales_targets_exige_autenticacao(): void
    {
        $this->get('/essentials/user-sales-targets')->assertRedirect('/login');
    }

    public function test_essentials_dashboard_responde_para_admin(): void
    {
        $this->actAsAdmin();
        $response = $this->get('/essentials/dashboard');

        $this->assertContains($response->status(), [200, 302, 403],
            'GET /essentials/dashboard deve responder 200/302/403 para admin, recebi: ' . $response->status());
    }

    public function test_hrm_dashboard_responde_para_admin(): void
    {
        $this->actAsAdmin();
        $response = $this->get('/hrm/dashboard');

        $this->assertContains($response->status(), [200, 302, 403],
            'GET /hrm/dashboard deve responder 200/302/403 para admin, recebi: ' . $response->status());
    }
}
