<?php

declare(strict_types=1);

/**
 * US-SELL-023 — Status produção visível na lista (badge separado).
 *
 * Pattern canon US-SELL-008/017/021: testes ESTRUTURAIS (file_get_contents +
 * regex) — auto-mem feedback_tenancy_changes_require_pest_local dispensa banco
 * real pra mudanças que adicionam JOIN nullable + select scalar (sem mexer em
 * scope/Model multi-tenant).
 *
 * Anti-regressão Tier 0:
 *   - LEFT JOIN preserva vendas legacy sem FSM (current_stage_id NULL → stage_key NULL → "—")
 *   - JOIN não duplica linhas (PK em sale_process_stages.id)
 *   - Mapping 11 stages canônicos (FsmProcessoVendaComProducaoSeeder ADR 0143)
 *   - Frontend mostra "—" silent quando NULL (não polui Lista pra biz sem FSM)
 *   - Tenancy implícito: current_stage_id só pode apontar pra stage do mesmo
 *     business porque ExecuteStageActionService valida (skill multi-tenant-patterns)
 *
 * Refs: ADR 0093 (multi-tenant Tier 0), ADR 0143 (FSM Pipeline LIVE prod biz=1),
 *       FsmProcessoVendaComProducaoSeeder, SPEC US-SELL-023
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — só os it() de frontend que
 * leem `SellsGradeAvancada.tsx` (coluna Produção, `ProducaoStageBadge`) —
 * componente DELETADO no refactor. file_get_contents num arquivo ausente falha.
 * Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * 🔴 Os it() de BACKEND (LEFT JOIN sale_process_stages) e o it() Tier-0
 * (`inertiaList NÃO usa withoutGlobalScopes` — guard cross-tenant biz=99)
 * PERMANECEM ATIVOS. Silenciar o guard withoutGlobalScopes violaria
 * "multi-tenant Tier 0 IRREVOGÁVEL".
 */

const SELL_CONTROLLER_PATH_023 = 'app/Http/Controllers/SellController.php';
const GRADE_PATH_023 = 'resources/js/Pages/Sells/_components/SellsGradeAvancada.tsx';

function readController023(): string
{
    return file_get_contents(base_path(SELL_CONTROLLER_PATH_023));
}

function readGrade023(): string
{
    return file_get_contents(base_path(GRADE_PATH_023));
}

// ─── Backend: SellController@inertiaList ────────────────────────────────────

it('inertiaList faz LEFT JOIN sale_process_stages via current_stage_id (preserva vendas legacy)', function () {
    $src = readController023();
    expect($src)->toMatch('/leftJoin\\([\'"]sale_process_stages\\s+as\\s+sps[\'"]/');
    expect($src)->toContain('transactions.current_stage_id');
});

it('inertiaList retorna current_stage_key no payload (sps.key as current_stage_key)', function () {
    $src = readController023();
    expect($src)->toMatch('/sps\\.key\\s+as\\s+current_stage_key/');
    expect($src)->toContain("'current_stage_key' => \$r->current_stage_key");
});

it('inertiaList JOIN sale_process_stages é LEFT (não INNER) — vendas legacy sem FSM permanecem na lista', function () {
    $src = readController023();
    // Pattern: leftJoin (sem inner). Garante zero perda de rows quando current_stage_id IS NULL.
    expect($src)->toMatch('/leftJoin\\([\'"]sale_process_stages/');
    expect($src)->not->toMatch('/innerJoin\\([\'"]sale_process_stages/');
});

// ─── Frontend: SellsGradeAvancada — coluna Produção + ProducaoStageBadge ────

it('SellsGradeAvancada declara mapping PRODUCAO_STAGE_LABEL com 11 stages canônicos', function () {
    $src = readGrade023();
    // Os 11 stages do FsmProcessoVendaComProducaoSeeder
    foreach ([
        'quote_draft', 'quote_sent', 'quote_approved',
        'in_production', 'ready_for_invoice',
        'invoiced', 'paid', 'delivered', 'completed',
        'cancelled', 'on_hold',
    ] as $stage) {
        expect($src)->toContain("$stage:");
    }
    // quarantine-reason: SellsGradeAvancada.tsx deletado (coluna Produção/ProducaoStageBadge) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada mapping de cores tem 7 estilos PT-BR canon (Aprovação/Em produção/Pronto/Faturada/Entregue/Cancelada/Em espera)', function () {
    $src = readGrade023();
    expect($src)->toContain("'Aprovação'");
    expect($src)->toContain("'Em produção'");
    expect($src)->toContain("'Pronto'");
    expect($src)->toContain("'Faturada'");
    expect($src)->toContain("'Entregue'");
    expect($src)->toContain("'Cancelada'");
    expect($src)->toContain("'Em espera'");
    // quarantine-reason: SellsGradeAvancada.tsx deletado (coluna Produção/ProducaoStageBadge) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada renderiza coluna "Produção" no thead (Grade only — Lista mode é enxuta)', function () {
    $src = readGrade023();
    // Coluna no thead
    expect($src)->toMatch('/<Th[^>]*>Produção<\\/Th>/');
    // quarantine-reason: SellsGradeAvancada.tsx deletado (coluna Produção/ProducaoStageBadge) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada componente ProducaoStageBadge existe + cobre stage_key NULL com "—" silencioso', function () {
    $src = readGrade023();
    expect($src)->toContain('function ProducaoStageBadge');
    expect($src)->toContain('<ProducaoStageBadge');
    // Pattern fallback NULL: "if (!stageKey) return ... —"
    expect($src)->toMatch('/if\\s*\\(!stageKey\\)[\\s\\S]*?—/');
    // quarantine-reason: SellsGradeAvancada.tsx deletado (coluna Produção/ProducaoStageBadge) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada in_production mapeia pra Em produção + cor âmbar', function () {
    $src = readGrade023();
    expect($src)->toMatch("/in_production:\\s*'Em produção'/");
    // Cor âmbar
    expect($src)->toMatch("/in_production:[\\s\\S]{0,200}?text-amber-700/");
    // quarantine-reason: SellsGradeAvancada.tsx deletado (coluna Produção/ProducaoStageBadge) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada cancelled mapeia pra Cancelada + cor rose/destructive', function () {
    $src = readGrade023();
    expect($src)->toMatch("/cancelled:\\s*'Cancelada'/");
    expect($src)->toMatch("/cancelled:[\\s\\S]{0,200}?text-rose-700/");
    // quarantine-reason: SellsGradeAvancada.tsx deletado (coluna Produção/ProducaoStageBadge) (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Tier 0 multi-tenant — defesa simbólica ─────────────────────────────────

it('inertiaList NÃO usa withoutGlobalScopes (cross-tenant biz=99 fica protegido)', function () {
    $src = readController023();
    // Sanity: a função inertiaList completa não pode introduzir bypass cross-tenant.
    $start = strpos($src, 'public function inertiaList');
    $end = strpos($src, 'public function bulkPrint');
    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();
    $body = substr($src, $start, $end - $start);
    expect($body)->not->toContain('withoutGlobalScopes');
});
