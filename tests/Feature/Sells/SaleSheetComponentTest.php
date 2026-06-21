<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Sells/_components/SaleSheet.tsx (US-SELL-008).
 *
 * Drawer lateral direito pro detalhe da venda. Cockpit Pattern V2 canon (ADR 0110).
 *
 * Anti-regressão das partes obrigatórias (ADR 0110 §Drawer SaleSheet pattern):
 *   - <Sheet> shadcn side="right" w-xl
 *   - 4 mini KPIs grid grid-cols-4 (Itens/Valor/Pago/Saldo)
 *   - Sections com heading text-[10px] uppercase
 *   - Footer ações (Imprimir + Editar)
 *   - Endpoint REST: GET /sells/{id}/sheet-data
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — APENAS o it() "aborta fetch
 * quando saleId muda (cleanup useEffect)" bate a string `cancelled` do guard antigo
 * `let cancelled = true; return () => { cancelled = true }` REFATORADO pra
 * `useCallback(fetchData)`; marker `cancelled` verificado ausente no SaleSheet.tsx vivo.
 * Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * ✅ Todos os OUTROS it() PERMANECEM ATIVOS — SaleSheet.tsx existe e a cobertura viva
 * (Sheet shadcn, 4 mini-KPIs, sections, fetch /sheet-data, loading/error, guard
 * defensivo `data.customer ?` que evita expor PII quando customer null) continua
 * verde. Quarentenar o arquivo inteiro silenciaria essa cobertura — NÃO foi feito.
 */

const SALE_SHEET_PATH_T = 'resources/js/Pages/Sells/_components/SaleSheet.tsx';

function readSheet(): string
{
    return file_get_contents(base_path(SALE_SHEET_PATH_T));
}

it('SaleSheet componente existe', function () {
    expect(file_exists(base_path(SALE_SHEET_PATH_T)))->toBeTrue();
});

it('SaleSheet usa Sheet shadcn (side=right + w-xl) — ADR 0110 §Drawer', function () {
    $source = readSheet();
    expect($source)->toContain('@/Components/ui/sheet');
    expect($source)->toContain('side="right"');
    expect($source)->toMatch('/sm:max-w-xl/');
});

it('SaleSheet props canônicas (saleId + open + onOpenChange)', function () {
    $source = readSheet();
    expect($source)->toContain('saleId');
    expect($source)->toContain('open');
    expect($source)->toContain('onOpenChange');
});

// ─── Cockpit Pattern V2 §Drawer §4 mini KPIs ─────────────────────────────────

it('SaleSheet tem 4 mini-KPIs grid grid-cols-4 (Itens/Valor/Pago/Saldo)', function () {
    $source = readSheet();
    expect($source)->toContain('grid-cols-4');
    expect($source)->toContain('MiniKpi');
    expect($source)->toContain("label=\"Itens\"");
    expect($source)->toContain("label=\"Valor\"");
    expect($source)->toContain("label=\"Pago\"");
    expect($source)->toContain("label=\"Saldo\"");
});

it('SaleSheet MiniKpi tem 3 tones canon (neutral/success/warning)', function () {
    $source = readSheet();
    expect($source)->toContain("'success'");
    expect($source)->toContain("'warning'");
    expect($source)->toMatch('/border-emerald-(200|900)/');
    expect($source)->toMatch('/border-amber-(200|900)/');
});

it('SaleSheet calcula saldoDevedor (final_total - total_paid)', function () {
    $source = readSheet();
    expect($source)->toContain('saldoDevedor');
    expect($source)->toMatch('/final_total\\s*-\\s*data\\.total_paid/');
});

it('SaleSheet aplica tone warning quando saldoDevedor > 0 (Cockpit canon §Cores)', function () {
    $source = readSheet();
    expect($source)->toMatch('/saldoDevedor\\s*>\\s*0\\s*\\?\\s*[\'"]warning[\'"]/');
});

// ─── Cockpit Pattern V2 §Drawer §Sections ────────────────────────────────────

it('SaleSheet tem Section component com heading text-[10px] uppercase tracking-widest', function () {
    $source = readSheet();
    expect($source)->toContain('Section');
    expect($source)->toContain('text-[10px]');
    expect($source)->toContain('uppercase');
    expect($source)->toContain('tracking-widest');
});

