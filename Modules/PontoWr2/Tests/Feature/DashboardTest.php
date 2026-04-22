<?php

namespace Modules\PontoWr2\Tests\Feature;

/**
 * Feature test do Dashboard do PontoWR2.
 */
class DashboardTest extends PontoTestCase
{
    /** @test */
    public function dashboard_exige_autenticacao(): void
    {
        $this->get('/ponto')->assertRedirect('/login');
    }

    /** @test */
    public function dashboard_renderiza_componente_correto_com_props_esperados(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto');

        $this->assertInertiaComponent($response, 'Ponto/Dashboard/Index');

        $props = $response->json('props');
        $this->assertArrayHasKey('kpis', $props);
        $this->assertArrayHasKey('aprovacoes', $props);
        $this->assertArrayHasKey('atividade_recente', $props);
        $this->assertArrayHasKey('serie_7dias', $props);

        // KPIs com as 6 chaves esperadas
        $this->assertEqualsCanonicalizing(
            ['colaboradores_ativos', 'presentes_agora', 'atrasos_hoje',
             'faltas_hoje', 'he_mes_minutos', 'aprovacoes_pendentes'],
            array_keys($props['kpis'])
        );

        // Série tem 7 dias (hoje + 6 anteriores)
        $this->assertCount(7, $props['serie_7dias']);
        $this->assertEqualsCanonicalizing(
            ['data', 'label', 'trabalhado', 'he'],
            array_keys($props['serie_7dias'][0])
        );
    }

    /** @test */
    public function dashboard_shell_menu_contem_ponto_wr2(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto');

        $menu = $response->json('props.shell.menu');
        $this->assertIsArray($menu);
        $this->assertGreaterThan(0, count($menu), 'Menu do shell deve ter pelo menos 1 item');
    }
}
