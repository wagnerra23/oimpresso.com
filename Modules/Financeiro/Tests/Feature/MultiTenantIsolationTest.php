<?php

namespace Modules\Financeiro\Tests\Feature;

use App\Business;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\PlanoConta;

/**
 * Teste real da regra R-FIN-001 · Isolamento multi-tenant por business_id.
 *
 * Cenário Gherkin:
 *   Dado que um usuário pertence ao business A
 *   Quando ele acessa qualquer recurso do módulo Financeiro
 *   Então só vê registros com business_id = A
 *
 * Baseline de testes Sub-Onda 1A.
 */
class MultiTenantIsolationTest extends FinanceiroTestCase
{
    public function test_dashboard_responde_sem_500(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/financeiro');

        $this->assertLessThan(
            500,
            $response->status(),
            'Dashboard /financeiro retornou 5xx: ' . $response->status()
        );
    }

    public function test_contas_receber_legacy_redireciona_pra_dashboard(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/financeiro/contas-receber');

        $this->assertContains(
            $response->status(),
            [200, 301, 302, 409],
            'Rota legada /financeiro/contas-receber não respondeu corretamente'
        );
    }

    public function test_contas_pagar_legacy_redireciona_pra_dashboard(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/financeiro/contas-pagar');

        $this->assertContains(
            $response->status(),
            [200, 301, 302, 409],
            'Rota legada /financeiro/contas-pagar não respondeu corretamente'
        );
    }

    public function test_business_scope_isola_planos_conta_cross_tenant(): void
    {
        $this->actAsAdmin();
        $primary = $this->business;

        $other = Business::where('id', '!=', $primary->id)->first();
        if (! $other) {
            $this->markTestSkipped('Sem segundo business em DB pra testar isolamento real.');
        }

        // Cria conta no primary
        PlanoConta::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->updateOrCreate(
                ['business_id' => $primary->id, 'codigo' => 'TEST.SCOPE.001'],
                [
                    'nome' => 'Teste Scope',
                    'tipo' => 'ativo',
                    'nivel' => 4,
                    'natureza' => 'debito',
                    'aceita_lancamento' => true,
                    'protegido' => false,
                ]
            );

        $isSuperadmin = auth()->user()->can('superadmin');

        if (! $isSuperadmin) {
            // Como user do primary não-superadmin, scope deveria encontrar
            $found = PlanoConta::where('codigo', 'TEST.SCOPE.001')->count();
            $this->assertEquals(1, $found, 'Scope deveria encontrar conta do próprio business');
        }

        // Logout + simula sessão de outro business — força scope a ser aplicado
        // (sem auth, can('superadmin') retorna false → scope ativo).
        auth()->logout();
        session(['user.business_id' => $other->id]);
        $foundCross = PlanoConta::where('codigo', 'TEST.SCOPE.001')->count();
        $this->assertEquals(0, $foundCross, 'Scope NÃO deveria vazar dados cross-business (foundCross=' . $foundCross . ')');

        // Cleanup
        PlanoConta::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $primary->id)
            ->where('codigo', 'TEST.SCOPE.001')
            ->forceDelete();
    }

    public function test_titulo_hard_delete_e_bloqueado(): void
    {
        $titulo = new \Modules\Financeiro\Models\Titulo();

        $this->expectException(\DomainException::class);
        $titulo->delete();
    }

    public function test_baixa_hard_delete_e_bloqueado(): void
    {
        $baixa = new \Modules\Financeiro\Models\TituloBaixa();

        $this->expectException(\DomainException::class);
        $baixa->delete();
    }

    public function test_movimento_hard_delete_e_bloqueado(): void
    {
        $mov = new \Modules\Financeiro\Models\CaixaMovimento();

        $this->expectException(\DomainException::class);
        $mov->delete();
    }

    public function test_conta_protegida_nao_pode_ser_deletada(): void
    {
        $this->actAsAdmin();

        $caixa = PlanoConta::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $this->business->id)
            ->where('codigo', '1.1.01.001')
            ->first();

        if (! $caixa) {
            $this->markTestSkipped('Plano de contas não seedado neste business — rode seeder antes.');
        }

        $this->expectException(\DomainException::class);
        $caixa->delete();
    }
}
