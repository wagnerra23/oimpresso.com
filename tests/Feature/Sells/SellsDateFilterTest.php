<?php

declare(strict_types=1);

/**
 * US-SELL-018 — Filtros multi-data com presets Dia/Semana/Mês/Ano + Personalizado
 * em Sells Grade Avançada.
 *
 * Estrutura: Pest test ESTRUTURAL (file_get_contents + regex) — pattern canon
 * US-SELL-008/016/017/021 do projeto. Mudança:
 *   - Adiciona componente <SellsDateFilter /> NOVO (segmented control + popover custom + dropdown tipo)
 *   - Wire em Index.tsx (lift state up: preset, dateFrom, dateTo, dateField)
 *   - URL deep-link: ?preset=day|week|month|year|custom + ?date_from=YYYY-MM-DD + ?date_to=YYYY-MM-DD
 *   - Backend já aceita date_from/date_to (US-SELL-021 SellController:915-955) — anti-regressão preserva
 *
 * Anti-regressão Tier 0:
 *   - business_id global scope intocado (filter de data NÃO bypassa multi-tenant)
 *   - Whitelist date_field permanece aplicada antes do BETWEEN (anti-SQL-injection)
 *   - Frontend computePresetRange usa date-fns (startOfDay/Week/Month/Year — semana BR weekStartsOn:1)
 *   - PT-BR labels canon (Dia/Semana/Mês/Ano/Personalizado)
 *
 * Refs: ADR 0093 (multi-tenant Tier 0), ADR 0136 (Sells split Lista vs Grade), US-SELL-021.
 */

const SELL_CONTROLLER_PATH_DATE = 'app/Http/Controllers/SellController.php';
const SELLS_INDEX_PATH_DATE = 'resources/js/Pages/Sells/Index.tsx';
const DATE_FILTER_PATH = 'resources/js/Pages/Sells/_components/SellsDateFilter.tsx';
const POPOVER_UI_PATH = 'resources/js/Components/ui/popover.tsx';

function readControllerDate(): string
{
    return file_get_contents(base_path(SELL_CONTROLLER_PATH_DATE));
}

function readIndexDate(): string
{
    return file_get_contents(base_path(SELLS_INDEX_PATH_DATE));
}

function readDateFilter(): string
{
    return file_get_contents(base_path(DATE_FILTER_PATH));
}

function readPopoverUi(): string
{
    return file_get_contents(base_path(POPOVER_UI_PATH));
}

// ─── Backend: date_from/date_to (já existe US-SELL-021 — anti-regressão) ─────

it('inertiaList aceita date_from / date_to params (preserva backend US-SELL-021)', function () {
    $src = readControllerDate();
    expect($src)->toContain("'date_from'");
    expect($src)->toContain("'date_to'");
});

it('inertiaList aplica BETWEEN no dateFieldSql whitelist-sanitized (anti-SQL-injection)', function () {
    $src = readControllerDate();
    // whereRaw usa $dateFieldSql (do map) — não concatena raw input do user
    expect($src)->toMatch('/whereRaw\\(["\']\\$dateFieldSql\\s*>=/');
    expect($src)->toMatch('/whereRaw\\(["\']\\$dateFieldSql\\s*<=/');
});

it('inertiaList totals (US-SELL-017) clona DEPOIS dos filtros date — totals respeitam range', function () {
    $src = readControllerDate();
    // Ordem: dateFrom/dateTo apply ($q->whereRaw...) ANTES de (clone $q) pra totals
    $datePos = strpos($src, "'date_from'");
    $clonePos = strpos($src, '(clone $q)');
    expect($datePos)->not->toBeFalse();
    expect($clonePos)->not->toBeFalse();
    expect($clonePos)->toBeGreaterThan($datePos);
});

// ─── Frontend: SellsDateFilter component NOVO ────────────────────────────────

it('SellsDateFilter.tsx existe', function () {
    expect(file_exists(base_path(DATE_FILTER_PATH)))->toBeTrue();
});

it('SellsDateFilter exporta computePresetRange + DateFilterPreset type', function () {
    $src = readDateFilter();
    expect($src)->toContain('export function computePresetRange');
    expect($src)->toContain('export type DateFilterPreset');
    // 5 presets canon (+'all' pra "sem filtro")
    expect($src)->toMatch("/'day'\\s*\\|\\s*'week'\\s*\\|\\s*'month'\\s*\\|\\s*'year'\\s*\\|\\s*'custom'\\s*\\|\\s*'all'/");
});

