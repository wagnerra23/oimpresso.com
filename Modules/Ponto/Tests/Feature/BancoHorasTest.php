<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * @covers-us US-PONT-002
 * @covers-us US-PONT-003
 */
class BancoHorasTest extends PontoTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function index_renderiza_inertia_com_totais(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto/banco-horas');

        $this->assertInertiaComponent($response, 'Ponto/BancoHoras/Index');

        // ── CONTRATO DEFER (corrigido 2026-08-24) ────────────────────────────────
        // Este caso afirmava que as props caras vinham no PRIMEIRO render. Elas NAO
        // vem: o Controller as entrega via `Inertia::defer` (RUNBOOK-inertia-defer-pattern
        // + proibicoes.md §Sempre-fazer), e prop deferida ausente do payload inicial E o
        // ponto do padrao. O irmao `DashboardDeferredContractTest (do Dashboard)` PROVA o defer e passava
        // verde ao lado deste — os dois no mesmo modulo, contradizendo-se, porque NENHUM
        // rodava em lane. Medido no CT100 em 2026-08-23.
        //
        // Agora o caso prova o contrato de verdade, nos DOIS lados: ausente no eager,
        // presente e bem-formado no partial reload.
        $props = $response->json('props');
        // `saldos` e `totais` sao Inertia::defer no BancoHorasController
        $this->assertArrayNotHasKey('saldos', $props);
        $this->assertArrayNotHasKey('totais', $props);

        $partial = $this->inertiaPartialGet(
            '/ponto/banco-horas',
            ['saldos', 'totais'],
            'Ponto/BancoHoras/Index'
        );
        $partial->assertStatus(200);
        $resolvidas = $partial->json('props');
        $this->assertArrayHasKey('saldos', $resolvidas);

        $this->assertEqualsCanonicalizing(
            ['credito_total', 'debito_total', 'colaboradores_credito', 'colaboradores_debito'],
            array_keys($resolvidas['totais'])
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ajuste_manual_exige_minutos_e_observacao(): void
    {
        $this->actAsAdmin();

        // Sem campos
        $response = $this->post('/ponto/banco-horas/1/ajuste', [], ['Accept' => 'application/json']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['minutos', 'observacao']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function show_retorna_404_para_colaborador_inexistente(): void
    {
        $this->actAsAdmin();
        $this->inertiaGet('/ponto/banco-horas/999999')->assertStatus(404);
    }
}
