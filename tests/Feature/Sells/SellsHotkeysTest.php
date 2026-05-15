<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Atalhos teclado /sells/create (US-SELL-P0-3).
 *
 * Cobre P0-3 do RUNBOOK-paridade-create §3.7 + §5:
 *   - 7 atalhos (`/`, F2, F9, Alt+P, Alt+R, Ctrl+Enter preservado, Esc preservado)
 *   - Footer microcopy <kbd> (Quick Win #2 design-arte δ)
 *   - Guards multi-tenant: dispatch via window.keydown não captura tecla
 *     quando target.tag = input/textarea/select
 *
 * Runtime test (Playwright/Cypress) NÃO disponível neste repo — tests
 * estruturais validam contrato file-system + grep textual.
 *
 * Refs:
 *   - memory/requisitos/Sells/RUNBOOK-paridade-create.md §3.7 atalhos (#56-#59) + §5 P0-3
 *   - memory/sessions/2026-05-14-design-arte-sells-create-noite.md Quick Win #1 + #2
 *   - resources/views/sale_pos/partials/keyboard_shortcuts.blade.php (Mousetrap legacy)
 *   - Canary: MARTINHO CAÇAMBAS biz=164 — Pain #1 reunião 13/maio "velocidade"
 */

const HOOK_PATH = 'resources/js/Pages/Sells/_components/useSellsHotkeys.ts';
const HINT_PATH = 'resources/js/Pages/Sells/_components/HotkeysHint.tsx';
const PAGE_PATH = 'resources/js/Pages/Sells/Create.tsx';

function readHotkeysHook(): string
{
    return file_get_contents(base_path(HOOK_PATH));
}

function readHotkeysHint(): string
{
    return file_get_contents(base_path(HINT_PATH));
}

function readSellsCreate(): string
{
    return file_get_contents(base_path(PAGE_PATH));
}

it('hook useSellsHotkeys.ts existe', function () {
    expect(file_exists(base_path(HOOK_PATH)))->toBeTrue();
});

it('hook exporta useSellsHotkeys + interface SellsHotkeysHandlers (contrato TS)', function () {
    $source = readHotkeysHook();
    expect($source)->toContain('export function useSellsHotkeys');
    expect($source)->toContain('export interface SellsHotkeysHandlers');
});

it('hook registra os 5 handlers canônicos (onFocusProduct/FirstField/Submit/Print/Reset)', function () {
    $source = readHotkeysHook();
    expect($source)->toContain('onFocusProduct');
    expect($source)->toContain('onFocusFirstField');
    expect($source)->toContain('onSubmit');
    expect($source)->toContain('onPrint');
    expect($source)->toContain('onReset');
});

it('hook trata os 5 atalhos novos (`/`, F2, F9, Alt+P, Alt+R)', function () {
    $source = readHotkeysHook();
    // `/` — foca produto
    expect($source)->toMatch("/e\\.key === '\\/'/");
    // F2 — foca primeiro field
    expect($source)->toContain("e.key === 'F2'");
    // F9 — submit
    expect($source)->toContain("e.key === 'F9'");
    // Alt+P — print
    expect($source)->toContain("e.altKey");
    expect($source)->toMatch("/e\\.key === 'p' \\|\\| e\\.key === 'P'/");
    // Alt+R — reset
    expect($source)->toMatch("/e\\.key === 'r' \\|\\| e\\.key === 'R'/");
});

it('hook preserva atalhos existentes (Ctrl+Enter submit + Esc blur) — não consome indevidamente', function () {
    $source = readHotkeysHook();
    // Hook explicitamente NÃO trata Ctrl+Enter (preserva tratamento existente no Create.tsx)
    expect($source)->toContain("e.key === 'Escape'");
    expect($source)->toContain("e.key === 'Enter'");
    // Deve ter early return pra Escape e Ctrl+Enter (preserva listeners Create.tsx)
    expect($source)->toMatch('/Escape[\s\S]{0,200}return;/');
});

it('hook tem guard de input/textarea/select pra atalho "/" (não rouba tecla quando user digita)', function () {
    $source = readHotkeysHook();
    expect($source)->toContain('isEditableTarget');
    expect($source)->toContain("tag === 'INPUT'");
    expect($source)->toContain("tag === 'TEXTAREA'");
    expect($source)->toContain("tag === 'SELECT'");
    // WCAG 2.2 AA — atalho de tecla única não captura input
    expect($source)->toContain('isContentEditable');
});

it('hook tem guard de modal aberto (não dispara atalho quando modal Radix/Bootstrap visível)', function () {
    $source = readHotkeysHook();
    expect($source)->toContain('isModalOpen');
    expect($source)->toContain('role="dialog"');
    expect($source)->toContain('aria-modal');
});

it('hook reset tem confirm dialog nativo (Wagner trauma "não remover dados sem aviso")', function () {
    $source = readHotkeysHook();
    expect($source)->toContain('window.confirm');
});

it('hook deixa TODO pra fase business.pos_settings.shortcuts JSON dinâmico (paridade Mousetrap configurável)', function () {
    $source = readHotkeysHook();
    // Próxima iteração: atalhos vêm do business config (paridade Blade
    // keyboard_shortcuts.blade.php). Comentário documenta intent.
    expect($source)->toContain('pos_settings');
});

it('componente HotkeysHint.tsx existe', function () {
    expect(file_exists(base_path(HINT_PATH)))->toBeTrue();
});

it('HotkeysHint renderiza <kbd> com 4 atalhos principais (Quick Win #2 design-arte δ)', function () {
    $source = readHotkeysHint();
    expect($source)->toContain('<kbd');
    // 4 atalhos canônicos exibidos pra usuária descobrir sem manual
    expect($source)->toContain("'/'");
    expect($source)->toContain("'F9'");
    expect($source)->toContain("'Ctrl+Enter'");
    expect($source)->toContain("'Esc'");
});

it('HotkeysHint usa cor semântica muted (não compete com botões primários)', function () {
    $source = readHotkeysHint();
    // ADR 0110 cores semânticas: text-muted-foreground / bg-muted (canon)
    expect($source)->toContain('text-muted-foreground');
    expect($source)->toContain('bg-muted');
});

it('Create.tsx importa e usa useSellsHotkeys (integração wireup)', function () {
    $source = readSellsCreate();
    expect($source)->toContain("from './_components/useSellsHotkeys'");
    expect($source)->toContain('useSellsHotkeys({');
});

it('Create.tsx wireup passa os 5 handlers (focusProduct/FirstField/submit/print/reset)', function () {
    $source = readSellsCreate();
    expect($source)->toContain('onFocusProduct: focusProductSearch');
    expect($source)->toContain('onFocusFirstField:');
    expect($source)->toContain('onSubmit:');
    expect($source)->toContain('onPrint:');
    expect($source)->toContain('onReset:');
});

it('Create.tsx importa e renderiza <HotkeysHint /> no footer (Quick Win #2)', function () {
    $source = readSellsCreate();
    expect($source)->toContain("import HotkeysHint from './_components/HotkeysHint'");
    expect($source)->toContain('<HotkeysHint />');
});

it('Create.tsx preserva useForm reset (paridade Mousetrap legacy cancel/draft)', function () {
    $source = readSellsCreate();
    // reset é destructured de useForm — necessário pra onReset callback (Alt+R)
    // Pattern: `{ data, setData, ..., reset } = useForm(`
    expect($source)->toMatch('/reset\s*}\s*=\s*useForm\(/');
});

it('Create.tsx NÃO remove atalhos existentes Ctrl+Enter + Esc (paridade preservação)', function () {
    $source = readSellsCreate();
    // useEffect Ctrl+Enter existente preservado
    expect($source)->toContain("e.key === 'Enter'");
    // useEffect Esc blur existente preservado
    expect($source)->toContain("e.key === 'Escape'");
});

it('RUNBOOK paridade-create cataloga o gap P0-3 atalhos (gate audit trail)', function () {
    $runbook = file_get_contents(base_path('memory/requisitos/Sells/RUNBOOK-paridade-create.md'));
    // §5 P0-3 catalogado
    expect($runbook)->toContain('P0-3');
    expect($runbook)->toContain('Atalhos teclado');
});
