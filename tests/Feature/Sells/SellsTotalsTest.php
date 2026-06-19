<?php

declare(strict_types=1);

/**
 * US-SELL-017 — Totalizador rodapé (sticky-bottom em Grade Avançada,
 * opt-in compact em Lista).
 *
 * Estrutura: Pest test ESTRUTURAL (file_get_contents + regex) — pattern
 * canon US-SELL-008/021 do projeto. Mudança não toca scope/Model multi-tenant —
 * só adiciona payload field calculado em SQL existente.
 *
 * Anti-regressão Tier 0:
 *   - totals respeita TODOS os filtros (payment_status, search, date_field, date_from/to)
 *     porque clona o builder ANTES do paginate (sem mexer ordering/limit)
 *   - totals NÃO regrede filtro overdue (pill "Atrasadas" → totals só dessas vendas)
 *   - totals respeita business_id (ADR 0093) porque o builder original já filtra
 *   - sum_due nunca negativo (max(0, total - paid))
 *
 * Frontend:
 *   - SellsTotalsRow.tsx existe + 2 modos (compact + sticky-bottom)
 *   - PT-BR copy: Qtd, Total, Pago, A receber
 *   - Money formatter pt-BR (R$ X.XXX,XX — vírgula decimal, ponto milhar)
 *   - Lista mode: opt-in via toggle "Mostrar totais" (default off)
 *   - Grade mode: sempre visível (sticky-bottom no card da tabela)
 *
 * Refs: ADR 0136 (Sells Grade Avançada), ADR 0093 (multi-tenant Tier 0).
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — só os it() de frontend que
 * leem `SellsTotalsRow.tsx` / `SellsGradeAvancada.tsx` (DELETADOS) e o markup do
 * toggle "Mostrar totais" de `Index.tsx` (markers verificados ausentes). NÃO é bug.
 * Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * 🔴 Os it() de BACKEND (inertiaList totals) PERMANECEM ATIVOS — guards VIVOS que
 * passam hoje: clone do builder preserva TODOS os filtros (inclui business_id ADR
 * 0093), COALESCE(SUM) anti-null, sum_due nunca negativo, subquery anti-regressão
 * US-SELL-008. Idem o it() de Index.tsx que ainda passa (captura json.totals).
 */

const SELL_CONTROLLER_PATH_TOTALS = 'app/Http/Controllers/SellController.php';
const TOTALS_ROW_PATH = 'resources/js/Pages/Sells/_components/SellsTotalsRow.tsx';
const INDEX_PATH_TOTALS = 'resources/js/Pages/Sells/Index.tsx';
const GRADE_PATH_TOTALS = 'resources/js/Pages/Sells/_components/SellsGradeAvancada.tsx';

function readControllerTotals(): string
{
    return file_get_contents(base_path(SELL_CONTROLLER_PATH_TOTALS));
}

function readTotalsRow(): string
{
    return file_get_contents(base_path(TOTALS_ROW_PATH));
}

function readIndexTotals(): string
{
    return file_get_contents(base_path(INDEX_PATH_TOTALS));
}

function readGradeTotals(): string
{
    return file_get_contents(base_path(GRADE_PATH_TOTALS));
}

// ─── Backend: inertiaList ganha totals (US-SELL-017) ────────────────────────

it('inertiaList retorna totals com 4 fields canon (count, sum_final_total, sum_total_paid, sum_due)', function () {
    $src = readControllerTotals();
    expect($src)->toContain("'count'");
    expect($src)->toContain("'sum_final_total'");
    expect($src)->toContain("'sum_total_paid'");
    expect($src)->toContain("'sum_due'");
});

it('inertiaList totals usa CLONE do builder ANTES do paginate (preserva filtros, remove order/limit)', function () {
    $src = readControllerTotals();
    // Pattern: $totalsQuery = (clone $q);
    expect($src)->toMatch('/totalsQuery\\s*=\\s*\\(clone\\s+\\$q\\)/');
});

it('inertiaList totals respeita filtro overdue (clone vem DEPOIS de aplicar pill filter)', function () {
    $src = readControllerTotals();
    // O clone deve aparecer DEPOIS do bloco que aplica payment_status filter.
    // Extrai posições e compara.
    $overduePos = strpos($src, "payment_status === 'overdue'");
    if ($overduePos === false) {
        // Fallback regex (variação espacial)
        preg_match('/payment_status\s*===\s*[\'"]overdue[\'"]/', $src, $m, PREG_OFFSET_CAPTURE);
        $overduePos = $m[0][1] ?? false;
    }
    expect($overduePos)->not->toBeFalse();

    $clonePos = strpos($src, '(clone $q)');
    expect($clonePos)->not->toBeFalse();
    expect($clonePos)->toBeGreaterThan($overduePos);
});

