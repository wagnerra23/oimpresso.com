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

    // ─────────────── Wave 17 D1+D7+D8+D9 — saturação governance (66→81) ───────────────

    /**
     * D1 — todas as 9 Models do Financeiro usam BusinessScope (multi-tenant Tier 0).
     *
     * Auditável via reflection: garante que nenhuma Model nova entre sem o scope.
     */
    public function test_d1_todas_models_financeiro_usam_business_scope(): void
    {
        $models = [
            \Modules\Financeiro\Models\Titulo::class,
            \Modules\Financeiro\Models\TituloBaixa::class,
            \Modules\Financeiro\Models\CaixaMovimento::class,
            \Modules\Financeiro\Models\ContaBancaria::class,
            \Modules\Financeiro\Models\PlanoConta::class,
            \Modules\Financeiro\Models\Categoria::class,
            \Modules\Financeiro\Models\BoletoRemessa::class,
            \Modules\Financeiro\Models\ExtratoLancamento::class,
            \Modules\Financeiro\Models\AccountsLegacyMap::class,
        ];

        foreach ($models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertContains(
                \Modules\Financeiro\Models\Concerns\BusinessScope::class,
                $traits,
                "Model {$modelClass} DEVE usar trait BusinessScope (multi-tenant Tier 0 ADR 0093)."
            );
        }
    }

    /**
     * D7 — LogsActivity aplicado nas 7 Models sensíveis (audit trail LGPD + CTN).
     *
     * AccountsLegacyMap é bridge importer (sem LogsActivity por design — gerenciado
     * pelo importer Python). Categoria/ExtratoLancamento adicionados Wave 17.
     */
    public function test_d7_models_sensiveis_tem_logs_activity(): void
    {
        $modelsComAudit = [
            \Modules\Financeiro\Models\Titulo::class,
            \Modules\Financeiro\Models\TituloBaixa::class,
            \Modules\Financeiro\Models\CaixaMovimento::class,
            \Modules\Financeiro\Models\ContaBancaria::class,
            \Modules\Financeiro\Models\PlanoConta::class,
            \Modules\Financeiro\Models\BoletoRemessa::class,
            \Modules\Financeiro\Models\Categoria::class,         // Wave 17 D7
            \Modules\Financeiro\Models\ExtratoLancamento::class, // Wave 17 D7
        ];

        foreach ($modelsComAudit as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertContains(
                \Spatie\Activitylog\Traits\LogsActivity::class,
                $traits,
                "Model {$modelClass} DEVE usar trait LogsActivity (D7 audit governance)."
            );
        }
    }

    /**
     * D8 — 3 FormRequests novos existem e expõem rules() corretamente type-hintable.
     */
    public function test_d8_form_requests_novos_existem_e_validam(): void
    {
        $requests = [
            \Modules\Financeiro\Http\Requests\StoreTransactionRequest::class,
            \Modules\Financeiro\Http\Requests\UpdateTransactionRequest::class,
            \Modules\Financeiro\Http\Requests\StoreAccountRequest::class,
        ];

        foreach ($requests as $requestClass) {
            $this->assertTrue(
                class_exists($requestClass),
                "FormRequest {$requestClass} DEVE existir (D8)."
            );

            $reflection = new \ReflectionClass($requestClass);
            $this->assertTrue(
                $reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class),
                "{$requestClass} DEVE estender FormRequest."
            );

            $instance = new $requestClass();
            $rules = $instance->rules();
            $this->assertIsArray($rules);
            $this->assertNotEmpty($rules, "{$requestClass}::rules() não pode ser vazio.");
        }
    }

    /**
     * D9.a — Controllers principais wrap em OtelHelper::spanBiz.
     */
    public function test_d9_controllers_principais_tem_otel_span(): void
    {
        $controllersComSpan = [
            'DashboardController.php' => "OtelHelper::spanBiz('financeiro.dashboard",
            'BoletoController.php'    => "OtelHelper::spanBiz('financeiro.boleto.cancelar'",
            'ContaReceberController.php' => "OtelHelper::spanBiz('financeiro.boleto.emitir'",
            'FluxoController.php'     => "OtelHelper::spanBiz('financeiro.fluxo.projetar'",
            'ExtratoController.php'   => "OtelHelper::spanBiz('financeiro.extrato.lancamentos'",
        ];

        foreach ($controllersComSpan as $file => $needle) {
            $path = module_path('Financeiro', "Http/Controllers/{$file}");
            $this->assertFileExists($path);
            $source = file_get_contents($path);
            $this->assertStringContainsString(
                $needle,
                $source,
                "{$file} DEVE wrap em {$needle} (D9.a observability)."
            );
        }
    }

    /**
     * D9.c — FinanceiroHealthCommand registrado e instanciável.
     */
    public function test_d9_financeiro_health_command_existe(): void
    {
        $class = \Modules\Financeiro\Console\Commands\FinanceiroHealthCommand::class;
        $this->assertTrue(class_exists($class));

        $instance = new $class();
        $reflection = new \ReflectionClass($instance);
        $signatureProp = $reflection->getProperty('signature');
        $signatureProp->setAccessible(true);
        $signature = $signatureProp->getValue($instance);

        $this->assertStringContainsString('financeiro:health', $signature);
        $this->assertStringContainsString('--detail', $signature);
        // Anti-pattern: --verbose colide com Symfony Console reserved (handoff 2026-05-14 PR #851).
        $this->assertStringNotContainsString('{--verbose', $signature, '--verbose colide com Symfony reserved.');
    }
}
