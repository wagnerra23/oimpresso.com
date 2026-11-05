<?php

namespace Tests\Feature\Modules\Financeiro;

use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Tests\Feature\FinanceiroTestCase;

/**
 * Tela /financeiro/relatorios + alias /financeiro/dashboard.
 *
 * Cobertura:
 *  - alias /financeiro/dashboard responde 301 -> /financeiro
 *  - GET /financeiro/relatorios sem login -> 302 pra login
 *  - GET /financeiro/relatorios com admin -> 200 + props (dre|fluxo|resumo|filters)
 *  - filtros data_de/data_ate aplicam e mantêm no payload
 *  - export CSV retorna text/csv 200
 *
 * Pattern: classe PHPUnit (mesmo da CategoriaCrudTest pra evitar conflito Pest).
 */
class RelatoriosTest extends FinanceiroTestCase
{
    private const NUMERO_TEST = 'REL_TEST_';

    protected function tearDown(): void
    {
        Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('numero', 'like', self::NUMERO_TEST . '%')
            ->forceDelete();

        parent::tearDown();
    }

    /** Alias legado: /financeiro/dashboard redireciona 301 (hoje para /financeiro/unificado). */
    public function test_dashboard_alias_redireciona_301(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/financeiro/dashboard');

        $this->assertEquals(
            301,
            $response->status(),
            '/financeiro/dashboard deveria redirecionar 301 pra canonical /financeiro'
        );

        $this->assertStringContainsString(
            '/financeiro',
            (string) $response->headers->get('Location'),
            'Location deveria apontar pra /financeiro'
        );
    }

    public function test_relatorios_sem_login_redireciona(): void
    {
        // Sem actAsAdmin — sem auth.
        $response = $this->get('/financeiro/relatorios');

        $this->assertContains(
            $response->status(),
            [302, 401, 403],
            'Sem auth deveria 302 pra login (ou 401/403). Got: ' . $response->status()
        );
    }

    public function test_relatorios_com_admin_responde_200(): void
    {
        $this->actAsAdmin();

        $response = $this->inertiaGet('/financeiro/relatorios');

        $this->assertEquals(
            200,
            $response->status(),
            'Relatorios deveria 200 com admin logado. Got: ' . $response->status()
        );
    }

    /**
     * UC-REL-01 + UC-REL-02 — o payload traz fluxo semanal e resumo do período.
     * UC-REL-05 — e NÃO traz mais `dre`: a DRE saiu desta tela em 2026-05-20 e vive em
     * /financeiro/dre (DreController). Antes este teste exigia `dre` e caía ao ser ligado.
     */
    public function test_uc_rel_01_02_05_props_fluxo_e_resumo_sem_dre(): void
    {
        $this->actAsAdmin();

        $response = $this->inertiaGet('/financeiro/relatorios');
        $payload = json_decode($response->getContent(), true);

        $this->assertIsArray($payload, 'Resposta Inertia JSON inválida.');
        $this->assertArrayHasKey('props', $payload);

        $props = $payload['props'];
        $this->assertArrayHasKey('filters', $props);
        $this->assertArrayHasKey('fluxo', $props);
        $this->assertArrayHasKey('resumo', $props);
        $this->assertArrayNotHasKey('dre', $props, 'UC-REL-05: a DRE mora em /financeiro/dre, não aqui.');

        // Fluxo (UC-REL-01)
        $this->assertArrayHasKey('semanas', $props['fluxo']);
        $this->assertArrayHasKey('totais', $props['fluxo']);
        foreach (['projetado_receber', 'projetado_pagar', 'realizado_receber', 'realizado_pagar', 'saldo_projetado', 'saldo_realizado'] as $k) {
            $this->assertArrayHasKey($k, $props['fluxo']['totais']);
        }

        // Resumo (UC-REL-02)
        foreach (['a_receber', 'a_pagar', 'recebido_periodo', 'pago_periodo', 'saldo_aberto', 'saldo_periodo'] as $k) {
            $this->assertArrayHasKey($k, $props['resumo']);
        }
        $this->assertArrayHasKey('vencidos_qtd', $props['resumo']['a_receber']);
        $this->assertArrayHasKey('vencidos_qtd', $props['resumo']['a_pagar']);
    }

