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
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — os it() que leem o componente
 * `SellsInsightsView.tsx` (DELETADO) e o markup de tab bar de `Index.tsx`
 * (`type ViewMode`, `vd-tabs-mode`, `ls.get('view_mode'…)`, `businessName=`),
 * markers verificados ausentes. Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * ✅ Os it() do CSS `sells-cowork-insights.css` PERMANECEM ATIVOS — o arquivo CSS
 * existe e seus tokens (`.vd-tabs-mode*`, `.vd-insights-*`), o import em inertia.css
 * e a invariante de escopo `.sells-cowork` (zero regra `^.vd-` vazando) seguem verdes.
 * Não silenciar cobertura de DS viva.
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
    $path = base_path(TABS_INSIGHTS_VIEW_PATH);
    if (!file_exists($path)) {
        test()->markTestSkipped('SellsInsightsView.tsx não encontrado (legacy-quarantine: componente deletado)');
    }

    return (string) file_get_contents($path);
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
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Index.tsx: tab bar markup ──────────────────────────────────────────────

it('Index.tsx renderiza tab bar com 2 botões Dashboard/Insights Jana', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain('className="vd-tabs-mode"')
        ->toContain("setViewMode('dashboard')")
        ->toContain("setViewMode('insights')")
        ->toContain('Dashboard')
        ->toContain('Insights Jana');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx tab buttons têm semântica role=tab + aria-selected', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain('role="tab"')
        ->toContain("aria-selected={viewMode === 'dashboard'}")
        ->toContain("aria-selected={viewMode === 'insights'}");
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Index.tsx: conditional render ──────────────────────────────────────────

it('Index.tsx faz conditional render do SellsInsightsView quando viewMode=insights', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain("viewMode === 'insights' ? (")
        ->toContain('<SellsInsightsView')
        ->toContain("import SellsInsightsView from './_components/SellsInsightsView'");
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── SellsInsightsView component ────────────────────────────────────────────

it('SellsInsightsView.tsx existe com props canônicas', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('export interface InsightsViewProps')
        ->toContain('sellKpis:')
        ->toContain('coworkAggregates?:')
        ->toContain('rows:')
        ->toContain('export default function SellsInsightsView');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsInsightsView renderiza brief diário Jana + 4 análises grid', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('vd-insights-brief')
        ->toContain('vd-insights-grid')
        // V2 onda gaps r5: nome Jana agora dentro de h2 com span.dot (não literal "Jana · Analista IA")
        ->toContain('vd-insights-jh-id')
        ->toContain('Analista IA')
        // 4 análises
        ->toContain('Inadimplência')
        ->toContain('Faturamento')
        ->toContain('Top 5 clientes')
        ->toContain('Métodos de pagamento');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ── V2 onda gaps r5 — header Jana + KPIs + H2 + Ações ────────────────────────

it('SellsInsightsView V2 — GAP 3 JanaHeader dedicado com avatar + tenant breadcrumb', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('vd-insights-jh')
        ->toContain('vd-insights-jh-av')
        ->toContain('vd-insights-jh-tenant')
        ->toContain('vd-insights-jh-updated')
        ->toContain('Configurar')
        ->toContain('Exportar');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsInsightsView V2 — GAP 4 Brief header com 📅 + IA pill + Ouvir áudio', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('vd-insights-brief-h-l')
        ->toContain('Brief diário')
        ->toContain('vd-insights-pill ia')
        ->toContain('vd-insights-audio')
        ->toContain('Ouvir áudio');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsInsightsView V2 — GAP 1 KPIs row dedicado Jana (4 cards próprios)', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('vd-insights-kpis')
        ->toContain('vd-insights-kpi-h')
        ->toContain('vd-insights-kpi-v')
        ->toContain('Faturamento mês')
        ->toContain('Inadimplência total')
        ->toContain('Ticket médio')
        ->toContain('PIX hoje');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsInsightsView V2 — GAP 5 H2 separadores hierárquicos', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('vd-insights-h2')
        ->toContain('ANÁLISES PRINCIPAIS')
        ->toContain('SUGERE');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsInsightsView V2 — GAP 2 Lista AÇÕES sugeridas estruturadas (AcaoRow)', function () {
    $src = tabsReadInsightsView();
    expect($src)
        ->toContain('vd-insights-acoes')
        ->toContain('vd-insights-acao')
        ->toContain('vd-insights-acao-ic')
        ->toContain('vd-insights-acao-text')
        ->toContain('vd-insights-cta')
        // 5 sinais geradores de ação
        ->toContain('regua-whatsapp')
        ->toContain('negociar-top')
        ->toContain('investigar-ticket')
        ->toContain('pix-adocao')
        ->toContain('preventivo-pendentes');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsInsightsView V2 — sparkline range labels D-N e hoje', function () {
    $src = tabsReadInsightsView();
    expect($src)->toContain('vd-insights-spark-range');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx passa businessName + businessId pro SellsInsightsView', function () {
    $src = tabsReadIndexTsx();
    expect($src)
        ->toContain('businessName={businessName}')
        ->toContain('businessId={businessIdShared}')
        ->toContain('sharedPage.props.business?.name');
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('sells-cowork-insights.css define tokens V2 (JanaHeader + KPIs + H2 + Ações)', function () {
    $src = tabsReadInsightsCss();
    expect($src)
        // GAP 3 JanaHeader
        ->toContain('.sells-cowork .vd-insights-jh')
        ->toContain('.sells-cowork .vd-insights-jh-av')
        ->toContain('.sells-cowork .vd-insights-jh-btn')
        // GAP 4 Brief header
        ->toContain('.sells-cowork .vd-insights-pill.ia')
        ->toContain('.sells-cowork .vd-insights-audio')
        // GAP 1 KPIs row
        ->toContain('.sells-cowork .vd-insights-kpis')
        ->toContain('.sells-cowork .vd-insights-kpi-v')
        // GAP 5 H2 separadores
        ->toContain('.sells-cowork .vd-insights-h2')
        // GAP 2 Ações
        ->toContain('.sells-cowork .vd-insights-acoes')
        ->toContain('.sells-cowork .vd-insights-acao.tone-rose')
        ->toContain('.sells-cowork .vd-insights-cta.danger');
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
    // quarantine-reason: SellsInsightsView.tsx deletado + tab bar viewMode removida do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

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
