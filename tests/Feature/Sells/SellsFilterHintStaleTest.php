<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Fix bug 2026-05-18 "vendas só aparecem até dia 14" — testes estruturais.
 *
 * Cobre:
 *   - Index.tsx adiciona helpers clearDateFilter + dateFilterActive + dateFilterStale + dateFilterLabel
 *   - JSX renderiza hint visível com botão "Limpar filtro" quando filter ativo
 *   - Hint ganha classe .stale quando dateTo < ontem
 *   - CSS .vd-date-filter-hint escopado em .sells-cowork em inertia.css
 *
 * Multi-tenant Tier 0 safe (sem DB) — file_get_contents + regex.
 *
 * Refs:
 *   - reclamação cliente 2026-05-18 via Wagner
 *   - localStorage[oimpresso.sells.datePreset/dateFrom/dateTo] stuck pattern
 *   - PR #1034 (DateFilter reintegration que preservou keys legacy)
 */

const SELLS_INDEX_PATH = __DIR__ . '/../../../resources/js/Pages/Sells/Index.tsx';
const INERTIA_CSS_PATH = __DIR__ . '/../../../resources/css/inertia.css';

describe('Bug fix 2026-05-18 — hint filter stale em Sells/Index', function () {
    it('Index.tsx define helpers de detecção de filter ativo + stale', function () {
        $src = file_get_contents(SELLS_INDEX_PATH);
        // Helpers novos
        expect($src)->toContain('const clearDateFilter = useCallback');
        expect($src)->toContain('const dateFilterActive =');
        expect($src)->toContain('const dateFilterStale = useMemo');
        expect($src)->toContain('const dateFilterLabel = useMemo');
        // clearDateFilter limpa STATE + localStorage
        expect($src)->toContain("setDatePreset('all')");
        expect($src)->toContain("setDateFrom('')");
        expect($src)->toContain("setDateTo('')");
        expect($src)->toContain("lsSet('datePreset', 'all')");
        expect($src)->toContain("lsSet('dateFrom', '')");
        expect($src)->toContain("lsSet('dateTo', '')");
    });

    it('Index.tsx renderiza JSX hint condicional com botao Limpar filtro', function () {
        $src = file_get_contents(SELLS_INDEX_PATH);
        // Render conditional
        expect($src)->toContain('{dateFilterActive && (');
        expect($src)->toContain('vd-date-filter-hint');
        expect($src)->toContain('vd-date-filter-hint-clear');
        expect($src)->toContain('Limpar filtro');
        expect($src)->toContain('onClick={clearDateFilter}');
        // Stale state visual
        expect($src)->toContain("Filtro antigo escondendo vendas novas");
        expect($src)->toContain("Filtro de data ativo");
    });

    it('Index.tsx detecta dateTo < ontem como stale (proteção contra localStorage stuck)', function () {
        $src = file_get_contents(SELLS_INDEX_PATH);
        // Logica core: dateTo < yesterday
        expect($src)->toContain('86_400_000');
        expect($src)->toContain('today.getTime() - 86_400_000');
        expect($src)->toContain('toDate < yesterday');
    });
});

describe('Bug fix 2026-05-18 — CSS hint escopado', function () {
    it('inertia.css adiciona .vd-date-filter-hint scopado em .sells-cowork', function () {
        $css = file_get_contents(INERTIA_CSS_PATH);
        expect($css)->toContain('.sells-cowork .vd-date-filter-hint');
        expect($css)->toContain('.sells-cowork .vd-date-filter-hint.stale');
        expect($css)->toContain('.sells-cowork .vd-date-filter-hint-clear');
        expect($css)->toContain('.sells-cowork .vd-date-filter-hint-range');
        // Comentário do bug pra rastreabilidade
        expect($css)->toContain('Fix bug 2026-05-18');
    });
});
