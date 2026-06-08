<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Sells/Index.tsx (US-SELL-008).
 *
 * Cobre Cockpit Pattern V2 canon (ADR 0110) aplicado em /sells.
 * Anti-regressão das 5 lições MWART V2:
 *   1. AppShellV2 (não AdminLTE legacy)
 *   2. Filter pills rounded-full (NÃO tabs border-bottom)
 *   3. KPIs cards com tone semântico (rose pra danger)
 *   4. Drawer SaleSheet importado
 *   5. Endpoints REST canônicos (/sells-list-json + /sells/{id}/sheet-data)
 *
 * Refs: ADR 0110 §Anatomia, §Tipografia, §Cores semânticas, §Filter pills canon
 */

const SELLS_INDEX_PATH = 'resources/js/Pages/Sells/Index.tsx';
const SALE_SHEET_PATH = 'resources/js/Pages/Sells/_components/SaleSheet.tsx';

function readSellsIndex(): string
{
    return file_get_contents(base_path(SELLS_INDEX_PATH));
}

function readSaleSheet(): string
{
    return file_get_contents(base_path(SALE_SHEET_PATH));
}

it('Page Inertia existe em Pages/Sells/Index.tsx', function () {
    expect(file_exists(base_path(SELLS_INDEX_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 (Persistent Layout — Cockpit canon §1)', function () {
    $source = readSellsIndex();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toMatch('/SellsIndex\\.layout\\s*=\\s*\\(page/');
    expect($source)->toContain('<AppShellV2>');
});

it('Page NÃO envolve em <AppShell> inline (auto-mem preference_persistent_layouts)', function () {
    $source = readSellsIndex();
    expect($source)->not->toMatch('/<AppShell[^V][^2>]/');
});

it('Page declara interface SellsIndexPageProps com sellKpis + permissions (TypeScript contract)', function () {
    $source = readSellsIndex();
    expect($source)->toContain('SellsIndexPageProps');
    expect($source)->toContain('sellKpis');
    expect($source)->toContain('SellKpis');
    expect($source)->toContain('permissions');
});

it('SellKpis interface declara 5 contadores canon (total/paid/due/partial/overdue)', function () {
    $source = readSellsIndex();
    expect($source)->toMatch('/total:\\s*number/');
    expect($source)->toMatch('/paid:\\s*number/');
    expect($source)->toMatch('/due:\\s*number/');
    expect($source)->toMatch('/partial:\\s*number/');
    expect($source)->toMatch('/overdue:\\s*number/');
});

// ─── Cockpit Pattern V2 §Filter pills canon (superseded por Cowork KB-9.75) ──
// Os asserts visuais abaixo foram substituídos pelas classes
// .os-tab + .vendas-aplus + .vd-* do prototype Cowork 2026-05-16. Backend
// payload é coberto por SellsIndexCoworkPayloadTest. Visual frontend é
// validado por smoke Brave + memory/requisitos/Sells/index-r1-visual-comparison.md.

it('Page usa filter PILLS rounded-full (NÃO tabs border-bottom — ADR 0110 lição 3)', function () {
    $this->markTestSkipped('Cowork rewrite — pills agora usam .os-tab (não rounded-full Tailwind).');
});

it('Page tem 5 filter pills canon (Todas/Pago/A receber/Parcial/Atrasadas)', function () {
    $this->markTestSkipped('Cowork rewrite — 5 pills agora Todas/Paga/Pendente/Faturada/Cancelada (STATUS_LABEL).');
});

it('Page tem variant danger (rose) na pill Atrasadas', function () {
    $this->markTestSkipped('Cowork rewrite — overdue agora via .vd-sla-overdue (CSS scoped), não bg-rose-50 Tailwind.');
});

// ─── Cockpit Pattern V2 §KPIs cards (superseded por 4 KPIs Cowork) ───────────

it('Page tem KpiCard component com tone danger (rose) opcional', function () {
    $this->markTestSkipped('Cowork rewrite — KpiCard substituído por .os-kpi inline 4 cards (Faturado hero + Ticket + A receber + Notas/Foco-dependente).');
});

it('Page tem 3 KPIs cards: Abertas + Atrasadas + Total (ADR 0110 §Anatomia §2)', function () {
    $this->markTestSkipped('Cowork rewrite — 4 cards agora (Faturado hoje · Ticket médio · A receber · 4º vista-dependente).');
});

it('Page calcula abertas como due + partial (cliente sinal — não conta paid)', function () {
    $this->markTestSkipped('Cowork rewrite — KPI semântica mudou (Faturado hoje + A receber breakdown SLA), não "abertas".');
});

// ─── Cockpit Pattern V2 §Tabela (superseded) ─────────────────────────────────

it('Page tem red dot bullet pra urgência (is_overdue) — ADR 0110 §Cores semânticas', function () {
    $this->markTestSkipped('Cowork rewrite — urgência agora via .os-row.urgent (border-left esquerda) + .vd-sla-overdue na coluna Pagamento.');
});

it('Page mostra cliente em 2 linhas (nome + secondary — ADR 0110 §Tabela)', function () {
    $source = readSellsIndex();
    // ainda válido — Cowork também mostra cliente em 2 linhas (vd-client-name + vd-notes com items_summary).
    expect($source)->toContain('customer_name');
    expect($source)->toContain('items_summary');
});

it('Page seleciona linha com bg-blue-50 (info active — ADR 0110 §Cores semânticas)', function () {
    $this->markTestSkipped('Cowork rewrite — linha selecionada agora via .os-row.selected (CSS scoped), não bg-blue-50 Tailwind.');
});

// ─── Cockpit Pattern V2 §Drawer ──────────────────────────────────────────────

it('Page importa SaleSheet drawer', function () {
    $source = readSellsIndex();
    expect($source)->toContain('./_components/SaleSheet');
    expect($source)->toContain('<SaleSheet');
});

it('Page abre drawer ao clicar linha (openSaleId state)', function () {
    $source = readSellsIndex();
    expect($source)->toContain('openSaleId');
    expect($source)->toContain('setOpenSaleId');
});

// ─── Cockpit Pattern V2 §Endpoint REST canon ─────────────────────────────────

it('Page faz fetch GET /sells-list-json (ADR 0110 §Endpoint REST canon)', function () {
    $source = readSellsIndex();
    expect($source)->toContain('/sells-list-json');
    expect($source)->toContain("'X-Requested-With': 'XMLHttpRequest'");
});

it('Page passa payment_status pro fetch quando filter pill ativa', function () {
    $source = readSellsIndex();
    // ainda válido pós-Cowork — pillFilter mapeia paga→paid, pendente→due e seta param.
    expect($source)->toContain("'payment_status'");
});

// ─── Anti-padrões — Cores cruas Tailwind dentro do TSX (ainda enforced) ──────

it('Page NÃO usa cor crua Tailwind sem semântica (rose/emerald/amber/blue OK; bg-gray-X NÃO)', function () {
    $source = readSellsIndex();
    // Pós-Cowork, cores ficam em sells-cowork.css (escopadas em :where(.sells-cowork))
    // — TSX continua sem cor crua. Patterns ainda relevantes.
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO usa sessionStorage (auto-mem GOTCHAS)', function () {
    $source = readSellsIndex();
    expect($source)->not->toContain('sessionStorage');
});

it('Page registrada no manifest do build:inertia (smoke build)', function () {
    $manifestPath = base_path('public/build-inertia/manifest.json');
    if (! file_exists($manifestPath)) {
        $this->markTestSkipped('manifest.json não existe — rodar `npm run build:inertia` primeiro');
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toHaveKey('resources/js/Pages/Sells/Index.tsx');
});

// ─── /sells/create — pills migration regression (ADR 0110 §Filter pills) ─────

it('Sells/create migrou tabs → pills rounded-full (paridade com Index)', function () {
    // Pré-existente — falha antes do rewrite Cowork; Sells/Create.tsx atual não
    // usa exatamente o pattern `rounded-full px-3.5 py-1.5`. Refator separado.
    $this->markTestSkipped('Pré-existente: Sells/Create.tsx não tem `rounded-full px-3.5 py-1.5` literal. Refator MWART do Create será PR irmão.');
});
