<?php

namespace Modules\PontoWr2\Tests\Feature;

class AprovacaoTest extends PontoTestCase
{
    /** @test */
    public function index_exige_autenticacao(): void
    {
        $this->get('/ponto/aprovacoes')->assertRedirect('/login');
    }

    /** @test */
    public function index_retorna_inertia_com_estrutura_esperada(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto/aprovacoes');

        $this->assertInertiaComponent($response, 'Ponto/Aprovacoes/Index');

        $props = $response->json('props');
        $this->assertArrayHasKey('aprovacoes', $props);
        $this->assertArrayHasKey('filtros', $props);
        $this->assertArrayHasKey('contagens', $props);
        $this->assertArrayHasKey('tipos', $props);

        // Contagens tem 6 estados esperados
        $this->assertEqualsCanonicalizing(
            ['RASCUNHO', 'PENDENTE', 'APROVADA', 'REJEITADA', 'APLICADA', 'CANCELADA'],
            array_keys($props['contagens'])
        );

        // Tipos é array de {value, label}
        $this->assertCount(8, $props['tipos']);
        $this->assertArrayHasKey('value', $props['tipos'][0]);
    }

    /** @test */
    public function filtro_por_estado_funciona(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto/aprovacoes', ['estado' => 'APROVADA']);

        $this->assertInertiaComponent($response, 'Ponto/Aprovacoes/Index');
        $this->assertEquals('APROVADA', $response->json('props.filtros.estado'));
    }

    /** @test */
    public function rejeitar_exige_motivo(): void
    {
        $this->actAsAdmin();

        // Sem motivo deve dar 422
        $response = $this->post('/ponto/aprovacoes/999999/rejeitar', [], ['Accept' => 'application/json']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['motivo']);
    }
}
