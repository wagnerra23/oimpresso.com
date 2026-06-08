---
slug: 2026-05-15-wave2-b4-produto
title: "Wave 2 B4 Produto — migração MWART 7 telas (Agent W2-C paralelo)"
type: session-log
authority: canonical
session_date: 2026-05-15
quarter: 2026-Q2
related:
  - '0104'
  - '0149'
  - '0093'
  - '0114'
  - '0107'
pii: false
agent: W2-C
---

# Wave 2 B4 Produto — migração MWART 7 telas

> **Agent W2-C paralelo · Wave 2 B4 Produto · 2026-05-15**
> Migração MWART canônica ([ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md)) + screen-pattern reuse blueprint Cowork ([ADR 0149](../decisions/0149-mwart-screen-pattern-reuse-cowork.md)).
> Paralelo a W1-A Sells · W1-B Cliente · W2-D Stock/Purchase · W3-E Repair (áreas isoladas, zero overlap).

## Sumário executivo

7 telas Blade legacy do bucket **B4 Produto** migradas em paralelo isolado, reusando blueprint visual `prototipo-ui/prototipos/produto-cockpit/` (família AppShellV2 + tokens OKLCH Cowork + header pattern). 4 telas em pattern reuse direto (Index/Create/Show/Edit) + 3 com divergência declarada justificada (SellingPrices/BulkEdit/StockHistory).

| # | Tela | Pages tsx | Charter | RUNBOOK | Visual Comparison | Controller branch | Pest |
|---|---|---|---|---|---|---|---|
| 1 | Produto/Index | ✅ | ✅ atualizado v2 | ✅ | ✅ | ✅ index() | ✅ 16 tests |
| 2 | Produto/Create | ✅ | ✅ | ✅ | ✅ | ✅ create() | ✅ 17 tests |
| 3 | Produto/Show | ✅ | ✅ | ✅ | ✅ | ✅ show($id) | ✅ 14 tests |
| 4 | Produto/Edit | ✅ | ✅ | ✅ | ✅ | ✅ edit($id) | ✅ 13 tests |
| 5 | Produto/SellingPrices | ✅ | ✅ + divergência | ✅ | ✅ | ✅ addSellingPrices() | ✅ 13 tests |
| 6 | Produto/BulkEdit | ✅ | ✅ + divergência | ✅ | ✅ | ✅ bulkEdit() | ✅ 13 tests |
| 7 | Produto/StockHistory | ✅ | ✅ + divergência | ✅ | ✅ | ✅ productStockHistory() | ✅ 14 tests |

**Pest local:** `113/113 passed (147 assertions, 0.55s)`.

## Pattern reuse blueprint (ADR 0149)

Blueprint visual canônico: `prototipo-ui/prototipos/produto-cockpit/` aprovado em Wave 2 B4 Produto 2026-05-15.

```yaml
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/produto-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente Wagner)"
  derived_screens: [Index, Create, Show, Edit, SellingPrices, BulkEdit, StockHistory]
```

**Divergências declaradas (3 telas):**

- **SellingPrices:** matriz `variation × price_group` é tabela densa específica — não cabe no pattern drawer Cockpit. Mantém família AppShellV2 + tokens + header pattern; diverge no conteúdo central.
- **BulkEdit:** datatable multi-row edit é pattern distinto de Index Cockpit. ADR 0149 §"Casos que NÃO se qualificam" admite.
- **StockHistory:** timeline movimento por variação + filtros location/period — pattern cronológico distinto de list cockpit.

Cada charter declara `divergence_from_blueprint:` explicitamente.

## Tier 0 IRREVOGÁVEIS aplicados

