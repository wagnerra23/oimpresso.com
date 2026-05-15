<?php

declare(strict_types=1);

/**
 * Pest test estrutural — ProductQuickAddModal (US-SELL-013 / P0-2).
 *
 * Cobre P0-2 do RUNBOOK paridade Sells/Create (memory/requisitos/Sells/RUNBOOK-paridade-create.md §5):
 *   - Cadastro de produto inline no meio da venda (Lara Caçambas SKU on-the-fly)
 *   - Sem isso, +5min/produto novo aberto em nova aba → fail canary Martinho biz=164 19/maio
 *
 * Pattern: ESTRUTURAL (file_get_contents + regex) — canon Sells/Create suite
 * (ref: SellsCreatePageTest.php).
 *
 * Tier 0 anti-regressão:
 *   - business_id NÃO é mockado nem hardcoded em client — backend
 *     ProductController@saveQuickProduct lê de session()->get('user.business_id')
 *   - Modal não invoca endpoint /products/list-json com biz=1 fixo — endpoint
 *     /products/list já é multi-tenant scoped via global scope (ADR 0093)
 *   - Frontend testes confirmam estrutura; integração real fica pro smoke
 *     biz=1 vs biz=99 (cross-tenant) Wagner roda manual pré-canary
 *
 * Refs:
 *   - resources/js/Pages/Sells/_components/ProductQuickAddModal.tsx
 *   - resources/js/Pages/Sells/Create.tsx (integração)
 *   - app/Http/Controllers/ProductController.php@saveQuickProduct (endpoint)
 */

const MODAL_PATH = 'resources/js/Pages/Sells/_components/ProductQuickAddModal.tsx';
const CREATE_PATH_P0_2 = 'resources/js/Pages/Sells/Create.tsx';

function readQuickAddModal(): string
{
    return file_get_contents(base_path(MODAL_PATH));
}

function readSellsCreateForQuickAdd(): string
{
    return file_get_contents(base_path(CREATE_PATH_P0_2));
}

// ─── Caso 1: Modal renderiza com estrutura Inertia/React canônica ──────────────

it('ProductQuickAddModal arquivo existe em _components/', function () {
    expect(file_exists(base_path(MODAL_PATH)))->toBeTrue();
});

it('Modal usa Dialog primitive shadcn (Radix) — não modal jQuery legacy', function () {
    $src = readQuickAddModal();
    expect($src)->toContain('@/Components/ui/dialog');
    expect($src)->toContain('<Dialog');
    expect($src)->toContain('<DialogContent');
    expect($src)->toContain('<DialogHeader');
    expect($src)->toContain('<DialogFooter');
});

it('Modal exporta QuickAddedProduct type pra parent consumir', function () {
    $src = readQuickAddModal();
    expect($src)->toContain('export interface QuickAddedProduct');
    expect($src)->toContain('product_id');
    expect($src)->toContain('variation_id');
    expect($src)->toContain('selling_price');
});

it('Modal tem 7 campos paridade Blade quick_add_product (name, sku, unit, sell, purchase, category, type)', function () {
    $src = readQuickAddModal();
    expect($src)->toContain('id="quick_name"');
    expect($src)->toContain('id="quick_sku"');
    expect($src)->toContain('id="quick_unit"');
    expect($src)->toContain('id="quick_sell_price"');
    expect($src)->toContain('id="quick_purchase_price"');
    expect($src)->toContain('id="quick_category"');
    // Type 'single' é hardcoded default (Caçambas/ROTA LIVRE não usam variable/combo)
    expect($src)->toContain("fd.append('type', 'single')");
});

it('Modal usa Input/Label/Select shadcn (R-DS-001 reutilização)', function () {
    $src = readQuickAddModal();
    expect($src)->toContain('@/Components/ui/input');
    expect($src)->toContain('@/Components/ui/label');
    expect($src)->toContain('@/Components/ui/select');
    expect($src)->toContain('@/Components/ui/button');
});

// ─── Caso 2: Submit POSTa pro endpoint canônico /products/save_quick_product ───

