<?php

declare(strict_types=1);

/**
 * US-SELL-019 — Agrupamento por campo (Sells Grade Avançada).
 *
 * Estrutura: Pest test ESTRUTURAL (file_get_contents + regex) — pattern canon
 * US-SELL-008/016/017 do projeto. Mudança 100% frontend (TanStack Table v8
 * getGroupedRowModel client-side); zero mudança backend (totals/data já
 * batem o filtro inteiro — agrupamento é só layout).
 *
 * Trade-off documentado: client-side TanStack suficiente <500 vendas
 * (per_page max 100). Multi-level (drag-to-group) fica pra US futura.
 *
 * Anti-regressão Tier 0:
 *   - Multi-tenant (business_id) intocado — agrupamento NÃO toca query
 *   - SellsBulkActionsBar agora habilita "Agrupar por…" (substitui botão
 *     disabled "P1 — em breve" do US-SELL-016)
 *   - 4 opções canon (none/customer_name/payment_status/emission_month)
 *   - PT-BR labels (Sem agrupamento/Cliente/Status pagamento/Mês emissão)
 *
 * Refs: ADR 0136 (Sells Grade Avançada), ADR 0093 (Tier 0).
 */

const GROUP_BY_DROPDOWN_PATH = 'resources/js/Pages/Sells/_components/SellsGroupByDropdown.tsx';
const GRADE_PATH_GROUPBY = 'resources/js/Pages/Sells/_components/SellsGradeAvancada.tsx';
const BULK_BAR_PATH_GROUPBY = 'resources/js/Pages/Sells/_components/SellsBulkActionsBar.tsx';
const INDEX_PATH_GROUPBY = 'resources/js/Pages/Sells/Index.tsx';
const SELL_CONTROLLER_PATH_GROUPBY = 'app/Http/Controllers/SellController.php';

function readGroupByDropdown(): string
{
    return file_get_contents(base_path(GROUP_BY_DROPDOWN_PATH));
}

function readGradeGroupBy(): string
{
    return file_get_contents(base_path(GRADE_PATH_GROUPBY));
}

function readBulkBarGroupBy(): string
{
    return file_get_contents(base_path(BULK_BAR_PATH_GROUPBY));
}

function readIndexGroupBy(): string
{
    return file_get_contents(base_path(INDEX_PATH_GROUPBY));
}

// ─── SellsGroupByDropdown component NOVO ─────────────────────────────────────

it('SellsGroupByDropdown.tsx existe', function () {
    expect(file_exists(base_path(GROUP_BY_DROPDOWN_PATH)))->toBeTrue();
});

it('SellsGroupByDropdown exporta GroupByField type com 4 opções canon', function () {
    $src = readGroupByDropdown();
    expect($src)->toContain('export type GroupByField');
    // 4 opções — none + 3 grupos canon
    expect($src)->toMatch("/'none'\\s*\\|\\s*'customer_name'\\s*\\|\\s*'payment_status'\\s*\\|\\s*'emission_month'/");
});

it('SellsGroupByDropdown exporta GROUP_BY_LABEL pt-BR (Sem agrupamento/Cliente/Status/Mês)', function () {
    $src = readGroupByDropdown();
    expect($src)->toContain('GROUP_BY_LABEL');
    expect($src)->toContain("'Sem agrupamento'");
    expect($src)->toContain("'Cliente'");
    expect($src)->toContain("'Status pagamento'");
    expect($src)->toContain("'Mês emissão'");
});

it('SellsGroupByDropdown exporta GROUP_BY_OPTIONS array (renderiza dropdown)', function () {
    $src = readGroupByDropdown();
    expect($src)->toContain('GROUP_BY_OPTIONS');
    // Array com as 4 keys
    expect($src)->toMatch("/'none',\\s*'customer_name',\\s*'payment_status',\\s*'emission_month'/");
});

it('SellsGroupByDropdown usa shadcn DropdownMenu (não inventa)', function () {
    $src = readGroupByDropdown();
    expect($src)->toContain("from '@/Components/ui/dropdown-menu'");
    expect($src)->toContain('<DropdownMenu>');
    expect($src)->toContain('DropdownMenuTrigger');
    expect($src)->toContain('DropdownMenuItem');
});

it('SellsGroupByDropdown tem item "Limpar agrupamento" quando ativo (UX rápido reset)', function () {
    $src = readGroupByDropdown();
    expect($src)->toContain('Limpar agrupamento');
});

// ─── SellsGradeAvancada — TanStack getGroupedRowModel ────────────────────────

it('SellsGradeAvancada importa @tanstack/react-table grouping APIs', function () {
    $src = readGradeGroupBy();
    expect($src)->toContain("from '@tanstack/react-table'");
    expect($src)->toContain('useReactTable');
    expect($src)->toContain('getCoreRowModel');
    expect($src)->toContain('getGroupedRowModel');
    expect($src)->toContain('getExpandedRowModel');
});

it('SellsGradeAvancada importa GroupByField type do dropdown', function () {
    $src = readGradeGroupBy();
    expect($src)->toContain("from './SellsGroupByDropdown'");
    expect($src)->toContain('GroupByField');
});

it('SellsGradeAvancada aceita props groupBy + onGroupByChange (lift state up)', function () {
    $src = readGradeGroupBy();
    expect($src)->toMatch('/groupBy:\\s*GroupByField/');
    expect($src)->toMatch('/onGroupByChange:\\s*\\(g:\\s*GroupByField\\)\\s*=>\\s*void/');
});

