---
page: /produto/unificado
component: resources/js/Pages/Produto/Unificado/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-09"
parent_module: Produto
related_adrs: [110, 107, 93, 94]
tier: A
charter_version: 1
---

# Page Charter — /produto/unificado (DRAFT)

> **Status:** draft criado em batch 2026-05-09 a partir de [`produto-app.jsx`](../../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/produto-app.jsx) (60 KB — material mais robusto do canon). Wagner aprova **Non-Goals + Automation Anti-hooks** ANTES de virar `status: live`.
>
> ⚠️ **Backend canon:** `app/Http/Controllers/ProductController.php` (UPOS herdado). Produto = `App\Product` + `App\Variation` + `App\Brands` + `App\Category` direto em `app/`, **NÃO** em `Modules\Produto\` ([LICOES_F3_FINANCEIRO_REJEITADO.md](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) AP-1). BOM = `Modules\Manufacturing\Entities\MfgRecipe`. Tabelas de preço = `App\SellingPriceGroup`. Histórico = `App\TransactionSellLine`.

---

## Mission

Catálogo unificado: numa tela única alterna entre 5 sub-views (Produtos / Categorias / Insumos·BOM / Tabelas de preço / Histórico de uso) com KPI strip persistente + drawer detalhe — substitui múltiplas telas Blade UPOS dispersas (`/products`, `/categories`, `/manufacturing/recipes`, `/selling-price-group`, etc) numa visão Cockpit V2.

---

## Goals — Features (faz)

- AppShellV2 + sidebar Catálogo expandido com 5 sub-items (Produtos default)
- KPI strip persistente entre sub-views (5 KPIs: Catálogo ativo / Populares / Saídas 30d / Margem média / Sem giro)
- 5 sub-views state-driven via querystring `?tela=produtos|categorias|insumos|tabelas|historico`:
  - **Produtos** — segmented filter (todos/ativos/inativos/lowstock) + busca + view toggle (table/grid) + densidade (compact/comfortable/spacious)
  - **Categorias** — tree view 1 nível com count produtos + flag inativo
  - **Insumos · BOM** — listagem produtos `not_for_selling=1` + custo + estoque + fornecedor
  - **Tabelas de preço** — `App\SellingPriceGroup` com multiplicador (decisão pendente — ver Non-Goals)
  - **Histórico de uso** — `App\TransactionSellLine` últimos 30d com OS/cliente/qty/valor
- Click row em "Produtos" → drawer 480px com:
  - Header: SKU + nome + categoria + status active/inactive
  - 4 KPIs: preço / custo / margem / saídas 30d
  - Section "BOM" (se existir `MfgRecipe`): ingredientes + qty
  - Section "Histórico" últimas 5 vendas
- Densidade configurável (compact 32px / comfortable 44px / spacious 56px) persistida em `oimpresso.produto.densidade`
- Multi-tenant: queries com `where('business_id', session('user.business_id'))` em todos models
- Permission gate: `product.view`, `product.view_own`, `product.create`, `product.update` (Spatie UPOS canon)

---

## Non-Goals — Features (NÃO faz)

> ⚠️ Anti-alucinação. Wagner aprova esta lista.

- ❌ CRUD inline (criar/editar via rotas dedicadas Blade `/products/create`, `/products/{id}/edit`)
- ❌ Bulk actions (deletar/ativar múltiplos) — backlog
- ❌ Stock management (entradas/saídas — vai pra `/stocks` Blade legacy)
- ❌ Importar CSV (rota Blade `/products/import`)
- ❌ Print etiqueta de barras (rota Blade `/products/{id}/print-label`)
- ❌ Variações inline no drawer (vai pra `/products/{id}/variations` Blade)
- ❌ Multiplicador `App\SellingPriceGroup` editável aqui — **decisão schema pendente**: (a) adicionar coluna `multiplier` em `selling_price_groups`, ou (b) calcular via `VariationGroupPrice` e dropar conceito multiplicador; ADR `arq/NNNN-selling-price-multiplier.md` antes de F3
- ❌ Auto-aplicar margem mínima em produto novo (vai vir do template do business)
- ❌ Recalcular custo médio em tempo real ao abrir drawer (usa `default_purchase_price` cached)
- ❌ Forecast de demanda baseado em histórico (escopo Modules/Inventory futuro)
- ❌ Preview de imagem do produto no drawer (UPOS guarda em `media` table — feature backlog)
- ❌ Trigger sync com fornecedor externo (cron separado)

---

## UX Targets

- p95 first-paint < 1500ms (Produtos sub-view com 100 itens)
- 0 erros JS console
- Cabe em monitor 1280px (Larissa balcão)
- Sub-view switching `<200ms` (Inertia partial reload)
- Drawer abre `<300ms` após click linha
- Densidade persiste reload (localStorage)
- Tipografia canon ADR 0110: h1 22-24px, KPI value 28px, table row 13px
- Cores semânticas: emerald (ativo/popular), amber (warning baixo estoque), rose (inativo/sem giro), stone (neutro)

---

## UX Anti-patterns

- ❌ 5 telas separadas em URLs diferentes (canon = sub-views state-driven via `?tela=`)
- ❌ Modal/Dialog pra detalhe produto (canon = `<Sheet>` lateral)
- ❌ Cor crua `bg-(red|green|orange)-N`
- ❌ KPI custom inline (canon = `@/Components/shared/KpiCard`)
- ❌ Avatar circular emoji-style em produto (canon = letra/SKU `rounded-md`)
- ❌ `font-bold` em h1 (canon = `font-semibold`)
- ❌ `sessionStorage` (canon = `localStorage` prefix `oimpresso.produto.*`)

---

## Automation Hooks

- Endpoint `GET /produto/unificado?tela=<sub>` — `ProdutoUnificadoController::index()` agrega:
  - `Product::where('business_id', $bid)->active()->count()` (KPI catálogo ativo)
  - `TransactionSellLine` join `transactions` últimos 30d sum quantity (KPI saídas)
  - Sub-view específica conforme `tela`
- Endpoint `GET /produto/{id}/sheet-data` — drawer detail com Variation default + BOM (`MfgRecipe`) + 5 últimas vendas
- Multi-tenant: `App\Product`, `App\Variation`, `App\Brands`, `App\SellingPriceGroup` todos com `business_id` (UPOS canon)
- Permission middleware no `__construct` (`can:product.view`)
- Cache: KPI agregations cacheadas por job diário (chave `produto:kpis:{business_id}`)

---

## Automation Anti-hooks

> ⚠️ Wagner aprova esta lista.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara webhook fornecedor
- ❌ Não escreve no banco no render (read-only puro)
- ❌ Não roda recálculo custo médio na request (cron diário faz)
- ❌ Não chama Brain B/Sonnet
- ❌ Não acessa produto de outro `business_id` (multi-tenant Tier 0)
- ❌ Não dispara `MfgRecipe` recompute em sub-view "Insumos"
- ❌ Não cria variação automática ao abrir drawer
- ❌ Não persiste imagem upload nesta request (upload vai por rota dedicada)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// tests/Feature/Produto/UnificadoCharterTest.php

it('renders under 1500ms p95 with 100 products')
it('switches sub-view via querystring without full reload')
it('does not emit emails on render')
it('does not dispatch jobs on render')
it('does not mutate state on GET')
it('isolates products by business_id across all 5 sub-views')
it('returns 404 for cross-tenant product access via sheet-data')
it('renders at 1280px without horizontal scroll')
it('persists densidade preference in localStorage')
it('uses localStorage prefix oimpresso.produto.* (never sessionStorage)')
it('does not call MfgRecipe recompute on insumos sub-view')
it('does not access App\\Product without ->where(business_id)')
```

