<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-EDIT-COWORK Onda Cowork — visual scoped form em /sells/{id}/edit.
 *
 * Cobertura estrutural via file_get_contents (mesmo pattern de
 * SellsShowCoworkTest.php). Foca em garantir que:
 *  - CSS sells-cowork-edit.css existe + scope `.sells-cowork-edit {` + tokens canon
 *  - inertia.css importa o novo CSS
 *  - Edit.tsx wrappa outer div com classe + marker US-SELL-EDIT-COWORK
 *  - Charter Edit.charter.md preservado (anti-patterns não introduzidos)
 *  - Funcionalidade Edit.tsx preservada (useForm + Deferred + AppShellV2 + FSM safety)
 *
 * Refs:
 *  - resources/css/sells-cowork-edit.css
 *  - resources/js/Pages/Sells/Edit.tsx
 *  - resources/js/Pages/Sells/Edit.charter.md
 *  - resources/css/inertia.css
 *  - tests/Feature/Sells/SellsShowCoworkTest.php (pattern espelhado)
 */

const EDIT_TSX_PATH = 'resources/js/Pages/Sells/Edit.tsx';
const EDIT_CHARTER_PATH = 'resources/js/Pages/Sells/Edit.charter.md';
const EDIT_CSS_PATH = 'resources/css/sells-cowork-edit.css';
const EDIT_INERTIA_CSS_PATH = 'resources/css/inertia.css';

function editRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

/**
 * Remove linhas que começam com // (comentários JS) e blocos /* ... *\/ antes
 * de checar anti-patterns regex — evita falso positivo em JSDoc/comentários.
 */
function editStripJsComments(string $source): string
{
    // Remove block comments /* ... */
    $stripped = preg_replace('#/\*[\s\S]*?\*/#', '', $source);
    // Remove linhas line-comment (preserva código relevante)
    $lines = preg_split('/\R/', $stripped);
    $kept = array_filter($lines, function (string $line): bool {
        return ! preg_match('/^\s*\/\//', $line);
    });

    return implode("\n", $kept);
}

// ─── Arquivos existem ─────────────────────────────────────────────────

it('CSS sells-cowork-edit.css existe', function () {
    expect(file_exists(base_path(EDIT_CSS_PATH)))->toBeTrue();
});

it('Edit.tsx existe (não removido)', function () {
    expect(file_exists(base_path(EDIT_TSX_PATH)))->toBeTrue();
});

it('Edit.charter.md existe e mantém parent_module Sells', function () {
    expect(file_exists(base_path(EDIT_CHARTER_PATH)))->toBeTrue();
    $charter = editRead(EDIT_CHARTER_PATH);
    expect($charter)->toContain('parent_module: Sells');
});

// ─── CSS scoped + tokens canon ────────────────────────────────────────

it('CSS sells-cowork-edit.css escopa em .sells-cowork-edit {', function () {
    $source = editRead(EDIT_CSS_PATH);
    expect($source)->toContain('.sells-cowork-edit {');
});

it('CSS sells-cowork-edit.css usa tokens oklch (palette canon — não cor crua)', function () {
    $source = editRead(EDIT_CSS_PATH);
    expect($source)
        ->toContain('oklch(')
        ->toContain('--accent:')
        ->toContain('--text:')
        ->toContain('--border:');
});

it('CSS sells-cowork-edit.css define IBM Plex Sans/Mono (canon família)', function () {
    $source = editRead(EDIT_CSS_PATH);
    expect($source)
        ->toContain('IBM Plex Sans')
        ->toContain('IBM Plex Mono');
});

