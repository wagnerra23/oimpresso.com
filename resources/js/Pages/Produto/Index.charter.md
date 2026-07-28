---
id: resources-js-pages-produto-index-charter
page: /products
component: resources/js/Pages/Produto/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
bundle_source: produtos-page.jsx
owner: wagner
status: live
last_validated: "2026-07-12"
parent_module: Produto
related_adrs: [110, 107, 93, 104, 149]
related_us: [US-PROD-020, US-PROD-023]
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md
related_visual_comparison: memory/requisitos/Produto/_telas/produto-index-visual-comparison.md
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
- Multi-tenant: `App\Product` filtrado por `business_id` — ⚠️ por `where` **explícito** em cada builder, **não** por global scope (medido 2026-07-26: `addGlobalScope` = 0 em `app/Product.php`)
- Permission gate: `product.view` **ou** `product.create` (`ProductController@index:66-68`)
  _(a linha anterior citava `product.view_own`; varredura contada em `app/`+`Modules/`+`routes/`+`resources/` (excl. charters): **0** ocorrências — a permissão não existe no projeto.)_

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

> 🔧 **Reconciliação factual 2026-07-26** (agent `sdd-from-source`, Fase 2.6 — só fato, nenhuma
> intenção alterada). As 4 linhas abaixo afirmavam rotas e mecanismos que **não existem**; cada
> correção traz a evidência ao lado. Intenção (Goals/Non-Goals/Anti-hooks) segue sendo do [W].

- Endpoint `GET /products` — `ProductController@index` (`:64-374`) serve **três** consumidores no mesmo método: `request()->ajax()` → DataTables server-side (`:73-312`), header `X-Inertia` → `Produto/Index` (`:342-359`), nenhum dos dois → `view('product.index')` (`:361`).
  _(era `GET /produto` — rota inexistente; a rota real é `/products`, `routes/web.php:460` `Route::resource`.)_
- Props deferidas (`Inertia::defer`): `kpis` (`:349`) · `rows` (`:350`) · `categorias` (`:351`). **A lista React não pagina** — `buildProdutoIndexRows` corta em `limit(200)` (`:447`).
  _(era "retorna lista paginada" — não retorna; ver `UC-PIDX-01`.)_
- ~~Endpoint `GET /produto/{id}/sheet-data`~~ — **não existe.** Varredura contada em `routes/`+`app/`: `sheet-data` só existe para `/sells/{id}` (`routes/web.php:485`); **0** ocorrências para produto. O card navega pra ficha (`Index.tsx:423`), não abre drawer.
- Multi-tenant: `where('business_id', …)` **explícito** nos 3 builders (`:382`, `:427`, `:479`).
  _(era "global scope `business_id` em `App\Product`" — **não há global scope**: `addGlobalScope` = **0** ocorrências em `app/Product.php`. A contenção é manual e repetida; ver `UC-PIDX-04`.)_
- Permission gate: `can('product.view')` **OU** `can('product.create')` (`:66-68`, 403).

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

> 🔧 **Reconciliação factual 2026-07-26:** o arquivo prometido abaixo (`IndexCharterTest.php`)
> **nunca existiu** (`ls tests/Feature/Produto/` — 21 arquivos, nenhum com esse nome). O que existe
> é `Wave2Index{Inertia,Baseline}Test.php`, que fazem **string-match no fonte**
> (`expect($source)->toContain(...)`) — passariam com o isolamento quebrado e o preço vazando — e
> que **não rodam na lane de PR** — a lane `Estoque · MySQL` usa allowlist explícita de arquivos e
> nenhum `Wave2Index*` está nela. ⚠️ Eles **rodam** no fullsuite nightly do CT 100 (`phpunit.xml`
> inclui `./tests/Feature` recursivamente + `scripts/tests/shards-plan.mjs` enumera
> `tests/Feature/Produto` como shard) — a redação anterior dizia "não rodam em lane nenhuma", o que
> era falso (LC-08: conclusão *system-scoped* tirada de varredura *file-scoped* em `.github/`).
> A lista de intenção fica **preservada** (é do [W]); marcado ao lado
> o que passou a ter defesa real.
>
> **Defesa real desde 2026-07-26** — [`tests/Feature/Produto/ProdutoIndexContratoTest.php`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php)
> (na lane, failing-first): `UC-PIDX-01` alcance · `UC-PIDX-02` busca server-side · `UC-PIDX-03`
> preço/custo por permissão · `UC-PIDX-04` isolamento + KPIs · `UC-PIDX-05` GET não escreve ·
> `UC-PIDX-06` filtro de inativos. Contrato em [`Index.casos.md`](Index.casos.md).

```php
// ⚠️ ARQUIVO INEXISTENTE — lista de INTENÇÃO do [W], mantida como tal.
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

- Material visual: [`prototipo-ui/cowork/produtos-page.jsx`](../../../../prototipo-ui/cowork/produtos-page.jsx) — o mesmo que o frontmatter declara em `bundle_source`
  _(a linha anterior apontava `ui_kits/cowork-2026-05-09/prod-page.jsx`; caminho **inexistente** — `ls` → No such file. Corrigido 2026-07-26.)_
- Contrato de casos: [`Index.casos.md`](Index.casos.md) — `UC-PIDX-01..06` (`CU-PROD-15` do SDD)
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

| 2026-07-26 | [CC] | **Reconciliação factual** (agent `sdd-from-source`, [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) Fase 2.6) — corrigidos 5 FATOS verificáveis, com a evidência ao lado de cada um: rota `/produto`→`/products` · `sheet-data` inexistente · `product.view_own` inexistente · "global scope" inexistente (`addGlobalScope`=0) · material visual em caminho podre. §Pest GUARD anotado (o `IndexCharterTest.php` nunca existiu; a defesa real agora é `ProdutoIndexContratoTest`). **Nada de intenção foi tocado** — Goals/Non-Goals/Anti-hooks seguem do [W]. Trio fechado com [`Index.casos.md`](Index.casos.md). |

> _Promovido draft→live em 2026-07-12 por `charter-promote-signal.mjs` — sinal: route-hits:16._
>
> ⚠️ **Divergência aberta (decisão [W], não reconciliada):** este charter está `status: live`, mas o
> [SDD §1.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) e o
> [SPEC](../../../../memory/requisitos/Produto/SPEC.md) afirmam *"as 8 telas React existem, nenhuma é
> `live`"*, e a US-PROD-023 ainda pede "finalizar + promover". **Hipótese** (não afirmo): `/products`
> serve os dois branches (Blade e Inertia) na **mesma rota**, então `route-hits:16` pode ter medido
> tráfego do **Blade** — se for isso, afeta as 8 telas do módulo e qualquer tela em branch dual
> MWART. Verificar antes de tratar o `live` como fato. Registrado em `Index.casos.md` §Divergências D-4.
