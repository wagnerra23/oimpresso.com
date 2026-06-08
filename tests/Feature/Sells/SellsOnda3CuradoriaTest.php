<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R3-CURADORIA Onda 3 — estrutura dos 3 componentes
 * (SaleItemComments + SaleAuditTrail + SaleLinkifier) integrados no SaleSheet.
 *
 * Cobertura estrutural via file_get_contents (Pest browser cobre interativo
 * quando estabilizar). Foca em garantir que:
 *  - componentes existem nos paths canônicos
 *  - SaleSheet importa todos 3 + plug-points renderizam
 *  - CSS .sells-cowork-curadoria scoped + importado em inertia.css
 *
 * Refs:
 *  - resources/js/Pages/Sells/_components/{SaleItemComments,SaleAuditTrail,SaleLinkifier}.tsx
 *  - resources/css/sells-cowork-curadoria.css
 */

const R3_SHEET_PATH = 'resources/js/Pages/Sells/_components/SaleSheet.tsx';
const R3_COMMENTS_PATH = 'resources/js/Pages/Sells/_components/SaleItemComments.tsx';
const R3_AUDIT_PATH = 'resources/js/Pages/Sells/_components/SaleAuditTrail.tsx';
const R3_LINKIFY_PATH = 'resources/js/Pages/Sells/_components/SaleLinkifier.tsx';
const R3_CSS_PATH = 'resources/css/sells-cowork-curadoria.css';
const R3_INERTIA_CSS_PATH = 'resources/css/inertia.css';

