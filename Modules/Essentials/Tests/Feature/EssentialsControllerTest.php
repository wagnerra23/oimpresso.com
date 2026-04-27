<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Smoke tests para Modules/Essentials/Http/Controllers/EssentialsController.
 *
 * Cobre a rota base /essentials (index) que devolve a view `essentials::index`.
 * Por enquanto o controller é um stub Blade — quando migrado para Inertia,
 * estes testes ganham assertion sobre o componente esperado.
 *
 * Métodos prefixados com `test_` para PHPUnit 12 (anotação @test foi removida).
 */
class EssentialsControllerTest extends EssentialsTestCase
{
    public function test_index_exige_autenticacao(): void
    {
        $this->get('/essentials')->assertRedirect('/login');
    }

    public function test_index_responde_para_admin_autenticado(): void
    {
        $this->actAsAdmin();
        $response = $this->get('/essentials');

        $this->assertContains($response->status(), [200, 302],
            'GET /essentials deve responder 200 ou 302 para admin, recebi: ' . $response->status());
    }

    public function test_dashboard_essentials_exige_autenticacao(): void
    {
        $this->get('/essentials/dashboard')->assertRedirect('/login');
    }

    public function test_install_controller_existe_e_tem_metodo_index(): void
    {
        // Essentials predates ADR 0024 — ainda não estende
        // BaseModuleInstallController (planejado em backlog). Por enquanto
        // só blindamos a existência da classe e do método público index().
        $reflection = new \ReflectionClass(\Modules\Essentials\Http\Controllers\InstallController::class);
        $this->assertTrue(
            $reflection->hasMethod('index'),
            'Essentials/InstallController deve expor método index().'
        );
    }
}
