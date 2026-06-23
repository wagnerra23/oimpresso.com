---
page: /products
component: resources/js/Pages/Produto/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [110, 107, 93, 104, 149]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente — Wave 2 B4 Produto 2026-05-15)"
  derived_screens: [Create, Show, Edit, SellingPrices, BulkEdit, StockHistory]
  divergence_from_blueprint: "none — Index é o blueprint canon B4 Produto"
---

# Page Charter — /products (DRAFT — Wave 2 B4 Produto pattern reuse)

> **Status:** draft v2 atualizado 2026-05-15 (Wave 2 B4 Produto Agent W2-C). Blueprint visual definido: `prototipo-ui/cowork/produtos-page.jsx` (cockpit-page.jsx + Produtos Cockpit.html + visual-source.html). Pattern serve de blueprint pras 6 telas derivadas (Create/Show/Edit/SellingPrices/BulkEdit/StockHistory) — ADR 0149.
>
> Versão v1 (2026-05-09) referenciava `prod-page.jsx` (6.5 KB grid-first). v2 promove para blueprint cockpit completo. Wagner aprova **Non-Goals + Automation Anti-hooks** ANTES de virar `status: live`.
>
> ⚠️ **Relação com `/produto/unificado`:** essa Page é a versão SIMPLES (catálogo grid only). `/produto/unificado` é a versão DENSA (5 sub-views). Wagner decide na aprovação se mantém ambas ou unifica em uma só. Backend canon: `app/Http/Controllers/ProductController.php` (UPOS herdado). Produto = `App\Product` direto em `app/`, **NÃO** em `Modules\Produto\`.

---

## Mission

Catálogo simples de produtos em grid view com tabs de categoria, busca e cards visuais — variante "lite" do `/produto/unificado` pra usuários que querem visão rápida sem complexidade de BOM/tabelas/histórico.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb
- `<PageHeader>` shared: h1 "Produtos" + subtitle + botões "Importar" + "Novo produto" (rotas Blade legacy)
- 4 KPI cards: Total / Ativos / Categorias / Populares (popularity ≥ 70)
- Tabs de categoria com counter (Todos / impressos / comvis / embalagens / brindes / adesivos)
- Toggle "Mostrar inativos" (default: oculto)
- Search bar (busca em nome + SKU)
- Grid view de cards (NÃO tabela — diferença chave vs `/produto/unificado`):
  - Card: categoria badge + nome + SKU mono + preço/unidade + lead time + barra de popularidade
  - Card inativo: classe `inactive` + badge "inativo"
- Click card abre drawer (mesma DetailSheet do `/produto/unificado`)
- Multi-tenant: `App\Product` filtrado por `business_id` global scope
- Permission gate: `product.view`, `product.view_own`

---

## Non-Goals — Features (NÃO faz)

> ⚠️ Anti-alucinação. Wagner aprova.

- ❌ Sub-views (Insumos/BOM/Tabelas/Histórico) — vai pra `/produto/unificado`
- ❌ View toggle table/grid (sempre grid; tabela vai em `/produto/unificado`)
- ❌ Densidade compact/comfortable/spacious — sempre comfortable
- ❌ CRUD inline (criar/editar via rotas Blade dedicadas)
- ❌ Bulk actions
- ❌ Stock management
- ❌ Importar CSV inline (botão linka pra rota Blade)
- ❌ Filtros avançados (price range, brand, supplier) — backlog
- ❌ Edição de preço inline no card
- ❌ Categoria tree (apenas tabs flat 1 nível)

---

## UX Targets

- p95 first-paint < 1000ms (grid 50 cards)
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal (Larissa)
- Cards responsivos: 4 col / 3 col / 2 col / 1 col por breakpoint
- Drawer abre < 300ms
- Tabs categoria switching < 100ms (filter client-side)
- Tipografia canon: h1 22-24px, card title 14px, SKU mono 11px, price `tabular-nums`
- Cores semânticas: emerald (ativo+popular), stone (ativo neutro), rose (inativo)

---

## UX Anti-patterns

- ❌ Tabela ao invés de cards (canon dessa Page = grid only)
- ❌ Modal pra detalhe (canon = Sheet)
- ❌ Cor crua `bg-(green|red)-N`
- ❌ Card sem barra de popularidade (perde feedback visual)
- ❌ Card inativo idêntico a ativo (perda de hierarquia)
- ❌ `sessionStorage`

---

## Automation Hooks

- Endpoint `GET /produto` — `ProductController::index()` retorna lista paginada
- Endpoint `GET /produto/{id}/sheet-data` — compartilhado com `/produto/unificado`
- Multi-tenant: global scope `business_id` em `App\Product`
- Permission middleware: `can:product.view`

---

## Automation Anti-hooks

> ⚠️ Wagner aprova.

- ❌ Não dispara emails
- ❌ Não dispara SMS
- ❌ Não escreve no banco (read-only)
- ❌ Não roda jobs
- ❌ Não chama Brain B
- ❌ Não acessa produto de outro `business_id`
- ❌ Não dispara `MfgRecipe` recompute (Insumos não está nesta Page)
- ❌ Não persiste imagem upload

---

## Métricas vivas (Pest GUARD)

```php
// tests/Feature/Produto/IndexCharterTest.php

it('renders under 1000ms p95 with 50 products in grid')
it('does not emit emails on render')
it('does not dispatch jobs')
it('does not mutate state on GET')
it('isolates products by business_id')
it('returns 404 for cross-tenant product access')
it('renders at 1280px without horizontal scroll')
it('renders cards in 4-col grid at desktop')
it('does NOT show table view (grid only)')
it('uses localStorage prefix oimpresso.produto.* if any state persisted')
```

---

## Comparáveis canônicos (`mwart-comparative` V4)

- **Stripe Products listing** (cards visuais densos)
- **Linear projects grid** (card pattern)
- **Excluir:** Shopify (overhead), Vendor home pages

---

## Refs

- Material visual: `ui_kits/cowork-2026-05-09/prod-page.jsx` (6.5 KB)
- Canon visual: [ADR ui/0012](../../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)
- Charter relacionado: [`/produto/unificado`](Unificado/Index.charter.md) — versão densa
- [ADR 0110 — Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight obrigatório
- Backend: `app/Http/Controllers/ProductController.php` (UPOS canon)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | [CL] | Charter draft criado em batch. Path `Pages/Produto/Index.tsx` (flat). **Decisão pendente Wagner:** mantém Page simples + Page unificada (`Produto/Unificado/`) como duas opções, OU consolida em uma só? Material `prod-page.jsx` é mais "balcão rápido", `produto-app.jsx` é "admin completo". **Aprovação pendente** em Non-Goals + Anti-hooks pra `status: live`. |
