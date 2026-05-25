<?php

declare(strict_types=1);

/**
 * Pest — Onda 4 ADR 0192 · Integração Vendas × Oficina (frontend Sells/Index)
 *
 * GUARDs estruturais sobre `resources/js/Pages/Sells/Index.tsx` que garantem
 * que os 4 pontos de costura Onda 4 não regridem em refactors:
 *  - saved tree branch "Por origem" + filhos Balcão/Oficina/Online
 *  - KPI hero breakdown quando Foco=faturamento (vd-kpi-breakdown)
 *  - listener cross-módulo `oimpresso:open-venda` → setOpenSaleId
 *  - localStorage Tier 0 `visao_origem` (per-business)
 *
 * Refs:
 *  - resources/js/Pages/Sells/Index.tsx
 *  - resources/js/Pages/Sells/_components/VdSource.tsx
 *  - resources/js/Pages/Sells/Index.charter.md (v5)
 *  - memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 *  - memory/requisitos/Sells/Index-r3-integracao-vendas-oficina-visual-comparison.md
 */

const SELLS_INDEX_TSX_PATH = 'resources/js/Pages/Sells/Index.tsx';
const VDSOURCE_TSX_PATH = 'resources/js/Pages/Sells/_components/VdSource.tsx';
const SELLS_COWORK_CSS_PATH = 'resources/css/sells-cowork.css';

function ondaFourReadSellsIndex(): string
{
    return file_get_contents(base_path(SELLS_INDEX_TSX_PATH));
}

function ondaFourReadVdSource(): string
{
    return file_get_contents(base_path(VDSOURCE_TSX_PATH));
}

function ondaFourReadSellsCoworkCss(): string
{
    return file_get_contents(base_path(SELLS_COWORK_CSS_PATH));
}

// ──────────────────────────────────────────────────────────────
// Onda 3 — VdSource pill + coluna Origem
// ──────────────────────────────────────────────────────────────

it('VdSource component existe e expõe props canon (source, sourceLabel, osRef, onPickOs)', function () {
    expect(file_exists(base_path(VDSOURCE_TSX_PATH)))->toBeTrue();
    $source = ondaFourReadVdSource();
    expect($source)
        ->toContain('export default function VdSource')
        ->toContain('source: VdSourceKind | string')
        ->toContain('sourceLabel: string')
        ->toContain('osRef: string | null')
        ->toContain('onPickOs?: (osRef: string) => void');
});

it('VdSource renderiza link OS-NNNN quando source=oficina (cross-link visível)', function () {
    $source = ondaFourReadVdSource();
    expect($source)
        ->toContain("kind === 'oficina'")
        ->toContain('isOficina && osRef')
        ->toContain('vd-src-os');
});

it('CSS tokens source (balcao/oficina/online) escopados em .sells-cowork .vendas-aplus', function () {
    $css = ondaFourReadSellsCoworkCss();
    expect($css)
        ->toContain('.sells-cowork .vendas-aplus {')
        ->toContain('--vd-src-balcao:')
        ->toContain('--vd-src-oficina:')
        ->toContain('--vd-src-online:');
});

it('CSS .vd-row-oficina stripe sutil + responsive 1100/1280 preservados', function () {
    $css = ondaFourReadSellsCoworkCss();
    expect($css)
        ->toContain('.os-row.vd-row-oficina td:first-child')
        ->toContain('inset 2px 0 0 var(--vd-src-oficina)')
        ->toContain('@media (max-width: 1280px)')
        ->toContain('@media (max-width: 1100px)');
});

// ──────────────────────────────────────────────────────────────
// Onda 4 — saved tree + KPI breakdown + listener + localStorage
// ──────────────────────────────────────────────────────────────

it('Index.tsx tem state visaoOrigem + persiste em localStorage Tier 0 visao_origem', function () {
    $src = ondaFourReadSellsIndex();
    expect($src)
        ->toContain("type VisaoOrigemKind = '' | 'balcao' | 'oficina' | 'online'")
        ->toContain('useState<VisaoOrigemKind>')
        ->toContain("ls.get('visao_origem'")
        ->toContain("ls.set('visao_origem', visaoOrigem)");
});

it('Index.tsx tem branch "Por origem" no saved tree com 3 filhos Balcão/Oficina/Online', function () {
    $src = ondaFourReadSellsIndex();
    expect($src)
        ->toContain('Por origem')
        ->toContain("['balcao', 'oficina', 'online']")
        ->toContain('vd-tree-row l1')
        ->toContain('origemExpanded');
});

it('Index.tsx tem KPI hero breakdown quando foco=faturamento (vd-kpi-breakdown)', function () {
    $src = ondaFourReadSellsIndex();
    expect($src)
        ->toContain('kpiTodayBySource')
        ->toContain('vd-kpi-breakdown')
        ->toContain('vd-kpi-b balcao')
        ->toContain('vd-kpi-b oficina')
        ->toContain('vd-kpi-b online')
        ->toContain("foco === 'faturamento'");
});

it('Index.tsx escuta evento cross-módulo oimpresso:open-venda → setOpenSaleId', function () {
    $src = ondaFourReadSellsIndex();
    expect($src)
        ->toContain("addEventListener('oimpresso:open-venda'")
        ->toContain("removeEventListener('oimpresso:open-venda'")
        ->toContain('detail?.venda_id')
        ->toContain('setOpenSaleId(Number(vendaId))');
});

it('Index.tsx filtra rows por visaoOrigem quando state populado (client-side)', function () {
    $src = ondaFourReadSellsIndex();
    expect($src)
        ->toContain('if (visaoOrigem) out = out.filter')
        ->toContain("(r.source ?? 'balcao') === visaoOrigem");
});

it('Index.tsx interface SaleRow ganhou source/source_label/os_ref (paridade payload backend)', function () {
    $src = ondaFourReadSellsIndex();
    expect($src)
        ->toContain("source?: 'balcao' | 'oficina' | 'online' | string")
        ->toContain('source_label?: string')
        ->toContain('os_ref?: string | null');
});

it('Index.tsx delega onPickOs pro SellsTabelaUnificada navegando pra Repair', function () {
    $src = ondaFourReadSellsIndex();
    expect($src)
        ->toContain('onPickOs={(osRef)')
        ->toContain('/repair/producao-oficina?os=');
});

it('SellsTabelaUnificada adiciona ColumnId source nos presets Operacional e Produção', function () {
    $src = file_get_contents(base_path('resources/js/Pages/Sells/_components/SellsTabelaUnificada.tsx'));
    // Operacional ganha 'source' entre 'seller' e 'pipeline'
    expect($src)->toContain("'seller', 'source', 'pipeline'");
    // Produção ganha 'source' entre 'location' e 'pipeline'
    expect($src)->toContain("'location', 'source', 'pipeline'");
});

it('SellsTabelaUnificada propaga onPickOs pro renderer da célula source', function () {
    $src = file_get_contents(base_path('resources/js/Pages/Sells/_components/SellsTabelaUnificada.tsx'));
    expect($src)
        ->toContain('onPickOs?:')
        ->toContain('handlePickOs')
        ->toContain('VdSource')
        ->toContain("case 'source':");
});
