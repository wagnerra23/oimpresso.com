<?php

declare(strict_types=1);

/**
 * Pest STRUCTURAL — KB-9.75 P3 #12 (cheat-sheet) + P1 #10 (toast hub).
 *
 * Cobre gaps P3 #12 (cheat-sheet overlay '?') e P1 #10 (oimpressoToast hub
 * canônico) do `memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md`.
 *
 * Refs:
 *  - resources/js/Pages/Sells/_components/SellsCheatSheet.tsx (NOVO)
 *  - resources/js/Lib/oimpressoToast.ts (NOVO)
 *  - resources/css/sells-kb975-cheatsheet.css (NOVO)
 *  - resources/js/Pages/Sells/Index.tsx (wire-up)
 *  - resources/js/Pages/Sells/Show.tsx (wire-up)
 */

const KB975_CHEAT_PATH = 'resources/js/Pages/Sells/_components/SellsCheatSheet.tsx';
const KB975_TOAST_PATH = 'resources/js/Lib/oimpressoToast.ts';
const KB975_CHEAT_CSS = 'resources/css/sells-kb975-cheatsheet.css';
const KB975_INERTIA_CSS = 'resources/css/inertia.css';
const KB975_INDEX_PAGE = 'resources/js/Pages/Sells/Index.tsx';
const KB975_SHOW_PAGE = 'resources/js/Pages/Sells/Show.tsx';

// ──────────────────────────────────────────────────────────────
// SellsCheatSheet.tsx (gap P3 #12)
// ──────────────────────────────────────────────────────────────

it('SellsCheatSheet.tsx existe em _components', function () {
    expect(file_exists(base_path(KB975_CHEAT_PATH)))->toBeTrue();
});

it('SellsCheatSheet declara Props com open + onClose + shortcuts controlados externamente', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_PATH));
    expect($source)->toContain('open: boolean');
    expect($source)->toContain('onClose: () => void');
    expect($source)->toContain('shortcuts: SellsShortcut[]');
});

it('SellsCheatSheet exporta interface SellsShortcut com kbd + label + area', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_PATH));
    expect($source)->toContain('export interface SellsShortcut');
    expect($source)->toContain('kbd: string | string[]');
    expect($source)->toContain('label: ReactNode');
    expect($source)->toContain('area?: string');
});

it('SellsCheatSheet trata Esc e ? via listener próprio (idempotente com Page handler)', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_PATH));
    expect($source)->toContain("e.key === 'Escape'");
    expect($source)->toContain("e.key === '?'");
    expect($source)->toContain("window.addEventListener('keydown'");
    expect($source)->toContain("window.removeEventListener('keydown'");
});

it('SellsCheatSheet renderiza overlay backdrop + dialog ARIA modal', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_PATH));
    expect($source)->toContain('vd-cheat-bd');
    expect($source)->toContain('vd-cheat');
    expect($source)->toContain('role="dialog"');
    expect($source)->toContain('aria-modal="true"');
});

it('SellsCheatSheet exporta listas canon SELLS_INDEX_SHORTCUTS e SELLS_SHOW_SHORTCUTS', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_PATH));
    expect($source)->toContain('export const SELLS_INDEX_SHORTCUTS');
    expect($source)->toContain('export const SELLS_SHOW_SHORTCUTS');
});

it('SELLS_INDEX_SHORTCUTS cobre atalhos canon J/K/Enter/N/E/?/Esc/Cmd+K', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_PATH));
    // Lista mínima Cowork KB-9.75 — Wagner aprovou no prompt
    expect($source)->toContain("kbd: 'J'");
    expect($source)->toContain("kbd: 'K'");
    expect($source)->toContain("kbd: 'Enter'");
    expect($source)->toContain("kbd: 'N'");
    expect($source)->toContain("kbd: 'E'");
    expect($source)->toContain("kbd: '?'");
    expect($source)->toContain("kbd: 'Esc'");
    expect($source)->toContain("kbd: ['⌘', 'K']");
});

// ──────────────────────────────────────────────────────────────
// oimpressoToast.ts (gap P1 #10)
// ──────────────────────────────────────────────────────────────

it('oimpressoToast.ts existe em resources/js/Lib', function () {
    expect(file_exists(base_path(KB975_TOAST_PATH)))->toBeTrue();
});

it('oimpressoToast exporta API canon invoiced/paid/emitted/error/info/warning', function () {
    $source = file_get_contents(base_path(KB975_TOAST_PATH));
    expect($source)->toContain('export const oimpressoToast');
    expect($source)->toContain('invoiced,');
    expect($source)->toContain('paid,');
    expect($source)->toContain('emitted,');
    expect($source)->toContain('error,');
    expect($source)->toContain('info,');
    expect($source)->toContain('warning,');
});

it('oimpressoToast dispatch CustomEvent oimpresso:toast no window com detail tone/msg/saleId', function () {
    $source = file_get_contents(base_path(KB975_TOAST_PATH));
    expect($source)->toContain("'oimpresso:toast'");
    expect($source)->toContain('new CustomEvent(HUB_EVENT');
    expect($source)->toContain('tone:');
    expect($source)->toContain('msg,');
    expect($source)->toContain('saleId');
});

it('oimpressoToast dispatch eventos namespaced venda-invoiced/paid/emitted-nfe/nfse', function () {
    $source = file_get_contents(base_path(KB975_TOAST_PATH));
    // Glossário BR canon — Faturar ≠ Receber pagamento (gap #6 vocabulário)
    expect($source)->toContain('oimpresso:venda-invoiced');
    expect($source)->toContain('oimpresso:venda-paid');
    expect($source)->toContain('oimpresso:venda-emitted-nfe');
    expect($source)->toContain('oimpresso:venda-emitted-nfse');
});