it('Modal submete via fetch POST → /products/save_quick_product (endpoint legacy reusado, sem touch backend)', function () {
    $src = readQuickAddModal();
    expect($src)->toContain("'/products/save_quick_product'");
    expect($src)->toContain("method: 'POST'");
    expect($src)->toContain('FormData');
});

it('Modal envia CSRF token no header (X-CSRF-TOKEN) — Tier 0 evita 419', function () {
    $src = readQuickAddModal();
    expect($src)->toContain("'X-CSRF-TOKEN'");
    expect($src)->toContain('csrf-token');
});

it('Modal NÃO hardcoda business_id no payload — backend lê de session (Tier 0 ADR 0093)', function () {
    $src = readQuickAddModal();
    // Anti-regressão: client NUNCA deve mandar business_id pro endpoint —
    // saveQuickProduct ignora payload e lê de session('user.business_id').
    // Se algum dev colar business_id no FormData = vazamento cross-tenant.
    expect($src)->not->toMatch('/fd\\.append\\([\'"]business_id[\'"]/');
    expect($src)->not->toMatch('/business_id:\\s*\\d+/');
});

it('Modal gera SKU placeholder LEG-<timestamp> quando vazio (atalho Lara Caçambas)', function () {
    $src = readQuickAddModal();
    expect($src)->toContain('LEG-');
    expect($src)->toContain('Date.now()');
});

it('Modal não usa cor crua não-semântica (canon ADR 0110)', function () {
    $src = readQuickAddModal();
    expect($src)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

// ─── Caso 3: Integração em Sells/Create.tsx ───────────────────────────────────

it('Sells/Create importa ProductQuickAddModal + QuickAddedProduct', function () {
    $src = readSellsCreateForQuickAdd();
    expect($src)->toContain("from './_components/ProductQuickAddModal'");
    expect($src)->toContain('QuickAddedProduct');
});

it('Sells/Create tem state showProductQuickAdd + handler handleProductQuickAdded', function () {
    $src = readSellsCreateForQuickAdd();
    expect($src)->toContain('showProductQuickAdd');
    expect($src)->toContain('setShowProductQuickAdd');
    expect($src)->toContain('handleProductQuickAdded');
});

it('Sells/Create tem botão "Novo produto" próximo do ProductSearchAutocomplete', function () {
    $src = readSellsCreateForQuickAdd();
    expect($src)->toContain('Novo produto');
    expect($src)->toContain('setShowProductQuickAdd(true)');
});

it('Sells/Create renderiza ProductQuickAddModal e passa onProductCreated', function () {
    $src = readSellsCreateForQuickAdd();
    expect($src)->toContain('<ProductQuickAddModal');
    expect($src)->toContain('onProductCreated={handleProductQuickAdded}');
});

it('handleProductQuickAdded adiciona produto à lista data.products (qty=1, unit_price=selling_price)', function () {
    $src = readSellsCreateForQuickAdd();
    // Confere shape do produto adicionado — bate com handleAddProduct convencional.
    expect($src)->toMatch('/handleProductQuickAdded[\\s\\S]*?setData\\([\'"]products[\'"][\\s\\S]*?quantity:\\s*1/');
    expect($src)->toMatch('/handleProductQuickAdded[\\s\\S]*?unit_price:\\s*Number\\(p\\.selling_price/');
});

// ─── Endpoint backend está vivo (controller existe) ───────────────────────────

it('Endpoint ProductController@saveQuickProduct existe no backend (reusado, não criado)', function () {
    $src = file_get_contents(base_path('app/Http/Controllers/ProductController.php'));
    expect($src)->toContain('public function saveQuickProduct');
    // Confirma permission check Tier 0
    expect($src)->toContain("can('product.create')");
    // Confirma business_id pelo session (anti-cross-tenant)
    expect($src)->toContain("session()->get('user.business_id')");
});

it('Rota POST /products/save_quick_product registrada em routes/web.php', function () {
    $src = file_get_contents(base_path('routes/web.php'));
    expect($src)->toContain('/products/save_quick_product');
    expect($src)->toContain("'saveQuickProduct'");
});
