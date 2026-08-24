<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * @covers-us US-PONT-001
 */
class AprovacaoTest extends PontoTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function index_exige_autenticacao(): void
    {
        $this->get('/ponto/aprovacoes')->assertRedirect('/login');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_retorna_inertia_com_estrutura_esperada(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto/aprovacoes');

        $this->assertInertiaComponent($response, 'Ponto/Aprovacoes/Index');

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
        // `aprovacoes` e `contagens` sao Inertia::defer no AprovacaoController
        $this->assertArrayNotHasKey('aprovacoes', $props);
        $this->assertArrayNotHasKey('contagens', $props);
        // eager continua vindo
        $this->assertArrayHasKey('filtros', $props);
        $this->assertArrayHasKey('tipos', $props);

        $partial = $this->inertiaPartialGet(
            '/ponto/aprovacoes',
            ['aprovacoes', 'contagens'],
            'Ponto/Aprovacoes/Index'
        );
        $partial->assertStatus(200);
        $resolvidas = $partial->json('props');
        $this->assertArrayHasKey('aprovacoes', $resolvidas);

        // Contagens tem 6 estados esperados
        $this->assertEqualsCanonicalizing(
            ['RASCUNHO', 'PENDENTE', 'APROVADA', 'REJEITADA', 'APLICADA', 'CANCELADA'],
            array_keys($resolvidas['contagens'])
        );

        // Tipos é array de {value, label}
        $this->assertCount(8, $props['tipos']);
        $this->assertArrayHasKey('value', $props['tipos'][0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function filtro_por_estado_funciona(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/ponto/aprovacoes', ['estado' => 'APROVADA']);

        $this->assertInertiaComponent($response, 'Ponto/Aprovacoes/Index');
        $this->assertEquals('APROVADA', $response->json('props.filtros.estado'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejeitar_exige_motivo(): void
    {
        $this->actAsAdmin();

        // Sem motivo deve dar 422
        $response = $this->post('/ponto/aprovacoes/999999/rejeitar', [], ['Accept' => 'application/json']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['motivo']);
    }
}