---

## Comparáveis canônicos (`mwart-comparative` V4)

- **Linear** (lista densa + atalhos) — referência principal pra Produtos sub-view
- **Stripe Products** (catálogo com sub-views unificadas) — referência pra arquitetura sub-tela
- **Notion database** (apenas pra view toggle table/grid — visual rejeitado pelo resto)
- **Excluir:** Shopify Admin (overhead e-commerce), POS-Larissa-style (vai pra `/sale-pos/create` separado)

---

## Refs

- Material visual: [`ui_kits/cowork-2026-05-09/produto-app.jsx`](../../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/produto-app.jsx) (60 KB) + [`produto-data.jsx`](../../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/produto-data.jsx) + [`produto-icons.jsx`](../../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/produto-icons.jsx) + [`Produto Unificado.html`](../../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/Produto%20Unificado.html)
- Screenshot evidência: [`screenshot-06-produto.png`](../../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/screenshot-06-produto.png) (95 KB)
- Canon visual: [ADR ui/0012](../../../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)
- [ADR 0110 — Cockpit Pattern V2](../../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0107 — Visual gate F1.5](../../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight obrigatório antes de F3 (Models reais UPOS, NÃO inventar `Modules\Produto`)
- Backend candidate: `prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php` no zip Cowork — referência **com TODOs** (NÃO copiar literal — investigou Models reais mas sem `__construct` middleware nem permissions)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | [CL] | Charter draft criado em batch. Path canon `Pages/Produto/Unificado/Index.tsx` segue padrão `Pages/Financeiro/Unificado/Index.tsx` (subdir). Backend em `app/Http/Controllers/` (UPOS canon — não em Modules). **Decisões pendentes pra Wagner:** (1) `SellingPriceGroup.multiplier` schema (a vs b) precisa ADR; (2) confirmar `MfgRecipe` namespace em `Modules\Manufacturing\Entities\` (controller candidato Cowork admite "TODO confirmar"); (3) cache strategy KPIs (job diário vs `Cache::remember`). **Aprovação pendente** em Non-Goals + Anti-hooks pra `status: live`. |