- ✅ **business_id global scope** — toda query `Product::where('business_id', $business_id)`. Builders Inertia recebem `int $businessId` explícito como argumento (não dependem de `session()` dentro de closure deferred — pattern Tier 0 multi-tenant ADR 0093).
- ✅ **PT-BR** em todo UI texto, labels, breadcrumbs, comentários.
- ✅ **Inertia::defer()** em props caras (KPIs, rows, categorias, variations, rackDetails) — Tier 0 desde 2026-05-15 (RUNBOOK-inertia-defer-pattern).
- ✅ **TypeScript estrito sem `any`** — interfaces nomeadas (`ProdutoIndexPageProps`, etc), Pest GUARD bloqueia `: any` regex.
- ✅ **localStorage prefixo `oimpresso.produto.`** — nunca `sessionStorage`.
- ✅ **Cores semânticas** tokens OKLCH Cowork — `emerald`/`stone`/`rose`/`amber`. Pest GUARD bloqueia `bg-{gray|indigo|purple|pink|yellow|red|green}-N` crus.
- ✅ **Coexistência opt-in Blade legacy** — branch `if (request()->header('X-Inertia'))` antes de `return view('product.X')`. Sem X-Inertia, render Blade intacto.
- ✅ **NÃO modificação** do método `store()`/`update()` PHP (out of scope).
- ✅ **NÃO modificação** tabela `products` core UltimatePOS.

## Coexistência com `Produto/Unificado`

`Pages/Produto/Unificado/Index.tsx` (rota `/produto/unificado` separada) PERMANECE INTOCADO. Esta migração endereça canon UPOS `/products`. Decisão futura (Wagner): manter ambos, OR consolidar.

## Anti-padrões evitados (LICOES_F3_FINANCEIRO_REJEITADO)

- ❌ `withoutGlobalScopes` sem comentário SUPERADMIN — não usado
- ❌ Middleware `tenant` fantasma — não usado; preserva canon UPOS `['web','SetSessionData','auth',...]`
- ❌ Models inventados — `App\Product`, `App\Category`, `App\Brands` reais
- ❌ Services inventados — Reusa `ProductUtil` + `ModuleUtil` existentes
- ❌ Cor crua — só tokens semânticos
- ❌ `sessionStorage` — só `localStorage` com prefixo

## ProductController.php — branches Inertia adicionados

Imports adicionados:
```php
use Inertia\Inertia;
```

Branches dual `if (request()->header('X-Inertia'))` adicionados em:
- `index()` (linha ~63) + 3 builders novos: `buildProdutoIndexKpis()`, `buildProdutoIndexRows()`, `buildProdutoIndexCategorias()`
- `create()` (linha ~358)
- `show($id)` (linha ~592)
- `edit($id)` (linha ~610)
- `addSellingPrices($id)` (linha ~1697)
- `bulkEdit(Request $request)` (linha ~2035)
- `productStockHistory($id)` (linha ~2292)

**Todos** preservam `return view('product.X')` Blade legacy abaixo do branch — rollback = remover header `X-Inertia` no client.

## Arquivos criados/editados (43 arquivos)

```
resources/js/Pages/Produto/
  Index.charter.md           (atualizado v1 → v2 com mwart_pattern_reuse)
  Index.tsx                  (recriado: blueprint Cowork canon)
  Create.charter.md          (novo)
  Create.tsx                 (novo)
  Show.charter.md            (novo)
  Show.tsx                   (novo)
  Edit.charter.md            (novo)
  Edit.tsx                   (novo)
  SellingPrices.charter.md   (novo)
  SellingPrices.tsx          (novo)
  BulkEdit.charter.md        (novo)
  BulkEdit.tsx               (novo)
  StockHistory.charter.md    (novo)
  StockHistory.tsx           (novo)
  Unificado/                 (NÃO TOCADO — preservado)

memory/requisitos/Inventory/
  RUNBOOK-produto-index.md
  RUNBOOK-produto-create.md
  RUNBOOK-produto-show.md
  RUNBOOK-produto-edit.md
  RUNBOOK-produto-selling-prices.md
  RUNBOOK-produto-bulk-edit.md
  RUNBOOK-produto-stock-history.md
  produto-index-visual-comparison.md
  produto-create-visual-comparison.md
  produto-show-visual-comparison.md
  produto-edit-visual-comparison.md
  produto-selling-prices-visual-comparison.md
  produto-bulk-edit-visual-comparison.md
  produto-stock-history-visual-comparison.md

tests/Feature/Produto/
  Wave2IndexBaselineTest.php          (F2 baseline)
  Wave2IndexInertiaTest.php           (F4 QA)
  Wave2CreateBaselineTest.php
  Wave2CreateInertiaTest.php
  Wave2ShowBaselineTest.php
  Wave2ShowInertiaTest.php
  Wave2EditBaselineTest.php
  Wave2EditInertiaTest.php
  Wave2SellingPricesBaselineTest.php
  Wave2SellingPricesInertiaTest.php
  Wave2BulkEditBaselineTest.php
  Wave2BulkEditInertiaTest.php
  Wave2StockHistoryBaselineTest.php
  Wave2StockHistoryInertiaTest.php

app/Http/Controllers/
  ProductController.php               (editado: +Inertia import +7 branches +3 builders)
```