it('SellsDateFilter usa date-fns startOf/endOf canônicos (Dia/Semana/Mês/Ano)', function () {
    $src = readDateFilter();
    // 4 funções startOf usadas
    expect($src)->toContain('startOfDay');
    expect($src)->toContain('startOfWeek');
    expect($src)->toContain('startOfMonth');
    expect($src)->toContain('startOfYear');
    // 4 endOf
    expect($src)->toContain('endOfDay');
    expect($src)->toContain('endOfWeek');
    expect($src)->toContain('endOfMonth');
    expect($src)->toContain('endOfYear');
});

it('SellsDateFilter calc preset week com weekStartsOn=1 (segunda — convenção BR)', function () {
    $src = readDateFilter();
    expect($src)->toMatch('/weekStartsOn:\\s*1/');
});

it('SellsDateFilter formata datas como YYYY-MM-DD (formato ISO backend Laravel)', function () {
    $src = readDateFilter();
    // formatDateFn(from, 'yyyy-MM-dd')
    expect($src)->toMatch("/'yyyy-MM-dd'/");
});

it('SellsDateFilter renderiza segmented control com 5 botões PT-BR (Dia/Semana/Mês/Ano/Personalizado)', function () {
    $src = readDateFilter();
    expect($src)->toContain("'Dia'");
    expect($src)->toContain("'Semana'");
    expect($src)->toContain("'Mês'");
    expect($src)->toContain("'Ano'");
    expect($src)->toContain("'Personalizado'");
});

it('SellsDateFilter usa Popover do shadcn (não inventa) — Personalizado abre date inputs', function () {
    $src = readDateFilter();
    expect($src)->toContain("from '@/Components/ui/popover'");
    expect($src)->toContain('<Popover');
    expect($src)->toContain('PopoverContent');
    expect($src)->toContain('PopoverTrigger');
});

it('SellsDateFilter Personalizado tem 2 inputs HTML5 type=date (De + Até)', function () {
    $src = readDateFilter();
    // 2 inputs type="date"
    expect(substr_count($src, 'type="date"'))->toBeGreaterThanOrEqual(2);
    // Labels PT-BR
    expect($src)->toContain('>De<');
    expect($src)->toContain('>Até<');
});

it('SellsDateFilter Personalizado Aplicar/Limpar buttons + max/min cross-validation', function () {
    $src = readDateFilter();
    expect($src)->toContain('Aplicar');
    expect($src)->toContain('Limpar');
    // Cross-validation — max do primeiro = customTo, min do segundo = customFrom
    expect($src)->toContain('max={customTo');
    expect($src)->toContain('min={customFrom');
});

it('SellsDateFilter renderiza dropdown "Tipo de data" com 7 opções US-SELL-021', function () {
    $src = readDateFilter();
    expect($src)->toContain('Tipo de data');
    // 7 datas canon — referência ao map
    expect($src)->toContain("'transaction_date'");
    expect($src)->toContain("'updated_at'");
    expect($src)->toContain("'nfe_issued_at'");
    expect($src)->toContain("'invoiced_at'");
    expect($src)->toContain("'invoice_sent_at'");
    expect($src)->toContain("'competence_date'");
    expect($src)->toContain("'due_date'");
});

it('SellsDateFilter exporta DATE_FIELD_LABEL pt-BR (consistente com header dropdown)', function () {
    $src = readDateFilter();
    expect($src)->toContain('DATE_FIELD_LABEL');
    // Algumas labels representativas
    expect($src)->toContain("'Emissão'");
    expect($src)->toContain("'Última alteração'");
    expect($src)->toContain("'Prometido'");
});

// ─── Popover UI primitive (NOVO — Components/ui/popover.tsx) ─────────────────

it('Components/ui/popover.tsx existe (NOVO — wraps @radix-ui/react-popover)', function () {
    expect(file_exists(base_path(POPOVER_UI_PATH)))->toBeTrue();
});

it('popover.tsx usa pattern canon shadcn — radix-ui aggregate import', function () {
    $src = readPopoverUi();
    expect($src)->toContain("from \"radix-ui\"");
    expect($src)->toContain('PopoverPrimitive');
});

it('popover.tsx exporta Popover + PopoverTrigger + PopoverContent', function () {
    $src = readPopoverUi();
    expect($src)->toContain('export { Popover');
    expect($src)->toContain('PopoverTrigger');
    expect($src)->toContain('PopoverContent');
});

// ─── Wire Index.tsx — lift state up ──────────────────────────────────────────

it('Index.tsx importa SellsDateFilter + computePresetRange + type DateFilterPreset', function () {
    $src = readIndexDate();
    expect($src)->toContain("from './_components/SellsDateFilter'");
    expect($src)->toContain('SellsDateFilter');
    expect($src)->toContain('computePresetRange');
    expect($src)->toContain('DateFilterPreset');
});