    /** UC-REL-03 — o filtro de período chega ao payload. */
    public function test_uc_rel_03_filtros_data_aplicam_no_payload(): void
    {
        $this->actAsAdmin();

        $response = $this->inertiaGet('/financeiro/relatorios', [
            'data_de'  => '2026-01-01',
            'data_ate' => '2026-01-31',
        ]);

        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('2026-01-01', $payload['props']['filters']['data_de']);
        $this->assertEquals('2026-01-31', $payload['props']['filters']['data_ate']);

        // As semanas do fluxo ficam dentro do range pedido (começam na segunda da 1ª semana).
        $semanas = $payload['props']['fluxo']['semanas'];
        $this->assertNotEmpty($semanas);
        foreach ($semanas as $sem) {
            $this->assertLessThanOrEqual('2026-01-31', $sem['semana_inicio']);
            $this->assertGreaterThanOrEqual('2025-12-29', $sem['semana_inicio']);
        }
    }

    public function test_filtros_invertidos_sao_corrigidos(): void
    {
        $this->actAsAdmin();

        // de > ate → controller deve trocar.
        $response = $this->inertiaGet('/financeiro/relatorios', [
            'data_de'  => '2026-03-31',
            'data_ate' => '2026-01-01',
        ]);

        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('2026-01-01', $payload['props']['filters']['data_de']);
        $this->assertEquals('2026-03-31', $payload['props']['filters']['data_ate']);
    }

    public function test_export_csv_retorna_csv(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/financeiro/relatorios/export-csv?tipo=fluxo&data_de=2026-01-01&data_ate=2026-01-31');

        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get('Content-Type', '')
        );
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition', '')
        );
    }

    /**
     * UC-REL-06 — o fluxo soma só títulos da empresa logada, e o GET não escreve.
     * O título da empresa 2 (fictícia) no mesmo período não entra na conta.
     */
    public function test_uc_rel_06_fluxo_soma_so_a_empresa_e_get_nao_escreve(): void
    {
        $this->actAsAdmin();
        $businessId = $this->business->id;
        $outra = \App\Business::query()->where('id', '!=', $businessId)->value('id');

        $base = [
            'status' => 'aberto', 'moeda' => 'BRL', 'emissao' => '2026-02-01',
            'competencia_mes' => '2026-02', 'origem' => 'manual', 'created_by' => $this->admin->id,
        ];
        Titulo::query()->create($base + [
            'business_id' => $businessId, 'numero' => self::NUMERO_TEST . 'R001', 'tipo' => 'receber',
            'cliente_descricao' => 'TEST CLIENTE', 'valor_total' => 1000, 'valor_aberto' => 1000, 'vencimento' => '2026-02-10',
        ]);
        Titulo::query()->create($base + [
            'business_id' => $businessId, 'numero' => self::NUMERO_TEST . 'P001', 'tipo' => 'pagar',
            'cliente_descricao' => 'TEST FORNECEDOR', 'valor_total' => 300, 'valor_aberto' => 300, 'vencimento' => '2026-02-11',
        ]);
        if ($outra) {
            Titulo::query()->withoutGlobalScope(BusinessScopeImpl::class)->create($base + [
                'business_id' => $outra, 'numero' => self::NUMERO_TEST . 'X001', 'tipo' => 'receber',
                'cliente_descricao' => 'TEST OUTRA EMPRESA', 'valor_total' => 5000, 'valor_aberto' => 5000, 'vencimento' => '2026-02-10',
            ]);
        }

        $antes = Titulo::query()->withoutGlobalScope(BusinessScopeImpl::class)->count();

        $response = $this->inertiaGet('/financeiro/relatorios', [
            'data_de'  => '2026-02-09',
            'data_ate' => '2026-02-15',
        ]);
        $payload = json_decode($response->getContent(), true);
        $totais = $payload['props']['fluxo']['totais'];

        // Só os títulos de teste caem nesta semana no CI (banco semeado sem títulos).
        // Se houver outros da empresa no período, a soma só pode ser maior — nunca incluir os 5000 da outra.
        $this->assertGreaterThanOrEqual(1000.0, (float) $totais['projetado_receber']);
        $this->assertGreaterThanOrEqual(300.0, (float) $totais['projetado_pagar']);
        $semOutros = Titulo::query()->where('tipo', 'receber')->whereIn('status', ['aberto', 'parcial'])
            ->whereBetween('vencimento', ['2026-02-09', '2026-02-15'])->sum('valor_aberto');
        $this->assertEqualsWithDelta((float) $semOutros, (float) $totais['projetado_receber'], 0.001,
            'O fluxo deveria somar exatamente os títulos a receber da empresa logada.');

        $this->assertSame($antes, Titulo::query()->withoutGlobalScope(BusinessScopeImpl::class)->count(), 'GET /relatorios não pode escrever.');
    }
}
