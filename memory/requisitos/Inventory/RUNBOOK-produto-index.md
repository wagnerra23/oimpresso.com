---
slug: inventory-runbook-produto-index
title: "Produto — Runbook da tela Lista de produtos (migração MWART)"
type: runbook
module: Inventory
status: active
date: 2026-05-15
---

# RUNBOOK — Lista de produtos (`/products`)

> **Tipo:** runbook de migração Blade → Inertia/React (MWART)
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Estado origem:** Blade legacy `view('product.index')` via [ProductController@index](../../../app/Http/Controllers/ProductController.php#L63) — DataTables jQuery + Yajra server-side processing
> **Estado alvo:** `Pages/Produto/Index.tsx` (Inertia v3 + React 19 + AppShellV2 + Cowork blueprint)
> **Persona alvo:** Larissa (ROTA LIVRE biz=4) — loja vestuário Gravatal/SC monitor 1280px. Estende-se a verticais ComunicacaoVisual + OficinaAuto.
> **Blueprint Cowork:** [`prototipo-ui/prototipos/produto-cockpit/`](../../../prototipo-ui/prototipos/produto-cockpit/) — pattern visual canon (sidebar 200 + header sticky + filter tabs + KPI strip + lista + drawer).

## 1. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota `/products` renderiza Page React | `curl -s -H "X-Inertia: true" /products` retorna `"component":"Produto/Index"` |
| Bundle Inertia builda | `grep "Pages/Produto/Index" public/build-inertia/manifest.json` |
| AppShellV2 envolvendo | `<div class="app-shell-v2">` ao redor da Page |
| Permissão `product.view` respeitada | Login sem `product.view` → 403 |
| Multi-tenant `business_id` scopado | Larissa biz=4 só vê seus produtos |
| Coexistência DataTables | header `X-Inertia` ausente → mantém Blade legacy |

## 2. Pré-condições

- [ ] Permissão `product.view` OR `product.create` atribuída
- [ ] Subscription ativa (quota não interfere em listagem)
- [ ] Skill `multi-tenant-patterns` Tier A carregada
- [ ] Skill `mwart-process` Tier A carregada
- [ ] Charter ao lado: `Pages/Produto/Index.charter.md` (status atualizado com `mwart_pattern_reuse`)

## 3. Passo-a-passo

### 3.1 Branch dual no controller `index()`

```php
public function index()
{
    if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) {
        abort(403, 'Unauthorized action.');
    }
    $business_id = request()->session()->get('user.business_id');

    if (request()->ajax()) {
        // ... mantém pipeline DataTables/Yajra existente intacto
    }

    if (request()->header('X-Inertia')) {
        return Inertia::render('Produto/Index', [
            'filters' => $this->buildIndexFilters(),
            'kpis' => Inertia::defer(fn () => $this->buildIndexKpis($business_id)),
            'rows' => Inertia::defer(fn () => $this->buildIndexRows($business_id)),
            'categorias' => Inertia::defer(fn () => $this->buildIndexCategorias($business_id)),
            'permissions' => [
                'create' => auth()->user()->can('product.create'),
                'update' => auth()->user()->can('product.update'),
                'delete' => auth()->user()->can('product.delete'),
                'opening_stock' => auth()->user()->can('product.opening_stock'),
            ],
        ]);
    }

    // restante Blade legacy intacto
    return view('product.index')->with(compact(/* ... */));
}
```

**Tier 0:** todo método `buildIndexX($business_id)` repassa `business_id` explícito como argumento — `session()` não é confiável dentro de closure deferred (jobs/queue). `Inertia::defer()` é Tier 0 desde 2026-05-15 (RUNBOOK-inertia-defer-pattern).

### 3.2 Page Inertia

`resources/js/Pages/Produto/Index.tsx` — Cowork blueprint `produto-cockpit/produto-cockpit-page.jsx`:

- Header sticky (h1 "Produtos" + subtitle + ações "Importar" + "Novo")
- KPI strip 4 cards: Total · Ativos · Categorias · Populares
- Tabs categoria flat (Todos / por categoria)
- Search bar SKU/nome
- Toggle "Mostrar inativos" (default oculto)
- Grid cards densidade Linear (`Deferred` com skeleton)
- Click card → drawer lateral (extensão futura — out of scope desta migração)
- Tabular-nums em valores monetários
- Cores semânticas: `emerald` (ativo+popular), `stone` (neutro), `rose` (inativo)

### 3.3 Coexistência com `/produto/unificado`

`Produto/Unificado/Index.tsx` permanece intocado (rota `produto.unificado.index` separada). Esta tela `/products` é o canon UPOS herdado; `unificado` é versão densa multi-tab. Wagner decide consolidar no futuro.

### 3.4 Anti-padrões a evitar (LICOES_F3_FINANCEIRO_REJEITADO)

- ❌ `withoutGlobalScopes` sem comentário SUPERADMIN
- ❌ middleware `tenant` (não existe — usar canon UPOS `['web','SetSessionData','auth','language','timezone','AdminSidebarMenu','CheckUserLogin']`)
- ❌ Models inventados — usar `App\Product` + `App\Category` + `App\Brands`
- ❌ Cor crua `bg-blue-500` — tokens semânticos
- ❌ `sessionStorage` — usar `localStorage` com prefixo `oimpresso.produto.`

## 4. Testes (F2 baseline + F4 inertia)

```bash
# F2 baseline (antes de mexer)
vendor/bin/pest tests/Feature/Produto/Wave2IndexBaselineTest.php

# F4 inertia (após F3)
vendor/bin/pest tests/Feature/Produto/Wave2IndexInertiaTest.php
```

Cobertura:
- baseline: rota responde 200 modo Blade + DataTables ajax retorna `data` array
- inertia: `assertInertia ->component('Produto/Index')->has('permissions')`
- multi-tenant: biz=1 vs biz=99 isolamento

## 5. Rollback

```bash
php artisan tinker --execute="\
  \App\Models\Business::where('id', 1)->update(['pos_settings' => json_encode(['useV2ProdutoIndex' => false])]);"
```

Ou remover header `X-Inertia` no client (testar via curl plain).

## 6. Cutover (F5)

Feature flag default OFF até validar em canary biz=1 7 dias. Cliente real biz=4 só após validação.

## 7. Refs

- Blueprint visual: [`produto-cockpit/produto-cockpit-page.jsx`](../../../prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx)
- Charter: [`resources/js/Pages/Produto/Index.charter.md`](../../../resources/js/Pages/Produto/Index.charter.md)
- Visual comparison: [`produto-index-visual-comparison.md`](produto-index-visual-comparison.md)
- ADR 0149 screen-pattern reuse
- LICOES_F3_FINANCEIRO_REJEITADO

## 8. Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Runbook criado em Wave 2 batch B4 Produto (Agent paralelo W2-C). |
