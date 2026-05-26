<?php

declare(strict_types=1);

/**
 * Pest — KB-9.75 P5 parking lot · Tab bar Sells [Dashboard | Insights Jana].
 *
 * Cobertura estrutural (segue pattern SellsCommissionColumnTest, sem boot DB):
 *   - Index.tsx tem estado viewMode persistido em ls Tier 0
 *   - Tab bar markup presente (.vd-tabs-mode + 2 botões)
 *   - Conditional render: viewMode='insights' renderiza <SellsInsightsView/>
 *   - Componente SellsInsightsView.tsx existe + brief diário Jana + 4 análises
 *   - CSS sells-cowork-insights.css importado no inertia.css
 *
 * Refs:
 *   - resources/js/Pages/Sells/Index.tsx
 *   - resources/js/Pages/Sells/_components/SellsInsightsView.tsx
 *   - resources/css/sells-cowork-insights.css
 *   - resources/css/inertia.css
 *   - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md gap #13
 */

const TABS_INDEX_TSX_PATH = 'resources/js/Pages/Sells/Index.tsx';
const TABS_INSIGHTS_VIEW_PATH = 'resources/js/Pages/Sells/_components/SellsInsightsView.tsx';
const TABS_INSIGHTS_CSS_PATH = 'resources/css/sells-cowork-insights.css';
const TABS_INERTIA_CSS_PATH = 'resources/css/inertia.css';

function tabsReadIndexTsx(): string
{
    return file_get_contents(base_path(TABS_INDEX_TSX_PATH));
}

function tabsReadInsightsView(): string
{
    return file_get_contents(base_path(TABS_INSIGHTS_VIEW_PATH));
}

function tabsReadInsightsCss(): string
{
    return file_get_contents(base_path(TABS_INSIGHTS_CSS_PATH));
}

function tabsReadInertiaCss(): string
{
    return file_get_contents(base_path(TABS_INERTIA_CSS_PATH));
}

// ─── Index.tsx: estado viewMode ─────────────────────────────────────────────

it('Index.tsx declara estado viewMode com persistência ls Tier 0', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain("type ViewMode = 'dashboard' | 'insights'")
        ->toContain("useState<ViewMode>")
        ->toContain("ls.get('view_mode', 'dashboard')")
        ->toContain("ls.set('view_mode', viewMode)");
});

// ─── Index.tsx: tab bar markup ──────────────────────────────────────────────

it('Index.tsx renderiza tab bar com 2 botões Dashboard/Insights Jana', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain('className="vd-tabs-mode"')
        ->toContain("setViewMode('dashboard')")
        ->toContain("setViewMode('insights')")
        ->toContain('Dashboard')
        ->toContain('Insights Jana');
});

it('Index.tsx tab buttons têm semântica role=tab + aria-selected', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain('role="tab"')
        ->toContain("aria-selected={viewMode === 'dashboard'}")
        ->toContain("aria-selected={viewMode === 'insights'}");
});

// ─── Index.tsx: conditional render ──────────────────────────────────────────

it('Index.tsx faz conditional render do SellsInsightsView quando viewMode=insights', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain("viewMode === 'insights' ? (")
        ->toContain('<SellsInsightsView')
        ->toContain("import SellsInsightsView from './_components/SellsInsightsView'");
});

// ─── SellsInsightsView component ────────────────────────────────────────────

it('SellsInsightsView.tsx existe com props canônicas', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('export interface InsightsViewProps')
        ->toContain('sellKpis:')
        ->toContain('coworkAggregates?:')
        ->toContain('rows:')
        ->toContain('export default function SellsInsightsView');
});

it('SellsInsightsView renderiza brief diário Jana + 4 análises grid', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('vd-insights-brief')
        ->toContain('vd-insights-grid')
        ->toContain('Jana · Analista IA')
        // 4 análises
        ->toContain('Inadimplência')
        ->toContain('Faturamento')
        ->toContain('Top 5 clientes')
        ->toContain('Métodos de pagamento');
});

it('SellsInsightsView trata empty states sem crash', function () {
    $src = tabsReadInsightsView();
    expect($src)
        // Sparkline carregando
        ->toContain('Carregando sparkline')
        // Sem dados de clientes
        ->toContain('Sem dados de clientes')
        // Sem pagamentos
        ->toContain('Sem pagamentos registrados');
});

// ─── CSS tokens ─────────────────────────────────────────────────────────────

it('sells-cowork-insights.css define tokens .vd-tabs-mode-* + .vd-insights-*', function () {
    $src = tabsReadInsightsCss();
    expect($src)
        ->toContain('.sells-cowork .vd-tabs-mode')
        ->toContain('.sells-cowork .vd-tabs-mode-btn')
        ->toContain('.sells-cowork .vd-tabs-mode-btn.active')
        ->toContain('.sells-cowork .vd-insights')
        ->toContain('.sells-cowork .vd-insights-brief')
        ->toContain('.sells-cowork .vd-insights-grid')
        ->toContain('.sells-cowork .vd-insights-card')
        ->toContain('.sells-cowork .vd-insights-bucket');
});

it('inertia.css importa sells-cowork-insights.css', function () {
    $src = tabsReadInertiaCss();
    expect($src)->toContain('sells-cowork-insights.css');
});

it('sells-cowork-insights.css escopado em .sells-cowork (não vaza fora)', function () {
    $src = tabsReadInsightsCss();
    // Cada regra .vd-* prefixada por .sells-cowork
    expect(preg_match_all('/^\.vd-/m', $src))->toBe(0);
});