function r3Read(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Componentes existem ──────────────────────────────────────────────

it('SaleItemComments component existe', function () {
    expect(file_exists(base_path(R3_COMMENTS_PATH)))->toBeTrue();
});

it('SaleAuditTrail component existe', function () {
    expect(file_exists(base_path(R3_AUDIT_PATH)))->toBeTrue();
});

it('SaleLinkifier component existe', function () {
    expect(file_exists(base_path(R3_LINKIFY_PATH)))->toBeTrue();
});

it('CSS sells-cowork-curadoria.css existe', function () {
    expect(file_exists(base_path(R3_CSS_PATH)))->toBeTrue();
});

// ─── SaleItemComments ─────────────────────────────────────────────────

it('SaleItemComments exporta hook useSaleItemComments + default component', function () {
    $source = r3Read(R3_COMMENTS_PATH);
    expect($source)
        ->toContain('export function useSaleItemComments()')
        ->toContain('export default function SaleItemComments(');
});

it('useSaleItemComments persiste em localStorage[oimpresso.sells.itemComments]', function () {
    $source = r3Read(R3_COMMENTS_PATH);
    expect($source)
        ->toContain("'oimpresso.sells.itemComments'")
        ->toContain('window.localStorage.getItem(LS_KEY)')
        ->toContain('window.localStorage.setItem(LS_KEY');
});

it('useSaleItemComments retorna { add, remove, get, countFor }', function () {
    $source = r3Read(R3_COMMENTS_PATH);
    expect($source)
        ->toContain('const add = useCallback(')
        ->toContain('const remove = useCallback(')
        ->toContain('const get = useCallback(')
        ->toContain('const countFor = useCallback(');
});

it('SaleItemComments usa ⌘↵ shortcut pra submit (Cmd+Enter)', function () {
    $source = r3Read(R3_COMMENTS_PATH);
    expect($source)
        ->toContain("e.key === 'Enter' && (e.metaKey || e.ctrlKey)")
        ->toContain('⌘↵');
});

// ─── SaleAuditTrail ───────────────────────────────────────────────────

it('SaleAuditTrail buildEntries cobre create + payment + fiscal autorizada + rejeitada', function () {
    $source = r3Read(R3_AUDIT_PATH);
    expect($source)
        ->toContain("kind: 'create'")
        ->toContain("kind: 'payment'")
        ->toContain("kind: 'fiscal'")
        ->toContain("kind: 'reject'")
        ->toContain("'autorizada'")
        ->toContain("'rejeitada'");
});

it('SaleAuditTrail formata data PT-BR + sort cronologico', function () {
    $source = r3Read(R3_AUDIT_PATH);
    expect($source)
        ->toContain("toLocaleString('pt-BR'")
        ->toContain('.sort((a, b) => a.when.localeCompare(b.when))');
});

it('SaleAuditTrail exporta tipo SaleAuditInput', function () {
    $source = r3Read(R3_AUDIT_PATH);
    expect($source)
        ->toContain('interface SaleAuditInput')
        ->toContain('export type { SaleAuditInput }');
});

// ─── SaleLinkifier ────────────────────────────────────────────────────

it('SaleLinkifier parseia 4 padrões: #V-NNNN, #OS-NNNN, #CLI-Nome, #orc-NNNN', function () {
    $source = r3Read(R3_LINKIFY_PATH);
    expect($source)
        ->toContain("re: /#V-(\\d+)/gi")
        ->toContain("re: /#OS-(\\d+)/gi")
        ->toContain("re: /#CLI-([A-Za-zÀ-ÿ0-9_-]+)/g")
        ->toContain("re: /#orc-(\\d+)/gi");
});

it('SaleLinkifier href default canon (Inertia routes)', function () {
    $source = r3Read(R3_LINKIFY_PATH);
    expect($source)
        ->toContain("venda: (id) => `/sells/\${id}`")
        ->toContain("os: (id) => `/repair/\${id}`")
        ->toContain("cliente: (id) => `/contacts/customers?search=")
        ->toContain("orcamento: (id) => `/sells/quotations?search=");
});

it('SaleLinkifier suporta onPick callback custom (drawer-internal nav)', function () {
    $source = r3Read(R3_LINKIFY_PATH);
    expect($source)
        ->toContain('onPick?: (id: string, kind: LinkifyKind) => void')
        ->toContain('if (onPick) {')
        ->toContain('e.preventDefault()');
});

// ─── Integração no SaleSheet ─────────────────────────────────────────

it('SaleSheet importa os 3 componentes R3', function () {
    $source = r3Read(R3_SHEET_PATH);
    expect($source)
        ->toContain("import SaleItemComments, { useSaleItemComments } from './SaleItemComments'")
        ->toContain("import SaleAuditTrail, { type SaleAuditInput } from './SaleAuditTrail'")
        ->toContain("import SaleLinkifier from './SaleLinkifier'");
});

it('SaleSheet usa useSaleItemComments() hook compartilhado', function () {
    $source = r3Read(R3_SHEET_PATH);
    expect($source)
        ->toContain('const itemComments = useSaleItemComments()');
});

it('SaleSheet renderiza SaleItemComments por linha (idx como item_idx)', function () {
    $source = r3Read(R3_SHEET_PATH);
    expect($source)
        ->toContain('<SaleItemComments')
        ->toContain('venda_id={data.id}')
        ->toContain('item_idx={idx}')
        ->toContain('controller={itemComments}');
});

it('SaleSheet renderiza SaleLinkifier em additional_notes (cross-link)', function () {
    $source = r3Read(R3_SHEET_PATH);
    expect($source)
        ->toContain('<SaleLinkifier text={data.additional_notes} />');
});

it('SaleSheet renderiza SaleAuditTrail no final do body (após Histórico)', function () {
    $source = r3Read(R3_SHEET_PATH);
    expect($source)
        ->toContain('<SaleAuditTrail')
        ->toContain('satisfies SaleAuditInput');
});

// ─── CSS scoped + import ─────────────────────────────────────────────

it('CSS sells-cowork-curadoria.css scope .sells-cowork-curadoria', function () {
    $source = r3Read(R3_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-curadoria {')
        ->toContain('--vd-cur-blue:')
        ->toContain('--vd-cur-audit:');
});

it('CSS curadoria define classes principais (item-thread, audit, link pills)', function () {
    $source = r3Read(R3_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-curadoria .vd-item-thread')
        ->toContain('.sells-cowork-curadoria .vd-item-comment')
        ->toContain('.sells-cowork-curadoria .vd-audit')
        ->toContain('.sells-cowork-curadoria .vd-link');
});

it('CSS link pills cobre 4 variants (venda/os/cli/orc)', function () {
    $source = r3Read(R3_CSS_PATH);
    expect($source)
        ->toContain('.vd-link-venda')
        ->toContain('.vd-link-os')
        ->toContain('.vd-link-cli')
        ->toContain('.vd-link-orc');
});

it('inertia.css importa sells-cowork-curadoria.css', function () {
    $source = r3Read(R3_INERTIA_CSS_PATH);
    expect($source)
        ->toContain('@import "./sells-cowork-curadoria.css"');
});