it('oimpressoToast usa sonner toast (não substitui — wrapper opcional canon)', function () {
    $source = file_get_contents(base_path(KB975_TOAST_PATH));
    expect($source)->toContain("import { toast } from 'sonner'");
    expect($source)->toContain('toast.success(');
    expect($source)->toContain('toast.error(');
    expect($source)->toContain('toast.info(');
});

it('oimpressoToast emitted aceita kind nfe ou nfse e roteia o evento correto', function () {
    $source = file_get_contents(base_path(KB975_TOAST_PATH));
    expect($source)->toContain("kind: 'nfe' | 'nfse'");
    expect($source)->toContain("kind === 'nfe'");
});

it('oimpressoToast trata ambiente sem window (SSR-safe, no-op silent)', function () {
    $source = file_get_contents(base_path(KB975_TOAST_PATH));
    expect($source)->toContain("typeof window === 'undefined'");
});

// ──────────────────────────────────────────────────────────────
// CSS bundle
// ──────────────────────────────────────────────────────────────

it('sells-kb975-cheatsheet.css existe', function () {
    expect(file_exists(base_path(KB975_CHEAT_CSS)))->toBeTrue();
});

it('sells-kb975-cheatsheet.css NÃO está escopado em .sells-cowork (overlay fullscreen global)', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_CSS));
    // O overlay precisa renderizar acima de qualquer wrapper — Index, Show, Caixa, etc.
    // Por isso .vd-cheat-bd e .vd-cheat ficam top-level, NÃO prefixados.
    expect($source)->toContain('.vd-cheat-bd {');
    expect($source)->toContain('.vd-cheat {');
    expect($source)->not->toContain('.sells-cowork .vd-cheat-bd');
});

it('sells-kb975-cheatsheet.css define grid 2 colunas default + responsive 3 cols / 1 col mobile', function () {
    $source = file_get_contents(base_path(KB975_CHEAT_CSS));
    expect($source)->toContain('grid-template-columns: 1fr 1fr');
    expect($source)->toContain('grid-template-columns: repeat(3, 1fr)');
    expect($source)->toContain('@media (max-width: 900px)');
});

it('sells-kb975-cheatsheet.css importada em inertia.css', function () {
    $source = file_get_contents(base_path(KB975_INERTIA_CSS));
    expect($source)->toContain('sells-kb975-cheatsheet.css');
});

// ──────────────────────────────────────────────────────────────
// Sells/Index.tsx wire-up
// ──────────────────────────────────────────────────────────────

it('Sells/Index.tsx importa SellsCheatSheet + SELLS_INDEX_SHORTCUTS', function () {
    $source = file_get_contents(base_path(KB975_INDEX_PAGE));
    expect($source)->toContain("import SellsCheatSheet, { SELLS_INDEX_SHORTCUTS } from './_components/SellsCheatSheet'");
});

it('Sells/Index.tsx renderiza SellsCheatSheet controlado por cheatOpen', function () {
    $source = file_get_contents(base_path(KB975_INDEX_PAGE));
    expect($source)->toContain('<SellsCheatSheet');
    expect($source)->toContain('open={cheatOpen}');
    expect($source)->toContain('onClose={() => setCheatOpen(false)}');
    expect($source)->toContain('shortcuts={SELLS_INDEX_SHORTCUTS}');
});

it('Sells/Index.tsx mantém handler que abre cheatOpen via ? (sem regressão)', function () {
    $source = file_get_contents(base_path(KB975_INDEX_PAGE));
    expect($source)->toContain("e.key === '?'");
    expect($source)->toContain('setCheatOpen(true)');
});

it('Sells/Index.tsx NÃO contém mais a função inline SellsCheatSheet (extraída pra _components)', function () {
    $source = file_get_contents(base_path(KB975_INDEX_PAGE));
    // A definição inline foi extraída — não pode haver `function SellsCheatSheet(`
    // local no arquivo. O import + JSX continuam.
    expect($source)->not->toMatch('/function\s+SellsCheatSheet\s*\(/');
});

// ──────────────────────────────────────────────────────────────
// Sells/Show.tsx wire-up
// ──────────────────────────────────────────────────────────────

it('Sells/Show.tsx importa SellsCheatSheet + SELLS_SHOW_SHORTCUTS', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain("import SellsCheatSheet, { SELLS_SHOW_SHORTCUTS } from './_components/SellsCheatSheet'");
});

it('Sells/Show.tsx declara state cheatOpen e renderiza overlay', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain('const [cheatOpen, setCheatOpen]');
    expect($source)->toContain('<SellsCheatSheet');
    expect($source)->toContain('shortcuts={SELLS_SHOW_SHORTCUTS}');
});

it('Sells/Show.tsx adiciona ? ao handler keydown (abre cheat-sheet)', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain("e.key === '?'");
    expect($source)->toContain('setCheatOpen(true)');
});

it('Sells/Show.tsx suprime E/P/Esc-back enquanto cheatOpen (precedência correta)', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    // Suprimir outros atalhos quando overlay aberta evita Esc → router.visit(back)
    // disparar quando o usuário queria só fechar o overlay.
    expect($source)->toContain('if (cheatOpen) return');
});

it('Sells/Show.tsx mantém atalhos E/P/Esc-back (sem regressão dos handlers existentes)', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain("e.key === 'e'");
    expect($source)->toContain("e.key === 'p'");
    expect($source)->toContain("e.key === 'Escape'");
});
