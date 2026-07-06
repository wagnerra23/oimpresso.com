<?php

namespace Modules\Ponto\Tests\Feature;

/**
 * Contrato defer backend↔frontend do Dashboard Ponto (US-PONT-006).
 *
 * Âncora de contrato (não derivado da implementação):
 *   RUNBOOK-inertia-defer-pattern.md §3 — "Frontend wrap em
 *   `<Deferred data=\"...\" fallback={skeleton}>`" + proibicoes.md §Sempre fazer.
 *
 * Regressão real que este teste trava (re-grade cego 2026-07-06): o
 * DashboardController entregava TODAS as props via Inertia::defer mas a Page
 * Ponto/Dashboard/Index.tsx desreferenciava `kpis.colaboradores_ativos` direto
 * no first render (prop deferida = undefined até o auto-fetch async) →
 * TypeError → tela quebrada. Espelha o idioma estrutural do
 * Modules/Governance/Tests/Feature/InertiaDeferAuditTest.php (metade backend);
 * este cobre a metade FRONTEND que faltava.
 */
class DashboardDeferredContractTest extends PontoTestCase
{
    private const PAGE_PATH = 'resources/js/Pages/Ponto/Dashboard/Index.tsx';

    private const CONTROLLER_PATH = 'Modules/Ponto/Http/Controllers/DashboardController.php';

    /** Props que o Controller entrega via Inertia::defer (server_time é eager). */
    private const DEFERRED_PROPS = [
        'kpis',
        'aprovacoes',
        'atividade_recente',
        'serie_7dias',
        'presenca_agora',
        'alertas',
    ];

    #[\PHPUnit\Framework\Attributes\Test]
    public function controller_defere_as_seis_props_caras(): void
    {
        $source = file_get_contents(base_path(self::CONTROLLER_PATH));
        $this->assertNotFalse($source);

        foreach (self::DEFERRED_PROPS as $prop) {
            $this->assertMatchesRegularExpression(
                "/'{$prop}'\s*=>\s*Inertia::defer\(/",
                $source,
                "Prop '{$prop}' deveria ser Inertia::defer no DashboardController (RUNBOOK-inertia-defer-pattern.md §2.1)."
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function page_importa_deferred_do_inertia(): void
    {
        $source = file_get_contents(base_path(self::PAGE_PATH));
        $this->assertNotFalse($source);

        $this->assertMatchesRegularExpression(
            '/import\s*\{[^}]*\bDeferred\b[^}]*\}\s*from\s*[\'"]@inertiajs\/react[\'"]/',
            $source,
            'Page com props deferidas DEVE importar <Deferred> de @inertiajs/react — sem ele o first render desreferencia undefined e crasha (RUNBOOK-inertia-defer-pattern.md §3).'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cada_prop_deferida_tem_wrap_deferred_com_fallback(): void
    {
        $source = file_get_contents(base_path(self::PAGE_PATH));
        $this->assertNotFalse($source);

        foreach (self::DEFERRED_PROPS as $prop) {
            $this->assertMatchesRegularExpression(
                '/<Deferred\s+data="' . preg_quote($prop, '/') . '"\s+fallback=/',
                $source,
                "Prop deferida '{$prop}' precisa de <Deferred data=\"{$prop}\" fallback={skeleton}> na Page — contrato RUNBOOK-inertia-defer-pattern.md §3."
            );
        }
    }
}
