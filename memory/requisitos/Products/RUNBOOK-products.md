---
slug: products-runbook
title: "Products — Runbook da tela Produtos (migração MWART)"
type: runbook
module: Products
status: active
date: 2026-05-14
authors: [W+C]
---

# RUNBOOK — Produtos (`/products`, `/products/create`, `/products/{id}`, `/products/{id}/edit`, `/products/{id}/stock-history`)

> **Tipo:** runbook de migração Blade → Inertia/React (MWART) — Fase F1 PLAN ADR 0104.
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md), [ADR 0141](../../decisions/0141-skill-migracao-blade-react.md)
> **Estado origem:** Blade legacy `view('product.index|create|edit|show|stock_history')` em [ProductController](../../../app/Http/Controllers/ProductController.php) — DataTables AJAX + 10 views Blade (`index/create/edit/show/stock_history/stock_history_details/add-selling-prices/bulk-edit/view-modal/view-product-group-prices`) + 6 partials.
> **Estado alvo:** `Pages/Products/{Index,Create,Edit,Show,StockHistory}.tsx` (Inertia v3 + React 19 + shadcn-style + AppShellV2 + Cockpit Pattern V2 ADR 0110).
> **Persona alvo canary:** **Lara (filha do Martinho Caçambas)** — responsável estoque biz=164 LIVE. Monitor 1280px, ~1.838 products importados, não-técnica. Análogo a Larissa ROTA LIVRE (biz=4) — vestuário. Pain-point #1 reunião: "velocidade pra encontrar peça e cadastrar produto novo" — `/products` é onde Lara passa o dia.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota `/products` renderiza Page React quando X-Inertia | DevTools mostra `data-page` com `"component":"Products/Index"` |
| Bundle Inertia builda | `npm run build:inertia && grep "Pages/Products" public/build-inertia/manifest.json` |
| AppShellV2 envolvendo | DOM tem `<div class="app-shell-v2">` ao redor da Page |
| Multi-tenant Tier 0 scopado | Login biz=164 só vê seus products (Pest cross-tenant biz=1 vs biz=99 OBRIGATÓRIO — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) |
| Permissão `product.view`/`product.create`/`product.update`/`product.delete` respeitada | Login sem permissão → 403 + fallback Blade preservado |
| Busca instant (debounce 300ms) responde | digitar "caçamba" mostra resultados em < 500ms p95 |
| Stock total agregado correto | soma `vld.qty_available` por variation/location bate com legacy |
| Velocidade abrir cadastro: < 1s | clique "Novo produto" → Create.tsx renderizado em < 1s p95 |
| Cabe em monitor 1280px | sem scroll horizontal (Lara monitora resolução baixa) |
| Cutover gradual | Blade legacy preservado como fallback se header NÃO traz `X-Inertia` |

## 1. Objetivo

Migrar lista de produtos e CRUD de Blade legacy AdminLTE roxo (DataTables + jQuery + Select2) pra Inertia React Cockpit V2. **Pain-point #1** identificado reunião Martinho Caçambas LTDA (biz=164): velocidade pra encontrar peça + cadastrar produto novo. Tela atual: 10 views Blade + DataTables + 23 custom_fields visíveis — lenta, denso visualmente, sem busca instant, scroll vertical 4 telas no create. Estado-da-arte 2026 (Linear/Notion/Stripe/Shopify Admin): denso mas legível, atalhos teclado, busca instant, drawer detail sem reload, form 3 sections collapsible.

Resolve:
- **Velocidade busca** (DataTables full-reload → fetch JSON 25 linhas em <300ms p95)
- **Cadastro relâmpago** (form atual = scroll vertical 4 telas; novo = 6 campos obrigatórios + restante colapsável)
- **Consistência visual** (mesmo padrão Cockpit V2 que `/sells`, `/contacts`, `/financeiro/boletos`)
- **Auditoria estoque legível** (stock_history hoje = tabela densa Blade; novo = timeline cronológica com filtros)

## 2. Pré-condições

