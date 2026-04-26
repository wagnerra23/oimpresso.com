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

    public function test_relatorios_props_tem_estrutura_esperada(): void
    {
        $this->actAsAdmin();

        $response = $this->inertiaGet('/financeiro/relatorios');
        $payload = json_decode($response->getContent(), true);

        $this->assertIsArray($payload, 'Resposta Inertia JSON inválida.');
        $this->assertArrayHasKey('props', $payload);

        $props = $payload['props'];
        $this->assertArrayHasKey('filters', $props);
        $this->assertArrayHasKey('dre', $props);
        $this->assertArrayHasKey('fluxo', $props);
        $this->assertArrayHasKey('resumo', $props);

        // DRE shape
        $this->assertArrayHasKey('meses', $props['dre']);
        $this->assertArrayHasKey('despesas_por_cat', $props['dre']);
        $this->assertArrayHasKey('totais', $props['dre']);
        $this->assertArrayHasKey('receita', $props['dre']['totais']);
        $this->assertArrayHasKey('despesa', $props['dre']['totais']);
        $this->assertArrayHasKey('resultado', $props['dre']['totais']);

        // Fluxo shape
        $this->assertArrayHasKey('semanas', $props['fluxo']);
        $this->assertArrayHasKey('totais', $props['fluxo']);
        $this->assertArrayHasKey('saldo_projetado', $props['fluxo']['totais']);
        $this->assertArrayHasKey('saldo_realizado', $props['fluxo']['totais']);

        // Resumo shape
        $this->assertArrayHasKey('a_receber', $props['resumo']);
        $this->assertArrayHasKey('a_pagar', $props['resumo']);
        $this->assertArrayHasKey('saldo_aberto', $props['resumo']);
        $this->assertArrayHasKey('saldo_periodo', $props['resumo']);
    }

    public function test_filtros_data_aplicam_no_payload(): void
    {
        $this->actAsAdmin();

        $response = $this->inertiaGet('/financeiro/relatorios', [
            'data_de'  => '2026-01-01',
            'data_ate' => '2026-01-31',
        ]);

        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('2026-01-01', $payload['props']['filters']['data_de']);
        $this->assertEquals('2026-01-31', $payload['props']['filters']['data_ate']);

        // Único mês no range.
        $this->assertCount(1, $payload['props']['dre']['meses']);
        $this->assertEquals('2026-01', $payload['props']['dre']['meses'][0]['mes']);
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

        $response = $this->get('/financeiro/relatorios/export-csv?tipo=dre&data_de=2026-01-01&data_ate=2026-01-31');

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

    public function test_dre_soma_titulos_do_business(): void
    {
        $this->actAsAdmin();
        $businessId = $this->business->id;

        // Cria 1 título a receber e 1 a pagar pra competência 2026-02
        Titulo::query()->create([
            'business_id' => $businessId,
            'numero' => self::NUMERO_TEST . 'R001',
            'tipo' => 'receber',
            'status' => 'aberto',
            'cliente_descricao' => 'TEST CLIENTE',
            'valor_total' => 1000,
            'valor_aberto' => 1000,
            'moeda' => 'BRL',
            'emissao' => '2026-02-01',
            'vencimento' => '2026-02-15',
            'competencia_mes' => '2026-02',
            'origem' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        Titulo::query()->create([
            'business_id' => $businessId,
            'numero' => self::NUMERO_TEST . 'P001',
            'tipo' => 'pagar',
            'status' => 'aberto',
            'cliente_descricao' => 'TEST FORNECEDOR',
            'valor_total' => 300,
            'valor_aberto' => 300,
            'moeda' => 'BRL',
            'emissao' => '2026-02-01',
            'vencimento' => '2026-02-20',
            'competencia_mes' => '2026-02',
            'origem' => 'manual',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->inertiaGet('/financeiro/relatorios', [
            'data_de'  => '2026-02-01',
            'data_ate' => '2026-02-28',
        ]);

        $payload = json_decode($response->getContent(), true);
        $mes = collect($payload['props']['dre']['meses'])->firstWhere('mes', '2026-02');

        $this->assertNotNull($mes, 'Mês 2026-02 deveria existir no DRE');
        $this->assertGreaterThanOrEqual(1000, (float) $mes['receita']);
        $this->assertGreaterThanOrEqual(300, (float) $mes['despesa']);
        $this->assertGreaterThanOrEqual(700, (float) $mes['resultado']);
    }
}
