<?php

declare(strict_types=1);

/**
 * US-SELL-016 — Multiseleção + ações em lote (Grade Avançada).
 *
 * Estrutura: Pest test ESTRUTURAL (file_get_contents + regex) — pattern
 * canon US-SELL-008/021 do projeto (auto-mem feedback_tenancy_changes_require_pest_local
 * dispensa banco real pra mudanças que adicionam endpoint mas seguem
 * scope/Controller/Model multi-tenant existentes — só nova action sem
 * Model novo + whitelist params).
 *
 * Anti-regressão Tier 0 (ADR 0093 multi-tenant):
 *   - bulk-print SEMPRE filtra business_id ANTES de WHERE IN (cross-tenant fail-secure)
 *   - bulk-export SEMPRE filtra business_id ANTES de WHERE IN
 *   - Permission gate (direct_sell.view + variants) em ambos endpoints
 *   - IDs são sanitizados (intval + filter > 0) — anti-injection
 *   - Anti-DoS: limit max 200 IDs por request
 *
 * Frontend:
 *   - SellsBulkActionsBar.tsx existe + endpoints corretos
 *   - SellsGradeAvancada.tsx renderiza Checkbox por linha + checkbox header
 *   - Index.tsx lift state up (selectedIds: Set<number>)
 *   - selectedIds reseta quando filtro muda
 *
 * Refs: ADR 0136 (Sells Grade Avançada toggle), ADR 0093 (multi-tenant Tier 0).
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — só os it() de frontend que
 * leem `SellsBulkActionsBar.tsx` / `SellsGradeAvancada.tsx` (DELETADOS) e o markup
 * de `Index.tsx` já refatorado (handlers `totals=`/`onToggleSelect=`/effect de
 * reset removidos). file_get_contents num componente ausente falha; NÃO é bug de
 * produto. Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * 🔴 Os it() de BACKEND (bulkPrint/bulkExport/totals) e ROTAS abaixo PERMANECEM
 * ATIVOS — são guards Tier-0/segurança VIVOS que PASSAM HOJE: scope business_id
 * ANTES do WHERE IN (cross-tenant fail-secure ADR 0093), permission gate abort(403),
 * sanitização intval de IDs (anti-SQL-injection/type-juggling), whitelist de colunas,
 * anti-DoS 200. Silenciá-los violaria "multi-tenant Tier 0 IRREVOGÁVEL".
 */

const SELL_CONTROLLER_PATH_BULK = 'app/Http/Controllers/SellController.php';
const ROUTES_PATH_BULK = 'routes/web.php';
const BULK_BAR_PATH = 'resources/js/Pages/Sells/_components/SellsBulkActionsBar.tsx';
const GRADE_PATH_BULK = 'resources/js/Pages/Sells/_components/SellsGradeAvancada.tsx';
const INDEX_PATH_BULK = 'resources/js/Pages/Sells/Index.tsx';

function readControllerBulk(): string
{
    return file_get_contents(base_path(SELL_CONTROLLER_PATH_BULK));
}

function readRoutesBulk(): string
{
    return file_get_contents(base_path(ROUTES_PATH_BULK));
}

function readBulkBar(): string
{
    $path = base_path(BULK_BAR_PATH);
    if (!file_exists($path)) {
        test()->markTestSkipped('SellsBulkActionsBar.tsx não encontrado (legacy-quarantine: componente deletado)');
    }

    return (string) file_get_contents($path);
}

function readGradeAvancada(): string
{
    $path = base_path(GRADE_PATH_BULK);
    if (!file_exists($path)) {
        test()->markTestSkipped('SellsGradeAvancada.tsx não encontrado (legacy-quarantine: componente deletado)');
    }

    return (string) file_get_contents($path);
}

function readIndexBulk(): string
{
    return file_get_contents(base_path(INDEX_PATH_BULK));
}

// ─── Backend: bulkPrint endpoint ────────────────────────────────────────────

it('SellController@bulkPrint existe (US-SELL-016)', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/public function bulkPrint\\(/');
});

it('bulkPrint tem permission gate canon (direct_sell.view + variants) — abort 403 fail-secure', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkPrint[\\s\\S]*?direct_sell\\.view[\\s\\S]*?view_own_sell_only[\\s\\S]*?view_commission_agent_sell[\\s\\S]*?abort\\(403\\)/');
});

it('bulkPrint aplica multi-tenant scope (business_id) ANTES de WHERE IN — Tier 0 ADR 0093', function () {
    $src = readControllerBulk();
    // business_id where deve aparecer antes de whereIn — extrai bloco e verifica ordem.
    expect($src)->toMatch('/bulkPrint[\\s\\S]*?where\\([\'"]business_id[\'"][\\s\\S]*?whereIn\\([\'"]id[\'"]/');
});