- [x] Permissões `product.view|product.create|product.update|product.delete|product.opening_stock` (Spatie) — já implementadas no ProductController existente
- [x] Modelo `App\Product` já tem multi-tenant via `where('products.business_id', $business_id)` em todas queries — PRESERVAR
- [x] Skill `multi-tenant-patterns` Tier A — `business_id` obrigatório
- [x] Skill `mwart-quality` Tier A — 9 pré-flight checks
- [x] Skill `migracao-blade-react` Tier B — orquestra 6-step pipeline ([ADR 0141](../../decisions/0141-skill-migracao-blade-react.md))
- [x] Pest baseline `tests/Feature/Products/ProductsInertiaTest.php` com cross-tenant (biz=1 vs biz=99) — F2 BACKEND BASELINE obrigatório ANTES de mexer no `index()` controller
- [x] Feature flag opt-in via dual-mode no controller: `Inertia::render` quando `X-Inertia` header, `view()` legacy caso contrário (cutover gradual sem big bang)

## 3. Passo-a-passo (F1→F5 ADR 0104)

### F1 — PLAN (este RUNBOOK) ✅

- [x] Snapshot paridade: index, create, edit, show, stock-history migram; bulk-edit + add-selling-prices + view-modal = NÃO no MVP (Blade preservado)
- [x] Charters por tela: `Index.charter.md` + `Create.charter.md` (mínimo)
- [x] SPEC append (se existir SPEC.md), ou epic US-PROD-* no MCP

### F2 — BACKEND BASELINE

Dual-mode pattern (PRESERVAR Blade legacy + adicionar Inertia branch):

```php
// app/Http/Controllers/ProductController.php — index()
use Inertia\Inertia;

public function index()
{
    if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) {
        abort(403, 'Unauthorized action.');
    }
    $business_id = request()->session()->get('user.business_id');

    // DataTables AJAX legacy preservado (call by JS legacy)
    if (request()->ajax() && ! request()->header('X-Inertia')) {
        // ... query original DataTables retorna ...
    }

    // US-PROD-001 — Inertia branch (Products/Index). Cockpit V2 (ADR 0110).
    if (request()->header('X-Inertia')) {
        $base = Product::where('business_id', $business_id)
            ->where('type', '!=', 'modifier');

        $kpis = [
            'total'    => (clone $base)->count(),
            'with_stock' => (clone $base)->where('enable_stock', 1)->count(),
            'inactive'   => (clone $base)->where('is_inactive', 1)->count(),
            'in_alert'   => Product::where('business_id', $business_id)
                ->where('type', '!=', 'modifier')
                ->where('enable_stock', 1)
                ->whereNotNull('alert_quantity')
                // Total stock < alert_quantity — agregado SUM(vld.qty_available)
                ->whereRaw('(SELECT COALESCE(SUM(vld.qty_available),0) FROM variation_location_details vld JOIN variations v ON v.id=vld.variation_id WHERE v.product_id=products.id) < alert_quantity')
                ->count(),
        ];

        return Inertia::render('Products/Index', [
            'kpis' => $kpis,
            'permissions' => [
                'create' => auth()->user()->can('product.create'),
                'update' => auth()->user()->can('product.update'),
                'delete' => auth()->user()->can('product.delete'),
                'view'   => auth()->user()->can('product.view'),
                'opening_stock' => auth()->user()->can('product.opening_stock'),
            ],
            'filterOptions' => [
                'categories' => Category::forDropdown($business_id, 'product'),
                'brands' => Brands::forDropdown($business_id),
                'units' => Unit::forDropdown($business_id),
            ],
        ]);
    }

    // ... resto Blade legacy preservado intacto ...
}
```

Novo endpoint REST JSON paginado pra tabela:

```php
public function listJson()
{
    $business_id = request()->session()->get('user.business_id');

    if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) {
        abort(403, 'Unauthorized action.');
    }

    $query = Product::where('products.business_id', $business_id)
        ->where('products.type', '!=', 'modifier')
        ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
        ->leftJoin('units', 'products.unit_id', '=', 'units.id')
        ->leftJoin('categories as c1', 'products.category_id', '=', 'c1.id')
        ->join('variations as v', 'v.product_id', '=', 'products.id')
        ->leftJoin('variation_location_details as vld', 'vld.variation_id', '=', 'v.id')
        ->whereNull('v.deleted_at')
        ->select([
            'products.id',
            'products.name',
            'products.sku',
            'products.type',
            'products.enable_stock',
            'products.is_inactive',
            'products.alert_quantity',
            'products.product_custom_field1', // officeimpresso_codigo (cliente Martinho)
            'brands.name as brand',
            'units.actual_name as unit',
            'c1.name as category',
            DB::raw('SUM(vld.qty_available) as current_stock'),
            DB::raw('MIN(v.sell_price_inc_tax) as min_price'),
            DB::raw('MAX(v.sell_price_inc_tax) as max_price'),
            DB::raw('MIN(v.dpp_inc_tax) as min_purchase_price'),
        ])
        ->groupBy('products.id');

    // Filtros — q (busca livre), type, category_id, brand_id, status, enable_stock
    $q = trim((string) request()->get('q', ''));
    if ($q !== '') {
        $query->where(function ($w) use ($q) {
            $w->where('products.name', 'like', "%{$q}%")
                ->orWhere('products.sku', 'like', "%{$q}%")
                ->orWhere('products.product_custom_field1', 'like', "%{$q}%")
                ->orWhereHas('variations', function ($qq) use ($q) {
                    $qq->where('sub_sku', 'like', "%{$q}%");
                });
        });
    }
    // ... (categoria, brand, type, status, enable_stock filtros — whitelisted)

    $per_page = (int) request()->get('per_page', 25);
    if (! in_array($per_page, [10, 25, 50, 100], true)) $per_page = 25;

    $paginated = $query->paginate($per_page);

    return response()->json([
        'data' => $paginated->items(),
        'meta' => [
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'from'         => $paginated->firstItem(),
            'to'           => $paginated->lastItem(),
        ],
    ]);
}
```

Rota:
```php
Route::get('/products/list-json', [ProductController::class, 'listJson'])->name('products.list-json');
```

### F3 — FRONTEND INCREMENTAL

- US-PROD-001 — `Pages/Products/Index.tsx` (lista + KPIs + filtros)
- US-PROD-002 — `Pages/Products/Create.tsx` + `Pages/Products/Edit.tsx` (form 3 sections)
- US-PROD-003 — `Pages/Products/Show.tsx` (detalhe 4 KPIs + sections)
- US-PROD-004 — `Pages/Products/StockHistory.tsx` (timeline cronológica)

### F4 — QA HARDENING

- Pest passa 100% (5+ casos: estrutura + multi-tenant cross-tenant + branch Inertia + listJson)
- Smoke `/products?type=single`, `/products/create`, `/products/{id}/edit`, `/products/{id}`, `/products/{id}/stock-history` em biz=1 (Wagner) — NUNCA biz=164 (Martinho cliente) ou biz=4 (Larissa) — ADR 0101
- Browser MCP screenshot resolução 1280×800
- Score audit cockpit-runbook modo B ≥ 70

### F5 — CUTOVER + SUNSET

1. Aviso prévio Wagner → Martinho/Lara (WhatsApp): "feature nova em produtos, vamos ativar contigo na próxima"
2. Habilitar flag opt-in pra biz=1 (canary 7d) + biz=164 (cliente piloto canary 30d)
3. Monitorar `storage/logs/laravel.log` ALERT entries
4. Após 30d sem incidente → deletar `resources/views/product/{index,create,edit,show,stock_history}.blade.php` + branch Blade fallback em `ProductController::{index,create,edit,show,productStockHistory}` + JS legacy `public/js/product.js`

## 4. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- `Product::where('products.business_id', $business_id)` em TODA query — global scope ainda não aplicado em UltimatePOS legacy, PRESERVAR padrão manual
- `listJson()` escopa por session `user.business_id`
- Pest test cross-tenant biz=1 vs biz=99 OBRIGATÓRIO — biz=164 NUNCA usado em test (cliente real, ADR 0101)
- PII redact em logs Pest (nomes Test Product XXX, SKU SKU-TEST-XXX)

## 5. Permissões (Spatie)

- `product.view` — listar + detalhes
- `product.create` — criar
- `product.update` — editar
- `product.delete` — excluir
- `product.opening_stock` — gerenciar estoque inicial
- Sem permissão → 403 + Blade fallback (canary gradual)

## 6. UX targets

- p95 first-paint Index < 1500ms
- p95 fetch list-json < 500ms (25 linhas + KPIs)
- Cabe em 1280px sem scroll-x (Lara monitor 1280px)
- Drawer detail < 300ms após click
- Cadastro completo (3 sections) < 4 telas scroll (vs 4+ no Blade)
- Atalhos: `/` foca busca, `Esc` limpa busca, Cmd/Ctrl+S salva form, Esc cancela form

## 7. Tipografia + tokens (canon Cockpit V2 — ADR 0110)