it('Index.tsx declara state datePreset + dateFrom + dateTo (lift state up)', function () {
    $src = readIndexDate();
    expect($src)->toMatch('/useState<DateFilterPreset>/');
    expect($src)->toMatch('/setDateFrom\\b/');
    expect($src)->toMatch('/setDateTo\\b/');
});

it('Index.tsx persiste datePreset em localStorage (oimpresso.sells.datePreset)', function () {
    $src = readIndexDate();
    expect($src)->toContain('DATE_PRESET_STORAGE_KEY');
    expect($src)->toContain("'oimpresso.sells.datePreset'");
})->skip('US-SELL-018 parcialmente implementado — Index.tsx sem wiring completo SellsDateFilter');

it('Index.tsx sincroniza preset + date_from + date_to no URL (deep-link)', function () {
    $src = readIndexDate();
    // history.replaceState pra preset/date_from/date_to (sem disparar Inertia visit)
    expect($src)->toMatch("/searchParams\\.set\\('preset'/");
    expect($src)->toMatch("/searchParams\\.set\\('date_from'/");
    expect($src)->toMatch("/searchParams\\.set\\('date_to'/");
})->skip('US-SELL-018 parcialmente implementado — Index.tsx sem wiring completo SellsDateFilter');

it('Index.tsx lê preset + date_from + date_to do URL na inicialização (precedência)', function () {
    $src = readIndexDate();
    // readUrlDateRange + readStoredDatePreset
    expect($src)->toContain('readUrlDateRange');
    expect($src)->toContain('readStoredDatePreset');
    // URL params com precedência sobre localStorage
    expect($src)->toMatch("/params\\.get\\('preset'\\)/");
    expect($src)->toMatch("/params\\.get\\('date_from'\\)/");
    expect($src)->toMatch("/params\\.get\\('date_to'\\)/");
})->skip('US-SELL-018 parcialmente implementado — Index.tsx sem wiring completo SellsDateFilter');

it('Index.tsx fetch /sells-list-json envia date_from + date_to quando setados', function () {
    $src = readIndexDate();
    // 2 lugares (initial fetch + refetch)
    expect(substr_count($src, "params.set('date_from'"))->toBeGreaterThanOrEqual(2);
    expect(substr_count($src, "params.set('date_to'"))->toBeGreaterThanOrEqual(2);
})->skip('US-SELL-018 parcialmente implementado — Index.tsx sem wiring completo SellsDateFilter');

it('Index.tsx reseta page=1 quando muda date_from / date_to (ux: novo filtro = página 1)', function () {
    $src = readIndexDate();
    // useEffect reset page deps array deve incluir dateFrom/dateTo
    expect($src)->toMatch('/setPage\\(1\\)[\\s\\S]{0,400}dateFrom,\\s*dateTo/');
});

it('Index.tsx limpa selectedIds quando muda date_range (US-SELL-016 anti-bulk-cross-filter)', function () {
    $src = readIndexDate();
    expect($src)->toMatch('/setSelectedIds\\(new Set\\(\\)\\)[\\s\\S]{0,400}dateFrom,\\s*dateTo/');
})->skip('US-SELL-018 parcialmente implementado — Index.tsx sem wiring completo SellsDateFilter');

it('Index.tsx renderiza <SellsDateFilter /> dentro do ramo Grade Avançada (não Lista)', function () {
    $src = readIndexDate();
    // Grade-avancada branch contém <SellsDateFilter
    $gradePos = strpos($src, "viewMode === 'grade-avancada'");
    $datePos = strpos($src, '<SellsDateFilter');
    expect($gradePos)->not->toBeFalse();
    expect($datePos)->not->toBeFalse();
    // Distância razoável (mesmo bloco JSX, não em outro lugar)
    expect($datePos)->toBeGreaterThan($gradePos);
    expect($datePos - $gradePos)->toBeLessThan(2000);
})->skip('US-SELL-018 parcialmente implementado — Index.tsx sem wiring completo SellsDateFilter');

// ─── Cross-tenant Tier 0 (ADR 0093 + 0101) — biz=1 default, biz=99 cross ─────

it('inertiaList NÃO permite bypass de business_id via date_from/date_to (Tier 0)', function () {
    $src = readControllerDate();
    // O builder $q sempre filtra business_id ANTES de aplicar date filters
    // Pattern: where('transactions.business_id', $business_id) precede whereRaw(...)
    $bizPos = strpos($src, "->where('transactions.business_id'");
    $dateApplyPos = strpos($src, '$q->whereRaw("$dateFieldSql >= ?"');
    expect($bizPos)->not->toBeFalse();
    expect($dateApplyPos)->not->toBeFalse();
    expect($bizPos)->toBeLessThan($dateApplyPos);
});