it('inertiaList totals respeita filtro de search livre (clone DEPOIS do where search)', function () {
    $src = readControllerTotals();
    $searchPos = strpos($src, "contacts.name', 'like'");
    expect($searchPos)->not->toBeFalse();
    $clonePos = strpos($src, '(clone $q)');
    expect($clonePos)->toBeGreaterThan($searchPos);
});

it('inertiaList totals usa COALESCE(SUM) — defesa contra null em tabela vazia', function () {
    $src = readControllerTotals();
    expect($src)->toMatch('/totalsQuery[\\s\\S]*?COALESCE\\(SUM/');
});

it('inertiaList sum_due = max(0, total - pago) — nunca negativo (defesa numérica)', function () {
    $src = readControllerTotals();
    expect($src)->toMatch('/max\\(0\\.?0?,\\s*\\$sumFinalTotal\\s*-\\s*\\$sumTotalPaid\\)/');
});

it('inertiaList total_paid sum usa subquery transaction_payments (NÃO coluna direct — regrediria bug US-SELL-008)', function () {
    $src = readControllerTotals();
    // No bloco totals: SUM((SELECT ... FROM transaction_payments ...))
    expect($src)->toMatch('/totalsQuery[\\s\\S]*?transaction_payments[\\s\\S]*?tp\\.is_return/');
});

// ─── Frontend: SellsTotalsRow.tsx ───────────────────────────────────────────

it('SellsTotalsRow.tsx existe', function () {
    expect(file_exists(base_path(TOTALS_ROW_PATH)))->toBeTrue();
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsTotalsRow tem 4 labels canon PT-BR (Qtd, Total, Pago, A receber)', function () {
    $src = readTotalsRow();
    expect($src)->toContain('Qtd');
    expect($src)->toContain('Total');
    expect($src)->toContain('Pago');
    expect($src)->toContain('A receber');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsTotalsRow tem 2 modos: compact (Lista opt-in) + sticky-bottom (Grade default)', function () {
    $src = readTotalsRow();
    expect($src)->toContain('compact');
    expect($src)->toContain('sticky bottom-0');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsTotalsRow money formatter usa pt-BR / BRL (vírgula decimal, ponto milhar)', function () {
    $src = readTotalsRow();
    expect($src)->toMatch("/Intl\\.NumberFormat\\([\\s'\"]+pt-BR[\\s'\"]+,[\\s\\S]*?style:\\s*[\'\"]currency[\'\"]/");
    expect($src)->toContain("currency: 'BRL'");
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsTotalsRow Pago é semantic emerald (verde) e A receber é amber (amarelo) — Cockpit V2', function () {
    $src = readTotalsRow();
    expect($src)->toMatch('/text-emerald-700[\\s\\S]*?text-amber-700/');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Frontend: Index.tsx (Lista mode toggle "Mostrar totais") ───────────────

it('Index.tsx tem toggle "Mostrar totais" em Lista mode (US-SELL-017)', function () {
    $src = readIndexTotals();
    expect($src)->toContain('Mostrar totais');
    expect($src)->toContain('Esconder totais');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx toggle "Mostrar totais" persiste em localStorage com prefix oimpresso. (charter ADR 0110)', function () {
    $src = readIndexTotals();
    expect($src)->toContain('oimpresso.sells.showTotalsLista');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx toggle "Mostrar totais" default OFF (não polui Lista limpa)', function () {
    $src = readIndexTotals();
    // Pattern: getItem('oimpresso.sells.showTotalsLista') === '1' (false se nunca setou)
    expect($src)->toMatch('/showTotalsLista[\\s\\S]*?===\\s*[\'"]1[\'"]/');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx renderiza SellsTotalsRow compact em Lista mode quando toggle ativo', function () {
    $src = readIndexTotals();
    expect($src)->toMatch('/showTotalsLista\\s*&&[\\s\\S]*?SellsTotalsRow[\\s\\S]*?compact/');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx captura totals do JSON response (refetch + initial)', function () {
    $src = readIndexTotals();
    expect($src)->toContain('setTotals(json.totals');
});

// ─── Frontend: SellsGradeAvancada (sticky-bottom default) ──────────────────

it('SellsGradeAvancada renderiza SellsTotalsRow sempre (não opt-in — Grade é grid denso power-user)', function () {
    $src = readGradeTotals();
    expect($src)->toContain('<SellsTotalsRow');
    // Sem flag condicional — sempre renderiza
    expect($src)->toMatch('/<SellsTotalsRow\\s+totals=/');
    // quarantine-reason: SellsTotalsRow/SellsGradeAvancada deletados ou toggle Mostrar totais removido do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');