it('SellsGradeAvancada usa GroupingState + ExpandedState do TanStack', function () {
    $src = readGradeGroupBy();
    expect($src)->toContain('GroupingState');
    expect($src)->toContain('ExpandedState');
});

it('SellsGradeAvancada define aggregationFn sum em final_total + total_paid (subtotais)', function () {
    $src = readGradeGroupBy();
    // 2 colunas com aggregationFn: 'sum' (final_total + total_paid)
    expect(substr_count($src, "aggregationFn: 'sum'"))->toBeGreaterThanOrEqual(2);
});

it('SellsGradeAvancada renderiza header de grupo expandable (chevron + count + subtotal)', function () {
    $src = readGradeGroupBy();
    // GroupedTable component
    expect($src)->toContain('GroupedTable');
    // Chevron expand/collapse
    expect($src)->toContain('ChevronDown');
    expect($src)->toContain('ChevronRight');
    // row.getIsGrouped pra distinguir header de grupo vs row normal
    expect($src)->toContain('getIsGrouped');
    expect($src)->toContain('getToggleExpandedHandler');
});

it('SellsGradeAvancada calcula emission_month YYYY-MM (groupBy mês emissão)', function () {
    $src = readGradeGroupBy();
    // Function que extrai YYYY-MM do transaction_date
    expect($src)->toContain('emissionMonth');
    expect($src)->toMatch('/getFullYear\\(\\)/');
    expect($src)->toMatch('/getMonth\\(\\)\\s*\\+\\s*1/');
    expect($src)->toMatch('/padStart\\(2,\\s*[\'"]0[\'"]\\)/');
});

it('SellsGradeAvancada subtotal exibe count de vendas no header de grupo (PT-BR singular/plural)', function () {
    $src = readGradeGroupBy();
    // formatCount + label "vendas" (plural) / "venda" (singular)
    expect($src)->toMatch('/subRows\\.length\\s*===\\s*1\\s*\\?\\s*[\'"]venda[\'"]\\s*:\\s*[\'"]vendas[\'"]/');
});

it('SellsGradeAvancada default expanded=true (DX — user vê tudo, depois recolhe)', function () {
    $src = readGradeGroupBy();
    // useState<ExpandedState>(true)
    expect($src)->toMatch('/useState<ExpandedState>\\(true\\)/');
});

// ─── SellsBulkActionsBar — habilita "Agrupar por…" (substitui disabled) ──────

it('SellsBulkActionsBar NÃO tem mais botão disabled "P1 — em breve" (substituído por dropdown)', function () {
    $src = readBulkBarGroupBy();
    // Botão disabled antigo removido
    expect($src)->not->toContain('P1 — em breve');
    expect($src)->not->toContain("'Agrupar por…'");
});

it('SellsBulkActionsBar usa SellsGroupByDropdown component (variant=bar)', function () {
    $src = readBulkBarGroupBy();
    expect($src)->toContain("from './SellsGroupByDropdown'");
    expect($src)->toContain('<SellsGroupByDropdown');
    expect($src)->toContain('variant="bar"');
});

it('SellsBulkActionsBar aceita props groupBy + onGroupByChange opcionais (back-compat)', function () {
    $src = readBulkBarGroupBy();
    // Optional params (sem onGroupByChange = não renderiza dropdown — back-compat)
    expect($src)->toMatch('/groupBy\\?:\\s*GroupByField/');
    expect($src)->toMatch('/onGroupByChange\\?:\\s*\\(g:\\s*GroupByField\\)\\s*=>\\s*void/');
});

// ─── Index.tsx — lift state up + persist localStorage ────────────────────────

it('Index.tsx declara state groupBy + setGroupBy (lift state up)', function () {
    $src = readIndexGroupBy();
    expect($src)->toMatch('/useState<GroupByField>/');
    expect($src)->toMatch('/setGroupBy\\b/');
});

it('Index.tsx persiste groupBy em localStorage (oimpresso.sells.groupBy)', function () {
    $src = readIndexGroupBy();
    expect($src)->toContain('GROUP_BY_STORAGE_KEY');
    expect($src)->toContain("'oimpresso.sells.groupBy'");
});

it('Index.tsx passa groupBy + setGroupBy pra <SellsGradeAvancada>', function () {
    $src = readIndexGroupBy();
    expect($src)->toMatch('/groupBy=\\{groupBy\\}/');
    expect($src)->toMatch('/onGroupByChange=\\{setGroupBy\\}/');
});

it('Index.tsx importa GroupByField type do componente', function () {
    $src = readIndexGroupBy();
    expect($src)->toContain("from './_components/SellsGroupByDropdown'");
    expect($src)->toContain('GroupByField');
});

// ─── Cross-tenant Tier 0 (anti-regressão — agrupamento NÃO bypassa scope) ────

it('GroupBy é 100% frontend — controller @inertiaList NÃO recebe param group_by (Tier 0)', function () {
    // Confirma que a feature é client-side: SellController NÃO ganhou nenhum
    // novo param group_by. Se algum dia tornar server-side (otimização), DEVE
    // adicionar whitelist + business_id scope antes — esta guard pega regressão.
    $src = file_get_contents(base_path(SELL_CONTROLLER_PATH_GROUPBY));
    expect($src)->not->toMatch('/[\'"]group_by[\'"]\\s*=>/');
    expect($src)->not->toMatch("/\\\$request->input\\(['\"]group_by['\"]/");
});