it('bulkPrint sanitiza IDs (intval + filter > 0) — anti SQL-injection / type-juggling', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkPrint[\\s\\S]*?array_map\\([\'"]intval[\'"]/');
    expect($src)->toMatch('/bulkPrint[\\s\\S]*?\\$i\\s*>\\s*0/');
});

it('bulkPrint anti-DoS: limita 200 IDs por request', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkPrint[\\s\\S]*?count\\(\\$ids\\)\\s*>\\s*200/');
});

it('bulkPrint reusa receiptContent() de SellPosController via Reflection (private method)', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkPrint[\\s\\S]*?ReflectionClass[\\s\\S]*?receiptContent[\\s\\S]*?setAccessible\\(true\\)/');
});

it('bulkPrint retorna HTML page-break-after entre recibos pra impressora separar', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkPrint[\\s\\S]*?page-break-after/');
});

// ─── Backend: bulkExport endpoint ───────────────────────────────────────────

it('SellController@bulkExport existe (US-SELL-016)', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/public function bulkExport\\(/');
});

it('bulkExport tem permission gate (direct_sell.view + variants)', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkExport[\\s\\S]*?direct_sell\\.view[\\s\\S]*?abort\\(403\\)/');
});

it('bulkExport aplica multi-tenant scope (business_id) ANTES de WHERE IN — Tier 0 ADR 0093', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkExport[\\s\\S]*?where\\([\'"]transactions\\.business_id[\'"][\\s\\S]*?whereIn\\([\'"]transactions\\.id[\'"]/');
});

it('bulkExport sanitiza IDs (intval + filter > 0)', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkExport[\\s\\S]*?array_map\\([\'"]intval[\'"]/');
});

it('bulkExport whitelist colunas (anti-injection via columns param)', function () {
    $src = readControllerBulk();
    // Pattern: array_intersect($requestedCols, array_keys($allColumns))
    expect($src)->toMatch('/bulkExport[\\s\\S]*?array_intersect\\(/');
    expect($src)->toMatch('/bulkExport[\\s\\S]*?\\$allColumns/');
});

it('bulkExport CSV usa BOM UTF-8 (\xEF\xBB\xBF) pra Excel BR abrir com acentuação', function () {
    $src = readControllerBulk();
    expect($src)->toContain("\xEF\xBB\xBF");
});

it('bulkExport CSV usa separador ; (padrão BR) e cabeçalho PT-BR', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/fputcsv\\([^,]+,[^,]+,\\s*[\'"];[\'"]\\)/');
    // Cabeçalhos PT-BR
    expect($src)->toContain("'Data'");
    expect($src)->toContain("'Nº fatura'");
    expect($src)->toContain("'Cliente'");
    expect($src)->toContain("'A receber'");
});

it('bulkExport money formatter usa vírgula decimal + ponto milhar (BR)', function () {
    $src = readControllerBulk();
    // number_format($val, 2, ',', '.')
    expect($src)->toMatch("/number_format\\([^,]+,\\s*2,\\s*[\'\"]\\,[\'\"],\\s*[\'\"]\\.[\'\"]\\)/");
});

it('bulkExport é streaming (streamDownload) — não carrega tudo em memória', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/bulkExport[\\s\\S]*?streamDownload/');
});

// ─── Backend: inertiaList payload ganha totals (US-SELL-017) ────────────────

it('inertiaList retorna totals no payload (US-SELL-017)', function () {
    $src = readControllerBulk();
    expect($src)->toContain("'totals'");
    expect($src)->toContain("'sum_final_total'");
    expect($src)->toContain("'sum_total_paid'");
    expect($src)->toContain("'sum_due'");
    expect($src)->toContain("'count'");
});

it('inertiaList totals é calculado SOBRE O FILTRO INTEIRO (clone do builder, não só página)', function () {
    $src = readControllerBulk();
    expect($src)->toMatch('/totalsQuery\\s*=\\s*\\(clone\\s+\\$q\\)/');
});

it('inertiaList sum_due nunca negativo (max(0, total - pago))', function () {
    $src = readControllerBulk();
    // max(0.0, $sumFinalTotal - $sumTotalPaid) ou similar
    expect($src)->toMatch('/max\\(0\\.?0?,\\s*\\$sumFinalTotal\\s*-\\s*\\$sumTotalPaid\\)/');
});

// ─── Routes ─────────────────────────────────────────────────────────────────

it('Route POST /sells/bulk-print registrada com nome canon', function () {
    $src = readRoutesBulk();
    expect($src)->toMatch('/Route::post\\([\'"]\\/sells\\/bulk-print[\'"][\\s\\S]*?bulkPrint/');
    expect($src)->toContain("name('sells.bulk-print')");
});

