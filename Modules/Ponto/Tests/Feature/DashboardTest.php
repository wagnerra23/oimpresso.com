<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * Feature test do Dashboard do PontoWR2.
 *
 * @covers-us US-PONT-006
 */
class DashboardTest extends PontoTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function dashboard_exige_autenticacao(): void
    {
        $this->get('/ponto')->assertRedirect('/login');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dashboard_renderiza_componente_correto_com_props_esperados(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto');

        $this->assertInertiaComponent($response, 'Ponto/Dashboard/Index');

        // ── CONTRATO DEFER (corrigido 2026-08-24) ────────────────────────────────
        // Este caso afirmava que as props caras vinham no PRIMEIRO render. Elas NAO
        // vem: o Controller as entrega via `Inertia::defer` (RUNBOOK-inertia-defer-pattern
        // + proibicoes.md §Sempre-fazer), e prop deferida ausente do payload inicial E o
        // ponto do padrao. O irmao `DashboardDeferredContractTest` PROVA o defer e passava
        // verde ao lado deste — os dois no mesmo modulo, contradizendo-se, porque NENHUM
        // rodava em lane. Medido no CT100 em 2026-08-23.
        //
        // Agora o caso prova o contrato de verdade, nos DOIS lados: ausente no eager,
        // presente e bem-formado no partial reload.
        $props = $response->json('props');
        foreach (['kpis', 'aprovacoes', 'atividade_recente', 'serie_7dias'] as $deferida) {
            $this->assertArrayNotHasKey(
                $deferida,
                $props,
                "Prop '{$deferida}' e Inertia::defer — nao pode vir no primeiro render."
            );
        }
        // eager de verdade continua vindo (senao o assert acima passaria por tela vazia)
        $this->assertArrayHasKey('server_time', $props);

        $partial = $this->inertiaPartialGet(
            '/ponto',
            ['kpis', 'serie_7dias'],
            'Ponto/Dashboard/Index'
        );
        $partial->assertStatus(200);
        $resolvidas = $partial->json('props');

        $this->assertArrayHasKey('kpis', $resolvidas);
        $this->assertEqualsCanonicalizing(
            ['colaboradores_ativos', 'presentes_agora', 'atrasos_hoje',
             'faltas_hoje', 'he_mes_minutos', 'aprovacoes_pendentes'],
            array_keys($resolvidas['kpis'])
        );

        // Série tem 7 dias (hoje + 6 anteriores)
        $this->assertCount(7, $resolvidas['serie_7dias']);
        $this->assertEqualsCanonicalizing(
            ['data', 'label', 'trabalhado', 'he'],
            array_keys($resolvidas['serie_7dias'][0])
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dashboard_shell_menu_contem_ponto_wr2(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto');

        $menu = $response->json('props.shell.menu');
        $this->assertIsArray($menu);
        $this->assertGreaterThan(0, count($menu), 'Menu do shell deve ter pelo menos 1 item');
    }
}
