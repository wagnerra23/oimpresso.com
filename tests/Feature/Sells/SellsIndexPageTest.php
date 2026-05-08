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

// ─── Cockpit Pattern V2 §Filter pills canon ──────────────────────────────────

it('Page usa filter PILLS rounded-full (NÃO tabs border-bottom — ADR 0110 lição 3)', function () {
    $source = readSellsIndex();
    // Pills devem ser rounded-full
    expect($source)->toContain('rounded-full');
    // Pattern canon das pills deve estar presente
    expect($source)->toContain('text-xs font-medium');
});

it('Page tem 5 filter pills canon (Todas/Pago/A receber/Parcial/Atrasadas)', function () {
    $source = readSellsIndex();
    expect($source)->toContain("'Todas'");
    expect($source)->toContain("'Pago'");
    expect($source)->toContain("'A receber'");
    expect($source)->toContain("'Parcial'");
    expect($source)->toContain("'Atrasadas'");
});

it('Page tem variant danger (rose) na pill Atrasadas', function () {
    $source = readSellsIndex();
    // ADR 0110 §Cores semânticas: rose = danger
    expect($source)->toContain('danger');
    expect($source)->toMatch('/bg-rose-(50|100)/');
});

// ─── Cockpit Pattern V2 §KPIs cards ──────────────────────────────────────────

it('Page tem KpiCard component com tone danger (rose) opcional', function () {
    $source = readSellsIndex();
    expect($source)->toContain('KpiCard');
    expect($source)->toContain('danger');
    expect($source)->toMatch('/border-rose-(200|900)/');
});

it('Page tem 3 KPIs cards: Abertas + Atrasadas + Total (ADR 0110 §Anatomia §2)', function () {
    $source = readSellsIndex();
    expect($source)->toContain('Abertas');
    expect($source)->toContain('Atrasadas');
    expect($source)->toContain('Total');
});

it('Page calcula abertas como due + partial (cliente sinal — não conta paid)', function () {
    $source = readSellsIndex();
    expect($source)->toMatch('/abertas\\s*=\\s*kpis\\.due\\s*\\+\\s*kpis\\.partial/');
});

// ─── Cockpit Pattern V2 §Tabela ──────────────────────────────────────────────

it('Page tem red dot bullet pra urgência (is_overdue) — ADR 0110 §Cores semânticas', function () {
    $source = readSellsIndex();
    // Pattern canon: <span className="h-2 w-2 rounded-full bg-rose-500" />
    expect($source)->toMatch('/h-2\\s+w-2\\s+rounded-full\\s+bg-rose-500/');
});

it('Page mostra cliente em 2 linhas (nome + secondary — ADR 0110 §Tabela)', function () {
    $source = readSellsIndex();
    expect($source)->toContain('customer_name');
    expect($source)->toContain('customer_secondary');
});

it('Page seleciona linha com bg-blue-50 (info active — ADR 0110 §Cores semânticas)', function () {
    $source = readSellsIndex();
    expect($source)->toMatch('/bg-blue-50(\\/[0-9]+)?/');
    expect($source)->toContain('isOpen');
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
    expect($source)->toContain("'payment_status'");
});

// ─── Anti-padrões (ADR 0110 §Cores) ──────────────────────────────────────────

it('Page NÃO usa cor crua Tailwind sem semântica (rose/emerald/amber/blue OK; bg-gray-X NÃO)', function () {
    $source = readSellsIndex();
    // Cores canon V2: rose/emerald/amber/blue/muted/foreground são OK
    // Cores cruas como bg-gray-500, text-yellow-700 são proibidas
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
    $source = file_get_contents(base_path('resources/js/Pages/Sells/Create.tsx'));
    // Pills canon presentes
    expect($source)->toContain('rounded-full px-3.5 py-1.5');
    // Estado ativo pill: NÃO usa border-b-2 do tabs como classe ativa.
    // Olha só dentro do JSX (após className=, evita falso positivo de comentário).
    expect($source)->not->toMatch("/className=\\{?\\s*['\"][^'\"]*border-b-2 border-primary -mb-px/");
});
