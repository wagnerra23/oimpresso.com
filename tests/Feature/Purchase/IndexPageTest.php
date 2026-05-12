<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Purchase/Index.tsx + PurchaseController dual path.
 *
 * Piloto da skill `migracao-blade-react` v0.1.0 (ADR 0141).
 * Cobertura:
 *   - Page Inertia existe e segue Cockpit Pattern V2 canon (ADR 0110)
 *   - Controller dual path (Blade legacy + Inertia atrás de flag)
 *   - Multi-tenant Tier 0 preservado (ADR 0093 IRREVOGÁVEL)
 *   - Snapshot paridade + visual-comparison existem (STEP 1 + STEP 4 da skill)
 *
 * Snapshot: memory/mwart-inventory/purchase/index.snapshot.md
 * Visual:   memory/requisitos/Purchase/index-visual-comparison.md
 */

const PURCHASE_INDEX_PATH = 'resources/js/Pages/Purchase/Index.tsx';
const PURCHASE_CONTROLLER_PATH = 'app/Http/Controllers/PurchaseController.php';
const PURCHASE_SNAPSHOT_PATH = 'memory/mwart-inventory/purchase/index.snapshot.md';
const PURCHASE_VISUAL_COMP_PATH = 'memory/requisitos/Purchase/index-visual-comparison.md';

function readPurchaseIndex(): string
{
    return file_get_contents(base_path(PURCHASE_INDEX_PATH));
}

function readPurchaseController(): string
{
    return file_get_contents(base_path(PURCHASE_CONTROLLER_PATH));
}

// ─── STEP 1: SNAPSHOT PARIDADE existe ────────────────────────────────────────

it('snapshot paridade existe em memory/mwart-inventory/purchase/index.snapshot.md', function () {
    expect(file_exists(base_path(PURCHASE_SNAPSHOT_PATH)))->toBeTrue();
    $content = file_get_contents(base_path(PURCHASE_SNAPSHOT_PATH));
    expect($content)->toContain('tipo: LIST');
    expect($content)->toContain('Multi-tenant scope');
    expect($content)->toContain('Tier 0');
});

// ─── STEP 2: TRADUÇÃO VISUAL — Page Inertia ──────────────────────────────────