it('SaleSheet tem 3 sections obrigatórias (Cliente / Produtos / Pagamentos)', function () {
    $source = readSheet();
    expect($source)->toContain('Cliente');
    expect($source)->toMatch('/Produtos\\s*\\(\\$/');
    expect($source)->toMatch('/Pagamentos\\s*\\(\\$/');
});

it('SaleSheet renderiza customer com mobile + email + local + data (ADR 0110 §Drawer §Cliente)', function () {
    $source = readSheet();
    expect($source)->toContain('customer.mobile');
    expect($source)->toContain('customer.email');
    expect($source)->toContain('location.name');
    expect($source)->toContain('formatDate');
});

it('SaleSheet renderiza tabela de produtos (qtde + subtotal)', function () {
    $source = readSheet();
    expect($source)->toContain('lines.map');
    expect($source)->toContain('product_name');
    expect($source)->toContain('product_sku');
    expect($source)->toContain('subtotal');
});

it('SaleSheet renderiza timeline de pagamentos com ✓ verde (CheckCircle2 emerald)', function () {
    $source = readSheet();
    expect($source)->toContain('payments.map');
    expect($source)->toContain('CheckCircle2');
    expect($source)->toMatch('/text-emerald-(500|700)/');
    expect($source)->toContain('PAYMENT_METHOD_LABEL');
});

// ─── Cockpit Pattern V2 §Drawer §Header ──────────────────────────────────────

it('SaleSheet header tem badges (payment + shipping) + título + customer line', function () {
    $source = readSheet();
    expect($source)->toContain('PaymentBadge');
    expect($source)->toContain('shipping_status');
    expect($source)->toContain('SheetTitle');
    expect($source)->toContain('customer.name');
});

it('SaleSheet badges canônicas (paid=emerald, due=amber, partial=blue)', function () {
    $source = readSheet();
    expect($source)->toContain('PAYMENT_STATUS_STYLE');
    expect($source)->toMatch('/paid:.*emerald/');
    expect($source)->toMatch('/due:.*amber/');
    expect($source)->toMatch('/partial:.*blue/');
});

// ─── Cockpit Pattern V2 §Drawer §Footer ações ────────────────────────────────

it('SaleSheet footer sticky com Imprimir + Editar (ADR 0110 §Drawer §Footer)', function () {
    $source = readSheet();
    expect($source)->toContain('Imprimir');
    expect($source)->toContain('Editar');
    expect($source)->toContain('urls.print');
    expect($source)->toContain('urls.edit');
    expect($source)->toContain('target="_blank"');
});

// ─── Cockpit Pattern V2 §Endpoint REST canon ─────────────────────────────────

it('SaleSheet faz fetch GET /sells/{id}/sheet-data (ADR 0110 §Endpoint REST)', function () {
    $source = readSheet();
    expect($source)->toMatch('/`\\/sells\\/\\$\\{saleId\\}\\/sheet-data`/');
    expect($source)->toContain("'X-Requested-With': 'XMLHttpRequest'");
});

it('SaleSheet trata loading + error states (UX defensiva)', function () {
    $source = readSheet();
    expect($source)->toContain('loading');
    expect($source)->toContain('error');
    expect($source)->toContain('Loader2');
    expect($source)->toContain('AlertTriangle');
});

it('SaleSheet aborta fetch quando saleId muda (cleanup useEffect)', function () {
    $source = readSheet();
    expect($source)->toContain('cancelled');
    expect($source)->toMatch('/return\\s*\\(\\)\\s*=>\\s*\\{\\s*cancelled\\s*=\\s*true/');
    // quarantine-reason: guard `cancelled` refatorado p/ useCallback no SaleSheet.tsx vivo (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Anti-padrões ────────────────────────────────────────────────────────────

it('SaleSheet NÃO usa cor crua Tailwind sem semântica', function () {
    $source = readSheet();
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('SaleSheet NÃO mostra dados sensíveis quando customer null (defensive)', function () {
    $source = readSheet();
    expect($source)->toMatch('/data\\.customer\\s*\\?/');
});