it('CSS sells-cowork-edit.css cobre blocos essenciais (header/section/field/footer)', function () {
    $source = editRead(EDIT_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-edit .vde-header')
        ->toContain('.sells-cowork-edit .vde-h1')
        ->toContain('.sells-cowork-edit .vde-section')
        ->toContain('.sells-cowork-edit .vde-section-head')
        ->toContain('.sells-cowork-edit .vde-section-num')
        ->toContain('.sells-cowork-edit .vde-field')
        ->toContain('.sells-cowork-edit .vde-footer');
});

it('CSS sells-cowork-edit.css define form states (focus/error/disabled — semantic)', function () {
    $source = editRead(EDIT_CSS_PATH);
    expect($source)
        ->toContain('--focus-ring:')
        ->toContain('--error:')
        ->toContain('.vde-field:focus')
        ->toContain('.vde-field:disabled')
        ->toContain('.vde-field-error');
});

it('CSS sells-cowork-edit.css define footer sticky com botões Cancelar/Salvar', function () {
    $source = editRead(EDIT_CSS_PATH);
    expect($source)
        ->toContain('position: sticky')
        ->toContain('.vde-btn')
        ->toContain('.vde-btn-primary');
});

it('CSS sells-cowork-edit.css tem responsive ≤1024px + print media', function () {
    $source = editRead(EDIT_CSS_PATH);
    expect($source)
        ->toContain('@media (max-width: 1024px)')
        ->toContain('@media print');
});

// ─── Inertia.css importa ──────────────────────────────────────────────

it('inertia.css importa sells-cowork-edit.css', function () {
    $source = editRead(EDIT_INERTIA_CSS_PATH);
    expect($source)->toContain('@import "./sells-cowork-edit.css"');
});

// ─── Edit.tsx wrappa com classe + marker ─────────────────────────────

it('Edit.tsx wrappa outer div com classe .sells-cowork-edit', function () {
    $source = editRead(EDIT_TSX_PATH);
    expect($source)->toContain('sells-cowork-edit container mx-auto');
});

it('Edit.tsx tem marker comentário US-SELL-EDIT-COWORK', function () {
    $source = editRead(EDIT_TSX_PATH);
    expect($source)->toContain('US-SELL-EDIT-COWORK');
});

// ─── Funcionalidade preservada (não-regressão) ───────────────────────

it('Edit.tsx preserva AppShellV2 layout', function () {
    $source = editRead(EDIT_TSX_PATH);
    expect($source)
        ->toContain("import AppShellV2 from '@/Layouts/AppShellV2'")
        ->toContain('SellsEdit.layout');
});

it('Edit.tsx preserva useForm + Deferred (form deferred pattern ADR 0149)', function () {
    $source = editRead(EDIT_TSX_PATH);
    expect($source)
        ->toContain('useForm')
        ->toContain('<Deferred data="form"');
});

it('Edit.tsx preserva atalho Cmd/Ctrl+Enter + guards permissions.update', function () {
    $source = editRead(EDIT_TSX_PATH);
    expect($source)
        ->toContain("e.key === 'Enter'")
        ->toContain('metaKey')
        ->toContain('permissions.update');
});

it('Edit.tsx preserva FSM safety (NUNCA seta current_stage_id — ADR 0143)', function () {
    $source = editRead(EDIT_TSX_PATH);
    // Não pode existir setData('current_stage_id', ...) nem current_stage_id no useForm
    expect($source)->not->toContain("setData('current_stage_id'");
    expect($source)->not->toMatch('/current_stage_id:\s*[^,}\n]+(?:,|\n|\})/');
});

// ─── Anti-patterns charter NÃO introduzidos ──────────────────────────
// Charter Edit.charter.md §UX Anti-patterns: ❌ font-bold em h1 / ❌ AppShell sem V2 / ❌ cor crua

it('Edit.tsx NÃO introduz font-bold em h1 (anti-pattern charter)', function () {
    $source = editStripJsComments(editRead(EDIT_TSX_PATH));
    // Permitido font-semibold; proibido font-bold em <h1>
    expect($source)->not->toMatch('/<h1[^>]*font-bold/');
});

it('Edit.tsx NÃO introduz border-b-2 (anti-pattern charter)', function () {
    $source = editStripJsComments(editRead(EDIT_TSX_PATH));
    expect($source)->not->toContain('border-b-2');
});

it('CSS sells-cowork-edit.css NÃO usa cor crua bg-blue-500 (anti-pattern charter)', function () {
    // Strip CSS block comments antes de validar — comentário documental cita
    // anti-pattern como exemplo do que NÃO fazer, isso é OK.
    $source = preg_replace('#/\*[\s\S]*?\*/#', '', editRead(EDIT_CSS_PATH));
    expect($source)
        ->not->toContain('bg-blue-500')
        ->not->toContain('#3b82f6');
});

// ─── Charter status preservado ───────────────────────────────────────

it('Edit.charter.md mantém parent_module + related_adrs 0104/0143/0093/0149 (governança)', function () {
    $charter = editRead(EDIT_CHARTER_PATH);
    expect($charter)
        ->toContain('parent_module: Sells')
        ->toContain('0104')
        ->toContain('0143')
        ->toContain('0093')
        ->toContain('0149');
});