## Pest local — prova execução

```
Tests:    113 passed (147 assertions)
Duration: 0.55s
Random Order Seed: 1778886178
```

Helper `repo_path()` adicionado em cada test pra não depender de `base_path()` (que exige Application booted; tests rodam fora do contexto Laravel quando direcionados a paths absolutos da worktree).

## Gaps catalogados pra Wave 3+

| Gap | Impacto | Tela |
|---|---|---|
| Drawer lateral pattern Cowork (clicar card → abre drawer) | UX melhor | Index |
| Image upload preview cliente-side | UX melhor | Create/Edit |
| Variation builder dinâmico inline | Variable products | Create/Edit |
| Combo composition picker | Combo products | Create/Edit |
| Hero KPIs (Estoque/Custo/Preço/Vendas-mês) dinâmicos | UX rico Show | Show |
| Timeline movimento JSON endpoint (não HTML partial) | StockHistory rico | StockHistory |
| Bulk apply (mesma price em N variations) | SellingPrices produtividade | SellingPrices |
| Confirmation modal antes submit bulk | Anti-erro destrutivo | BulkEdit |

## F5 Cutover plan (parent consolida)

1. Branch dual mantida sem feature flag — ativação por header `X-Inertia` no client (default `useV2Produto=false` em pos_settings — Wave 3 ativa).
2. Smoke biz=1 Wagner 7 dias canary antes de habilitar `business_id=4` (ROTA LIVRE Larissa).
3. Backup tabelas críticas (`products`, `variations`, `categories`, `variation_group_prices`, `variation_location_details`).
4. Wagner SCREENSHOT approval pendente em SYNC_LOG pra cada uma das 7 telas (F1.5 ADR 0114).

## Trabalho paralelo coordenado

Áreas isoladas confirmadas — zero overlap:
- W2-C (este Agent) tocou: `Pages/Produto/`, `ProductController.php`, `memory/requisitos/Inventory/RUNBOOK-produto-*`, `produto-*-visual-comparison`, `tests/Feature/Produto/`
- W1-A Sells tocou: `Pages/Sells/`, `SellPosController`, `memory/requisitos/Sells/`
- W1-B Cliente tocou: `Pages/Contact/`, `ContactController`, `memory/requisitos/Contact/`
- W2-D Stock/Purchase: `Pages/Stock*/`, `Pages/Purchase/`, `Purchase*Controller`
- W3-E Repair: `Pages/Repair/`, `RepairController`

Zero git ops feitas no agent (parent consolida).

## Refs

- [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- [ADR 0149](../decisions/0149-mwart-screen-pattern-reuse-cowork.md) — Screen-pattern reuse blueprint Cowork
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — Loop Cowork ↔ Claude Code formalizado
- [ADR 0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — Visual comparison gate F3
- Blueprint Cowork: `prototipo-ui/prototipos/produto-cockpit/`
- LICOES_F3_FINANCEIRO_REJEITADO: `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Sessão criada · 7 telas migradas · 113 Pest passed · zero overlap com agents irmãos. |