- h1 `text-2xl font-semibold tracking-tight`
- subtitle `text-sm text-muted-foreground`
- KPI value `text-4xl font-semibold tabular-nums`
- pill `text-xs font-medium rounded-full px-3.5 py-1.5`
- badge `text-[11px] font-medium rounded-full px-2.5 py-0.5`
- Cores semânticas: rose (alerta/atrasado) / emerald (ativo/positivo) / amber (em alerta estoque) / blue (info ativo)
- ❌ NUNCA cor crua `bg-(red|green|blue)-N` — sempre tokens shadcn

## 8. Endpoints

| Método | Rota | Retorna |
|---|---|---|
| GET | `/products` (X-Inertia) | Inertia render Products/Index |
| GET | `/products` (ajax+X-Requested-With) | DataTables legacy (preservado) |
| GET | `/products` (sem nada) | Blade legacy preservado |
| GET | `/products/list-json?q=&page=&per_page=&type=&category_id=&brand_id=&status=&enable_stock=&sort=&dir=` | `{ data: ProductRow[], meta: {...} }` |
| GET | `/products/create` (X-Inertia) | Inertia render Products/Create |
| POST | `/products` | redirect (legacy preservado) |
| GET | `/products/{id}/edit` (X-Inertia) | Inertia render Products/Edit |
| PUT | `/products/{id}` | redirect (legacy preservado) |
| GET | `/products/{id}` (X-Inertia) | Inertia render Products/Show |
| GET | `/products/stock-history/{id}` (X-Inertia) | Inertia render Products/StockHistory |
| GET | `/products/stock-history/{id}` (ajax variation) | Blade legacy parcial preservado |

## 9. Tests anti-regressão

- [tests/Feature/Products/ProductsInertiaTest.php](../../../tests/Feature/Products/ProductsInertiaTest.php) — 10+ testes estruturais + cross-tenant + branch Inertia + listJson

## 10. Pegadinhas (gotchas)

- **`products.type` whitelist** — único valor permitido = `single|variable|combo`. Martinho usa só `single` (peças/caçambas simples). NÃO mostrar UI complexa de variations pro MVP.
- **`product_custom_field1`** — Martinho usa como `officeimpresso_codigo` (código legacy WR Comercial Delphi). Coluna preservada no listJson + searchable.
- **`v.deleted_at`** — variations soft-delete. SEMPRE adicionar `whereNull('v.deleted_at')` nos joins (ou produto deletado aparece).
- **GROUP BY `products.id`** — agregação `SUM(vld.qty_available)` precisa group. Se esquecer = sql_mode strict quebra em prod MySQL 8.
- **`format_date()` shift +3h** em campo "agora" (ADR 0066) — manter helper `format_now_local()` em backend. NÃO usar em frontend; frontend recebe `transaction_date` já formatado.
- **Identificador MySQL >64 chars** — índice composto em products precisa nome explícito (ex: `idx_products_biz_type_sku`).
- **Hook `block-mwart-violation.ps1`** vai BLOQUEAR Edit/Write em `Pages/Products/*.tsx` SEM esse RUNBOOK existir. Hook validado.
- **`vendor/` junction Windows** (ADR proibição) — NUNCA `git worktree remove --force` sem remover junction de `vendor/` antes.

## 11. Rollback

```bash
# Rollback rápido: revert PR + deploy. Blade legacy fica intacto, então rollback é zero-risk.
# Em caso de emergência: editar dual-mode pra ignorar header X-Inertia temporariamente
# (ProductController index/create/edit/show/productStockHistory)
ssh hostinger 'cd ~/oimpresso.com && git revert <SHA> && php artisan view:clear'
# Tempo total rollback: < 2min
```

## Refs

- Pages: [resources/js/Pages/Products/](../../../resources/js/Pages/Products/)
- Controller: [app/Http/Controllers/ProductController.php](../../../app/Http/Controllers/ProductController.php)
- Tests: [tests/Feature/Products/ProductsInertiaTest.php](../../../tests/Feature/Products/ProductsInertiaTest.php)
- Pattern referência (mais recente): [Crm/Contacts/Index.tsx](../../../resources/js/Pages/Crm/Contacts/Index.tsx) (PR de hoje 2026-05-14)
- Gold-standard: [Sells/Index.tsx](../../../resources/js/Pages/Sells/Index.tsx) (PR #261)
