<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Purchase/Show.tsx + PurchaseController@show dual path.
 *
 * Piloto MWART migracao-blade-react v0.1.0 — PR2 (show DETAIL).
 * Mata bug 500 em prod (show_details.blade.php:430 DNS1D::getBarcodePNG).
 *
 * Snapshot: memory/mwart-inventory/purchase/show.snapshot.md
 * Visual:   memory/requisitos/Purchase/show-visual-comparison.md
 */

const PURCHASE_SHOW_PATH = 'resources/js/Pages/Purchase/Show.tsx';
const PURCHASE_SHOW_SNAPSHOT = 'memory/mwart-inventory/purchase/show.snapshot.md';
const PURCHASE_SHOW_VISUAL = 'memory/requisitos/Purchase/show-visual-comparison.md';

function readPurchaseShow(): string
{
    return file_get_contents(base_path(PURCHASE_SHOW_PATH));
}

function readPurchaseControllerShow(): string
{
    return file_get_contents(base_path('app/Http/Controllers/PurchaseController.php'));
}

// ─── STEP 1: SNAPSHOT existe ────────────────────────────────────────────────

it('snapshot show existe', function () {
    expect(file_exists(base_path(PURCHASE_SHOW_SNAPSHOT)))->toBeTrue();
    $content = file_get_contents(base_path(PURCHASE_SHOW_SNAPSHOT));
    expect($content)->toContain('tipo: DETAIL');
    expect($content)->toContain('bug_critico');
});

// ─── STEP 2: Page Inertia ───────────────────────────────────────────────────

it('Page Show.tsx existe', function () {
    expect(file_exists(base_path(PURCHASE_SHOW_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 (Persistent Layout)', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toMatch('/PurchaseShow\\.layout\\s*=\\s*\\(page/');
});

it('Page importa PageHeader shared (Cockpit V2 canon)', function () {
    expect(readPurchaseShow())->toContain('@/Components/shared/PageHeader');
});

it('Page declara interfaces TS canônicas (PurchaseDetail + PurchaseLine + PaymentLine + Permissions)', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('interface PurchaseDetail');
    expect($source)->toContain('interface PurchaseLine');
    expect($source)->toContain('interface PaymentLine');
    expect($source)->toContain('interface Permissions');
});

it('Page tem StatusPill 3 estados (received/pending/ordered) — paridade Blade', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('received:');
    expect($source)->toContain('pending:');
    expect($source)->toContain('ordered:');
});

it('Page tem PaymentPill 4 estados (paid/due/partial/overdue) — paridade Blade', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('paid:');
    expect($source)->toContain('due:');
    expect($source)->toContain('partial:');
    expect($source)->toContain('overdue:');
});

it('Page respeita permissions (update/delete renderizam condicionalmente)', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('permissions.update');
    expect($source)->toContain('permissions.delete');
});

it('Page NÃO renderiza barcode (bug-fix por omissão — linha 430 Blade DNS1D)', function () {
    $source = readPurchaseShow();
    // Confirma que NÃO importou nem chamou nada relacionado a barcode
    expect($source)->not->toContain('Barcode');
    expect($source)->not->toContain('DNS1D');
});

it('Page tem 3 Cards top (Fornecedor/Empresa/Resumo) e 2 Cards mid (Pagamentos/Totais)', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('Fornecedor');
    expect($source)->toContain('Empresa');
    expect($source)->toContain('Resumo');
    expect($source)->toContain('Pagamentos');
    expect($source)->toContain('Totais');
});

it('Page tem Total geral (PT-BR — não "Totalmente grande" do Blade)', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('Total geral');
    // Anti-regressão do bug i18n no Blade
    expect($source)->not->toContain('Totalmente grande');
});

it('Page formata BRL (Intl.NumberFormat pt-BR)', function () {
    $source = readPurchaseShow();
    expect($source)->toContain("'pt-BR'");
    expect($source)->toContain("'BRL'");
});

it('Page tem botões ação Voltar/Imprimir/Editar/Excluir', function () {
    $source = readPurchaseShow();
    expect($source)->toContain('Voltar');
    expect($source)->toContain('Imprimir');
    expect($source)->toContain('Editar');
    expect($source)->toContain('Excluir');
});

// ─── STEP 3: Controller dual path ───────────────────────────────────────────

it('Controller@show tem permission re-adicionada (não comentada)', function () {
    $source = readPurchaseControllerShow();
    // Antes: comentada nas linhas 557-559
    expect($source)->toMatch("/if\\s*\\(!?\\s*auth\\(\\)->user\\(\\)->can\\('purchase\\.view'\\)/");
    expect($source)->toContain('view_own_purchase');
});

it('Controller@show tem dual path (Blade legacy se AJAX puro + Inertia default)', function () {
    $source = readPurchaseControllerShow();
    expect($source)->toMatch("/request\\(\\)->ajax\\(\\)\\s*&&\\s*!?\\s*request\\(\\)->header\\('X-Inertia'\\)/");
    expect($source)->toContain('showInertia');
    expect($source)->toContain("Inertia::render('Purchase/Show'");
});

it('Controller@showInertia PRESERVA Tier 0 (recebe $purchase já scopado por business_id)', function () {
    $source = readPurchaseControllerShow();
    // showInertia recebe $purchase como parâmetro (não faz query nova sem scope)
    expect($source)->toMatch('/private function showInertia\\(\\$purchase/');
    // Query original @show já faz scope (Transaction::where('business_id'...))
    expect($source)->toMatch("/Transaction::where\\('business_id', \\\$business_id\\)/");
});

it('Controller@showInertia retorna permissions completas', function () {
    $source = readPurchaseControllerShow();
    expect($source)->toContain("\$user->can('purchase.update')");
    expect($source)->toContain("\$user->can('purchase.delete')");
});

it('Controller@show PRESERVA Blade legacy path (compat retro AJAX modal)', function () {
    $source = readPurchaseControllerShow();
    expect($source)->toContain("return view('purchase.show')");
});

// ─── STEP 4: visual-comparison existe ───────────────────────────────────────

it('visual-comparison.md existe', function () {
    expect(file_exists(base_path(PURCHASE_SHOW_VISUAL)))->toBeTrue();
    $content = file_get_contents(base_path(PURCHASE_SHOW_VISUAL));
    expect($content)->toContain('aguardando-screenshot-wagner');
});

// ─── Anti-padrões (ADR 0093 + ADR 0094) ─────────────────────────────────────

it('Page NÃO tem business_id hardcoded (vem das props via Controller)', function () {
    expect(readPurchaseShow())->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});

it('Controller@showInertia NÃO usa withoutGlobalScopes sem comentário SUPERADMIN', function () {
    $source = readPurchaseControllerShow();
    if (str_contains($source, 'withoutGlobalScopes')) {
        expect($source)->toMatch('/SUPERADMIN/');
    } else {
        expect(true)->toBeTrue();
    }
});