it('Page Inertia existe em Pages/Purchase/Index.tsx', function () {
    expect(file_exists(base_path(PURCHASE_INDEX_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 (Persistent Layout — ADR 0094)', function () {
    $source = readPurchaseIndex();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toMatch('/PurchaseIndex\\.layout\\s*=\\s*\\(page/');
    expect($source)->toContain('<AppShellV2');
});

it('Page importa PageHeader shared (canon V2 — ADR 0110)', function () {
    $source = readPurchaseIndex();
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toContain('<PageHeader');
});

it('Page declara interfaces TS canônicas (PurchaseRow + Filters + Permissions)', function () {
    $source = readPurchaseIndex();
    expect($source)->toContain('interface PurchaseRow');
    expect($source)->toContain('interface Filters');
    expect($source)->toContain('interface Permissions');
    expect($source)->toContain('interface Props');
});

it('Page tem 6 filtros canônicos (location, supplier, status, payment_status, date range)', function () {
    $source = readPurchaseIndex();
    expect($source)->toContain('location_id');
    expect($source)->toContain('supplier_id');
    expect($source)->toContain('payment_status');
    expect($source)->toContain('start_date');
    expect($source)->toContain('end_date');
});

it('Page tem PaymentPill com 4 estados (paid/due/partial/overdue) — paridade Blade', function () {
    $source = readPurchaseIndex();
    expect($source)->toContain("paid:");
    expect($source)->toContain("due:");
    expect($source)->toContain("partial:");
    expect($source)->toContain("overdue:");
});

it('Page tem StatusPill com 3 estados (received/pending/ordered) — paridade Blade', function () {
    $source = readPurchaseIndex();
    expect($source)->toContain("received:");
    expect($source)->toContain("pending:");
    expect($source)->toContain("ordered:");
});

it('Page respeita permissions (view/create/update/delete renderizam condicionalmente)', function () {
    $source = readPurchaseIndex();
    expect($source)->toContain('permissions.view');
    expect($source)->toContain('permissions.create');
    expect($source)->toContain('permissions.update');
    expect($source)->toContain('permissions.delete');
});

it('Page tem empty state contextual (paridade UX — runbook-LIST)', function () {
    $source = readPurchaseIndex();
    expect($source)->toMatch('/Ainda n.{1,3}o h.{1,3} compras|Nenhuma compra/');
    expect($source)->toContain('Registrar primeira compra');
});

// ─── STEP 3: ADAPTAÇÃO CONTROLLER — dual path ────────────────────────────────

it('Controller importa Inertia\\Inertia (STEP 3)', function () {
    $source = readPurchaseController();
    expect($source)->toMatch('/use Inertia\\\\Inertia;/');
});

it('Controller index() tem dual path (Blade fallback + Inertia atrás de flag)', function () {
    $source = readPurchaseController();
    // Branch detection: header X-Inertia OU query v=2
    expect($source)->toMatch("/header\\('X-Inertia'\\)\\s*\\|\\|\\s*request\\(\\)->query\\('v'\\)\\s*===\\s*'2'/");
    // Método helper privado
    expect($source)->toContain('private function indexInertia');
    // Inertia::render
    expect($source)->toContain("Inertia::render('Purchase/Index'");
});

it('Controller indexInertia PRESERVA business_id scope (Tier 0 IRREVOGÁVEL — ADR 0093)', function () {
    $source = readPurchaseController();
    // Path Inertia recebe business_id como parâmetro
    expect($source)->toMatch('/indexInertia\\(\\$business_id/');
    // Usa getListPurchases($business_id)
    expect($source)->toContain('getListPurchases($business_id)');
});

it('Controller indexInertia PRESERVA permitted_locations filter', function () {
    $source = readPurchaseController();
    expect($source)->toContain("permitted_locations()");
    expect($source)->toContain("whereIn('transactions.location_id', \$permitted_locations)");
});

it('Controller indexInertia PRESERVA filtros condicionais (status, supplier, payment, date)', function () {
    $source = readPurchaseController();
    expect($source)->toContain("request()->location_id");
    expect($source)->toContain("request()->supplier_id");
    expect($source)->toContain("request()->status");
    expect($source)->toContain("request()->input('payment_status')");
    expect($source)->toContain("request()->start_date");
});

it('Controller indexInertia PRESERVA ownership filter (view_own_purchase)', function () {
    $source = readPurchaseController();
    expect($source)->toContain("view_own_purchase");
    expect($source)->toContain("transactions.created_by");
});

it('Controller indexInertia retorna permissions completas pro Page', function () {
    $source = readPurchaseController();
    expect($source)->toContain("\$user->can('purchase.view')");
    expect($source)->toContain("\$user->can('purchase.create')");
    expect($source)->toContain("\$user->can('purchase.update')");
    expect($source)->toContain("\$user->can('purchase.delete')");
});

// ─── STEP 3 CHECK: Blade legacy NÃO foi removida ─────────────────────────────

it('Controller PRESERVA path Blade legacy (não substituiu — dual safe)', function () {
    $source = readPurchaseController();
    // Path original return view ainda presente
    expect($source)->toContain("return view('purchase.index')");
    expect($source)->toContain("compact('business_locations', 'suppliers', 'orderStatuses')");
});

it('Controller PRESERVA path AJAX DataTables legacy (Yajra)', function () {
    $source = readPurchaseController();
    // AJAX path intacto
    expect($source)->toContain("request()->ajax()");
    expect($source)->toContain("Datatables::of(\$purchases)");
});

// ─── STEP 4: GATE VISUAL — comparison existe ─────────────────────────────────

it('visual-comparison.md existe em memory/requisitos/Purchase/', function () {
    expect(file_exists(base_path(PURCHASE_VISUAL_COMP_PATH)))->toBeTrue();
    $content = file_get_contents(base_path(PURCHASE_VISUAL_COMP_PATH));
    expect($content)->toContain('15 dimensões comparativas');
    expect($content)->toContain('aguardando-screenshot-wagner');
});

// ─── Anti-padrões (ADR 0093 + ADR 0094) ──────────────────────────────────────

it('Page NÃO tem business_id hardcoded (deve vir das props via Controller — Tier 0)', function () {
    $source = readPurchaseIndex();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});

it('Controller indexInertia NÃO usa withoutGlobalScopes sem comentário SUPERADMIN', function () {
    $source = readPurchaseController();
    // Se tiver withoutGlobalScopes, deve ter "SUPERADMIN" próximo (ADR 0093 proibição)
    if (str_contains($source, 'withoutGlobalScopes')) {
        expect($source)->toMatch('/SUPERADMIN.*withoutGlobalScopes|withoutGlobalScopes.*SUPERADMIN/s');
    } else {
        expect(true)->toBeTrue(); // sem withoutGlobalScopes = OK
    }
});
