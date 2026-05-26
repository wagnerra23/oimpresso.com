<?php

declare(strict_types=1);

/**
 * Pest — Sells/Edit Parking-Lot P1 + P2 + P3 (parity post-PR #1663).
 *
 * Cobertura estrutural via file_get_contents — garante que as 3 features
 * combinadas neste PR estão wire-up no frontend Edit.tsx:
 *
 *  P1 — Features só-no-Blade preservadas (IMEI inline, customer_secondary_address,
 *       is_recurring checkbox, sell_document upload, commission_agent select,
 *       per-line discount_type R$/% toggle, staff_note textarea separada)
 *  P2 — Auto-save draft localStorage com key per-sale (`oimpresso.sells.b{biz}.u{user}.edit.{id}.draft`)
 *       + TTL 24h + "Descartar rascunho" button
 *  P3 — Cmd+Enter chama handleSubmit programaticamente (não mais useForm.put que
 *       perdia products[])
 *
 * Pattern espelha SellsEditCoworkTest.php (file_get_contents structural assertions).
 *
 * Refs:
 *  - resources/js/Pages/Sells/Edit.tsx
 *  - resources/js/Pages/Sells/Create.tsx (auto-save pattern reference)
 *  - app/Http/Controllers/SellPosController.php@update (backend accepts is_recurring,
 *    staff_note, commission_agent, sell_document via $request->except('_token'))
 *  - memory/sessions/2026-05-26-kb975-stack-final-26prs-paridade-95pct.md
 */

const EDIT_TSX = 'resources/js/Pages/Sells/Edit.tsx';

function plRoot(): string
{
    // Sobe 3 níveis de tests/Feature/Sells → repo root. Compatível com worktrees
    // (não depende de base_path() do Laravel container, que pode não estar bootstrapped).
    return dirname(__DIR__, 3);
}

function plRead(string $rel): string
{
    return file_get_contents(plRoot() . DIRECTORY_SEPARATOR . $rel);
}

// ─── P1 — Features só-no-Blade preservadas ──────────────────────────

it('Edit.tsx adiciona campo IMEI/serial inline por linha de produto', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('imei_number')
        ->toContain('IMEI / nº série')
        ->toContain('imei_number: e.target.value');
});

it('Edit.tsx adiciona toggle desconto R$/% per-line (discount_type)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain("discount_type: 'fixed' | 'percentage'")
        ->toContain('discount_type: \'fixed\'')
        ->toContain("e.target.value as 'fixed' | 'percentage'");
});

it('Edit.tsx adiciona campo customer_secondary_address (endereço cobrança ≠ entrega)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('customer_secondary_address')
        ->toContain('Endereço de cobrança');
});

it('Edit.tsx adiciona checkbox is_recurring (Assinatura recorrente)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('is_recurring')
        ->toContain('Assinatura recorrente')
        ->toContain('checked={data.is_recurring === 1}');
});

it('Edit.tsx adiciona upload file sell_document (.pdf/.csv/.zip/.doc/.docx/.jpg/.png)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('sell_document')
        ->toContain('.pdf,.csv,.zip,.doc,.docx,.jpg,.jpeg,.png')
        ->toContain('5 * 1024 * 1024'); // 5MB max
});

it('Edit.tsx adiciona select commission_agent (Responsável/comissionado)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('commission_agent')
        ->toContain('Responsável')
        ->toContain('form.users');
});

it('Edit.tsx adiciona textarea staff_note separada de additional_notes', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('staff_note')
        ->toContain('Nota interna')
        ->toContain('visível só pra equipe');
});

// ─── P1 — Backend payload wire-up ──────────────────────────

it('Edit.tsx envia sell_document via router.post + _method=put + forceFormData (multipart)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain("payload._method = 'put'")
        ->toContain('forceFormData: true')
        ->toContain('router.post');
});

it('Edit.tsx envia is_recurring como 0/1 (paridade backend TransactionUtil)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)->toContain('is_recurring: data.is_recurring ? 1 : 0');
});

it('Edit.tsx buildProductsPayload honra discount_type per-line + imei_number', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain("line_discount_type: p.discount_type ?? 'fixed'")
        ->toContain('imei_number: p.imei_number ?? \'\'');
});

// ─── P2 — Auto-save draft localStorage ──────────────────────────

it('Edit.tsx tem DRAFT_TTL_MS = 24h (espelha Create.tsx)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)->toContain('DRAFT_TTL_MS = 24 * 60 * 60 * 1000');
});

it('Edit.tsx auto-save lsKey inclui business_id + user_id + sale_id (multi-tenant + multi-sale)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)->toContain('`oimpresso.sells.b${bizId}.u${userId}.edit.${saleId}.draft`');
});

it('Edit.tsx auto-save é debounced 500ms (paridade Create)', function () {
    $src = plRead(EDIT_TSX);
    // Procura setTimeout com 500ms próximo a localStorage.setItem.
    expect($src)
        ->toContain('500')
        ->toContain('localStorage.setItem(draftKey')
        ->toContain('setTimeout');
});

it('Edit.tsx mostra botão Descartar rascunho quando draft restaurado', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('draftRestored')
        ->toContain('Descartar rascunho');
});

it('Edit.tsx descarta draft stale (backend updated_at > draft.savedAt)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)
        ->toContain('backendUpdated > parsed.savedAt')
        ->toContain('localStorage.removeItem(draftKey)');
});

// ─── P3 — Cmd+Enter passa products[] no payload ──────────────────────────

it('Edit.tsx Cmd+Enter chama handleSubmit (não mais useForm.put que perdia products)', function () {
    $src = plRead(EDIT_TSX);
    // Cmd+Enter handler precisa chamar handleSubmit, NÃO put()
    expect($src)
        ->toContain("handleSubmit({ preventDefault: () => {} }")
        ->toContain("e.key === 'Enter'")
        ->toContain('metaKey');
});

it('Edit.tsx removeu destructure do put de useForm (uso direto router.put)', function () {
    $src = plRead(EDIT_TSX);
    // Não destructura mais put (só data, setData, processing, errors).
    expect($src)->toContain('const { data, setData, processing, errors } = useForm');
    expect($src)->not->toContain('const { data, setData, put,');
});

// ─── Tier 0 IRREVOGÁVEL — FSM safety + multi-tenant preservados ──────────

it('Edit.tsx PRESERVA FSM safety (NUNCA seta current_stage_id — ADR 0143)', function () {
    $src = plRead(EDIT_TSX);
    expect($src)->not->toContain("setData('current_stage_id'");
    expect($src)->not->toMatch('/current_stage_id:\s*[^,}\n]+(?:,|\n|\})/');
});

it('Edit.tsx PRESERVA multi-tenant Tier 0 (ADR 0093) — lsKey biz+user obrigatório', function () {
    $src = plRead(EDIT_TSX);
    // Sem bizId/userId não monta draftKey — early return previne cross-tenant leak.
    expect($src)
        ->toContain('if (!bizId || !userId || !saleId) return null')
        ->toContain('useAuth')
        ->toContain('useBusiness');
});
