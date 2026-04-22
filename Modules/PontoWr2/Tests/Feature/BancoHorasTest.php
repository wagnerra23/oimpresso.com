<?php

namespace Modules\PontoWr2\Tests\Feature;

class BancoHorasTest extends PontoTestCase
{
    /** @test */
    public function index_renderiza_inertia_com_totais(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto/banco-horas');

        $this->assertInertiaComponent($response, 'Ponto/BancoHoras/Index');

        $props = $response->json('props');
        $this->assertArrayHasKey('saldos', $props);
        $this->assertArrayHasKey('totais', $props);

        $this->assertEqualsCanonicalizing(
            ['credito_total', 'debito_total', 'colaboradores_credito', 'colaboradores_debito'],
            array_keys($props['totais'])
        );
    }

    /** @test */
    public function ajuste_manual_exige_minutos_e_observacao(): void
    {
        $this->actAsAdmin();

        // Sem campos
        $response = $this->post('/ponto/banco-horas/1/ajuste', [], ['Accept' => 'application/json']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['minutos', 'observacao']);
    }

    /** @test */
    public function show_retorna_404_para_colaborador_inexistente(): void
    {
        $this->actAsAdmin();
        $this->inertiaGet('/ponto/banco-horas/999999')->assertStatus(404);
    }
}