it('Route POST /sells/bulk-export registrada com nome canon', function () {
    $src = readRoutesBulk();
    expect($src)->toMatch('/Route::post\\([\'"]\\/sells\\/bulk-export[\'"][\\s\\S]*?bulkExport/');
    expect($src)->toContain("name('sells.bulk-export')");
});

// ─── Frontend: SellsBulkActionsBar.tsx ──────────────────────────────────────

it('SellsBulkActionsBar.tsx existe', function () {
    expect(file_exists(base_path(BULK_BAR_PATH)))->toBeTrue();
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsBulkActionsBar tem botão Imprimir seleção (PT-BR copy)', function () {
    $src = readBulkBar();
    expect($src)->toContain('Imprimir seleção');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsBulkActionsBar tem botão Exportar CSV (PT-BR copy)', function () {
    $src = readBulkBar();
    expect($src)->toContain('Exportar CSV');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsBulkActionsBar tem dropdown "Agrupar por…" HABILITADO via SellsGroupByDropdown (US-SELL-019)', function () {
    // Atualizado 2026-05-12 — US-SELL-019 substitui o botão disabled "P1 — em breve"
    // por <SellsGroupByDropdown variant="bar" /> funcional. Tier 0 anti-regressão:
    // botão antigo NÃO pode reaparecer (cobre rollback acidental).
    $src = readBulkBar();
    expect($src)->toContain('SellsGroupByDropdown');
    expect($src)->toContain('variant="bar"');
    // Garante que disabled "P1 — em breve" foi removido
    expect($src)->not->toContain('P1 — em breve');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsBulkActionsBar usa endpoint POST /sells/bulk-print', function () {
    $src = readBulkBar();
    expect($src)->toContain('/sells/bulk-print');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsBulkActionsBar usa endpoint POST /sells/bulk-export', function () {
    $src = readBulkBar();
    expect($src)->toContain('/sells/bulk-export');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsBulkActionsBar inclui CSRF token em requests', function () {
    $src = readBulkBar();
    expect($src)->toContain('csrf-token');
    expect($src)->toContain('X-CSRF-TOKEN');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsBulkActionsBar tem botão "Limpar" seleção', function () {
    $src = readBulkBar();
    expect($src)->toContain('Limpar');
    expect($src)->toContain('onClearSelection');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Frontend: SellsGradeAvancada.tsx (multiseleção + tfoot) ────────────────

it('SellsGradeAvancada importa Checkbox shadcn', function () {
    $src = readGradeAvancada();
    expect($src)->toContain("from '@/Components/ui/checkbox'");
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada renderiza checkbox header (selecionar todas)', function () {
    $src = readGradeAvancada();
    expect($src)->toContain('onToggleSelectAll');
    expect($src)->toContain('Selecionar todas');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada renderiza checkbox por linha', function () {
    $src = readGradeAvancada();
    expect($src)->toContain('onToggleSelect');
    expect($src)->toMatch('/Selecionar venda/');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada importa SellsBulkActionsBar', function () {
    $src = readGradeAvancada();
    expect($src)->toContain("from './SellsBulkActionsBar'");
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada importa SellsTotalsRow (US-SELL-017)', function () {
    $src = readGradeAvancada();
    expect($src)->toContain("from './SellsTotalsRow'");
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada renderiza coluna A receber (4ª coluna money)', function () {
    $src = readGradeAvancada();
    expect($src)->toContain('A receber');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Frontend: Index.tsx (lift state up) ────────────────────────────────────

it('Index.tsx lift state selectedIds: Set<number> up', function () {
    $src = readIndexBulk();
    expect($src)->toMatch('/useState<Set<number>>\\(\\(\\)\\s*=>\\s*new\\s+Set\\(\\)\\)/');
});

it('Index.tsx limpa selectedIds quando filtro/busca/date_field muda (anti-confusão)', function () {
    $src = readIndexBulk();
    // Pattern: useEffect(() => { setSelectedIds(new Set()); }, [statusFilter, search, dateField]);
    expect($src)->toMatch('/setSelectedIds\\(new\\s+Set\\(\\)\\)[\\s\\S]*?\\[statusFilter,\\s*search,\\s*dateField\\]/');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx passa props pra SellsGradeAvancada (rows, totals, selectedIds, handlers)', function () {
    $src = readIndexBulk();
    expect($src)->toContain('selectedIds={selectedIds}');
    expect($src)->toContain('totals={totals}');
    expect($src)->toContain('onToggleSelect={handleToggleSelect}');
    expect($src)->toContain('onToggleSelectAll={handleToggleSelectAll}');
    // quarantine-reason: SellsBulkActionsBar.tsx/SellsGradeAvancada.tsx deletados + handlers totals=C:/Program Files/Git/onToggleSelect=/effect reset removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx captura totals do JSON response', function () {
    $src = readIndexBulk();
    expect($src)->toContain('setTotals(json.totals');
});
